<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * ============================================================
 * INDICADORES CLAVE (KPI) - TELEVENTAS
 * Panel de control estratégico para gestión de televentas
 * ============================================================
 */

require_once('../includes/db.php');

// --- FILTROS ---
$f_ini   = $_GET['f_ini'] ?? date('Y-01-01');
$f_fin   = $_GET['f_fin'] ?? date('Y-m-d');
$codvend = $_GET['codvend'] ?? '';

// Construir condición de televendedor
$vend_cond   = !empty($codvend) ? "AND f.usuario = '$codvend'" : "";

// Fetch televendedores for select
$stmt_vends = $pdo->query("SELECT DISTINCT usuario FROM pfac WHERE usuario IS NOT NULL AND usuario <> '' ORDER BY usuario ASC");
$vendedores = $stmt_vends->fetchAll(PDO::FETCH_ASSOC);

// --- CÁLCULO DE KPIs ---

// 1. Resumen General de Ventas
$stmt_ventas = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT f.numero) as total_pedidos,
        SUM(f.totalg) as total_bs,
        SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) as total_usd,
        COUNT(DISTINCT f.usuario) as total_vendedores
    FROM pfac f
    WHERE f.fecha BETWEEN :ini AND :fin $vend_cond
");
$stmt_ventas->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$ventas_summary = $stmt_ventas->fetch(PDO::FETCH_ASSOC);

$total_pedidos = $ventas_summary['total_pedidos'] ?? 0;
$total_usd = $ventas_summary['total_usd'] ?? 0;
$total_bs = $ventas_summary['total_bs'] ?? 0;
$total_vendedores = $ventas_summary['total_vendedores'] ?? 0;

$ticket_promedio = $total_pedidos > 0 ? ($total_usd / $total_pedidos) : 0;
$prom_usd_vend = $total_vendedores > 0 ? ($total_usd / $total_vendedores) : 0;

// 2. Unidades Vendidas
$stmt_unidades = $pdo->prepare("
    SELECT SUM(i.cana) as total_unidades
    FROM itpfac i
    INNER JOIN pfac f ON i.numa = f.numero
    WHERE f.fecha BETWEEN :ini AND :fin $vend_cond
");
$stmt_unidades->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$total_unidades = $stmt_unidades->fetchColumn() ?: 0;

// 3. Ventas por Televendedor (Top 5 por Valor)
$stmt_vend_val = $pdo->prepare("
    SELECT COALESCE(NULLIF(f.usuario, ''), 'Sin Televendedor') as vendedor, SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) as valor 
    FROM pfac f
    WHERE f.fecha BETWEEN :ini AND :fin $vend_cond
    GROUP BY f.usuario
    ORDER BY valor DESC 
    LIMIT 5
");
$stmt_vend_val->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$vend_val = $stmt_vend_val->fetchAll(PDO::FETCH_ASSOC);

// Pre-calcular porcentajes para las etiquetas de la leyenda
$total_vend_val = array_sum(array_column($vend_val, 'valor'));
$vend_val_labels = array_map(function($m) use ($total_vend_val) {
    $p = ($total_vend_val > 0) ? round(($m['valor'] / $total_vend_val) * 100, 1) : 0;
    return ($m['vendedor']) . " ($p%)";
}, $vend_val);

// 4. Evolución Mensual
$stmt_evol = $pdo->prepare("
    SELECT 
        DATE_FORMAT(f.fecha, '%Y-%m') as mes,
        SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) as venta_usd,
        COUNT(DISTINCT f.numero) as pedidos
    FROM pfac f
    WHERE f.fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $vend_cond
    GROUP BY mes
    ORDER BY mes ASC
");
$stmt_evol->execute();
$evolucion = $stmt_evol->fetchAll(PDO::FETCH_ASSOC);

// 5. Cuadro de Mando por Televendedor (Desglose Detallado)
$stmt_mando = $pdo->prepare("
    SELECT 
        f.usuario as vd,
        COALESCE(NULLIF(f.usuario, ''), 'Sin Televendedor') as vendedor,
        COUNT(DISTINCT f.numero) as pedidos,
        SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) as venta_usd,
        SUM(f.totalg) as venta_bs,
        (SELECT SUM(i.cana) FROM itpfac i WHERE i.numa = f.numero) as unidades
    FROM pfac f
    WHERE f.fecha BETWEEN :ini AND :fin $vend_cond
    GROUP BY f.usuario
    ORDER BY venta_usd DESC
    LIMIT 100
");
$stmt_mando->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$cuadro_mando = $stmt_mando->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Indicadores KPI | Televentas";
$activePage = "televentas";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');
?>

<main class="main-content">
    <div class="content-wrapper">
        
        <!-- Navegación -->
        <nav class="module-nav">
            <a href="vista_televentas.php" class="nav-item">
                <i class="fas fa-home"></i> <span>Inicio</span>
            </a>
            <a href="televentas_articulos.php" class="nav-item">
                <i class="fas fa-list"></i> <span>Artículos Vendidos</span>
            </a>
            <a href="televentas_top.php" class="nav-item">
                <i class="fas fa-trophy"></i> <span>Top Vendidos</span>
            </a>
            <a href="televentas_kpis.php" class="nav-item active">
                <i class="fas fa-chart-line"></i> <span>Indicadores KPI</span>
            </a>
        </nav>

        <div class="page-title">
            <h1>Indicadores de Gestión (KPI) de Televentas</h1>
            <p>Análisis estratégico de ventas, pedidos y efectividad de televendedores</p>
        </div>

        <!-- Filtros -->
        <section class="card filters-card" style="margin-bottom: 30px;">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Desde</label>
                    <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
                </div>
                <div class="filter-group">
                    <label>Hasta</label>
                    <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
                </div>
                <div class="filter-group">
                    <label>Televendedor</label>
                    <select name="codvend" style="min-width: 200px;">
                        <option value="">TODOS LOS TELEVENDEDORES</option>
                        <?php foreach($vendedores as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['usuario']); ?>" <?php echo $codvend == $v['usuario'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['usuario']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i> Filtrar KPI</button>
                </div>
            </form>
        </section>

        <!-- Métricas Principales -->
        <div class="metrics-grid">
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ventas Totales (USD)</span>
                    <p class="metric-value">$ <?php echo number_format($total_usd, 2, ',', '.'); ?></p>
                    <span class="metric-trend">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></span>
                </div>
            </div>
            <div class="card metric-card warning">
                <div class="metric-icon"><i class="fas fa-clipboard-check"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Pedidos Realizados</span>
                    <p class="metric-value"><?php echo number_format($total_pedidos, 0, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format($total_unidades, 0, ',', '.'); ?> unid. despachadas</span>
                </div>
            </div>
            <div class="card metric-card info">
                <div class="metric-icon"><i class="fas fa-receipt"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ticket Promedio</span>
                    <p class="metric-value">$ <?php echo number_format($ticket_promedio, 2, ',', '.'); ?></p>
                    <span class="metric-trend">Venta promedio por pedido</span>
                </div>
            </div>
            <div class="card metric-card primary">
                <div class="metric-icon"><i class="fas fa-users"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Productividad / Televendedor</span>
                    <p class="metric-value">$ <?php echo number_format($prom_usd_vend, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo $total_vendedores; ?> televendedores activos</span>
                </div>
            </div>
        </div>

        <!-- Sección de Gráficos -->
        <div class="chart-section" style="margin-top:30px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            
            <!-- Ventas por Televendedor -->
            <div class="card chart-card">
                <div class="t-header">
                    <h2><i class="fas fa-chart-pie"></i> Ventas por Televendedor (Top 5)</h2>
                </div>
                <div class="chart-container" style="height:350px;">
                    <canvas id="chartBrandsValue"></canvas>
                </div>
            </div>

            <!-- Ticket Promedio vs Pedidos (Gráfico placeholder o similar) -->
            <div class="card chart-card">
                <div class="t-header">
                    <h2><i class="fas fa-chart-bar"></i> Pedidos y Ticket Promedio (USD)</h2>
                </div>
                <div style="padding: 20px; display: flex; flex-direction: column; justify-content: center; height: 350px; text-align: center;">
                    <i class="fas fa-chart-line" style="font-size: 5rem; color: var(--border-light); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--accent-cyan); font-size: 2rem;">$ <?php echo number_format($ticket_promedio, 2, ',', '.'); ?></h3>
                    <p style="color: var(--text-muted);">Ticket promedio general en el periodo seleccionado</p>
                    <h3 style="color: var(--accent-yellow); font-size: 2rem; margin-top: 20px;"><?php echo number_format($total_pedidos, 0, ',', '.'); ?></h3>
                    <p style="color: var(--text-muted);">Total de Pedidos</p>
                </div>
            </div>

            <!-- Evolución Mensual -->
            <div class="card chart-card" style="grid-column: span 2;">
                <div class="t-header">
                    <h2><i class="fas fa-chart-line"></i> Evolución de Ventas y Pedidos (Últimos 6 Meses)</h2>
                </div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="chartEvolucion"></canvas>
                </div>
            </div>
        </div>

        <!-- Cuadro de Mando Detallado -->
        <div class="card table-card" style="margin-top:30px;">
            <div class="t-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fas fa-table"></i> Desglose Gerencial por Televendedor</h2>
                <div style="font-size: 0.8rem; opacity: 0.7;">* Filtrado por periodo seleccionado</div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>TELEVENDEDOR</th>
                            <th class="text-center">CANT. PEDIDOS</th>
                            <th class="text-right">VENTAS ($)</th>
                            <th class="text-right">VENTAS (Bs.)</th>
                            <th class="text-center">TICKET PROM. ($)</th>
                            <th class="text-right">% DEL TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuadro_mando as $m): 
                            $ticket_vnd = $m['pedidos'] > 0 ? ($m['venta_usd'] / $m['pedidos']) : 0;
                            $pct_vnd = $total_usd > 0 ? ($m['venta_usd'] / $total_usd) * 100 : 0;
                        ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?php echo htmlspecialchars($m['vendedor'] ?: 'Sin Vendedor'); ?></td>
                                <td class="text-center" style="font-weight:700; color:var(--text-main);"><?php echo number_format($m['pedidos'], 0, ',', '.'); ?></td>
                                <td class="text-right" style="font-weight:700; color:var(--accent-green);">$ <?php echo number_format($m['venta_usd'], 2, ',', '.'); ?></td>
                                <td class="text-right">Bs. <?php echo number_format($m['venta_bs'], 2, ',', '.'); ?></td>
                                <td class="text-center" style="color:var(--accent-cyan); font-weight: 700;">$ <?php echo number_format($ticket_vnd, 2, ',', '.'); ?></td>
                                <td class="text-right">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                        <span style="font-weight:800; font-size:0.8rem;"><?php echo number_format($pct_vnd, 1); ?>%</span>
                                        <div style="width: 50px; background: rgba(255,255,255,0.1); border-radius: 3px; height: 6px; overflow: hidden;">
                                            <div style="width: <?php echo $pct_vnd; ?>%; background: var(--primary); height: 100%;"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($cuadro_mando)): ?>
                            <tr><td colspan="6" class="text-center" style="padding: 30px; opacity: 0.5;">No hay datos para mostrar</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Gráfico de Valor por Vendedor
    const valCtx = document.getElementById('chartBrandsValue');
    if (valCtx && <?php echo count($vend_val_labels); ?> > 0) {
        new Chart(valCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($vend_val_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($vend_val, 'valor')); ?>,
                    backgroundColor: ['#00b4ff', '#00ffc3', '#ffcc00', '#ff3e3e', '#ae00ff'],
                    borderColor: '#11192a',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#fff' } },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                const value = Number(c.parsed);
                                const dataset = c.dataset.data;
                                const total = dataset.reduce((acc, curr) => acc + Number(curr), 0);
                                const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${c.label.split('(')[0]}: $ ${value.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Gráfico de Evolución (Línea/Barras Combinado)
    const evolCtx = document.getElementById('chartEvolucion');
    if (evolCtx && <?php echo count($evolucion); ?> > 0) {
        new Chart(evolCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($evolucion, 'mes')); ?>,
                datasets: [
                    {
                        type: 'line',
                        label: 'Ventas USD',
                        data: <?php echo json_encode(array_column($evolucion, 'venta_usd')); ?>,
                        fill: true,
                        backgroundColor: 'rgba(0, 255, 195, 0.1)',
                        borderColor: '#00ffc3',
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#00ffc3',
                        yAxisID: 'y'
                    },
                    {
                        type: 'bar',
                        label: 'Cant. Pedidos',
                        data: <?php echo json_encode(array_column($evolucion, 'pedidos')); ?>,
                        backgroundColor: 'rgba(0, 180, 255, 0.4)',
                        borderColor: '#00b4ff',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { color: 'rgba(255,255,255,0.05)' }, 
                        ticks: { color: '#00ffc3' } 
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false }, 
                        ticks: { color: '#00b4ff' } 
                    },
                    x: { grid: { display: false }, ticks: { color: '#fff' } }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                if(c.datasetIndex === 0) {
                                    return 'Ventas: $ ' + c.parsed.y.toLocaleString('es-VE', {minimumFractionDigits:2});
                                } else {
                                    return 'Pedidos: ' + c.parsed.y;
                                }
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include('../includes/footer.php'); ?>
