<?php
session_start();

include 'includes/db.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: index.php?error=csrf');
        exit;
    }
    unset($_SESSION['csrf_token']);

    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    try {
            $stmt = $pdo->prepare(
                "SELECT us_codigo, us_nombre, supervisor, us_clave, activo, remoto
                 FROM usuario 
                 WHERE us_codigo = ? AND activo = 'S'"
            );
        $stmt->execute([$user]);
        $userData = $stmt->fetch();

        $valid = false;
        if ($userData) {
            $clave = (string)$userData['us_clave'];
            
            // Verificación de la clave estrictamente en texto plano
            if ($pass === $clave) {
                $valid = true;
            }
        }

        if ($valid) {
            require_once 'includes/lan_check.php';
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
            
            $es_remoto_permitido = (strtoupper(trim((string)$userData['remoto'])) === 'S');
            
            if (!is_local_ip($client_ip) && !$es_remoto_permitido) {
                header('Location: index.php?error=remote');
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['logged_in']    = true;
            $_SESSION['user_id']      = $userData['us_codigo'];
            $_SESSION['user_name']    = trim($userData['us_nombre']);
            $_SESSION['is_supervisor'] = in_array(strtoupper(trim((string)$userData['us_codigo'])), ['PRUEBAS', 'LCARIPA']);
            $_SESSION['remoto']       = strtoupper(trim((string)$userData['remoto']));
            header('Location: dashboard.php');
            exit;
        }

        header('Location: index.php?error=auth');
        exit;

    } catch (Exception $e) {
        error_log('login.php error: ' . $e->getMessage());
        header('Location: index.php?error=db');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
