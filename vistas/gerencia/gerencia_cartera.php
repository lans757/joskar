<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
/**
 * ============================================================
 * GERENCIA · CARTERA Y COBRANZAS
 * DSO, antigüedad de saldos, recaudo del periodo, gestores.
 * ============================================================
 */
require_once('../../includes/db.php');

$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');

// --- Recaudo del periodo (gecli) ---
$stmt_r = $pdo->prepare("
    SELECT
        COUNT(*) AS operaciones,
        SUM(a.monto) AS bs,
        SUM(IF(a.mdolar <> 0, a.mdolar,
            ROUND(a.monto / NULLIF((SELECT oficial FROM monecam WHERE moneda='USD' AND fecha <= a.fbanco ORDER BY fecha DESC LIMIT 1), 0), 2)
        )) AS usd
    FROM gecli a
    WHERE a.fbanco BETWEEN :ini AND :fin
");
$stmt_r->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$rec = $stmt_r->fetch(PDO::FETCH_ASSOC);
$rec_ops = (int)($rec['operaciones'] ?? 0);
$rec_bs  = (float)($rec['bs']  ?? 0);
$rec_usd = (float)($rec['usd'] ?? 0);

// --- Ventas del periodo (para DSO) ---
$stmt_v = $pdo->prepare("
    SELECT SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS usd
    FROM sfac f WHERE f.fecha BETWEEN :ini AND :fin
");
$stmt_v->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$ventas_usd = (float)$stmt_v->fetchColumn();
$dias = max(1, (strtotime($f_fin) - strtotime($f_ini)) / 86400 + 1);

// --- Cartera por antigüedad (facturas pendientes) ---
// Se asume sfac.status no 'C' = pendiente; saldo = totalg si no hay smov de cancelación
$stmt_a = $pdo->query("
    SELECT
        SUM(CASE WHEN DATEDIFF(CURDATE(), fecha) <= 30 THEN saldo_usd ELSE 0 END) AS d_0_30,
        SUM(CASE WHEN DATEDIFF(CURDATE(), fecha) BETWEEN 31 AND 60 THEN saldo_usd ELSE 0 END) AS d_31_60,
        SUM(CASE WHEN DATEDIFF(CURDATE(), fecha) BETWEEN 61 AND 90 THEN saldo_usd ELSE 0 END) AS d_61_90,
        SUM(CASE WHEN DATEDIFF(CURDATE(), fecha) > 90 THEN saldo_usd ELSE 0 END) AS d_91_mas,
        SUM(saldo_usd) AS total_cartera
    FROM (
        SELECT f.numero, f.fecha,
               CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END AS saldo_usd
        FROM sfac f
        WHERE f.status <> 'C' AND f.totalg > 0
    ) t
");
$ant = $stmt_a->fetch(PDO::FETCH_ASSOC);
$d_0_30   = (float)($ant['d_0_30']   ?? 0);
$d_31_60  = (float)($ant['d_31_60']  ?? 0);
$d_61_90  = (float)($ant['d_61_90']  ?? 0);
$d_91_mas = (float)($ant['d_91_mas'] ?? 0);
$cartera_total = (float)($ant['total_cartera'] ?? 0);
$cartera_vencida = $d_31_60 + $d_61_90 + $d_91_mas;
$pct_vencida = $cartera_total > 0 ? ($cartera_vencida / $cartera_total) * 100 : 0;

// --- DSO (días en cuentas por cobrar) ---
$dso = $ventas_usd > 0 ? ($cartera_total / $ventas_usd) * $dias : 0;

// --- Recaudo por banco/método ---
$stmt_b = $pdo->prepare("
    SELECT COALESCE(NULLIF(b.banco,''),'EFECTIVO') AS banco,
           COUNT(*) AS ops,
           SUM(a.monto) AS bs,
           SUM(IF(a.mdolar<>0,a.mdolar,
                ROUND(a.monto / NULLIF((SELECT oficial FROM monecam WHERE moneda='USD' AND fecha<=a.fbanco ORDER BY fecha DESC LIMIT 1),0),2)
           )) AS usd
    FROM gecli a LEFT JOIN banc b ON a.codbanc = b.codbanc
    WHERE a.fbanco BETWEEN :ini AND :fin
    GROUP BY banco ORDER BY usd DESC
");
$stmt_b->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$por_banco = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

// --- Top clientes deudores ---
$stmt_d = $pdo->query("
    SELECT f.cod_cli, f.nombre,
           COUNT(DISTINCT f.numero) AS facturas,
           SUM(CASE WHEN f.tasa>0 THEN ROUND(f.totalg/f.tasa,2) ELSE 0 END) AS saldo_usd,
           MAX(DATEDIFF(CURDATE(), f.fecha)) AS dias_max
    FROM sfac f
    WHERE f.status <> 'C' AND f.totalg > 0
    GROUP BY f.cod_cli, f.nombre
    ORDER BY saldo_usd DESC LIMIT 25
");
$top_deudores = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = "Gerencia | Cartera";
$activePage = "gerencia";
$path_prefix = "../../";
include('../../includes/header.php');
include('../../includes/sidebar.php');
?>
<main class="main-content">
    <div class="content-wrapper">
        <nav class="module-nav">
            <a href="vista_gerencia.php" class="nav-item"><i class="fas fa-home"></i> <span>Resumen</span></a>
            <a href="gerencia_ventas.php" class="nav-item"><i class="fas fa-chart-line"></i> <span>Ventas y Margen</span></a>
            <a href="gerencia_cartera.php" class="nav-item active"><i class="fas fa-hand-holding-usd"></i> <span>Cartera</span></a>
            <a href="gerencia_inventario.php" class="nav-item"><i class="fas fa-boxes"></i> <span>Inventario</span></a>
            <a href="gerencia_compras.php" class="nav-item"><i class="fas fa-truck-loading"></i> <span>Compras</span></a>
        </nav>

        <div class="page-title">
            <h1><i class="fas fa-hand-holding-usd"></i> Cartera y Cobranzas</h1>
            <p>Recaudo del periodo, DSO, antigüedad de saldos y top deudores.</p>
        </div>

        <section class="card filters-card" style="margin-bottom:30px;">
            <form method="GET" class="filters-row">
                <div class="filter-group"><label>Desde</label><input type="date" name="f_ini" value="<?php echo htmlspecialchars($f_ini); ?>"></div>
                <div class="filter-group"><label>Hasta</label><input type="date" name="f_fin" value="<?php echo htmlspecialchars($f_fin); ?>"></div>
                <div class="btn-group"><button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i> Actualizar</button></div>
            </form>
        </section>

        <div class="metrics-grid">
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-coins"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Recaudo (USD)</span>
                    <p class="metric-value">$ <?php echo number_format($rec_usd, 2, ',', '.'); ?></p>
                    <span class="metric-trend">Bs. <?php echo number_format($rec_bs, 2, ',', '.'); ?> · <?php echo $rec_ops; ?> ops</span>
                </div>
            </div>
            <div class="card metric-card primary">
                <div class="metric-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Cartera Total</span>
                    <p class="metric-value">$ <?php echo number_format($cartera_total, 2, ',', '.'); ?></p>
                    <span class="metric-trend">Saldo por cobrar</span>
                </div>
            </div>
            <div class="card metric-card <?php echo $pct_vencida > 30 ? 'alert' : 'warning'; ?>">
                <div class="metric-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Cartera Vencida (>30d)</span>
                    <p class="metric-value">$ <?php echo number_format($cartera_vencida, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format($pct_vencida, 1); ?>% de la cartera</span>
                </div>
            </div>
            <div class="card metric-card warning">
                <div class="metric-icon"><i class="fas fa-stopwatch"></i></div>
                <div class="metric-content">
                    <span class="metric-label">DSO (Días)</span>
                    <p class="metric-value"><?php echo number_format($dso, 0); ?></p>
                    <span class="metric-trend">Días promedio de cobro</span>
                </div>
            </div>
        </div>

        <div class="chart-section" style="margin-top:30px; display:grid; grid-template-columns:1fr 1fr; gap:30px;">
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-layer-group"></i> Antigüedad de Saldos</h2></div>
                <div class="chart-container" style="height:320px;"><canvas id="chartAging"></canvas></div>
            </div>
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-university"></i> Recaudo por Banco/Método</h2></div>
                <div class="chart-container" style="height:320px;"><canvas id="chartBanco"></canvas></div>
            </div>
        </div>

        <div class="card table-card" style="margin-top:30px;">
            <div class="t-header"><h2><i class="fas fa-user-tag"></i> Top Clientes Deudores</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        <th>CÓDIGO</th><th>CLIENTE</th>
                        <th class="text-center">FACTURAS</th>
                        <th class="text-right">SALDO ($)</th>
                        <th class="text-center">DÍAS MAX</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($top_deudores as $d):
                        $col = $d['dias_max'] > 90 ? 'var(--accent-red)' : ($d['dias_max']>30?'var(--accent-orange)':'var(--accent-green)');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['cod_cli']); ?></td>
                            <td style="color:var(--primary);font-weight:600;"><?php echo htmlspecialchars($d['nombre']); ?></td>
                            <td class="text-center"><?php echo $d['facturas']; ?></td>
                            <td class="text-right" style="font-weight:700;">$ <?php echo number_format($d['saldo_usd'], 2, ',', '.'); ?></td>
                            <td class="text-center"><span class="badge" style="background:<?php echo $col; ?>;color:#000;padding:2px 8px;border-radius:4px;font-weight:800;"><?php echo $d['dias_max']; ?>d</span></td>
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
    new Chart(document.getElementById('chartAging'), {
        type: 'bar',
        data: {
            labels: ['0-30 días','31-60','61-90','+90'],
            datasets: [{
                data: [<?php echo $d_0_30; ?>, <?php echo $d_31_60; ?>, <?php echo $d_61_90; ?>, <?php echo $d_91_mas; ?>],
                backgroundColor: ['#34d399','#fbbf24','#fb923c','#f87171']
            }]
        },
        options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}}
    });
    new Chart(document.getElementById('chartBanco'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($por_banco,'banco')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($por_banco,'usd')); ?>,
                backgroundColor: ['#38bdf8','#34d399','#fbbf24','#fb923c','#f87171','#a78bfa','#22d3ee']
            }]
        },
        options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'right'}}}
    });
});
</script>
<?php include('../../includes/footer.php'); ?>
