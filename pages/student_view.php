<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/print_layout.php';
require_login();
$pageTitle = 'Student profile'; $active = 'students';

$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM students WHERE id = ?"); $s->execute([$id]);
$stu = $s->fetch();
if (!$stu) { http_response_code(404); exit('Student not found.'); }

$reqs = $pdo->query("SELECT id, name FROM requirements ORDER BY id")->fetchAll();
$r = $pdo->prepare("SELECT * FROM health_records WHERE student_id = ? ORDER BY checkup_date DESC, id DESC");
$r->execute([$id]);
$today = date('Y-m-d'); $state = [];
foreach ($r->fetchAll() as $rec) {
    $rid = $rec['requirement_id'];
    $valid = $rec['status'] === 'Compliant' && ($rec['valid_until'] === null || $rec['valid_until'] >= $today);
    if ($valid) {
        if (!isset($state[$rid]) || $state[$rid]['label'] !== 'Compliant') $state[$rid] = ['label' => 'Compliant', 'rec' => $rec];
    } elseif (!isset($state[$rid])) {
        $state[$rid] = ['label' => $rec['status'] === 'Compliant' ? 'Expired' : $rec['status'], 'rec' => $rec];
    }
}
$cleared = count($reqs) > 0;
foreach ($reqs as $q) if (($state[$q['id']]['label'] ?? '') !== 'Compliant') $cleared = false;
$full = $stu['last_name'] . ', ' . $stu['first_name'];

ob_start(); ?>
<p><strong>Student:</strong> <?= e($full) ?> (<?= e($stu['student_no']) ?>)<br>
<strong>Course and year:</strong> <?= e($stu['course']) ?> - Year <?= (int)$stu['year_level'] ?></p>
<table>
  <tr><th>Requirement</th><th>Status</th><th>Checkup date</th><th>Valid until</th><th>Ref</th></tr>
  <?php foreach ($reqs as $q): $st = $state[$q['id']] ?? null; ?>
  <tr><td><?= e($q['name']) ?></td><td><?= badge($st['label'] ?? 'Missing') ?></td>
      <td><?= e($st['rec']['checkup_date'] ?? '-') ?></td><td><?= e($st['rec']['valid_until'] ?? '-') ?></td><td><?= e($st['rec']['ref_no'] ?? '-') ?></td></tr>
  <?php endforeach; ?>
</table>
<?php $table = ob_get_clean();

if (isset($_GET['print'])) {
    log_activity('PRINT_CLEARANCE', 'Printed clearance slip for ' . $stu['student_no']);
    print_start('Health Clearance Slip', 'As of ' . date('F j, Y'));
    echo '<div class="status">' . ($cleared ? 'CLEARED' : 'NOT YET CLEARED') . '</div>' . $table;
    echo '<p style="font-size:13px;margin-top:16px">' . ($cleared
        ? 'This certifies that the student named above has complied with all required health requirements.'
        : 'The student named above has not yet completed all required health requirements.') . '</p>';
    print_end();
    exit;
}
include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top"><h1><?= e($full) ?></h1>
  <div class="bar2"><a class="bs" href="/pages/students.php">Back</a>
    <a class="bs" href="/pages/record_form.php?student_id=<?= $id ?>">Add record</a>
    <a class="bp" href="/pages/student_view.php?id=<?= $id ?>&print=1">Print clearance slip</a></div></div>
<div class="banner <?= $cleared ? 'ok' : 'no' ?>"><?= $cleared ? 'Cleared: all requirements are compliant and valid.' : 'Not yet cleared: some requirements are missing, pending, expired or non-compliant.' ?></div>
<div class="card" style="overflow-x:auto"><?= $table ?></div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>