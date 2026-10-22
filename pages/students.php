<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Students'; $active = 'students';

if (!$pdo->query("SHOW TABLES LIKE 'students'")->fetchColumn()) {
    include __DIR__ . '/../includes/layout_top.php';
    echo '<div class="err"><p><strong>Missing tables.</strong> <a href="/setup.php">Run database setup</a> with your MySQL Workbench root password, then refresh.</p></div>';
    include __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $s = $pdo->prepare("SELECT student_no, first_name, last_name FROM students WHERE id = ?");
    $s->execute([$id]);
    $stu = $s->fetch();
    if ($stu) {
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        log_activity('DELETE_STUDENT', $stu['student_no'] . ' - ' . $stu['last_name'] . ', ' . $stu['first_name']);
        $_SESSION['flash'] = ['ok', 'Student deleted.'];
    }
    header('Location: /pages/students.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$course = trim($_GET['course'] ?? '');
$year = (int)($_GET['year'] ?? 0);
$clearance = $_GET['clearance'] ?? '';
if (!in_array($clearance, ['', 'cleared', 'not'], true)) $clearance = '';

$courses = $pdo->query("SELECT DISTINCT course FROM students ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

[$rows, $totalReq] = clearance_list($pdo, [
    'course' => $course,
    'year' => $year > 0 ? $year : null,
    'clearance' => $clearance,
]);

if ($q !== '') {
    $ql = mb_strtolower($q);
    $rows = array_values(array_filter($rows, function ($r) use ($ql) {
        $hay = mb_strtolower($r['student_no'] . ' ' . $r['first_name'] . ' ' . $r['last_name']);
        return str_contains($hay, $ql);
    }));
}

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top">
  <h1>Students</h1>
  <a class="bp" href="/pages/student_form.php">Add student</a>
</div>

<div class="card">
  <form class="filters" method="get">
    <div><label for="q">Search</label><input class="sel" id="q" name="q" placeholder="Name or student no." value="<?= e($q) ?>"></div>
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
    <a class="bs" href="/pages/students.php">Reset</a>
  </form>
</div>

<div class="card" style="overflow-x:auto">
  <p class="k"><?= count($rows) ?> student(s) · <?= (int)$totalReq ?> requirement(s)</p>
  <table>
    <tr>
      <th>Student no.</th><th>Name</th><th>Course</th><th>Year</th>
      <th>Progress</th><th>Status</th><th>Actions</th>
    </tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['student_no']) ?></td>
      <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
      <td><?= e($r['course']) ?></td>
      <td><?= (int)$r['year_level'] ?></td>
      <td><?= (int)$r['done'] ?> / <?= (int)$totalReq ?></td>
      <td><?= badge($r['cleared'] ? 'Cleared' : 'Not cleared') ?></td>
      <td>
        <a class="bs sm" href="/pages/student_view.php?id=<?= (int)$r['id'] ?>">View</a>
        <a class="bs sm" href="/pages/student_form.php?id=<?= (int)$r['id'] ?>">Edit</a>
        <a class="bs sm" href="/pages/record_form.php?student_id=<?= (int)$r['id'] ?>">Add record</a>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this student and their health records?')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="bd sm" type="submit">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7">No students found. Add a student or adjust filters.</td></tr><?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
