<?php
/**
 * ============================================================
 * MARKETING - REPORTE DE VENTAS CON DESGLOSE DE DESCUENTOS
 * Versión Unificada y Optimizada (Basado en ProteoERP)
 * ============================================================
 * Usa la consulta optimizada de repo_sitems + interfaz moderna AJAX
 */

require_once('../../includes/db.php'); // Tu conexión PDO

// ===================================
// AJAX ENDPOINTS
 // ===================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    try {
        $action = $_GET['ajax'];

        // 1. Cargar filtros dinámicos
        if ($action === 'filtros') {
            $proveedores = $pdo->query("SELECT proveed as id, CONCAT(nombre, ' (', proveed, ')') as text 
                                        FROM sprv ORDER BY nombre LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

            $clientes = $pdo->query("SELECT cliente as id, CONCAT(nombre, ' (', cliente, ')') as text 
                                     FROM scli ORDER BY nombre LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

            $estados = $pdo->query("SELECT DISTINCT tipo_doc FROM sfac 
                                    WHERE tipo_doc IS NOT NULL AND tipo_doc != ''")->fetchAll(PDO::FETCH_COLUMN);

            $tipos_desc = [
                ['id' => 'descu1', 'nombre' => 'Comercial (%)'],
                ['id' => 'descu2', 'nombre' => 'Pronto Pago (%)'],
                ['id' => 'descu3', 'nombre' => 'Volumen (%)'],
                ['id' => 'descu',  'nombre' => 'Especial (BS)'],
                ['id' => 'descu4', 'nombre' => 'Promoción (%)']
            ];

            echo json_encode([
                'proveedores' => $proveedores,
                'clientes'    => $clientes,
                'estados'     => $estados,
                'tipos_desc'  => $tipos_desc
            ]);
            exit;
        }

        // 2. Datos de la tabla + estadísticas
        if ($action === 'datos' || $action === 'exportar') {

            $f_ini     = $_GET['f_ini'] ?? date('Y-m-01');
            $f_fin     = $_GET['f_fin'] ?? date('Y-m-d');
            $codprov   = $_GET['codprov'] ?? '';
            $cod_cli   = $_GET['cod_cli'] ?? '';
            $tipo_desc = $_GET['tipo_desc'] ?? '';
            $estado    = $_GET['estado'] ?? '';
            $tlineal   = $_GET['tlineal'] ?? 'N';

            $where = "WHERE a.fecha BETWEEN :ini AND :fin";
            $params = [':ini' => $f_ini, ':fin' => $f_fin];

            if ($codprov)  { $where .= " AND a.proveed = :prov";   $params[':prov'] = $codprov; }
            if ($cod_cli)  { $where .= " AND a.cod_cli = :cli";    $params[':cli'] = $cod_cli; }
            if ($estado)   { $where .= " AND a.tipo = :est";       $params[':est'] = $estado; }
            
            if ($tlineal === 'S') {
                $where .= " AND a.descu1 > 0";
            }

            if ($tipo_desc) {
                if ($tipo_desc === 'descu') {
                    $where .= " AND a.descuento > 0";
                } else {
                    $where .= " AND a.$tipo_desc > 0";
                }
            }

            // Consulta base
            $base_columns = "
                    a.numero as factura_numero,
                    a.fecha,
                    a.tipo as tipo_doc,
                    a.cod_cli,
                    b.nombre as nombre_cliente,
                    a.codigo,
                    a.barras as codigo_barras,
                    a.descrip as descripcion,
                    a.cana as cantidad,
                    a.preca as precio_unitario,
                    a.tota as monto_total_bs,
                    a.proveed as codprov,
                    c.nombre as nombre_proveedor,
                    a.descu1, a.descu2, a.descu3, a.descu4, a.descuento as descu_especial_bs,
                    a.oficial as tasa_bcv,
                    a.vendedor,
                    d.nombre as nom_ven
            ";

            $base_sql = "
                SELECT $base_columns
                FROM repo_sitems a
                LEFT JOIN scli b ON b.cliente = a.cod_cli
                LEFT JOIN sprv c ON c.proveed = a.proveed
                LEFT JOIN vend d ON d.vendedor = a.vendedor
                $where";

            // Paginación
            $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $offset = ($page - 1) * $limit;

            // Totales globales y Estadísticas
            $sql_stats = "SELECT 
                            COUNT(*) as total_count, 
                            SUM(cana) as total_cantidad, 
                            SUM(cana * preca) as total_subtotal,
                            0 as total_iva,
                            SUM(COALESCE(total_descu1,0) + COALESCE(total_descu2,0) + COALESCE(total_descu3,0) + COALESCE(total_descu4,0) + COALESCE(total_descuento,0)) as total_descuento
                          FROM repo_sitems a
                          $where";

            $stmt_stats = $pdo->prepare($sql_stats);
            $stmt_stats->execute($params);
            $totals = $stmt_stats->fetch(PDO::FETCH_ASSOC);

            // Top Laboratorios para el gráfico
            $sql_top = "SELECT 
                            c.nombre as prov_nombre,
                            SUM(COALESCE(a.total_descu1,0) + COALESCE(a.total_descu2,0) + COALESCE(a.total_descu3,0) + COALESCE(a.total_descu4,0) + COALESCE(a.total_descuento,0)) as total_descuento
                        FROM repo_sitems a
                        LEFT JOIN sprv c ON c.proveed = a.proveed
                        $where
                        GROUP BY c.nombre
                        ORDER BY total_descuento DESC
                        LIMIT 5";
            $stmt_top = $pdo->prepare($sql_top);
            $stmt_top->execute($params);
            $top_provs = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

            // Distribución de descuentos
            $sql_dist = "SELECT 
                            SUM(COALESCE(total_descu1,0)) as d1,
                            SUM(COALESCE(total_descu2,0)) as d2,
                            SUM(COALESCE(total_descu3,0)) as d3,
                            SUM(COALESCE(total_descuento,0)) as de,
                            SUM(COALESCE(total_descu4,0)) as d4
                         FROM repo_sitems a
                         $where";
            $stmt_dist = $pdo->prepare($sql_dist);
            $stmt_dist->execute($params);
            $d = $stmt_dist->fetch(PDO::FETCH_ASSOC);
            $dist_descuentos = [
                (float)($d['d1']??0), (float)($d['d2']??0), (float)($d['d3']??0), (float)($d['de']??0), (float)($d['d4']??0)
            ];

            // Datos paginados
            $sql = $base_sql . " ORDER BY a.fecha DESC, a.numero DESC LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed = [];
            $active_cols = ['descu1'=>false, 'descu2'=>false, 'descu3'=>false, 'descu4'=>false, 'descu_especial_bs'=>false];

            foreach ($rows as $r) {
                $subtotal = (float)$r['cantidad'] * (float)$r['precio_unitario'];
                $total_dscto = ((float)$subtotal * (float)$r['descu1']/100) +
                               ((float)$subtotal * (float)$r['descu2']/100) +
                               ((float)$subtotal * (float)$r['descu3']/100) +
                               ((float)$subtotal * (float)$r['descu4']/100) +
                               (float)$r['descu_especial_bs'];

                if ((float)$r['descu1'] > 0) $active_cols['descu1'] = true;
                if ((float)$r['descu2'] > 0) $active_cols['descu2'] = true;
                if ((float)$r['descu3'] > 0) $active_cols['descu3'] = true;
                if ((float)$r['descu4'] > 0) $active_cols['descu4'] = true;
                if ((float)$r['descu_especial_bs'] > 0) $active_cols['descu_especial_bs'] = true;

                $r['subtotal_bruto'] = $subtotal;
                $r['total_descuento'] = $total_dscto;
                $r['monto_final'] = $subtotal - $total_dscto;
                $r['total_neto'] = $r['monto_final'];

                $processed[] = $r;
            }

            if ($action === 'exportar') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=descuentos_'.date('Ymd').'.csv');
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($out, ['Factura','Fecha','Código','Descripción','Proveedor','Cliente','Cant','P.Unit','Subtotal','Descuento','Monto Neto', 'Estado']);
                
                $stmt_all = $pdo->prepare($base_sql . " ORDER BY a.fecha DESC, a.numero DESC");
                $stmt_all->execute($params);
                while ($row = $stmt_all->fetch(PDO::FETCH_ASSOC)) {
                    $sub = (float)$row['cantidad'] * (float)$row['precio_unitario'];
                    $desc = ($sub * (float)$row['descu1'] / 100) + ($sub * (float)$row['descu2'] / 100) + 
                            ($sub * (float)$row['descu3'] / 100) + ($sub * (float)$row['descu4'] / 100) + 
                            (float)$row['descu_especial_bs'];
                    $fin = $sub - $desc;
                    fputcsv($out, [
                        $row['factura_numero'], $row['fecha'], $row['codigo'], $row['descripcion'],
                        $row['nombre_proveedor'], $row['nombre_cliente'], $row['cantidad'], 
                        $row['precio_unitario'], $sub, $desc, $fin, $row['tipo_doc']
                    ]);
                }
                fclose($out);
                exit;
            }

            echo json_encode([
                'filas'       => $processed,
                'active_cols' => array_keys(array_filter($active_cols)),
                'totales'     => [
                    'cantidad' => (float)$totals['total_cantidad'],
                    'subtotal' => (float)$totals['total_subtotal'],
                    'descuento'=> (float)$totals['total_descuento'],
                    'total'    => (float)$totals['total_subtotal'] - (float)$totals['total_descuento']
                ],
                'count'       => (int)$totals['total_count'],
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => ceil($totals['total_count'] / $limit),
                'top_provs'   => $top_provs,
                'dist_desc'   => $dist_descuentos
            ]);

            exit;
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ===================================
// CARGA LA VISTA HTML
// ===================================
$pageTitle = "Marketing | Desglose de Descuentos";
$activePage = "marketing";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

<main class="main-content">
    <div id="loader-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; flex-direction:column; color:white;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-3 fs-5 fw-bold">Procesando Datos...</div>
    </div>

    <div class="marketing-wrapper fade-in">
        
        <div class="page-title mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold"><i class="fas fa-tags me-2 text-primary"></i>Monitor de Marketing</h1>
                    <p class="text-muted">Análisis optimizado de facturación y efectividad de descuentos.</p>
                </div>
                <button onclick="exportData()" class="btn btn-success rounded-pill px-4 shadow-sm">
                    <i class="fas fa-file-csv me-2"></i> Exportar CSV
                </button>
            </div>
        </div>

        <!-- Metrics Section -->
        <div class="row g-3 mb-4">
            <div class="col-md">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-glass">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-primary-soft text-primary rounded-3 p-3 me-3">
                            <i class="fas fa-receipt fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Registros</h6>
                            <h4 class="fw-bold mb-0" id="m-count">0</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-glass">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-amber-soft text-warning rounded-3 p-3 me-3">
                            <i class="fas fa-tag fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Descuento</h6>
                            <h4 class="fw-bold mb-0" id="m-discount">Bs 0.00</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-glass">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-emerald-soft text-success rounded-3 p-3 me-3">
                            <i class="fas fa-percentage fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">% Promedio</h6>
                            <h4 class="fw-bold mb-0" id="m-pct">0%</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-glass">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-purple-soft text-purple rounded-3 p-3 me-3">
                            <i class="fas fa-coins fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total c/IVA</h6>
                            <h4 class="fw-bold mb-0" id="m-total">Bs 0.00</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4 g-4" id="charts-row" style="display:none;">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-glass">
                    <h5 class="fw-bold mb-4"><i class="fas fa-chart-pie me-2 text-primary"></i>Distribución</h5>
                    <div style="height:250px;"><canvas id="chartDist"></canvas></div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-glass">
                    <h5 class="fw-bold mb-4"><i class="fas fa-chart-bar me-2 text-success"></i>Top Laboratorios (Descuento)</h5>
                    <div style="height:250px;"><canvas id="chartTop"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-glass">
            <form id="filterForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold">Desde</label>
                    <input type="date" name="f_ini" id="f_ini" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Hasta</label>
                    <input type="date" name="f_fin" id="f_fin" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Proveedor</label>
                    <select name="codprov" id="f_prov" class="form-select select2">
                        <option value="">-- Todos los Proveedores --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Cliente</label>
                    <select name="cod_cli" id="f_cli" class="form-select select2">
                        <option value="">-- Todos los Clientes --</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Descuento Lineal</label>
                    <select name="tlineal" id="f_tlineal" class="form-select">
                        <option value="N">Normal</option>
                        <option value="S">Mostrar Descuento Lineal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tipo Descuento</label>
                    <select name="tipo_desc" id="f_type" class="form-select">
                        <option value="">-- Ver Todos --</option>
                    </select>
                </div>
                 <div class="col-md-2">
                    <label class="form-label fw-bold">Estado</label>
                    <select name="estado" id="f_stat" class="form-select">
                        <option value="">-- Todos --</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" onclick="loadData()" class="btn btn-primary w-100 rounded-pill">
                        <i class="fas fa-search me-2"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Table Section -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-glass">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="marketingTable">
                    <thead class="bg-light">
                        <tr id="table-headers">
                            <!-- Inyectado por JS -->
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr>
                            <td colspan="15" class="text-center py-5 text-muted">
                                <i class="fas fa-info-circle me-2"></i> Seleccione filtros y presione Buscar
                            </td>
                        </tr>
                    </tbody>
                    <tfoot id="table-footer" class="bg-light fw-bold">
                        <!-- Inyectado por JS -->
                    </tfoot>
                </table>
            </div>
            <div class="card-footer bg-white border-0 p-3" id="pagination">
                <!-- Inyectado por JS -->
            </div>
        </div>
    </div>
</main>



<script>
const mappingDesc = {
    'descu1': 'Comercial (%)',
    'descu2': 'Pronto Pago (%)',
    'descu3': 'Volumen (%)',
    'descu_especial_bs': 'Especial (BS)',
    'descu4': 'Promoción (%)'
};

async function loadFilters() {
    try {
        const r = await fetch('?ajax=filtros');
        const d = await r.json();
        
        const provs = document.getElementById('f_prov');
        d.proveedores.forEach(p => provs.add(new Option(p.text, p.id)));

        const clis = document.getElementById('f_cli');
        d.clientes.forEach(c => clis.add(new Option(c.text, c.id)));

        const stats = document.getElementById('f_stat');
        d.estados.forEach(s => stats.add(new Option(s, s)));

        const types = document.getElementById('f_type');
        d.tipos_desc.forEach(t => types.add(new Option(t.nombre, t.id)));

        if(typeof $ !== 'undefined') $('.select2').select2({ theme: 'bootstrap-5' });
    } catch (e) { console.error("Error filtros:", e); }
}

let currentPage = 1;
const recordsPerPage = 50;
let chartDistInst = null;
let chartTopInst = null;

async function loadData(page = 1) {
    const loader = document.getElementById('loader-overlay');
    loader.style.display = 'flex';
    
    currentPage = page;
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form));
    params.append('ajax', 'datos');
    params.append('page', page);
    params.append('limit', recordsPerPage);

    try {
        const r = await fetch('?' + params.toString());
        const d = await r.json();
        if (d.error) throw new Error(d.error);
        
        renderHeaders(d.active_cols);
        renderRows(d);
        updateMetrics(d);
        renderPagination(d);
        renderCharts(d);
    } catch (e) {
        document.getElementById('table-body').innerHTML = `<tr><td colspan="15" class="text-center text-danger py-4">Error: ${e.message}</td></tr>`;
    } finally {
        loader.style.display = 'none';
    }
}

function renderHeaders(activeCols) {
    const head = document.getElementById('table-headers');
    let html = `
        <th>Factura</th>
        <th>Fecha</th>
        <th>Producto / Código</th>
        <th>Laboratorio</th>
        <th>Cliente</th>
        <th class="text-center">Cant</th>
        <th class="text-end">P. Unit</th>
        <th class="text-end">Subtotal</th>
    `;
    activeCols.forEach(c => html += `<th class="text-end">${mappingDesc[c]}</th>`);
    html += `
        <th class="text-end">Total Desc.</th>
        <th class="text-end">Monto Neto</th>
        <th>Estado</th>
    `;
    head.innerHTML = html;
}

function renderRows(data) {
    const body = document.getElementById('table-body');
    const footer = document.getElementById('table-footer');
    
    if (data.filas.length === 0) {
        body.innerHTML = '<tr><td colspan="15" class="text-center py-5">No se encontraron resultados</td></tr>';
        footer.innerHTML = '';
        return;
    }

    let bHtml = '';
    data.filas.forEach(row => {
        bHtml += `<tr>
            <td><span class="fw-bold">${row.tipo_doc}-${row.factura_numero}</span></td>
            <td class="small">${row.fecha}</td>
            <td>
                <div class="fw-bold small">${row.descripcion}</div>
                <div class="text-muted" style="font-size:0.7rem"><code>${row.codigo}</code> ${row.codigo_barras ? '| '+row.codigo_barras : ''}</div>
            </td>
            <td class="small">${row.nombre_proveedor}</td>
            <td class="small">${row.nombre_cliente || 'FINAL'}</td>
            <td class="text-center"><span class="badge bg-light text-dark">${parseFloat(row.cantidad).toFixed(0)}</span></td>
            <td class="text-end">Bs ${parseFloat(row.precio_unitario).toLocaleString('es-VE',{minF:2})}</td>
            <td class="text-end">Bs ${parseFloat(row.subtotal_bruto).toLocaleString('es-VE',{minF:2})}</td>
        `;

        data.active_cols.forEach(col => {
            let val = row[col];
            let label = (col === 'descu_especial_bs') ? 'Bs '+parseFloat(val).toFixed(2) : parseFloat(val).toFixed(1)+'%';
            let cls = val > 0 ? 'bg-positive' : 'text-muted';
            bHtml += `<td class="text-end"><span class="${val > 0 ? 'badge-discount '+cls : ''}">${label}</span></td>`;
        });

        bHtml += `
            <td class="text-end text-danger fw-bold">Bs ${parseFloat(row.total_descuento).toLocaleString('es-VE',{minimumFractionDigits:2})}</td>
            <td class="text-end fw-bold text-dark">Bs ${parseFloat(row.monto_final).toLocaleString('es-VE',{minimumFractionDigits:2})}</td>
            <td><span class="badge bg-secondary-subtle text-secondary small">${row.tipo_doc}</span></td>
        </tr>`;
    });
    body.innerHTML = bHtml;

    let fHtml = `<tr>
        <td colspan="5" class="text-end fw-bold">TOTALES</td>
        <td class="text-center fw-bold">${data.totales.cantidad.toFixed(0)}</td>
        <td></td>
        <td class="text-end fw-bold">Bs ${data.totales.subtotal.toLocaleString('es-VE',{minimumFractionDigits:2})}</td>
    `;
    data.active_cols.forEach(() => fHtml += '<td></td>');
    fHtml += `
        <td class="text-end text-danger fw-bold">Bs ${data.totales.descuento.toLocaleString('es-VE',{minimumFractionDigits:2})}</td>
        <td class="text-end fw-bold text-dark">Bs ${data.totales.total.toLocaleString('es-VE',{minimumFractionDigits:2})}</td>
        <td></td>
    </tr>`;
    footer.innerHTML = fHtml;
}

function updateMetrics(d) {
    document.getElementById('m-count').innerText = d.count.toLocaleString();
    document.getElementById('m-discount').innerText = 'Bs ' + d.totales.descuento.toLocaleString('es-VE', {minimumFractionDigits:2});
    document.getElementById('m-total').innerText = 'Bs ' + d.totales.total.toLocaleString('es-VE', {minimumFractionDigits:2});
    const pct = d.totales.subtotal > 0 ? (d.totales.descuento / d.totales.subtotal * 100).toFixed(1) : 0;
    document.getElementById('m-pct').innerText = pct + '%';
}

function renderCharts(d) {
    document.getElementById('charts-row').style.display = 'flex';
    const ctxD = document.getElementById('chartDist').getContext('2d');
    if (chartDistInst) chartDistInst.destroy();
    chartDistInst = new Chart(ctxD, {
        type: 'doughnut',
        data: {
            labels: ['Comercial', 'P. Pago', 'Volumen', 'Especial', 'Promoción'],
            datasets: [{ data: d.dist_desc, backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#d63384', '#6f42c1'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    });

    const ctxT = document.getElementById('chartTop').getContext('2d');
    if (chartTopInst) chartTopInst.destroy();
    chartTopInst = new Chart(ctxT, {
        type: 'bar',
        data: {
            labels: d.top_provs.map(p => p.prov_nombre.substring(0,18)),
            datasets: [{ label: 'Monto Descuento', data: d.top_provs.map(p => p.total_descuento), backgroundColor: '#198754', borderRadius: 5 }]
        },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
    });
}

function renderPagination(d) {
    const container = document.getElementById('pagination');
    if (d.total_pages <= 1) { container.innerHTML = ''; return; }
    let html = `<div class="d-flex justify-content-between align-items-center small text-muted"><div>Mostrando ${d.filas.length} de ${d.count} registros</div><nav><ul class="pagination pagination-sm mb-0">`;
    for (let i = 1; i <= d.total_pages; i++) {
        if (i > 5 && i < d.total_pages) { if(i === 6) html += '<li class="page-item disabled"><span class="page-link">...</span></li>'; continue; }
        html += `<li class="page-item ${i === d.page ? 'active' : ''}"><a class="page-link" href="#" onclick="loadData(${i}); return false;">${i}</a></li>`;
    }
    html += `</ul></nav></div>`;
    container.innerHTML = html;
}

function exportData() {
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form));
    params.append('ajax', 'exportar');
    window.location.href = '?' + params.toString();
}

loadFilters();
</script>

<?php include('../../includes/footer.php'); ?>