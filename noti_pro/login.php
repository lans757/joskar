<?php
session_start();

include('includes/db.php');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    try {
        // Reuse the PDO connection already created by db.php
        $stmt = $pdo->prepare(
            "SELECT us_codigo, us_nombre, supervisor FROM usuario WHERE us_codigo = ? AND us_clave = ?"
        );
        $stmt->execute([$user, $pass]);
        $userData = $stmt->fetch();

        if ($userData) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userData['us_codigo'];
            $_SESSION['user_name'] = trim($userData['us_nombre']);
            $_SESSION['is_supervisor'] = ($userData['supervisor'] === 'S');

            header('Location: dashboard.php');
            exit;
        } else {
            header('Location: index.php?error=auth');
            exit;
        }

    } catch (Exception $e) {
        header('Location: index.php?error=db');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
