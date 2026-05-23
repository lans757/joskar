<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('MARKETING')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}

// --- Manejo AJAX: Detalle de factura ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalle_factura') {
    $numa = $_GET['numa'] ?? '';
    header('Content-Type: application/json');
    try {
        $stmt_det = $pdo->prepare("
            SELECT 
                d.codigoa AS CodigoProducto,
                s.descrip AS Descripcion,
                pv.nombre AS Proveedor,
                JSON_UNQUOTE(JSON_EXTRACT(d.last_insert, '$.preca')) AS Precio,
                JSON_UNQUOTE(JSON_EXTRACT(d.last_insert, '$.cana')) AS Cantidad,
                d.nombre AS Oferta,
                d.descuento AS Porcentaje
            FROM itpfacdescu d
            LEFT JOIN sinv s ON d.codigoa = s.codigo
            LEFT JOIN sprv pv ON s.prvreg = pv.proveed
            WHERE d.numa = :numa
        ");
        $stmt_det->execute([':numa' => $numa]);
        echo json_encode($stmt_det->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$pageTitle = "ProteoERP | Dashboard Ofertas Aplicadas";
$activePage = "marketing";
$path_prefix = "../../";

$extraStyles = "<link rel='stylesheet' href='{$path_prefix}assets/css/marketing.css'>
<style>
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px); }
.modal-overlay.active { display:flex; }
.modal-box { background:var(--bg-base); width:90%; max-width:900px; border-radius:16px; border:1px solid var(--border); box-shadow:0 10px 40px rgba(0,0,0,0.3); display:flex; flex-direction:column; max-height:90vh; }
.modal-hd { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:var(--bg-elevated); border-radius:16px 16px 0 0; }
.modal-hd h3 { margin:0; font-size:1.4rem; color:var(--text-primary); }
.modal-close { background:transparent; border:none; color:var(--text-muted); font-size:1.8rem; cursor:pointer; }
.modal-close:hover { color:var(--text-primary); }
.modal-body { padding:24px; overflow-y:auto; flex:1; }
.clickable-row { cursor:pointer; }
.clickable-row:hover { background: rgba(255,255,255,0.03); }
</style>
";

include('../../includes/header.php');
include('../../includes/sidebar.php');

$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');

// Consulta para Gráfico: Agrupado por Día de la Semana
$stmt_dias = $pdo->prepare("
    SELECT 
        DAYOFWEEK(d.fecha) AS dia_num,
        COUNT(DISTINCT d.numa) AS total_facturas,
        COUNT(d.codigoa) AS total_productos
    FROM itpfacdescu d
    WHERE d.fecha >= :ini AND d.fecha <= :fin
    GROUP BY dia_num
    ORDER BY dia_num
");
$stmt_dias->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$datos_dias = $stmt_dias->fetchAll(PDO::FETCH_ASSOC);

// Mapeo manual de días
$nombres_dias = [1 => 'Domingo', 2 => 'Lunes', 3 => 'Martes', 4 => 'Miércoles', 5 => 'Jueves', 6 => 'Viernes', 7 => 'Sábado'];
$chartLabels = [];
$chartDataFac = [];
$chartDataProd = [];
$total_fac_global = 0;
$total_prod_global = 0;
$dia_estrella = ['nombre' => '-', 'total' => 0];

// Inicializar todos los días en 0
foreach($nombres_dias as $num => $nombre) {
    $chartLabels[$num] = $nombre;
    $chartDataFac[$num] = 0;
    $chartDataProd[$num] = 0;
}

foreach($datos_dias as $row) {
    $num = $row['dia_num'];
    $chartDataFac[$num] = (int)$row['total_facturas'];
    $chartDataProd[$num] = (int)$row['total_productos'];
    $total_fac_global += $row['total_facturas'];
    $total_prod_global += $row['total_productos'];
    if($row['total_facturas'] > $dia_estrella['total']) {
        $dia_estrella = ['nombre' => $nombres_dias[$num], 'total' => $row['total_facturas']];
    }
}

// Reindexar arrays para JS
$chartLabels = array_values($chartLabels);
$chartDataFac = array_values($chartDataFac);
$chartDataProd = array_values($chartDataProd);

// Consulta para la Tabla: Agrupado por Factura
$stmt_fac = $pdo->prepare("
    SELECT 
        d.fecha AS Fecha,
        d.numa AS Factura,
        p.cod_cli AS CodigoCliente,
        p.nombre AS Cliente,
        z.nombre AS Zona,
        COUNT(d.codigoa) AS CantidadProductos,
        MAX(d.nombre) AS EjemploOferta
    FROM itpfacdescu d
    LEFT JOIN pfac p ON d.numa = p.numero
    LEFT JOIN zona z ON p.zona = z.codigo
    WHERE d.fecha >= :ini AND d.fecha <= :fin
    GROUP BY d.fecha, d.numa, p.cod_cli, p.nombre, z.nombre
    ORDER BY d.fecha DESC, d.numa DESC
");
$stmt_fac->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$facturas = $stmt_fac->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main-content">
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="vista_marketing.php" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Dashboard</span>
        </a>
        <a href="marketing_kpis.php" class="nav-item">
            <i class="fas fa-table"></i>
            <span>Histórico KPIs</span>
        </a>
        <a href="carga_marketing.php" class="nav-item">
            <i class="fas fa-upload"></i>
            <span>Carga de Datos</span>
        </a>
        <a href="vista_descuentos.php" class="nav-item active">
            <i class="fas fa-tags"></i>
            <span>Ofertas Aplicadas</span>
        </a>
    </nav>

    <div class="animate-fadeIn">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
            <div>
                <h1 style="font-size: 2.4rem; font-weight: 800; letter-spacing: -1.5px; margin-bottom: 6px; background: linear-gradient(135deg, var(--text-primary) 30%, var(--text-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    Métricas de Descuentos
                </h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500;">
                    Rendimiento por día y facturación de ofertas.
                </p>
            </div>
            
            <form method="GET" style="display: flex; gap: 12px; align-items: flex-end; background: var(--bg-elevated); padding: 12px 20px; border-radius: 16px; border: 1px solid var(--border);">
                <div>
                    <label style="display:block; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;">Desde</label>
                    <input type="date" name="f_ini" value="<?php echo $f_ini; ?>" style="background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary); border-radius: 8px; padding: 6px 12px; font-family: var(--font-mono); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;">Hasta</label>
                    <input type="date" name="f_fin" value="<?php echo $f_fin; ?>" style="background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary); border-radius: 8px; padding: 6px 12px; font-family: var(--font-mono); font-size: 0.85rem;">
                </div>
                <button type="submit" style="background: var(--social-color); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">Filtrar</button>
            </form>
        </div>

        <!-- KPIs y Gráfico Superior -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 32px;">
            <!-- Mini KPIs -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div class="glass-panel" style="padding: 24px; flex:1; position:relative;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--social-color), transparent);"></div>
                    <span style="font-size: 1.8rem; margin-bottom:12px; display:block;">🧾</span>
                    <div class="kpi-value"><?php echo number_format($total_fac_global, 0, ',', '.'); ?></div>
                    <div class="kpi-title">Facturas c/ Descuento</div>
                </div>
                <div class="glass-panel" style="padding: 24px; flex:1; position:relative;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #10b981, transparent);"></div>
                    <span style="font-size: 1.8rem; margin-bottom:12px; display:block;">📦</span>
                    <div class="kpi-value"><?php echo number_format($total_prod_global, 0, ',', '.'); ?></div>
                    <div class="kpi-title">Total Prod. en Oferta</div>
                </div>
                <div class="glass-panel" style="padding: 24px; flex:1; position:relative;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #fbbf24, transparent);"></div>
                    <span style="font-size: 1.8rem; margin-bottom:12px; display:block;">⭐</span>
                    <div class="kpi-value" style="font-size:1.8rem;"><?php echo $dia_estrella['nombre']; ?></div>
                    <div class="kpi-title">Día con Mayor Demanda</div>
                </div>
            </div>

            <!-- Chart -->
            <div class="glass-panel" style="padding: 24px; display:flex; flex-direction:column;">
                <div style="font-size:0.85rem; font-weight: 700; color:var(--text-muted); letter-spacing:0.1em; margin-bottom:16px; text-transform: uppercase;">
                    Facturas con Descuento por Día de la Semana
                </div>
                <div style="flex:1; position:relative; min-height: 300px;">
                    <canvas id="barDias"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla Agrupada por Factura -->
        <div class="glass-panel animate-fadeUp" style="padding: 24px; animation-delay: 0.1s;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:1.1rem; color:var(--text-primary);"><i class="fas fa-list"></i> Listado de Facturas</h3>
                <small style="color:var(--text-muted);">Haz clic en una factura para ver el detalle de productos.</small>
            </div>
            <div style="overflow-x: auto; max-height: 500px;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead style="position: sticky; top: 0; background: var(--bg-elevated); z-index: 10; white-space: nowrap;">
                        <tr style="border-bottom: 2px solid var(--border);">
                            <th style="padding: 12px 16px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Fecha</th>
                            <th style="padding: 12px 16px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Factura</th>
                            <th style="padding: 12px 16px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Cliente</th>
                            <th style="padding: 12px 16px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Zona</th>
                            <th style="padding: 12px 16px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">Principal Oferta</th>
                            <th style="padding: 12px 16px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; text-align:center;">Prod. con Dto.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($facturas)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">No hay facturas registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($facturas as $row): ?>
                                <tr class="clickable-row" onclick="abrirModalFactura('<?= $row['Factura'] ?>')" style="border-bottom: 1px solid var(--border); transition: background 0.2s;">
                                    <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap;"><?= date('d/m/Y', strtotime($row['Fecha'])) ?></td>
                                    <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 0.85rem; color: var(--social-color); font-weight: 700; white-space: nowrap;"><?= htmlspecialchars($row['Factura']) ?></td>
                                    <td style="padding: 12px 16px; font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">
                                        <?= htmlspecialchars($row['Cliente']) ?><br>
                                        <small style="color:var(--text-muted); font-family:var(--font-mono);"><?= htmlspecialchars($row['CodigoCliente']) ?></small>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($row['Zona']) ?></td>
                                    <td style="padding: 12px 16px; font-size: 0.85rem; color: #10b981; font-weight: 700;">
                                        <span style="background: rgba(16, 185, 129, 0.1); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(16, 185, 129, 0.2);"><?= htmlspecialchars($row['EjemploOferta']) ?></span>
                                    </td>
                                    <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 0.95rem; color: var(--text-primary); text-align: center; font-weight:700;">
                                        <?= $row['CantidadProductos'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Factura -->
<div class="modal-overlay" id="modalFactura">
    <div class="modal-box">
        <div class="modal-hd">
            <h3>Factura <span id="modal-fac-num" style="color:var(--social-color);"></span></h3>
            <button class="modal-close" onclick="cerrarModalFactura()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modal-loading" style="text-align:center; padding:40px; display:none;">
                <div class="animate-spin" style="width:40px; height:40px; border:3px solid rgba(255,255,255,0.1); border-top-color:var(--social-color); border-radius:50%; margin:0 auto 16px;"></div>
                <p style="color:var(--text-muted);">Cargando productos...</p>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.85rem; white-space:nowrap;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--border);">
                            <th style="padding:10px; color:var(--text-muted);">Cód</th>
                            <th style="padding:10px; color:var(--text-muted);">Producto</th>
                            <th style="padding:10px; color:var(--text-muted);">Proveedor</th>
                            <th style="padding:10px; color:var(--text-muted); text-align:right;">Precio</th>
                            <th style="padding:10px; color:var(--text-muted); text-align:center;">Cant.</th>
                            <th style="padding:10px; color:var(--text-muted);">Oferta</th>
                            <th style="padding:10px; color:var(--text-muted); text-align:center;">%</th>
                        </tr>
                    </thead>
                    <tbody id="modal-fac-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</main>

<script>
// Inicializar Gráfico
const ctx = document.getElementById('barDias');
if(ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Facturas con Descuento',
                data: <?php echo json_encode($chartDataFac); ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });
}

// Lógica Modal
function abrirModalFactura(numa) {
    document.getElementById('modalFactura').classList.add('active');
    document.getElementById('modal-fac-num').innerText = numa;
    document.getElementById('modal-loading').style.display = 'block';
    document.getElementById('modal-fac-tbody').innerHTML = '';

    fetch(`?ajax=detalle_factura&numa=${numa}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('modal-loading').style.display = 'none';
            if(data.error) {
                document.getElementById('modal-fac-tbody').innerHTML = `<tr><td colspan="7" style="color:var(--accent-red); padding:16px;">${data.error}</td></tr>`;
                return;
            }
            if(!data.length) {
                document.getElementById('modal-fac-tbody').innerHTML = `<tr><td colspan="7" style="color:var(--text-muted); padding:16px;">No se encontraron productos.</td></tr>`;
                return;
            }

            let html = '';
            data.forEach(p => {
                html += `
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                        <td style="padding:10px; font-family:var(--font-mono); color:var(--text-secondary);">${p.CodigoProducto || '-'}</td>
                        <td style="padding:10px; font-weight:600; color:var(--text-primary); max-width:250px; overflow:hidden; text-overflow:ellipsis;" title="${p.Descripcion}">${p.Descripcion || '-'}</td>
                        <td style="padding:10px; color:var(--text-secondary); max-width:150px; overflow:hidden; text-overflow:ellipsis;" title="${p.Proveedor}">${p.Proveedor || '-'}</td>
                        <td style="padding:10px; font-family:var(--font-mono); text-align:right;">${parseFloat(p.Precio).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
                        <td style="padding:10px; font-family:var(--font-mono); text-align:center;">${parseFloat(p.Cantidad).toLocaleString('es-VE')}</td>
                        <td style="padding:10px; color:#10b981; font-weight:600;">${p.Oferta || '-'}</td>
                        <td style="padding:10px; font-family:var(--font-mono); color:#10b981; text-align:center;">${parseFloat(p.Porcentaje).toLocaleString('es-VE')}%</td>
                    </tr>
                `;
            });
            document.getElementById('modal-fac-tbody').innerHTML = html;
        });
}

function cerrarModalFactura() {
    document.getElementById('modalFactura').classList.remove('active');
}
</script>

<?php include('../../includes/footer.php'); ?>
