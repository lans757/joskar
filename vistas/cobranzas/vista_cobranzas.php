<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('COBRANZAS')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}
/**
 * ============================================================
 * GESTIÓN DE COBRANZAS - NOTIPRO
 * Vista optimizada para auditoría de ingresos y conciliación
 * ============================================================
 */

require_once('../../includes/db.php');

// --- Manejo AJAX: Detalle de Movimiento ---
// DEBE IR AL PRINCIPIO PARA EVITAR SALIDA HTML EN LA RESPUESTA JSON
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalle') {
    $gestion = $_GET['gestion'] ?? '';
    header('Content-Type: application/json');
    try {
        // Datos de la gestión principal
        $stmt_mov = $pdo->prepare(
            "SELECT a.id as gestion, a.fecha as fechagestion, a.fbanco, b.cliente as cod_cli, b.nombre as cliente, 
                    a.status as estatus, a.monto as monto_bs, 
                    if(a.mdolar <> 0, a.mdolar, ROUND(a.monto / (SELECT oficial FROM monecam WHERE moneda = 'USD' AND fecha <= a.fbanco ORDER BY fecha DESC LIMIT 1), 2)) as monto_usd,
                    COALESCE(NULLIF(c.banco,''), 'EFECTIVO') as banco, a.tipo_doc as tipo_pago, a.descrip
             FROM gecli a 
             JOIN scli b ON a.cliente = b.id 
             LEFT JOIN banc c ON c.codbanc = COALESCE((SELECT p.codbanc FROM gecli p WHERE p.numero = SUBSTRING_INDEX(a.numero, '-', 1) AND p.multip = 'S' LIMIT 1), a.codbanc)
             WHERE a.id = :gestion
             LIMIT 1"
        );
        $stmt_mov->execute([':gestion' => $gestion]);
        $mov = $stmt_mov->fetch(PDO::FETCH_ASSOC);

        // Facturas relacionadas a esta gestión a través de smov
        $stmt_fac = $pdo->prepare(
            "SELECT f.numero as factura, f.fecha as fecha_fac, f.cod_cli, f.nombre as cliente, 
                    f.totalg as total_bs, 
                    CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END as total_usd,
                    0 as saldo_bs, f.status as estatus_fac
             FROM sfac f
             JOIN smov s ON s.cod_cli = f.cod_cli AND (
                 s.observa1 LIKE CONCAT('%', f.numero, '%') OR 
                 s.numero = f.numero OR
                 s.num_ref = f.numero
             )
             WHERE s.gestion = :gestion AND s.tipo_doc IN ('AB','AN')
             GROUP BY f.numero
             ORDER BY f.fecha DESC"
        );
        $stmt_fac->execute([':gestion' => $gestion]);
        $facturas = $stmt_fac->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['mov' => $mov, 'facturas' => $facturas]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'AJAX_ERR: ' . $e->getMessage()]);
    }
    exit;
}

$pageTitle   = "ProteoERP | Cuadre de Caja";
$activePage  = "cobranzas";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');

// --- Filtros de búsqueda ---
$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');
$f_tipo_fec = $_GET['f_tipo_fec'] ?? 'registro';
$f_banco = $_GET['f_banco'] ?? '';
$f_txt  = $_GET['f_txt']  ?? '';

// --- Paginación ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Query Principal: Auditoría ---
try {
    // Banco Mapper para filtros
    $stmt_banc = $pdo->query("SELECT codbanc, banco FROM banc WHERE activo = 'S' ORDER BY banco");
    $banco_mapper = $stmt_banc->fetchAll(PDO::FETCH_KEY_PAIR);

    $params = [':ini' => $f_ini, ':fin' => $f_fin];
    $date_col = ($f_tipo_fec === 'fbanco') ? 'DATE(aa.fbanco)' : 'COALESCE(DATE(aa.estampa), DATE(aa.fechagestion))';
    $where_add = "";
    if (!empty($f_banco)) { 
        $where_add .= " AND base.codbanc = :banco";  
        $params[':banco'] = $f_banco; 
    }
    if (!empty($f_txt))   { 
        $where_add .= " AND (base.cliente LIKE :txt OR base.cod_cli LIKE :txt OR base.gestion LIKE :txt)"; 
        $params[':txt'] = "%$f_txt%"; 
    }

    $loose_ini = date('Y-m-d', strtotime($f_ini . ' - 60 days'));
    $loose_fin = date('Y-m-d', strtotime($f_fin . ' + 60 days'));
    $gecli_date_filter = "AND a.fecha >= '$loose_ini' AND a.fecha <= '$loose_fin'";
    if ($f_tipo_fec === 'fbanco') {
        $gecli_date_filter = "AND a.fbanco >= :ini AND a.fbanco <= :fin";
    }

    $sql_agestion = "
        SELECT 
            base.descrip, base.gestion, base.cod_cli, base.cliente, base.estado, base.tipo_doc, base.monto, base.tasa, base.montod,
            COALESCE(NULLIF(base.banco,''),'EFECTIVO') as banco, base.codbanc, base.fbanco, base.nombrep, base.fechagestion, base.estampa,
            base.numeros_smov, base.vendedor_smov, base.vend_scli, base.responsable, bb.nombre as responsnombre 
        FROM ( 
            SELECT aa.descrip,aa.gestion,aa.cod_cli,aa.cliente,aa.estado,aa.tipo_doc,aa.monto,aa.tasa,aa.montod,aa.banco,aa.codbanc,aa.fbanco,aa.nombrep,aa.fechagestion,aa.numeros_smov,aa.vendedor_smov,aa.vend_scli,aa.estampa,
            IF(aa.vendedor_smov='74,96','74',IF(aa.vendedor_smov='86,98','98',IF(aa.vendedor_smov='100,86','100',aa.responsable))) as responsable 
            FROM ( 
                SELECT a.id AS gestion, b.cliente AS cod_cli, b.nombre AS cliente, a.status AS estado, a.tipo_doc, a.monto, a.descrip, 
                    COALESCE(m.oficial, 1) as tasa, 
                    if(a.mdolar <> 0, a.mdolar, ROUND(a.monto / COALESCE(m.oficial, 1), 2)) montod, 
                    c.banco, 
                    COALESCE(p.codbanc, a.codbanc) AS codbanc, 
                    t.nombre nombrep, a.fbanco, a.fecha AS fechagestion, s.estampa, s.numeros_smov, s.vendedor_smov, b.vendedor AS vend_scli, 
                    COALESCE(NULLIF(TRIM(s.vendedor_smov), ''), b.vendedor) AS responsable 
                FROM gecli a 
                JOIN scli b ON a.cliente = b.id 
                LEFT JOIN tarjeta t ON a.fpago = t.tipo 
                LEFT JOIN ( 
                    SELECT tipo_doc, cod_cli, gestion, estampa, GROUP_CONCAT(DISTINCT numero ORDER BY numero SEPARATOR ',') AS numeros_smov, GROUP_CONCAT(DISTINCT vendedor ORDER BY vendedor SEPARATOR ',') AS vendedor_smov 
                    FROM smov WHERE tipo_doc IN ('AB','AN') AND estampa >= '$loose_ini' AND estampa <= '$loose_fin' GROUP BY cod_cli, gestion 
                ) s ON s.cod_cli = b.cliente AND s.gestion = a.id 
                LEFT JOIN (
                    SELECT numero, codbanc FROM gecli WHERE multip = 'S' AND fecha >= '$loose_ini'
                ) p ON p.numero = SUBSTRING_INDEX(a.numero, '-', 1)
                LEFT JOIN banc c ON c.codbanc = COALESCE(p.codbanc, a.codbanc) 
                LEFT JOIN monecam m ON m.moneda = 'USD' AND m.fecha = a.fbanco
                WHERE a.multip = 'N' $gecli_date_filter
            ) aa 
            WHERE $date_col >= :ini AND $date_col <= :fin AND aa.estado = 'C' AND aa.responsable <> '01'
        ) base 
        LEFT JOIN vend bb ON base.responsable=bb.vendedor
        WHERE 1=1 $where_add
    ";

    // KPIs
    $stmt_tot = $pdo->prepare("SELECT COUNT(*) as total_ops, SUM(monto) as total_bs, SUM(montod) as total_usd FROM ($sql_agestion) AS result");
    $stmt_tot->execute($params);
    $kpis      = $stmt_tot->fetch(PDO::FETCH_ASSOC);
    $total_ops = $kpis['total_ops'] ?? 0;
    $total_bs  = $kpis['total_bs']  ?? 0;
    $total_usd = $kpis['total_usd'] ?? 0;

    // Resumen Consolidado (Small Table)
    $stmt_res = $pdo->prepare("SELECT banco as banco_pago, COUNT(*) as ops, SUM(monto) as total_bs, SUM(montod) as total_usd FROM ($sql_agestion) AS result GROUP BY banco ORDER BY total_usd DESC");
    $stmt_res->execute($params);
    $consolidado = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

    // Detalle Auditoría (Main Table)
    $stmt_det = $pdo->prepare("$sql_agestion ORDER BY responsnombre ASC, base.fechagestion DESC LIMIT $limit OFFSET $offset");
    $stmt_det->execute($params);
    $movimientos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ($total_ops > 0) ? (int)ceil($total_ops / $limit) : 1;

} catch (PDOException $e) {
    die("Error de base de datos (MAIN): [" . $e->getCode() . "]: " . $e->getMessage());
}

function renderBancoBadge($n) {
    $n = trim($n);
    if (empty($n) || $n==='EFECTIVO') return "<span class='pill-banco' style='background:rgba(0,230,118,0.1); color:var(--accent-green); border:1px solid rgba(0,230,118,0.2);'>EFECTIVO</span>";
    $l=strtolower($n);
    $style = "background:rgba(255,255,255,0.05); color:var(--text-muted); border:1px solid var(--border);";
    if (str_contains($l,'banesco')) $style = "background:rgba(0,180,255,0.1); color:var(--primary); border:1px solid rgba(0,180,255,0.2);";
    elseif (str_contains($l,'provincial')) $style = "background:rgba(37,99,235,0.1); color:#60a5fa; border:1px solid rgba(37,99,235,0.2);";
    elseif (str_contains($l,'multipago')||str_contains($l,'caja')) $style = "background:rgba(255,193,7,0.1); color:var(--accent-yellow); border:1px solid rgba(255,193,7,0.2);";
    return "<span class='pill-banco' style='$style'>".htmlspecialchars($n)."</span>";
}
function renderEstatus($e) {
    $e=strtoupper(trim($e??''));
    if($e==='C') return "<span class='badge badge-ok'><i class='fas fa-check-circle'></i> CONFIRMADA</span>";
    if($e==='P') return "<span class='badge badge-low'><i class='fas fa-clock'></i> PENDIENTE</span>";
    if($e==='X') return "<span class='badge badge-critical'><i class='fas fa-times-circle'></i> ANULADA</span>";
    return "<span class='badge' style='background:rgba(255,255,255,0.05); color:var(--text-muted); border:1px solid var(--border);'>$e</span>";
}
?>



<main class="main-content">
<div class="content-wrapper">

    <!-- HEADER -->
    <div class="page-title">
        <h1><i class="fas fa-hand-holding-usd"></i> Gestión de Cobranzas</h1>
        <p>Auditoría consolidada de ingresos y formas de pago &bull; 
           <strong><?php echo date('d/m/Y',strtotime($f_ini)); ?></strong> - <strong><?php echo date('d/m/Y',strtotime($f_fin)); ?></strong>
        </p>
    </div>

    <!-- KPI CARDS -->
    <div class="metrics-grid">
        <div class="card metric-card warning">
            <div class="metric-icon"><i class="fas fa-hashtag"></i></div>
            <div class="metric-content">
                <span class="metric-label">Operaciones</span>
                <p class="metric-value"><?php echo number_format($total_ops,0,',','.'); ?></p>
            </div>
        </div>
        <div class="card metric-card primary">
            <div class="metric-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="metric-content">
                <span class="metric-label">Recaudado (BS)</span>
                <p class="metric-value">Bs. <?php echo number_format($total_bs,2,',','.'); ?></p>
            </div>
        </div>
        <div class="card metric-card success">
            <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-content">
                <span class="metric-label">Recaudado (USD)</span>
                <p class="metric-value">$ <?php echo number_format($total_usd,2,'.',','); ?></p>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card filters-card">
        <div class="filters-header">
            <i class="fas fa-filter"></i>
            <h2>Filtros de búsqueda</h2>
        </div>
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label>Filtrar Por</label>
                <select name="f_tipo_fec">
                    <option value="registro" <?php echo $f_tipo_fec=='registro'?'selected':'';?>>Fecha Gestión</option>
                    <option value="fbanco" <?php echo $f_tipo_fec=='fbanco'?'selected':'';?>>Fecha Banco</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Desde</label>
                <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
            </div>
            <div class="filter-group">
                <label>Banco / Método</label>
                <select name="f_banco">
                    <option value="">TODOS LOS MÉTODOS</option>
                    <?php foreach($banco_mapper as $cod=>$nom): ?>
                    <option value="<?php echo $cod;?>" <?php echo($f_banco==$cod)?'selected':'';?>>
                        <?php echo htmlspecialchars($nom);?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group grow">
                <label>Buscar Cliente / Referencia</label>
                <div class="sw">
                    <input type="text" name="f_txt" value="<?php echo htmlspecialchars($f_txt);?>" 
                           placeholder="Ej: Farmacia Tariba, 177465, FC00037919…">
                    <button type="submit" class="btn-neon btn-cyan">
                        <i class="fas fa-sync-alt"></i> ACTUALIZAR
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- RESUMEN POR BANCO -->
    <div class="card table-card" style="margin-top: 25px;">
        <div class="t-header">
            <h3><i class="fas fa-university"></i> Resumen de Ingresos por Banco / Forma de Pago</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>BANCO / MÉTODO</th>
                        <th class="c">OPERACIONES</th>
                        <th class="r">TOTAL (BS)</th>
                        <th class="r">TOTAL (EST. USD)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($consolidado as $c): ?>
                    <tr>
                        <td><?php echo renderBancoBadge($c['banco_pago']); ?></td>
                        <td class="c"><strong><?php echo $c['ops'];?></strong></td>
                        <td class="r" style="color:var(--text-main); font-weight:700;">Bs. <?php echo number_format($c['total_bs'],2,',','.');?></td>
                        <td class="r" style="color:var(--accent-green); font-weight:700;">$ <?php echo number_format($c['total_usd'],2,'.',',');?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AUDITORIA DETALLE -->
    <div class="card table-card" style="margin-top: 28px;">
        <div class="t-header">
            <h3><i class="fas fa-list-ul"></i> Detalle Monitor Auditoría (SMOV)</h3>
            <div class="rng">
                Mostrando del <?php echo $offset+1; ?> al <?php echo min($offset+$limit, $total_ops); ?> de <strong><?php echo $total_ops; ?></strong>
                <button class="btn-neon btn-green" onclick="exportXls('table-audit','Detalle_SMOV')" style="margin-left:15px; height: 38px; font-size: 0.75rem; padding: 0 15px;">
                    <i class="fas fa-file-excel"></i> Exportar XLS
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="table-audit">
                <thead>
                    <tr>
                        <th class="c">GESTIÓN</th>
                        <th class="c">FECHA G.</th>
                        <th>FEC. B.</th>
                        <th>RESPONSABLE</th>
                        <th>CLIENTE</th>
                        <th>BANCO / TIPO</th>
                        <th class="r">MONTO BS</th>
                        <th class="r">MONTO USD</th>
                        <th>ESTATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movimientos)): ?>
                        <tr><td colspan="9" class="text-center" style="padding:48px; opacity:0.5;">No se encontraron registros bajo los filtros actuales.</td></tr>
                    <?php else: ?>
                        <?php foreach($movimientos as $r): 
                            $isHL=(!empty($r['estado']) && strtoupper($r['estado'])!=='C');
                        ?>
                        <tr class="clickable <?php echo $isHL?'row-total':'';?>" onclick="abrirModal('<?php echo htmlspecialchars($r['gestion']);?>')">
                            <td class="c"><span class="code-badge"><?php echo str_pad((int)$r['gestion'], 4, '0', STR_PAD_LEFT);?></span></td>
                            <td class="c"><?php echo date('d/m/Y', strtotime($r['fechagestion']));?></td>
                            <td><?php echo !empty($r['fbanco']) ? date('d/m/Y', strtotime($r['fbanco'])) : '—';?></td>
                            <td style="font-weight:600; font-size:0.8rem;">
                                <?php echo htmlspecialchars($r['responsable'] . ' - ' . $r['responsnombre']);?>
                            </td>
                            <td>
                                <div style="font-weight:700; color:var(--text-main); font-size:0.8rem;"><?php echo htmlspecialchars($r['cliente']);?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($r['cod_cli']);?> &bull; <?php echo htmlspecialchars($r['descrip']??'');?></div>
                            </td>
                            <td>
                                <?php echo renderBancoBadge($r['banco']); ?>
                            </td>
                            <td class="r" style="font-weight:700;">Bs. <?php echo number_format($r['monto'], 2, ',', '.'); ?></td>
                            <td class="r" style="font-weight:700; color:var(--accent-green);">$ <?php echo number_format($r['montod'], 2, '.', ','); ?></td>
                            <td class="c"><?php echo renderEstatus($r['estado']);?></td>
                        </tr>
                        <?php endforeach;?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php
                    $page_bs  = array_sum(array_column($movimientos,'monto'));
                    $page_usd = array_sum(array_column($movimientos,'montod'));
                    ?>
                    <tr>
                        <td colspan="6" class="text-right" style="opacity:0.7;"><i class="fas fa-sigma"></i> SUBTOTAL PÁGINA (<?php echo count($movimientos);?> ops)</td>
                        <td class="r" style="font-weight:800;">Bs. <?php echo number_format($page_bs,2,',','.');?></td>
                        <td class="r" style="font-weight:800; color:var(--accent-green);">$ <?php echo number_format($page_usd,2,'.',',');?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div class="pagination-wrapper">
            <div class="page-info-bubble">
                Página <strong><?php echo $page; ?></strong> de <strong><?php echo $total_pages; ?></strong>
            </div>
            
            <div class="pager-container">
                <div class="pager-group">
                    <?php if($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>1]));?>" class="pager-btn" title="Primero"><i class="fas fa-angles-left"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1]));?>" class="pager-btn" title="Anterior"><i class="fas fa-angle-left"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angles-left"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angle-left"></i></button>
                    <?php endif; ?>

                    <span class="pcur" style="margin: 0 15px; font-weight:700; color:var(--primary);"><?php echo $page; ?></span>

                    <?php if($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1]));?>" class="pager-btn" title="Siguiente"><i class="fas fa-angle-right"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$total_pages]));?>" class="pager-btn" title="Último"><i class="fas fa-angles-right"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angle-right"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angles-right"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="GET" class="page-input-group">
                <?php foreach($_GET as $k=>$v) if($k!=='page') echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; ?>
                <span style="opacity:0.6;">Ir a</span>
                <input type="number" name="page" class="page-input" min="1" max="<?php echo $total_pages;?>" value="<?php echo $page;?>">
                <button type="submit" class="pager-btn" style="background:rgba(255,255,255,0.05);"><i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </div>

    <div class="click-tip">
        <i class="fas fa-info-circle"></i> Tip: Haz clic en una fila para inspeccionar el desglose de facturas y formas de pago.
    </div>

</div><!-- /.content-wrapper -->
</main><!-- /.main-content -->

<!-- MODAL DETALLE DE GESTIÓN -->
<div class="modal-overlay" id="modalOverlay" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-hd">
            <div>
                <h3><i class="fas fa-file-invoice-dollar"></i> Detalle de Gestión de Auditoría</h3>
                <div class="mref" id="modalRef">Cargando...</div>
            </div>
            <button class="modal-close" onclick="cerrarModal()" title="Cerrar">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="modal-loading">
                <div class="spinner"></div>
                <span>Recuperando información del servidor…</span>
            </div>
        </div>
        <div class="modal-ft">
             <button class="btn-cerrar" onclick="cerrarModal()"><i class="fas fa-times"></i> CERRAR VENTANA</button>
        </div>
    </div>
</div>

<!-- =============================================================== SCRIPTS -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
/* ---- Exportar Excel ---- */
function exportXls(id, name) {
    const t = document.getElementById(id);
    if (!t) return;
    const wb = XLSX.utils.table_to_book(t, { sheet: name });
    XLSX.writeFile(wb, name + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

/* ---- Formateo ---- */
const fmtCur = (n, c='Bs.') => c + ' ' + new Intl.NumberFormat('es-VE',{minimumFractionDigits:2}).format(n);
const fmtFec = (d) => d ? d.split('-').reverse().join('/') : '—';

/* ---- Manejo Modal ---- */
const modal      = document.getElementById('modalOverlay');
const modalHD    = document.getElementById('modalRef');
const modalBody  = document.getElementById('modalBody');

async function abrirModal(gestion) {
    modal.classList.add('open');
    modalHD.innerText = 'Cargando gestión #' + gestion + '...';
    modalBody.innerHTML = '<div class="modal-loading"><div class="spinner"></div><span>Solicitando datos...</span></div>';

    try {
        const resp = await fetch(`?ajax=detalle&gestion=${gestion}`);
        const data = await resp.json();

        if (data.error) throw new Error(data.error);
        renderModalDetail(data);
    } catch (err) {
        modalHD.innerText = 'Error';
        modalBody.innerHTML = `<div class="modal-no-fac"><i class="fas fa-triangle-exclamation"></i><br>No se pudo cargar el detalle.<br><small>${err.message}</small></div>`;
    }
}

function cerrarModal() {
    modal.classList.remove('open');
}

function estBadge(s) {
    s = (s || '').toUpperCase().trim();
    if (s === 'C') return '<span class="badge badge-ok"><i class="fas fa-check-circle"></i> CONFIRMADA</span>';
    if (s === 'P') return '<span class="badge badge-low"><i class="fas fa-clock"></i> PENDIENTE</span>';
    if (s === 'X') return '<span class="badge badge-critical"><i class="fas fa-times-circle"></i> ANULADA</span>';
    return `<span class="badge" style="background:rgba(255,255,255,0.05); color:var(--text-muted); border:1px solid var(--border);">${s}</span>`;
}

function renderModalDetail(data) {
    const m = data.mov;
    const f = data.facturas;

    modalHD.innerHTML = `<div>
        <h3><i class="fas fa-file-invoice-dollar"></i> Gestión #${String(m.gestion).padStart(4, '0')}</h3>
        <div class="mref">${m.cliente} (${m.cod_cli}) &bull; ${fmtFec(m.fechagestion)}</div>
    </div>`;

    let html = `
        <div class="modal-info-grid">
            <div class="mic"><span class="lbl">Monto Gestionado</span><span class="val gr">${fmtCur(m.monto_bs)}</span></div>
            <div class="mic"><span class="lbl">Equivalente USD</span><span class="val bl">$ ${new Intl.NumberFormat('en-US',{minimumFractionDigits:2}).format(m.monto_usd)}</span></div>
            <div class="mic"><span class="lbl">Banco / Destino</span><span class="val">${m.banco}</span></div>
            <div class="mic"><span class="lbl"><i class="fas fa-calendar-check"></i> F. Banco</span><span class="val">${fmtFec(m.fbanco)}</span></div>
            <div class="mic"><span class="lbl">Estatus</span><span class="val">${estBadge(m.estatus)}</span></div>
        </div>

        <div class="modal-sec-ttl"><i class="fas fa-file-lines"></i> Facturas Afectadas / Relacionadas</div>
    `;

    if (f.length === 0) {
        html += '<div class="modal-no-fac"><i class="fas fa-info-circle"></i> No hay facturas vinculadas directamente a esta operación en SFACT.</div>';
    } else {
        html += `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>N° FACTURA</th>
                        <th>FECHA</th>
                        <th class="r">TOTAL (BS)</th>
                        <th class="r">USD</th>
                        <th class="c">ESTATUS</th>
                    </tr>
                </thead>
                <tbody>`;
        
        let totB = 0, totD = 0;
        f.forEach(fac => {
            totB += parseFloat(fac.total_bs);
            totD += parseFloat(fac.total_usd);
            html += `
                <tr>
                    <td><span class="code-badge">${fac.factura}</span></td>
                    <td>${fmtFec(fac.fecha_fac)}</td>
                    <td class="r" style="font-weight:700;">${fmtCur(fac.total_bs,'')}</td>
                    <td class="r" style="color:var(--accent-green);">$ ${new Intl.NumberFormat('en-US',{minimumFractionDigits:2}).format(fac.total_usd)}</td>
                    <td class="c">${estBadge(fac.estatus_fac)}</td>
                </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right">TOTAL RELACIONADO:</td>
                        <td class="r">${fmtCur(totB,'')}</td>
                        <td class="r">$ ${new Intl.NumberFormat('en-US',{minimumFractionDigits:2}).format(totD)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>`;
    }

    modalBody.innerHTML = html;
}

// Cerrar con ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

// Cerrar clic fuera
modal.addEventListener('click', (e) => {
    if (e.target === modal) cerrarModal();
});
</script>

<?php include('../../includes/footer.php'); ?>
</body>
</html>