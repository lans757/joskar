<?php
require_once __DIR__ . '/../../includes/require_admin_notipro.php';
require __DIR__ . '/../../includes/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Token CSRF inválido.'];
    } else {
        $targetUser = trim((string)($_POST['us_codigo'] ?? ''));
        $activo = (($_POST['activo'] ?? 'N') === 'S') ? 'S' : 'N';
        $remoto = (($_POST['remoto'] ?? 'N') === 'S') ? 'S' : 'N';

        if ($targetUser === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Usuario no especificado.'];
        } elseif (strcasecmp($targetUser, trim((string)$_SESSION['user_id'])) === 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No puedes modificar tu propio acceso de administrador.'];
        } else {
            try {
                $chk = $pdo->prepare("SELECT 1 FROM usuario WHERE us_codigo = ?");
                $chk->execute([$targetUser]);
                if (!$chk->fetchColumn()) {
                    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'El usuario no existe en Proteo.'];
                } else {
                    /* 
                    $stmt = $pdo->prepare(
                        "INSERT INTO notipro_acceso (us_codigo, activo, remoto, updated_by)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE activo = VALUES(activo),
                                                 remoto = VALUES(remoto),
                                                 updated_by = VALUES(updated_by)"
                    );
                    $stmt->execute([$targetUser, $activo, $remoto, $_SESSION['user_id']]);
                    */
                    $_SESSION['flash'] = ['type' => 'ok', 'msg' => "(Simulado) Permisos actualizados para {$targetUser}."];
                }
            } catch (Exception $e) {
                error_log('admin_notipro update: ' . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Error al actualizar permisos.'];
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$rows = $pdo->query(
    "SELECT u.us_codigo, u.us_nombre, u.supervisor,
            COALESCE(a.activo, 'S') AS activo,
            COALESCE(a.remoto, 'N') AS remoto,
            a.updated_at, a.updated_by
     FROM usuario u
     LEFT JOIN notipro_acceso a ON a.us_codigo = u.us_codigo
     ORDER BY u.us_codigo"
)->fetchAll();

$pageTitle = "Dashboard Droguería Joskar | Administración de Accesos";
$activePage = "admin_notipro";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>
<main class="main-content">
    <?php include("../../includes/navbar.php"); ?>
    <div class="content-wrapper">
        <div class="page-title">
            <h1>Administración de Accesos</h1>
            <p>Gestiona qué usuarios pueden ingresar al sistema y si tienen acceso remoto. Esta configuración no afecta a Proteo.</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'ok' ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $flash['type'] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 20px; overflow-x: auto;">
            <div class="filter-group" style="margin-bottom: 16px; max-width: 360px;">
                <label for="np-search">Buscar usuario</label>
                <input type="text" id="np-search" placeholder="Código o nombre..." autocomplete="off">
            </div>
            <table class="modern-table" id="np-users-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Supervisor</th>
                        <th>Acceso al Sistema</th>
                        <th>Acceso Remoto</th>
                        <th>Última actualización</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $isSelf = strcasecmp($r['us_codigo'], trim((string)$_SESSION['user_id'])) === 0; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['us_codigo']); ?></strong></td>
                        <td><?php echo htmlspecialchars(trim((string)$r['us_nombre'])); ?></td>
                        <td><?php echo $r['supervisor'] === 'S' ? 'Sí' : 'No'; ?></td>
                        <td>
                            <?php if ($r['activo'] === 'S'): ?>
                                <span class="premium-badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="premium-badge badge-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['remoto'] === 'S'): ?>
                                <span class="premium-badge badge-primary">Permitido</span>
                            <?php else: ?>
                                <span class="premium-badge badge-danger">Bloqueado</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85em;color:var(--text-muted);">
                            <?php echo $r['updated_at'] ? htmlspecialchars($r['updated_at']) . ($r['updated_by'] ? ' · ' . htmlspecialchars($r['updated_by']) : '') : '—'; ?>
                        </td>
                        <td>
                            <?php if ($isSelf): ?>
                                <em style="color:var(--text-muted);">(tú)</em>
                            <?php else: ?>
                                <form method="POST" style="display:flex; gap:12px; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="us_codigo" value="<?php echo htmlspecialchars($r['us_codigo']); ?>">
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <label style="font-size:.8em; display:flex; align-items:center; gap:4px; cursor:pointer;">
                                            <input type="checkbox" name="activo" value="S" <?php echo $r['activo']==='S'?'checked':''; ?>> Activo
                                        </label>
                                        <label style="font-size:.8em; display:flex; align-items:center; gap:4px; cursor:pointer;">
                                            <input type="checkbox" name="remoto" value="S" <?php echo $r['remoto']==='S'?'checked':''; ?>> Remoto
                                        </label>
                                    </div>
                                    <button type="submit" class="btn-neon" style="padding:6px 12px; font-size:.75em; min-width:80px; height:32px;">Guardar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
(function () {
    const input = document.getElementById('np-search');
    const table = document.getElementById('np-users-table');
    if (!input || !table) return;
    const rows = table.tBodies[0].rows;
    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        for (const row of rows) {
            const code = row.cells[0].textContent.toLowerCase();
            const name = row.cells[1].textContent.toLowerCase();
            row.style.display = (!q || code.includes(q) || name.includes(q)) ? '' : 'none';
        }
    });
})();
</script>
<?php include('../../includes/footer.php'); ?>
