<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('GERENCIA')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}
/**
 * ============================================================
 * GERENCIA · INVENTARIO Y ROTACIÓN
 * Valor de inventario, rotación, días de stock,
 * productos sin movimiento.
 * ============================================================
 */
require_once('../../includes/db.php');

$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');
$dias_p = max(1, (strtotime($f_fin) - strtotime($f_ini)) / 86400 + 1);

// --- Resumen inventario ---
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS items,
        SUM(existen) AS unidades,
        SUM(existen * pondd) AS valor_usd,
        SUM(CASE WHEN existen <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN existen > 0 THEN 1 ELSE 0 END) AS con_stock
    FROM sinv
");
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
$items     = (int)($inv['items'] ?? 0);
$valor_inv = (float)($inv['valor_usd'] ?? 0);
$sin_stock = (int)($inv['sin_stock'] ?? 0);
$con_stock = (int)($inv['con_stock'] ?? 0);

// --- Costo de ventas del periodo (COGS) ---
$stmt_c = $pdo->prepare("
    SELECT SUM(i.cana * v.pondd) AS cogs, SUM(i.totad) AS ventas, SUM(i.cana) AS unid
    FROM itpfac i INNER JOIN sinv v ON i.codigoa = v.codigo
    WHERE i.fecha BETWEEN :ini AND :fin
");
$stmt_c->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$cv = $stmt_c->fetch(PDO::FETCH_ASSOC);
$cogs   = (float)($cv['cogs'] ?? 0);
$ventas = (float)($cv['ventas'] ?? 0);
$unid_v = (int)($cv['unid'] ?? 0);

// Rotación (anualizada) y días de stock
$factor_anual = 365 / $dias_p;
$rotacion = $valor_inv > 0 ? ($cogs / $valor_inv) * $factor_anual : 0;
$dias_stock = $rotacion > 0 ? 365 / $rotacion : 0;

// --- Productos sin movimiento (sin ventas en 6 meses pero con stock) ---
$stmt_sm = $pdo->query("
    SELECT v.codigo, v.descrip, v.marca, v.existen, v.pondd, (v.existen * v.pondd) AS valor
    FROM sinv v
    LEFT JOIN (SELECT DISTINCT codigoa FROM itpfac WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) m
      ON m.codigoa = v.codigo
    WHERE v.existen > 0 AND m.codigoa IS NULL
    ORDER BY valor DESC LIMIT 30
");
$sin_mov = $stmt_sm->fetchAll(PDO::FETCH_ASSOC);
$valor_sin_mov = array_sum(array_column($sin_mov, 'valor'));

// --- Top marcas por valor ---
$stmt_m = $pdo->query("
    SELECT COALESCE(NULLIF(marca,''),'SIN MARCA') AS marca,
           SUM(existen * pondd) AS valor
    FROM sinv WHERE existen > 0
    GROUP BY marca ORDER BY valor DESC LIMIT 10
");
$marcas_val = $stmt_m->fetchAll(PDO::FETCH_ASSOC);

// --- Rotación por marca (top 15) ---
$stmt_r = $pdo->prepare("
    SELECT v.marca,
           SUM(v.existen * v.pondd) AS inv_usd,
           COALESCE(s.cogs,0) AS cogs,
           COALESCE(s.unid,0) AS unid
    FROM sinv v
    LEFT JOIN (
        SELECT v2.marca, SUM(i.cana * v2.pondd) AS cogs, SUM(i.cana) AS unid
        FROM itpfac i INNER JOIN sinv v2 ON i.codigoa = v2.codigo
        WHERE i.fecha BETWEEN :ini AND :fin
        GROUP BY v2.marca
    ) s ON s.marca = v.marca
    WHERE v.existen > 0
    GROUP BY v.marca
    HAVING inv_usd > 0
    ORDER BY inv_usd DESC LIMIT 15
");
$stmt_r->execute([':ini'=>$f_ini,':fin'=>$f_fin]);
$rot_marca = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
$rot_labels = array_column($rot_marca, 'marca');
$rot_values = array_map(function($r) use ($factor_anual) {
    return $r['inv_usd'] > 0 ? round(($r['cogs'] / $r['inv_usd']) * $factor_anual, 2) : 0;
}, $rot_marca);

$pageTitle  = "Gerencia | Inventario";
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
            <a href="gerencia_inventario.php" class="nav-item active"><i class="fas fa-boxes"></i> <span>Inventario</span></a>
            <a href="gerencia_compras.php" class="nav-item"><i class="fas fa-truck-loading"></i> <span>Compras</span></a>
        </nav>

        <div class="page-title">
            <h1><i class="fas fa-boxes"></i> Inventario y Rotación</h1>
            <p>Valor, rotación anualizada, días de stock y productos sin movimiento.</p>
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
                <div class="metric-icon"><i class="fas fa-warehouse"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Valor Inventario</span>
                    <p class="metric-value">$ <?php echo number_format($valor_inv, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format($con_stock, 0, ',', '.'); ?> activos · <?php echo number_format($sin_stock,0,',','.'); ?> sin stock</span>
                </div>
            </div>
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-sync-alt"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Rotación (anualizada)</span>
                    <p class="metric-value"><?php echo number_format($rotacion, 2); ?>x</p>
                    <span class="metric-trend">Veces al año</span>
                </div>
            </div>
            <div class="card metric-card warning">
                <div class="metric-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Días de Stock</span>
                    <p class="metric-value"><?php echo number_format($dias_stock, 0); ?></p>
                    <span class="metric-trend">Cobertura promedio</span>
                </div>
            </div>
            <div class="card metric-card alert">
                <div class="metric-icon"><i class="fas fa-snowflake"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Sin Movimiento (6m)</span>
                    <p class="metric-value">$ <?php echo number_format($valor_sin_mov, 0, ',', '.'); ?></p>
                    <span class="metric-trend">Top 30 capital inmovilizado</span>
                </div>
            </div>
        </div>

        <div class="chart-section" style="margin-top:30px; display:grid; grid-template-columns:1fr 1fr; gap:30px;">
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-chart-pie"></i> Valor por Marca (Top 10)</h2></div>
                <div class="chart-container" style="height:340px;"><canvas id="chartVal"></canvas></div>
            </div>
            <div class="card chart-card">
                <div class="t-header"><h2><i class="fas fa-chart-bar"></i> Rotación por Marca</h2></div>
                <div class="chart-container" style="height:340px;"><canvas id="chartRot"></canvas></div>
            </div>
        </div>

        <div class="card table-card" style="margin-top:30px;">
            <div class="t-header"><h2><i class="fas fa-snowflake"></i> Productos sin Movimiento (últimos 6 meses)</h2></div>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        <th>CÓDIGO</th><th>DESCRIPCIÓN</th><th>MARCA</th>
                        <th class="text-center">EXISTENCIA</th>
                        <th class="text-right">COSTO U.</th>
                        <th class="text-right">VALOR ($)</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($sin_mov as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['codigo']); ?></td>
                            <td style="color:var(--primary);"><?php echo htmlspecialchars($p['descrip']); ?></td>
                            <td><?php echo htmlspecialchars($p['marca']); ?></td>
                            <td class="text-center"><?php echo number_format($p['existen'], 0, ',', '.'); ?></td>
                            <td class="text-right">$ <?php echo number_format($p['pondd'], 2, ',', '.'); ?></td>
                            <td class="text-right" style="font-weight:700;color:var(--accent-orange);">$ <?php echo number_format($p['valor'], 2, ',', '.'); ?></td>
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
    new Chart(document.getElementById('chartVal'), {
        type:'pie',
        data:{
            labels: <?php echo json_encode(array_column($marcas_val,'marca')); ?>,
            datasets:[{
                data: <?php echo json_encode(array_column($marcas_val,'valor')); ?>,
                backgroundColor:['#38bdf8','#34d399','#fbbf24','#fb923c','#f87171','#a78bfa','#22d3ee','#f472b6','#facc15','#4ade80']
            }]
        },
        options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'right'}}}
    });
    new Chart(document.getElementById('chartRot'), {
        type:'bar',
        data:{
            labels: <?php echo json_encode($rot_labels); ?>,
            datasets:[{
                label:'Rotación (x)',
                data: <?php echo json_encode($rot_values); ?>,
                backgroundColor:'rgba(52,211,153,0.55)'
            }]
        },
        options:{responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}}
    });
});
</script>
<?php include('../../includes/footer.php'); ?>
