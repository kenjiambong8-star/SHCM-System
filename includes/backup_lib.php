<?php
const BACKUP_TABLES = ['users', 'requirements', 'students', 'health_records', 'activity_logs'];

function backup_dir(): string {
    $d = __DIR__ . '/../backups';
    if (!is_dir($d)) mkdir($d, 0750, true);
    return realpath($d);
}
function valid_backup_name(string $n): bool {
    return (bool)preg_match('/^shcm_(db|full)_\d{8}-\d{6}_[a-f0-9]{8}\.(sql|zip)$/', $n);
}
function dump_database(PDO $pdo): string {
    $out = "-- SHCM System data backup\n-- Created: " . date('Y-m-d H:i:s') . "\n";
    foreach (BACKUP_TABLES as $t) {
        $out .= "-- TABLE $t\n";
        $stmt = $pdo->query("SELECT * FROM `$t`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($row as $v) $vals[] = $v === null ? 'NULL' : $pdo->quote((string)$v);
            $out .= "INSERT INTO `$t` (`" . implode('`,`', array_keys($row)) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    return $out;
}
function create_backup(PDO $pdo, string $type): string {
    $dir = backup_dir();
    $stamp = date('Ymd-His'); $rand = bin2hex(random_bytes(4));
    $sql = dump_database($pdo);
    if ($type !== 'full') {
        $name = "shcm_db_{$stamp}_{$rand}.sql";
        file_put_contents("$dir/$name", $sql, LOCK_EX);
        return $name;
    }
    if (!class_exists('ZipArchive')) throw new RuntimeException('The PHP zip extension is not installed.');
    $name = "shcm_full_{$stamp}_{$rand}.zip";
    $zip = new ZipArchive();
    if ($zip->open("$dir/$name", ZipArchive::CREATE) !== true) throw new RuntimeException('Could not create the zip file.');
    $zip->addFromString('database.sql', $sql);
    $root = realpath(__DIR__ . '/..');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($root) + 1);
        if (preg_match('#^(backups|\.git|node_modules)(/|$)#', $rel) || basename($rel) === '.DS_Store') continue;
        if ($file->isFile()) $zip->addFile($file->getPathname(), 'files/' . $rel);
    }
    $zip->close();
    return $name;
}
function list_backups(): array {
    $out = [];
    foreach (glob(backup_dir() . '/shcm_*') ?: [] as $p) {
        $n = basename($p);
        if (!valid_backup_name($n)) continue;
        $out[] = ['name' => $n, 'size' => filesize($p), 'time' => filemtime($p),
                  'type' => str_contains($n, '_full_') ? 'Full (files + database)' : 'Database only'];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}
function read_backup_sql(string $path, string $ext): string {
    if (filesize($path) > 50 * 1024 * 1024) throw new RuntimeException('Backup file is too large.');
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Cannot open the zip file.');
        $sql = $zip->getFromName('database.sql');
        $zip->close();
        if ($sql === false) throw new RuntimeException('database.sql not found inside the zip.');
        return $sql;
    }
    return file_get_contents($path);
}
function restore_database(PDO $pdo, string $sql): int {
    $count = 0;
    $allowed = '/^INSERT INTO `(' . implode('|', BACKUP_TABLES) . ')` /';
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();
    try {
        foreach (array_reverse(BACKUP_TABLES) as $t) $pdo->exec("DELETE FROM `$t`");
        foreach (preg_split('/\R/', $sql) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '--')) continue;
            if (!preg_match($allowed, $line)) throw new RuntimeException('The file contains an unexpected statement.');
            $pdo->exec($line);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $e;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return $count;
}