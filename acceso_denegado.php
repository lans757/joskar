<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pageTitle = "Acceso Denegado";
include('includes/header.php');
include('includes/sidebar.php');
?>
<main class='main-content'>
    <div class="content-wrapper" style="text-align: center; margin-top: 100px;">
        <h1 style="color: var(--accent-red); font-size: 3rem;"><i class="fas fa-ban"></i> Acceso Denegado</h1>
        <p style="font-size: 1.2rem; margin-top: 20px;">No tienes permisos suficientes para acceder a este módulo.</p>
        <p style="color: var(--text-muted); margin-top: 10px;">Consulta con el administrador si crees que esto es un error.</p>
        <a href="dashboard.php" class="btn btn-primary" style="margin-top: 30px; display: inline-block;">
            <i class="fas fa-home"></i> Volver al Inicio
        </a>
    </div>
</main>
<?php include('includes/footer.php'); ?>
