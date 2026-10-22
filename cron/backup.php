<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/backup_lib.php';
$type = ($argv[1] ?? 'db') === 'full' ? 'full' : 'db';
$name = create_backup($pdo, $type);
$pdo->prepare("INSERT INTO activity_logs (user_id, username, action, details, ip_address) VALUES (NULL, 'system', 'BACKUP', ?, 'cli')")
    ->execute(["Automatic $type backup: $name"]);
echo "Created $name\n";