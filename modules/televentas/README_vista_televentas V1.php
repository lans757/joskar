<?php
/**
 * ============================================================
 * MONITOR DE TELEVENTAS - NOTIPRO / ProteoERP
 * Vista de ranking, gráfico de participación y detalle AJAX
 * Basado en el patrón de vista_cobranzas.php
 * ============================================================
 */

require_once('../../includes/db.php');

// --- Manejo AJAX: Detalle de pedidos por vendedor ---
// DEBE IR AL PRINCIPIO PARA EVITAR SALIDA HTML EN LA RESPUESTA JSON
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalle_vendedor') {
    $cod_vend = $_GET['cod_vend'] ?? '';
    $f_ini    = $_GET['f_ini']    ?? date('Y-m-01');
    $f_fin    = $_GET['f_fin']    ?? date('Y-m-d');

    header('Content-Type: application/json');
    try {
        // Datos del vendedor
        $stmt_vend = $pdo->prepare(
            "SELECT v.vendedor AS codvend, v.nombre AS nombre_vend, v.telefono, v.email AS correo
             FROM vend v
             WHERE v.vendedor = :cod
             LIMIT 1"
        );
        $stmt_vend->execute([':cod' => $cod_vend]);
        $vendedor = $stmt_vend->fetch(PDO::FETCH_ASSOC);

        // Pedidos del vendedor en el rango de fechas
        $stmt_ped = $pdo->prepare(
            "SELECT f.numero    AS pedido,
                    f.fecha     AS fecha_ped,
                    f.cod_cli,
                    f.nombre    AS cliente,
                    f.status    AS estatus,
                    f.totalg    AS total_bs,
                    f.tasa,
                    CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END AS total_usd
             FROM sfac f
             WHERE f.vd  = :cod
               AND f.tipo_doc  = 'F'
               AND f.fecha    >= :ini
               AND f.fecha    <= :fin
             ORDER BY f.fecha DESC, f.numero DESC
             LIMIT 200"
        );
        $stmt_ped->execute([
            ':cod' => $cod_vend,
            ':ini' => $f_ini,
            ':fin' => $f_fin,
        ]);
        $pedidos = $stmt_ped->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['vendedor' => $vendedor, 'pedidos' => $pedidos]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'AJAX_ERR: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// CONFIGURACIÓN DE PÁGINA
// ============================================================
$pageTitle  = "ProteoERP | Monitor de Televentas";
$activePage = "televentas";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');

// --- Filtros de búsqueda ---
$f_ini  = $_GET['f_ini']  ?? date('Y-m-01');
$f_fin  = $_GET['f_fin']  ?? date('Y-m-d');
$f_txt  = $_GET['f_txt']  ?? '';   // Buscar por nombre vendedor o cliente

// --- Paginación para tabla de detalle completo ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$offset = ($page - 1) * $limit;

// ============================================================
// QUERIES
// ============================================================
try {
    // --- Parámetros base ---
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    $where  = "WHERE f.tipo_doc = 'F' AND f.fecha >= :ini AND f.fecha <= :fin";

    // Filtro de texto libre (vendedor o cliente)
    if (!empty($f_txt)) {
        $where .= " AND (v.nombre LIKE :txt OR f.nombre LIKE :txt2 OR f.numero LIKE :txt3)";
        $params[':txt']  = "%$f_txt%";
        $params[':txt2'] = "%$f_txt%";
        $params[':txt3'] = "%$f_txt%";
    }

    // --------------------------------------------------------
    // KPIs Globales
    // --------------------------------------------------------
    $stmt_kpi = $pdo->prepare(
        "SELECT
             COUNT(DISTINCT f.numero)                                         AS total_pedidos,
             SUM(f.totalg)                                                    AS total_bs,
             SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS total_usd,
             COUNT(DISTINCT f.vd)                                       AS total_vendedores
         FROM sfac f
         LEFT JOIN vend v ON v.vendedor = f.vd
         $where"
    );
    $stmt_kpi->execute($params);
    $kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);

    $total_pedidos    = $kpis['total_pedidos']    ?? 0;
    $total_bs         = $kpis['total_bs']         ?? 0;
    $total_usd        = $kpis['total_usd']        ?? 0;
    $total_vendedores = $kpis['total_vendedores'] ?? 0;

    // Promedio USD por vendedor (sólo si hay vendedores)
    $prom_usd = ($total_vendedores > 0) ? ($total_usd / $total_vendedores) : 0;

    // --------------------------------------------------------
    // Resumen / Ranking por Vendedor (para tabla y gráfico)
    // --------------------------------------------------------
    $stmt_rank = $pdo->prepare(
        "SELECT
             f.vd                                                                   AS codvend,
             COALESCE(NULLIF(v.nombre, ''), CONCAT('Vend. ', f.vd))                 AS nombre_vend,
             COUNT(DISTINCT f.numero)                                               AS pedidos,
             SUM(f.totalg)                                                          AS total_bs,
             SUM(CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END) AS total_usd
         FROM sfac f
         LEFT JOIN vend v ON v.vendedor = f.vd
         $where
         GROUP BY f.vd, nombre_vend
         ORDER BY total_usd DESC"
    );
    $stmt_rank->execute($params);
    $ranking = $stmt_rank->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------------
    // Total de registros para paginación del detalle completo
    // --------------------------------------------------------
    $stmt_cnt = $pdo->prepare(
        "SELECT COUNT(DISTINCT f.numero) AS total
         FROM sfac f
         LEFT JOIN vend v ON v.vendedor = f.vd
         $where"
    );
    $stmt_cnt->execute($params);
    $total_rows  = (int)($stmt_cnt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $total_pages = ($total_rows > 0) ? (int)ceil($total_rows / $limit) : 1;

    // --------------------------------------------------------
    // Detalle de Pedidos (tabla paginada inferior)
    // --------------------------------------------------------
    $stmt_det = $pdo->prepare(
        "SELECT
             f.numero                                                               AS pedido,
             f.fecha                                                                AS fecha_ped,
             f.cod_cli,
             f.nombre                                                               AS cliente,
             f.vd                                                                   AS codvend,
             COALESCE(NULLIF(v.nombre, ''), CONCAT('Vend. ', f.vd))                 AS nombre_vend,
             f.status                                                               AS estatus,
             f.tasa,
             f.totalg                                                               AS total_bs,
             CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END     AS total_usd
         FROM sfac f
         LEFT JOIN vend v ON v.vendedor = f.vd
         $where
         ORDER BY f.fecha DESC, f.numero DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt_det->execute($params);
    $pedidos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos (TELEVENTAS): [" . $e->getCode() . "]: " . $e->getMessage());
}

// ============================================================
// HELPERS de renderizado (mismo patrón que cobranzas)
// ============================================================

/**
 * Genera un badge de estatus para pedidos.
 * Statuses comunes en F: 'C' = Cerrado, 'P' = Pendiente, 'X' = Anulado
 */
function renderEstatusPed(string $e): string {
    $e = strtoupper(trim($e));
    return match($e) {
        'C' => "<span class='badge badge-ok'><i class='fas fa-check-circle'></i> CERRADO</span>",
        'P' => "<span class='badge badge-low'><i class='fas fa-clock'></i> PENDIENTE</span>",
        'X' => "<span class='badge badge-critical'><i class='fas fa-times-circle'></i> ANULADO</span>",
        default => "<span class='badge' style='background:rgba(255,255,255,0.05);color:var(--text-muted);border:1px solid var(--border);'>$e</span>",
    };
}

/**
 * Prepara los datos del ranking para Chart.js (JSON seguro).
 * Retorna array ['labels' => [...], 'data' => [...], 'colores' => [...]]
 */
function prepararDatosGrafico(array $ranking): array {
    // Paleta base de colores (se cicla si hay más vendedores)
    $palette = [
        'rgba(0,180,255,0.85)',   // azul primario
        'rgba(0,230,118,0.85)',   // verde acento
        'rgba(255,193,7,0.85)',   // amarillo
        'rgba(255,82,82,0.85)',   // rojo
        'rgba(156,39,176,0.85)',  // violeta
        'rgba(255,152,0,0.85)',   // naranja
        'rgba(0,188,212,0.85)',   // cyan
        'rgba(233,30,99,0.85)',   // rosa
        'rgba(121,85,72,0.85)',   // marrón
        'rgba(96,125,139,0.85)',  // gris azulado
    ];

    $labels  = [];
    $data    = [];
    $colores = [];

    foreach ($ranking as $i => $row) {
        $labels[]  = $row['nombre_vend'];
        $data[]    = (float)$row['total_usd'];
        $colores[] = $palette[$i % count($palette)];
    }

    return ['labels' => $labels, 'data' => $data, 'colores' => $colores];
}

$grafico = prepararDatosGrafico($ranking);
?>

<!-- ============================================================
     ESTILOS ESPECÍFICOS DE TELEVENTAS (modal + overrides locales)
     Los estilos globales se heredan de style.css vía header.php
     ============================================================ -->
<!-- Los estilos locales han sido movidos a style.css y estandarizados -->


<!-- ============================================================
     CONTENIDO PRINCIPAL
     ============================================================ -->
<main class="main-content">
    <?php include("../../includes/navbar.php"); ?>
<div class="content-wrapper">

    <!-- CABECERA DE PÁGINA -->
    <div class="page-title">
        <h1><i class="fas fa-headset"></i> Monitor de Televentas</h1>
        <p>Ranking de vendedores &bull; Documentos <code>F</code> &bull;
           <strong><?php echo date('d/m/Y', strtotime($f_ini)); ?></strong>
           &mdash;
           <strong><?php echo date('d/m/Y', strtotime($f_fin)); ?></strong>
        </p>
    </div>

    <!-- ====================================================
         KPI CARDS
         ==================================================== -->
    <div class="metrics-grid">
        <!-- Total Ventas USD -->
        <div class="metric-card success">
            <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-content">
                <div class="metric-label">Total Ventas (USD)</div>
                <div class="metric-value">$ <?php echo number_format($total_usd, 2, '.', ','); ?></div>
            </div>
        </div>

        <!-- Total Ventas BS -->
        <div class="metric-card">
            <div class="metric-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="metric-content">
                <div class="metric-label">Total Ventas (BS)</div>
                <div class="metric-value">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></div>
            </div>
        </div>

        <!-- Pedidos Cerrados -->
        <div class="metric-card warning">
            <div class="metric-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="metric-content">
                <div class="metric-label">Pedidos Registrados</div>
                <div class="metric-value"><?php echo number_format($total_pedidos, 0, ',', '.'); ?></div>
            </div>
        </div>

        <!-- Promedio USD por Vendedor -->
        <div class="metric-card">
            <div class="metric-icon"><i class="fas fa-user-tie"></i></div>
            <div class="metric-content">
                <div class="metric-label">Prom. USD / Vendedor</div>
                <div class="metric-value">$ <?php echo number_format($prom_usd, 2, '.', ','); ?></div>
            </div>
        </div>
    </div>

    <!-- ====================================================
         FILTROS
         ==================================================== -->
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
            <div class="filter-group grow">
                <label>Buscar Vendedor / Cliente / N° Pedido</label>
                <div class="sw">
                    <input type="text" name="f_txt"
                           value="<?php echo htmlspecialchars($f_txt); ?>"
                           placeholder="Ej: María González, FC00012345…">
                    <button type="submit" class="btn-neon">
                        <i class="fas fa-magnifying-glass"></i> BUSCAR
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ====================================================
         SECCIÓN GRÁFICA + RANKING
         ==================================================== -->
    <div class="card table-card" style="margin-top:25px;">
        <div class="t-header">
            <h3><i class="fas fa-chart-pie"></i> Participación de Ventas por Vendedor</h3>
            <button class="btn-csv" onclick="exportXls('table-ranking','Ranking_Televentas')">
                <i class="fas fa-file-excel"></i> DESCARGAR XLS
            </button>
        </div>

        <!-- Gráfico de Torta (Chart.js) -->
        <div style="padding:20px 25px 10px;">
            <?php if (empty($ranking)): ?>
                <p style="opacity:0.5; text-align:center; padding:40px 0;">
                    No hay datos en el rango seleccionado.
                </p>
            <?php else: ?>
            <div class="chart-wrapper">
                <!-- Canvas del Pie Chart -->
                <div class="chart-canvas-wrap">
                    <canvas id="pieVentas"></canvas>
                </div>
                <!-- Leyenda personalizada generada desde JS -->
                <div class="chart-legend" id="pieLeyenda"></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabla de Ranking por Vendedor -->
        <div class="table-container" style="margin-top:10px;">
            <table id="table-ranking">
                <thead>
                    <tr>
                        <th class="c">#</th>
                        <th>VENDEDOR</th>
                        <th class="c">PEDIDOS</th>
                        <th class="r">TOTAL (BS)</th>
                        <th class="r">TOTAL (USD)</th>
                        <th>PARTICIPACIÓN</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($ranking)): ?>
                    <tr><td colspan="6" class="text-center" style="padding:48px; opacity:0.5;">Sin registros.</td></tr>
                <?php else:
                    // Calcular máximo USD para barra de progreso
                    $max_usd = max(array_column($ranking, 'total_usd')) ?: 1;
                    foreach ($ranking as $i => $v):
                        $pct    = ($total_usd > 0) ? round(($v['total_usd'] / $total_usd) * 100, 1) : 0;
                        $barPct = ($v['total_usd'] / $max_usd) * 100;
                        $rank   = $i + 1;
                        $rankClass = match($rank) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
                        // Color sincronizado con el gráfico (mismo orden de paleta)
                        $palette = [
                            'rgba(0,180,255,0.85)', 'rgba(0,230,118,0.85)',
                            'rgba(255,193,7,0.85)', 'rgba(255,82,82,0.85)',
                            'rgba(156,39,176,0.85)', 'rgba(255,152,0,0.85)',
                            'rgba(0,188,212,0.85)', 'rgba(233,30,99,0.85)',
                            'rgba(121,85,72,0.85)', 'rgba(96,125,139,0.85)',
                        ];
                        $color = $palette[$i % count($palette)];
                ?>
                    <tr class="clickable" onclick="abrirModalVendedor(
                            '<?php echo htmlspecialchars($v['codvend']); ?>',
                            '<?php echo htmlspecialchars(addslashes($v['nombre_vend'])); ?>'
                        )">
                        <td class="c"><span class="rank-num <?php echo $rankClass; ?>"><?php echo $rank; ?></span></td>
                        <td>
                            <div style="font-weight:700; color:var(--text-main);"><?php echo htmlspecialchars($v['nombre_vend']); ?></div>
                            <div style="font-size:0.72rem; color:var(--text-muted);">Cód. <?php echo htmlspecialchars($v['codvend']); ?></div>
                        </td>
                        <td class="c"><strong><?php echo $v['pedidos']; ?></strong></td>
                        <td class="r" style="font-weight:700;">Bs. <?php echo number_format($v['total_bs'], 2, ',', '.'); ?></td>
                        <td class="r" style="color:var(--accent-green); font-weight:700;">$ <?php echo number_format($v['total_usd'], 2, '.', ','); ?></td>
                        <td style="min-width:150px;">
                            <div style="font-size:0.78rem; font-weight:700; color:var(--text-muted); margin-bottom:5px;"><?php echo $pct; ?>%</div>
                            <div class="progress-bar-wrap">
                                <div class="progress-bar-fill"
                                     style="width:<?php echo $barPct; ?>%; background:<?php echo $color; ?>;"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right" style="opacity:0.7;">
                            <i class="fas fa-sigma"></i> TOTAL PERIODO (<?php echo count($ranking); ?> vendedores)
                        </td>
                        <td class="c" style="font-weight:800;"><?php echo $total_pedidos; ?></td>
                        <td class="r" style="font-weight:800;">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></td>
                        <td class="r" style="font-weight:800; color:var(--accent-green);">$ <?php echo number_format($total_usd, 2, '.', ','); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ====================================================
         DETALLE DE PEDIDOS (Tabla paginada)
         ==================================================== -->
    <div class="card table-card" style="margin-top:28px;">
        <div class="t-header">
            <h3><i class="fas fa-list-ul"></i> Detalle de Ventas (F)</h3>
            <div class="rng">
                Mostrando del <?php echo $offset + 1; ?> al <?php echo min($offset + $limit, $total_rows); ?>
                de <strong><?php echo $total_rows; ?></strong>
                <button class="btn-csv"
                        onclick="exportXls('table-detalle','Detalle_Televentas')"
                        style="margin-left:15px;">
                    <i class="fas fa-file-excel"></i> DESCARGAR XLS
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="table-detalle">
                <thead>
                    <tr>
                        <th>N° PEDIDO</th>
                        <th>FECHA</th>
                        <th>CLIENTE</th>
                        <th>VENDEDOR</th>
                        <th class="r">TOTAL (BS)</th>
                        <th class="r">TOTAL (USD)</th>
                        <th class="c">TASA</th>
                        <th class="c">ESTATUS</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding:48px; opacity:0.5;">
                            No se encontraron pedidos bajo los filtros actuales.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pedidos as $r): ?>
                    <tr class="clickable"
                        onclick="abrirModalVendedor('<?php echo htmlspecialchars($r['codvend']); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($r['nombre_vend'])); ?>')">
                        <td><span class="code-badge"><?php echo htmlspecialchars($r['pedido']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($r['fecha_ped'])); ?></td>
                        <td class="product-name">
                            <div style="font-weight:700; color:var(--text-main);"><?php echo htmlspecialchars($r['cliente']); ?></div>
                            <div style="font-size:0.72rem; color:var(--text-muted);"><?php echo htmlspecialchars($r['cod_cli']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($r['nombre_vend']); ?></div>
                            <div style="font-size:0.72rem; color:var(--text-muted);">Cód. <?php echo htmlspecialchars($r['codvend']); ?></div>
                        </td>
                        <td class="r" style="font-weight:700;">Bs. <?php echo number_format($r['total_bs'], 2, ',', '.'); ?></td>
                        <td class="r" style="color:var(--accent-green); font-weight:700;">$ <?php echo number_format($r['total_usd'], 2, '.', ','); ?></td>
                        <td class="c" style="color:var(--text-muted); font-size:0.82rem;"><?php echo number_format($r['tasa'], 2, ',', '.'); ?></td>
                        <td class="c"><?php echo renderEstatusPed($r['estatus'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php
                    $page_bs  = array_sum(array_column($pedidos, 'total_bs'));
                    $page_usd = array_sum(array_column($pedidos, 'total_usd'));
                    ?>
                    <tr>
                        <td colspan="4" class="text-right" style="opacity:0.7;">
                            <i class="fas fa-sigma"></i> SUBTOTAL PÁGINA (<?php echo count($pedidos); ?> pedidos)
                        </td>
                        <td class="r" style="font-weight:800;">Bs. <?php echo number_format($page_bs, 2, ',', '.'); ?></td>
                        <td class="r" style="font-weight:800; color:var(--accent-green);">$ <?php echo number_format($page_usd, 2, '.', ','); ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- PAGINACIÓN (mismo patrón que cobranzas) -->
        <div class="pagination-wrapper">
            <div class="page-info-bubble">
                Página <strong><?php echo $page; ?></strong> de <strong><?php echo $total_pages; ?></strong>
            </div>

            <div class="pager-container">
                <div class="pager-group">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                           class="pager-btn" title="Primero"><i class="fas fa-angles-left"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                           class="pager-btn" title="Anterior"><i class="fas fa-angle-left"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angles-left"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angle-left"></i></button>
                    <?php endif; ?>

                    <span class="pcur" style="margin:0 15px; font-weight:700; color:var(--primary);"><?php echo $page; ?></span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                           class="pager-btn" title="Siguiente"><i class="fas fa-angle-right"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                           class="pager-btn" title="Último"><i class="fas fa-angles-right"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angle-right"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angles-right"></i></button>
                    <?php endif; ?>
                </div>
            </div>

            <form method="GET" class="page-input-group">
                <?php foreach ($_GET as $k => $v) if ($k !== 'page') echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">'; ?>
                <span style="opacity:0.6;">Ir a</span>
                <input type="number" name="page" class="page-input"
                       min="1" max="<?php echo $total_pages; ?>"
                       value="<?php echo $page; ?>">
                <button type="submit" class="pager-btn" style="background:rgba(255,255,255,0.05);">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Tip inferior -->
    <div class="click-tip">
        <i class="fas fa-info-circle"></i>
        Tip: Haz clic en un vendedor o pedido para ver el desglose completo de sus pedidos del período.
    </div>

</div><!-- /.content-wrapper -->
</main><!-- /.main-content -->

<!-- ============================================================
     MODAL DETALLE VENDEDOR
     Mismo patrón que vista_cobranzas.php
     ============================================================ -->
<div class="modal-overlay" id="modalOverlay" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-hd">
            <div>
                <h3 id="modalTitulo"><i class="fas fa-user-tie"></i> Detalle de Vendedor</h3>
                <div class="mref" id="modalRef">Cargando…</div>
            </div>
            <button class="modal-close" onclick="cerrarModal()" title="Cerrar">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="modal-loading">
                <div class="spinner"></div>
                <span>Recuperando pedidos del servidor…</span>
            </div>
        </div>
        <div class="modal-ft">
            <button class="btn-cerrar" onclick="cerrarModal()">
                <i class="fas fa-times"></i> CERRAR VENTANA
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<!-- SheetJS para exportar XLS (mismo CDN que cobranzas) -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<!-- Chart.js para el gráfico de torta -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
// ============================================================
// DATOS DEL SERVIDOR → JS (generados en PHP, escapados)
// ============================================================
const chartLabels  = <?php echo json_encode($grafico['labels'],  JSON_UNESCAPED_UNICODE); ?>;
const chartData    = <?php echo json_encode($grafico['data']); ?>;
const chartColores = <?php echo json_encode($grafico['colores']); ?>;

// Rango de fechas activo (para pasar al AJAX de detalle)
const F_INI = '<?php echo $f_ini; ?>';
const F_FIN = '<?php echo $f_fin; ?>';

// ============================================================
// EXPORTAR EXCEL (mismo patrón que cobranzas)
// ============================================================
function exportXls(tableId, name) {
    const t = document.getElementById(tableId);
    if (!t) return;
    const wb = XLSX.utils.table_to_book(t, { sheet: name });
    XLSX.writeFile(wb, name + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
}

// ============================================================
// GRÁFICO DE TORTA (Chart.js)
// ============================================================
(function initPieChart() {
    const canvas = document.getElementById('pieVentas');
    if (!canvas || chartData.length === 0) return;

    const totalUSD = chartData.reduce((a, b) => a + b, 0);

    // Formatear moneda USD para tooltips
    const fmtUSD = (n) => '$ ' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(n);
    const fmtPct = (n) => (totalUSD > 0 ? ((n / totalUSD) * 100).toFixed(1) : 0) + '%';

    const pie = new Chart(canvas, {
        type: 'doughnut', // Doughnut es más elegante; igual muestra participación
        data: {
            labels: chartLabels,
            datasets: [{
                data:            chartData,
                backgroundColor: chartColores,
                borderColor:     'rgba(0,0,0,0.3)',
                borderWidth:     2,
                hoverOffset:     12,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: { display: false }, // Usamos leyenda personalizada
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const val = ctx.parsed;
                            return `  ${ctx.label}: ${fmtUSD(val)} (${fmtPct(val)})`;
                        }
                    },
                    backgroundColor: 'rgba(15,20,30,0.95)',
                    titleColor: '#fff',
                    bodyColor: 'rgba(255,255,255,0.85)',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 14,
                    cornerRadius: 8,
                }
            },
            animation: { animateRotate: true, duration: 800 }
        }
    });

    // -------------------------------------------------------
    // Leyenda personalizada a la derecha del gráfico
    // -------------------------------------------------------
    const legend = document.getElementById('pieLeyenda');
    if (!legend) return;

    chartLabels.forEach((label, i) => {
        const val  = chartData[i];
        const item = document.createElement('div');
        item.className = 'legend-item';
        item.title = `Ver detalle de ${label}`;
        item.innerHTML = `
            <span class="legend-dot" style="background:${chartColores[i]};"></span>
            <span class="legend-name">${label}</span>
            <span class="legend-val">${fmtUSD(val)}</span>
        `;
        // Al hacer clic en la leyenda → resaltar sector en el gráfico
        item.addEventListener('click', () => {
            const meta = pie.getDatasetMeta(0);
            // Toggle visibilidad del segmento
            if (pie.getDataVisibility(i)) {
                pie.hide(0, i);
            } else {
                pie.show(0, i);
            }
        });
        legend.appendChild(item);
    });
})();

// ============================================================
// MODAL DETALLE VENDEDOR (patrón abrirModal de cobranzas)
// ============================================================
const modal    = document.getElementById('modalOverlay');
const modalRef = document.getElementById('modalRef');
const modalBody = document.getElementById('modalBody');

// Formateo de helpers JS
const fmtCur = (n, c = 'Bs.') =>
    c + ' ' + new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2 }).format(n);
const fmtUSD2 = (n) =>
    '$ ' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(n);
const fmtFec = (d) => d ? d.split('-').reverse().join('/') : '—';

/**
 * Abre el modal con los pedidos del vendedor seleccionado.
 * Se llama tanto desde la tabla de ranking como desde la tabla de detalle.
 */
async function abrirModalVendedor(codVend, nombreVend) {
    modal.classList.add('open');
    modalRef.innerText = `Cargando pedidos de ${nombreVend}…`;
    modalBody.innerHTML = `
        <div class="modal-loading">
            <div class="spinner"></div>
            <span>Recuperando pedidos del servidor…</span>
        </div>`;

    try {
        const url = `?ajax=detalle_vendedor&cod_vend=${encodeURIComponent(codVend)}&f_ini=${F_INI}&f_fin=${F_FIN}`;
        const resp = await fetch(url);
        const data = await resp.json();

        if (data.error) throw new Error(data.error);
        renderModalVendedor(data);
    } catch (err) {
        modalRef.innerText = 'Error';
        modalBody.innerHTML = `
            <div class="modal-no-ped">
                <i class="fas fa-triangle-exclamation"></i><br>
                No se pudo cargar el detalle.<br>
                <small>${err.message}</small>
            </div>`;
    }
}

/** Renderiza el contenido del modal una vez recibida la respuesta AJAX. */
function renderModalVendedor(data) {
    const v = data.vendedor || {};
    const p = data.pedidos  || [];

    // KPIs del vendedor
    const totBS  = p.reduce((a, r) => a + parseFloat(r.total_bs),  0);
    const totUSD = p.reduce((a, r) => a + parseFloat(r.total_usd), 0);

    // Actualizar cabecera
    modalRef.innerHTML = `
        <strong>${v.nombre_vend || ('Vendedor ' + (v.codvend || ''))}</strong>
        &bull; Cód. ${v.codvend || '—'}
        &bull; ${v.correo ? `<a href="mailto:${v.correo}" style="color:var(--primary);">${v.correo}</a>` : ''}
        &bull; ${v.telefono || ''}`;

    // Estado de estatus para JS
    const estBadge = (s) => {
        s = (s || '').toUpperCase().trim();
        if (s === 'C') return '<span class="badge badge-ok"><i class="fas fa-check-circle"></i> CERRADO</span>';
        if (s === 'P') return '<span class="badge badge-low"><i class="fas fa-clock"></i> PENDIENTE</span>';
        if (s === 'X') return '<span class="badge badge-critical"><i class="fas fa-times-circle"></i> ANULADO</span>';
        return `<span class="badge" style="background:rgba(255,255,255,0.05);color:var(--text-muted);border:1px solid var(--border);">${s}</span>`;
    };

    let html = `
        <!-- KPIs resumen del vendedor -->
        <div class="modal-info-grid">
            <div class="mic">
                <span class="lbl">Total Vendido (BS)</span>
                <span class="val gr">${fmtCur(totBS)}</span>
            </div>
            <div class="mic">
                <span class="lbl">Total Vendido (USD)</span>
                <span class="val bl">${fmtUSD2(totUSD)}</span>
            </div>
            <div class="mic">
                <span class="lbl">Pedidos en período</span>
                <span class="val">${p.length}</span>
            </div>
            <div class="mic">
                <span class="lbl">Teléfono</span>
                <span class="val">${v.telefono || '—'}</span>
            </div>
        </div>

        <div class="modal-sec-ttl"><i class="fas fa-file-invoice"></i> Pedidos del Período</div>
    `;

    if (p.length === 0) {
        html += '<div class="modal-no-ped"><i class="fas fa-info-circle"></i> No se encontraron pedidos en este período.</div>';
    } else {
        html += `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>N° PEDIDO</th>
                        <th>FECHA</th>
                        <th>CLIENTE</th>
                        <th class="r">TOTAL (BS)</th>
                        <th class="r">TOTAL (USD)</th>
                        <th class="c">ESTATUS</th>
                    </tr>
                </thead>
                <tbody>`;

        p.forEach(r => {
            html += `
            <tr>
                <td><span class="code-badge">${r.pedido}</span></td>
                <td>${fmtFec(r.fecha_ped)}</td>
                <td>
                    <div style="font-weight:700;">${r.cliente}</div>
                    <div style="font-size:0.72rem; color:var(--text-muted);">${r.cod_cli}</div>
                </td>
                <td class="r" style="font-weight:700;">${fmtCur(r.total_bs)}</td>
                <td class="r" style="color:var(--accent-green); font-weight:700;">${fmtUSD2(r.total_usd)}</td>
                <td class="c">${estBadge(r.estatus)}</td>
            </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">TOTAL:</td>
                        <td class="r" style="font-weight:800;">${fmtCur(totBS)}</td>
                        <td class="r" style="font-weight:800; color:var(--accent-green);">${fmtUSD2(totUSD)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>`;
    }

    modalBody.innerHTML = html;
}

/** Cierra el modal de detalle. */
function cerrarModal() {
    modal.classList.remove('open');
}

// Cerrar con tecla ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

// Cerrar al hacer clic fuera del contenido del modal
modal.addEventListener('click', (e) => {
    if (e.target === modal) cerrarModal();
});
</script>

<?php include('../../includes/footer.php'); ?>
</body>
</html>