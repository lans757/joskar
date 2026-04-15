<?php

/*$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db'   => 'datasis'
];
*/
$config = [
    'host' => 'localhost',
    'user' => 'datasis',
    'pass' => '1234',
    'db'   => 'datasis'
];
try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8", $config['user'], $config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
