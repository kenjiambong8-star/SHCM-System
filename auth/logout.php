<?php
require_once __DIR__ . '/../includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['user_id'])) {
    csrf_check();
    log_activity('LOGOUT', 'User logged out');
    $_SESSION = [];
    session_destroy();
}
header('Location: /auth/login.php');
exit;