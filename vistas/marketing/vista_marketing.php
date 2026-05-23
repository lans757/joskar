<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('MARKETING')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}

$pageTitle = "ProteoERP | Social Media";
$activePage = "marketing";
$path_prefix = "../../";

// Inject custom marketing CSS
$extraStyles = "<link rel='stylesheet' href='{$path_prefix}assets/css/marketing.css'>";

include('../../includes/header.php');
include('../../includes/sidebar.php');

$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main-content">
<div class="content-wrapper">

    <!-- Navegación de Módulo -->
    <nav class="module-nav">
        <a href="vista_marketing.php" class="nav-item active">
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
        <a href="vista_descuentos.php" class="nav-item">
            <i class="fas fa-tags"></i>
            <span>Ofertas Aplicadas</span>
        </a>
    </nav>

    <div class="animate-fadeIn">
        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:32px;">
            <div>
                <h1 style="font-size: 2.4rem; font-weight: 800; letter-spacing: -1.5px; margin-bottom: 6px; background: linear-gradient(135deg, var(--text-primary) 30%, var(--text-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    Social Media
                </h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500;">
                    Métricas de impacto y crecimiento
                </p>
            </div>
            
            <div style="display:flex; gap:12px; align-items:center;">
                <!-- Date Selector -->
                <div style="display: flex; align-items: center; background: var(--bg-elevated); border-radius: 14px; padding: 4px; border: 1px solid var(--border);">
                    <button id="btn-prev-month" style="width:36px; height:36px; border-radius:10px; background:transparent; border:none; color:var(--text-primary); font-size:1.1rem; cursor:pointer;">‹</button>
                    <span id="current-month-display" style="font-family:var(--font-mono); font-size:0.85rem; color:var(--text-secondary); min-width:120px; text-align:center; font-weight: 600;">
                        Cargando...
                    </span>
                    <button id="btn-next-month" style="width:36px; height:36px; border-radius:10px; background:transparent; border:none; color:var(--text-primary); font-size:1.1rem; cursor:pointer;">›</button>
                </div>
            </div>
        </div>

        <div id="loading-spinner" style="display: flex; justify-content: center; padding: 80px;">
            <div class="animate-spin" style="width: 40px; height: 40px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.1); border-top-color: var(--social-color);"></div>
        </div>

        <div id="no-data-msg" style="display:none; text-align:center; padding:80px 24px; border-radius:24px; background:var(--glass-bg); backdrop-filter:blur(32px); border:1px solid var(--border);">
            <div style="font-size:48px; margin-bottom:16px;">📱</div>
            <p style="color:rgba(255,255,255,0.5); font-size: 1.1rem;">No hay datos para este mes</p>
        </div>

        <div id="dashboard-content" style="display:none;">
            <!-- Main KPIs -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px;">
                
                <!-- Seguidores -->
                <div class="glass-panel animate-fadeUp" style="padding: 28px; animation-delay: 0s;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--social-color), transparent);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <span style="font-size: 1.8rem;">👥</span>
                        <span id="kpi-seg-delta"></span>
                    </div>
                    <div class="kpi-value" id="kpi-seg-val">0</div>
                    <div class="kpi-title">Seguidores</div>
                </div>

                <!-- Nuevos Seguidores -->
                <div class="glass-panel animate-fadeUp" style="padding: 28px; animation-delay: 0.1s;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #10b981, transparent);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <span style="font-size: 1.8rem;">📈</span>
                        <span id="kpi-nuevos-delta"></span>
                    </div>
                    <div class="kpi-value" id="kpi-nuevos-val">0</div>
                    <div class="kpi-title">Nuevos Seg.</div>
                </div>

                <!-- Alcance -->
                <div class="glass-panel animate-fadeUp" style="padding: 28px; animation-delay: 0.2s;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #3b82f6, transparent);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <span style="font-size: 1.8rem;">🌐</span>
                        <span id="kpi-alcance-delta"></span>
                    </div>
                    <div class="kpi-value" id="kpi-alcance-val">0</div>
                    <div class="kpi-title">Alcance</div>
                </div>

                <!-- Interacciones -->
                <div class="glass-panel animate-fadeUp" style="padding: 28px; animation-delay: 0.3s;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #f0436a, transparent);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <span style="font-size: 1.8rem;">❤️</span>
                        <span id="kpi-int-delta"></span>
                    </div>
                    <div class="kpi-value" id="kpi-int-val">0</div>
                    <div class="kpi-title">Interacciones</div>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 32px;">
                <!-- Bar Chart -->
                <div class="glass-panel animate-fadeUp" style="padding: 30px; animation-delay: 0.4s;">
                    <div style="font-size:0.85rem; font-weight: 700; color:var(--text-muted); letter-spacing:0.1em; margin-bottom:24px; text-transform: uppercase;">Rendimiento vs Mes Anterior</div>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>

                <!-- Donut Chart -->
                <div class="glass-panel animate-fadeUp" style="padding: 30px; animation-delay: 0.5s; display: flex; flex-direction: column;">
                    <div style="font-size:0.85rem; font-weight: 700; color:var(--text-muted); letter-spacing:0.1em; margin-bottom:20px; text-transform: uppercase;">Contenido (Videos)</div>
                    <div style="flex: 1; position: relative;">
                        <canvas id="donutChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Actions Row -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                
                <!-- Quick Actions -->
                <div class="animate-fadeUp" style="animation-delay: 0.7s; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <button id="btn-videos" style="border: 1px solid var(--border); background: var(--bg-elevated); border-radius: 24px; padding: 20px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span style="font-size:28px;">🎬</span>
                        <span style="font-size:0.9rem; font-weight: 700; color: var(--text-primary);">Ver Vídeos</span>
                        <span id="videos-count" style="font-size:0.75rem; color: var(--text-muted); font-weight: 600;">0 registros</span>
                    </button>

                    <button id="btn-campanas" style="border: 1px solid var(--border); background: var(--bg-elevated); border-radius: 24px; padding: 20px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span style="font-size:28px;">📣</span>
                        <span style="font-size:0.9rem; font-weight: 700; color: var(--text-primary);">Ver Campañas</span>
                        <span id="campanas-count" style="font-size:0.75rem; color: var(--text-muted); font-weight: 600;">0 activas</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="modal-videos" class="marketing-modal-overlay">
    <div class="marketing-modal animate-fadeUp">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--social-color), transparent);"></div>
        <div class="marketing-modal-header">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Vídeos del Mes</h2>
            <button class="close-modal" style="background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary); cursor: pointer; font-size: 1.2rem; width: 32px; height: 32px; border-radius: 50%;">✕</button>
        </div>
        <div class="marketing-modal-body" id="modal-videos-body" style="display:flex; flex-direction:column; gap:12px;"></div>
    </div>
</div>

<div id="modal-campanas" class="marketing-modal-overlay">
    <div class="marketing-modal animate-fadeUp">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #fbbf24, transparent);"></div>
        <div class="marketing-modal-header">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Campañas Publicitarias</h2>
            <button class="close-modal" style="background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary); cursor: pointer; font-size: 1.2rem; width: 32px; height: 32px; border-radius: 50%;">✕</button>
        </div>
        <div class="marketing-modal-body" id="modal-campanas-body" style="display:flex; flex-direction:column; gap:20px;"></div>
    </div>
</div>

</main>

<script src="<?php echo $path_prefix; ?>assets/js/marketing.js"></script>

<?php include('../../includes/footer.php'); ?>
