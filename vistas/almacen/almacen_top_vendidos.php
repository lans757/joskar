<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
/**
 * ============================================================
 * TOP PRODUCTOS MÁS VENDIDOS - PROTEOERP
 * Reporte de rotación de inventario basado en ventas reales
 * ============================================================
 */

require_once('../../includes/db.php');

// --- Manejo AJAX: Datos del Gráfico ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'chart') {
    $f_ini = $_GET['f_ini'] ?? date('Y-01-01');
    $f_fin = $_GET['f_fin'] ?? date('Y-m-d');
    $codprov = $_GET['codprov'] ?? '';

    header('Content-Type: application/json');
    try {
        $params = [':ini' => $f_ini, ':fin' => $f_fin];
        $where = "WHERE i.fecha >= :ini AND i.fecha <= :fin";
        
        if (!empty($codprov)) {
            $where .= " AND v.prvreg = :prov";
            $params[':prov'] = $codprov;
        }

        $stmt = $pdo->prepare("
            SELECT 
                i.codigoa as codigo,
                LEFT(v.descrip, 25) as descripcion,
                SUM(i.cana) as cantidad
            FROM itpfac i
            INNER JOIN sinv v ON i.codigoa = v.codigo
            $where
            GROUP BY i.codigoa, v.descrip
            ORDER BY cantidad DESC
            LIMIT 15
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- Manejo Inicial de Filtros ---
$f_ini = $_GET['f_ini'] ?? date('Y-m-01'); // Primer día del mes
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');
$f_prov = $_GET['codprov'] ?? '';

// Cargar Proveedores para el filtro
$stmt_prov = $pdo->query("SELECT proveed, nombre FROM sprv ORDER BY nombre ASC");
$proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

// Query Principal: Listado de Top Vendidos
$params = [':ini' => $f_ini, ':fin' => $f_fin];
$where = "WHERE i.fecha >= :ini AND i.fecha <= :fin";
if (!empty($f_prov)) {
    $where .= " AND v.prvreg = :prov";
    $params[':prov'] = $f_prov;
}

$stmt_list = $pdo->prepare("
    SELECT 
        i.codigoa as codigo,
        v.descrip as descripcion,
        v.prvreg as codprov,
        p.nombre as proveedor,
        SUM(i.cana) as cantidad,
        SUM(i.totad) as total_usd,
        SUM(i.tota) as total_bs
    FROM itpfac i
    INNER JOIN sinv v ON i.codigoa = v.codigo
    LEFT JOIN sprv p ON v.prvreg = p.proveed
    $where
    GROUP BY i.codigoa, v.descrip, p.nombre
    ORDER BY cantidad DESC
    LIMIT 100
");
$stmt_list->execute($params);
$productos = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Top Artículos Vendidos | Almacén";
$activePage = "almacen";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

<main class="main-content">
    <div class="content-wrapper">
        
        <!-- Navegación -->
        <nav class="module-nav">
            <a href="vista_almacen.php" class="nav-item">
                <i class="fas fa-home"></i> <span>Inicio</span>
            </a>
            <a href="almacen_articulos_comprados.php" class="nav-item">
                <i class="fas fa-list"></i> <span>Artículos Comprados</span>
            </a>
            <a href="almacen_compras_fecha.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> <span>Compras por Fecha</span>
            </a>
            <a href="almacen_top_vendidos.php" class="nav-item active">
                <i class="fas fa-trophy"></i> <span>Top Vendidos</span>
            </a>
            <a href="almacen_kpis.php" class="nav-item">
                <i class="fas fa-chart-line"></i> <span>Indicadores KPI</span>
            </a>
        </nav>

        <div class="page-title">
            <h1>Top Productos más Vendidos</h1>
            <p>Análisis de rotación y demanda por proveedor (Datos de Pedidos/Facturación)</p>
        </div>

        <!-- Filtros -->
        <section class="card filters-card">
            <form method="GET" class="filters-row" id="filter-form">
                <div class="filter-group">
                    <label>Desde</label>
                    <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
                </div>
                <div class="filter-group">
                    <label>Hasta</label>
                    <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
                </div>
                <div class="filter-group" style="flex: 2;">
                    <label>Proveedor</label>
                    <select name="codprov">
                        <option value="">TODOS LOS PROVEEDORES</option>
                        <?php foreach($proveedores as $p): ?>
                            <option value="<?php echo $p['proveed']; ?>" <?php echo ($f_prov == $p['proveed'] ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </form>
        </section>

        <!-- Sección de Análisis Visual -->
        <div class="chart-section" style="margin-bottom:30px;">
            <div class="card chart-card">
                <div class="t-header">
                    <h2><i class="fas fa-chart-bar"></i> Top 15 Artículos (Unidades)</h2>
                </div>
                <div class="chart-container" style="height:450px; position:relative;">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

            <div class="card info-card">
                <div class="t-header">
                    <h2><i class="fas fa-info-circle"></i> Resumen de Rotación</h2>
                </div>
                <div class="stat-box" style="margin-top:20px;">
                    <div class="stat-label">Total Unidades Despachadas</div>
                    <div class="stat-value" id="total-units-summary" style="color:var(--accent-yellow);">
                        <?php 
                            $total_unid = array_sum(array_column($productos, 'cantidad'));
                            echo number_format($total_unid, 0, ',', '.');
                        ?>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Venta Total Estimada ($)</div>
                    <div class="stat-value" style="color:var(--primary);">
                        <?php 
                            $total_usd = array_sum(array_column($productos, 'total_usd'));
                            echo '$ ' . number_format($total_usd, 2, ',', '.');
                        ?>
                    </div>
                </div>
                <p style="padding:20px; font-size:0.85rem; opacity:0.7; border-top: 1px solid var(--border-light);">
                    Este análisis agrupa los artículos con mayor volumen de salida en el periodo seleccionado. 
                    Útil para identificar quiebres de stock preventivos y negociar reposiciones con proveedores clave.
                </p>
            </div>
        </div>

        <!-- Tabla de Detalle -->
        <div class="card table-card">
            <div class="t-header">
                <h2><i class="fas fa-sort-amount-down"></i> Ranking de Artículos Vendidos</h2>
                <button class="btn-neon btn-green" onclick="exportXls('table-top', 'Top_Vendidos')" style="height:32px; font-size:0.75rem; padding: 0 15px;">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
            </div>
            <div class="table-responsive">
                <table id="table-top">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>CÓDIGO</th>
                            <th>DESCRIPCIÓN</th>
                            <th>PROVEEDOR</th>
                            <th class="text-center">UNIDADES</th>
                            <th class="text-right">VENTA BS.</th>
                            <th class="text-right">VENTA $</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="7" class="text-center" style="padding:50px; opacity:0.5;">No hay datos para el periodo seleccionado.</td></tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach ($productos as $p): ?>
                                <tr>
                                    <td class="text-center" style="font-weight:700; color:var(--text-muted);"><?php echo $rank++; ?></td>
                                    <td><span class="code-badge"><?php echo $p['codigo']; ?></span></td>
                                    <td style="font-weight:500; font-size:0.85rem;"><?php echo htmlspecialchars($p['descripcion']); ?></td>
                                    <td style="font-size:0.75rem; opacity:0.8;"><?php echo htmlspecialchars($p['proveedor']); ?></td>
                                    <td class="text-center" style="font-weight:800; color:var(--accent-cyan);"><?php echo number_format($p['cantidad'], 0, ',', '.'); ?></td>
                                    <td class="text-right"><?php echo number_format($p['total_bs'], 2, ',', '.'); ?></td>
                                    <td class="text-right" style="font-weight:700; color:var(--primary);"><?php echo '$ ' . number_format($p['total_usd'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- XLSX para exportación -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
let topChart = null;

async function loadChart() {
    const params = new URLSearchParams(window.location.search);
    const f_ini = params.get('f_ini') || '<?php echo $f_ini; ?>';
    const f_fin = params.get('f_fin') || '<?php echo $f_fin; ?>';
    const prov = params.get('codprov') || '<?php echo $f_prov; ?>';

    try {
        const response = await fetch(`?ajax=chart&f_ini=${f_ini}&f_fin=${f_fin}&codprov=${prov}`);
        const result = await response.json();
        if (result.success) {
            renderChart(result.data);
        }
    } catch (err) {
        console.error('Error cargando gráfico:', err);
    }
}

function renderChart(data) {
    const ctx = document.getElementById('topProductsChart').getContext('2d');
    const labels = data.map(d => d.descripcion);
    const values = data.map(d => parseFloat(d.cantidad));

    if (topChart) topChart.destroy();

    topChart = new Chart(ctx, {
        type: 'bar', // Gráfico de barras horizontal para mejor lectura de nombres
        data: {
            labels: labels,
            datasets: [{
                label: 'Unidades Vendidas',
                data: values,
                backgroundColor: 'rgba(0, 180, 255, 0.6)',
                borderColor: '#00b4ff',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y', // Convertir en barras horizontales
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#8b9bb4' }
                },
                y: {
                    grid: { display: false },
                    ticks: { 
                        color: '#ffffff',
                        font: { size: 10 }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Cant: ${context.parsed.x.toLocaleString('es-VE')} unid.`;
                        }
                    }
                }
            }
        }
    });
}

function exportXls(tableId, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, { sheet: "TopVendidos" });
    XLSX.writeFile(wb, `${filename}_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

document.addEventListener('DOMContentLoaded', loadChart);
</script>

<?php include('../../includes/footer.php'); ?>
