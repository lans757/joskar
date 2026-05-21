<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('GERENCIA')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}
$pageTitle = "ProteoERP | Gerencia";
$activePage = "gerencia";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-title">
            <h1><i class="fas fa-briefcase"></i> Indicadores Gerenciales</h1>
            <p>Panel ejecutivo consolidado — ventas, cartera, inventario y compras.</p>
        </div>

        <div class="metrics-grid">
            <a href="gerencia_ventas.php" class="card metric-card primary" style="text-decoration:none;">
                <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ventas y Margen</span>
                    <p class="metric-value" style="font-size:1.4rem;">Resumen Comercial</p>
                    <span class="metric-trend">Bs / USD · Ticket prom. · MoM / YoY · Margen bruto</span>
                </div>
            </a>

            <a href="gerencia_cartera.php" class="card metric-card success" style="text-decoration:none;">
                <div class="metric-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Cartera y Cobranzas</span>
                    <p class="metric-value" style="font-size:1.4rem;">Recuperación</p>
                    <span class="metric-trend">DSO · Antigüedad de saldos · Recaudo por gestor</span>
                </div>
            </a>

            <a href="gerencia_inventario.php" class="card metric-card warning" style="text-decoration:none;">
                <div class="metric-icon"><i class="fas fa-boxes"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Inventario y Rotación</span>
                    <p class="metric-value" style="font-size:1.4rem;">Stock Estratégico</p>
                    <span class="metric-trend">Valor · Rotación · Días stock · Sin movimiento</span>
                </div>
            </a>

            <a href="gerencia_compras.php" class="card metric-card alert" style="text-decoration:none;">
                <div class="metric-icon"><i class="fas fa-truck-loading"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Compras y Rankings</span>
                    <p class="metric-value" style="font-size:1.4rem;">Proveedores · Clientes</p>
                    <span class="metric-trend">Compras periodo · Top proveedores · Top clientes y vendedores</span>
                </div>
            </a>
        </div>
    </div>
</main>
<?php include('../../includes/footer.php'); ?>
