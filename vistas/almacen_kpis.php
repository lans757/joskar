<?php
/**
 * ============================================================
 * INDICADORES CLAVE (KPI) - ALMACÉN PROTEOERP
 * Panel de control estratégico para gestión de inventario
 * ============================================================
 */

require_once('../includes/db.php');

// --- FILTROS ---
$f_ini   = $_GET['f_ini'] ?? date('Y-01-01');
$f_fin   = $_GET['f_fin'] ?? date('Y-m-d');
$codprov = $_GET['codprov'] ?? '';

// Construir condición de proveedor
$prov_cond   = !empty($codprov) ? "AND prvreg = '$codprov'" : "";
$prov_cond_v  = !empty($codprov) ? "AND v.prvreg = '$codprov'" : "";
$prov_cond_v2 = !empty($codprov) ? "AND v2.prvreg = '$codprov'" : "";
$prov_cond_i  = !empty($codprov) ? "AND i.prvreg = '$codprov'" : "";

// Fetch providers for select
$stmt_provs = $pdo->query("SELECT proveed, nombre FROM sprv ORDER BY nombre ASC");
$proveedores = $stmt_provs->fetchAll(PDO::FETCH_ASSOC);

// --- CÁLCULO DE KPIs ---

// 1. Resumen de Inventario General
$stmt_inv = $pdo->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(existen) as total_unidades,
        SUM(existen * pondd) as valor_total_usd,
        SUM(CASE WHEN existen <= 0 THEN 1 ELSE 0 END) as items_sin_stock,
        SUM(CASE WHEN existen > 0 THEN 1 ELSE 0 END) as items_con_stock
    FROM sinv
    WHERE 1=1 $prov_cond
");
$inv_summary = $stmt_inv->fetch(PDO::FETCH_ASSOC);

// 2. Compras del Periodo (Join con sinv para filtrar por proveedor si es necesario)
// Si solo queremos compras de ESE proveedor:
$stmt_compras = $pdo->prepare("
    SELECT SUM(ctotald) FROM scst 
    WHERE recep BETWEEN ? AND ? 
    " . (!empty($codprov) ? "AND proveed = ?" : "") . "
");
if (!empty($codprov)) {
    $stmt_compras->execute([$f_ini, $f_fin, $codprov]);
} else {
    $stmt_compras->execute([$f_ini, $f_fin]);
}
$compras_periodo = $stmt_compras->fetchColumn() ?: 0;

// 3. Ventas del Periodo (itpfac joins sinv para proveedor)
$stmt_ventas = $pdo->prepare("
    SELECT SUM(i.totad) 
    FROM itpfac i
    INNER JOIN sinv v ON i.codigoa = v.codigo
    WHERE i.fecha BETWEEN ? AND ? 
    $prov_cond_v
");
$stmt_ventas->execute([$f_ini, $f_fin]);
$ventas_periodo = $stmt_ventas->fetchColumn() ?: 0;

// 4. Concentración por Marca (Top 5 por Valor)
$stmt_marcas_val = $pdo->query("
    SELECT marca, SUM(existen * pondd) as valor 
    FROM sinv 
    WHERE existen > 0 $prov_cond
    GROUP BY marca 
    ORDER BY valor DESC 
    LIMIT 5
");
$marcas_val = $stmt_marcas_val->fetchAll(PDO::FETCH_ASSOC);

// Pre-calcular porcentajes para las etiquetas de la leyenda
$total_marcas_val = array_sum(array_column($marcas_val, 'valor'));
$marcas_val_labels = array_map(function($m) use ($total_marcas_val) {
    $p = ($total_marcas_val > 0) ? round(($m['valor'] / $total_marcas_val) * 100, 1) : 0;
    return $m['marca'] . " ($p%)";
}, $marcas_val);

// 5. Rotación de Marcas (Top 5 por Ventas del Periodo)
$stmt_marcas_rot = $pdo->prepare("
    SELECT v.marca, SUM(i.cana) as unidades
    FROM itpfac i
    INNER JOIN sinv v ON i.codigoa = v.codigo
    WHERE i.fecha BETWEEN ? AND ? $prov_cond_v
    GROUP BY v.marca
    ORDER BY unidades DESC
    LIMIT 5
");
$stmt_marcas_rot->execute([$f_ini, $f_fin]);
$marcas_rot = $stmt_marcas_rot->fetchAll(PDO::FETCH_ASSOC);

$stmt_evol = $pdo->query("
    SELECT 
        DATE_FORMAT(i.fecha, '%Y-%m') as mes,
        SUM(i.totad) as venta_usd
    FROM itpfac i
    INNER JOIN sinv v ON i.codigoa = v.codigo
    WHERE i.fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $prov_cond_v
    GROUP BY mes
    ORDER BY mes ASC
");
$evolucion = $stmt_evol->fetchAll(PDO::FETCH_ASSOC);

// 7. Cuadro de Mando por Marca (Desglose Detallado)
$stmt_mando = $pdo->prepare("
    SELECT 
        v.marca,
        SUM(CASE WHEN v.existen > 0 THEN 1 ELSE 0 END) as items_ok,
        SUM(CASE WHEN v.existen <= 0 THEN 1 ELSE 0 END) as items_out,
        SUM(v.existen * v.pondd) as valor_inv,
        COALESCE(s.unid, 0) as v_unid,
        COALESCE(s.monto, 0) as v_monto,
        MAX(v.prvreg) as default_prov
    FROM sinv v
    LEFT JOIN (
        SELECT v2.marca, SUM(i.cana) as unid, SUM(i.totad) as monto
        FROM itpfac i
        INNER JOIN sinv v2 ON i.codigoa = v2.codigo
        WHERE i.fecha BETWEEN :ini AND :fin $prov_cond_v2
        GROUP BY v2.marca
    ) s ON v.marca = s.marca
    WHERE 1=1 $prov_cond_v
    GROUP BY v.marca
    HAVING valor_inv > 0 OR v_monto > 0
    ORDER BY valor_inv DESC
    LIMIT 100
");
$stmt_mando->execute([':ini' => $f_ini, ':fin' => $f_fin]);
$cuadro_mando = $stmt_mando->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Indicadores KPI | Almacén";
$activePage = "almacen";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');
?>

<main class="main-content">
    <div class="content-wrapper">
        
        <!-- Navegación -->
        <nav class="module-nav">
            <a href="vista_almacen.php" class="nav-item">
                <i class="fas fa-home"></i> <span>Inicio</span>
            </a>
            <a href="almacen_articulos_comprados.php" class="nav-item">
                <i class="fas fa-list"></i> <span>Artículos Comprados</span>
            </a>
            <a href="almacen_compras_fecha.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> <span>Compras por Fecha</span>
            </a>
            <a href="almacen_top_vendidos.php" class="nav-item">
                <i class="fas fa-trophy"></i> <span>Top Vendidos</span>
            </a>
            <a href="almacen_kpis.php" class="nav-item active">
                <i class="fas fa-chart-line"></i> <span>Indicadores KPI</span>
            </a>
        </nav>

        <div class="page-title">
            <h1>Indicadores de Gestión (KPI)</h1>
            <p>Análisis estratégico de valor, rotación y salud del inventario</p>
        </div>

        <!-- Filtros -->
        <section class="card filters-card" style="margin-bottom: 30px;">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Desde</label>
                    <input type="date" name="f_ini" value="<?php echo $f_ini; ?>">
                </div>
                <div class="filter-group">
                    <label>Hasta</label>
                    <input type="date" name="f_fin" value="<?php echo $f_fin; ?>">
                </div>
                <div class="filter-group">
                    <label>Proveedor</label>
                    <select name="codprov" style="min-width: 200px;">
                        <option value="">TODOS LOS PROVEEDORES</option>
                        <?php foreach($proveedores as $p): ?>
                            <option value="<?php echo $p['proveed']; ?>" <?php echo $codprov == $p['proveed'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-neon btn-cyan"><i class="fas fa-search"></i> Filtrar KPI</button>
                </div>
            </form>
        </section>

        <!-- Métricas Principales -->
        <div class="metrics-grid">
            <a href="vista_almacen.php?alerta=all<?php echo $codprov ? '&codprov='.$codprov : ''; ?>" class="card metric-card" style="text-decoration: none;">
                <div class="metric-icon" style="color:var(--primary);"><i class="fas fa-warehouse"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Valor del Inventario</span>
                    <p class="metric-value">$ <?php echo number_format($inv_summary['valor_total_usd'], 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format($inv_summary['items_con_stock'], 0, ',', '.'); ?> artículos activos</span>
                </div>
            </a>
            <div class="card metric-card">
                <div class="metric-icon" style="color:var(--accent-green);"><i class="fas fa-shopping-cart"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Inversión (Periodo)</span>
                    <p class="metric-value">$ <?php echo number_format($compras_periodo, 2, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo date('d/m/Y', strtotime($f_ini)); ?> al <?php echo date('d/m/Y', strtotime($f_fin)); ?></span>
                </div>
            </div>
            <div class="card metric-card">
                <div class="metric-icon" style="color:var(--accent-cyan);"><i class="fas fa-cash-register"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Ventas (Periodo)</span>
                    <p class="metric-value">$ <?php echo number_format($ventas_periodo, 2, ',', '.'); ?></p>
                    <span class="metric-trend" style="color:<?php echo $ventas_periodo > $compras_periodo ? 'var(--accent-green)' : 'var(--accent-orange)'; ?>">
                        Ratio V/I: <?php echo $compras_periodo > 0 ? number_format($ventas_periodo / $compras_periodo, 2) : 'N/A'; ?>
                    </span>
                </div>
            </div>
            <a href="vista_almacen.php?alerta=out<?php echo $codprov ? '&codprov='.$codprov : ''; ?>" class="card metric-card" style="text-decoration: none;">
                <div class="metric-icon" style="color:var(--accent-red);"><i class="fas fa-box-open"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Productos sin Stock</span>
                    <p class="metric-value" style="color:var(--accent-red);"><?php echo number_format($inv_summary['items_sin_stock'], 0, ',', '.'); ?></p>
                    <span class="metric-trend"><?php echo number_format(($inv_summary['items_sin_stock'] / ($inv_summary['total_items'] ?: 1)) * 100, 1); ?>% de ruptura</span>
                </div>
            </a>
        </div>

        <!-- Sección de Gráficos -->
        <div class="chart-section" style="margin-top:30px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            
            <!-- Inversión por Marca -->
            <div class="card chart-card">
                <div class="t-header">
                    <h2><i class="fas fa-chart-pie"></i> Valor Invertido por Marca (Top 5)</h2>
                </div>
                <div class="chart-container" style="height:350px;">
                    <canvas id="chartBrandsValue"></canvas>
                </div>
            </div>

            <!-- Salud del Inventario -->
            <div class="card chart-card">
                <div class="t-header">
                    <h2><i class="fas fa-heartbeat"></i> Estado de Disponibilidad</h2>
                </div>
                <div class="chart-container" style="height:350px;">
                    <canvas id="chartStockHealth"></canvas>
                </div>
            </div>

            <!-- Evolución Mensual -->
            <div class="card chart-card" style="grid-column: span 2;">
                <div class="t-header">
                    <h2><i class="fas fa-chart-line"></i> Evolución de Ventas (Últimos 6 Meses $)</h2>
                </div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="chartEvolucion"></canvas>
                </div>
            </div>

            <!-- Rotación Mensual -->
            <div class="card chart-card" style="grid-column: span 2;">
                <div class="t-header">
                    <h2><i class="fas fa-sync-alt"></i> Rotación por Marcas (Unidades Periodo)</h2>
                </div>
                <div class="chart-container" style="height:400px;">
                    <canvas id="chartRotation"></canvas>
                </div>
            </div>
        </div>

        <!-- Cuadro de Mando Detallado -->
        <div class="card table-card" style="margin-top:30px;">
            <div class="t-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fas fa-table"></i> Desglose Gerencial por Marca (Cifras en $)</h2>
                <div style="font-size: 0.8rem; opacity: 0.7;">* Filtrado por periodo seleccionado</div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>MARCA / LÍNEA</th>
                            <th class="text-center">ITEMS OK</th>
                            <th class="text-center">SIN STOCK</th>
                            <th class="text-right">VALOR INV. ($)</th>
                            <th class="text-center">UNID. VEND.</th>
                            <th class="text-right">VENTAS ($)</th>
                            <th class="text-center">ROTACIÓN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuadro_mando as $m): 
                            $rotacion = ($m['valor_inv'] > 0) ? ($m['v_monto'] / $m['valor_inv']) : 0;
                            // Limitar rotación visual para evitar porcentajes imposibles si hay poca inversión
                            $rot_display = $rotacion > 1 ? 1 : $rotacion;
                            $color_rot = $rot_display > 0.5 ? '#00ffc3' : ($rot_display > 0.1 ? '#00b4ff' : '#ffcc00');
                            
                            $link_prov = $codprov ? $codprov : $m['default_prov'];
                        ?>
                            <tr>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?php echo htmlspecialchars($m['marca'] ?: 'SIN MARCA'); ?></td>
                                <td class="text-center">
                                    <a href="vista_almacen.php?alerta=all&marca=<?php echo urlencode($m['marca'] ?: ''); ?>&codprov=<?php echo urlencode($link_prov ?: ''); ?>" 
                                       style="color:var(--primary); text-decoration:none; font-weight:700;">
                                        <?php echo number_format($m['items_ok'], 0, ',', '.'); ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <a href="vista_almacen.php?alerta=out&marca=<?php echo urlencode($m['marca'] ?: ''); ?>&codprov=<?php echo urlencode($link_prov ?: ''); ?>" 
                                       style="color:<?php echo $m['items_out'] > 0 ? 'var(--accent-red)' : 'var(--text-muted)'; ?>; text-decoration:none; font-weight:700;">
                                        <?php echo number_format($m['items_out'], 0, ',', '.'); ?>
                                    </a>
                                </td>
                                <td class="text-right" style="font-weight:700;">$ <?php echo number_format($m['valor_inv'], 2, ',', '.'); ?></td>
                                <td class="text-center"><?php echo number_format($m['v_unid'], 0, ',', '.'); ?></td>
                                <td class="text-right" style="color:var(--accent-cyan);">$ <?php echo number_format($m['v_monto'], 2, ',', '.'); ?></td>
                                <td class="text-center">
                                    <span class="badge" style="background:<?php echo $color_rot; ?>; color:#000; font-weight:800; padding:2px 8px; border-radius:4px; font-size:0.7rem;">
                                        <?php echo number_format($rot_display * 100, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Gráfico de Valor por Marca
    new Chart(document.getElementById('chartBrandsValue'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($marcas_val_labels); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($marcas_val, 'valor')); ?>,
                backgroundColor: ['#00b4ff', '#00ffc3', '#ffcc00', '#ff3e3e', '#ae00ff'],
                borderColor: '#11192a',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { color: '#fff' } },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            const value = Number(c.parsed);
                            const dataset = c.dataset.data;
                            const total = dataset.reduce((acc, curr) => acc + Number(curr), 0);
                            const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${c.label}: $ ${value.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });

    // 2. Gráfico de Salud (Stock vs No Stock)
    new Chart(document.getElementById('chartStockHealth'), {
        type: 'doughnut',
        data: {
            labels: ['Con Stock', 'Sin Stock'],
            datasets: [{
                data: [<?php echo $inv_summary['items_con_stock']; ?>, <?php echo $inv_summary['items_sin_stock']; ?>],
                backgroundColor: ['#00ffc3', '#ff3e3e'],
                borderColor: '#11192a',
                borderWidth: 4,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#fff' } }
            }
        }
    });

    // 3. Gráfico de Rotación (Barras)
    new Chart(document.getElementById('chartRotation'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($marcas_rot, 'marca')); ?>,
            datasets: [{
                label: 'Unidades Vendidas (Mes)',
                data: <?php echo json_encode(array_column($marcas_rot, 'unidades')); ?>,
                backgroundColor: 'rgba(0, 180, 255, 0.4)',
                borderColor: '#00b4ff',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#fff' } },
                x: { grid: { display: false }, ticks: { color: '#fff' } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 4. Gráfico de Evolución (Línea)
    new Chart(document.getElementById('chartEvolucion'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($evolucion, 'mes')); ?>,
            datasets: [{
                label: 'Ventas USD',
                data: <?php echo json_encode(array_column($evolucion, 'venta_usd')); ?>,
                fill: true,
                backgroundColor: 'rgba(0, 255, 195, 0.1)',
                borderColor: '#00ffc3',
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#00ffc3'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#fff' } },
                x: { grid: { display: false }, ticks: { color: '#fff' } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            return 'Ventas: $ ' + c.parsed.y.toLocaleString('es-VE', {minimumFractionDigits:2});
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include('../includes/footer.php'); ?>
