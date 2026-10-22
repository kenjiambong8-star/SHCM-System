<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT ref_no, amount FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    if ($t = $stmt->fetch()) {
        $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
        log_activity('DELETE', "Deleted " . $t['ref_no'] . " (amount " . $t['amount'] . ")");
        $_SESSION['flash'] = ['ok', 'Transaction deleted.'];
    }
}
header('Location: /pages/transactions.php');
exit;