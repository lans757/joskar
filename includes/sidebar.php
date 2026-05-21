<?php
if (!isset($path_prefix)) $path_prefix = "";
if (!isset($activePage)) $activePage = "dashboard";
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Droguería Joskar C.A.</h2>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo $path_prefix; ?>dashboard.php" class="<?php echo ($activePage == 'dashboard' ? 'active' : ''); ?>">
                <i class="fas fa-home"></i> Inicio Dashboard
            </a>
        </li>
        <hr style="border: none; border-top: 1px solid var(--border-light); margin: 10px 0;">
        <?php if (has_module_access('ALMACEN')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/almacen/vista_almacen.php" class="<?php echo ($activePage == 'almacen' ? 'active' : ''); ?>">
                <i class="fas fa-warehouse"></i> Indicadores Almacén
            </a>
        </li>
        <?php endif; ?>
        <?php if (has_module_access('TELEVENTAS')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/televentas/vista_televentas.php" class="<?php echo ($activePage == 'televentas' ? 'active' : ''); ?>">
                <i class="fas fa-headset"></i> Indicadores Televentas
            </a>
        </li>
        <?php endif; ?>
        <?php if (has_module_access('COMPRAS')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/compras/vista_compras.php" class="<?php echo ($activePage == 'compras' ? 'active' : ''); ?>">
                <i class="fas fa-shopping-cart"></i> Indicadores Compras
            </a>
        </li>
        <?php endif; ?>
        <?php if (has_module_access('ADMINISTRACION')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/administracion/vista_administracion.php" class="<?php echo ($activePage == 'administracion' ? 'active' : ''); ?>">
                <i class="fas fa-building"></i> Indicadores Administración
            </a>
        </li>
        <?php endif; ?>
        <?php if (has_module_access('COBRANZAS')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/cobranzas/vista_cobranzas.php" class="<?php echo ($activePage == 'cobranzas' ? 'active' : ''); ?>">
                <i class="fas fa-hand-holding-usd"></i> Indicadores Cobranzas
            </a>
        </li>
        <?php endif; ?>
        <?php if (has_module_access('GERENCIA')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/gerencia/vista_gerencia.php" class="<?php echo ($activePage == 'gerencia' ? 'active' : ''); ?>">
                <i class="fas fa-chart-line"></i> Indicadores Gerencia
            </a>
        </li>
        <?php endif; ?>
        <?php if (has_module_access('INVENTARIO_HARDWARE')): ?>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/inventario_hardware/vista_inventario_hardware.php" class="<?php echo ($activePage == 'inventario_hardware' ? 'active' : ''); ?>">
                <i class="fas fa-laptop-code"></i> Inventario Hardware
            </a>
        </li>
        <?php endif; ?>
        
        <li class="sidebar-footer" style="margin-top: auto;">
            <a href="<?php echo $path_prefix; ?>logout.php" style="color: var(--accent-red);">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
            <button type="button" class="theme-toggle" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                <i class="fas fa-sun icon-sun"></i>
                <i class="fas fa-moon icon-moon"></i>
            </button>
        </li>
    </ul>
</aside>
