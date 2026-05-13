<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=Reporte_Inventario_' . date('Y-m-d') . '.xls');

// Database configuration (matching api.php)
$config = [
    'host' => 'localhost',
    'user' => 'datasis',
    'pass' => '1234',
    'db'   => 'datasis'
];

try {
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);
    if ($conn->connect_error) {
        throw new Exception("Conexión Fallida: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$action = $_GET['action'] ?? 'alerts';
$search = $conn->real_escape_string($_GET['search'] ?? '');
$alerta = $_GET['alerta'] ?? 'all';
$almacen= $_GET['almacen'] ?? '0001';

$search_where = !empty($search) ? " AND (b.codigo LIKE '%$search%' OR b.descrip LIKE '%$search%')" : "";
$almacen_where = " AND i.alma = '$almacen'";

echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'><style>table { border-collapse: collapse; } th { background-color: #f2f2f2; border: 1px solid #ccc; font-weight: bold; } td { border: 1px solid #ccc; }</style></head><body>";

if ($action == 'alerts' || $action == 'alertas') {
    echo "<h2>Reporte de Alertas de Stock - Sede $almacen</h2>";
    echo "<table><thead><tr><th>CÓDIGO</th><th>PRODUCTO</th><th>EXISTENCIA</th><th>VDP (PROM)</th><th>MÍNIMO</th><th>ESTADO</th><th>SUGERIDO</th></tr></thead><tbody>";

    $where_parts = ["i.existen >= 1", "i.alma = '$almacen'"];
    if ($alerta == 'critical') {
        $where_parts[] = "(SELECT (i.existen / (IFNULL(SUM(si.cana), 0) / 30 + 0.0001)) FROM sitems si WHERE si.codigoa = b.codigo AND si.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) BETWEEN 1 AND 10";
    } elseif ($alerta == 'low') {
        $where_parts[] = "(SELECT (i.existen / (IFNULL(SUM(si.cana), 0) / 30 + 0.0001)) FROM sitems si WHERE si.codigoa = b.codigo AND si.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) BETWEEN 10 AND 30";
    } elseif ($alerta == 'ok') {
        $where_parts[] = "(SELECT (i.existen / (IFNULL(SUM(si.cana), 0) / 30 + 0.0001)) FROM sitems si WHERE si.codigoa = b.codigo AND si.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) > 30";
    }
    if (!empty($search_where)) $where_parts[] = substr($search_where, 5);
    $where = "WHERE " . implode(" AND ", $where_parts);

    $sql = "SELECT b.codigo, b.descrip, i.existen, b.exmin as min,
            (SELECT SUM(cana) FROM sitems WHERE codigoa = b.codigo AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as ventau,
            (i.existen / ((SELECT SUM(cana) FROM sitems WHERE codigoa = b.codigo AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) / 30 + 0.0001)) as diasinv 
            FROM sinv b 
            JOIN itsinv i ON b.codigo = i.codigo
            $where 
            ORDER BY i.existen ASC";

    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $dStock = (float)$row['diasinv'];
        $exist = (float)$row['existen'];
        $vdp = (float)$row['ventau'] / 30;
        $sugerido = max(0, ($vdp * 15) - $exist);
        $status = ($dStock < 10 || $exist <= 0) ? "Crítico" : (($dStock <= 30) ? "Atención" : "Óptimo");
        
        echo "<tr>";
        echo "<td>{$row['codigo']}</td>";
        echo "<td>{$row['descrip']}</td>";
        echo "<td>" . number_format($row['existen'], 2) . "</td>";
        echo "<td>" . number_format($vdp, 2) . "</td>";
        echo "<td>" . number_format($row['min'], 2) . "</td>";
        echo "<td>$status (" . round($dStock) . "d)</td>";
        echo "<td>" . ($sugerido > 0 ? round($sugerido) : '---') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";

} else if ($action == 'movements' || $action == 'movimientos') {
    echo "<h2>Reporte de Rotación Comercial - Sede $almacen</h2>";
    echo "<table><thead><tr><th>GRUPO</th><th>CÓDIGO</th><th>DESCRIPCIÓN</th><th>VENTAS (30D)</th><th>STOCK</th><th>DÍAS INV.</th></tr></thead><tbody>";

    $where = "WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND i.existen >= 1 $search_where $almacen_where";
    $sql = "SELECT b.grupo, b.codigo, b.descrip, SUM(a.cana) as ventau, i.existen,
                   (i.existen / (SUM(a.cana) / 30 + 0.0001)) as diasinv
            FROM sitems a
            JOIN sinv b ON a.codigoa = b.codigo
            JOIN itsinv i ON b.codigo = i.codigo
            $where
            GROUP BY b.codigo
            ORDER BY ventau DESC";

    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['grupo']}</td>";
        echo "<td>{$row['codigo']}</td>";
        echo "<td>{$row['descrip']}</td>";
        echo "<td>" . number_format($row['ventau'], 2) . "</td>";
        echo "<td>" . number_format($row['existen'], 2) . "</td>";
        echo "<td>" . round($row['diasinv']) . " d</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

echo "</body></html>";
$conn->close();
?>
