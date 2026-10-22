<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'Users'; $active = 'users';

function password_problem($p) {
    return (strlen($p) < 8 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p))
        ? 'Password needs 8+ characters with letters and numbers.' : null;
}
function done($msg) { $_SESSION['flash'] = ['ok', $msg]; header('Location: /pages/users.php'); exit; }

$errors = []; $myId = (int)$_SESSION['user_id'];
$new = ['full_name' => '', 'email' => '', 'username' => '', 'role' => 'staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $new = ['full_name' => trim($_POST['full_name'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
                'username' => trim($_POST['username'] ?? ''), 'role' => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff'];
        $pass = $_POST['password'] ?? '';
        if ($new['full_name'] === '' || strlen($new['full_name']) > 100) $errors[] = 'Enter a full name.';
        if (!filter_var($new['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $new['username'])) $errors[] = 'Username: 4-30 letters, numbers or underscore.';
        if ($p = password_problem($pass)) $errors[] = $p;
        if (!$errors) {
            $d = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $d->execute([$new['username'], $new['email']]);
            if ($d->fetch()) $errors[] = 'Username or email is already registered.';
        }
        if (!$errors) {
            $pdo->prepare("INSERT INTO users (full_name, email, username, password_hash, role, is_active) VALUES (?,?,?,?,?,1)")
                ->execute([$new['full_name'], $new['email'], $new['username'], password_hash($pass, PASSWORD_DEFAULT), $new['role']]);
            log_activity('ADD_USER', 'Created ' . $new['role'] . ' account ' . $new['username']);
            done('User created.');
        }
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $q = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $q->execute([$id]);
        $t = $q->fetch();
        if (!$t) { $errors[] = 'User not found.'; }
        elseif ($act === 'unlock') {
            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$id]);
            log_activity('UNLOCK_USER', 'Unlocked ' . $t['username']); done('Account unlocked.');
        } elseif ($act === 'resetpw') {
            $pw = $_POST['new_password'] ?? '';
            if ($p = password_problem($pw)) { $errors[] = $p; }
            else {
                $pdo->prepare("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?")
                    ->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
                log_activity('RESET_PASSWORD', 'Reset password of ' . $t['username']); done('Password reset.');
            }
        } elseif (in_array($act, ['toggle', 'role'], true) && $id === $myId) {
            $errors[] = 'You cannot change your own account here.';
        } elseif ($act === 'toggle') {
            $v = $t['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$v, $id]);
            log_activity($v ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', ($v ? 'Approved ' : 'Deactivated ') . $t['username']);
            done($v ? 'User approved.' : 'User deactivated.');
        } elseif ($act === 'role') {
            $r = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$r, $id]);
            log_activity('CHANGE_ROLE', $t['username'] . ' is now ' . $r); done('Role updated.');
        }
    }
}
$users = $pdo->query("SELECT * FROM users ORDER BY created_at")->fetchAll();
include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top"><h1>Users</h1></div>
<?php if ($errors): ?><div class="err"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div><?php endif; ?>

<div class="card">
  <p class="k">Add a user</p>
  <form method="post" class="filters">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add">
    <div><label for="fn">Full name</label><input class="sel" id="fn" name="full_name" value="<?= e($new['full_name']) ?>" required></div>
    <div><label for="em">Email</label><input class="sel" id="em" type="email" name="email" value="<?= e($new['email']) ?>" required></div>
    <div><label for="un">Username</label><input class="sel" id="un" name="username" value="<?= e($new['username']) ?>" required></div>
    <div><label for="pw">Password</label><input class="sel" id="pw" type="password" name="password" required></div>
    <div><label for="rl">Role</label><select class="sel" id="rl" name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
    <button class="bp" type="submit">Create user</button>
  </form>
</div>

<div class="card" style="overflow-x:auto">
  <table>
    <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Manage</th></tr>
    <?php foreach ($users as $x): $locked = $x['locked_until'] && strtotime($x['locked_until']) > time(); $self = (int)$x['id'] === $myId; ?>
    <tr>
      <td><?= e($x['full_name']) ?><br><span class="muted"><?= e($x['email']) ?></span></td>
      <td><?= e($x['username']) ?></td><td><?= e($x['role']) ?></td>
      <td><?= $x['is_active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-wait">Pending or inactive</span>' ?>
          <?= $locked ? '<span class="badge b-bad">Locked</span>' : '' ?></td>
      <td>
        <?php if (!$self): ?>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <button class="<?= $x['is_active'] ? 'bd' : 'bp' ?> sm" type="submit"><?= $x['is_active'] ? 'Deactivate' : 'Approve' ?></button></form>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <select name="role" aria-label="Role"><option value="staff" <?= $x['role'] === 'staff' ? 'selected' : '' ?>>Staff</option><option value="admin" <?= $x['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select>
          <button class="bs sm" type="submit">Set role</button></form>
        <?php endif; ?>
        <?php if ($locked): ?>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <button class="bs sm" type="submit">Unlock</button></form>
        <?php endif; ?>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resetpw"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <input type="password" name="new_password" placeholder="New password" aria-label="New password" required>
          <button class="bs sm" type="submit">Reset password</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
