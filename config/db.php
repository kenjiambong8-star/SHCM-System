<?php
$dsn = "mysql:host=127.0.0.1;port=3306;dbname=shcm_system;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, 'shcm_user', 'Shcm2026Secure', $options);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Database connection failed.');
}
