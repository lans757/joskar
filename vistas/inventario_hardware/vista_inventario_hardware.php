<?php
require_once __DIR__ . '/../../includes/auth.php';
require_module_access('INVENTARIO_HARDWARE');
/**
 * ============================================================
 * INVENTARIO HARDWARE - NOTIPRO
 * Vista de equipos y activos
 * ============================================================
 */

require_once('../../includes/db.php');

$pageTitle   = "ProteoERP | Inventario Hardware";
$activePage  = "inventario_hardware";
$path_prefix = "../../";

include('../../includes/header.php');
include('../../includes/sidebar.php');

// --- Paginación ---
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Query Principal ---
try {
    $stmt_tot = $pdo->prepare("SELECT COUNT(*) as total_ops FROM invsis");
    $stmt_tot->execute();
    $kpis      = $stmt_tot->fetch(PDO::FETCH_ASSOC);
    $total_ops = $kpis['total_ops'] ?? 0;

    $stmt_det = $pdo->prepare("SELECT * FROM invsis ORDER BY estampa DESC LIMIT $limit OFFSET $offset");
    $stmt_det->execute();
    $equipos = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ($total_ops > 0) ? (int)ceil($total_ops / $limit) : 1;

} catch (PDOException $e) {
    die("Error de base de datos (MAIN): [" . $e->getCode() . "]: " . $e->getMessage());
}
?>


<main class="main-content">
<div class="content-wrapper">

    <!-- HEADER -->
    <div class="page-title">
        <h1><i class="fas fa-laptop-code"></i> Inventario Hardware</h1>
        <p>Control de activos y equipos asignados al personal</p>
    </div>

    <!-- DETALLE -->
    <div class="card table-card" style="margin-top: 28px;">
        <div class="t-header">
            <h3><i class="fas fa-list-ul"></i> Detalle de Equipos</h3>
            <div class="rng">
                Mostrando del <?php echo $offset+1; ?> al <?php echo min($offset+$limit, $total_ops); ?> de <strong><?php echo $total_ops; ?></strong>
                <button class="btn-neon btn-cyan" onclick="exportXls('table-inventario','Inventario_Hardware')" style="margin-left:15px; height: 38px; font-size: 0.75rem; padding: 0 15px;">
                    <i class="fas fa-file-excel"></i> Exportar XLS
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="table-inventario">
                <thead>
                    <tr>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Procesador</th>
                        <th>Memoria Ram</th>
                        <th>Disco Duro</th>
                        <th>Direccion MAC</th>
                        <th>Nombre de Equipo</th>
                        <th>Usuario de Equipo</th>
                        <th>Anydesk</th>
                        <th>Departamento</th>
                        <th>Comentario</th>
                        <th>Usuario</th>
                        <th>Estampa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipos)): ?>
                        <tr><td colspan="13" class="text-center" style="padding:48px; opacity:0.5;">No se encontraron registros.</td></tr>
                    <?php else: ?>
                        <?php foreach($equipos as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['marca'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['modelo'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['cpu'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['ram'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['hdd'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['mac'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['nombre'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['user_pc'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['anydesk'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['dpto'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['coment'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['usuario'] ?? ''); ?></td>
                            <td><?php echo !empty($r['estampa']) ? date('Y-m-d H:i:s', strtotime($r['estampa'])) : '—'; ?></td>
                        </tr>
                        <?php endforeach;?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div class="pagination-wrapper">
            <div class="page-info-bubble">
                Página <strong><?php echo $page; ?></strong> de <strong><?php echo $total_pages; ?></strong>
            </div>
            
            <div class="pager-container">
                <div class="pager-group">
                    <?php if($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>1]));?>" class="pager-btn" title="Primero"><i class="fas fa-angles-left"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1]));?>" class="pager-btn" title="Anterior"><i class="fas fa-angle-left"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angles-left"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angle-left"></i></button>
                    <?php endif; ?>

                    <span class="pcur" style="margin: 0 15px; font-weight:700; color:var(--primary);"><?php echo $page; ?></span>

                    <?php if($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1]));?>" class="pager-btn" title="Siguiente"><i class="fas fa-angle-right"></i></a>
                        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$total_pages]));?>" class="pager-btn" title="Último"><i class="fas fa-angles-right"></i></a>
                    <?php else: ?>
                        <button class="pager-btn disabled"><i class="fas fa-angle-right"></i></button>
                        <button class="pager-btn disabled"><i class="fas fa-angles-right"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="GET" class="page-input-group">
                <?php foreach($_GET as $k=>$v) if($k!=='page') echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; ?>
                <span style="opacity:0.6;">Ir a</span>
                <input type="number" name="page" class="page-input" min="1" max="<?php echo $total_pages;?>" value="<?php echo $page;?>">
                <button type="submit" class="pager-btn" style="background:rgba(255,255,255,0.05);"><i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </div>

</div><!-- /.content-wrapper -->
</main><!-- /.main-content -->

<!-- =============================================================== SCRIPTS -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
/* ---- Exportar Excel ---- */
function exportXls(id, name) {
    const t = document.getElementById(id);
    if (!t) return;
    const wb = XLSX.utils.table_to_book(t, { sheet: name });
    XLSX.writeFile(wb, name + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}
</script>

<?php include('../../includes/footer.php'); ?>
</body>
</html>
