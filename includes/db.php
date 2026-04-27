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
    // If host is localhost or 127.0.0.1, try to use 127.0.0.1 via TCP to avoid socket issues
    $dsn_host = ($config['host'] === 'localhost' || $config['host'] === '127.0.0.1') ? '127.0.0.1' : $config['host'];
    $dsn = "mysql:host={$dsn_host};port={$config['port']};dbname={$config['db']};charset=utf8mb4";
    
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    
    // Display detailed error if debug is on
    if (($_env['APP_DEBUG'] ?? 'false') === 'true') {
        echo "<div style='background: #fee2e2; color: #991b1b; padding: 20px; border: 1px solid #f87171; margin: 20px; font-family: sans-serif;'>";
        echo "<h1>Database Connection Error (DEBUG)</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Host:</strong> {$config['host']} (Target: $dsn_host)</p>";
        echo "<p><strong>User:</strong> {$config['user']}</p>";
        echo "<p><strong>DB:</strong> {$config['db']}</p>";
        echo "</div>";
    }

    if (file_exists(__DIR__ . '/../errors/500.php')) {
        include __DIR__ . '/../errors/500.php';
    } else {
        echo "<h1>Error 500: Database Connection Failed</h1>";
    }
    exit;
}
?>
