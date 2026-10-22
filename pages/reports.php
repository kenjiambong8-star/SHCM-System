<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/print_layout.php';
require_login();
$pageTitle = 'Reports'; $active = 'reports';

if (!$pdo->query("SHOW TABLES LIKE 'students'")->fetchColumn()) {
    include __DIR__ . '/../includes/layout_top.php';
    echo '<div class="err"><p><strong>Missing tables.</strong> <a href="/setup.php">Run database setup</a> with your MySQL Workbench root password, then refresh.</p></div>';
    include __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

$course = trim($_GET['course'] ?? '');
$year = (int)($_GET['year'] ?? 0);
$clearance = $_GET['clearance'] ?? '';
if (!in_array($clearance, ['', 'cleared', 'not'], true)) $clearance = '';
$print = isset($_GET['print']);

$courses = $pdo->query("SELECT DISTINCT course FROM students ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
[$rows, $totalReq] = clearance_list($pdo, [
    'course' => $course,
    'year' => $year > 0 ? $year : null,
    'clearance' => $clearance,
]);

$clearedN = count(array_filter($rows, fn($r) => $r['cleared']));
$notN = count($rows) - $clearedN;
$byCourse = [];
foreach ($rows as $r) {
    $c = $r['course'];
    if (!isset($byCourse[$c])) $byCourse[$c] = ['total' => 0, 'cleared' => 0];
    $byCourse[$c]['total']++;
    if ($r['cleared']) $byCourse[$c]['cleared']++;
}

$filterLabel = 'Course: ' . ($course !== '' ? $course : 'all')
    . ' · Year: ' . ($year > 0 ? $year : 'all')
    . ' · Clearance: ' . ($clearance === 'cleared' ? 'cleared' : ($clearance === 'not' ? 'not cleared' : 'all'));

ob_start(); ?>
<p><strong><?= count($rows) ?></strong> student(s) ·
  <strong><?= $clearedN ?></strong> cleared ·
  <strong><?= $notN ?></strong> not cleared ·
  <?= (int)$totalReq ?> requirement(s)</p>

<table style="max-width:480px;margin-bottom:18px">
  <tr><th>Course</th><th>Students</th><th>Cleared</th><th>Rate</th></tr>
  <?php foreach ($byCourse as $name => $c): ?>
  <tr>
    <td><?= e($name) ?></td>
    <td><?= (int)$c['total'] ?></td>
    <td><?= (int)$c['cleared'] ?></td>
    <td><?= $c['total'] ? number_format(100 * $c['cleared'] / $c['total'], 1) . '%' : '—' ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$byCourse): ?><tr><td colspan="4">No data.</td></tr><?php endif; ?>
</table>

<table>
  <tr><th>Student no.</th><th>Name</th><th>Course</th><th>Year</th><th>Progress</th><th>Status</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['student_no']) ?></td>
    <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
    <td><?= e($r['course']) ?></td>
    <td><?= (int)$r['year_level'] ?></td>
    <td><?= (int)$r['done'] ?> / <?= (int)$totalReq ?></td>
    <td><?= $r['cleared'] ? 'Cleared' : 'Not cleared' ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6">No students match these filters.</td></tr><?php endif; ?>
</table>
<?php $body = ob_get_clean();

if ($print) {
    log_activity('PRINT_REPORT', $filterLabel);
    print_start('Clearance report', $filterLabel . ' · As of ' . date('F j, Y'));
    echo $body;
    print_end();
    exit;
}

$printUrl = '/pages/reports.php?' . http_build_query([
    'course' => $course, 'year' => $year, 'clearance' => $clearance, 'print' => 1,
]);

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top">
  <h1>Reports</h1>
  <a class="bp" href="<?= e($printUrl) ?>">Print preview</a>
</div>

<div class="card">
  <form class="filters" method="get">
    <div><label for="course">Course</label>
      <select class="sel" id="course" name="course">
        <option value="">All courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= e($c) ?>" <?= $course === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label for="year">Year</label>
      <select class="sel" id="year" name="year">
        <option value="0">All years</option>
        <?php for ($y = 1; $y <= 5; $y++): ?>
          <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div><label for="clearance">Clearance</label>
      <select class="sel" id="clearance" name="clearance">
        <option value="">All</option>
        <option value="cleared" <?= $clearance === 'cleared' ? 'selected' : '' ?>>Cleared</option>
        <option value="not" <?= $clearance === 'not' ? 'selected' : '' ?>>Not cleared</option>
      </select>
    </div>
    <button class="bp" type="submit">Apply filter</button>
    <a class="bs" href="/pages/reports.php">Reset</a>
  </form>
</div>

<div class="stats">
  <div class="card"><p class="k">Students (filtered)</p><p class="v"><?= count($rows) ?></p></div>
  <div class="card"><p class="k">Cleared</p><p class="v"><?= $clearedN ?></p></div>
  <div class="card"><p class="k">Not cleared</p><p class="v"><?= $notN ?></p></div>
  <div class="card"><p class="k">Clearance rate</p><p class="v"><?= count($rows) ? number_format(100 * $clearedN / count($rows), 1) . '%' : '—' ?></p></div>
</div>

<div class="card" style="overflow-x:auto"><?= $body ?></div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
