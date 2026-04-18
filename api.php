<?php
session_start();
header('Content-Type: application/json');

include 'includes/lan_check.php';
include 'includes/db.php';

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$action  = $_GET['action']  ?? 'alertas';
$limit   = min(max((int)($_GET['limit']  ?? 50), 1), 500);
$offset  = max((int)($_GET['offset'] ?? 0), 0);
$search  = $_GET['search']  ?? '';
$alerta  = $_GET['alerta']  ?? 'all';
$almacen = $_GET['almacen'] ?? '0001';
$codprov = $_GET['codprov'] ?? '';
$marca   = $_GET['marca']   ?? '';

if (empty($almacen)) $almacen = '0001';

$sort_f = $_GET['sort_field'] ?? '';
$sort_d = ($_GET['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

$vdp_expr  = "(SELECT IFNULL(SUM(cana), 0) FROM sitems WHERE codigoa = b.codigo AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))";
$dias_expr = "(i.existen / ($vdp_expr / 30 + 0.0001))";

function alertaCond($alerta, $dias_expr) {
    switch ($alerta) {
        case 'critical': return "$dias_expr < 10";
        case 'low':      return "$dias_expr BETWEEN 10 AND 30";
        case 'ok':       return "$dias_expr > 30";
        case 'out':      return "i.existen <= 0";
        default:         return "1=1";
    }
}

function getCounts($pdo, $almacen, $dias_expr, $codprov = '', $marca = '') {
    $counts = ['critical' => 0, 'low' => 0, 'ok' => 0, 'totalH1' => 0, 'valorUSD' => 0, 'out' => 0];

    $base = "FROM sinv b JOIN itsinv i ON b.codigo = i.codigo WHERE i.alma = ?";
    $p    = [$almacen];
    if ($codprov !== '') { $base .= " AND b.prvreg = ?"; $p[] = $codprov; }
    if ($marca   !== '') { $base .= " AND b.marca = ?";  $p[] = $marca; }

    $s = $pdo->prepare("SELECT COUNT(*) $base AND i.existen >= 1");
    $s->execute($p); $counts['totalH1'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) $base AND i.existen >= 1 AND $dias_expr < 10");
    $s->execute($p); $counts['critical'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) $base AND i.existen > 0 AND $dias_expr BETWEEN 10 AND 30");
    $s->execute($p); $counts['low'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) $base AND i.existen > 0 AND $dias_expr > 30");
    $s->execute($p); $counts['ok'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) $base AND i.existen <= 0");
    $s->execute($p); $counts['out'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT SUM(i.existen * b.pondd) $base AND i.existen > 0");
    $s->execute($p); $counts['valorUSD'] = (float)($s->fetchColumn() ?? 0);

    return $counts;
}

$metrics = ['critical' => 0, 'low' => 0, 'ok' => 0, 'totalH1' => 0, 'valorUSD' => 0];

if ($action === 'me') {
    if (empty($_SESSION['logged_in'])) {
        echo json_encode(['logged_in' => false]);
    } else {
        echo json_encode([
            'logged_in'     => true,
            'user_id'       => $_SESSION['user_id'],
            'user_name'     => $_SESSION['user_name'],
            'is_supervisor' => $_SESSION['is_supervisor'] ?? false,
        ]);
    }
    exit;
}

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

try {
    try {
        $metrics = getCounts($pdo, $almacen, $dias_expr, $codprov, $marca);
    } catch (Exception $e) {
        // Proceed with zeroed metrics if inventory tables are missing
    }

    if ($action === 'alertas' || $action === 'alerts') {

        $allowed_sort = ['codigo', 'descrip', 'existen', 'diasinv', 'min', 'ventau'];
        $order_by = in_array($sort_f, $allowed_sort) ? "$sort_f $sort_d" : "i.existen ASC, diasinv ASC";

        $alerta_cond = alertaCond($alerta, $dias_expr);
        $exist_filter = ($alerta === 'out') ? "i.existen <= 0" : "i.existen >= 1";

        $conds  = ["i.alma = ?", $exist_filter, $alerta_cond];
        $params = [$almacen];

        if ($search !== '') {
            $conds[]  = "(b.codigo LIKE ? OR b.descrip LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($codprov !== '') { $conds[] = "b.prvreg = ?"; $params[] = $codprov; }
        if ($marca   !== '') { $conds[] = "b.marca = ?";  $params[] = $marca; }

        $where = "WHERE " . implode(" AND ", $conds);

        $s = $pdo->prepare("SELECT COUNT(*) FROM sinv b JOIN itsinv i ON b.codigo = i.codigo $where");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $sql = "
            SELECT b.codigo, b.descrip, i.existen,
                   b.exmin AS min, b.exmax AS max,
                   p.nombre AS proveedor,
                   $vdp_expr AS ventau,
                   $dias_expr AS diasinv
            FROM sinv b
            JOIN itsinv i ON b.codigo = i.codigo
            LEFT JOIN sprv p ON b.prvreg = p.proveed
            $where
            ORDER BY $order_by
            LIMIT $limit OFFSET $offset
        ";
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $items = [];
        foreach ($s->fetchAll() as $row) {
            $row['existen'] = (float)$row['existen'];
            $row['min']     = (float)$row['min'];
            $row['max']     = (float)$row['max'];
            $row['ventau']  = (float)$row['ventau'];
            $row['diasinv'] = (float)$row['diasinv'];
            $items[] = $row;
        }

        echo json_encode(['data' => $items, 'total' => $total, 'metrics' => $metrics]);

    } elseif ($action === 'movimientos' || $action === 'movements') {

        $allowed_sort = ['grupo', 'codigo', 'descrip', 'ventau', 'existen', 'diasinv'];
        $order_by = in_array($sort_f, $allowed_sort) ? "$sort_f $sort_d" : "ventau DESC";

        $conds  = ["a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", "i.alma = ?", "i.existen >= 1"];
        $params = [$almacen];

        if ($search !== '') {
            $conds[]  = "(b.codigo LIKE ? OR b.descrip LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($codprov !== '') { $conds[] = "b.prvreg = ?"; $params[] = $codprov; }

        if ($alerta !== 'all') {
            $conds[] = alertaCond($alerta, $dias_expr);
        }

        $where = "WHERE " . implode(" AND ", $conds);

        $s = $pdo->prepare("
            SELECT COUNT(DISTINCT b.codigo) FROM sitems a
            JOIN sinv b ON a.codigoa = b.codigo
            JOIN itsinv i ON b.codigo = i.codigo
            $where
        ");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $sql = "
            SELECT b.grupo, b.codigo, b.descrip,
                   p.nombre AS proveedor,
                   SUM(a.cana) AS ventau,
                   i.existen,
                   (i.existen / (SUM(a.cana) / 30 + 0.0001)) AS diasinv,
                   b.pfecha1 AS ucompra
            FROM sitems a
            JOIN sinv b ON a.codigoa = b.codigo
            JOIN itsinv i ON b.codigo = i.codigo
            LEFT JOIN sprv p ON b.prvreg = p.proveed
            $where
            GROUP BY b.codigo, b.grupo, b.descrip, i.existen, b.pfecha1, p.nombre
            ORDER BY $order_by
            LIMIT $limit OFFSET $offset
        ";
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $items = [];
        foreach ($s->fetchAll() as $row) {
            $row['existen'] = (float)$row['existen'];
            $row['ventau']  = (float)$row['ventau'];
            $row['diasinv'] = (float)$row['diasinv'];
            $items[] = $row;
        }

        echo json_encode(['data' => $items, 'total' => $total, 'metrics' => $metrics]);

    } else {
        echo json_encode(['error' => 'Acción inválida']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
    error_log('api.php error: ' . $e->getMessage());
}
?>
