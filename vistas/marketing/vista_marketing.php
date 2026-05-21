<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('MARKETING')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}

$pageTitle = "ProteoERP | Indicadores Marketing";
$activePage = "marketing";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');

?>

<main class="main-content">
<div class="content-wrapper">
    <div class="page-title">
        <h1><i class="fas fa-bullhorn" style="color: #3b82f6;"></i> Dashboard de Marketing</h1>
        <p>Vista general de indicadores de marketing y desempeño de campañas.</p>
    </div>

    <!-- Filtros de fecha y demás se agregarán aquí -->
    <div class="card" style="margin-top: 20px;">
        <div class="t-header">
            <h3><i class="fas fa-filter"></i> Filtros (Próximamente)</h3>
        </div>
        <p style="padding: 15px; color: var(--text-muted);">
            En esta sección se agregarán los filtros de fechas, campañas, etc.
        </p>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid" style="margin-top: 20px;">
        <!-- KPI Cards placeholders -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="kpi-info">
                <h3>KPI 1</h3>
                <div class="kpi-value" id="kpi-1-value">--</div>
                <div class="kpi-desc">Esperando definición</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-info">
                <h3>KPI 2</h3>
                <div class="kpi-value" id="kpi-2-value">--</div>
                <div class="kpi-desc">Esperando definición</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="fas fa-bullseye"></i>
            </div>
            <div class="kpi-info">
                <h3>KPI 3</h3>
                <div class="kpi-value" id="kpi-3-value">--</div>
                <div class="kpi-desc">Esperando definición</div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                <i class="fas fa-funnel-dollar"></i>
            </div>
            <div class="kpi-info">
                <h3>KPI 4</h3>
                <div class="kpi-value" id="kpi-4-value">--</div>
                <div class="kpi-desc">Esperando definición</div>
            </div>
        </div>
    </div>

    <!-- Tablas o Gráficos -->
    <div class="card" style="margin-top: 20px;">
        <div class="t-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3><i class="fas fa-table"></i> Datos Principales</h3>
        </div>
        
        <div class="table-container" style="padding: 15px;">
            <p style="color: var(--text-muted); text-align: center;">
                <em>Las tablas y gráficos se construirán una vez se definan las consultas SQL y los KPIs.</em>
            </p>
        </div>
    </div>
</div>
</main>

<script>
// Scripts para interactividad (gráficos, ajax) irán aquí
console.log("Vista de marketing cargada. Esperando definición de métricas.");
</script>

<?php include('../../includes/footer.php'); ?>
