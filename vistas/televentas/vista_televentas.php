<?php
require_once __DIR__ . '/../../includes/auth.php';
require_module_access('TELEVENTAS');
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
                    f.dolar,
                    f.usuario   AS usuario_cargo,
                    CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END AS total_usd
             FROM pfac f
             WHERE f.vd        = :cod
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

// --- Manejo AJAX: Detalle de pedidos por USUARIO que cargó el pedido ---
// Endpoint: ?ajax=detalle_usuario&cod_usr=NOMBREUSR&f_ini=YYYY-MM-DD&f_fin=YYYY-MM-DD
// Devuelve los pedidos registrados por ese usuario con sus vendedores asociados.
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalle_usuario') {
    $cod_usr = $_GET['cod_usr'] ?? '';
    $f_ini   = $_GET['f_ini']   ?? date('Y-m-01');
    $f_fin   = $_GET['f_fin']   ?? date('Y-m-d');

    header('Content-Type: application/json');
    try {
        // KPIs rápidos del usuario en el período
        $stmt_kpi_usr = $pdo->prepare(
            "SELECT COUNT(DISTINCT f.numero)                                              AS total_pedidos,
                    SUM(f.totalg)                                                         AS total_bs,
                    SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) AS total_usd
             FROM pfac f
             WHERE f.usuario  = :usr
               AND f.fecha   >= :ini
               AND f.fecha   <= :fin"
        );
        $stmt_kpi_usr->execute([':usr' => $cod_usr, ':ini' => $f_ini, ':fin' => $f_fin]);
        $kpi_usr = $stmt_kpi_usr->fetch(PDO::FETCH_ASSOC);

        // Pedidos del usuario, incluyendo el nombre del vendedor asignado
        $stmt_ped_usr = $pdo->prepare(
            "SELECT f.numero    AS pedido,
                    f.fecha     AS fecha_ped,
                    f.cod_cli,
                    f.nombre    AS cliente,
                    f.status    AS estatus,
                    f.totalg    AS total_bs,
                    f.dolar,
                    f.usuario   AS usuario_cargo,
                    CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END AS total_usd,
                    f.vd        AS codvend,
                    COALESCE(NULLIF(v.nombre,''), CONCAT('Vend. ', f.vd))       AS nombre_vend
             FROM pfac f
             LEFT JOIN vend v ON v.vendedor = f.vd
             WHERE f.usuario  = :usr
               AND f.fecha   >= :ini
               AND f.fecha   <= :fin
             ORDER BY f.fecha DESC, f.numero DESC
             LIMIT 300"
        );
        $stmt_ped_usr->execute([':usr' => $cod_usr, ':ini' => $f_ini, ':fin' => $f_fin]);
        $pedidos_usr = $stmt_ped_usr->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'usuario'  => $cod_usr,
            'kpis'     => $kpi_usr,
            'pedidos'  => $pedidos_usr,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'AJAX_ERR_USR: ' . $e->getMessage()]);
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
$f_ini      = $_GET['f_ini']      ?? date('Y-m-01');
$f_fin      = $_GET['f_fin']      ?? date('Y-m-d');
$f_txt      = $_GET['f_txt']      ?? '';   // Buscar por nombre vendedor o cliente
$f_usuario  = $_GET['f_usuario']  ?? '';   // Filtro por usuario que cargó el pedido (campo f.usuario en sfac)

// --- Paginación para tabla de detalle completo ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$offset = ($page - 1) * $limit;

// ============================================================
// QUERIES
// ============================================================
try {
    // --- Listado de usuarios para el <select> del filtro ---
    // Obtiene todos los valores distintos del campo `usuario` registrados en sfac
    // para el tipo de documento 'PEDFTP'. Se usa en el <select> del formulario.
    $stmt_usuarios = $pdo->query(
        "SELECT DISTINCT f.usuario
         FROM pfac f
         WHERE f.usuario IS NOT NULL
           AND f.usuario <> ''
         ORDER BY f.usuario ASC"
    );
    $lista_usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_COLUMN); // array plano de strings

    // --- Parámetros base ---
    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    $where  = "WHERE f.fecha >= :ini AND f.fecha <= :fin";

    // Filtro por usuario que cargó el pedido (campo f.usuario)
    // Cuando está activo restringe TODAS las queries: KPIs, ranking, gráfico y detalle
    if (!empty($f_usuario)) {
        $where .= " AND f.usuario = :usuario";
        $params[':usuario'] = $f_usuario;
    }

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
             SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) AS total_usd,
             COUNT(DISTINCT f.vd)                                       AS total_vendedores
         FROM pfac f
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
             SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) AS total_usd
         FROM pfac f
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
         FROM pfac f
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
             f.dolar,
             f.totalg                                                               AS total_bs,
             CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END     AS total_usd,
             f.usuario                                                              AS usuario_cargo
         FROM pfac f
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
 * Statuses comunes en PEDFTP: 'C' = Cerrado, 'P' = Pendiente, 'X' = Anulado
 */
function renderEstatusPed(string $e): string {
    $e = strtoupper(trim($e));
    return match($e) {
        'C' => "<span class='badge badge-ok'><i class='fas fa-check-circle'></i> CERRADO</span>",
        'P' => "<span class='badge badge-low'><i class='fas fa-clock'></i> PENDIENTE</span>",
        'X' => "<span class='badge badge-critical'><i class='fas fa-times-circle'></i> ANULADO</span>",
        default => "<span class='badge badge badge-neutral'>$e</span>",
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

// ============================================================
// DATOS PARA LA DONA DE USUARIOS (DONA_USUARIOS_v1)
// Agrupa las ventas del período (con los mismos filtros activos)
// por el campo sfac.usuario. Reutiliza $where y $params que ya
// incluyen el filtro f_usuario si está presente.
// ============================================================
try {
    $stmt_usr = $pdo->prepare(
        "SELECT
             COALESCE(NULLIF(f.usuario,''), '(sin usuario)')     AS nombre_usr,
             COUNT(DISTINCT f.numero)                             AS pedidos,
             SUM(f.totalg)                                        AS total_bs,
             SUM(CASE WHEN f.dolar > 0 THEN ROUND(f.totalg / f.dolar, 2) ELSE 0 END) AS total_usd
         FROM pfac f
         LEFT JOIN vend v ON v.vendedor = f.vd
         $where
         GROUP BY f.usuario
         ORDER BY total_usd DESC"
    );
    $stmt_usr->execute($params);
    $ranking_usr = $stmt_usr->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ranking_usr = []; // Si falla no rompe la página
}

// Reutiliza la misma función prepararDatosGrafico: los labels serán
// los nombres de usuario en lugar de nombres de vendedor.
$grafico_usr = prepararDatosGrafico(
    array_map(fn($r) => array_merge($r, ['nombre_vend' => $r['nombre_usr']]), $ranking_usr)
);
?>

<!-- ============================================================
     ESTILOS ESPECÍFICOS DE TELEVENTAS (modal + overrides locales)
     Los estilos globales se heredan de style.css vía header.php
     ============================================================ -->


<!-- ============================================================
     CONTENIDO PRINCIPAL
     ============================================================ -->
<main class="main-content">
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="televentas_articulos.php" class="nav-item">
            <i class="fas fa-list"></i>
            <span>Artículos Vendidos</span>
        </a>
        <a href="televentas_top.php" class="nav-item">
            <i class="fas fa-trophy"></i>
            <span>Top Vendidos</span>
        </a>
        <a href="televentas_kpis.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Indicadores KPI</span>
        </a>
    </nav>

    <!-- CABECERA DE PÁGINA -->
    <div class="page-title">
        <h1><i class="fas fa-headset"></i> Monitor de Televentas (Pedidos)</h1>
        <p>Ranking de vendedores &bull; Pedidos <code>PFAC</code> &bull;
           <strong><?php echo date('d/m/Y', strtotime($f_ini)); ?></strong>
           &mdash;
           <strong><?php echo date('d/m/Y', strtotime($f_fin)); ?></strong>
           <?php if (!empty($f_usuario)): ?>
               &bull; <span class="user-filter-badge">
                   <i class="fas fa-user-shield"></i> Usuario: <?php echo htmlspecialchars($f_usuario); ?>
               </span>
           <?php endif; ?>
        </p>
    </div>

    <!-- ====================================================
         KPI CARDS
         ==================================================== -->
    <div class="metrics-grid">
        <!-- Total Ventas USD -->
        <div class="card metric-card success">
            <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-content">
                <span class="metric-label">Total Ventas (USD)</span>
                <p class="metric-value">$ <?php echo number_format($total_usd, 2, '.', ','); ?></p>
            </div>
        </div>

        <!-- Total Ventas BS -->
        <div class="card metric-card primary">
            <div class="metric-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="metric-content">
                <span class="metric-label">Total Ventas (BS)</span>
                <p class="metric-value">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Pedidos Cerrados -->
        <div class="card metric-card warning">
            <div class="metric-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="metric-content">
                <span class="metric-label">Pedidos Registrados</span>
                <p class="metric-value"><?php echo number_format($total_pedidos, 0, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Promedio USD por Vendedor -->
        <div class="card metric-card info">
            <div class="metric-icon"><i class="fas fa-user-tie"></i></div>
            <div class="metric-content">
                <span class="metric-label">Prom. USD / Vendedor</span>
                <p class="metric-value">$ <?php echo number_format($prom_usd, 2, '.', ','); ?></p>
            </div>
        </div>
    </div>

    <!-- ====================================================
         CABECERA + FILTRO COMPACTO
         ==================================================== -->
    <div class="tv-header-row">
        <div class="tv-title-block">
            <div class="tv-module-tag"><i class="fas fa-headset"></i> Televentas</div>
            <h1>Monitor de Ventas</h1>
            <p class="tv-period">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y', strtotime($f_ini)); ?> &mdash; <?php echo date('d/m/Y', strtotime($f_fin)); ?>
                <?php if (!empty($f_usuario)): ?>
                    <span class="user-filter-badge"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($f_usuario); ?></span>
                <?php endif; ?>
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
            <div class="tv-input-wrap">
                <label><i class="fas fa-user-shield icon-mr"></i>Operador</label>
                <select name="f_usuario">
                    <option value="">— Todos —</option>
                    <?php foreach ($lista_usuarios as $usr): ?>
                    <option value="<?php echo htmlspecialchars($usr); ?>" <?php echo ($f_usuario === $usr) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($usr); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="tv-btn-apply">
                <i class="fas fa-sync-alt"></i> Aplicar
            </button>
        </form>
    </div>

    <!-- ====================================================
         HERO KPI — VENTAS USD (elemento visual dominante)
         ==================================================== -->
    <div class="tv-hero-kpi">
        <div class="tv-hero-glow"></div>
        <div class="tv-hero-icon"><i class="fas fa-dollar-sign"></i></div>
        <div class="tv-hero-content">
            <span class="tv-hero-label">Total Ventas del Período</span>
            <div class="tv-hero-value">
                $ <?php echo number_format($total_usd, 2, '.', ','); ?>
                <span class="tv-hero-currency">USD</span>
            </div>
            <div class="tv-hero-bs">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></div>
        </div>
        <div class="tv-hero-trend">
            <div class="tv-trend-item">
                <i class="fas fa-clipboard-list"></i>
                <span class="tv-trend-val"><?php echo number_format($total_pedidos, 0); ?></span>
                <span class="tv-trend-lbl">Pedidos</span>
            </div>
            <div class="tv-trend-sep"></div>
            <div class="tv-trend-item">
                <i class="fas fa-users"></i>
                <span class="tv-trend-val"><?php echo $total_vendedores; ?></span>
                <span class="tv-trend-lbl">Vendedores</span>
            </div>
            <div class="tv-trend-sep"></div>
            <div class="tv-trend-item">
                <i class="fas fa-chart-line"></i>
                <span class="tv-trend-val">$ <?php echo number_format($prom_usd, 0, '.', ','); ?></span>
                <span class="tv-trend-lbl">Prom/Vend.</span>
            </div>
        </div>
    </div>

    <!-- ====================================================
         PANEL DE ANÁLISIS: GRÁFICO + RANKING
         ==================================================== -->
    <div class="tv-analysis-grid">

        <!-- Dona de participación -->
        <div class="card tv-chart-card">
            <div class="tv-card-hd">
                <span class="tv-card-title"><i class="fas fa-chart-pie"></i> Participación</span>
                <div class="tv-dona-tabs">
                    <button id="btn-dona-vend" onclick="switchDona('vendedor')" class="btn-dona-tab active">
                        <i class="fas fa-user-tie"></i> Vendedor
                    </button>
                    <button id="btn-dona-usr" onclick="switchDona('usuario')" class="btn-dona-tab">
                        <i class="fas fa-user-shield"></i> Usuario
                    </button>
                </div>
            </div>
            <?php if (empty($ranking)): ?>
                <p class="empty-data-msg">No hay datos en el rango seleccionado.</p>
            <?php else: ?>
            <div id="wrap-dona-vendedor" class="chart-wrapper">
                <div class="chart-canvas-wrap"><canvas id="pieVentas"></canvas></div>
                <div class="chart-legend" id="pieLeyenda"></div>
            </div>
            <div id="wrap-dona-usuario" class="chart-wrapper chart-hidden">
                <div class="chart-canvas-wrap"><canvas id="pieUsuarios"></canvas></div>
                <div class="chart-legend" id="pieLeyendaUsr"></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Ranking de vendedores -->
        <div class="card tv-ranking-card">
            <div class="tv-card-hd">
                <span class="tv-card-title"><i class="fas fa-medal"></i> Ranking de Vendedores</span>
                <button class="btn-neon btn-green btn-sm-export" onclick="exportXls('table-ranking','Ranking_Televentas')">
                    <i class="fas fa-file-excel"></i> XLS
                </button>
            </div>
            <div class="table-container">
                <table id="table-ranking">
                    <thead>
                        <tr>
                            <th class="c">#</th>
                            <th>VENDEDOR</th>
                            <th class="c">PEDIDOS</th>
                            <th class="r">TOTAL USD</th>
                            <th>PART.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ranking)): ?>
                        <tr><td colspan="5" class="text-center empty-table-cell">Sin registros.</td></tr>
                    <?php else:
                        $max_usd = max(array_column($ranking, 'total_usd')) ?: 1;
                        foreach ($ranking as $i => $v):
                            $pct      = ($total_usd > 0) ? round(($v['total_usd'] / $total_usd) * 100, 1) : 0;
                            $barPct   = ($v['total_usd'] / $max_usd) * 100;
                            $rank     = $i + 1;
                            $rankClass = match($rank) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
                            $palette  = ['rgba(0,180,255,0.85)','rgba(0,230,118,0.85)','rgba(255,193,7,0.85)','rgba(255,82,82,0.85)','rgba(156,39,176,0.85)','rgba(255,152,0,0.85)','rgba(0,188,212,0.85)','rgba(233,30,99,0.85)','rgba(121,85,72,0.85)','rgba(96,125,139,0.85)'];
                            $color    = $palette[$i % count($palette)];
                    ?>
                        <tr class="clickable" onclick="abrirModalVendedor('<?php echo htmlspecialchars($v['codvend']); ?>','<?php echo htmlspecialchars(addslashes($v['nombre_vend'])); ?>')">
                            <td class="c"><span class="rank-num <?php echo $rankClass; ?>"><?php echo $rank; ?></span></td>
                            <td>
                                <div class="text-main-bold"><?php echo htmlspecialchars($v['nombre_vend']); ?></div>
                                <div class="text-muted-sm">Cód. <?php echo htmlspecialchars($v['codvend']); ?></div>
                            </td>
                            <td class="c"><strong><?php echo $v['pedidos']; ?></strong></td>
                            <td class="r amount-usd">$ <?php echo number_format($v['total_usd'], 2, '.', ','); ?></td>
                            <td class="min-w-150">
                                <div class="progress-pct"><?php echo $pct; ?>%</div>
                                <div class="progress-bar-wrap">
                                    <div class="progress-bar-fill" style="width:<?php echo $barPct; ?>%; background:<?php echo $color; ?>;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-right opacity-70"><i class="fas fa-sigma"></i> TOTAL (<?php echo count($ranking); ?> vendedores)</td>
                            <td class="c total-amount"><?php echo $total_pedidos; ?></td>
                            <td class="r total-usd">$ <?php echo number_format($total_usd, 2, '.', ','); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="click-tip">
                <i class="fas fa-info-circle"></i>
                Haz clic en un vendedor para ver el desglose completo de sus pedidos.
            </div>
        </div>

    </div><!-- /.tv-analysis-grid -->

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

// --- Datos DONA_USUARIOS_v1: participación por usuario que cargó el pedido ---
const usrLabels  = <?php echo json_encode($grafico_usr['labels'],  JSON_UNESCAPED_UNICODE); ?>;
const usrData    = <?php echo json_encode($grafico_usr['data']); ?>;
const usrColores = <?php echo json_encode($grafico_usr['colores']); ?>;

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
        // Al hacer clic en la leyenda → togglear visibilidad del segmento
        item.addEventListener('click', () => {
            pie.toggleDataVisibility(i);
            pie.update();
            item.classList.toggle('hidden');
        });
        legend.appendChild(item);
    });
})();

// ============================================================
// DONA_USUARIOS_v1 — Participación por usuario que cargó el pedido
// Inicializa el gráfico de la dona de usuarios. Reutiliza la misma
// lógica de tooltips, leyenda y toggle que la dona de vendedores.
// ============================================================
(function initDonaUsuarios() {
    const canvas = document.getElementById('pieUsuarios');
    if (!canvas || usrData.length === 0) return;

    const totalU = usrData.reduce((a, b) => a + b, 0);
    const fmtU   = (n) => '$ ' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(n);
    const fmtP   = (n) => (totalU > 0 ? ((n / totalU) * 100).toFixed(1) : 0) + '%';

    const pieUsr = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: usrLabels,
            datasets: [{
                data:            usrData,
                backgroundColor: usrColores,
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
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `  ${ctx.label}: ${fmtU(ctx.parsed)} (${fmtP(ctx.parsed)})`
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

    // Leyenda personalizada de la dona de usuarios
    const legendUsr = document.getElementById('pieLeyendaUsr');
    if (!legendUsr) return;

    usrLabels.forEach((label, i) => {
        const val  = usrData[i];
        const item = document.createElement('div');
        item.className = 'legend-item';
        item.title = `Ver pedidos de ${label}`;
        item.innerHTML = `
            <span class="legend-dot" style="background:${usrColores[i]};"></span>
            <span class="legend-name">${label}</span>
            <span class="legend-val">${fmtU(val)}</span>
        `;
        // Clic en segmento de leyenda: toggle visual en el gráfico
        item.addEventListener('click', (e) => {
            pieUsr.toggleDataVisibility(i);
            pieUsr.update();
            item.classList.toggle('hidden');
        });
        // Botón dedicado "Ver pedidos" a la derecha de cada ítem de leyenda
        const btnVer = document.createElement('button');
        btnVer.className  = 'btn-ver-usr';
        btnVer.title      = `Abrir detalle de pedidos de ${label}`;
        btnVer.innerHTML  = '<i class="fas fa-external-link-alt"></i>';
        btnVer.addEventListener('click', (e) => {
            e.stopPropagation(); // No propagar al toggle del gráfico
            abrirModalUsuario(label);
        });
        item.appendChild(btnVer);
        legendUsr.appendChild(item);
    });

    // Clic directo en un segmento del gráfico → abrir modal del usuario
    canvas.addEventListener('click', (e) => {
        const points = pieUsr.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        if (points.length > 0) {
            const idx    = points[0].index;
            const label  = usrLabels[idx];
            if (label && label !== '(sin usuario)') {
                abrirModalUsuario(label);
            }
        }
    });
})();

// ============================================================
// SWITCH ENTRE DONA DE VENDEDORES Y DONA DE USUARIOS
// Alterna la visibilidad de los dos wrappers y actualiza el
// estilo activo de los botones tab sin recargar la página.
// ============================================================
function switchDona(modo) {
    const wrapVend = document.getElementById('wrap-dona-vendedor');
    const wrapUsr  = document.getElementById('wrap-dona-usuario');
    const btnVend  = document.getElementById('btn-dona-vend');
    const btnUsr   = document.getElementById('btn-dona-usr');

    if (modo === 'usuario') {
        wrapVend.classList.add('chart-hidden');
        wrapUsr.classList.remove('chart-hidden');
        btnVend.classList.remove('active');
        btnUsr.classList.add('active');
    } else {
        wrapUsr.classList.add('chart-hidden');
        wrapVend.classList.remove('chart-hidden');
        btnUsr.classList.remove('active');
        btnVend.classList.add('active');
    }
}

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
        &bull; ${v.correo ? `<a href="mailto:${v.correo}" class="text-primary">${v.correo}</a>` : ''}
        &bull; ${v.telefono || ''}`;

    // Estado de estatus para JS
    const estBadge = (s) => {
        s = (s || '').toUpperCase().trim();
        if (s === 'C') return '<span class="badge badge-ok"><i class="fas fa-check-circle"></i> CERRADO</span>';
        if (s === 'P') return '<span class="badge badge-low"><i class="fas fa-clock"></i> PENDIENTE</span>';
        if (s === 'X') return '<span class="badge badge-critical"><i class="fas fa-times-circle"></i> ANULADO</span>';
        return `<span class="badge badge-neutral">${s}</span>`;
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
                    <div class="fw-700">${r.cliente}</div>
                    <div class="text-muted-sm">${r.cod_cli}</div>
                </td>
                <td class="r fw-700">${fmtCur(r.total_bs)}</td>
                <td class="r amount-usd">${fmtUSD2(r.total_usd)}</td>
                <td class="c">${estBadge(r.estatus)}</td>
            </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">TOTAL:</td>
                        <td class="r total-amount">${fmtCur(totBS)}</td>
                        <td class="r total-usd">${fmtUSD2(totUSD)}</td>
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

// ============================================================
// MODAL DETALLE USUARIO (DONA_USUARIOS_v1)
// Abre el modal mostrando los pedidos registrados por ese
// operador (f.usuario) en el período activo.
// El endpoint AJAX es: ?ajax=detalle_usuario&cod_usr=X&f_ini=Y&f_fin=Z
// ============================================================

/**
 * Abre el modal con los pedidos del usuario/operador seleccionado.
 * Se invoca desde:
 *   - Botón "Ver pedidos" en la leyenda de la dona de usuarios
 *   - Clic directo en un segmento del gráfico de usuarios
 */
async function abrirModalUsuario(codUsr) {
    modal.classList.add('open');
    document.getElementById('modalRef').innerText = `Cargando pedidos del usuario "${codUsr}"…`;
    modalBody.innerHTML = `
        <div class="modal-loading">
            <div class="spinner"></div>
            <span>Recuperando pedidos del operador…</span>
        </div>`;

    try {
        const url  = `?ajax=detalle_usuario&cod_usr=${encodeURIComponent(codUsr)}&f_ini=${F_INI}&f_fin=${F_FIN}`;
        const resp = await fetch(url);
        const data = await resp.json();

        if (data.error) throw new Error(data.error);
        renderModalUsuario(codUsr, data);
    } catch (err) {
        document.getElementById('modalRef').innerText = 'Error';
        modalBody.innerHTML = `
            <div class="modal-no-ped">
                <i class="fas fa-triangle-exclamation"></i><br>
                No se pudo cargar el detalle del usuario.<br>
                <small>${err.message}</small>
            </div>`;
    }
}

/**
 * Renderiza el contenido del modal para un usuario/operador.
 * Muestra KPIs globales del usuario y la tabla de pedidos con
 * la columna de vendedor asignado en cada documento.
 */
function renderModalUsuario(codUsr, data) {
    const k = data.kpis   || {};
    const p = data.pedidos || [];

    const totBS  = parseFloat(k.total_bs  ?? 0);
    const totUSD = parseFloat(k.total_usd ?? 0);
    const totPed = parseInt(k.total_pedidos ?? 0);

    // Actualizar cabecera del modal
    document.getElementById('modalRef').innerHTML =
        `<i class="fas fa-user-shield icon-primary-mr"></i>
         <strong>Operador: ${codUsr}</strong>
         &bull; ${fmtFec(F_INI.replaceAll('-','-'))} — ${fmtFec(F_FIN.replaceAll('-','-'))}`;

    const estBadge = (s) => {
        s = (s || '').toUpperCase().trim();
        if (s === 'C') return '<span class="badge badge-ok"><i class="fas fa-check-circle"></i> CERRADO</span>';
        if (s === 'P') return '<span class="badge badge-low"><i class="fas fa-clock"></i> PENDIENTE</span>';
        if (s === 'X') return '<span class="badge badge-critical"><i class="fas fa-times-circle"></i> ANULADO</span>';
        return `<span class="badge badge-neutral">${s}</span>`;
    };

    let html = `
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
                <span class="lbl">Pedidos registrados</span>
                <span class="val">${totPed}</span>
            </div>
            <div class="mic">
                <span class="lbl">Usuario / Operador</span>
                <span class="val fs-095">${codUsr}</span>
            </div>
        </div>
        <div class="modal-sec-ttl">
            <i class="fas fa-file-invoice"></i> Pedidos del Período — registrados por este operador
        </div>`;

    if (p.length === 0) {
        html += '<div class="modal-no-ped"><i class="fas fa-info-circle"></i> No se encontraron pedidos para este operador en el período.</div>';
    } else {
        html += `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>N° PEDIDO</th>
                        <th>FECHA</th>
                        <th>CLIENTE</th>
                        <th>VENDEDOR ASIGNADO</th>
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
                    <div class="fw-700">${r.cliente}</div>
                    <div class="text-muted-sm">${r.cod_cli}</div>
                </td>
                <td>
                    <div class="fw-600">${r.nombre_vend ?? '—'}</div>
                    <div class="text-muted-sm">Cód. ${r.codvend ?? '—'}</div>
                </td>
                <td class="r fw-700">${fmtCur(r.total_bs)}</td>
                <td class="r amount-usd">${fmtUSD2(r.total_usd)}</td>
                <td class="c">${estBadge(r.estatus)}</td>
            </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right">TOTAL (${p.length} pedidos):</td>
                        <td class="r total-amount">${fmtCur(totBS)}</td>
                        <td class="r total-usd">${fmtUSD2(totUSD)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>`;
    }

    modalBody.innerHTML = html;
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
