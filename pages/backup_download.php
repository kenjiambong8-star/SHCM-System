<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_lib.php';
require_admin();
$f = $_GET['f'] ?? '';
$path = backup_dir() . '/' . $f;
if (!valid_backup_name($f) || !is_file($path)) { http_response_code(404); exit('File not found.'); }
log_activity('BACKUP_DOWNLOAD', "Downloaded $f");
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $f . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;