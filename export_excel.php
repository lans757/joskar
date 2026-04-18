<?php
session_start();

if (empty($_SESSION['logged_in'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=Reporte_Inventario_' . date('Y-m-d') . '.xls');

include 'includes/db.php';

$action  = $_GET['action']  ?? 'alerts';
$search  = $_GET['search']  ?? '';
$alerta  = $_GET['alerta']  ?? 'all';
$almacen = $_GET['almacen'] ?? '0001';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'><style>table{border-collapse:collapse}th{background-color:#f2f2f2;border:1px solid #ccc;font-weight:bold}td{border:1px solid #ccc}</style></head><body>";

if ($action === 'alerts' || $action === 'alertas') {
    echo "<h2>Reporte de Alertas de Stock - Sede " . $h($almacen) . "</h2>";
    echo "<table><thead><tr><th>CÓDIGO</th><th>PRODUCTO</th><th>EXISTENCIA</th><th>VDP (PROM)</th><th>MÍNIMO</th><th>ESTADO</th><th>SUGERIDO</th></tr></thead><tbody>";

    $conds  = ["i.existen >= 1", "i.alma = ?"];
    $params = [$almacen];

    if ($alerta === 'critical') {
        $conds[] = "(SELECT (i.existen / (IFNULL(SUM(si.cana), 0) / 30 + 0.0001)) FROM sitems si WHERE si.codigoa = b.codigo AND si.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) BETWEEN 1 AND 10";
    } elseif ($alerta === 'low') {
        $conds[] = "(SELECT (i.existen / (IFNULL(SUM(si.cana), 0) / 30 + 0.0001)) FROM sitems si WHERE si.codigoa = b.codigo AND si.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) BETWEEN 10 AND 30";
    } elseif ($alerta === 'ok') {
        $conds[] = "(SELECT (i.existen / (IFNULL(SUM(si.cana), 0) / 30 + 0.0001)) FROM sitems si WHERE si.codigoa = b.codigo AND si.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) > 30";
    }

    if ($search !== '') {
        $conds[]  = "(b.codigo LIKE ? OR b.descrip LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where = "WHERE " . implode(" AND ", $conds);

    $sql = "SELECT b.codigo, b.descrip, i.existen, b.exmin AS min,
            (SELECT SUM(cana) FROM sitems WHERE codigoa = b.codigo AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS ventau,
            (i.existen / ((SELECT SUM(cana) FROM sitems WHERE codigoa = b.codigo AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) / 30 + 0.0001)) AS diasinv
            FROM sinv b
            JOIN itsinv i ON b.codigo = i.codigo
            $where
            ORDER BY i.existen ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        $dStock   = (float)$row['diasinv'];
        $exist    = (float)$row['existen'];
        $vdp      = (float)$row['ventau'] / 30;
        $sugerido = max(0, ($vdp * 15) - $exist);
        $status   = ($dStock < 10 || $exist <= 0) ? 'Crítico' : ($dStock <= 30 ? 'Atención' : 'Óptimo');

        echo "<tr>";
        echo "<td>" . $h($row['codigo']) . "</td>";
        echo "<td>" . $h($row['descrip']) . "</td>";
        echo "<td>" . number_format($exist, 2) . "</td>";
        echo "<td>" . number_format($vdp, 2) . "</td>";
        echo "<td>" . number_format($row['min'], 2) . "</td>";
        echo "<td>" . $h($status) . " (" . round($dStock) . "d)</td>";
        echo "<td>" . ($sugerido > 0 ? round($sugerido) : '---') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";

} elseif ($action === 'movements' || $action === 'movimientos') {
    echo "<h2>Reporte de Rotación Comercial - Sede " . $h($almacen) . "</h2>";
    echo "<table><thead><tr><th>GRUPO</th><th>CÓDIGO</th><th>DESCRIPCIÓN</th><th>VENTAS (30D)</th><th>STOCK</th><th>DÍAS INV.</th></tr></thead><tbody>";

    $conds  = ["a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", "i.existen >= 1", "i.alma = ?"];
    $params = [$almacen];

    if ($search !== '') {
        $conds[]  = "(b.codigo LIKE ? OR b.descrip LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where = "WHERE " . implode(" AND ", $conds);

    $sql = "SELECT b.grupo, b.codigo, b.descrip, SUM(a.cana) AS ventau, i.existen,
                   (i.existen / (SUM(a.cana) / 30 + 0.0001)) AS diasinv
            FROM sitems a
            JOIN sinv b ON a.codigoa = b.codigo
            JOIN itsinv i ON b.codigo = i.codigo
            $where
            GROUP BY b.codigo, b.grupo, b.descrip, i.existen
            ORDER BY ventau DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $h($row['grupo']) . "</td>";
        echo "<td>" . $h($row['codigo']) . "</td>";
        echo "<td>" . $h($row['descrip']) . "</td>";
        echo "<td>" . number_format($row['ventau'], 2) . "</td>";
        echo "<td>" . number_format($row['existen'], 2) . "</td>";
        echo "<td>" . round((float)$row['diasinv']) . " d</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

echo "</body></html>";
?>
