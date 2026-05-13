<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = "ProteoERP | Gerencia";
$activePage = "gerencia";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');
?>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-title">
            <h1>Indicadores Gerencia</h1>
            <p>Módulo de monitoreo para el departamento de Gerencia.</p>
        </div>
        
        <div class="card" style="padding: 40px; text-align: center; color: var(--text-muted);">
            <i class="fas fa-tools" style="font-size: 3rem; margin-bottom: 20px; color: var(--primary);"></i>
            <h3>Módulo en Desarrollo</h3>
            <p>Esta sección estará disponible próximamente.</p>
        </div>
    </div>
</main>
<?php
include('../includes/footer.php');
?>