<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'Activity log'; $active = 'activity';

$users   = $pdo->query("SELECT DISTINCT username FROM activity_logs ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$u = $_GET['user'] ?? ''; $a = $_GET['action'] ?? ''; $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';

$where = []; $params = [];
if ($u !== '') { $where[] = 'username = ?'; $params[] = $u; }
if ($a !== '') { $where[] = 'action = ?'; $params[] = $a; }
if ($from !== '') { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$c = $pdo->prepare("SELECT COUNT(*) FROM activity_logs" . $w); $c->execute($params);
$total = (int)$c->fetchColumn();
$per = 50; $pages = max(1, (int)ceil($total / $per));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per;

$st = $pdo->prepare("SELECT * FROM activity_logs" . $w . " ORDER BY id DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();
$link = fn($p) => '/pages/activity_log.php?' . http_build_query(array_merge($_GET, ['page' => $p]));
include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top"><h1>Activity log</h1></div>
<div class="card">
  <form class="filters" method="get">
    <div><label for="user">User</label><select class="sel" id="user" name="user"><option value="">All users</option>
      <?php foreach ($users as $x): ?><option <?= $u === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
    <div><label for="action">Activity</label><select class="sel" id="action" name="action"><option value="">All activities</option>
      <?php foreach ($actions as $x): ?><option <?= $a === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
    <div><label for="from">From</label><input class="sel" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div><label for="to">To</label><input class="sel" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <button class="bp" type="submit">Apply filter</button>
    <a class="bs" href="/pages/activity_log.php">Reset</a>
  </form>
</div>
<div class="card" style="overflow-x:auto">
  <p class="k"><?= $total ?> log entries</p>
  <table>
    <tr><th>Date</th><th>Time</th><th>User</th><th>Activity</th><th>Details</th><th>IP address</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td><td><?= e(date('g:i:s A', strtotime($r['created_at']))) ?></td>
        <td><?= e($r['username']) ?></td><td><?= e($r['action']) ?></td><td><?= e($r['details']) ?></td><td><?= e($r['ip_address']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6">No log entries found.</td></tr><?php endif; ?>
  </table>
  <div class="pager">
    <?php if ($page > 1): ?><a class="bs sm" href="<?= e($link($page - 1)) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="bs sm" href="<?= e($link($page + 1)) ?>">Next</a><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
