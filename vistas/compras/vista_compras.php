<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('COMPRAS')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}
$pageTitle = "ProteoERP | Compras";
$activePage = "compras";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-title">
            <h1>Indicadores Compras</h1>
            <p>Módulo de monitoreo para el departamento de Compras.</p>
        </div>
        
        <div class="card" style="padding: 40px; text-align: center; color: var(--text-muted);">
            <i class="fas fa-tools" style="font-size: 3rem; margin-bottom: 20px; color: var(--primary);"></i>
            <h3>Módulo en Desarrollo</h3>
            <p>Esta sección estará disponible próximamente.</p>
        </div>
    </div>
</main>
<?php
include('../../includes/footer.php');
?>