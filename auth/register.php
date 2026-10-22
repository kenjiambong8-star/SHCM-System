<?php
require_once __DIR__ . '/../includes/functions.php';
if (!empty($_SESSION['user_id'])) { header('Location: /pages/dashboard.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $user  = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!captcha_ok()) $errors[] = 'Wrong security answer.';
    if ($name === '' || strlen($name) > 100) $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $user)) $errors[] = 'Username: 4-30 letters, numbers or underscore.';
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass))
        $errors[] = 'Password needs 8+ characters with letters and numbers.';
    if ($pass !== $pass2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$user, $email]);
        if ($stmt->fetch()) $errors[] = 'Username or email is already registered.';
    }

    if (!$errors) {
               $count  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $role   = $count === 0 ? 'admin' : 'staff';
        $active = $count === 0 ? 1 : 0;
        $hash   = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $user, $hash, $role, $active]);
        log_activity('REGISTER', "New $role account (" . ($active ? 'active' : 'pending approval') . ")", $user);
        header('Location: /auth/login.php?registered=1');
        exit;
    }
}
$question = new_captcha();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create account - SHCM System</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="wrap">
  <?php include __DIR__ . '/../includes/auth_side.php'; ?>
  <div class="panel"><div class="box">
    <h2>Create account</h2>
    <p class="muted">Register to start recording transactions.</p>
    <?php if ($errors): ?>
      <div class="err"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="f"><label for="n">Full name</label><input id="n" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required></div>
      <div class="f"><label for="e">Email</label><input id="e" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
      <div class="f"><label for="u">Username</label><input id="u" name="username" value="<?= e($_POST['username'] ?? '') ?>" required></div>
      <div class="f"><label for="p">Password</label><input id="p" type="password" name="password" required></div>
      <div class="f"><label for="p2">Confirm password</label><input id="p2" type="password" name="password2" required></div>
      <div class="f"><label for="c">Security check: what is <?= e($question) ?>?</label>
        <div class="cap"><span><?= e($question) ?> = ?</span><input id="c" name="captcha" inputmode="numeric" required></div></div>
      <button class="btn" type="submit">Create account</button>
    </form>
    <p style="margin-top:24px;font-size:14px">Already registered? <a href="/auth/login.php">Sign in</a></p>
  </div></div>
</div>
</body>
</html>