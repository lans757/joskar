<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * ============================================================
 * LISTADO DE COMPRAS POR FECHA - PROTEOERP
 * Reporte cronológico de facturas de compra
 * ============================================================
 */

require_once('../includes/db.php');

// --- Manejo AJAX: Gráfico Proveedor ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'proveedores') {
    $f_ini = $_GET['f_ini'] ?? date('Y-01-01');
    $f_fin = $_GET['f_fin'] ?? date('Y-m-d');
    $f_txt = $_GET['f_txt'] ?? '';
    
    $where = "WHERE c.recep >= :ini AND c.recep <= :fin";
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    
    if (!empty($f_txt) && strlen(trim($f_txt)) >= 2) {
        $txt_search = "%" . str_replace(" ", "%", trim($f_txt)) . "%";
        $where .= " AND (c.numero LIKE :txt OR p.nombre LIKE :txt OR c.usuario LIKE :txt)";
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
                COUNT(c.numero) as total_compras,
                SUM(c.ctotald) as monto_total
            FROM scst c
            INNER JOIN sprv p ON c.proveed = p.proveed
            $where
            GROUP BY p.proveed, p.nombre
            ORDER BY monto_total DESC
        ");
        $stmt->execute($params);
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $proveedores]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- Parámetros iniciales ---
$pageTitle   = "ProteoERP | Compras por Fecha";
$activePage  = "almacen";
$activeSubPage = "compras_fecha";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');

// --- Filtros ---
$f_ini  = $_GET['f_ini'] ?? date('Y-01-01');
$f_fin  = $_GET['f_fin'] ?? date('Y-m-d');
$f_prov = $_GET['f_prov'] ?? '';
$f_txt  = $_GET['f_txt'] ?? '';

// --- Paginación ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    // Lista de proveedores para filtro
    $stmt_prov = $pdo->query("SELECT proveed, nombre FROM sprv ORDER BY nombre");
    $proveedores_filter = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

    // --- Query Principal: Listado de Compras ---
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    $where = "WHERE c.recep >= :ini AND c.recep <= :fin";
    
    if (!empty($f_prov)) {
        $where .= " AND c.proveed = :prov";
        $params[':prov'] = $f_prov;
    }
    if (!empty($f_txt) && strlen(trim($f_txt)) >= 2) {
        $txt_search = "%" . str_replace(" ", "%", trim($f_txt)) . "%";
        $where .= " AND (c.numero LIKE :txt OR p.nombre LIKE :txt OR c.usuario LIKE :txt)";
        $params[':txt'] = $txt_search;
    } else {
        $f_txt = '';
    }

    // KPIs
    $stmt_kpi = $pdo->prepare("
        SELECT 
            COUNT(c.numero) as total_documentos,
            SUM(c.ctotal) as monto_total,
            SUM(c.ctotald) as monto_total_d,
            SUM((SELECT SUM(cantidad) FROM itscst WHERE numero = c.numero)) as total_unidades
        FROM scst c
        INNER JOIN sprv p ON c.proveed = p.proveed
        $where
    ");
    $stmt_kpi->execute($params);
    $kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);

    // Listado de compras (Detalle de cabecera)
    $stmt_list = $pdo->prepare("
        SELECT 
            c.depo as alm,
            c.tipo_doc as tipo,
            c.estampa as registro,
            c.hora,
            c.usuario,
            c.fecha,
            c.recep as recepcion,
            c.numero,
            p.nombre as proveedor,
            (SELECT SUM(cantidad) FROM itscst WHERE numero = c.numero) as unidades,
            ROUND(c.cstotal, 2) as subtotal,
            ROUND(c.cimpuesto, 2) as impuesto,
            ROUND(c.ctotal, 2) as total,
            ROUND(c.cdolar, 2) as tasa_bcv,
            ROUND(c.ctotald, 2) as total_usd,
            c.consigna as consig
        FROM scst c
        INNER JOIN sprv p ON c.proveed = p.proveed
        $where
        ORDER BY c.recep DESC, c.numero DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt_list->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) { $stmt_list->bindValue($key, $val); }
    $stmt_list->execute();
    $compras = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    // Total de registros para paginación
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM scst c INNER JOIN sprv p ON c.proveed = p.proveed $where");
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Helpers Formateo
function formatBs($val) { return 'Bs. ' . number_format($val, 2, ',', '.'); }
function formatDL($val) { return '$ ' . number_format($val, 2, ',', '.'); }
?>

<main class="main-content">
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="vista_almacen.php" class="nav-item">
            <i class="fas fa-home"></i> <span>Inicio</span>
        </a>
        <a href="almacen_articulos_comprados.php" class="nav-item">
            <i class="fas fa-list"></i>
            <span>Artículos Comprados</span>
        </a>
        <a href="almacen_compras_fecha.php" class="nav-item active">
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
        <h1><i class="fas fa-history" style="color:var(--primary);"></i> Listado de Compras por Fecha</h1>
        <p>Historial cronológico de recepciones de mercancía &bull; 
           <strong><?php echo date('d/m/Y', strtotime($f_ini)); ?></strong> - <strong><?php echo date('d/m/Y', strtotime($f_fin)); ?></strong>
        </p>
    </div>

    <!-- KPI CARDS -->
    <div class="metrics-grid">
        <div class="card metric-card primary">
            <div class="metric-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="metric-content">
                <span class="metric-label">Documentos</span>
                <p class="metric-value"><?php echo number_format($kpis['total_documentos'] ?? 0, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="card metric-card success">
            <div class="metric-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="metric-content">
                <span class="metric-label">Total en Bolívares</span>
                <p class="metric-value"><?php echo formatBs($kpis['monto_total'] ?? 0); ?></p>
            </div>
        </div>
        <div class="card metric-card info">
            <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-content">
                <span class="metric-label">Total en Divisas</span>
                <p class="metric-value"><?php echo formatDL($kpis['monto_total_d'] ?? 0); ?></p>
            </div>
        </div>
        <div class="card metric-card warning">
            <div class="metric-icon"><i class="fas fa-boxes"></i></div>
            <div class="metric-content">
                <span class="metric-label">Unidades Recibidas</span>
                <p class="metric-value"><?php echo number_format($kpis['total_unidades'] ?? 0, 0, ',', '.'); ?></p>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card filters-card">
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label>Desde (Recepción)</label>
                <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
            </div>
            <div class="filter-group">
                <label>Hasta (Recepción)</label>
                <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
            </div>
            <div class="filter-group" style="flex-grow: 1;">
                <label>Búsqueda Rápida</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" name="f_txt" id="search-input" value="<?php echo htmlspecialchars($f_txt); ?>" placeholder="Número, Proveedor o Usuario...">
                    <button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>
        <div id="search-alert" class="search-alert" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="search-alert-msg"></span>
            <button onclick="clearSearch()"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- GRÁFICO Y RESUMEN -->
    <div class="chart-section" style="margin-bottom: 25px;">
        <div class="card table-card">
            <div class="table-header"><h2><i class="fas fa-chart-pie"></i> Participación Proveedores (Monto $)</h2></div>
            <div class="chart-container">
                <canvas id="donutChart" style="max-height: 350px;"></canvas>
            </div>
        </div>
        <div class="card table-card">
            <div class="table-header"><h2><i class="fas fa-list-ol"></i> Top Proveedores del Periodo</h2></div>
            <div id="chartLegend" class="chart-legend" style="padding: 10px;">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="card table-card">
        <div class="t-header">
            <h2><i class="fas fa-table"></i> Detalle de Documentos</h2>
            <button class="btn-neon btn-green" onclick="exportXls('table-compras', 'Compras_Fecha')" style="height:32px; font-size:0.7rem; padding: 0 12px;">
                <i class="fas fa-file-excel"></i> Excel
            </button>
        </div>
        <div class="table-container">
            <table id="table-compras">
                <thead>
                    <tr>
                        <th class="text-center">ALM.</th>
                        <th class="text-center">TIPO</th>
                        <th class="text-center">REGISTRO</th>
                        <th class="text-center">USUARIO</th>
                        <th class="text-center">HORA</th>
                        <th class="text-center">FECHA DOC.</th>
                        <th class="text-center">RECEPCIÓN</th>
                        <th>NÚMERO</th>
                        <th>PROVEEDOR</th>
                        <th class="text-right">UNID.</th>
                        <th class="text-right">SUBTOTAL BS.</th>
                        <th class="text-right">IMPUESTO BS.</th>
                        <th class="text-right">TOTAL BS.</th>
                        <th class="text-center">TASA BCV</th>
                        <th class="text-right">TOTAL $</th>
                        <th class="text-center">CONS.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compras)): ?>
                    <tr><td colspan="16" class="text-center" style="padding:48px; opacity:0.5;">No hay registros confirmados.</td></tr>
                    <?php else: ?>
                    <?php foreach ($compras as $c): 
                        $reg_date = date('d/m/Y', strtotime($c['registro']));
                        // Usar el campo hora directo de la BD
                        $reg_time = $c['hora']; 
                    ?>
                    <tr>
                        <td class="text-center" style="font-size:0.7rem; opacity:0.8;"><?php echo $c['alm']; ?></td>
                        <td class="text-center" style="font-weight:700; opacity:0.8;"><?php echo $c['tipo']; ?></td>
                        <td class="text-center" style="font-size:0.7rem;"><?php echo $reg_date; ?></td>
                        <td class="text-center"><span class="code-badge" style="background:rgba(0,180,255,0.05); color:var(--primary); font-size:0.7rem;"><?php echo $c['usuario']; ?></span></td>
                        <td class="text-center" style="font-size:0.7rem; opacity:0.8;"><?php echo $reg_time; ?></td>
                        <td class="text-center"><?php echo date('d/m/Y', strtotime($c['fecha'])); ?></td>
                        <td class="text-center" style="font-weight:700; color:var(--primary);"><?php echo date('d/m/Y', strtotime($c['recepcion'])); ?></td>
                        <td><span class="code-badge"><?php echo $c['numero']; ?></span></td>
                        <td style="font-weight:700; font-size:0.8rem;"><?php echo htmlspecialchars($c['proveedor']); ?></td>
                        <td class="text-right" style="font-weight:800;"><?php echo number_format($c['unidades'], 0, ',', '.'); ?></td>
                        <td class="text-right"><?php echo number_format($c['subtotal'], 2, ',', '.'); ?></td>
                        <td class="text-right"><?php echo number_format($c['impuesto'], 2, ',', '.'); ?></td>
                        <td class="text-right" style="font-weight:700; color:var(--accent-green);"><?php echo number_format($c['total'], 2, ',', '.'); ?></td>
                        <td class="text-center" style="opacity:0.8; font-size:0.8rem;"><?php echo number_format($c['tasa_bcv'], 2, ',', '.'); ?></td>
                        <td class="text-right" style="font-weight:700; color:var(--primary);"><?php echo formatDL($c['total_usd']); ?></td>
                        <td class="text-center">
                            <span class="status-pill <?php echo ($c['consig'] == 'S') ? 'completed' : 'pending'; ?>" style="font-size:0.65rem; padding: 2px 8px;">
                                <?php echo $c['consig']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div class="pagination-wrapper" style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size:0.85rem; opacity:0.6;">Total: <strong><?php echo number_format($total_records, 0, ',', '.'); ?></strong> registros</div>
            <div class="pager-container" style="display:flex; gap:10px;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="nav-item"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <span class="nav-item active"><?php echo $page; ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="nav-item"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
const chartColors = ['#00b4ff', '#00e676', '#ffc107', '#ff5722', '#9c27b0', '#2196f3', '#4caf50', '#ff9800', '#f44336', '#673ab7'];
let donutChart = null;

async function loadData() {
    const params = new URLSearchParams(window.location.search);
    try {
        const resp = await fetch(`?ajax=proveedores&${params.toString()}`);
        const json = await resp.json();
        if(json.success) {
            renderChart(json.data);
            renderLegend(json.data);
        }
    } catch(e) { console.error(e); }
}

function renderChart(data) {
    const ctx = document.getElementById('donutChart').getContext('2d');
    if(donutChart) donutChart.destroy();
    donutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.proveedor),
            datasets: [{
                data: data.map(d => d.monto_total),
                backgroundColor: chartColors,
                borderWidth: 2,
                borderColor: '#11192a'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) label += ': ';
                            if (context.parsed !== null) {
                                label += '$ ' + context.parsed.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
}

function renderLegend(data) {
    const cont = document.getElementById('chartLegend');
    const total = data.reduce((a, b) => a + parseFloat(b.monto_total), 0);
    cont.innerHTML = data.slice(0, 10).map((d, i) => `
        <div class="legend-item">
            <div class="legend-color" style="background:${chartColors[i % chartColors.length]}"></div>
            <div class="legend-text" style="flex:1;">
                <strong>${d.proveedor}</strong><br>
                <small>${d.total_compras} compras</small>
            </div>
            <div class="legend-value">${total > 0 ? ((d.monto_total/total)*100).toFixed(1) : 0}%</div>
        </div>
    `).join('');
}

function exportXls(id, name) {
    const wb = XLSX.utils.table_to_book(document.getElementById(id));
    XLSX.writeFile(wb, `${name}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

document.addEventListener('DOMContentLoaded', loadData);

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

// Verificar si hay búsqueda sin resultados
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const searchVal = urlParams.get('f_txt');
        if (searchVal && searchVal.length >= 2) {
            const noDataRow = document.querySelector('td[colspan]');
            if (noDataRow && (noDataRow.textContent.includes('No hay registros') || noDataRow.textContent.includes('No se encontraron'))) {
                showSearchAlert('No se encontró ningún registro con: "' + searchVal + '"');
            }
        }
    }, 100);
});
</script>



<?php include('../includes/footer.php'); ?>
