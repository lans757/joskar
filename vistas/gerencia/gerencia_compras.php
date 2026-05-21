<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('GERENCIA')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}
/**
 * ============================================================
 * GERENCIA · COMPRAS + TOP CLIENTES / VENDEDORES
 * Compras del periodo, top proveedores, top clientes y
 * ranking de vendedores.
 * ============================================================
 */
require_once('../../includes/db.php');

$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');

// --- Compras del periodo ---
$stmt_c = $pdo->prepare("
    SELECT COUNT(*) AS docs,
           COUNT(DISTINCT proveed) AS proveedores,
           SUM(ctotald) AS total_usd
    FROM scst WHERE recep BETWEEN :ini AND :fin
");
$stmt_c->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$cp = $stmt_c->fetch(PDO::FETCH_ASSOC);
$c_docs = (int)($cp['docs'] ?? 0);
$c_prov = (int)($cp['proveedores'] ?? 0);
$c_usd  = (float)($cp['total_usd'] ?? 0);

// --- Ventas del periodo (para comparativa V/C) ---
$stmt_v = $pdo->prepare("
    SELECT SUM(CASE WHEN f.tasa>0 THEN ROUND(f.totalg/f.tasa,2) ELSE 0 END) AS usd,
           COUNT(DISTINCT f.numero) AS facturas
    FROM sfac f WHERE f.fecha BETWEEN :ini AND :fin
");
$stmt_v->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$vs = $stmt_v->fetch(PDO::FETCH_ASSOC);
$v_usd = (float)($vs['usd'] ?? 0);
$v_fac = (int)($vs['facturas'] ?? 0);
$ratio_vc = $c_usd > 0 ? $v_usd / $c_usd : 0;

// --- Top proveedores ---
$stmt_tp = $pdo->prepare("
    SELECT c.proveed, p.nombre, COUNT(*) AS docs, SUM(c.ctotald) AS total
    FROM scst c LEFT JOIN sprv p ON p.proveed = c.proveed
    WHERE c.recep BETWEEN :ini AND :fin
    GROUP BY c.proveed, p.nombre
    ORDER BY total DESC LIMIT 15
");
$stmt_tp->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$top_prov = $stmt_tp->fetchAll(PDO::FETCH_ASSOC);

// --- Top clientes ---
$stmt_tc = $pdo->prepare("
    SELECT f.cod_cli, f.nombre,
           COUNT(DISTINCT f.numero) AS facturas,
           SUM(CASE WHEN f.tasa>0 THEN ROUND(f.totalg/f.tasa,2) ELSE 0 END) AS usd
    FROM sfac f
    WHERE f.fecha BETWEEN :ini AND :fin
    GROUP BY f.cod_cli, f.nombre
    ORDER BY usd DESC LIMIT 15
");
$stmt_tc->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$top_cli = $stmt_tc->fetchAll(PDO::FETCH_ASSOC);

// --- Top vendedores (pfac.usuario) ---
$stmt_tv = $pdo->prepare("
    SELECT COALESCE(NULLIF(usuario,''),'Sin Vendedor') AS vendedor,
           COUNT(DISTINCT numero) AS pedidos,
           SUM(totalg) AS bs,
           SUM(CASE WHEN dolar>0 THEN ROUND(totalg/dolar,2) ELSE 0 END) AS usd
    FROM pfac WHERE fecha BETWEEN :ini AND :fin
    GROUP BY usuario ORDER BY usd DESC LIMIT 15
");
$stmt_tv->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$top_vend = $stmt_tv->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = "Gerencia | Compras y Rankings";
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
            <a href="gerencia_cartera.php" class="nav-item"><i class="fas fa-hand-holding-usd"></i> <span>Cartera</span></a>
            <a href="gerencia_inventario.php" class="nav-item"><i class="fas fa-boxes"></i> <span>Inventario</span></a>
            <a href="gerencia_compras.php" class="nav-item active"><i class="fas fa-truck-loading"></i> <span>Compras</span></a>
        </nav>

        <div class="page-title">
            <h1><i class="fas fa-truck-loading"></i> Compras y Rankings</h1>
            <p>Compras del periodo, top proveedores, clientes y vendedores.</p>
        </div>

        <section class="card filters-card" style="margin-bottom:30px;">
            <form method="GET" class="filters-row">
                <div class="filter-group"><label>Desde</label><input type="date" name="f_ini" value="<?php echo htmlspecialchars($f_ini); ?>"></div>
                <div class="filter-group"><label>Hasta</label><input type="date" name="f_fin" value="<?php echo htmlspecialchars($f_fin); ?>"></div>
                <div class="btn-group"><button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i> Actualizar</button></div>
            </form>
        </section>

        <div class="metrics-grid">
            <div class="card metric-card alert">
                <div class="metric-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Compras (USD)</span>
                    <p class="metric-value">$ <?php echo number_format($c_usd, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo $c_docs; ?> docs · <?php echo $c_prov; ?> proveedores</span>
                </div>
            </div>
            <div class="card metric-card primary">
                <div class="metric-icon"><i class="fas fa-cash-register"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ventas (USD)</span>
                    <p class="metric-value">$ <?php echo number_format($v_usd, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo $v_fac; ?> facturas</span>
                </div>
            </div>
            <div class="card metric-card <?php echo $ratio_vc >= 1.2 ? 'success' : ($ratio_vc>=1?'warning':'alert'); ?>">
                <div class="metric-icon"><i class="fas fa-balance-scale"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ratio Ventas / Compras</span>
                    <p class="metric-value"><?php echo number_format($ratio_vc, 2); ?>x</p>
                    <span class="metric-trend">Salud comercial</span>
                </div>
            </div>
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-users"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Top Vendedores</span>
                    <p class="metric-value"><?php echo count($top_vend); ?></p>
                    <span class="metric-trend">Con actividad en periodo</span>
                </div>
            </div>
        </div>

        <div class="chart-section" style="margin-top:30px; display:grid; grid-template-columns:1fr 1fr; gap:30px;">
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-industry"></i> Top Proveedores</h2></div>
                <div class="chart-container" style="height:380px;"><canvas id="chartProv"></canvas></div>
            </div>
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-user-friends"></i> Top Clientes</h2></div>
                <div class="chart-container" style="height:380px;"><canvas id="chartCli"></canvas></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-top:30px;">
            <div class="card table-card">
                <div class="t-header"><h2><i class="fas fa-truck"></i> Detalle Proveedores</h2></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>PROVEEDOR</th><th class="text-center">DOCS</th><th class="text-right">TOTAL ($)</th></tr></thead>
                        <tbody>
                        <?php foreach($top_prov as $p): ?>
                            <tr>
                                <td style="color:var(--primary);"><?php echo htmlspecialchars($p['nombre'] ?: $p['proveed']); ?></td>
                                <td class="text-center"><?php echo $p['docs']; ?></td>
                                <td class="text-right" style="font-weight:700;">$ <?php echo number_format($p['total'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card table-card">
                <div class="t-header"><h2><i class="fas fa-trophy"></i> Top Vendedores</h2></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>VENDEDOR</th><th class="text-center">PEDIDOS</th><th class="text-right">USD</th></tr></thead>
                        <tbody>
                        <?php foreach($top_vend as $v): ?>
                            <tr>
                                <td style="color:var(--primary);font-weight:600;"><?php echo htmlspecialchars($v['vendedor']); ?></td>
                                <td class="text-center"><?php echo $v['pedidos']; ?></td>
                                <td class="text-right" style="font-weight:700;color:var(--accent-green);">$ <?php echo number_format($v['usd'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('chartProv'), {
        type:'bar',
        data:{
            labels: <?php echo json_encode(array_map(fn($p)=>$p['nombre']?:$p['proveed'], $top_prov)); ?>,
            datasets:[{label:'Compras USD', data: <?php echo json_encode(array_column($top_prov,'total')); ?>, backgroundColor:'rgba(248,113,113,0.55)'}]
        },
        options:{responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}}
    });
    new Chart(document.getElementById('chartCli'), {
        type:'bar',
        data:{
            labels: <?php echo json_encode(array_column($top_cli,'nombre')); ?>,
            datasets:[{label:'Ventas USD', data: <?php echo json_encode(array_column($top_cli,'usd')); ?>, backgroundColor:'rgba(56,189,248,0.55)'}]
        },
        options:{responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}}
    });
});
</script>
<?php include('../../includes/footer.php'); ?>
