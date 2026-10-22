<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Transaction'; $active = 'transactions';

$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$row = ['category_id' => '', 'description' => '', 'amount' => '', 'txn_date' => date('Y-m-d')];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) { http_response_code(404); exit('Transaction not found.'); }
    $row = $existing;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $catId  = (int)($_POST['category_id'] ?? 0);
    $desc   = trim($_POST['description'] ?? '');
    $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
    $date   = $_POST['txn_date'] ?? '';

    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) $errors[] = 'Enter a valid date.';

    $chk = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $chk->execute([$catId]);
    if (!$chk->fetch()) $errors[] = 'Choose a category.';

    if ($amount === false || $amount <= 0 || $amount >= 10000000) $errors[] = 'Amount must be a positive number.';
    if (strlen($desc) > 255) $errors[] = 'Description is too long (255 max).';

    $row = ['category_id' => $catId, 'description' => $desc, 'amount' => $_POST['amount'] ?? '', 'txn_date' => $date];

    if (!$errors) {
        $amount = round($amount, 2);
        if ($id) {
            $pdo->prepare("UPDATE transactions SET category_id=?, description=?, amount=?, txn_date=? WHERE id=?")
                ->execute([$catId, $desc, $amount, $date, $id]);
            log_activity('EDIT', "Edited " . $existing['ref_no'] . " (amount $amount)");
            $_SESSION['flash'] = ['ok', 'Transaction updated.'];
        } else {
            $tmp = 'TMP' . bin2hex(random_bytes(4));
            $pdo->prepare("INSERT INTO transactions (ref_no, user_id, category_id, description, amount, txn_date)
                           VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$tmp, $_SESSION['user_id'], $catId, $desc, $amount, $date]);
            $newId = (int)$pdo->lastInsertId();
            $ref = sprintf('TX-%04d', $newId);
            $pdo->prepare("UPDATE transactions SET ref_no = ? WHERE id = ?")->execute([$ref, $newId]);
            log_activity('ADD', "Added $ref (amount $amount)");
            $_SESSION['flash'] = ['ok', "Transaction $ref added."];
        }
        header('Location: /pages/transactions.php');
        exit;
    }
}

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top"><h1><?= $id ? 'Edit transaction' : 'Add transaction' ?></h1></div>
<div class="card formcard">
  <?php if ($errors): ?><div class="err"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="f"><label for="txn_date">Date</label><input type="date" id="txn_date" name="txn_date" value="<?= e($row['txn_date']) ?>" required></div>
    <div class="f"><label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <option value="">Choose a category</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$row['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="f"><label for="description">Description</label><input id="description" name="description" maxlength="255" value="<?= e($row['description']) ?>"></div>
    <div class="f"><label for="amount">Amount (PHP)</label><input id="amount" name="amount" inputmode="decimal" value="<?= e($row['amount']) ?>" required></div>
    <button class="bp" type="submit">Save</button>
    <a class="bs" href="/pages/transactions.php">Cancel</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>