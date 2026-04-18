<?php
$_env_file = __DIR__ . '/../.env';
$_env = [];
if (file_exists($_env_file)) {
    $parsed = parse_ini_file($_env_file);
    if ($parsed !== false) {
        $_env = $parsed;
    }
}

$config = [
    'host' => $_env['DB_HOST'] ?? '127.0.0.1',
    'user' => $_env['DB_USER'] ?? 'root',
    'pass' => $_env['DB_PASS'] ?? '',
    'db'   => $_env['DB_NAME'] ?? 'datasis',
    'port' => $_env['DB_PORT'] ?? '3306',
];

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    if (file_exists(__DIR__ . '/../errors/500.php')) {
        include __DIR__ . '/../errors/500.php';
    } else {
        echo "<h1>Error 500: Database Connection Failed</h1>";
        if (($_env['APP_DEBUG'] ?? 'false') === 'true') {
            echo "<p>{$e->getMessage()}</p>";
        }
    }
    exit;
}
?>
