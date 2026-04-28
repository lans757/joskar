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
            "SELECT u.us_codigo, u.us_nombre, u.supervisor, u.us_clave,
                    COALESCE(a.activo, 'S') AS np_activo,
                    COALESCE(a.remoto, 'N') AS np_remoto
             FROM usuario u
             LEFT JOIN notipro_acceso a ON a.us_codigo = u.us_codigo
             WHERE u.us_codigo = ?"
        );
        $stmt->execute([$user]);
        $userData = $stmt->fetch();

        $valid = false;
        if ($userData) {
            $stored = (string)$userData['us_clave'];
            // Proteo guarda la clave en texto plano en usuario.us_clave (CHAR(12)).
            // No re-hasheamos: la columna trunca a 12 y corrompe cualquier hash bcrypt.
            if (hash_equals($stored, $pass)) {
                $valid = true;
            }
        }

        if ($valid) {
            /* 
            if (strtoupper(trim((string)$userData['np_activo'])) !== 'S') {
                header('Location: index.php?error=inactive');
                exit;
            }
            */

            /*
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $isRemote = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;

            if ($isRemote && strtoupper(trim((string)$userData['np_remoto'])) !== 'S') {
                header('Location: index.php?error=remote');
                exit;
            }
            */

            session_regenerate_id(true);
            $_SESSION['logged_in']    = true;
            $_SESSION['user_id']      = $userData['us_codigo'];
            $_SESSION['user_name']    = trim($userData['us_nombre']);
            $_SESSION['is_supervisor'] = ($userData['supervisor'] === 'S');
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
