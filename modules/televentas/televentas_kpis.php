<?php
/**
 * ============================================================
 * KPI DE TELEVENTAS POR USUARIO - NOTIPRO / ProteoERP
 * ============================================================
 */

require_once('../../includes/db.php');

// ============================================================
// CONFIGURACIÓN DE PÁGINA
// ============================================================
$pageTitle  = "ProteoERP | KPIs de Televentas";
$activePage = "televentas";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');

// --- Filtros de búsqueda ---
$f_ini      = $_GET['f_ini']      ?? date('Y-m-01');
$f_fin      = $_GET['f_fin']      ?? date('Y-m-d');

// ============================================================
// QUERIES
// ============================================================
try {
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    $where  = "WHERE f.fecha >= :ini AND f.fecha <= :fin";

    // --------------------------------------------------------
    // KPIs Globales
    // --------------------------------------------------------
    $stmt_global = $pdo->prepare(
        "SELECT
             COUNT(DISTINCT f.numero)                                         AS total_pedidos,
             SUM(f.totalg)                                                    AS total_bs,
             SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2)
                      WHEN f.totalg > 0 THEN f.totalg
                      ELSE 0 END) AS total_usd,
             COUNT(DISTINCT f.usuario)                                        AS total_usuarios
         FROM pfac f
         $where"
    );
    $stmt_global->execute($params);
    $global_kpis = $stmt_global->fetch(PDO::FETCH_ASSOC);

    $total_pedidos  = $global_kpis['total_pedidos']  ?? 0;
    $total_bs       = $global_kpis['total_bs']       ?? 0;
    $total_usd      = $global_kpis['total_usd']      ?? 0;
    $total_usuarios = $global_kpis['total_usuarios'] ?? 0;

    // --------------------------------------------------------
    // Ranking por Usuario
    // --------------------------------------------------------
    $stmt_rank = $pdo->prepare(
        "SELECT
             f.usuario                                                              AS us_codigo,
             COALESCE(NULLIF(u.us_nombre, ''), f.usuario)                          AS us_nombre,
             COUNT(DISTINCT f.numero)                                               AS pedidos,
             SUM(f.totalg)                                                          AS total_bs,
             SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2)
                      WHEN f.totalg > 0 THEN f.totalg
                      ELSE 0 END) AS total_usd,
             COUNT(DISTINCT f.vd)                                                   AS vendedores_atendidos
         FROM pfac f
         LEFT JOIN usuario u ON u.us_codigo = f.usuario
         $where
         GROUP BY f.usuario
         ORDER BY total_usd DESC"
    );
    $stmt_rank->execute($params);
    $ranking_usuarios = $stmt_rank->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('televentas_kpis error: ' . $e->getMessage());
    http_response_code(500);
    include '../../errors/500.php';
    exit;
}

// Prepara datos para el gráfico
$labels  = [];
$data_usd = [];
$palette = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#f8f9fc', '#4e73df', '#1cc88a'];

foreach ($ranking_usuarios as $row) {
    $labels[]   = $row['us_nombre'];
    $data_usd[] = (float)$row['total_usd'];
}
?>

<main class="main-content">
    <?php include("../../includes/navbar.php"); ?>
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="vista_televentas.php" class="nav-item">
            <i class="fas fa-arrow-left"></i>
            <span>Volver a Monitor</span>
        </a>
        <a href="televentas_kpis.php" class="nav-item active">
            <i class="fas fa-chart-line"></i>
            <span>KPIs Televentas</span>
        </a>
    </nav>

    <!-- CABECERA -->
    <div class="tv-header-row">
        <div class="tv-title-block">
            <div class="tv-module-tag"><i class="fas fa-chart-bar"></i> KPIs por Usuario</div>
            <h1>Rendimiento de Televentas</h1>
            <p class="tv-period">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y', strtotime($f_ini)); ?> &mdash; <?php echo date('d/m/Y', strtotime($f_fin)); ?>
            </p>
        </div>
        <form method="GET" class="tv-filter-compact">
            <div class="tv-input-wrap">
                <label>Desde</label>
                <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
            </div>
            <div class="tv-input-wrap">
                <label>Hasta</label>
                <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
            </div>
            <button type="submit" class="tv-btn-apply">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </form>
    </div>

    <!-- METRIC CARDS -->
    <div class="metrics-grid">
        <div class="card metric-card success">
            <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-content">
                <span class="metric-label">Total Recaudado (USD)</span>
                <p class="metric-value">$ <?php echo number_format($total_usd, 2); ?></p>
            </div>
        </div>
        <div class="card metric-card primary">
            <div class="metric-icon"><i class="fas fa-users"></i></div>
            <div class="metric-content">
                <span class="metric-label">Operadores Activos</span>
                <p class="metric-value"><?php echo $total_usuarios; ?></p>
            </div>
        </div>
        <div class="card metric-card warning">
            <div class="metric-icon"><i class="fas fa-shopping-basket"></i></div>
            <div class="metric-content">
                <span class="metric-label">Total Pedidos</span>
                <p class="metric-value"><?php echo number_format($total_pedidos, 0); ?></p>
            </div>
        </div>
        <div class="card metric-card info">
            <div class="metric-icon"><i class="fas fa-calculator"></i></div>
            <div class="metric-content">
                <span class="metric-label">Ticket Promedio</span>
                <p class="metric-value">$ <?php echo ($total_pedidos > 0) ? number_format($total_usd / $total_pedidos, 2) : '0.00'; ?></p>
            </div>
        </div>
    </div>

    <div class="tv-analysis-grid">
        <!-- Gráfico de Participación -->
        <div class="card tv-chart-card">
            <div class="tv-card-hd">
                <span class="tv-card-title"><i class="fas fa-chart-pie"></i> Participación por Usuario</span>
            </div>
            <div class="chart-container" style="position: relative; height:300px; width:100%">
                <canvas id="chartUsuarios"></canvas>
            </div>
        </div>

        <!-- Tabla de Ranking -->
        <div class="card tv-ranking-card">
            <div class="tv-card-hd">
                <span class="tv-card-title"><i class="fas fa-trophy"></i> Ranking de Operadores</span>
                <button class="btn-neon btn-green btn-sm-export" onclick="exportXls('table-users','KPI_Usuarios')">
                    <i class="fas fa-file-excel"></i> XLS
                </button>
            </div>
            <div class="table-container">
                <table id="table-users">
                    <thead>
                        <tr>
                            <th>OPERADOR</th>
                            <th class="c">PEDIDOS</th>
                            <th class="r">TOTAL USD</th>
                            <th class="c">VEND. ATENDIDOS</th>
                            <th>PART.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ranking_usuarios)): ?>
                        <tr><td colspan="5" class="text-center">No hay datos en este período.</td></tr>
                    <?php else:
                        foreach ($ranking_usuarios as $i => $u):
                            $pct = ($total_usd > 0) ? round(($u['total_usd'] / $total_usd) * 100, 1) : 0;
                            $color = $palette[$i % count($palette)];
                    ?>
                        <tr>
                            <td>
                                <div class="text-main-bold"><?php echo htmlspecialchars($u['us_nombre']); ?></div>
                                <div class="text-muted-sm">ID: <?php echo htmlspecialchars($u['us_codigo']); ?></div>
                            </td>
                            <td class="c"><strong><?php echo $u['pedidos']; ?></strong></td>
                            <td class="r amount-usd">$ <?php echo number_format($u['total_usd'], 2); ?></td>
                            <td class="c"><?php echo $u['vendedores_atendidos']; ?></td>
                            <td>
                                <div class="progress-pct"><?php echo $pct; ?>%</div>
                                <div class="progress-bar-wrap">
                                    <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</main>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<script>
function exportXls(tableId, name) {
    const t = document.getElementById(tableId);
    if (!t) return;
    const wb = XLSX.utils.table_to_book(t, { sheet: name });
    XLSX.writeFile(wb, name + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
}

document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('chartUsuarios'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                data: <?php echo json_encode($data_usd); ?>,
                backgroundColor: <?php echo json_encode(array_slice($palette, 0, count($labels))); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { color: '#fff' } }
            }
        }
    });
});
</script>

<?php include('../../includes/footer.php'); ?>
