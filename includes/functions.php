<?php
const APP_ENV    = 'local';   // change to 'production' when deployed
const IDLE_LIMIT = 1800;      // 30 minutes

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
    if ($https) header('Strict-Transport-Security: max-age=31536000');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => $https]);
    if (session_status() === PHP_SESSION_NONE) session_start();
}
require_once __DIR__ . '/../config/db.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); exit('Invalid request.');
    }
}

function log_activity($action, $details = '', $username = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $username ?? ($_SESSION['username'] ?? 'guest'),
        $action, $details, $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}

function require_login() {
    global $pdo;
    if (empty($_SESSION['user_id'])) { header('Location: /auth/login.php'); exit; }
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > IDLE_LIMIT) {
        log_activity('SESSION_TIMEOUT', 'Logged out after inactivity');
        $_SESSION = []; session_destroy();
        header('Location: /auth/login.php?timeout=1'); exit;
    }
    $s = $pdo->prepare("SELECT is_active, role FROM users WHERE id = ?");
    $s->execute([$_SESSION['user_id']]);
    $u = $s->fetch();
    if (!$u || !$u['is_active']) {
        $_SESSION = []; session_destroy();
        header('Location: /auth/login.php'); exit;
    }
    $_SESSION['role'] = $u['role'];
    $_SESSION['last_active'] = time();
}
function require_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        log_activity('ACCESS_DENIED', 'Tried to open an admin page');
        http_response_code(403); exit('Admins only.');
    }
}

function new_captcha() {
    $a = random_int(1, 9); $b = random_int(1, 9);
    if (random_int(0, 1)) { $_SESSION['captcha'] = $a + $b; return "$a + $b"; }
    if ($a < $b) { [$a, $b] = [$b, $a]; }
    $_SESSION['captcha'] = $a - $b;
    return "$a - $b";
}
function captcha_ok() {
    $given = trim($_POST['captcha'] ?? '');
    $ok = isset($_SESSION['captcha']) && ctype_digit($given) && (int)$given === $_SESSION['captcha'];
    unset($_SESSION['captcha']);
    return $ok;
}

function badge($s) {
    $map = ['Compliant'=>'b-ok','Cleared'=>'b-ok','Pending'=>'b-wait','Missing'=>'b-wait',
            'Non-compliant'=>'b-bad','Not cleared'=>'b-bad','Expired'=>'b-bad'];
    return '<span class="badge ' . ($map[$s] ?? 'b-wait') . '">' . e($s) . '</span>';
}

function clearance_list(PDO $pdo, array $f = []): array {
    $totalReq = (int)$pdo->query("SELECT COUNT(*) FROM requirements")->fetchColumn();
    $where = []; $params = [date('Y-m-d')];
    if (!empty($f['course'])) { $where[] = 's.course = ?'; $params[] = $f['course']; }
    if (!empty($f['year']))   { $where[] = 's.year_level = ?'; $params[] = (int)$f['year']; }
    $sql = "SELECT s.*, COUNT(DISTINCT r.requirement_id) AS done
            FROM students s
            LEFT JOIN health_records r ON r.student_id = s.id AND r.status = 'Compliant'
                 AND (r.valid_until IS NULL OR r.valid_until >= ?)"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " GROUP BY s.id ORDER BY s.course, s.year_level, s.last_name, s.first_name LIMIT 2000";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $r['cleared'] = $totalReq > 0 && (int)$r['done'] >= $totalReq;
        if (($f['clearance'] ?? '') === 'cleared' && !$r['cleared']) continue;
        if (($f['clearance'] ?? '') === 'not' && $r['cleared']) continue;
        $rows[] = $r;
    }
    return [$rows, $totalReq];
}