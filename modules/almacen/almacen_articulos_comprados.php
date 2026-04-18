<?php
/**
 * ============================================================
 * LISTADO DE ARTÍCULOS COMPRADOS - PROTEOERP
 * Reporte consolidado con desglose por proveedor
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['logged_in'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
    } else {
        header('Location: ../../index.php');
    }
    exit;
}

require_once '../../includes/db.php';

// --- Manejo AJAX: Gráfico Proveedor ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'proveedores') {
    $f_ini = $_GET['f_ini'] ?? date('Y-01-01');
    $f_fin = $_GET['f_fin'] ?? date('Y-m-d');
    
    $where = "WHERE c.recep >= :ini AND c.recep <= :fin";
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    
    $f_txt = $_GET['f_txt'] ?? '';
    if (!empty($f_txt) && strlen(trim($f_txt)) >= 2) {
        $txt_search = "%" . str_replace(" ", "%", trim($f_txt)) . "%";
        $where .= " AND (dc.descrip LIKE :txt OR dc.codigo LIKE :txt OR p.nombre LIKE :txt)";
        $params[':txt'] = $txt_search;
    } else {
        $f_txt = '';
    }
    
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.proveed as codigo,
                p.nombre as proveedor,
                COUNT(DISTINCT dc.codigo) as total_articulos,
                SUM(dc.cantidad) as cantidad_total,
                SUM(dc.importerd) as monto_total
            FROM itscst dc
            INNER JOIN scst c ON dc.numero = c.numero
            INNER JOIN sprv p ON c.proveed = p.proveed
            $where
            GROUP BY p.proveed, p.nombre
            ORDER BY monto_total DESC
        ");
        $stmt->execute($params);
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $proveedores
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error AJAX: ' . $e->getMessage()
        ]);
    }
    exit;
}

// --- Manejo AJAX: Filtrar por Proveedor ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filtrar') {
    $codprov = $_GET['codprov'] ?? '';
    $f_ini   = $_GET['f_ini']   ?? date('Y-01-01');
    $f_fin   = $_GET['f_fin']   ?? date('Y-m-d');
    $f_txt = $_GET['f_txt'] ?? '';

    header('Content-Type: application/json');
    try {
        $params = [':ini' => $f_ini, ':fin' => $f_fin];
        $where = " AND c.recep >= :ini AND c.recep <= :fin ";
        
        if (!empty($codprov)) {
            $where .= " AND c.proveed = :codprov ";
            $params[':codprov'] = $codprov;
        }
        
        if (!empty($f_txt) && strlen(trim($f_txt)) >= 2) {
            $txt_search = "%" . str_replace(" ", "%", trim($f_txt)) . "%";
            $where .= " AND (dc.descrip LIKE :txt OR dc.codigo LIKE :txt OR p.nombre LIKE :txt)";
            $params[':txt'] = $txt_search;
        } else {
            $f_txt = '';
        }

        $stmt = $pdo->prepare("
            SELECT 
                dc.codigo,
                dc.descrip as descripcion,
                dc.fecha,
                p.proveed as codprov,
                p.nombre as nombre_proveedor,
                dc.cantidad as cantidad_actual,
                ROUND(dc.costo, 2) as precio,
                ROUND(dc.costord, 2) as precio_d,
                ROUND(dc.cantidad * dc.costo, 2) as total,
                ROUND(dc.importerd, 2) as total_d,
                ROUND(dc.iva / CASE WHEN dc.costo > 0 THEN dc.costo ELSE 1 END * dc.costord * dc.cantidad, 2) as iva_d
            FROM itscst dc
            INNER JOIN scst c ON dc.numero = c.numero
            INNER JOIN sprv p ON c.proveed = p.proveed
            WHERE 1=1
            $where
            ORDER BY dc.fecha DESC, dc.codigo
        ");
        $stmt->execute($params);
        $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $articulos,
            'count' => count($articulos)
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// --- Parámetros iniciales ---
$pageTitle   = "ProteoERP | Artículos Comprados";
$activePage  = "almacen";
$activeSubPage = "articulos_comprados";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');

// --- Filtros ---
$f_ini = $_GET['f_ini'] ?? date('Y-01-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');
$f_prov = $_GET['f_prov'] ?? '';
$f_txt = $_GET['f_txt'] ?? '';

// --- Paginación ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Consultas principales ---
try {
    // Lista de proveedores para filtro
    $stmt_prov = $pdo->query("
        SELECT DISTINCT c.proveed, p.nombre
        FROM scst c
        INNER JOIN sprv p ON c.proveed = p.proveed
        ORDER BY p.nombre
    ");
    $proveedores_filter = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

    // --- Query Principal: Listado de Artículos Comprados ---
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    $where = "WHERE c.recep >= :ini AND c.recep <= :fin";
    
    if (!empty($f_prov)) {
        $where .= " AND c.proveed = :prov";
        $params[':prov'] = $f_prov;
    }
    if (!empty($f_txt) && strlen(trim($f_txt)) >= 2) {
        $txt_search = "%" . str_replace(" ", "%", trim($f_txt)) . "%";
        $where .= " AND (dc.descrip LIKE :txt OR dc.codigo LIKE :txt OR p.nombre LIKE :txt)";
        $params[':txt'] = $txt_search;
    } else {
        $f_txt = '';
    }

    // KPIs
    $stmt_kpi = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT dc.codigo) as total_articulos,
            SUM(dc.cantidad) as total_cantidad,
            SUM(dc.cantidad * dc.costo) as total_costo,
            COUNT(DISTINCT c.proveed) as total_proveedores,
            COUNT(DISTINCT c.numero) as total_compras
        FROM itscst dc
        INNER JOIN scst c ON dc.numero = c.numero
        INNER JOIN sprv p ON c.proveed = p.proveed
        $where
    ");
    $stmt_kpi->execute($params);
    $kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);

    // Resumen por Proveedor
    $stmt_res = $pdo->prepare("
        SELECT 
            p.proveed as codprov,
            p.nombre as proveedor,
            COUNT(DISTINCT dc.codigo) as articulos,
            SUM(dc.cantidad) as cantidad,
            SUM(dc.cantidad * dc.costo) as monto,
            COUNT(DISTINCT c.numero) as compras
        FROM itscst dc
        INNER JOIN scst c ON dc.numero = c.numero
        INNER JOIN sprv p ON c.proveed = p.proveed
        $where
        GROUP BY p.proveed, p.nombre
        ORDER BY monto DESC
    ");
    $stmt_res->execute($params);
    $resumen_prov = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

    // Detalle de Artículos
    $stmt_det = $pdo->prepare("
        SELECT 
            dc.codigo,
            dc.descrip as descripcion,
            dc.fecha,
            p.proveed as codprov,
            p.nombre as nombre_proveedor,
            dc.cantidad as cantidad_actual,
            ROUND(dc.costo, 2) as precio,
            ROUND(dc.costord, 2) as precio_d,
            ROUND(dc.cantidad * dc.costo, 2) as total,
            ROUND(dc.importerd, 2) as total_d,
            ROUND(dc.iva / CASE WHEN dc.costo > 0 THEN dc.costo ELSE 1 END * dc.costord * dc.cantidad, 2) as iva_d
        FROM itscst dc
        INNER JOIN scst c ON dc.numero = c.numero
        INNER JOIN sprv p ON c.proveed = p.proveed
        $where
        ORDER BY dc.fecha DESC, dc.codigo DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt_det->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_det->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_det->bindValue($key, $val);
    }
    $stmt_det->execute();
    $articulos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    // Total de registros para paginación
    $stmt_count = $pdo->prepare("
        SELECT COUNT(DISTINCT dc.codigo) as total
        FROM itscst dc
        INNER JOIN scst c ON dc.numero = c.numero
        INNER JOIN sprv p ON c.proveed = p.proveed
        $where
    ");
    $stmt_count->execute($params);
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_articulos = $count_result['total'] ?? 0;
    $total_pages = ($total_articulos > 0) ? (int)ceil($total_articulos / $limit) : 1;

} catch (PDOException $e) {
    die("Error de base de datos: [" . $e->getCode() . "]: " . $e->getMessage());
}

// Helper: Formatear moneda
if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        return 'Bs. ' . number_format($value, 2, ',', '.');
    }
}

if (!function_exists('formatUSD')) {
    function formatUSD($value) {
        return '$ ' . number_format($value, 2, ',', '.');
    }
}
?>



<main class="main-content">
    <?php include("../../includes/navbar.php"); ?>
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="vista_almacen.php" class="nav-item">
            <i class="fas fa-home"></i> <span>Inicio</span>
        </a>
        <a href="almacen_articulos_comprados.php" class="nav-item active">
            <i class="fas fa-list"></i>
            <span>Artículos Comprados</span>
        </a>
        <a href="almacen_compras_fecha.php" class="nav-item">
            <i class="fas fa-calendar-alt"></i>
            <span>Compras por Fecha</span>
        </a>
        <a href="almacen_top_vendidos.php" class="nav-item">
            <i class="fas fa-trophy"></i> <span>Top Vendidos</span>
        </a>
        <a href="almacen_kpis.php" class="nav-item">
            <i class="fas fa-chart-line"></i> <span>Indicadores KPI</span>
        </a>
    </nav>

    <!-- HEADER -->
    <div class="page-title">
        <h1><i class="fas fa-shopping-cart" style="color:var(--primary);"></i> Listado de Artículos Comprados</h1>
        <p>Reporte consolidado de compras por proveedor y producto &bull; 
           <strong><?php echo date('d/m/Y', strtotime($f_ini)); ?></strong> - <strong><?php echo date('d/m/Y', strtotime($f_fin)); ?></strong>
        </p>
    </div>

    <!-- KPI CARDS -->
    <div class="metrics-grid">
        <div class="card metric-card warning">
            <div class="metric-icon"><i class="fas fa-boxes"></i></div>
            <div class="metric-content">
                <span class="metric-label">Artículos Únicos</span>
                <p class="metric-value"><?php echo number_format($kpis['total_articulos'] ?? 0, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="card metric-card primary">
            <div class="metric-icon"><i class="fas fa-cubes"></i></div>
            <div class="metric-content">
                <span class="metric-label">Cantidad Total</span>
                <p class="metric-value"><?php echo number_format($kpis['total_cantidad'] ?? 0, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="card metric-card success">
            <div class="metric-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="metric-content">
                <span class="metric-label">Costo Total</span>
                <p class="metric-value"><?php echo formatCurrency($kpis['total_costo'] ?? 0); ?></p>
            </div>
        </div>
        <div class="card metric-card info">
            <div class="metric-icon"><i class="fas fa-user-tie"></i></div>
            <div class="metric-content">
                <span class="metric-label">Proveedores</span>
                <p class="metric-value"><?php echo number_format($kpis['total_proveedores'] ?? 0, 0, ',', '.'); ?></p>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card filters-card">
        <div class="filters-header">
            <i class="fas fa-filter"></i>
            <h2>Filtros de búsqueda</h2>
        </div>
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label>Fecha Desde</label>
                <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
            </div>
            <div class="filter-group">
                <label>Fecha Hasta</label>
                <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
            </div>
            <div class="filter-group">
                <label>Proveedor</label>
                <select name="f_prov">
                    <option value="">— TODOS LOS PROVEEDORES —</option>
                    <?php foreach ($proveedores_filter as $prov): ?>
                        <option value="<?php echo $prov['proveed']; ?>" <?php echo ($f_prov == $prov['proveed']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group grow">
                <label>Buscar Artículo / Código</label>
                <div class="sw" style="display:flex; gap:10px;">
                    <input type="text" name="f_txt" id="search-input" value="<?php echo htmlspecialchars($f_txt); ?>" 
                           placeholder="Código o descripción..." style="flex:1;">
                    <button type="submit" class="btn-neon btn-cyan" style="height:48px;">
                        <i class="fas fa-sync-alt"></i> ACTUALIZAR
                    </button>
                </div>
            </div>
        </form>
        <!-- Alerta de búsqueda -->
        <div id="search-alert" class="search-alert" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="search-alert-msg"></span>
            <button onclick="clearSearch()"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- TABS: Gráfico | Tabla -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showTab('chart', event)">
            <i class="fas fa-chart-pie"></i> Análisis por Proveedor
        </button>
        <button class="tab-btn" onclick="showTab('table', event)">
            <i class="fas fa-table"></i> Detalle de Artículos
        </button>
    </div>

    <!-- TAB 1: GRÁFICO DONUT -->
    <div id="chart" class="tab-content active">
        <div class="card table-card">
            <div class="table-header" style="border-bottom: 1px solid var(--border-light); padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="font-size: 1.1rem;"><i class="fas fa-chart-pie"></i> Distribución de Compras por Proveedor</h2>
            </div>
            
            <div class="chart-section" style="padding: 20px;">
                <!-- Gráfico Donut -->
                <div class="chart-container">
                    <canvas id="donutChart" width="400" height="400"></canvas>
                </div>
                
                <!-- Leyenda interactiva -->
                <div>
                    <h4 style="margin-top: 0; color: var(--primary); font-weight: 800;">Resumen por Proveedor</h4>
                    <div class="chart-legend" id="chartLegend">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen consolidado -->
        <div class="card table-card" style="margin-top: 25px;">
            <div class="table-header" style="border-bottom: 1px solid var(--border-light); padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="font-size: 1.1rem;"><i class="fas fa-list-ul"></i> Consolidado por Proveedor</h2>
            </div>
            <div class="table-container">
                <table id="table-consolidado">
                    <thead>
                        <tr>
                            <th>PROVEEDOR</th>
                            <th class="text-center">ARTÍCULOS</th>
                            <th class="text-center">COMPRAS</th>
                            <th class="text-right">CANTIDAD</th>
                            <th class="text-right">MONTO TOTAL</th>
                            <th class="text-right">% DEL TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resumen_prov)): ?>
                            <tr><td colspan="6" class="text-center" style="padding: 48px; opacity: 0.5;">No hay datos para mostrar.</td></tr>
                        <?php else: ?>
                            <?php $total_monto = array_sum(array_column($resumen_prov, 'monto')); ?>
                            <?php foreach ($resumen_prov as $r): ?>
                                <tr onclick="abrirModalArticulos('<?php echo $r['codprov']; ?>', '<?php echo htmlspecialchars(addslashes($r['proveedor'])); ?>')" 
                                    style="cursor: pointer;" title="Haga clic para ver artículos comprados a este proveedor">
                                    <td style="font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($r['proveedor']); ?>
                                    </td>
                                    <td class="text-center"><?php echo $r['articulos']; ?></td>
                                    <td class="text-center"><?php echo $r['compras']; ?></td>
                                    <td class="text-right"><?php echo number_format($r['cantidad'], 0, ',', '.'); ?></td>
                                    <td class="text-right" style="font-weight: 700; color: var(--accent-green);">
                                        <?php echo formatCurrency($r['monto']); ?>
                                    </td>
                                    <td class="text-right" style="color: var(--primary); font-weight: 700;">
                                        <?php echo number_format($total_monto > 0 ? ($r['monto'] / $total_monto) * 100 : 0, 1, ',', '.'); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: TABLA DETALLE -->
    <div id="table" class="tab-content">
        <div class="card table-card">
            <div class="t-header">
                <h2><i class="fas fa-list-ul"></i> Detalle de Artículos Comprados</h2>
                <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center;">
                    Mostrando del <?php echo $offset + 1; ?> al <?php echo min($offset + $limit, $total_articulos); ?> de <strong><?php echo $total_articulos; ?></strong>
                    <button class="btn-neon btn-green" onclick="exportXls('table-articulos', 'Articulos_Comprados')" style="margin-left: 15px; height: 32px; font-size: 0.7rem; padding: 0 12px; display:inline-flex; align-items: center;">
                        <i class="fas fa-file-excel" style="margin-right:5px;"></i> Excel
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table id="table-articulos">
                    <thead>
                        <tr>
                            <th class="text-center">FECHA</th>
                            <th>CÓDIGO</th>
                            <th>DESCRIPCIÓN</th>
                            <th>PROVEEDOR</th>
                            <th class="text-center">CANT.</th>
                            <th class="text-right">PRECIO BS.</th>
                            <th class="text-right">PRECIO $</th>
                            <th class="text-right">TOTAL BS.</th>
                            <th class="text-right">TOTAL $</th>
                            <th class="text-right">IVA $</th>
                            <th class="text-right">TOTAL+IVA $</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($articulos)): ?>
                            <tr><td colspan="11" class="text-center" style="padding: 48px; opacity: 0.5;">No se encontraron registros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($articulos as $art): ?>
                                <tr>
                                    <td class="text-center" style="font-size: 0.75rem; opacity: 0.8;"><?php echo date('d/m/Y', strtotime($art['fecha'])); ?></td>
                                    <td><span class="code-badge"><?php echo $art['codigo']; ?></span></td>
                                    <td style="font-weight: 500; font-size: 0.8rem;"><?php echo htmlspecialchars($art['descripcion']); ?></td>
                                    <td style="color: var(--primary); font-weight: 700; font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($art['nombre_proveedor']); ?>
                                    </td>
                                    <td class="text-center" style="font-weight:700;"><?php echo number_format($art['cantidad_actual'], 0, ',', '.'); ?></td>
                                    <td class="text-right"><?php echo formatCurrency($art['precio']); ?></td>
                                    <td class="text-right" style="color:var(--text-muted);"><?php echo formatUSD($art['precio_d']); ?></td>
                                    <td class="text-right" style="font-weight: 700; color: var(--accent-green); background:rgba(0,180,255,0.02);">
                                        <?php echo formatCurrency($art['total']); ?>
                                    </td>
                                    <td class="text-right" style="font-weight: 700; color: var(--primary);">
                                        <?php echo formatUSD($art['total_d']); ?>
                                    </td>
                                    <td class="text-right" style="font-size:0.8rem; opacity:0.8;">
                                        <?php echo formatUSD($art['iva_d']); ?>
                                    </td>
                                    <td class="text-right" style="font-weight: 800; color: var(--accent-green);">
                                        <?php echo formatUSD($art['total_d'] + $art['iva_d']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <?php
                        $page_total = array_sum(array_column($articulos, 'total'));
                        $page_total_d = array_sum(array_column($articulos, 'total_d'));
                        $page_iva_d = array_sum(array_column($articulos, 'iva_d'));
                        $page_cantidad = array_sum(array_column($articulos, 'cantidad_actual'));
                        ?>
                        <tr style="background: rgba(0,0,0,0.15);">
                            <td colspan="4" class="text-right" style="opacity: 0.7; font-weight: 700;">SUBTOTAL PÁGINA</td>
                            <td class="text-center" style="font-weight: 800;"><?php echo number_format($page_cantidad, 0, ',', '.'); ?></td>
                            <td colspan="2"></td>
                            <td class="text-right" style="font-weight: 800; color: var(--accent-green);">
                                <?php echo formatCurrency($page_total); ?>
                            </td>
                            <td class="text-right" style="font-weight: 800; color: var(--primary);">
                                <?php echo formatUSD($page_total_d); ?>
                            </td>
                            <td class="text-right">
                                <?php echo formatUSD($page_iva_d); ?>
                            </td>
                            <td class="text-right" style="font-weight: 800; color: var(--accent-green);">
                                <?php echo formatUSD($page_total_d + $page_iva_d); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <div class="pagination-wrapper" style="display:flex; justify-content:space-between; align-items:center; padding: 20px;">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                    Página <strong><?php echo $page; ?></strong> de <strong><?php echo $total_pages; ?></strong>
                </div>
                
                <div class="pager-container" style="display:flex; gap:10px; align-items:center;">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="nav-item" style="padding:8px 12px;"><i class="fas fa-angles-left"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="nav-item" style="padding:8px 12px;"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>

                    <span style="font-weight: 700; color: var(--primary);"><?php echo $page; ?></span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="nav-item" style="padding:8px 12px;"><i class="fas fa-angle-right"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="nav-item" style="padding:8px 12px;"><i class="fas fa-angles-right"></i></a>
                    <?php endif; ?>
                </div>
                
                <form method="GET" style="display: flex; align-items: center; gap: 8px;">
                    <?php foreach ($_GET as $k => $v) if ($k !== 'page') echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">'; ?>
                    <span style="font-size: 0.8rem; opacity: 0.6;">Ir a</span>
                    <input type="number" name="page" style="width: 50px; background: var(--bg-input); border: 1px solid var(--border); color: #fff; text-align: center; border-radius: 4px; padding: 4px;" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>">
                    <button type="submit" class="nav-item" style="padding: 4px 8px; border:none; cursor:pointer;"><i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i></button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Detalle de Artículos por Proveedor -->
    <div id="modal-proveedor" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-hd">
                <div>
                    <h3><i class="fas fa-shopping-basket" style="margin-right:10px;"></i>Artículos Comprados</h3>
                    <div class="mref" id="modal-subtitle">Proveedor: —</div>
                </div>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div id="modal-loading" class="text-center" style="padding: 50px; display: none;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2.5rem; color: var(--primary);"></i>
                    <p style="margin-top: 15px; color: var(--text-muted); font-weight: 600;">Consultando base de datos...</p>
                </div>
                <div id="modal-table-container">
                    <div class="table-container">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th class="text-center">FECHA</th>
                                    <th>CÓDIGO</th>
                                    <th>DESCRIPCIÓN</th>
                                    <th class="text-center">CANT.</th>
                                    <th class="text-right">PRECIO BS.</th>
                                    <th class="text-right">PRECIO $</th>
                                    <th class="text-right">TOTAL BS.</th>
                                    <th class="text-right">TOTAL $</th>
                                    <th class="text-right">IVA $</th>
                                    <th class="text-right">TOTAL+IVA $</th>
                                </tr>
                            </thead>
                            <tbody id="modal-table-body">
                                <!-- Se llena vía JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-ft">
                <button class="btn-cerrar" onclick="closeModal()">Cerrar Ventana</button>
            </div>
        </div>
    </div>

</div><!-- /.content-wrapper -->
</main><!-- /.main-content -->

<!-- =============================================================== SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
// Colores para el gráfico
const chartColors = [
    'rgba(0, 180, 255, 0.8)',
    'rgba(0, 230, 118, 0.8)',
    'rgba(255, 193, 7, 0.8)',
    'rgba(255, 87, 51, 0.8)',
    'rgba(156, 39, 176, 0.8)',
    'rgba(33, 150, 243, 0.8)',
    'rgba(76, 175, 80, 0.8)',
    'rgba(255, 152, 0, 0.8)',
    'rgba(244, 67, 54, 0.8)',
    'rgba(103, 58, 183, 0.8)'
];

let donutChart = null;

// Cargar datos del gráfico
async function loadChartData() {
    try {
        const params = new URLSearchParams(window.location.search);
        const url = `?ajax=proveedores&${params.toString()}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) throw new Error(data.error);
        
        renderDonutChart(data.data);
        renderLegend(data.data);
    } catch (err) {
        console.error('Error cargando gráfico:', err);
    }
}

function renderDonutChart(data) {
    const ctx = document.getElementById('donutChart');
    if (!ctx) return;
    
    const labels = data.map(d => d.proveedor);
    const amounts = data.map(d => parseFloat(d.monto_total));
    
    if (donutChart) donutChart.destroy();
    
    donutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: amounts,
                backgroundColor: chartColors.slice(0, labels.length),
                borderColor: 'var(--bg-card)',
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `$ ${value.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}

function renderLegend(data) {
    const legend = document.getElementById('chartLegend');
    if (!legend) return;
    let html = '';
    
    const totalMonto = data.reduce((a, b) => a + parseFloat(b.monto_total), 0);
    
    data.forEach((item, idx) => {
        const percent = totalMonto > 0 ? ((parseFloat(item.monto_total) / totalMonto) * 100).toFixed(1) : 0;
        html += `
            <div class="legend-item" onclick="abrirModalArticulos('${item.codigo}', '${item.proveedor.replace(/'/g, "\\'")}')" style="display:flex; align-items:center; gap:10px; padding:10px; border-bottom:1px solid var(--border-light); cursor:pointer;">
                <div class="legend-color" style="background-color: ${chartColors[idx % chartColors.length]}; width:12px; height:12px; border-radius:50%;"></div>
                <div class="legend-text" style="flex:1;">
                    <strong style="font-size:0.85rem;">${item.proveedor}</strong><br>
                    <small style="color: var(--text-muted);">${item.total_articulos} artículos</small>
                </div>
                <div class="legend-value" style="font-weight:700; color:var(--primary);">${percent}%</div>
            </div>
        `;
    });
    
    legend.innerHTML = html;
}

// Controles de tabs
function showTab(tabName, event) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

// Modal: Mostrar artículos por proveedor
async function abrirModalArticulos(codprov, nombre) {
    const modal = document.getElementById('modal-proveedor');
    const loading = document.getElementById('modal-loading');
    const container = document.getElementById('modal-table-container');
    const tbody = document.getElementById('modal-table-body');
    const subtitle = document.getElementById('modal-subtitle');
    
    subtitle.innerText = `Proveedor: ${nombre} (${codprov})`;
    tbody.innerHTML = '';
    loading.style.display = 'block';
    container.style.display = 'none';
    modal.classList.add('open');

    try {
        const params = new URLSearchParams(window.location.search);
        const f_ini = params.get('f_ini') || '';
        const f_fin = params.get('f_fin') || '';
        
        const response = await fetch(`?ajax=filtrar&codprov=${codprov}&f_ini=${f_ini}&f_fin=${f_fin}`);
        const data = await response.json();

        if (!data.success) throw new Error(data.error);

        let html = '';
        if (data.data.length === 0) {
            html = '<tr><td colspan="5" class="text-center" style="padding:40px; opacity:0.6;">No se encontraron artículos comprados.</td></tr>';
        } else {
            data.data.forEach(art => {
                html += `
                    <tr>
                        <td class="text-center" style="font-size:0.75rem; opacity:0.7;">${new Date(art.fecha + 'T00:00:00').toLocaleDateString('es-VE')}</td>
                        <td><span class="code-badge">${art.codigo}</span></td>
                        <td style="font-size:0.8rem; font-weight:500;">${art.descripcion}</td>
                        <td class="text-center" style="font-weight:700;">${parseInt(art.cantidad_actual).toLocaleString('es-VE')}</td>
                        <td class="text-right">Bs. ${parseFloat(art.precio).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right">$ ${parseFloat(art.precio_d).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right" style="font-weight:700; color:var(--accent-green);">Bs. ${parseFloat(art.total).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right" style="font-weight:700; color:var(--primary);">$ ${parseFloat(art.total_d).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right" style="font-size:0.8rem; opacity:0.8;">$ ${parseFloat(art.iva_d).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right" style="font-weight:900; color:var(--accent-green);">$ ${(parseFloat(art.total_d) + parseFloat(art.iva_d)).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
        }
        tbody.innerHTML = html;
        loading.style.display = 'none';
        container.style.display = 'block';

    } catch (err) {
        console.error('Error modal:', err);
        tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color:var(--accent-red); padding:40px;">Error: ${err.message}</td></tr>`;
        loading.style.display = 'none';
        container.style.display = 'block';
    }
}

function closeModal() {
    document.getElementById('modal-proveedor').classList.remove('open');
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modal-proveedor');
    if (event.target == modal) closeModal();
}

// Exportar Excel
function exportXls(id, name) {
    const t = document.getElementById(id);
    if (!t) return;
    const wb = XLSX.utils.table_to_book(t, { sheet: name });
    XLSX.writeFile(wb, name + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
}

// Inicializar gráfico al cargar la página
document.addEventListener('DOMContentLoaded', loadChartData);

// Alerta de búsqueda
function showSearchAlert(msg) {
    const alert = document.getElementById('search-alert');
    const alertMsg = document.getElementById('search-alert-msg');
    alertMsg.textContent = msg;
    alert.style.display = 'flex';
}

function clearSearch() {
    const input = document.getElementById('search-input');
    input.value = '';
    window.location.href = window.location.pathname;
}

// Verificar si hay búsqueda sin resultados al cargar
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchVal = urlParams.get('f_txt');
    if (searchVal && searchVal.length >= 2) {
        // Verificar si hay datos en la tabla
        const tableBody = document.querySelector('#table-articulos tbody');
        const noDataRow = tableBody.querySelector('td[colspan="11"]');
        if (noDataRow && noDataRow.textContent.includes('No se encontraron')) {
            showSearchAlert('No se encontró ningún artículo con: "' + searchVal + '"');
        }
    }
});
</script>



<?php 
include('../../includes/footer.php'); 
?>
