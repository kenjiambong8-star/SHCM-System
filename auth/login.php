<?php
require_once __DIR__ . '/../includes/functions.php';
if (!empty($_SESSION['user_id'])) { header('Location: /pages/dashboard.php'); exit; }

const MAX_ATTEMPTS = 5;
const LOCK_MINUTES = 15;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!captcha_ok()) {
        $error = 'Wrong security answer. Try again.';
        log_activity('LOGIN_FAILED', 'Wrong CAPTCHA', $username ?: 'guest');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['locked_until'] && strtotime($user['locked_until']) <= time()) {
            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);
            $user['failed_attempts'] = 0;
            $user['locked_until'] = null;
        }

        if ($user && $user['locked_until']) {
            $error = 'Account locked. Try again in ' . LOCK_MINUTES . ' minutes.';
            log_activity('LOGIN_BLOCKED', 'Login attempt on locked account', $username);
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'Your account is waiting for administrator approval.';
                log_activity('LOGIN_BLOCKED', 'Inactive account', $username);
            } else {
                $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['fullname']    = $user['full_name'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['last_active'] = time();
                log_activity('LOGIN', 'User logged in');
                header('Location: /pages/dashboard.php');
                exit;
            }
        } else {
            if ($user) {
                $attempts = $user['failed_attempts'] + 1;
                $lock = $attempts >= MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null;
                $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?")
                    ->execute([$attempts, $lock, $user['id']]);
            }
            $error = 'Invalid username or password.';
            log_activity('LOGIN_FAILED', 'Wrong username or password', $username ?: 'guest');
        }
    }
}
$question = new_captcha();
?>
<!doctype html>
<html lang="en">
<head>
<link rel="icon" href="/assets/img/logo.png">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in - SHCM System</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="wrap">
  <?php include __DIR__ . '/../includes/auth_side.php'; ?>
  <div class="panel"><div class="box">
    <h2>Sign in</h2>
    <p class="muted">Enter your account details to open the system.</p>
    <?php if (isset($_GET['registered'])): ?><div class="ok">Account created. An administrator must approve it before you can sign in.</div><?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?><div class="err">You were signed out after 30 minutes of inactivity.</div><?php endif; ?>
    <?php if (isset($_GET['restored'])): ?><div class="ok">Database restored. Please sign in again.</div><?php endif; ?>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="f"><label for="u">Username</label><input id="u" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus></div>
      <div class="f"><label for="p">Password</label><input id="p" type="password" name="password" required></div>
      <div class="f"><label for="c">Security check: what is <?= e($question) ?>?</label>
        <div class="cap"><span><?= e($question) ?> = ?</span><input id="c" name="captcha" inputmode="numeric" required></div></div>
      <button class="btn" type="submit">Sign in</button>
    </form>
    <p class="muted" style="margin-top:14px">Too many failed attempts will lock your account for <?= LOCK_MINUTES ?> minutes.</p>
    <p style="margin-top:24px;font-size:14px">No account yet? <a href="/auth/register.php">Create account</a></p>
  </div></div>
</div>
</body>
</html>