<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pageTitle = "ProteoERP | Dashboard General";
$activePage = "dashboard";
$path_prefix = "";

include('includes/header.php');
include('includes/sidebar.php');
require_once 'includes/db.php';

// Variables para métricas en tiempo real
$televentas_pedidos = 0;
$almacen_stock = 0;
$almacen_critico = 0;
$cobranzas_ops = 0;
$compras_pendientes = 0;
$admin_bancos = 0;
$gerencia_ventas = 0;

try {
    // 1. Televentas - Pedidos pendientes con productos (de view_pedidospen)
    $stmt_tele = $pdo->query("SELECT COUNT(DISTINCT numero) FROM view_pedidospen");
    $televentas_pedidos = (int)$stmt_tele->fetchColumn();

    // 2. Almacén - Total de items en stock para Almacén 0001
    $stmt_alm = $pdo->query("SELECT COUNT(*) FROM itsinv WHERE existen >= 1 AND alma = '0001'");
    $almacen_stock = (int)$stmt_alm->fetchColumn();

    // 3. Almacén - Items en nivel crítico (existencia < stock mínimo)
    $stmt_crit = $pdo->query("
        SELECT COUNT(*) 
        FROM itsinv i 
        JOIN sinv b ON i.codigo = b.codigo 
        WHERE i.alma = '0001' AND i.existen >= 1 AND b.exmin > i.existen
    ");
    $almacen_critico = (int)$stmt_crit->fetchColumn();

    // 4. Cobranzas - Operaciones de cobranzas registradas hoy
    $stmt_cob = $pdo->query("SELECT COUNT(*) FROM smov WHERE fecha = CURDATE()");
    $cobranzas_ops = (int)$stmt_cob->fetchColumn();
    
    // 5. Administración - Bancos activos en el sistema
    $stmt_admin = $pdo->query("SELECT COUNT(*) FROM banc WHERE activo = 'S'");
    $admin_bancos = (int)$stmt_admin->fetchColumn();

    // 6. Gerencia - Facturación acumulada en el último período (30 días desde la última operación)
    // Se usa MAX(fecha) para que en el demo se vea data aunque la base esté desactualizada
    $stmt_ger = $pdo->query("SELECT COUNT(*) FROM smov WHERE tipo_doc = 'FC' AND fecha >= DATE_SUB((SELECT MAX(fecha) FROM smov), INTERVAL 30 DAY)");
    $gerencia_ventas = (int)$stmt_ger->fetchColumn();

    // 7. Compras - Órdenes de compra pendientes
    $stmt_comp = $pdo->query("SELECT COUNT(*) FROM ordc WHERE status = 'PE'");
    $compras_pendientes = (int)$stmt_comp->fetchColumn();

    // 8. Inventario Hardware - Total de equipos
    $stmt_inv = $pdo->query("SELECT COUNT(*) FROM invsis");
    $inventario_equipos = (int)$stmt_inv->fetchColumn();

} catch (PDOException $e) {
    // En caso de error, mantener valores por defecto o 0
    $inventario_equipos = 0;
}
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
                    <div class="dept-icon" style="color: var(--accent-green);"><i class="fas fa-headset"></i></div>
                    <h3>Televentas</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Monitoreo de pedidos y efectividad comercial.</p>
                </div>
                <div class="dept-status">
                    <?php if ($televentas_pedidos > 0): ?>
                        <div class="status-dot" style="background: var(--accent-green); box-shadow: 0 0 10px var(--accent-green);"></div> <?php echo $televentas_pedidos; ?> Pedidos pendientes
                    <?php else: ?>
                        <div class="status-dot"></div> Sin pedidos pendientes
                    <?php endif; ?>
                </div>
            </a>

            <!-- Compras -->
            <a href="vistas/vista_compras.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--accent-yellow);"><i class="fas fa-shopping-basket"></i></div>
                    <h3>Compras</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Gestión de órdenes, proveedores y mercancia.</p>
                </div>
                <div class="dept-status">
                    <?php if ($compras_pendientes > 0): ?>
                        <div class="status-dot" style="background: var(--accent-yellow); box-shadow: 0 0 10px var(--accent-yellow);"></div> <?php echo $compras_pendientes; ?> Órdenes activas
                    <?php else: ?>
                        <div class="status-dot"></div> Sin órdenes activas
                    <?php endif; ?>
                </div>
            </a>

            <!-- Administración -->
            <a href="vistas/vista_administracion.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--primary);"><i class="fas fa-landmark"></i></div>
                    <h3>Administración</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Gestión bancaria, gastos e informes contables.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot" style="background: var(--primary); box-shadow: 0 0 10px var(--primary);"></div> <?php echo $admin_bancos; ?> Entidades operativas
                </div>
            </a>

            <!-- Cobranzas -->
            <a href="vistas/vista_cobranzas.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--accent-red);"><i class="fas fa-coins"></i></div>
                    <h3>Cobranzas</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Recuperación de cartera y conciliación de pagos.</p>
                </div>
                <div class="dept-status">
                    <?php if ($cobranzas_ops > 0): ?>
                        <div class="status-dot" style="background: var(--accent-red); box-shadow: 0 0 10px var(--accent-red);"></div> <?php echo $cobranzas_ops; ?> Operaciones hoy
                    <?php else: ?>
                        <div class="status-dot" style="background: var(--accent-red); box-shadow: 0 0 10px var(--accent-red); opacity: 0.3;"></div> Sin operaciones hoy
                    <?php endif; ?>
                </div>
            </a>

            <!-- Almacén -->
            <a href="vistas/vista_almacen.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: var(--accent-orange);"><i class="fas fa-warehouse"></i></div>
                    <h3>Almacén</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Control de stock físico, rotación y alertas críticas.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot" style="background: var(--accent-orange); box-shadow: 0 0 10px var(--accent-orange);"></div> <?php echo number_format($almacen_stock, 0, ',', '.'); ?> Items en existencia
                    <?php if ($almacen_critico > 0): ?>
                         <span style="color:var(--accent-red); font-weight:bold; margin-left: 8px;">(<?php echo $almacen_critico; ?> Críticos)</span>
                    <?php endif; ?>
                </div>
            </a>

            <!-- Gerencia -->
            <a href="vistas/vista_gerencia.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: #a855f7;"><i class="fas fa-chart-pie"></i></div>
                    <h3>Gerencia</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Análisis de rentabilidad y KPI estratégicos.</p>
                </div>
                <div class="dept-status">
                    <?php if ($gerencia_ventas > 0): ?>
                        <div class="status-dot" style="background: #a855f7; box-shadow: 0 0 10px #a855f7;"></div> <?php echo number_format($gerencia_ventas, 0, ',', '.'); ?> Facturas (30d)
                    <?php else: ?>
                        <div class="status-dot"></div> Sin ventas recientes
                    <?php endif; ?>
                </div>
            </a>

            <!-- Inventario Hardware -->
            <a href="vistas/vista_inventario_hardware.php" class="card dept-card">
                <div>
                    <div class="dept-icon" style="color: #00e676;"><i class="fas fa-laptop-code"></i></div>
                    <h3>Inventario Hardware</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Control de activos y equipos asignados al personal.</p>
                </div>
                <div class="dept-status">
                    <div class="status-dot" style="background: #00e676; box-shadow: 0 0 10px #00e676;"></div> <?php echo $inventario_equipos; ?> Equipos registrados
                </div>
            </a>
        </div>
    </div>
</main>

<?php
include('includes/footer.php');
?>
