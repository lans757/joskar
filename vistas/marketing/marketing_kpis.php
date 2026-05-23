<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('MARKETING')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}

$pageTitle = "ProteoERP | Histórico de KPIs";
$activePage = "marketing";
$path_prefix = "../../";

$extraStyles = "<link rel='stylesheet' href='{$path_prefix}assets/css/marketing.css'>";

include('../../includes/header.php');
include('../../includes/sidebar.php');

// Fetch history
$stmt = $pdo->query("SELECT * FROM indicadores_marketing ORDER BY periodo DESC");
$historico = $stmt->fetchAll();

$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function formatPeriod($dateString, $meses) {
    $time = strtotime($dateString);
    $mes = date('n', $time) - 1;
    $ano = date('Y', $time);
    return $meses[$mes] . ' ' . $ano;
}

function calculateER($interacciones, $alcance) {
    if (!$alcance) return '0.00%';
    return number_format(($interacciones / $alcance) * 100, 2) . '%';
}

$f_ini = $_GET['f_ini'] ?? date('Y-m-01');
$f_fin = $_GET['f_fin'] ?? date('Y-m-d');

// Consulta para Gráfico: Top Ofertas Aplicadas
$stmt_top_ofertas = $pdo->prepare("
    SELECT 
        d.nombre AS Oferta,
        COUNT(DISTINCT d.numa) AS TotalFacturas,
        SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(d.last_insert, '$.cana')) AS DECIMAL(10,2)) * CAST(JSON_UNQUOTE(JSON_EXTRACT(d.last_insert, '$.preca')) AS DECIMAL(10,2))) AS MontoTotalVendido
    FROM itpfacdescu d
    WHERE d.fecha >= :ini AND d.fecha <= :fin
    GROUP BY d.nombre
    ORDER BY TotalFacturas DESC
    LIMIT 10
");
$stmt_top_ofertas->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$datos_top = $stmt_top_ofertas->fetchAll(PDO::FETCH_ASSOC);

$topOfertaNombres = [];
$topOfertaFacturas = [];
$topOfertaMontos = [];

foreach($datos_top as $row) {
    $topOfertaNombres[] = $row['Oferta'];
    $topOfertaFacturas[] = (int)$row['TotalFacturas'];
    $topOfertaMontos[] = (float)$row['MontoTotalVendido'];
}

?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main-content">
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="vista_marketing.php" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Dashboard</span>
        </a>
        <a href="marketing_kpis.php" class="nav-item active">
            <i class="fas fa-table"></i>
            <span>Histórico KPIs</span>
        </a>
        <a href="carga_marketing.php" class="nav-item">
            <i class="fas fa-upload"></i>
            <span>Carga de Datos</span>
        </a>
        <a href="vista_descuentos.php" class="nav-item">
            <i class="fas fa-tags"></i>
            <span>Ofertas Aplicadas</span>
        </a>
    </nav>

    <div class="animate-fadeIn">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:32px;">
            <div>
                <h1 style="font-size: 2.4rem; font-weight: 800; letter-spacing: -1.5px; margin-bottom: 6px; background: linear-gradient(135deg, var(--text-primary) 30%, var(--text-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    Panel de KPIs (RRSS y Ventas)
                </h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500;">
                    Monitoreo de redes sociales e indicadores de ofertas aplicadas.
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

        <!-- Gráficos Analíticos de Descuentos -->
        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; margin-bottom: 32px;">
            <div class="glass-panel" style="padding: 24px; display:flex; flex-direction:column;">
                <div style="font-size:0.85rem; font-weight: 700; color:var(--text-muted); letter-spacing:0.1em; margin-bottom:16px; text-transform: uppercase;">
                    Top Ofertas Aplicadas (Por Facturas)
                </div>
                <div style="flex:1; position:relative; min-height: 300px;">
                    <canvas id="donutOfertas"></canvas>
                </div>
            </div>
            
            <div class="glass-panel" style="padding: 24px; display:flex; flex-direction:column;">
                <div style="font-size:0.85rem; font-weight: 700; color:var(--text-muted); letter-spacing:0.1em; margin-bottom:16px; text-transform: uppercase;">
                    Monto Vendido por Oferta ($/Bs)
                </div>
                <div style="flex:1; position:relative; min-height: 300px;">
                    <canvas id="barMontoOfertas"></canvas>
                </div>
            </div>
        </div>

        <div class="glass-panel animate-fadeUp" style="padding: 24px; animation-delay: 0.1s;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border);">
                            <th style="padding: 16px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Período</th>
                            <th style="padding: 16px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Seguidores</th>
                            <th style="padding: 16px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Nuevos Seg.</th>
                            <th style="padding: 16px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Alcance</th>
                            <th style="padding: 16px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Interacciones</th>
                            <th style="padding: 16px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Engagement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historico)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">No hay datos históricos disponibles.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($historico as $row): ?>
                                <tr style="border-bottom: 1px solid var(--border); transition: background 0.2s;">
                                    <td style="padding: 16px; font-weight: 700; color: var(--social-color);"><?= formatPeriod($row['periodo'], $meses) ?></td>
                                    <td style="padding: 16px; font-family: var(--font-mono);"><?= number_format($row['seguidores_total'], 0, ',', '.') ?></td>
                                    <td style="padding: 16px; font-family: var(--font-mono); color: #10b981;">+<?= number_format($row['nuevos_seguidores'], 0, ',', '.') ?></td>
                                    <td style="padding: 16px; font-family: var(--font-mono);"><?= number_format($row['alcance'], 0, ',', '.') ?></td>
                                    <td style="padding: 16px; font-family: var(--font-mono);"><?= number_format($row['interacciones'], 0, ',', '.') ?></td>
                                    <td style="padding: 16px; font-family: var(--font-mono); font-weight: 700;"><?= calculateER($row['interacciones'], $row['alcance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>

<script>
// Inicializar Gráfico de Dona: Top Ofertas
const ctxDonut = document.getElementById('donutOfertas');
if(ctxDonut) {
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($topOfertaNombres); ?>,
            datasets: [{
                data: <?php echo json_encode($topOfertaFacturas); ?>,
                backgroundColor: [
                    'rgba(99, 102, 241, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(20, 184, 166, 0.8)',
                    'rgba(236, 72, 153, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(168, 85, 247, 0.8)',
                    'rgba(244, 63, 94, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { color: 'rgba(255,255,255,0.7)', font: { family: 'ui-sans-serif, system-ui, sans-serif', size: 11 } } }
            },
            cutout: '75%'
        }
    });
}

// Inicializar Gráfico de Barras: Montos de Oferta
const ctxBarMonto = document.getElementById('barMontoOfertas');
if(ctxBarMonto) {
    new Chart(ctxBarMonto, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($topOfertaNombres); ?>,
            datasets: [{
                label: 'Monto Vendido',
                data: <?php echo json_encode($topOfertaMontos); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let val = context.raw;
                            return val.toLocaleString('es-VE', { minimumFractionDigits: 2 }) + ' $';
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: function(value) { return value.toLocaleString('es-VE'); } } },
                y: { grid: { display: false } }
            }
        }
    });
}
</script>

<?php include('../../includes/footer.php'); ?>
