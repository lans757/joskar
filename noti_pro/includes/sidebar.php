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
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/vista_almacen.php" class="<?php echo ($activePage == 'almacen' ? 'active' : ''); ?>">
                <i class="fas fa-warehouse"></i> Indicadores Almacén
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/vista_televentas.php" class="<?php echo ($activePage == 'televentas' ? 'active' : ''); ?>">
                <i class="fas fa-headset"></i> Indicadores Televentas
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/vista_compras.php" class="<?php echo ($activePage == 'compras' ? 'active' : ''); ?>">
                <i class="fas fa-shopping-cart"></i> Indicadores Compras
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/vista_administracion.php" class="<?php echo ($activePage == 'administracion' ? 'active' : ''); ?>">
                <i class="fas fa-building"></i> Indicadores Administración
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/vista_cobranzas.php" class="<?php echo ($activePage == 'cobranzas' ? 'active' : ''); ?>">
                <i class="fas fa-hand-holding-usd"></i> Indicadores Cobranzas
            </a>
        </li>
        <li>
            <a href="<?php echo $path_prefix; ?>vistas/vista_gerencia.php" class="<?php echo ($activePage == 'gerencia' ? 'active' : ''); ?>">
                <i class="fas fa-chart-line"></i> Indicadores Gerencia
            </a>
        </li>
        
        <!-- Add a logout option if it makes sense -->
        <li style="margin-top: auto;">
            <a href="<?php echo $path_prefix; ?>logout.php" style="color: var(--accent-red);">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </li>
    </ul>
</aside>
