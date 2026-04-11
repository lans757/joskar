<?php
/**
 * ============================================================
 * MONITOR DE TELEVENTAS - NOTIPRO / ProteoERP
 * Vista de ranking, gráfico de participación y detalle AJAX
 * Basado en el patrón de vista_cobranzas.php
 * ============================================================
 */

require_once('../includes/db.php');

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
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');

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
<style>
/* ---------- Modal (mismo patrón que cobranzas) ---------- */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.72);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 1100px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow);
    animation: mIn 0.3s cubic-bezier(0.4,0,0.2,1);
}
@keyframes mIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:none; } }
.modal-hd {
    padding: 24px 30px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    background: rgba(255,255,255,0.02);
}
.modal-hd h3 { font-size:1.25rem; font-weight:800; margin:0; color:var(--primary); }
.modal-hd .mref { font-size:0.85rem; color:var(--text-muted); margin-top:5px; font-weight:600; }
.modal-close {
    background: rgba(255,255,255,0.05);
    border: none;
    color: var(--text-main);
    width: 36px; height: 36px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex; align-items: center; justify-content: center;
    transition: var(--transition);
}
.modal-close:hover { background: var(--accent-red); color:#fff; }
.modal-body { overflow-y:auto; padding:25px 30px; flex:1; }
.modal-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px,1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.mic {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 12px 18px;
}
.mic .lbl { font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; margin-bottom:6px; }
.mic .val { font-size:1.1rem; font-weight:700; color:var(--text-main); font-family:'Outfit',sans-serif; }
.mic .val.gr { color:var(--accent-green); }
.mic .val.bl { color:var(--primary); }
.modal-sec-ttl {
    font-size:0.9rem; font-weight:800; text-transform:uppercase; color:var(--primary);
    margin:30px 0 15px;
    display:flex; align-items:center; gap:10px;
    padding-bottom:10px; border-bottom:1px solid var(--border-light);
}
.modal-ft { border-top:1px solid var(--border-light); padding:15px 30px; display:flex; justify-content:flex-end; background:rgba(255,255,255,0.01); }
.modal-no-ped { text-align:center; padding:40px; opacity:0.5; font-size:0.95rem; }
.btn-cerrar {
    background: var(--bg-input); color:var(--text-main);
    border:1px solid var(--border); padding:10px 24px;
    border-radius:var(--radius-sm); font-weight:700; cursor:pointer; transition:var(--transition);
}
.btn-cerrar:hover { background:var(--border); }

/* ---------- Gráfico de torta (contenedor) ---------- */
.chart-wrapper {
    display: flex;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
    padding: 10px 5px 5px;
}
.chart-canvas-wrap {
    flex: 0 0 auto;
    width: 300px;
    height: 300px;
    position: relative;
}
/* Leyenda personalizada derecha del gráfico */
.chart-legend {
    flex: 1 1 200px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
    padding-right: 5px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.82rem;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    transition: background 0.2s;
}
.legend-item:hover { background: rgba(255,255,255,0.05); }
.legend-dot {
    width: 12px; height: 12px; border-radius: 50%; flex-shrink:0;
}
.legend-name { flex:1; color:var(--text-main); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.legend-val  { color:var(--accent-green); font-weight:700; white-space:nowrap; }

/* Botón "Ver pedidos" dentro de cada ítem de leyenda de la dona de usuarios */
.btn-ver-usr {
    background: rgba(0,180,255,0.1);
    border: 1px solid rgba(0,180,255,0.25);
    color: var(--primary);
    width: 24px; height: 24px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 0.65rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-left: 6px;
    transition: var(--transition);
}
.btn-ver-usr:hover { background: rgba(0,180,255,0.3); }

/* ---------- Tabla y utilidades ---------- */
th.c, td.c { text-align:center !important; }
th.r, td.r { text-align:right  !important; }
.click-tip { font-size:0.85rem; color:var(--text-muted); padding:10px 0; display:flex; align-items:center; gap:8px; font-weight:500; }
tbody tr.clickable:hover { cursor:pointer; background:rgba(0,180,255,0.1) !important; }
.table-container { border-radius:0 0 var(--radius-lg) var(--radius-lg); overflow-x:auto; }
#table-ranking thead th,
#table-detalle thead th { position:sticky; top:0; z-index:10; background:var(--bg-card); box-shadow:inset 0 -2px 0 var(--border); }
.rank-num { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; background:rgba(255,255,255,0.07); font-weight:800; font-size:0.78rem; color:var(--text-muted); }
.rank-num.gold   { background:rgba(255,193,7,0.2);  color:#ffc107; }
.rank-num.silver { background:rgba(192,192,192,0.2); color:#c0c0c0; }
.rank-num.bronze { background:rgba(205,127,50,0.2);  color:#cd7f32; }

/* Barra de progreso en tabla ranking */
.progress-bar-wrap { position:relative; background:rgba(255,255,255,0.06); border-radius:4px; overflow:hidden; height:6px; min-width:80px; }
.progress-bar-fill { height:100%; border-radius:4px; transition:width 0.6s ease; }

/* ---------- Tabs selector de dona ---------- */
.btn-dona-tab {
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 7px 16px;
    border-radius: var(--radius-sm);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.btn-dona-tab:hover { background: rgba(255,255,255,0.08); color: var(--text-main); }
.btn-dona-tab.active {
    background: rgba(0,180,255,0.15);
    border-color: rgba(0,180,255,0.4);
    color: var(--primary);
}
</style>

<!-- ============================================================
     CONTENIDO PRINCIPAL
     ============================================================ -->
<main class="main-content">
<div class="content-wrapper">

    <!-- CABECERA DE PÁGINA -->
    <div class="page-title">
        <h1><i class="fas fa-headset"></i> Monitor de Televentas (Pedidos)</h1>
        <p>Ranking de vendedores &bull; Pedidos <code>PFAC</code> &bull;
           <strong><?php echo date('d/m/Y', strtotime($f_ini)); ?></strong>
           &mdash;
           <strong><?php echo date('d/m/Y', strtotime($f_fin)); ?></strong>
           <?php if (!empty($f_usuario)): ?>
               &bull; <span style="background:rgba(0,180,255,0.15); color:var(--primary); border:1px solid rgba(0,180,255,0.3); border-radius:20px; padding:2px 10px; font-size:0.82rem; font-weight:700;">
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
            <!-- *** FILTRO POR USUARIO QUE CARGÓ EL PEDIDO ***
                 Puebla el <select> con los valores distintos de sfac.usuario.
                 Al seleccionar uno, TODAS las queries (KPIs, ranking, gráfico,
                 detalle paginado) se restringen a ese operador.
                 Seleccionar "TODOS" limpia el filtro y restaura la vista global. -->
            <div class="filter-group">
                <label><i class="fas fa-user-shield" style="margin-right:5px;"></i>Usuario / Operador</label>
                <select name="f_usuario">
                    <option value="">— TODOS LOS USUARIOS —</option>
                    <?php foreach ($lista_usuarios as $usr): ?>
                    <option value="<?php echo htmlspecialchars($usr); ?>"
                            <?php echo ($f_usuario === $usr) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($usr); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group grow">
                <label>Buscar Vendedor / Cliente / N° Pedido</label>
                <div class="sw">
                    <input type="text" name="f_txt"
                           value="<?php echo htmlspecialchars($f_txt); ?>"
                           placeholder="Ej: María González, FC00012345…">
                    <button type="submit" class="btn-neon btn-cyan">
                        <i class="fas fa-sync-alt"></i> ACTUALIZAR
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
            <button class="btn-neon btn-green" onclick="exportXls('table-ranking','Ranking_Televentas')" style="height:38px; font-size:0.75rem; padding:0 15px;">
                <i class="fas fa-file-excel"></i> Exportar XLS
            </button>
        </div>

        <!-- Gráfico de Torta (Chart.js) -->
        <div style="padding:20px 25px 10px;">
            <?php if (empty($ranking)): ?>
                <p style="opacity:0.5; text-align:center; padding:40px 0;">
                    No hay datos en el rango seleccionado.
                </p>
            <?php else: ?>

            <!-- ============================================================
                 SELECTOR DE MODO DE GRÁFICO
                 "Por Vendedor" = dona original (DONA_VENDEDORES_v1)
                 "Por Usuario"  = dona nueva que agrupa por f.usuario
                 ============================================================ -->
            <div style="display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap;">
                <button id="btn-dona-vend"
                        onclick="switchDona('vendedor')"
                        class="btn-dona-tab active"
                        title="Participación agrupada por vendedor">
                    <i class="fas fa-user-tie"></i> Por Vendedor
                </button>
                <button id="btn-dona-usr"
                        onclick="switchDona('usuario')"
                        class="btn-dona-tab"
                        title="Participación agrupada por usuario que cargó el pedido">
                    <i class="fas fa-user-shield"></i> Por Usuario
                    <?php if (!empty($f_usuario)): ?>
                        <span style="background:rgba(0,180,255,0.3); border-radius:10px; padding:1px 7px; font-size:0.72rem; margin-left:4px;">
                            <?php echo htmlspecialchars($f_usuario); ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- -------------------------------------------------------
                 DONA_VENDEDORES_v1
                 Dona original: participación de ventas por vendedor.
                 Se mantiene intacta para reutilización futura.
                 Para desactivarla permanentemente: añadir display:none al wrapper
                 o eliminar el bloque completo.
                 ------------------------------------------------------- -->
            <div id="wrap-dona-vendedor" class="chart-wrapper">
                <div class="chart-canvas-wrap">
                    <canvas id="pieVentas"></canvas>
                </div>
                <div class="chart-legend" id="pieLeyenda"></div>
            </div>

            <!-- -------------------------------------------------------
                 DONA_USUARIOS_v1
                 Dona nueva: participación de ventas agrupada por el campo
                 f.usuario (operador que registró el pedido en sfac).
                 Los datos se generan en PHP ($grafico_usr) y se pasan a JS.
                 Si hay un f_usuario activo, esta dona muestra solo los
                 vendedores de ese operador (hereda el mismo $where).
                 ------------------------------------------------------- -->
            <div id="wrap-dona-usuario" class="chart-wrapper" style="display:none;">
                <div class="chart-canvas-wrap">
                    <canvas id="pieUsuarios"></canvas>
                </div>
                <div class="chart-legend" id="pieLeyendaUsr"></div>
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
            <h3><i class="fas fa-list-ul"></i> Detalle de Pedidos (PFAC)</h3>
            <div class="rng">
                Mostrando del <?php echo $offset + 1; ?> al <?php echo min($offset + $limit, $total_rows); ?>
                de <strong><?php echo $total_rows; ?></strong>
                <button class="btn-neon btn-green" 
                        onclick="exportXls('table-detalle','Detalle_Televentas')"
                        style="margin-left:15px; height:38px; font-size:0.75rem; padding:0 15px;">
                    <i class="fas fa-file-excel"></i> Exportar XLS
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
                        <th>USUARIO</th>
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
                        <!-- Celda usuario que cargó el pedido (campo sfac.usuario) -->
                        <td>
                            <span class="code-badge" style="background:rgba(0,180,255,0.08); color:var(--primary); border-color:rgba(0,180,255,0.2);">
                                <i class="fas fa-user-shield" style="font-size:0.65rem; margin-right:3px;"></i> <?php echo htmlspecialchars($r['usuario_cargo'] ?? '—'); ?>
                            </span>
                        </td>
                        <td class="r" style="font-weight:700;">Bs. <?php echo number_format($r['total_bs'], 2, ',', '.'); ?></td>
                        <td class="r" style="color:var(--accent-green); font-weight:700;">$ <?php echo number_format($r['total_usd'], 2, '.', ','); ?></td>
                        <td class="c" style="color:var(--text-muted); font-size:0.82rem;"><?php echo number_format($r['dolar'], 2, ',', '.'); ?></td>
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
                        <td colspan="5" class="text-right" style="opacity:0.7;">
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
            // Si el clic fue sobre el dot o el nombre → toggle gráfico
            // Si quiere abrir modal → doble clic o botón dedicado (ver abajo)
            pieUsr.getDataVisibility(i) ? pieUsr.hide(0, i) : pieUsr.show(0, i);
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
        wrapVend.style.display = 'none';
        wrapUsr.style.display  = 'flex';
        btnVend.classList.remove('active');
        btnUsr.classList.add('active');
    } else {
        wrapUsr.style.display  = 'none';
        wrapVend.style.display = 'flex';
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
        `<i class="fas fa-user-shield" style="color:var(--primary); margin-right:6px;"></i>
         <strong>Operador: ${codUsr}</strong>
         &bull; ${fmtFec(F_INI.replaceAll('-','-'))} — ${fmtFec(F_FIN.replaceAll('-','-'))}`;

    const estBadge = (s) => {
        s = (s || '').toUpperCase().trim();
        if (s === 'C') return '<span class="badge badge-ok"><i class="fas fa-check-circle"></i> CERRADO</span>';
        if (s === 'P') return '<span class="badge badge-low"><i class="fas fa-clock"></i> PENDIENTE</span>';
        if (s === 'X') return '<span class="badge badge-critical"><i class="fas fa-times-circle"></i> ANULADO</span>';
        return `<span class="badge" style="background:rgba(255,255,255,0.05);color:var(--text-muted);border:1px solid var(--border);">${s}</span>`;
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
                <span class="val" style="font-size:0.95rem;">${codUsr}</span>
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
                    <div style="font-weight:700;">${r.cliente}</div>
                    <div style="font-size:0.72rem; color:var(--text-muted);">${r.cod_cli}</div>
                </td>
                <td>
                    <div style="font-weight:600;">${r.nombre_vend ?? '—'}</div>
                    <div style="font-size:0.72rem; color:var(--text-muted);">Cód. ${r.codvend ?? '—'}</div>
                </td>
                <td class="r" style="font-weight:700;">${fmtCur(r.total_bs)}</td>
                <td class="r" style="color:var(--accent-green); font-weight:700;">${fmtUSD2(r.total_usd)}</td>
                <td class="c">${estBadge(r.estatus)}</td>
            </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right">TOTAL (${p.length} pedidos):</td>
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

// Cerrar con tecla ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

// Cerrar al hacer clic fuera del contenido del modal
modal.addEventListener('click', (e) => {
    if (e.target === modal) cerrarModal();
});
</script>

<?php include('../includes/footer.php'); ?>
</body>
</html>
