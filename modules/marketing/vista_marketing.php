<?php
/**
 * ============================================================
 * VISTA MARKETING - REPORTE DE VENTAS CON DESGLOSE DE DESCUENTOS
 * ============================================================
 * Réplica funcional y estética de la tabla de detalle de facturación
 * con desglose dinámico por tipo de descuento.
 * 
 * Basado en: reportes/ver/SITEMSXL/GEVEN/search/osp
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

// --- MANEJO AJAX ---

// 1. Endpoint: Filtros dinámicos
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filtros') {
    header('Content-Type: application/json');
    try {
        // Proveedores (sprv)
        $stmt_prov = $pdo->query("SELECT proveed as id, nombre FROM sprv ORDER BY nombre ASC LIMIT 200");
        $proveedores = $stmt_prov->fetchAll();

        // Clientes (scli)
        $stmt_cli = $pdo->query("SELECT cliente as id, nombre FROM scli ORDER BY nombre ASC LIMIT 200");
        $clientes = $stmt_cli->fetchAll();

        // Estados de factura (sfac - usamos tipo_doc ya que status es NULL)
        $stmt_stat = $pdo->query("SELECT DISTINCT tipo_doc as status FROM sfac WHERE tipo_doc IS NOT NULL AND tipo_doc != ''");
        $estados = $stmt_stat->fetchAll(PDO::FETCH_COLUMN);

        // Tipos de descuento (Mapping ProteoERP)
        $tipos_desc = [
            ['id' => 'descu1', 'nombre' => 'Comercial (%)'],
            ['id' => 'descu2', 'nombre' => 'Pronto Pago (%)'],
            ['id' => 'descu3', 'nombre' => 'Volumen (%)'],
            ['id' => 'descu',  'nombre' => 'Especial (BS)'],
            ['id' => 'descu4', 'nombre' => 'Promoción (%)']
        ];

        echo json_encode([
            'clientes' => $clientes,
            'proveedores' => $proveedores,
            'estados' => $estados,
            'tipos_desc' => $tipos_desc
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
        exit;
}

// 2. Endpoint: Datos de la tabla
if (isset($_GET['ajax']) && ($_GET['ajax'] === 'datos' || $_GET['ajax'] === 'exportar')) {
    $f_ini     = $_GET['f_ini']     ?? date('Y-m-01');
    $f_fin     = $_GET['f_fin']     ?? date('Y-m-d');
    $cod_cli   = $_GET['cod_cli']   ?? '';
    $codprov   = $_GET['codprov']   ?? '';
    $tipo_desc = $_GET['tipo_desc'] ?? '';
    $estado    = $_GET['estado']    ?? '';

    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $offset = ($page - 1) * $limit;

    header('Content-Type: application/json');
    try {
        $params = [':ini' => $f_ini, ':fin' => $f_fin];
        $where  = "WHERE f.fecha BETWEEN :ini AND :fin";

        if (!empty($cod_cli)) {
            $where .= " AND f.cod_cli = :cli";
            $params[':cli'] = $cod_cli;
        }
        if (!empty($codprov)) {
            $where .= " AND a.prov1 = :prov";
            $params[':prov'] = $codprov;
        }
        if (!empty($tipo_desc)) {
            $where .= " AND s.$tipo_desc > 0";
        }
        if (!empty($estado)) {
            $where .= " AND f.tipo_doc = :status";
            $params[':status'] = $estado;
        }

        // 1. Obtener totales globales y conteo total (sin paginación)
        $sql_totals = "SELECT 
                            COUNT(*) as total_count,
                            SUM(s.cana) as total_cantidad,
                            SUM(s.cana * s.preca) as total_subtotal,
                            SUM(s.iva) as total_iva,
                            SUM(
                                (s.cana * s.preca * s.descu1 / 100) + 
                                (s.cana * s.preca * s.descu2 / 100) + 
                                (s.cana * s.preca * s.descu3 / 100) + 
                                (s.cana * s.preca * s.descu4 / 100) + 
                                (s.descu)
                            ) as total_descuento
                       FROM sitems s
                       INNER JOIN sfac f ON s.numa = f.numero
                       INNER JOIN sinv a ON s.codigoa = a.codigo
                       $where";
        
        $stmt_totals = $pdo->prepare($sql_totals);
        $stmt_totals->execute($params);
        $global_totals = $stmt_totals->fetch(PDO::FETCH_ASSOC);
        
        $total_rows = (int)$global_totals['total_count'];
        $total_pages = ceil($total_rows / $limit);

        // 2. Obtener los datos paginados
        $sql = "SELECT 
                    s.codigoa as codigo,
                    s.desca as descripcion,
                    a.prov1 as codprov,
                    p.nombre as nombre_proveedor,
                    c.nombre as nombre_cliente,
                    s.cana as cantidad,
                    s.preca as precio_unitario,
                    s.precad as precio_unitario_usd,
                    s.tota as monto_total_bs,
                    s.totad as monto_total_usd,
                    s.descu1, s.descu2, s.descu3, s.descu4, s.descu as descu_especial_bs,
                    s.iva as iva_monto,
                    f.numero as factura_numero,
                    f.fecha as factura_fecha,
                    f.tipo_doc as factura_status,
                    a.existen as stock_actual,
                    a.consigna as stock_consignado,
                    s.barra as codigo_barras
                FROM sitems s
                INNER JOIN sfac f ON s.numa = f.numero
                INNER JOIN sinv a ON s.codigoa = a.codigo
                INNER JOIN sprv p ON a.prov1 = p.proveed
                LEFT JOIN scli c ON f.cod_cli = c.cliente
                $where
                ORDER BY f.fecha DESC, f.numero DESC
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesamiento de datos (totales de la página actual)
        $processed_rows = [];
        $active_cols = ['descu1' => false, 'descu2' => false, 'descu3' => false, 'descu4' => false, 'descu_especial_bs' => false];
        
        $page_totales = [
            'cantidad' => 0,
            'subtotal' => 0,
            'descuento' => 0,
            'iva' => 0,
            'total' => 0
        ];

        foreach ($rows as $r) {
            $subtotal_bruto = (float)$r['cantidad'] * (float)$r['precio_unitario'];
            $total_dscto = ((float)$subtotal_bruto * (float)$r['descu1'] / 100) + 
                           ((float)$subtotal_bruto * (float)$r['descu2'] / 100) + 
                           ((float)$subtotal_bruto * (float)$r['descu3'] / 100) + 
                           ((float)$subtotal_bruto * (float)$r['descu4'] / 100) + 
                           (float)$r['descu_especial_bs'];

            if ((float)$r['descu1'] > 0) $active_cols['descu1'] = true;
            if ((float)$r['descu2'] > 0) $active_cols['descu2'] = true;
            if ((float)$r['descu3'] > 0) $active_cols['descu3'] = true;
            if ((float)$r['descu4'] > 0) $active_cols['descu4'] = true;
            if ((float)$r['descu_especial_bs'] > 0)  $active_cols['descu_especial_bs'] = true;

            $monto_final = $subtotal_bruto - $total_dscto;
            $total_c_iva = $monto_final + (float)$r['iva_monto'];

            $r['subtotal_bruto'] = $subtotal_bruto;
            $r['total_descuento'] = $total_dscto;
            $r['monto_final'] = $monto_final;
            $r['total_con_iva'] = $total_c_iva;

            $page_totales['cantidad']  += (float)$r['cantidad'];
            $page_totales['subtotal']  += $subtotal_bruto;
            $page_totales['descuento'] += $total_dscto;
            $page_totales['iva']       += (float)$r['iva_monto'];
            $page_totales['total']     += $total_c_iva;

            $processed_rows[] = $r;
        }

        $final_totales = [
            'cantidad'  => (float)$global_totals['total_cantidad'],
            'subtotal'  => (float)$global_totals['total_subtotal'],
            'descuento' => (float)$global_totals['total_descuento'],
            'iva'       => (float)$global_totals['total_iva'],
            'total'     => ((float)$global_totals['total_subtotal'] - (float)$global_totals['total_descuento'] + (float)$global_totals['total_iva'])
        ];

        // Exportación CSV: Necesitamos TODOS los registros del rango, no solo la página actual
        if ($_GET['ajax'] === 'exportar') {
            $sql_all = "SELECT 
                            s.codigoa as codigo, s.desca as descripcion, a.prov1 as codprov,
                            p.nombre as nombre_proveedor, c.nombre as nombre_cliente,
                            s.cana as cantidad, s.preca as precio_unitario,
                            s.descu1, s.descu2, s.descu3, s.descu4, s.descu as descu_especial_bs,
                            s.iva as iva_monto, f.numero as factura_numero, f.fecha as factura_fecha,
                            f.tipo_doc as factura_status
                        FROM sitems s
                        INNER JOIN sfac f ON s.numa = f.numero
                        INNER JOIN sinv a ON s.codigoa = a.codigo
                        INNER JOIN sprv p ON a.prov1 = p.proveed
                        LEFT JOIN scli c ON f.cod_cli = c.cliente
                        $where
                        ORDER BY f.fecha DESC, f.numero DESC";
            $stmt_all = $pdo->prepare($sql_all);
            $stmt_all->execute($params);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=reporte_marketing_'.date('Ymd').'.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            
            fputcsv($output, ['Factura', 'Fecha', 'Codigo', 'Descripcion', 'Proveedor', 'Cliente', 'Cantidad', 'Precio Unit', 'Subtotal', 'Descuento', 'IVA', 'Total c/IVA', 'Estado']);
            
            while ($row = $stmt_all->fetch(PDO::FETCH_ASSOC)) {
                $sub = (float)$row['cantidad'] * (float)$row['precio_unitario'];
                $desc = ($sub * (float)$row['descu1'] / 100) + ($sub * (float)$row['descu2'] / 100) + 
                        ($sub * (float)$row['descu3'] / 100) + ($sub * (float)$row['descu4'] / 100) + 
                        (float)$row['descu_especial_bs'];
                $fin = $sub - $desc;
                $tot = $fin + (float)$row['iva_monto'];
                fputcsv($output, [
                    $row['factura_numero'], $row['factura_fecha'], $row['codigo'], $row['descripcion'],
                    $row['nombre_proveedor'], $row['nombre_cliente'], $row['cantidad'], 
                    $row['precio_unitario'], $sub, $desc, $row['iva_monto'], $tot, $row['factura_status']
                ]);
            }
            fclose($output);
            exit;
        }

        // 3. Obtener Top Proveedor y Distribución por tipo (en el rango completo)
        $sql_stats = "SELECT 
                        p.nombre as prov_nombre,
                        SUM(s.cana * s.preca) as subtotal_bruto,
                        SUM((s.cana * s.preca * s.descu1 / 100) + (s.cana * s.preca * s.descu2 / 100) + (s.cana * s.preca * s.descu3 / 100) + (s.cana * s.preca * s.descu4 / 100) + (s.descu)) as total_descuento
                      FROM sitems s
                      INNER JOIN sfac f ON s.numa = f.numero
                      INNER JOIN sinv a ON s.codigoa = a.codigo
                      INNER JOIN sprv p ON a.prov1 = p.proveed
                      $where
                      GROUP BY p.nombre
                      ORDER BY total_descuento DESC
                      LIMIT 5";
        $stmt_stats = $pdo->prepare($sql_stats);
        $stmt_stats->execute($params);
        $top_provs = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

        $sql_dist = "SELECT 
                        SUM(s.cana * s.preca * s.descu1 / 100) as d1,
                        SUM(s.cana * s.preca * s.descu2 / 100) as d2,
                        SUM(s.cana * s.preca * s.descu3 / 100) as d3,
                        SUM(s.cana * s.preca * s.descu4 / 100) as d4,
                        SUM(s.descu) as de
                     FROM sitems s
                     INNER JOIN sfac f ON s.numa = f.numero
                     INNER JOIN sinv a ON s.codigoa = a.codigo
                     $where";
        $stmt_dist = $pdo->prepare($sql_dist);
        $stmt_dist->execute($params);
        $d = $stmt_dist->fetch(PDO::FETCH_ASSOC);
        $dist_descuentos = [
            (float)($d['d1']??0), (float)($d['d2']??0), (float)($d['d3']??0), (float)($d['de']??0), (float)($d['d4']??0)
        ];

        header('Content-Type: application/json');
        echo json_encode([
            'filas'       => $processed_rows,
            'active_cols' => array_keys(array_filter($active_cols)),
            'totales'     => $final_totales,
            'page_totales'=> $page_totales,
            'count'       => $total_rows,
            'page'        => (int)$page,
            'limit'       => (int)$limit,
            'total_pages' => (int)$total_pages,
            'top_provs'   => $top_provs,
            'dist_desc'   => $dist_descuentos
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ESTRUCTURA DE PÁGINA
// ============================================================
$pageTitle = "Marketing | Monitor de Descuentos";
$activePage = "marketing";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

<main class="main-content">
    <?php include("../../includes/navbar.php"); ?>
    <!-- Overlay de Carga -->
    <div id="loader-overlay">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <span class="loader-text">Procesando Datos</span>
            <span class="loader-subtext">Esto puede tardar unos segundos dependiendo del volumen...</span>
        </div>
    </div>

    <div class="marketing-wrapper fade-in">
        
            <!-- Navegación de Módulo -->
        <nav class="module-nav">
            <a href="marketing_kpis.php" class="nav-item">
                <i class="fas fa-chart-line"></i> <span>Indicadores KPI</span>
            </a>
        </nav>
        
        <!-- Header Section -->
        <div class="page-title">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Monitor de Marketing</h1>
                    <p>Análisis inteligente de facturación y efectividad de descuentos.</p>
                </div>
                <button onclick="exportData()" class="btn-neon btn-green">
                    <i class="fas fa-file-csv me-2"></i> Exportar CSV
                </button>
            </div>
        </div>

        <!-- Metrics Section -->
        <div class="metric-row">
            <div class="glass-card metric-box">
                <div class="metric-icon-box" style="background: rgba(6, 182, 212, 0.1); color: var(--accent-cyan);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="metric-info">
                    <h4>Registros</h4>
                    <p id="m-count">0</p>
                </div>
            </div>
            <div class="glass-card metric-box">
                <div class="metric-icon-box" style="background: rgba(245, 158, 11, 0.1); color: var(--accent-amber);">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="metric-info">
                    <h4>Total Descuento</h4>
                    <p id="m-discount">Bs 0.00</p>
                </div>
            </div>
            <div class="glass-card metric-box">
                <div class="metric-icon-box" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald);">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="metric-info">
                    <h4>% Descuento Prom.</h4>
                    <p id="m-pct">0%</p>
                </div>
            </div>
            <div class="glass-card metric-box">
                <div class="metric-icon-box" style="background: rgba(156, 39, 176, 0.1); color: var(--accent-purple, #9c27b0);">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="metric-info">
                    <h4>Top Laboratorio</h4>
                    <p id="m-top-prov" style="font-size: 0.9rem; line-height: 1.2;">Cargando...</p>
                </div>
            </div>
            <div class="glass-card metric-box">
                <div class="metric-icon-box" style="background: rgba(244, 63, 94, 0.1); color: var(--accent-rose);">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="metric-info">
                    <h4>Total c/IVA</h4>
                    <p id="m-total">Bs 0.00</p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4 g-4" id="charts-row" style="display: none;">
            <div class="col-md-5">
                <div class="glass-card p-4 h-100">
                    <h5 class="mb-4 fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Distribución de Descuentos</h5>
                    <div style="height: 250px;"><canvas id="chartDist"></canvas></div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="glass-card p-4 h-100">
                    <h5 class="mb-4 fw-bold"><i class="fas fa-chart-bar me-2 text-success"></i>Top 5 Laboratorios (Monto Desc.)</h5>
                    <div style="height: 250px;"><canvas id="chartTop"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="glass-card">
            <form id="filterForm" class="filters-layout" onsubmit="event.preventDefault(); loadData();">
                <div class="filter-item">
                    <label>Fecha Inicio</label>
                    <input type="date" name="f_ini" id="f_ini" value="<?= date('Y-m-01') ?>" required>
                </div>
                <div class="filter-item">
                    <label>Fecha Fin</label>
                    <input type="date" name="f_fin" id="f_fin" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="filter-item">
                    <label>Proveedor</label>
                    <select name="codprov" id="f_prov" required>
                        <option value="">-- Seleccione un Proveedor --</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Cliente</label>
                    <select name="cod_cli" id="f_cli">
                        <option value="">-- Todos --</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Tipo Descuento</label>
                    <select name="tipo_desc" id="f_type">
                        <option value="">-- Todos --</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Estado</label>
                    <select name="estado" id="f_stat">
                        <option value="">-- Todos --</option>
                    </select>
                </div>
                <div class="filter-item">
                    <button type="submit" class="btn-neon btn-cyan w-100">
                        <i class="fas fa-search me-2"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Table Section -->
        <div class="glass-card" style="padding: 0;">
            <div class="table-container">
                <table class="modern-table" id="marketingTable">
                    <thead>
                        <tr id="table-headers">
                            <!-- Inyectado por JS -->
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr>
                            <td colspan="20" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted fw-bold">Analizando datos...</p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot id="table-footer" style="background: rgba(0,0,0,0.02); font-weight: 800;">
                        <!-- Inyectado por JS -->
                    </tfoot>
                </table>
            </div>
            
            <!-- Paginación -->
            <div class="pagination-container" id="pagination">
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

// 1. Cargar Filtros Dinámicos
async function loadFilters() {
    try {
        const r = await fetch('?ajax=filtros');
        const d = await r.json();
        
        const provs = document.getElementById('f_prov');
        d.proveedores.forEach(p => provs.add(new Option(`${p.nombre} (${p.id})`, p.id)));

        const clis = document.getElementById('f_cli');
        d.clientes.forEach(c => clis.add(new Option(`${c.nombre} (${c.id})`, c.id)));

        const stats = document.getElementById('f_stat');
        d.estados.forEach(s => stats.add(new Option(s, s)));

        const types = document.getElementById('f_type');
        d.tipos_desc.forEach(t => types.add(new Option(t.nombre, t.id)));

    } catch (e) { console.error("Error cargando filtros:", e); }
}

function validateFilters() {
    const fIni = document.getElementById('f_ini').value;
    const fFin = document.getElementById('f_fin').value;
    const fProv = document.getElementById('f_prov').value;
    
    if (!fIni || !fFin) {
        alert("⚠️ Por favor selecciona ambas fechas (Inicio y Fin).");
        return false;
    }
    
    if (!fProv) {
        alert("⚠️ Debes seleccionar un proveedor obligatorio para generar la consulta.");
        return false;
    }
    
    return true;
}

let currentPage = 1;
const recordsPerPage = 50;

// 2. Cargar Datos con AJAX
async function loadData(page = 1) {
    if (!validateFilters()) return;

    const loader = document.getElementById('loader-overlay');
    loader.style.display = 'flex';
    
    currentPage = page;
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form));
    params.append('ajax', 'datos');
    params.append('page', page);
    params.append('limit', recordsPerPage);

    const body = document.getElementById('table-body');
    // Mantenemos el spinner interno como fallback visual secundario
    body.innerHTML = '<tr><td colspan="20" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>';

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
        body.innerHTML = `<tr><td colspan="20" class="text-center text-danger py-4">Error: ${e.message}</td></tr>`;
    } finally {
        // Pequeño delay para evitar parpadeo si la carga es instantánea
        setTimeout(() => {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
                loader.style.opacity = '1';
            }, 300);
        }, 400);
    }
}

// 3. Renderizar Cabeceras Dinámicas
function renderHeaders(activeCols) {
    const head = document.getElementById('table-headers');
    let html = `
        <th>Factura</th>
        <th>Fecha</th>
        <th>Cód / Barras / Desc</th>
        <th>Laboratorio</th>
        <th>Cliente</th>
        <th class="text-center">Cant</th>
        <th class="text-right">P. Unit</th>
        <th class="text-right">Subtotal</th>
    `;
    
    activeCols.forEach(c => html += `<th class="text-right">${mappingDesc[c]}</th>`);
    
    html += `
        <th class="text-right">Total Desc</th>
        <th class="text-right">Monto Final</th>
        <th class="text-right">IVA</th>
        <th class="text-right" style="color: var(--primary)">Total c/IVA</th>
        <th>Estado</th>
    `;
    head.innerHTML = html;
}

// 4. Renderizar Filas
function renderRows(data) {
    const body = document.getElementById('table-body');
    const footer = document.getElementById('table-footer');
    
    if (data.filas.length === 0) {
        body.innerHTML = '<tr><td colspan="20" class="text-center py-5 text-muted">No se encontraron resultados</td></tr>';
        footer.innerHTML = '';
        return;
    }

    let bHtml = '';
    data.filas.forEach(row => {
        bHtml += `<tr class="fade-in">
            <td>
                <div class="fw-bold">${row.tipo_doc || row.factura_status}-${row.factura_numero}</div>
            </td>
            <td><span class="text-muted small">${row.factura_fecha}</span></td>
            <td>
                <div class="fw-bold"><code>${row.codigo}</code> ${row.codigo_barras ? '<span class="text-muted" style="font-size:0.75rem">['+row.codigo_barras+']</span>' : ''}</div>
                <div class="text-muted small" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${row.descripcion}">${row.descripcion}</div>
            </td>
            <td><span class="text-muted small">${row.nombre_proveedor}</span></td>
            <td><span class="text-muted small">${row.nombre_cliente || 'FINAL'}</span></td>
            <td class="text-center">
                <span class="badge bg-none text-dark">${parseFloat(row.cantidad).toFixed(0)}</span>
                <div class="small text-muted mt-1">Stk: ${parseFloat(row.stock_actual).toFixed(0)}</div>
            </td>
            <td class="text-right fw-bold">Bs ${parseFloat(row.precio_unitario).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td class="text-right">Bs ${parseFloat(row.subtotal_bruto).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        `;

        data.active_cols.forEach(col => {
            let val = row[col];
            let label, cls = 'bg-none';
            if (col === 'descu_especial_bs') {
                label = 'Bs ' + parseFloat(val).toFixed(2);
                if (val > 0) cls = 'bg-positive';
            } else {
                label = parseFloat(val).toFixed(1) + '%';
                if (val > 0) cls = (val > 20) ? 'bg-high' : 'bg-positive';
            }
            bHtml += `<td class="text-right"><span class="badge-discount ${cls}">${label}</span></td>`;
        });

        const tooltipDesglose = `Comercial: ${row.descu1}% | Pronto Pago: ${row.descu2}% | Volumen: ${row.descu3}% | Especial: Bs ${row.descu_especial_bs}`;

        bHtml += `
            <td class="text-right tooltip-cell" style="color: var(--accent-rose); font-weight: 700;">
                Bs ${parseFloat(row.total_descuento).toLocaleString('es-VE', {minimumFractionDigits:2})}
                <div class="tooltip-content">${tooltipDesglose}</div>
            </td>
            <td class="text-right fw-bold">Bs ${parseFloat(row.monto_final).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td class="text-right text-muted small">Bs ${parseFloat(row.iva_monto).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td class="text-right fw-bold" style="color: var(--primary); font-size: 1rem;">Bs ${parseFloat(row.total_con_iva).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td><span class="badge bg-light text-dark text-uppercase" style="font-size:0.65rem">${row.factura_status}</span></td>
        </tr>`;
    });
    body.innerHTML = bHtml;

    // Footer Totales
    let fHtml = `<tr>
        <td colspan="5" class="text-right">TOTALES</td>
        <td class="text-center">${data.totales.cantidad}</td>
        <td></td>
        <td class="text-right">Bs ${data.totales.subtotal.toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
    `;
    data.active_cols.forEach(() => fHtml += '<td></td>');
    fHtml += `
        <td class="text-right" style="color: var(--accent-rose)">Bs ${data.totales.descuento.toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        <td class="text-right">Bs ${(data.totales.subtotal - data.totales.descuento).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        <td class="text-right">Bs ${data.totales.iva.toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        <td class="text-right" style="color: var(--primary)">Bs ${data.totales.total.toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        <td></td>
    </tr>`;
    footer.innerHTML = fHtml;
}

// 5. Actualizar Widgets
function updateMetrics(d) {
    document.getElementById('m-count').innerText = d.count;
    document.getElementById('m-discount').innerText = 'Bs ' + d.totales.descuento.toLocaleString('es-VE', {minimumFractionDigits:2});
    document.getElementById('m-total').innerText = 'Bs ' + d.totales.total.toLocaleString('es-VE', {minimumFractionDigits:2});
    
    // % Promedio
    const pct = d.totales.subtotal > 0 ? (d.totales.descuento / d.totales.subtotal * 100).toFixed(1) : 0;
    document.getElementById('m-pct').innerText = pct + '%';

    // Top Prov
    const top = d.top_provs[0] ? d.top_provs[0].prov_nombre : '---';
    document.getElementById('m-top-prov').innerText = top.length > 25 ? top.substring(0, 25) + '...' : top;
}

let chartDistInst = null;
let chartTopInst = null;

// 5.1 Renderizar Gráficos
function renderCharts(d) {
    document.getElementById('charts-row').style.display = 'flex';
    
    // Gráfico de Dona: Distribución
    const ctxDist = document.getElementById('chartDist').getContext('2d');
    if (chartDistInst) chartDistInst.destroy();
    
    chartDistInst = new Chart(ctxDist, {
        type: 'doughnut',
        data: {
            labels: ['Comercial', 'P. Pago', 'Volumen', 'Especial', 'Promoción'],
            datasets: [{
                data: d.dist_desc,
                backgroundColor: ['#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            },
            cutout: '70%'
        }
    });

    // Gráfico de Barras: Top Laboratorios
    const ctxTop = document.getElementById('chartTop').getContext('2d');
    if (chartTopInst) chartTopInst.destroy();

    const labels = d.top_provs.map(p => p.prov_nombre.substring(0, 15));
    const values = d.top_provs.map(p => p.total_descuento);

    chartTopInst = new Chart(ctxTop, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Monto Descuento (Bs)',
                data: values,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderRadius: 8,
                barThickness: 35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { display: false } }
            }
        }
    });
}

// 6. Renderizar Paginación
function renderPagination(d) {
    const container = document.getElementById('pagination');
    if (!d.total_pages || d.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = `
        <div class="pagination-info">
            Mostrando <b>${d.filas.length}</b> de <b>${d.count}</b> registros
        </div>
        <div class="pagination-controls">
            <button class="page-btn" ${d.page === 1 ? 'disabled' : ''} onclick="loadData(${d.page - 1})">
                <i class="fas fa-chevron-left"></i>
            </button>
    `;

    // Lógica de páginas (máximo 5 botones)
    let start = Math.max(1, d.page - 2);
    let end = Math.min(d.total_pages, start + 4);
    if (end - start < 4) start = Math.max(1, end - 4);

    for (let i = start; i <= end; i++) {
        html += `
            <button class="page-btn ${i === d.page ? 'active' : ''}" onclick="loadData(${i})" 
                    style="${i === d.page ? 'background: var(--primary); color: white; border-color: var(--primary);' : ''}">
                ${i}
            </button>
        `;
    }

    html += `
            <button class="page-btn" ${d.page === d.total_pages ? 'disabled' : ''} onclick="loadData(${d.page + 1})">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    `;
    container.innerHTML = html;
}

// 7. Exportar
function exportData() {
    if (!validateDates()) return;
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form));
    params.append('ajax', 'exportar');
    window.location.href = '?' + params.toString();
}

// Auto-click buscar off: obligamos a seleccionar el proveedor primero
loadFilters();
// Ya no hacemos loadData automático porque forzamos la selección de proveedor
</script>

<?php include('../../includes/footer.php'); ?>
