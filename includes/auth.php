<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function _app_web_base() {
    $appRoot = dirname(__DIR__);
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appRoot = str_replace('\\', '/', $appRoot);
    if ($docRoot && strpos($appRoot, $docRoot) === 0) {
        $base = substr($appRoot, strlen($docRoot));
        return $base === '' ? '' : $base;
    }
    return '/' . basename($appRoot);
}

function require_login() {
    if (empty($_SESSION['logged_in'])) {
        $base = _app_web_base();
        header('Location: ' . $base . '/index.php?error=session');
        exit;
    }
}

function require_login_json() {
    if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado', 'logged_in' => false]);
        exit;
    }
}

function require_supervisor() {
    require_login();
    if (empty($_SESSION['is_supervisor'])) {
        $base = _app_web_base();
        header('Location: ' . $base . '/errors/400.php');
        exit;
    }
}

function has_module_access($area_key) {
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = strtoupper(trim($_SESSION['user_id']));
    $json_file = __DIR__ . '/accesos.json';
    
    if (!file_exists($json_file)) {
        return false;
    }
    
    $json_data = file_get_contents($json_file);
    $accesos = json_decode($json_data, true);
    
    if (!is_array($accesos)) {
        return false;
    }
    
    if (isset($accesos[$area_key]) && is_array($accesos[$area_key])) {
        // Hacemos la busqueda ignorando mayusculas/minusculas por seguridad
        $area_users = array_map('strtoupper', $accesos[$area_key]);
        return in_array($user_id, $area_users);
    }
    
    return false;
}
