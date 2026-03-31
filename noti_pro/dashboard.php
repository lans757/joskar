<?php
$pageTitle = "ProteoERP | Dashboard General";
$activePage = "dashboard";
$path_prefix = "";

include('includes/header.php');
include('includes/sidebar.php');
?>

<main class='main-content'>
    <div class="content-wrapper">
        <div class="page-title">
            <h1>Módulos de Gestión</h1>
            <p>Resumen operativo de los distintos departamentos de la empresa.</p>
        </div>

        <div class="warehouse-grid">
            <!-- Televentas -->
            <a href="vistas/vista_televentas.php" class="card dept-card">
                <div>
                    <div class="dept-icon"><i class="fas fa-headset"></i></div>
                    <h3>Televentas</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Monitoreo de pedidos y efectividad comercial.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot"></div> En línea • 12 Agentes activos
                </div>
            </a>

            <!-- Compras -->
            <a href="vistas/vista_compras.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--accent-yellow);"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Compras</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Gestión de órdenes y recepción de mercancía.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot" style="background: var(--accent-yellow); box-shadow: 0 0 10px var(--accent-yellow);"></div> 3 Órdenes pendientes
                </div>
            </a>

            <!-- Administración -->
            <a href="vistas/vista_administracion.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--primary);"><i class="fas fa-building"></i></div>
                    <h3>Administración</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Control de bancos, gastos e informes contables.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot"></div> Conciliación al día
                </div>
            </a>

            <!-- Cobranzas -->
            <a href="vistas/vista_cobranzas.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--accent-red);"><i class="fas fa-hand-holding-usd"></i></div>
                    <h3>Cobranzas</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Recuperación de cartera y análisis de riesgo.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot" style="background: var(--accent-red); box-shadow: 0 0 10px var(--accent-red);"></div> Alerta de morosidad
                </div>
            </a>

            <!-- Almacén -->
            <a href="vistas/vista_almacen.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--primary);"><i class="fas fa-warehouse"></i></div>
                    <h3>Almacén Inventory</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Control de stock físico, rotación y alertas críticas.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot"></div> 2366 Items registrados
                </div>
            </a>

            <!-- Gerencia -->
            <a href="vistas/vista_gerencia.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--accent-green);"><i class="fas fa-chart-line"></i></div>
                    <h3>Gerencia</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Reportes de rentabilidad y KPI estratégicos.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot"></div> Reporte Mensual Generado
                </div>
            </a>
        </div>
    </div>
</main>

<?php
include('includes/footer.php');
?>
