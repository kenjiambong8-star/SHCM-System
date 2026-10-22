<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Dashboard'; $active = 'dashboard';

$hasStudents = (bool)$pdo->query("SHOW TABLES LIKE 'students'")->fetchColumn();
$hasRecords  = (bool)$pdo->query("SHOW TABLES LIKE 'health_records'")->fetchColumn();
$hasReqs     = (bool)$pdo->query("SHOW TABLES LIKE 'requirements'")->fetchColumn();

$studentCount = $clearedN = $notN = $recordCount = $reqCount = 0;
$byCourse = []; $recentRecords = []; $missingTables = !$hasStudents || !$hasRecords || !$hasReqs;

if (!$missingTables) {
    [$all, $reqCount] = clearance_list($pdo);
    $studentCount = count($all);
    $clearedN = count(array_filter($all, fn($r) => $r['cleared']));
    $notN = $studentCount - $clearedN;
    foreach ($all as $r) {
        $c = $r['course'];
        if (!isset($byCourse[$c])) $byCourse[$c] = ['total' => 0, 'cleared' => 0];
        $byCourse[$c]['total']++;
        if ($r['cleared']) $byCourse[$c]['cleared']++;
    }
    $recordCount = (int)$pdo->query("SELECT COUNT(*) FROM health_records")->fetchColumn();
    $recentRecords = $pdo->query(
        "SELECT hr.checkup_date, hr.status, hr.valid_until, s.student_no, s.first_name, s.last_name, r.name AS requirement
         FROM health_records hr
         JOIN students s ON s.id = hr.student_id
         JOIN requirements r ON r.id = hr.requirement_id
         ORDER BY hr.id DESC LIMIT 8"
    )->fetchAll();
}

if ($_SESSION['role'] === 'admin') {
    $logs = $pdo->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 5")->fetchAll();
} else {
    $s = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $s->execute([$_SESSION['user_id']]);
    $logs = $s->fetchAll();
}

$today = date('Y-m-d');
$courseLabels = array_keys($byCourse);
$courseCleared = array_map(fn($c) => $c['cleared'], array_values($byCourse));
$courseNot = array_map(fn($c) => $c['total'] - $c['cleared'], array_values($byCourse));

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top">
  <h1>Dashboard</h1>
  <div class="bar2">
    <a class="bp" href="/pages/student_form.php">Add student</a>
    <a class="bs" href="/pages/record_form.php">Add record</a>
  </div>
</div>

<?php if ($missingTables): ?>
<div class="err">
  <p><strong>Database tables are missing.</strong></p>
  <p>Open the setup page and enter your MySQL Workbench root password to create them:</p>
  <p style="margin-top:12px"><a class="bp" href="/setup.php">Run database setup</a></p>
</div>
<?php else: ?>

<div class="stats">
  <div class="card"><p class="k">Students</p><p class="v"><?= $studentCount ?></p></div>
  <div class="card"><p class="k">Cleared</p><p class="v"><?= $clearedN ?></p></div>
  <div class="card"><p class="k">Not cleared</p><p class="v"><?= $notN ?></p></div>
  <div class="card"><p class="k">Health records</p><p class="v"><?= $recordCount ?></p></div>
</div>

<div class="grid2">
  <div class="card">
    <p class="k">Clearance by course</p>
    <?php if ($byCourse): ?>
      <div class="chartbox"><canvas id="courseChart"></canvas></div>
    <?php else: ?>
      <p>No students yet. <a href="/pages/student_form.php">Add a student</a>.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <p class="k">Clearance overview</p>
    <?php if ($studentCount): ?>
      <div class="chartbox"><canvas id="pieChart"></canvas></div>
    <?php else: ?>
      <p>No data for this period.</p>
    <?php endif; ?>
  </div>
</div>

<div class="grid2">
  <div class="card" style="overflow-x:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <p class="k" style="margin:0">Recent health records</p>
      <a class="bs sm" href="/pages/records.php">View all</a>
    </div>
    <table>
      <tr><th>Date</th><th>Student</th><th>Requirement</th><th>Status</th></tr>
      <?php foreach ($recentRecords as $r):
        $label = $r['status'];
        if ($r['status'] === 'Compliant' && $r['valid_until'] !== null && $r['valid_until'] < $today) $label = 'Expired';
      ?>
      <tr>
        <td><?= e($r['checkup_date'] ?? '—') ?></td>
        <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?><br><span class="muted"><?= e($r['student_no']) ?></span></td>
        <td><?= e($r['requirement']) ?></td>
        <td><?= badge($label) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recentRecords): ?><tr><td colspan="4">No health records yet.</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="card">
    <p class="k">Recent activity</p>
    <?php foreach ($logs as $l): ?>
      <p class="logrow"><?= e($l['username']) ?>: <?= e($l['action']) ?> <?= e($l['details']) ?>
        <span><?= e(date('M d, g:i A', strtotime($l['created_at']))) ?></span></p>
    <?php endforeach; ?>
    <?php if (!$logs): ?><p>No activity yet.</p><?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
<?php if ($byCourse): ?>
new Chart(document.getElementById('courseChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($courseLabels, JSON_HEX_TAG) ?>,
    datasets: [
      { label: 'Cleared', data: <?= json_encode($courseCleared) ?>, backgroundColor: '#0F6E56', borderRadius: 6 },
      { label: 'Not cleared', data: <?= json_encode($courseNot) ?>, backgroundColor: '#C0504D', borderRadius: 6 }
    ]
  },
  options: { maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
});
<?php endif; ?>
<?php if ($studentCount): ?>
new Chart(document.getElementById('pieChart'), {
  type: 'doughnut',
  data: {
    labels: ['Cleared', 'Not cleared'],
    datasets: [{ data: [<?= $clearedN ?>, <?= $notN ?>], backgroundColor: ['#0F6E56', '#C0504D'] }]
  },
  options: { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
});
<?php endif; ?>
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
