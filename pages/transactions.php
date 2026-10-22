<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Transactions'; $active = 'transactions';

$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$cat  = (int)($_GET['category'] ?? 0);
$q    = trim($_GET['q'] ?? '');

$where = []; $params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 't.txn_date >= ?'; $params[] = $from; } else { $from = ''; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 't.txn_date <= ?'; $params[] = $to; }   else { $to = ''; }
if ($cat > 0) { $where[] = 't.category_id = ?'; $params[] = $cat; }
if ($q !== '') { $where[] = '(t.ref_no LIKE ? OR t.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$sql = "SELECT t.*, c.name AS category, u.username
        FROM transactions t
        JOIN categories c ON c.id = t.category_id
        JOIN users u ON u.id = t.user_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY t.txn_date DESC, t.id DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$total = array_sum(array_column($rows, 'amount'));

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top">
  <h1>Transactions</h1>
  <a class="bp" href="/pages/transaction_form.php">Add transaction</a>
</div>

<div class="card">
  <form class="filters" method="get">
    <div><label for="from">From</label><input class="sel" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div><label for="to">To</label><input class="sel" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div><label for="category">Category</label>
      <select class="sel" id="category" name="category">
        <option value="0">All categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label for="q">Search</label><input class="sel" id="q" name="q" placeholder="Ref or description" value="<?= e($q) ?>"></div>
    <button class="bp" type="submit">Apply filter</button>
    <a class="bs" href="/pages/transactions.php">Reset</a>
  </form>
</div>

<div class="card" style="overflow-x:auto">
  <p class="k"><?= count($rows) ?> record(s) &middot; Total: &#8369;<?= number_format($total, 2) ?></p>
  <table>
    <tr><th>Date</th><th>Ref</th><th>Category</th><th>Description</th><th>Amount</th><th>By</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['txn_date']) ?></td>
      <td><?= e($r['ref_no']) ?></td>
      <td><?= e($r['category']) ?></td>
      <td><?= e($r['description']) ?></td>
      <td>&#8369;<?= number_format($r['amount'], 2) ?></td>
      <td><?= e($r['username']) ?></td>
      <td>
        <a class="bs sm" href="/pages/transaction_form.php?id=<?= (int)$r['id'] ?>">Edit</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <form method="post" action="/pages/transaction_delete.php" style="display:inline" onsubmit="return confirm('Delete this transaction?')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="bd sm" type="submit">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7">No transactions found.</td></tr><?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>