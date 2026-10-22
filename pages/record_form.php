<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Health record'; $active = 'records';

$id = (int)($_GET['id'] ?? 0);
$preStudent = (int)($_GET['student_id'] ?? 0);
$row = [
    'student_id' => $preStudent, 'requirement_id' => 0, 'status' => 'Compliant',
    'checkup_date' => date('Y-m-d'), 'valid_until' => '', 'ref_no' => '', 'notes' => '',
];
if ($id) {
    $s = $pdo->prepare("SELECT * FROM health_records WHERE id = ?");
    $s->execute([$id]);
    $found = $s->fetch();
    if (!$found) { http_response_code(404); exit('Record not found.'); }
    $row = $found;
    $pageTitle = 'Edit health record';
} else {
    $pageTitle = 'Add health record';
}

$students = $pdo->query("SELECT id, student_no, first_name, last_name FROM students ORDER BY last_name, first_name")->fetchAll();
$reqs = $pdo->query("SELECT id, name FROM requirements ORDER BY id")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row = [
        'student_id' => (int)($_POST['student_id'] ?? 0),
        'requirement_id' => (int)($_POST['requirement_id'] ?? 0),
        'status' => $_POST['status'] ?? 'Pending',
        'checkup_date' => trim($_POST['checkup_date'] ?? ''),
        'valid_until' => trim($_POST['valid_until'] ?? ''),
        'ref_no' => trim($_POST['ref_no'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    if (!in_array($row['status'], ['Compliant', 'Pending', 'Non-compliant'], true)) $row['status'] = 'Pending';
    if ($row['student_id'] <= 0) $errors[] = 'Select a student.';
    if ($row['requirement_id'] <= 0) $errors[] = 'Select a requirement.';
    if ($row['checkup_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['checkup_date'])) $errors[] = 'Enter a checkup date.';
    if ($row['valid_until'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['valid_until'])) $errors[] = 'Valid until must be YYYY-MM-DD.';
    if ($row['valid_until'] === '') $row['valid_until'] = null;
    if (strlen($row['ref_no']) > 50) $errors[] = 'Reference is too long.';
    if (strlen($row['notes']) > 255) $errors[] = 'Notes are too long.';

    $chk = $pdo->prepare("SELECT id FROM students WHERE id = ?");
    $chk->execute([$row['student_id']]);
    if (!$chk->fetch()) $errors[] = 'Student not found.';
    $chk = $pdo->prepare("SELECT id FROM requirements WHERE id = ?");
    $chk->execute([$row['requirement_id']]);
    if (!$chk->fetch()) $errors[] = 'Requirement not found.';

    if (!$errors) {
        if ($id) {
            $pdo->prepare("UPDATE health_records SET student_id=?, requirement_id=?, status=?, checkup_date=?, valid_until=?, ref_no=?, notes=?, recorded_by=? WHERE id=?")
                ->execute([$row['student_id'], $row['requirement_id'], $row['status'], $row['checkup_date'], $row['valid_until'], $row['ref_no'] ?: null, $row['notes'] ?: null, $_SESSION['user_id'], $id]);
            log_activity('UPDATE_RECORD', 'Record #' . $id);
            $_SESSION['flash'] = ['ok', 'Health record updated.'];
        } else {
            $pdo->prepare("INSERT INTO health_records (student_id, requirement_id, status, checkup_date, valid_until, ref_no, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$row['student_id'], $row['requirement_id'], $row['status'], $row['checkup_date'], $row['valid_until'], $row['ref_no'] ?: null, $row['notes'] ?: null, $_SESSION['user_id']]);
            log_activity('ADD_RECORD', 'Student #' . $row['student_id'] . ' requirement #' . $row['requirement_id']);
            $_SESSION['flash'] = ['ok', 'Health record added.'];
        }
        header('Location: /pages/student_view.php?id=' . $row['student_id']);
        exit;
    }
}

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top">
  <h1><?= e($pageTitle) ?></h1>
  <a class="bs" href="<?= $row['student_id'] ? '/pages/student_view.php?id=' . (int)$row['student_id'] : '/pages/records.php' ?>">Back</a>
</div>
<?php if ($errors): ?><div class="err"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div><?php endif; ?>
<?php if (!$students): ?>
  <div class="err"><p>No students yet. <a href="/pages/student_form.php">Add a student</a> first.</p></div>
<?php elseif (!$reqs): ?>
  <div class="err"><p>No requirements found. Run <code>config/schema.sql</code> in MySQL Workbench.</p></div>
<?php else: ?>
<div class="card formcard">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="f"><label for="student_id">Student</label>
      <select id="student_id" name="student_id" class="sel" style="width:100%;height:46px" required>
        <option value="">Select student</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)$row['student_id'] === (int)$s['id'] ? 'selected' : '' ?>>
            <?= e($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['student_no'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div class="f"><label for="requirement_id">Requirement</label>
      <select id="requirement_id" name="requirement_id" class="sel" style="width:100%;height:46px" required>
        <option value="">Select requirement</option>
        <?php foreach ($reqs as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= (int)$row['requirement_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="f"><label for="status">Status</label>
      <select id="status" name="status" class="sel" style="width:100%;height:46px">
        <?php foreach (['Compliant','Pending','Non-compliant'] as $stn): ?>
          <option value="<?= $stn ?>" <?= ($row['status'] ?? '') === $stn ? 'selected' : '' ?>><?= $stn ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="f"><label for="checkup_date">Checkup date</label>
      <input id="checkup_date" type="date" name="checkup_date" value="<?= e($row['checkup_date'] ?? '') ?>" required></div>
    <div class="f"><label for="valid_until">Valid until</label>
      <input id="valid_until" type="date" name="valid_until" value="<?= e($row['valid_until'] ?? '') ?>"></div>
    <div class="f"><label for="ref_no">Reference no.</label>
      <input id="ref_no" name="ref_no" value="<?= e($row['ref_no'] ?? '') ?>" maxlength="50"></div>
    <div class="f"><label for="notes">Notes</label>
      <input id="notes" name="notes" value="<?= e($row['notes'] ?? '') ?>" maxlength="255"></div>
    <button class="bp" type="submit"><?= $id ? 'Save changes' : 'Add record' ?></button>
  </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
