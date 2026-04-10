<?php
session_start();
header('Content-Type: application/json');


include('includes/db.php');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

try {
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);
    if ($conn->connect_error) {
        throw new Exception("Conexión Fallida: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die(json_encode(["error" => $e->getMessage()]));
}

$action  = $_GET['action']     ?? 'alertas';
$limit   = isset($_GET['limit'])  ? (int)$_GET['limit']  : 50;
$offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$search  = $conn->real_escape_string($_GET['search']  ?? '');
$alerta  = $_GET['alerta']  ?? 'all';
$almacen = $_GET['almacen'] ?? '0001';

$sort_f = $conn->real_escape_string($_GET['sort_field'] ?? '');
$sort_d = ($_GET['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

// Almacén por defecto
if (empty($almacen)) $almacen = '0001';

$codprov   = $conn->real_escape_string($_GET['codprov'] ?? '');
$prov_cond = !empty($codprov) ? "AND b.prvreg = '$codprov'" : "";

// ─── Búsqueda ────────────────────────────────────────────────────────────────
$search_cond = !empty($search)
    ? "AND (b.codigo LIKE '%$search%' OR b.descrip LIKE '%$search%')"
    : "";

// ─── Subquery de ventas 30 días (reutilizable) ────────────────────────────────
// diasinv = existencia / (ventas30d / 30)
// Si ventas = 0 → diasinv = 9999 (producto sin movimiento = stock "infinito")
$vdp_expr   = "(SELECT IFNULL(SUM(cana), 0) FROM sitems WHERE codigoa = b.codigo AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))";
$dias_expr  = "(i.existen / ($vdp_expr / 30 + 0.0001))";

// ─── Condición de estado (para filtro $alerta) ────────────────────────────────
// CRÍTICO  : existen = 0  ó  diasinv < 10
// ATENCIÓN : diasinv BETWEEN 10 AND 30
// ÓPTIMO   : diasinv > 30
function alertaCond($alerta, $dias_expr) {
    switch ($alerta) {
        case 'critical': return "$dias_expr < 10";
        case 'low':      return "$dias_expr BETWEEN 10 AND 30";
        case 'ok':       return "$dias_expr > 30";
        default:         return "1=1"; // all
    }
}

// ─── Métricas ─────────────────────────────────────────────────────────────────
function getCounts($conn, $almacen, $dias_expr, $prov_cond) {
    $counts = ['critical' => 0, 'low' => 0, 'ok' => 0, 'totalH1' => 0, 'valorUSD' => 0];

    // Total de productos con stock en el almacén
    $res = $conn->query("
        SELECT COUNT(*) as total
        FROM sinv b
        JOIN itsinv i ON b.codigo = i.codigo
        WHERE i.alma = '$almacen'
          AND i.existen >= 1
          $prov_cond
    ");
    if ($res) $counts['totalH1'] = (int)$res->fetch_assoc()['total'];

    // CRÍTICO: con stock pero menos de 10 días
    $res = $conn->query("
        SELECT COUNT(*) as total
        FROM sinv b
        JOIN itsinv i ON b.codigo = i.codigo
        WHERE i.alma = '$almacen'
          AND i.existen >= 1
          AND $dias_expr < 10
          $prov_cond
    ");
    if ($res) $counts['critical'] = (int)$res->fetch_assoc()['total'];

    // BAJO MÍNIMO (ATENCIÓN): entre 10 y 30 días
    $res = $conn->query("
        SELECT COUNT(*) as total
        FROM sinv b
        JOIN itsinv i ON b.codigo = i.codigo
        WHERE i.alma = '$almacen'
          AND i.existen > 0
          AND $dias_expr BETWEEN 10 AND 30
          $prov_cond
    ");
    if ($res) $counts['low'] = (int)$res->fetch_assoc()['total'];

    // ÓPTIMO: más de 30 días de stock
    $res = $conn->query("
        SELECT COUNT(*) as total
        FROM sinv b
        JOIN itsinv i ON b.codigo = i.codigo
        WHERE i.alma = '$almacen'
          AND i.existen > 0
          AND $dias_expr > 30
          $prov_cond
    ");
    if ($res) $counts['ok'] = (int)$res->fetch_assoc()['total'];

    // VALOR USD total del almacén
    $res = $conn->query("
        SELECT SUM(i.existen * b.pondd) as total
        FROM sinv b
        JOIN itsinv i ON b.codigo = i.codigo
        WHERE i.alma = '$almacen' AND i.existen > 0 $prov_cond
    ");
    if ($res) $counts['valorUSD'] = (float)$res->fetch_assoc()['total'];

    return $counts;
}

// ─── Router ───────────────────────────────────────────────────────────────────
try {
    $metrics = getCounts($conn, $almacen, $dias_expr, $prov_cond);

    // ── ALERTAS DE STOCK ──────────────────────────────────────────────────────
    if ($action === 'alertas' || $action === 'alerts') {

        $allowed_sort = ['codigo', 'descrip', 'existen', 'diasinv', 'min', 'ventau'];
        $order_by = in_array($sort_f, $allowed_sort) ? "$sort_f $sort_d" : "i.existen ASC, diasinv ASC";

        $alerta_cond = alertaCond($alerta, $dias_expr);

        $where = "
            WHERE i.alma = '$almacen'
              AND i.existen >= 1
              AND $alerta_cond
              $search_cond
              $prov_cond
        ";

        // Total para paginación
        $total_res = $conn->query("
            SELECT COUNT(*) as total
            FROM sinv b
            JOIN itsinv i ON b.codigo = i.codigo
            $where
        ");
        if (!$total_res) throw new Exception("Count error: " . $conn->error);
        $total = (int)$total_res->fetch_assoc()['total'];

        // Datos paginados
        $sql = "
            SELECT
                b.codigo,
                b.descrip,
                i.existen,
                b.exmin  AS min,
                b.exmax  AS max,
                $vdp_expr AS ventau,
                $dias_expr AS diasinv
            FROM sinv b
            JOIN itsinv i ON b.codigo = i.codigo
            $where
            ORDER BY $order_by
            LIMIT $limit OFFSET $offset
        ";

        $result = $conn->query($sql);
        if (!$result) throw new Exception("Query error: " . $conn->error);

        $items = [];
        while ($row = $result->fetch_assoc()) {
            // Asegurar tipos numéricos para el JS
            $row['existen'] = (float)$row['existen'];
            $row['min']     = (float)$row['min'];
            $row['max']     = (float)$row['max'];
            $row['ventau']  = (float)$row['ventau'];
            $row['diasinv'] = (float)$row['diasinv'];
            $items[] = $row;
        }

        echo json_encode(['data' => $items, 'total' => $total, 'metrics' => $metrics]);

    // ── ROTACIÓN COMERCIAL ────────────────────────────────────────────────────
    } elseif ($action === 'movimientos' || $action === 'movements') {

        $allowed_sort = ['grupo', 'codigo', 'descrip', 'ventau', 'existen', 'diasinv'];
        $order_by = in_array($sort_f, $allowed_sort) ? "$sort_f $sort_d" : "ventau DESC";

        // Rotación: solo productos con ventas en los últimos 30 días y stock >= 1
        $where = "
            WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND i.alma = '$almacen'
              AND i.existen >= 1
              $search_cond
              $prov_cond
        ";

        // Aplicar filtro de alerta también en movimientos
        if ($alerta !== 'all') {
            $alerta_cond = alertaCond($alerta, $dias_expr);
            $where .= " AND $alerta_cond";
        }

        $total_res = $conn->query("
            SELECT COUNT(DISTINCT b.codigo) as total
            FROM sitems a
            JOIN sinv b ON a.codigoa = b.codigo
            JOIN itsinv i ON b.codigo = i.codigo
            $where
        ");
        if (!$total_res) throw new Exception("Count error: " . $conn->error);
        $total = (int)$total_res->fetch_assoc()['total'];

        $sql = "
            SELECT
                b.grupo,
                b.codigo,
                b.descrip,
                SUM(a.cana) AS ventau,
                i.existen,
                (i.existen / (SUM(a.cana) / 30 + 0.0001)) AS diasinv,
                b.pfecha1 AS ucompra
            FROM sitems a
            JOIN sinv b ON a.codigoa = b.codigo
            JOIN itsinv i ON b.codigo = i.codigo
            $where
            GROUP BY b.codigo, b.grupo, b.descrip, i.existen, b.pfecha1
            ORDER BY $order_by
            LIMIT $limit OFFSET $offset
        ";

        $result = $conn->query($sql);
        if (!$result) throw new Exception("Query error: " . $conn->error);

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $row['existen'] = (float)$row['existen'];
            $row['ventau']  = (float)$row['ventau'];
            $row['diasinv'] = (float)$row['diasinv'];
            $items[] = $row;
        }

        echo json_encode(['data' => $items, 'total' => $total, 'metrics' => $metrics]);

    // ── SESIÓN ────────────────────────────────────────────────────────────────
    } elseif ($action === 'me') {
        if (empty($_SESSION['logged_in'])) {
            echo json_encode(['logged_in' => false]);
        } else {
            echo json_encode([
                'logged_in'     => true,
                'user_id'       => $_SESSION['user_id'],
                'user_name'     => $_SESSION['user_name'],
                'is_supervisor' => $_SESSION['is_supervisor'] ?? false
            ]);
        }

    } else {
        echo json_encode(['error' => 'Acción inválida: ' . $action]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
