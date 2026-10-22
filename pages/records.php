<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Health records'; $active = 'records';

$reqs = $pdo->query("SELECT id, name FROM requirements ORDER BY id")->fetchAll();
$statuses = ['Pending', 'Compliant', 'Non-compliant'];

$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$req = (int)($_GET['requirement'] ?? 0);
$status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$q = trim($_GET['q'] ?? '');

$where = []; $params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'h.checkup_date >= ?'; $params[] = $from; } else { $from = ''; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'h.checkup_date <= ?'; $params[] = $to; }   else { $to = ''; }
if ($req > 0) { $where[] = 'h.requirement_id = ?'; $params[] = $req; }
if ($status !== '') { $where[] = 'h.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(s.student_no LIKE ? OR s.last_name LIKE ? OR s.first_name LIKE ? OR h.ref_no LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }

$sql = "SELECT h.*, s.student_no, s.last_name, s.first_name, q.name AS requirement
        FROM health_records h
        JOIN students s ON s.id = h.student_id
        JOIN requirements q ON q.id = h.requirement_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY h.checkup_date DESC, h.id DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top"><h1>Health records</h1><a class="bp" href="/pages/record_form.php">Add record</a></div>

<div class="card">
  <form class="filters" method="get">
    <div><label for="from">From</label><input class="sel" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div><label for="to">To</label><input class="sel" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div><label for="requirement">Requirement</label>
      <select class="sel" id="requirement" name="requirement"><option value="0">All requirements</option>
        <?php foreach ($reqs as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $req === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="status">Status</label>
      <select class="sel" id="status" name="status"><option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="q">Search</label><input class="sel" id="q" name="q" placeholder="Student or ref" value="<?= e($q) ?>"></div>
    <button class="bp" type="submit">Apply filter</button>
    <a class="bs" href="/pages/records.php">Reset</a>
  </form>
</div>

<div class="card" style="overflow-x:auto">
  <p class="k"><?= count($rows) ?> record(s)</p>
  <table>
    <tr><th>Checkup date</th><th>Ref</th><th>Student</th><th>Requirement</th><th>Status</th><th>Valid until</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['checkup_date']) ?></td>
      <td><?= e($r['ref_no']) ?></td>
      <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?><br><span class="muted"><?= e($r['student_no']) ?></span></td>
      <td><?= e($r['requirement']) ?></td>
      <td><?= badge($r['status']) ?></td>
      <td><?= e($r['valid_until'] ?? '-') ?></td>
      <td>
        <a class="bs sm" href="/pages/record_form.php?id=<?= (int)$r['id'] ?>">Edit</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <form method="post" action="/pages/record_delete.php" style="display:inline" onsubmit="return confirm('Delete this record?')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="bd sm" type="submit">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7">No records found.</td></tr><?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>