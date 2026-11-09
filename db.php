<?php
// db.php: opens ONE connection to the database that other files can reuse.
require_once __DIR__ . '/config.php';

$dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
if ($config['db_ssl_ca'] !== '') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $config['db_ssl_ca'];  // encrypt the connection (Azure)
}

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());  // details go to the server log
    die('Could not connect to the database.');                     // visitors see only this
}