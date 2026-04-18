<?php
$_env = file_exists(__DIR__ . '/../.env') ? parse_ini_file(__DIR__ . '/../.env') : [];

$config = [
    'host' => $_env['DB_HOST'] ?? 'localhost',
    'user' => $_env['DB_USER'] ?? '',
    'pass' => $_env['DB_PASS'] ?? '',
    'db'   => $_env['DB_NAME'] ?? '',
];
try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8", $config['user'], $config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    include __DIR__ . '/../errors/500.php';
    exit;
}
?>
