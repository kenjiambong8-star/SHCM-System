<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_lib.php';
require_admin();
$pageTitle = 'Backup and restore'; $active = 'backup';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'create') {
            $name = create_backup($pdo, ($_POST['type'] ?? '') === 'full' ? 'full' : 'db');
            log_activity('BACKUP', "Created $name");
            $_SESSION['flash'] = ['ok', "Backup created: $name"];
            header('Location: /pages/backup.php'); exit;
        }
        if ($act === 'delete') {
            $name = $_POST['name'] ?? '';
            if (!valid_backup_name($name)) throw new RuntimeException('Invalid file name.');
            @unlink(backup_dir() . '/' . $name);
            log_activity('BACKUP_DELETE', "Deleted $name");
            $_SESSION['flash'] = ['ok', 'Backup deleted.'];
            header('Location: /pages/backup.php'); exit;
        }
        if ($act === 'restore_file' || $act === 'restore_upload') {
            if (trim($_POST['confirm'] ?? '') !== 'RESTORE') throw new RuntimeException('Type RESTORE to confirm.');
            if ($act === 'restore_file') {
                $name = $_POST['name'] ?? '';
                $path = backup_dir() . '/' . $name;
                if (!valid_backup_name($name) || !is_file($path)) throw new RuntimeException('Backup file not found.');
                $sql = read_backup_sql($path, strtolower(pathinfo($name, PATHINFO_EXTENSION)));
            } else {
                $f = $_FILES['file'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Upload failed. Choose a .sql or .zip backup.');
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['sql', 'zip'], true)) throw new RuntimeException('Only .sql or .zip backups are accepted.');
                $sql = read_backup_sql($f['tmp_name'], $ext);
            }
            $n = restore_database($pdo, $sql);
            log_activity('RESTORE', "Restored $n rows from backup");
            $_SESSION = []; session_destroy();
            header('Location: /auth/login.php?restored=1'); exit;
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Operation failed. Check the server log.';
    }
}
$files = list_backups();
include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top"><h1>Backup and restore</h1></div>
<?php if ($errors): ?><div class="err"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div><?php endif; ?>

<div class="card">
  <p class="k">Create a backup now</p>
  <form method="post" class="bar2">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <button class="bp" name="type" value="db" type="submit">Database backup (.sql)</button>
    <button class="bs" name="type" value="full" type="submit">Full backup: files + database (.zip)</button>
  </form>
  <p class="muted" style="margin-top:10px">Recommended: daily database backup and weekly full backup (set up with the cron job in the deployment steps).</p>
</div>

<div class="card" style="overflow-x:auto">
  <p class="k">Saved backups</p>
  <table>
    <tr><th>File</th><th>Type</th><th>Created</th><th>Size</th><th>Actions</th></tr>
    <?php foreach ($files as $b): ?>
    <tr>
      <td><?= e($b['name']) ?></td><td><?= e($b['type']) ?></td><td><?= e(date('Y-m-d g:i A', $b['time'])) ?></td><td><?= e(number_format($b['size'] / 1024, 1)) ?> KB</td>
      <td>
        <a class="bs sm" href="/pages/backup_download.php?f=<?= urlencode($b['name']) ?>">Download</a>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="restore_file"><input type="hidden" name="name" value="<?= e($b['name']) ?>">
          <input name="confirm" placeholder="Type RESTORE" aria-label="Type RESTORE to confirm" required><button class="bd sm" type="submit">Restore</button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete this backup file?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($b['name']) ?>">
          <button class="bs sm" type="submit">Delete</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$files): ?><tr><td colspan="5">No backups yet.</td></tr><?php endif; ?>
  </table>
</div>

<div class="card">
  <p class="k">Restore from an uploaded file</p>
  <form method="post" enctype="multipart/form-data" class="filters">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="restore_upload">
    <div><label for="file">Backup file (.sql or .zip)</label><input class="sel" type="file" id="file" name="file" accept=".sql,.zip" required></div>
    <div><label for="cf">Type RESTORE to confirm</label><input class="sel" id="cf" name="confirm" required></div>
    <button class="bd" type="submit">Restore database</button>
  </form>
  <p class="muted" style="margin-top:10px">Restoring replaces all current data with the backup and signs everyone out. For a full (.zip) backup, the files are inside the <code>files/</code> folder of the zip: extract them over the project folder to restore the application files.</p>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>