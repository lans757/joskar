<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('SEGURIDAD')) {
    header('Location: ../../acceso_denegado.php');
    exit;
}

$pageTitle = "ProteoERP | Seguridad y Roles";
$activePage = "seguridad";
$path_prefix = "../../";
$json_path = __DIR__ . '/../../includes/accesos.json';

// Handle AJAX Request to Save JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_roles') {
    header('Content-Type: application/json');
    try {
        if (!file_exists($json_path)) {
            throw new Exception("El archivo accesos.json no existe.");
        }
        $new_data = json_decode($_POST['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Datos JSON inválidos recibidos.");
        }
        
        // Write file
        $result = @file_put_contents($json_path, json_encode($new_data, JSON_PRETTY_PRINT));
        if ($result === false) {
            throw new Exception("Error al escribir el archivo accesos.json. Revisa permisos.");
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

include('../../includes/header.php');
include('../../includes/sidebar.php');

// Read current JSON data
$accesos_data = [];
if (file_exists($json_path)) {
    $content = file_get_contents($json_path);
    $accesos_data = json_decode($content, true) ?: [];
}

$modulos = array_keys($accesos_data);
?>

<main class="main-content">
<div class="content-wrapper">
    <div class="page-title">
        <h1><i class="fas fa-shield-alt" style="color: #ef4444;"></i> Panel de Seguridad</h1>
        <p>Administración de permisos y acceso a módulos mediante JSON.</p>
    </div>

    <div class="card" style="margin-top: 20px;">
        <div class="t-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3><i class="fas fa-users-cog"></i> Módulos y Usuarios Asignados</h3>
            <button class="btn-neon btn-green" onclick="saveAccesos()">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
        
        <div class="table-container">
            <table id="roles-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">MÓDULO (ÁREA)</th>
                        <th>USUARIOS ASIGNADOS</th>
                        <th class="c" style="width: 15%;">ACCIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accesos_data as $modulo => $usuarios): ?>
                    <tr data-modulo="<?php echo htmlspecialchars($modulo); ?>">
                        <td style="font-weight: bold; color: var(--text-main);">
                            <?php echo htmlspecialchars($modulo); ?>
                        </td>
                        <td>
                            <div class="users-list" id="users-<?php echo htmlspecialchars($modulo); ?>" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php foreach ($usuarios as $usr): ?>
                                    <span class="pill-banco" style="background: rgba(37,99,235,0.1); color: #60a5fa; border: 1px solid rgba(37,99,235,0.2); display: inline-flex; align-items: center; gap: 6px;">
                                        <?php echo htmlspecialchars($usr); ?>
                                        <i class="fas fa-times delete-usr" onclick="removeUser(this)" style="cursor: pointer; color: #ef4444;" title="Quitar usuario"></i>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="c">
                            <button class="btn-neon btn-cyan" onclick="promptAddUser('<?php echo htmlspecialchars($modulo); ?>')" style="padding: 6px 12px; font-size: 0.8rem;">
                                <i class="fas fa-plus"></i> Añadir
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>

<script>
// Manejar la eliminación visual de un usuario
function removeUser(element) {
    if (confirm("¿Estás seguro de quitar el acceso a este usuario?")) {
        element.parentElement.remove();
    }
}

// Manejar la adición visual de un usuario
function promptAddUser(modulo) {
    const usr = prompt("Ingrese el código del usuario para dar acceso al módulo " + modulo + ":");
    if (usr && usr.trim() !== "") {
        const uppercaseUsr = usr.trim().toUpperCase();
        
        // Verificar si ya existe en la lista
        const container = document.getElementById("users-" + modulo);
        let exists = false;
        container.querySelectorAll('span').forEach(el => {
            if (el.textContent.trim().toUpperCase() === uppercaseUsr) {
                exists = true;
            }
        });
        
        if (exists) {
            alert("El usuario ya tiene acceso a este módulo.");
            return;
        }
        
        // Crear el nuevo elemento
        const span = document.createElement('span');
        span.className = "pill-banco";
        span.style.cssText = "background: rgba(37,99,235,0.1); color: #60a5fa; border: 1px solid rgba(37,99,235,0.2); display: inline-flex; align-items: center; gap: 6px;";
        span.innerHTML = uppercaseUsr + ' <i class="fas fa-times delete-usr" onclick="removeUser(this)" style="cursor: pointer; color: #ef4444;" title="Quitar usuario"></i>';
        
        container.appendChild(span);
    }
}

// Guardar los datos en el servidor usando AJAX
async function saveAccesos() {
    // Recolectar datos de la tabla
    const tableRows = document.querySelectorAll("#roles-table tbody tr");
    const accesosData = {};
    
    tableRows.forEach(row => {
        const modulo = row.getAttribute('data-modulo');
        const userElements = row.querySelectorAll('.users-list span');
        const users = Array.from(userElements).map(el => el.textContent.trim().toUpperCase());
        accesosData[modulo] = users;
    });
    
    const confirmSave = confirm("Se actualizarán los accesos de inmediato. ¿Deseas continuar?");
    if (!confirmSave) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'save_roles');
        formData.append('data', JSON.stringify(accesosData));
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            alert("Accesos guardados exitosamente.");
        } else {
            alert("Error al guardar: " + result.error);
        }
    } catch (error) {
        alert("Error de red al guardar los accesos.");
        console.error(error);
    }
}
</script>

<?php include('../../includes/footer.php'); ?>
