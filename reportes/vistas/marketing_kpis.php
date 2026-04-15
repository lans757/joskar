<?php
require_once('../../includes/db.php');

// --- MANEJO AJAX ---
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'filtros') {
        header('Content-Type: application/json');
        try {
            $stmt_prov = $pdo->query("SELECT proveed as id, nombre FROM sprv ORDER BY nombre ASC LIMIT 200");
            $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

            $stmt_cli = $pdo->query("SELECT cliente as id, nombre FROM scli ORDER BY nombre ASC LIMIT 200");
            $clientes = $stmt_cli->fetchAll(PDO::FETCH_ASSOC);

            $stmt_stat = $pdo->query("SELECT DISTINCT tipo_doc as status FROM sfac WHERE tipo_doc IS NOT NULL AND tipo_doc != ''");
            $estados = $stmt_stat->fetchAll(PDO::FETCH_COLUMN);

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

    if ($_GET['ajax'] === 'datos') {
        $f_ini     = $_GET['f_ini']     ?? date('Y-m-01');
        $f_fin     = $_GET['f_fin']     ?? date('Y-m-d');
        $cod_cli   = $_GET['cod_cli']   ?? '';
        $codprov   = $_GET['codprov']   ?? '';
        $tipo_desc = $_GET['tipo_desc'] ?? '';
        $estado    = $_GET['estado']    ?? '';

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

            // 1. Totales
            $sql_totals = "SELECT 
                                COUNT(*) as total_count,
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

            // 2. Top Proveedor
            $sql_stats = "SELECT 
                            p.nombre as prov_nombre,
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

            // 3. Distribución
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

            echo json_encode([
                'count'       => (int)$global_totals['total_count'],
                'totales'     => [
                    'subtotal'  => (float)$global_totals['total_subtotal'],
                    'descuento' => (float)$global_totals['total_descuento'],
                    'total'     => ((float)$global_totals['total_subtotal'] - (float)$global_totals['total_descuento'] + (float)$global_totals['total_iva'])
                ],
                'top_provs'   => $top_provs,
                'dist_desc'   => $dist_descuentos
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}

// ============================================================
// ESTRUCTURA DE PÁGINA
// ============================================================
$pageTitle = "Marketing | Indicadores KPI";
$activePage = "marketing";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

<main class="main-content">
    <!-- Overlay de Carga -->
    <div id="loader-overlay">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <span class="loader-text">Calculando Indicadores</span>
            <span class="loader-subtext">Esto puede tardar unos segundos...</span>
        </div>
    </div>

    <div class="marketing-wrapper fade-in">
        
        <nav class="module-nav mb-4">
            <a href="vista_marketing.php" class="nav-item">
                <i class="fas fa-list"></i> <span>Monitor Detallado</span>
            </a>
            <a href="marketing_kpis.php" class="nav-item active">
                <i class="fas fa-chart-line"></i> <span>Indicadores KPI</span>
            </a>
        </nav>
        
        <div class="page-title">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Indicadores de Marketing</h1>
                    <p>KPIs estratégicos y desempeño de promociones.</p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="glass-card mb-4 mt-3">
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
                        <i class="fas fa-search me-2"></i> Procesar
                    </button>
                </div>
            </form>
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

    </div>
</main>

<script>
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

async function loadData() {
    if (!validateFilters()) return;
    const loader = document.getElementById('loader-overlay');
    loader.style.display = 'flex';
    loader.style.opacity = '1';
    
    const params = new URLSearchParams(new FormData(document.getElementById('filterForm')));
    params.append('ajax', 'datos');

    try {
        const r = await fetch('?' + params.toString());
        const d = await r.json();
        if (d.error) throw new Error(d.error);
        
        // Actualizar widgets
        document.getElementById('m-count').innerText = d.count;
        document.getElementById('m-discount').innerText = 'Bs ' + d.totales.descuento.toLocaleString('es-VE', {minimumFractionDigits:2});
        document.getElementById('m-total').innerText = 'Bs ' + d.totales.total.toLocaleString('es-VE', {minimumFractionDigits:2});
        const pct = d.totales.subtotal > 0 ? (d.totales.descuento / d.totales.subtotal * 100).toFixed(1) : 0;
        document.getElementById('m-pct').innerText = pct + '%';
        const top = d.top_provs[0] ? d.top_provs[0].prov_nombre : '---';
        document.getElementById('m-top-prov').innerText = top.length > 25 ? top.substring(0, 25) + '...' : top;

        renderCharts(d);
    } catch (e) {
        alert("Error cargando KPIs: " + e.message);
    } finally {
        setTimeout(() => {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 300);
        }, 300);
    }
}

let chartDistInst = null;
let chartTopInst = null;
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
        options: { cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    });

    // Gráfico de Barras: Top Laboratorios
    const ctxTop = document.getElementById('chartTop').getContext('2d');
    if (chartTopInst) chartTopInst.destroy();
    chartTopInst = new Chart(ctxTop, {
        type: 'bar',
        data: {
            labels: d.top_provs.map(p => p.prov_nombre.substring(0, 15)),
            datasets: [{
                data: d.top_provs.map(p => p.total_descuento),
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderRadius: 8, barThickness: 35
            }]
        },
        options: { indexAxis: 'y', plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { grid: { display: false } } }
        }
    });
}

// Ya no llamamos loadData(); porque forzamos la selección de proveedor
// El usuario debe hacer clic en "Procesar"
loadFilters();
</script>

<?php include('../../includes/footer.php'); ?>
