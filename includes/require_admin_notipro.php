<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const NOTIPRO_ADMIN_USER = 'PRUEBAS';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $prefix = isset($path_prefix) ? $path_prefix : '';
    header('Location: ' . $prefix . 'index.php');
    exit;
}

if (!isset($_SESSION['user_id']) || strcasecmp(trim((string)$_SESSION['user_id']), NOTIPRO_ADMIN_USER) !== 0) {
    http_response_code(403);
    $prefix = isset($path_prefix) ? $path_prefix : '';
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403</title>';
    echo '<link rel="stylesheet" href="' . $prefix . 'assets/css/style.css"></head><body class="error-page-body error-403">';
    echo '<div class="error-container"><h1>Acceso denegado</h1>';
    echo '<p>Solo el administrador del sistema puede acceder a esta sección.</p></div></body></html>';
    exit;
}
