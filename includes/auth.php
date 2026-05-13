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
