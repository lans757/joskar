<?php
/**
 * ============================================================
 * GESTIÓN DE COBRANZAS - NOTIPRO
 * Vista optimizada para auditoría de ingresos y conciliación
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['logged_in'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
    } else {
        header('Location: ../../index.php');
    }
    exit;
}

require_once '../../includes/db.php';

// --- Manejo AJAX: Detalle de Movimiento ---
// DEBE IR AL PRINCIPIO PARA EVITAR SALIDA HTML EN LA RESPUESTA JSON
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalle') {
    $transac = $_GET['transac'] ?? '';
    header('Content-Type: application/json');
    try {
        // Datos del movimiento principal (pago)
        $stmt_mov = $pdo->prepare(
            "SELECT s.transac, s.fecha, s.fecha_op as fecpag, s.cod_cli, s.nombre,
                    s.tasa, p.status as estatus,
                    COALESCE(p.monto, s.monto) as monto_bs,
                    COALESCE(p.montod,
                        CASE WHEN s.tasa > 0
                             THEN ROUND(COALESCE(p.monto, s.monto) / s.tasa, 2)
                             ELSE COALESCE(s.montod, 0) END
                    ) as monto_usd,
                    COALESCE(NULLIF(b.banco,''), 'EFECTIVO') as banco,
                    p.tipo as tipo_pago,
                    s.observa1 as descrip
             FROM smov s
             LEFT JOIN sfpa p ON s.transac = p.transac
             LEFT JOIN banc b ON b.codbanc = COALESCE(NULLIF(p.banco,''), s.banco)
             WHERE s.transac = :transac
             LIMIT 1"
        );
        $stmt_mov->execute([':transac' => $transac]);
        $mov = $stmt_mov->fetch(PDO::FETCH_ASSOC);

        // Facturas relacionadas (si las hay)
        // Eliminado f.vendedor ya que no existe en el esquema sfac actual
        $stmt_fac = $pdo->prepare(
            "SELECT f.numero   as factura,
                    f.fecha    as fecha_fac,
                    f.cod_cli,
                    f.nombre   as cliente,
                    f.totalg   as total_bs,
                    CASE WHEN f.tasa > 0 THEN ROUND(f.totalg / f.tasa, 2) ELSE 0 END as total_usd,
                    0          as saldo_bs,
                    f.status   as estatus_fac
             FROM sfac f
             WHERE f.transac = :transac
                OR f.numero IN (
                    SELECT numero FROM sitems WHERE transac = :transac2
                )
             ORDER BY f.fecha DESC"
        );
        $stmt_fac->execute([':transac' => $transac, ':transac2' => $transac]);
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

include '../../includes/header.php';
include '../../includes/sidebar.php';

// --- Filtros de búsqueda ---
$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');
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
    $where  = "WHERE s.fecha >= :ini AND s.fecha <= :fin";
    if (!empty($f_banco)) { $where .= " AND b.codbanc = :banco";  $params[':banco'] = $f_banco; }
    if (!empty($f_txt))   { $where .= " AND (s.nombre LIKE :txt OR s.cod_cli LIKE :txt OR s.transac LIKE :txt)"; $params[':txt'] = "%$f_txt%"; }

    // KPIs
    $stmt_tot = $pdo->prepare("SELECT COUNT(*) as total_ops,
            SUM(COALESCE(p.monto, s.monto)) as total_bs,
            SUM(COALESCE(p.montod, CASE WHEN s.tasa > 0 THEN ROUND(COALESCE(p.monto,s.monto)/s.tasa,2) ELSE COALESCE(s.montod,0) END)) as total_usd
        FROM smov s LEFT JOIN sfpa p ON s.transac=p.transac
        LEFT JOIN banc b ON b.codbanc=COALESCE(NULLIF(p.banco,''),s.banco) AND b.activo='S'
        $where");
    $stmt_tot->execute($params);
    $kpis      = $stmt_tot->fetch(PDO::FETCH_ASSOC);
    $total_ops = $kpis['total_ops'] ?? 0;
    $total_bs  = $kpis['total_bs']  ?? 0;
    $total_usd = $kpis['total_usd'] ?? 0;

    // Resumen Consolidado (Small Table)
    $stmt_res = $pdo->prepare("SELECT COALESCE(NULLIF(b.banco,''),'EFECTIVO') as banco_pago,
            p.tipo as tipo_pago, COUNT(*) as ops,
            SUM(COALESCE(p.monto,s.monto)) as total_bs,
            SUM(COALESCE(p.montod,CASE WHEN s.tasa>0 THEN ROUND(COALESCE(p.monto,s.monto)/s.tasa,2) ELSE COALESCE(s.montod,0) END)) as total_usd
        FROM smov s LEFT JOIN sfpa p ON s.transac=p.transac
        LEFT JOIN banc b ON b.codbanc=COALESCE(NULLIF(p.banco,''),s.banco) AND b.activo='S'
        $where GROUP BY banco_pago, tipo_pago ORDER BY total_usd DESC");
    $stmt_res->execute($params);
    $consolidado = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

    // Detalle Auditoría (Main Table)
    $stmt_det = $pdo->prepare("SELECT s.transac as gestion, s.fecha as fecha_gestion, s.fecha_op as f_banco,
            s.cod_cli, s.nombre as cliente,
            COALESCE(NULLIF(b.banco,''),'EFECTIVO') as banco,
            p.tipo as tipo_pago, COALESCE(p.status, 'C') as estatus, s.tasa,
            COALESCE(p.monto, s.monto) as monto_bs,
            COALESCE(p.montod,CASE WHEN s.tasa>0 THEN ROUND(COALESCE(p.monto,s.monto)/s.tasa,2) ELSE COALESCE(s.montod,0) END) as monto_usd,
            s.observa1 as descrip
        FROM smov s LEFT JOIN sfpa p ON s.transac=p.transac
        LEFT JOIN banc b ON b.codbanc=COALESCE(NULLIF(p.banco,''),s.banco)
        $where ORDER BY s.fecha DESC, s.transac DESC LIMIT $limit OFFSET $offset");
    $stmt_det->execute($params);
    $movimientos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ($total_ops > 0) ? (int)ceil($total_ops / $limit) : 1;

} catch (PDOException $e) {
    die("Error de base de datos (MAIN): [" . $e->getCode() . "]: " . $e->getMessage());
}

function renderBancoBadge($n) {
    $n = trim($n);
    if (empty($n) || $n==='EFECTIVO') return "<span class='premium-badge badge-success'><i class='fas fa-money-bill-wave'></i> EFECTIVO</span>";
    return "<span class='premium-badge badge-primary'><i class='fas fa-university'></i> ".htmlspecialchars($n)."</span>";
}
function renderEstatus($e) {
    $e=strtoupper(trim($e??''));
    if($e==='C') return "<span class='premium-badge badge-success'><i class='fas fa-check-circle'></i> CONFIRMADA</span>";
    if($e==='P') return "<span class='premium-badge badge-warning'><i class='fas fa-clock'></i> PENDIENTE</span>";
    if($e==='X') return "<span class='premium-badge' style='background:rgba(255,82,82,0.1); color:var(--accent-red); border-color:rgba(255,82,82,0.2);'><i class='fas fa-times-circle'></i> ANULADA</span>";
    return "<span class='premium-badge'>$e</span>";
}
?>



<main class="main-content">
    <?php include "../../includes/navbar.php"; ?>
<div class="content-wrapper animate-in">

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
            <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
            <div class="metric-content">
                <span class="metric-label">Monto Promedio (USD)</span>
                <p class="metric-value">$ <?php echo number_format($total_ops > 0 ? $total_usd / $total_ops : 0, 2, '.', ','); ?></p>
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
                <label>Fecha Desde</label>
                <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
            </div>
            <div class="filter-group">
                <label>Fecha Hasta</label>
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
                    <button type="submit" class="btn-neon btn-cyan" style="box-shadow: 0 0 20px var(--primary-glow);">
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
                        <th>FORMA</th>
                        <th class="c">OPERACIONES</th>
                        <th class="r">TOTAL (EST. USD)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($consolidado as $c): ?>
                    <tr>
                        <td><?php echo renderBancoBadge($c['banco_pago']); ?></td>
                        <td><span class="code-badge"><?php echo htmlspecialchars($c['tipo_pago']);?></span></td>
                        <td class="c"><strong><?php echo $c['ops'];?></strong></td>
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
                    <i class="fas fa-file-excel"></i> EXCEL
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="table-audit">
                <thead>
                    <tr>
                        <th>ID GESTIÓN</th>
                        <th>FECHA GESTIÓN</th>
                        <th>FEC. BANCO</th>
                        <th>CLIENTE / PAGADOR</th>
                        <th>BANCO / MÉTODO</th>
                        <th class="r">MONTO (USD)</th>
                        <th class="c">ESTATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movimientos)): ?>
                        <tr><td colspan="8" class="text-center" style="padding:48px; opacity:0.5;">No se encontraron registros bajo los filtros actuales.</td></tr>
                    <?php else: ?>
                        <?php foreach($movimientos as $r): 
                            $isHL = !empty($r['estatus']) && strtoupper($r['estatus']) !== 'C';
                        ?>
                        <tr class="clickable <?php echo $isHL?'row-total':'';?>" onclick="abrirModal('<?php echo $r['gestion'];?>')">
                            <td><span class="code-badge"><?php echo $r['gestion'];?></span></td>
                            <td><?php echo date('d/m/Y', strtotime($r['fecha_gestion']));?></td>
                            <td><?php echo $r['f_banco'] ? date('d/m/Y', strtotime($r['f_banco'])) : '—'; ?></td>
                            <td class="product-name">
                                <div style="font-weight:700; color:var(--text-main);"><?php echo htmlspecialchars($r['cliente']);?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo $r['cod_cli'];?> &bull; <?php echo htmlspecialchars($r['descrip']??'');?></div>
                            </td>
                            <td><?php echo renderBancoBadge($r['banco']); ?></td>
                            <td class="r" style="color:var(--accent-green); font-weight:700;">$ <?php echo number_format($r['monto_usd'], 2, '.', ','); ?></td>
                            <td class="c"><?php echo renderEstatus($r['estatus']??'');?></td>
                        </tr>
                        <?php endforeach;?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php
                    $page_bs  = array_sum(array_column($movimientos,'monto_bs'));
                    $page_usd = array_sum(array_column($movimientos,'monto_usd'));
                    ?>
                    <tr>
                        <td colspan="5" class="text-right" style="opacity:0.7;"><i class="fas fa-sigma"></i> SUBTOTAL PÁGINA (<?php echo count($movimientos);?> ops)</td>
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
                        <a href="?<?php echo http_build_query([...$_GET,'page'=>1]);?>" class="pager-btn" title="Primero"><i class="fas fa-angles-left"></i></a>
                        <a href="?<?php echo http_build_query([...$_GET,'page'=>$page-1]);?>" class="pager-btn" title="Anterior"><i class="fas fa-angle-left"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angles-left"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angle-left"></i></button>
                    <?php endif; ?>

                    <span class="pcur" style="margin: 0 15px; font-weight:700; color:var(--primary);"><?php echo $page; ?></span>

                    <?php if($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query([...$_GET,'page'=>$page+1]);?>" class="pager-btn" title="Siguiente"><i class="fas fa-angle-right"></i></a>
                        <a href="?<?php echo http_build_query([...$_GET,'page'=>$total_pages]);?>" class="pager-btn" title="Último"><i class="fas fa-angles-right"></i></a>
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

async function abrirModal(transac) {
    modal.classList.add('open');
    modalHD.innerText = 'Cargando gestión #' + transac + '...';
    modalBody.innerHTML = '<div class="modal-loading"><div class="spinner"></div><span>Solicitando datos...</span></div>';

    try {
        const resp = await fetch(`?ajax=detalle&transac=${transac}`);
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
        <h3><i class="fas fa-file-invoice-dollar"></i> Gestión #${m.transac}</h3>
        <div class="mref">${m.nombre} (${m.cod_cli}) &bull; ${fmtFec(m.fecha)}</div>
    </div>`;

    let html = `
        <div class="modal-info-grid">
            <div class="mic"><span class="lbl">Monto Pagado</span><span class="val gr">${fmtCur(m.monto_bs)}</span></div>
            <div class="mic"><span class="lbl">Equivalente USD</span><span class="val bl">$ ${new Intl.NumberFormat('en-US',{minimumFractionDigits:2}).format(m.monto_usd)}</span></div>
            <div class="mic"><span class="lbl">Forma de Pago</span><span class="val">${m.tipo_pago}</span></div>
            <div class="mic"><span class="lbl">Banco / Destino</span><span class="val">${m.banco}</span></div>
            <div class="mic"><span class="lbl"><i class="fas fa-calendar-check"></i> F. Banco</span><span class="val">${fmtFec(m.fecpag)}</span></div>
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
                        <th class="r">USD</th>
                        <th class="c">ESTATUS</th>
                    </tr>
                </thead>
                <tbody>`;
        
        let totB = 0, totD = 0;
        f.forEach(fac => {
            totD += parseFloat(fac.total_usd);
            html += `
                <tr>
                    <td><span class="code-badge">${fac.factura}</span></td>
                    <td>${fmtFec(fac.fecha_fac)}</td>
                    <td class="r" style="color:var(--accent-green); font-weight:700;">$ ${new Intl.NumberFormat('en-US',{minimumFractionDigits:2}).format(fac.total_usd)}</td>
                    <td class="c">${estBadge(fac.estatus_fac)}</td>
                </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right">TOTAL RELACIONADO:</td>
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

<?php include '../../includes/footer.php'; ?>
</body>
</html>