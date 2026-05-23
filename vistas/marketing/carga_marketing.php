<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('MARKETING')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}

$pageTitle = "ProteoERP | Carga de Marketing";
$activePage = "marketing";
$path_prefix = "../../";

$extraStyles = "<link rel='stylesheet' href='{$path_prefix}assets/css/marketing.css'>";

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodo = $_POST['periodo'] ?? '';
    $seguidores_total = (int)($_POST['seguidores_total'] ?? 0);
    $nuevos_seguidores = (int)($_POST['nuevos_seguidores'] ?? 0);
    $alcance = (int)($_POST['alcance'] ?? 0);
    $interacciones = (int)($_POST['interacciones'] ?? 0);
    
    // Arrays for videos and campanas are submitted as JSON strings for now
    // In a full implementation, these would be dynamic form fields
    $videos = $_POST['videos'] ?? '[]';
    $campanas = $_POST['campanas'] ?? '[]';

    // Validate JSON
    if (json_decode($videos) === null) $videos = '[]';
    if (json_decode($campanas) === null) $campanas = '[]';

    if (!empty($periodo)) {
        // Ensure it's the first of the month
        $periodo = date('Y-m-01', strtotime($periodo));
        
        $stmt = $pdo->prepare("
            INSERT INTO indicadores_marketing (periodo, seguidores_total, nuevos_seguidores, alcance, interacciones, videos, campanas)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                seguidores_total = VALUES(seguidores_total),
                nuevos_seguidores = VALUES(nuevos_seguidores),
                alcance = VALUES(alcance),
                interacciones = VALUES(interacciones),
                videos = VALUES(videos),
                campanas = VALUES(campanas)
        ");
        
        try {
            $stmt->execute([$periodo, $seguidores_total, $nuevos_seguidores, $alcance, $interacciones, $videos, $campanas]);
            $msg = "Datos guardados exitosamente para el periodo: $periodo";
            $msgType = "success";
        } catch (PDOException $e) {
            $msg = "Error al guardar: " . $e->getMessage();
            $msgType = "error";
        }
    } else {
        $msg = "El periodo es obligatorio.";
        $msgType = "error";
    }
}

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>

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
        <a href="carga_marketing.php" class="nav-item active">
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
                    Carga de Indicadores
                </h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500;">
                    Ingresa o actualiza manualmente los datos mensuales de Marketing.
                </p>
            </div>
        </div>

        <?php if($msg): ?>
            <div style="padding: 16px; margin-bottom: 24px; border-radius: 12px; font-weight: 600; <?php echo $msgType === 'success' ? 'background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);' : 'background: rgba(240, 67, 106, 0.1); color: #f0436a; border: 1px solid rgba(240, 67, 106, 0.2);'; ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="glass-panel animate-fadeUp" style="padding: 30px; animation-delay: 0.1s; max-width: 800px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <div style="grid-column: span 2;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Período (Mes/Año)</label>
                    <input type="month" name="periodo" required style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono);">
                    <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">Se guardará como el primer día del mes seleccionado.</small>
                </div>

                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Total Seguidores</label>
                    <input type="number" name="seguidores_total" placeholder="Ej: 5000" style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono);">
                </div>

                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Nuevos Seguidores</label>
                    <input type="number" name="nuevos_seguidores" placeholder="Ej: 150" style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono);">
                </div>

                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Alcance Total</label>
                    <input type="number" name="alcance" placeholder="Ej: 20000" style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono);">
                </div>

                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Interacciones Totales</label>
                    <input type="number" name="interacciones" placeholder="Ej: 1500" style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono);">
                </div>
                
                <div style="grid-column: span 2;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Videos (Formato JSON)</label>
                    <textarea name="videos" rows="4" style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono); resize:vertical;">[]</textarea>
                    <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">Ejemplo: [{"red_social":"instagram","etiqueta":"Reel Promo","cantidad":1}]</small>
                </div>
                
                <div style="grid-column: span 2;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-secondary); margin-bottom:8px;">Campañas (Formato JSON)</label>
                    <textarea name="campanas" rows="4" style="width:100%; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-primary); font-family:var(--font-mono); resize:vertical;">[]</textarea>
                    <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">Ejemplo: [{"nombre":"Campaña Verano","presupuesto":500,"alcance":10000,"clics":500,"conversiones":50}]</small>
                </div>
            </div>

            <div style="text-align:right;">
                <button type="submit" style="padding:14px 32px; background:var(--social-color); border:none; border-radius:12px; color:#fff; font-weight:700; font-size:1rem; cursor:pointer; box-shadow: 0 4px 15px rgba(var(--social-color-rgb), 0.3);">
                    Guardar Datos
                </button>
            </div>
        </form>
    </div>
</div>
</main>

<?php include('../../includes/footer.php'); ?>
