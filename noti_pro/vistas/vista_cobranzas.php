<?php
/**
 * ============================================================
 *  MÓDULO: CUADRE DE CAJA PRO — vista_cobranzas.php
 *  Sistema: ProteoERP / noti_pro
 *  Basado en reporte: AGESTION / SMOV
 * ============================================================
 */

$pageTitle   = "ProteoERP | Cuadre de Caja";
$activePage  = "cobranzas";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');
require_once '../includes/db.php';

// 1. CAPTURA DE FILTROS Y PAGINACIÓN
$f_ini   = $_GET['f_ini'] ?? date('Y-01-01'); // Por defecto desde inicio de año para auditoría
$f_fin   = $_GET['f_fin'] ?? date('Y-m-d');
$f_banco = $_GET['f_banco'] ?? '';
$f_txt   = $_GET['f_txt'] ?? '';
$page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit   = 25;
$offset  = ($page - 1) * $limit;

try {
    // 2. MAESTRO DE BANCOS (MAPPER)
    $stmt_banc = $pdo->query("SELECT codbanc, banco FROM banc WHERE activo = 'S' ORDER BY banco");
    $banco_mapper = $stmt_banc->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. CONSTRUCCIÓN DE LA CONSULTA SQL CON FILTROS
    $where = "WHERE s.fecha >= :ini AND s.fecha <= :fin";
    $params = [':ini' => $f_ini, ':fin' => $f_fin];

    if (!empty($f_banco)) {
        $where .= " AND b.codbanc = :banco";
        $params[':banco'] = $f_banco;
    }
    if (!empty($f_txt)) {
        $where .= " AND (s.nombre LIKE :txt OR s.cod_cli LIKE :txt OR s.transac LIKE :txt)";
        $params[':txt'] = "%$f_txt%";
    }

    // 4. OBTENER RESUMEN CONSOLIDADO Y TOTALES (Sin LIMIT)
    $sql_resumen = "SELECT 
                        COALESCE(NULLIF(b.banco, ''), 'EFECTIVO') as banco_pago,
                        p.tipo as tipo_pago,
                        COUNT(*) as ops,
                        SUM(COALESCE(p.monto, s.monto)) as total_bs,
                        SUM(COALESCE(
                            p.montod, 
                            CASE WHEN s.tasa > 0 THEN ROUND(COALESCE(p.monto, s.monto) / s.tasa, 2) 
                            ELSE COALESCE(s.montod, 0) END
                        )) as total_usd
                    FROM smov s
                    LEFT JOIN sfpa p ON s.transac = p.transac
                    LEFT JOIN banc b ON b.codbanc = COALESCE(NULLIF(p.banco, ''), s.banco) AND b.activo = 'S'
                    $where
                    GROUP BY banco_pago, tipo_pago";
    
    $stmt_res = $pdo->prepare($sql_resumen);
    $stmt_res->execute($params);
    $consolidado_raw = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

    // Totales para KPIs
    $total_ops = 0; $total_bs = 0; $total_usd = 0;
    foreach($consolidado_raw as $c) {
        $total_ops += $c['ops'];
        $total_bs  += $c['total_bs'];
        $total_usd += $c['total_usd'];
    }

    // 5. OBTENER DETALLE PAGINADO
    $sql_detalle = "SELECT s.fecha, s.cod_cli, s.nombre as cliente, 
                           COALESCE(NULLIF(b.banco, ''), 'EFECTIVO') as banco_pago,
                           s.tasa, 
                           COALESCE(p.monto, s.monto) as monto_bs,
                           COALESCE(
                               p.montod, 
                               CASE WHEN s.tasa > 0 THEN ROUND(COALESCE(p.monto, s.monto) / s.tasa, 2) 
                               ELSE COALESCE(s.montod, 0) END
                           ) as monto_divisa,
                           s.transac as referencia, p.tipo as tipo_pago
                    FROM smov s
                    LEFT JOIN sfpa p ON s.transac = p.transac
                    LEFT JOIN banc b ON b.codbanc = COALESCE(NULLIF(p.banco, ''), s.banco)
                    $where
                    ORDER BY s.fecha DESC, s.transac DESC
                    LIMIT $limit OFFSET $offset";
    
    $stmt_det = $pdo->prepare($sql_detalle);
    $stmt_det->execute($params);
    $movimientos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ceil($total_ops / $limit);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

/**
 * Función auxiliar para renderizar el badge del banco con color/estilo
 */
function renderBancoBadge($nombre, $tipo = '', $monto_bs = 0, $monto_usd = 0) {
    $nombre = trim($nombre);
    $tipo   = strtoupper(trim($tipo));

    if ($nombre === 'EFECTIVO') {
        $label = ($monto_usd > 0) ? "EFECTIVO USD" : "EFECTIVO BS";
        return "<span class='pill-banco' style='background:rgba(0,230,118,0.15); color:#00e676; border: 1px solid #00e676;'>$label</span>";
    }

    if (!empty($nombre)) {
        $style = "";
        $lName = strtolower($nombre);
        if (str_contains($lName, 'banesco')) {
            $style = "background: rgba(0,180,255,0.15); color: #00b4ff; border: 1px solid #00b4ff;";
        } elseif (str_contains($lName, 'provincial')) {
            $style = "background: rgba(37,99,235,0.15); color: #60a5fa; border: 1px solid #2563eb;";
        } else {
            $style = "background: rgba(255,255,255,0.05); color: #94a3b8; border: 1px solid #475569;";
        }
        return "<span class='pill-banco' style='$style'>" . htmlspecialchars($nombre) . "</span>";
    }

    return "<span class='pill-banco' style='background:rgba(148,163,184,0.1); color:#94a3b8; border:1px dashed #475569;'>Vacío</span>";
}
?>



<main class="main-content">
    <div class="content-wrapper">
        <div class="page-title">
                <h1>Cuadre de Caja</h1>
            <p>Auditoría consolidada de ingresos y formas de pago (SMOV)</p>
        </div>

        <!-- BARRA DE RESUMEN (CARDS) -->
        <div class="kpi-row">
            <div class="kpi-card ops">
                <span class="kpi-label">Operaciones</span>
                <span class="kpi-val"><?php echo number_format($total_ops, 0); ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Recaudado (BS)</span>
                <span class="kpi-val" style="color:var(--primary)">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></span>
            </div>
            <div class="kpi-card usd">
                <span class="kpi-label">Recaudado (USD)</span>
                <span class="kpi-val" style="color:var(--accent-green)">$ <?php echo number_format($total_usd, 2); ?></span>
            </div>
        </div>

        <!-- FILTROS -->
        <section class="filters-card">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Fecha Desde</label>
                    <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
                </div>
                <div class="filter-group">
                    <label>Fecha Hasta</label>
                    <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
                </div>
                <div class="filter-group" style="min-width:220px;">
                    <label>Banco / Método</label>
                    <select name="f_banco">
                        <option value="">TODOS LOS MÉTODOS</option>
                        <?php foreach($banco_mapper as $cod => $nom): ?>
                            <option value="<?php echo $cod; ?>" <?php echo ($f_banco == $cod) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nom); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" style="flex:2;">
                    <label>Buscar Cliente o Referencia</label>
                    <input type="text" name="f_txt" value="<?php echo htmlspecialchars($f_txt); ?>" placeholder="Ej: Farmacia Tariba o 177465...">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> BUSCAR</button>
                </div>
            </form>
        </section>

        <!-- RESUMEN CONSOLIDADO POR BANCO -->
        <div class="card" style="border-top: 4px solid var(--primary);">
            <div class="t-header">
                <h3><i class="fas fa-university" style="color:var(--primary); margin-right:8px;"></i> Resumen Consolidado</h3>
                <button class="btn-csv" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> EXPORTAR EXCEL</button>
            </div>
            <div style="overflow-x: auto;">
                <table id="table-resumen">
                    <thead>
                        <tr>
                            <th>ENTIDAD BANCARIA / MÉTODO</th>
                            <th style="text-align:center;">OPS</th>
                            <th style="text-align:right;">TOTAL BS.</th>
                            <th style="text-align:right;">TOTAL USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Agrupar el consolidado por banco_pago para que no haya duplicados si el tipo_pago varía pero el banco es el mismo
                        $agrupado = [];
                        foreach($consolidado_raw as $c) {
                            $key = trim($c['banco_pago']);
                            if(!isset($agrupado[$key])) {
                                $agrupado[$key] = ['banco_pago' => $c['banco_pago'], 'tipo_pago' => $c['tipo_pago'], 'ops' => 0, 'bs' => 0, 'usd' => 0];
                            }
                            $agrupado[$key]['ops'] += $c['ops'];
                            $agrupado[$key]['bs']  += $c['total_bs'];
                            $agrupado[$key]['usd'] += $c['total_usd'];
                        }

                        foreach($agrupado as $item): ?>
                        <tr>
                            <td><?php echo renderBancoBadge($item['banco_pago'], $item['tipo_pago'], $item['bs'], $item['usd']); ?></td>
                            <td style="text-align:center; font-weight:700;"><?php echo $item['ops']; ?></td>
                            <td style="text-align:right;"><?php echo number_format($item['bs'], 2, ',', '.'); ?></td>
                            <td style="text-align:right; color:#00e676; font-weight:700;">$ <?php echo number_format($item['usd'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="row-total">
                            <td>TOTAL GENERAL</td>
                            <td style="text-align:center;"><?php echo number_format($total_ops, 0); ?></td>
                            <td style="text-align:right;">Bs. <?php echo number_format($total_bs, 2, ',', '.'); ?></td>
                            <td style="text-align:right;">$ <?php echo number_format($total_usd, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- TABLA PRINCIPAL AUDITORÍA -->
        <div class="card">
            <div class="t-header">
                <h3><i class="fas fa-list-check" style="color:var(--primary); margin-right:8px;"></i> Auditoría de Movimientos Individuales</h3>
            </div>
            <div style="overflow-x: auto;">
                <table id="table-audit">
                    <thead>
                        <tr>
                            <th>FECHA</th>
                            <th>CÓDIGO CLI</th>
                            <th>CLIENTE / REFERENCIA</th>
                            <th>BANCO DESTINO</th>
                            <th style="text-align:right;">TASA DE PAGO</th>
                            <th style="text-align:right;">MONTO (BS)</th>
                            <th style="text-align:right;">MONTO (DIVISA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($movimientos)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No se encontraron movimientos para los filtros seleccionados.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($movimientos as $r): ?>
                        <tr>
                            <td style="font-weight:600; font-size:0.8rem;"><?php echo date('d/m/Y', strtotime($r['fecha'])); ?></td>
                            <td style="font-family:monospace; color:var(--primary);"><?php echo $r['cod_cli']; ?></td>
                            <td>
                                <div style="font-weight:700;"><?php echo htmlspecialchars($r['cliente']); ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted);">Ref: <?php echo $r['referencia']; ?></div>
                            </td>
                            <td><?php echo renderBancoBadge($r['banco_pago'], $r['tipo_pago'], $r['monto_bs'], $r['monto_divisa']); ?></td>
                            <td style="text-align:right; color:var(--text-muted); font-size:0.8rem;">
                                <?php echo ($r['tasa'] > 0) ? number_format($r['tasa'], 2, ',', '.') : '---'; ?>
                            </td>
                            <td style="text-align:right; font-weight:700;">
                                <?php echo number_format($r['monto_bs'], 2, ',', '.'); ?>
                            </td>
                            <td style="text-align:right; color:#00e676; font-weight:800;">
                                $ <?php echo number_format($r['monto_divisa'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <div class="pagination-wrapper">
                <span class="page-info-bubble">
                    Mostrando <strong><?php echo min($offset + 1, $total_ops); ?> - <?php echo min($offset + $limit, $total_ops); ?></strong> de <?php echo $total_ops; ?> registros
                </span>
                
                <div class="pager-container">
                    <?php 
                        $query_params = $_GET; 
                        unset($query_params['page']);
                        $base_qs = http_build_query($query_params);
                        $base_url = "?" . ($base_qs ? $base_qs . "&" : "") . "page=";
                    ?>
                    <div class="pager-group">
                        <a href="<?php echo $base_url . max(1, $page - 1); ?>" class="pager-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>" title="Anterior">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>

                    <div style="color:var(--text-muted); font-size:0.85rem; padding: 0 10px;">
                        Página <strong><?php echo $page; ?></strong> de <?php echo $total_pages; ?>
                    </div>

                    <div class="pager-group">
                        <a href="<?php echo $base_url . min($total_pages, $page + 1); ?>" class="pager-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>" title="Siguiente">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="per-page-group">
                    <span style="font-size:0.8rem; color:var(--text-muted);">Por página:</span>
                    <select disabled class="per-page-select" style="width:60px; padding:4px;">
                        <option selected>25</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Inclusión de SheetJS para Exportación a Excel -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
function exportToExcel() {
    const table = document.getElementById('table-resumen');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Resumen Cobranzas" });
    XLSX.writeFile(wb, "Resumen_Cobranzas_" + new Date().toISOString().slice(0,10) + ".xlsx");
}
</script>

<?php include('../includes/footer.php'); ?>