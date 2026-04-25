<?php
if (!isset($path_prefix)) $path_prefix = "";
if (!isset($activePage)) $activePage = "dashboard";
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Dashboard Droguería Joskar</h2>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo $path_prefix; ?>dashboard.php" class="<?php echo ($activePage == 'dashboard' ? 'active' : ''); ?>">
                <i class="fas fa-home"></i> Inicio Dashboard
            </a>
        </li>
        <hr style="border: none; border-top: 1px solid var(--border-light); margin: 10px 0;">
        <li>
            <a href="<?php echo $path_prefix; ?>modules/almacen/vista_almacen.php" class="<?php echo ($activePage == 'almacen' ? 'active' : ''); ?>">
                <i class="fas fa-warehouse"></i> Indicadores Almacén
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>modules/televentas/vista_televentas.php" class="<?php echo ($activePage == 'televentas' ? 'active' : ''); ?>">
                <i class="fas fa-headset"></i> Indicadores Televentas
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>modules/compras/vista_compras.php" class="<?php echo ($activePage == 'compras' ? 'active' : ''); ?>">
                <i class="fas fa-shopping-cart"></i> Indicadores Compras
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>modules/administracion/vista_administracion.php" class="<?php echo ($activePage == 'administracion' ? 'active' : ''); ?>">
                <i class="fas fa-building"></i> Indicadores Administración
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>modules/cobranzas/vista_cobranzas.php" class="<?php echo ($activePage == 'cobranzas' ? 'active' : ''); ?>">
                <i class="fas fa-hand-holding-usd"></i> Indicadores Cobranzas
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>modules/gerencia/vista_gerencia.php" class="<?php echo ($activePage == 'gerencia' ? 'active' : ''); ?>">
                <i class="fas fa-chart-line"></i> Indicadores Gerencia
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>modules/marketing/vista_marketing.php" class="<?php echo ($activePage == 'marketing' ? 'active' : ''); ?>">
                <i class="fas fa-tags"></i> Monitor de Marketing
            </a>
        </li>
        
        <?php if (!empty($_SESSION['is_supervisor'])): ?>
        <hr style="border: none; border-top: 1px solid var(--border-light); margin: 10px 0;">
        <li>
            <a href="<?php echo $path_prefix; ?>modules/administracion/usuarios.php" class="<?php echo ($activePage == 'usuarios' ? 'active' : ''); ?>">
                <i class="fas fa-users-cog"></i> Gestión de Usuarios
            </a>
        </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && strcasecmp(trim((string)$_SESSION['user_id']), 'PRUEBAS') === 0): ?>
        <hr style="border: none; border-top: 1px solid var(--border-light); margin: 10px 0;">
        <li>
            <a href="<?php echo $path_prefix; ?>modules/admin_notipro/vista_admin_notipro.php" class="<?php echo ($activePage == 'admin_notipro' ? 'active' : ''); ?>">
                <i class="fas fa-user-shield"></i> Admin Acceso
            </a>
        </li>
        <?php endif; ?>

        <li style="margin-top: auto;">
            <a href="<?php echo $path_prefix; ?>logout.php" style="color: var(--accent-red);">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </li>
    </ul>
</aside>
