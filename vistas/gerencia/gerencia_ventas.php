<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
/**
 * ============================================================
 * GERENCIA · VENTAS GLOBALES Y MARGEN
 * Consolidado ejecutivo de ventas Bs/USD, ticket promedio,
 * crecimiento MoM/YoY y margen bruto estimado.
 * ============================================================
 */
require_once('../../includes/db.php');

$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');

// --- KPIs del periodo (sfac = facturación oficial) ---
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT f.numero) AS num_facturas,
        COUNT(DISTINCT f.cod_cli) AS clientes_unicos,
        SUM(f.totalg) AS total_bs,
        SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS total_usd
    FROM sfac f
    WHERE f.fecha BETWEEN :ini AND :fin
");
$stmt->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$k = $stmt->fetch(PDO::FETCH_ASSOC);
$num_fac    = (int)($k['num_facturas'] ?? 0);
$cli_unicos = (int)($k['clientes_unicos'] ?? 0);
$total_bs   = (float)($k['total_bs'] ?? 0);
$total_usd  = (float)($k['total_usd'] ?? 0);
$ticket     = $num_fac > 0 ? $total_usd / $num_fac : 0;

// --- Margen bruto estimado (itpfac.totad - cana*pondd) ---
$stmt_m = $pdo->prepare("
    SELECT
        SUM(i.totad) AS ventas_usd,
        SUM(i.cana * v.pondd) AS costo_usd
    FROM itpfac i
    INNER JOIN sinv v ON i.codigoa = v.codigo
    WHERE i.fecha BETWEEN :ini AND :fin
");
$stmt_m->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$mg = $stmt_m->fetch(PDO::FETCH_ASSOC);
$ventas_usd = (float)($mg['ventas_usd'] ?? 0);
$costo_usd  = (float)($mg['costo_usd']  ?? 0);
$margen_usd = $ventas_usd - $costo_usd;
$margen_pct = $ventas_usd > 0 ? ($margen_usd / $ventas_usd) * 100 : 0;

// --- Comparativa MoM (mismo número de días del mes anterior) ---
$dias = (strtotime($f_fin) - strtotime($f_ini)) / 86400 + 1;
$f_ini_prev = date('Y-m-d', strtotime("$f_ini -1 month"));
$f_fin_prev = date('Y-m-d', strtotime("$f_ini_prev +" . ($dias - 1) . " days"));
$stmt_p = $pdo->prepare("
    SELECT SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS usd
    FROM sfac f WHERE f.fecha BETWEEN :ini AND :fin
");
$stmt_p->execute([':ini' => $f_ini_prev, ':fin' => $f_fin_prev]);
$total_prev = (float)$stmt_p->fetchColumn();
$mom_pct = $total_prev > 0 ? (($total_usd - $total_prev) / $total_prev) * 100 : 0;

// --- Comparativa YoY ---
$stmt_y = $pdo->prepare("
    SELECT SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS usd
    FROM sfac f WHERE f.fecha BETWEEN :ini AND :fin
");
$stmt_y->execute([
    ':ini' => date('Y-m-d', strtotime("$f_ini -1 year")),
    ':fin' => date('Y-m-d', strtotime("$f_fin -1 year")),
]);
$total_yoy = (float)$stmt_y->fetchColumn();
$yoy_pct = $total_yoy > 0 ? (($total_usd - $total_yoy) / $total_yoy) * 100 : 0;

// --- Evolución últimos 12 meses ---
$stmt_evol = $pdo->query("
    SELECT
        DATE_FORMAT(f.fecha, '%Y-%m') AS mes,
        SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS usd,
        COUNT(DISTINCT f.numero) AS facturas
    FROM sfac f
    WHERE f.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mes ORDER BY mes ASC
");
$evolucion = $stmt_evol->fetchAll(PDO::FETCH_ASSOC);

// --- Margen por línea (top 15 marcas) ---
$stmt_mar = $pdo->prepare("
    SELECT
        COALESCE(NULLIF(v.marca,''),'SIN MARCA') AS marca,
        SUM(i.totad) AS ventas,
        SUM(i.cana * v.pondd) AS costo
    FROM itpfac i
    INNER JOIN sinv v ON i.codigoa = v.codigo
    WHERE i.fecha BETWEEN :ini AND :fin
    GROUP BY v.marca
    HAVING ventas > 0
    ORDER BY ventas DESC LIMIT 15
");
$stmt_mar->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$por_marca = $stmt_mar->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = "Gerencia | Ventas y Margen";
$activePage = "gerencia";
$path_prefix = "../../";
include('../../includes/header.php');
include('../../includes/sidebar.php');
?>
<main class="main-content">
    <div class="content-wrapper">
        <nav class="module-nav">
            <a href="vista_gerencia.php" class="nav-item"><i class="fas fa-home"></i> <span>Resumen</span></a>
            <a href="gerencia_ventas.php" class="nav-item active"><i class="fas fa-chart-line"></i> <span>Ventas y Margen</span></a>
            <a href="gerencia_cartera.php" class="nav-item"><i class="fas fa-hand-holding-usd"></i> <span>Cartera</span></a>
            <a href="gerencia_inventario.php" class="nav-item"><i class="fas fa-boxes"></i> <span>Inventario</span></a>
            <a href="gerencia_compras.php" class="nav-item"><i class="fas fa-truck-loading"></i> <span>Compras</span></a>
        </nav>

        <div class="page-title">
            <h1><i class="fas fa-chart-line"></i> Ventas y Margen</h1>
            <p>Consolidado de facturación, ticket promedio, crecimiento y margen bruto.</p>
        </div>

        <section class="card filters-card" style="margin-bottom:30px;">
            <form method="GET" class="filters-row">
                <div class="filter-group"><label>Desde</label><input type="date" name="f_ini" value="<?php echo htmlspecialchars($f_ini); ?>"></div>
                <div class="filter-group"><label>Hasta</label><input type="date" name="f_fin" value="<?php echo htmlspecialchars($f_fin); ?>"></div>
                <div class="btn-group"><button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i> Actualizar</button></div>
            </form>
        </section>

        <div class="metrics-grid">
            <div class="card metric-card primary">
                <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ventas (USD)</span>
                    <p class="metric-value">$ <?php echo number_format($total_usd, 2, ',', '.'); ?></p>
                    <span class="metric-trend">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></span>
                </div>
            </div>
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-percentage"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Margen Bruto</span>
                    <p class="metric-value">$ <?php echo number_format($margen_usd, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format($margen_pct, 1); ?>% sobre ventas</span>
                </div>
            </div>
            <div class="card metric-card warning">
                <div class="metric-icon"><i class="fas fa-receipt"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ticket Promedio</span>
                    <p class="metric-value">$ <?php echo number_format($ticket, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format($num_fac, 0, ',', '.'); ?> facturas · <?php echo $cli_unicos; ?> clientes</span>
                </div>
            </div>
            <div class="card metric-card <?php echo $mom_pct >= 0 ? 'success' : 'alert'; ?>">
                <div class="metric-icon"><i class="fas fa-<?php echo $mom_pct >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Crecimiento MoM / YoY</span>
                    <p class="metric-value" style="font-size:1.8rem;"><?php echo ($mom_pct>=0?'+':''). number_format($mom_pct, 1); ?>%</p>
                    <span class="metric-trend">YoY: <?php echo ($yoy_pct>=0?'+':''). number_format($yoy_pct, 1); ?>%</span>
                </div>
            </div>
        </div>

        <div class="chart-section" style="margin-top:30px; display:grid; grid-template-columns:1fr; gap:30px;">
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-chart-area"></i> Evolución de Ventas (últimos 12 meses)</h2></div>
                <div class="chart-container" style="height:340px;"><canvas id="chartEvol"></canvas></div>
            </div>
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-chart-bar"></i> Margen por Línea (Top 15)</h2></div>
                <div class="chart-container" style="height:450px;"><canvas id="chartMargen"></canvas></div>
            </div>
        </div>

        <div class="card table-card" style="margin-top:30px;">
            <div class="t-header"><h2><i class="fas fa-table"></i> Desglose por Marca</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        <th>MARCA</th>
                        <th class="text-right">VENTAS ($)</th>
                        <th class="text-right">COSTO ($)</th>
                        <th class="text-right">MARGEN ($)</th>
                        <th class="text-center">MARGEN %</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($por_marca as $m):
                        $marg = $m['ventas'] - $m['costo'];
                        $mpct = $m['ventas'] > 0 ? ($marg / $m['ventas']) * 100 : 0;
                        $color = $mpct >= 30 ? 'var(--accent-green)' : ($mpct >= 15 ? 'var(--primary)' : 'var(--accent-orange)');
                    ?>
                        <tr>
                            <td style="font-weight:600;color:var(--primary);"><?php echo htmlspecialchars($m['marca']); ?></td>
                            <td class="text-right">$ <?php echo number_format($m['ventas'], 2, ',', '.'); ?></td>
                            <td class="text-right">$ <?php echo number_format($m['costo'], 2, ',', '.'); ?></td>
                            <td class="text-right" style="font-weight:700;color:<?php echo $color; ?>">$ <?php echo number_format($marg, 2, ',', '.'); ?></td>
                            <td class="text-center"><span class="badge" style="background:<?php echo $color; ?>;color:#000;padding:2px 8px;border-radius:4px;font-weight:800;"><?php echo number_format($mpct, 1); ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('chartEvol'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($evolucion, 'mes')); ?>,
            datasets: [{
                label: 'Ventas USD',
                data: <?php echo json_encode(array_column($evolucion, 'usd')); ?>,
                fill: true, tension: 0.4,
                backgroundColor: 'rgba(56,189,248,0.15)',
                borderColor: '#38bdf8',
                pointBackgroundColor: '#38bdf8', pointRadius: 4
            }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
    });
    new Chart(document.getElementById('chartMargen'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($por_marca, 'marca')); ?>,
            datasets: [
                {label:'Ventas', data:<?php echo json_encode(array_column($por_marca,'ventas')); ?>, backgroundColor:'rgba(56,189,248,0.55)'},
                {label:'Costo',  data:<?php echo json_encode(array_column($por_marca,'costo'));  ?>, backgroundColor:'rgba(251,146,60,0.55)'}
            ]
        },
        options:{responsive:true, maintainAspectRatio:false, indexAxis:'y'}
    });
});
</script>
<?php include('../../includes/footer.php'); ?>
