<?php
$pageTitle  = "ProteoERP Dashboard | Almacén";
$activePage = "almacen";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');
?>

<main class="main-content">
    <div class="content-wrapper">
        
        <!-- Navegación de Módulo -->
        <nav class="module-nav">
            <a href="almacen_articulos_comprados.php" class="nav-item">
                <i class="fas fa-list"></i>
                <span>Artículos Comprados</span>
            </a>
            <a href="almacen_compras_fecha.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Compras por Fecha</span>
            </a>
            <a href="almacen_top_vendidos.php" class="nav-item">
                <i class="fas fa-trophy"></i>
                <span>Top Vendidos</span>
            </a>
        </nav>


        <!-- Título -->
        <div class="page-title">
            <h1>
                Inventario Total de
                <span id="total-inventory-count" style="color:var(--accent-yellow);">—</span>
                Productos
            </h1>
            <p>Monitoreo inteligente de stock y rotación de productos · Almacén Principal</p>
        </div>

        <!-- Métricas -->
        <div class="metrics-grid">
            <div class="card metric-card alert">
                <div class="metric-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Stock Crítico</span>
                    <p class="metric-value" id="count-critical">—</p>
                </div>
            </div>
            <div class="card metric-card warning">
                <div class="metric-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Bajo Mínimo</span>
                    <p class="metric-value" id="count-low">—</p>
                </div>
            </div>
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-check-circle"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Stock Óptimo</span>
                    <p class="metric-value" id="count-ok">—</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <section class="card filters-card">
            <div class="filters-header">
                <i class="fas fa-filter"></i>
                <h2>Filtros de Inventario</h2>
            </div>
            <div class="filters-row">

                <div class="filter-group">
                    <label>Sede / Almacén</label>
                    <select id="filter-almacen">
                        <option value="0001" selected>ALMACÉN PRINCIPAL</option>
                        <!-- Agrega más sedes aquí si aplica -->
                    </select>
                </div>

                <div class="filter-group">
                    <label>Estado de Alerta</label>
                    <select id="filter-alerta">
                        <option value="all">TODOS LOS PRODUCTOS</option>
                        <option value="critical">ESTADO CRÍTICO</option>
                        <option value="low">BAJO MÍNIMO</option>
                        <option value="ok">ESTADO ÓPTIMO</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Búsqueda Rápida</label>
                    <input type="text" id="filter-search" placeholder="Código o descripción...">
                </div>

                <div class="btn-group">
                    <button class="btn-neon btn-cyan" id="btn-refresh" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                    <button class="btn-neon btn-green" id="btn-export" onclick="exportData()">
                        <i class="fas fa-file-excel"></i> Exportar
                    </button>
                </div>

            </div>
        </section>

        <!-- Tabla -->
        <section class="card table-card">
            <div class="table-header">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('alertas')">
                        <i class="fas fa-bell" style="margin-right:6px;"></i>Alertas de Stock
                    </button>
                    <button class="tab-btn" onclick="switchTab('movimientos')">
                        <i class="fas fa-chart-line" style="margin-right:6px;"></i>Rotación Comercial
                    </button>
                </div>
            </div>

            <!-- Tab: Alertas de Stock -->
            <div class="table-container" id="alertas-tab">
                <table id="table-alertas">
                    <thead>
                        <tr>
                            <th data-sort="codigo" data-type="text">
                                CÓDIGO <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="descrip" data-type="text">
                                PRODUCTO <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="existen" data-type="number" class="text-right">
                                EXISTENCIA <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="ventau" data-type="number" class="text-right">
                                VDP (PROM) <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="min" data-type="number" class="text-right">
                                MÍNIMO <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="diasinv" data-type="number" class="text-center">
                                ESTADO <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th class="text-right">SUGERIDO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center" style="padding:50px;color:var(--text-muted);">
                                <i class="fas fa-spinner fa-spin" style="font-size:1.8rem;"></i><br><br>
                                Cargando datos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tab: Rotación Comercial -->
            <div class="table-container hidden" id="movimientos-tab">
                <table id="table-movimientos">
                    <thead>
                        <tr>
                            <th data-sort="grupo" data-type="text">
                                GRUPO <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="codigo" data-type="text">
                                CÓDIGO <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="descrip" data-type="text">
                                DESCRIPCIÓN <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="ventau" data-type="number" class="text-right">
                                VENTAS (30D) <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="existen" data-type="number" class="text-right">
                                STOCK <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="diasinv" data-type="number" class="text-center">
                                DÍAS INV. <span class="sort-icon"><i class="fas fa-sort"></i></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center" style="padding:50px;color:var(--text-muted);">
                                <i class="fas fa-spinner fa-spin" style="font-size:1.8rem;"></i><br><br>
                                Cargando datos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="pagination-wrapper">
                <span id="pagination-info" style="font-size:.82rem;color:var(--text-muted);font-weight:500;"></span>

                <div id="pagination-controls" class="pager-container"></div>

                <div class="per-page-group">
                    <span style="font-size:.82rem;color:var(--text-muted);">Registros:</span>
                    <select id="items-per-page" class="per-page-select">
                        <option value="10"  selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
            </div>

        </section>
    </div>
</main>

<?php
$extraScripts = "<script src='../dashboard.js'></script>";
include('../includes/footer.php');
?>
