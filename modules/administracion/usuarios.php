<?php
/**
 * Gestión de Usuarios — Solo supervisores
 *
 * Requiere columna us_activo en tabla usuario:
 *   ALTER TABLE usuario ADD COLUMN us_activo TINYINT(1) NOT NULL DEFAULT 1;
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['logged_in'])) {
    if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
    } else {
        header('Location: ../../index.php');
    }
    exit;
}

if (empty($_SESSION['is_supervisor'])) {
    if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
    } else {
        header('Location: ../../dashboard.php');
    }
    exit;
}

require_once '../../includes/db.php';

// ── Asegurar que la columna us_activo existe ──────────────────────────────
try {
    $pdo->query("SELECT us_activo FROM usuario LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE usuario ADD COLUMN us_activo TINYINT(1) NOT NULL DEFAULT 1");
}

// ── AJAX: Toggle estado ───────────────────────────────────────────────────
if (isset($_POST['ajax']) && $_POST['ajax'] === 'toggle') {
    header('Content-Type: application/json');

    // Validar CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_usuarios']) || !hash_equals($_SESSION['csrf_usuarios'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }

    $target = $_POST['us_codigo'] ?? '';
    if (!$target) {
        http_response_code(400);
        echo json_encode(['error' => 'Usuario requerido']);
        exit;
    }

    // No permitir auto-deshabilitarse
    if ($target === $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'No puedes deshabilitarte a ti mismo']);
        exit;
    }

    try {
        // Obtener estado actual
        $stmt = $pdo->prepare("SELECT us_activo FROM usuario WHERE us_codigo = ?");
        $stmt->execute([$target]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        $nuevo = $row['us_activo'] ? 0 : 1;
        $upd = $pdo->prepare("UPDATE usuario SET us_activo = ? WHERE us_codigo = ?");
        $upd->execute([$nuevo, $target]);

        echo json_encode(['ok' => true, 'activo' => $nuevo]);
    } catch (PDOException $e) {
        error_log('usuarios.php toggle error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno']);
    }
    exit;
}

// ── AJAX: Listar usuarios ─────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'lista') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query(
            "SELECT us_codigo, us_nombre, supervisor, us_activo FROM usuario ORDER BY us_nombre ASC"
        );
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log('usuarios.php lista error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno']);
    }
    exit;
}

// ── Generar CSRF token para acciones de este módulo ───────────────────────
if (empty($_SESSION['csrf_usuarios'])) {
    $_SESSION['csrf_usuarios'] = bin2hex(random_bytes(32));
}

// ── Página HTML ───────────────────────────────────────────────────────────
$pageTitle  = "ProteoERP | Gestión de Usuarios";
$activePage = "usuarios";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');
?>
<main class="main-content">
    <?php include("../../includes/navbar.php"); ?>
    <div class="content-wrapper">

        <div class="page-title">
            <h1><i class="fas fa-users-cog" style="color:var(--primary);margin-right:10px;"></i>Gestión de Usuarios</h1>
            <p>Habilita o deshabilita el acceso de usuarios al sistema.</p>
        </div>

        <!-- Buscador -->
        <div class="card" style="padding:16px 20px; margin-bottom:16px; display:flex; gap:12px; align-items:center;">
            <i class="fas fa-search" style="color:var(--text-muted);"></i>
            <input type="text" id="search-input" placeholder="Buscar por nombre o código..."
                   style="background:transparent;border:none;outline:none;color:var(--text-main);font-size:0.95rem;width:100%;">
        </div>

        <!-- Tabla -->
        <div class="card" style="padding:0; overflow:hidden;">
            <table class="data-table" id="tabla-usuarios" style="width:100%;">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th style="text-align:center;">Acción</th>
                    </tr>
                </thead>
                <tbody id="tbody-usuarios">
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">
                        <i class="fas fa-spinner fa-spin"></i> Cargando...
                    </td></tr>
                </tbody>
            </table>
        </div>

    </div>
</main>

<script>
const CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_usuarios'], ENT_QUOTES, 'UTF-8'); ?>';
const CURRENT_USER = '<?php echo htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8'); ?>';

let allUsers = [];

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderTabla(users) {
    const tbody = document.getElementById('tbody-usuarios');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">Sin resultados</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(u => {
        const esSupervisor = u.supervisor === 'S';
        const esActivo     = parseInt(u.us_activo) === 1;
        const esSelf       = u.us_codigo === CURRENT_USER;

        const badgeRol = esSupervisor
            ? `<span class="badge" style="background:rgba(168,85,247,0.15);color:#a855f7;border:1px solid rgba(168,85,247,0.3);">Supervisor</span>`
            : `<span class="badge" style="background:rgba(56,189,248,0.1);color:var(--primary);border:1px solid rgba(56,189,248,0.2);">Usuario</span>`;

        const badgeEstado = esActivo
            ? `<span class="badge" style="background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> Activo</span>`
            : `<span class="badge" style="background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.25);"><i class="fas fa-ban"></i> Inactivo</span>`;

        const btnToggle = esSelf
            ? `<button class="btn-toggle" disabled title="No puedes modificarte a ti mismo"
                   style="opacity:0.35;cursor:not-allowed;padding:6px 14px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text-muted);font-size:0.8rem;">
                   <i class="fas fa-lock"></i> Tú
               </button>`
            : `<button class="btn-toggle" data-codigo="${escHtml(u.us_codigo)}" data-activo="${u.us_activo}"
                   onclick="toggleUsuario(this)"
                   style="padding:6px 14px;border-radius:6px;border:1px solid ${esActivo ? 'rgba(239,68,68,0.4)' : 'rgba(16,185,129,0.4)'};
                          background:${esActivo ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'};
                          color:${esActivo ? '#ef4444' : '#10b981'};cursor:pointer;font-size:0.8rem;transition:var(--transition);">
                   <i class="fas ${esActivo ? 'fa-user-slash' : 'fa-user-check'}"></i>
                   ${esActivo ? 'Deshabilitar' : 'Habilitar'}
               </button>`;

        return `<tr data-codigo="${escHtml(u.us_codigo)}">
            <td><code style="color:var(--primary);font-size:0.85rem;">${escHtml(u.us_codigo)}</code></td>
            <td>${escHtml(u.us_nombre)}</td>
            <td>${badgeRol}</td>
            <td class="celda-estado">${badgeEstado}</td>
            <td style="text-align:center;" class="celda-accion">${btnToggle}</td>
        </tr>`;
    }).join('');
}

async function cargarUsuarios() {
    try {
        const res  = await fetch('usuarios.php?ajax=lista');
        allUsers   = await res.json();
        renderTabla(allUsers);
    } catch {
        document.getElementById('tbody-usuarios').innerHTML =
            '<tr><td colspan="5" style="text-align:center;padding:30px;color:#ef4444;">Error al cargar usuarios</td></tr>';
    }
}

async function toggleUsuario(btn) {
    const codigo = btn.dataset.codigo;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const form = new FormData();
    form.append('ajax', 'toggle');
    form.append('csrf_token', CSRF_TOKEN);
    form.append('us_codigo', codigo);

    try {
        const res  = await fetch('usuarios.php', { method: 'POST', body: form });
        const data = await res.json();

        if (!res.ok || !data.ok) {
            alert(data.error || 'Error al actualizar');
            btn.disabled = false;
            btn.innerHTML = btn.dataset.activo == 1
                ? '<i class="fas fa-user-slash"></i> Deshabilitar'
                : '<i class="fas fa-user-check"></i> Habilitar';
            return;
        }

        // Actualizar estado local y re-renderizar fila
        const user = allUsers.find(u => u.us_codigo === codigo);
        if (user) user.us_activo = data.activo;
        renderTabla(filtrar());

    } catch {
        alert('Error de conexión');
        btn.disabled = false;
    }
}

function filtrar() {
    const q = document.getElementById('search-input').value.toLowerCase();
    return q
        ? allUsers.filter(u =>
            u.us_codigo.toLowerCase().includes(q) ||
            u.us_nombre.toLowerCase().includes(q))
        : allUsers;
}

document.getElementById('search-input').addEventListener('input', () => renderTabla(filtrar()));

cargarUsuarios();
</script>

<?php include('../../includes/footer.php'); ?>
