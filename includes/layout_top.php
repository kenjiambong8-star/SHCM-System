<?php
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$nav = [
    'dashboard' => ['Dashboard', '/pages/dashboard.php'],
    'students'  => ['Students', '/pages/students.php'],
    'records'   => ['Health records', '/pages/records.php'],
    'reports'   => ['Reports', '/pages/reports.php'],
];
if ($isAdmin) {
    $nav['activity'] = ['Activity log', '/pages/activity_log.php'];
    $nav['users']    = ['Users', '/pages/users.php'];
    $nav['backup']   = ['Backup and restore', '/pages/backup.php'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'SHCM System') ?> - SHCM System</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="mbar"><button id="menuBtn" type="button" aria-expanded="false" aria-controls="sideNav">Menu</button><span>SHCM System</span></div>
<div class="app">
  <nav class="nav" id="sideNav" aria-label="Main">
    <div class="logo" style="display:flex;align-items:center;gap:10px"><img src="/assets/img/logo.png" alt="" width="32" height="32" style="background:#fff;border-radius:8px;padding:3px;height:32px;width:auto">SHCM System</div>
    <div class="who"><?= e($_SESSION['fullname'] ?? $_SESSION['username']) ?> (<?= e($_SESSION['role']) ?>)</div>
    <?php foreach ($nav as $key => [$label, $url]): ?>
      <a href="<?= e($url) ?>" class="<?= ($active ?? '') === $key ? 'on' : '' ?>" <?= ($active ?? '') === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <form method="post" action="/auth/logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="out" type="submit">Log out</button>
    </form>
  </nav>
  <main class="main">
  <?php if (!empty($_SESSION['flash'])): [$type, $msg] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="<?= $type === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($msg) ?></div>
  <?php endif; ?>