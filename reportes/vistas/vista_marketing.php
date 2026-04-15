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

require_once('../../includes/db.php');

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

        echo json_encode([
            'filas'       => $processed_rows,
            'active_cols' => array_keys(array_filter($active_cols)),
            'totales'     => $final_totales,
            'page_totales'=> $page_totales,
            'count'       => $total_rows,
            'page'        => (int)$page,
            'limit'       => (int)$limit,
            'total_pages' => (int)$total_pages
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

<style>
/* Variables y Estilos Globales de ProteoERP */
:root {
    --accent-cyan: #06b6d4;
    --accent-emerald: #10b981;
    --accent-amber: #f59e0b;
    --accent-rose: #f43f5e;
}

.marketing-wrapper {
    padding: 2rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Card Estilo Premium */
.glass-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: var(--transition);
}

/* Filtros */
.filters-layout {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem;
    align-items: end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-item label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.filter-item select, .filter-item input {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 0.6rem 0.8rem;
    color: var(--text-main);
    font-size: 0.9rem;
    transition: var(--transition);
}

.filter-item select:focus, .filter-item input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(0, 180, 255, 0.1);
    outline: none;
}

/* Tabla Moderna */
.table-container {
    overflow-x: auto;
    border-radius: var(--radius-md);
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.modern-table th {
    background: rgba(0,0,0,0.02);
    padding: 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    border-bottom: 2px solid var(--border);
    text-align: left;
    white-space: nowrap;
}

.modern-table td {
    padding: 1rem;
    font-size: 0.875rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.modern-table tr:last-child td {
    border-bottom: none;
}

.modern-table tr:hover {
    background: rgba(0, 180, 255, 0.03);
}

/* Badges y Tooltips */
.badge-discount {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.7rem;
}

.bg-positive { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); }
.bg-high { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); }
.bg-none { background: rgba(0,0,0,0.05); color: var(--text-muted); }

.tooltip-cell {
    position: relative;
    cursor: help;
}

.tooltip-content {
    visibility: hidden;
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 100;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
}

.tooltip-cell:hover .tooltip-content {
    visibility: visible;
    opacity: 1;
}

/* Métricas */
.metric-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.metric-box {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.metric-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.metric-info h4 {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
    text-transform: uppercase;
    font-weight: 700;
}

.metric-info p {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    color: var(--text-main);
}

/* Animaciones */
.fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Loading Overlay Premium */
#loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    transition: opacity 0.3s ease;
}

.loader-content {
    text-align: center;
    background: var(--bg-card);
    padding: 3rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.loader-spinner {
    width: 64px;
    height: 64px;
    border: 4px solid rgba(0, 180, 255, 0.1);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    margin: 0 auto 1.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loader-text {
    color: var(--text-main);
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: block;
}

.loader-subtext {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Paginación */
.pagination-container {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border);
    background: rgba(0,0,0,0.01);
}

.pagination-info {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.pagination-controls {
    display: flex;
    gap: 0.25rem;
}

.page-btn {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(0, 180, 255, 0.05);
}

.page-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
    .marketing-wrapper { padding: 1rem; }
    .filters-layout { grid-template-columns: 1fr; }
    .pagination-container { flex-direction: column; gap: 1rem; text-align: center; }
}
</style>

<main class="main-content">
    <!-- Overlay de Carga -->
    <div id="loader-overlay">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <span class="loader-text">Procesando Datos</span>
            <span class="loader-subtext">Esto puede tardar unos segundos dependiendo del volumen...</span>
        </div>
    </div>

    <div class="marketing-wrapper fade-in">
        
        <!-- Header Section -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 mb-1" style="font-weight: 800; letter-spacing: -0.025em;">Monitor de Marketing</h1>
                <p class="text-muted mb-0">Análisis inteligente de facturación y efectividad de descuentos.</p>
            </div>
            <div class="d-flex gap-2">
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
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="metric-info">
                    <h4>Monto Neto</h4>
                    <p id="m-net">Bs 0.00</p>
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

        <!-- Filters Section -->
        <div class="glass-card">
            <form id="filterForm" class="filters-layout" onsubmit="event.preventDefault(); loadData();">
                <div class="filter-item">
                    <label>Búsqueda por Mes</label>
                    <div class="d-flex gap-2">
                        <select id="quickMonth" class="form-select">
                            <option value="">-- Mes --</option>
                            <option value="01">Enero</option>
                            <option value="02">Febrero</option>
                            <option value="03">Marzo</option>
                            <option value="04">Abril</option>
                            <option value="05">Mayo</option>
                            <option value="06">Junio</option>
                            <option value="07">Julio</option>
                            <option value="08">Agosto</option>
                            <option value="09">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>
                        <select id="quickYear" class="form-select">
                            <?php 
                            $currY = date('Y');
                            for($y=$currY; $y>=$currY-2; $y--) {
                                $sel = ($y == $currY) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <!-- Inputs ocultos para enviar al servidor -->
                    <input type="hidden" name="f_ini" id="f_ini" value="<?= date('Y-m-01') ?>">
                    <input type="hidden" name="f_fin" id="f_fin" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="filter-item">
                    <label>Proveedor</label>
                    <select name="codprov" id="f_prov">
                        <option value="">-- Todos --</option>
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

// Lógica de búsqueda rápida por mes
document.getElementById('quickMonth').addEventListener('change', updateQuickDates);
document.getElementById('quickYear').addEventListener('change', updateQuickDates);

function updateQuickDates() {
    const month = document.getElementById('quickMonth').value;
    const year = document.getElementById('quickYear').value;
    if (!month || !year) return;

    const firstDay = `${year}-${month}-01`;
    const lastDay = new Date(year, month, 0).toISOString().split('T')[0];

    document.getElementById('f_ini').value = firstDay;
    document.getElementById('f_fin').value = lastDay;
}

function validateDates() {
    const month = document.getElementById('quickMonth').value;
    const year = document.getElementById('quickYear').value;
    
    if (!month || !year) {
        alert("⚠️ Por favor selecciona un mes para realizar la búsqueda.");
        return false;
    }
    return true;
}

let currentPage = 1;
const recordsPerPage = 50;

// 2. Cargar Datos con AJAX
async function loadData(page = 1) {
    if (!validateDates()) return;

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
    document.getElementById('m-net').innerText = 'Bs ' + (d.totales.subtotal - d.totales.descuento).toLocaleString('es-VE', {minimumFractionDigits:2});
    document.getElementById('m-total').innerText = 'Bs ' + d.totales.total.toLocaleString('es-VE', {minimumFractionDigits:2});
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

// Init
// Establecer el mes actual por defecto
const now = new Date();
document.getElementById('quickMonth').value = String(now.getMonth() + 1).padStart(2, '0');
document.getElementById('quickYear').value  = String(now.getFullYear());
updateQuickDates();

loadFilters();
loadData();
</script>

<?php include('../../includes/footer.php'); ?>
