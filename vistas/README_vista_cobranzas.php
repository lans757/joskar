<?php
/**
 * ============================================================
 *  MÓDULO: GESTIÓN DE COBRANZAS — vista_cobranzas.php
 *  Sistema: ProteoERP / noti_pro
 *  Reporte base: AGESTION / SMOV
 *
 *  ARQUITECTURA:
 *    - header.php  → <html>, <head>, <body>, .app-container
 *    - sidebar.php → <aside class="sidebar">
 *    - [este archivo] → <main class="main-content">
 *    - footer.php  → cierre de .app-container, scripts globales
 *
 *  ESTILOS: heredan de /noti_pro/style.css.
 *    Las clases locales (prefijadas cob-*) se definen en el
 *    bloque <style> embebido más abajo para no contaminar
 *    el CSS global hasta que el módulo esté consolidado.
 * ============================================================
 */

/* ────────────────────────────────────────────────────────────
 *  1. VARIABLES DE PÁGINA
 *  Se leen por header.php ($pageTitle) y sidebar.php ($activePage).
 *  $path_prefix ajusta las rutas relativas porque este archivo
 *  vive en /vistas/, un nivel más profundo que la raíz.
 * ──────────────────────────────────────────────────────────── */
$pageTitle   = "ProteoERP | Gestión de Cobranzas";
$activePage  = "cobranzas";
$path_prefix = "../";

include('../includes/header.php');
include('../includes/sidebar.php');

// 1.1 CONEXIÓN A BASE DE DATOS REAL (PARA MÉTODOS DE PAGO)
require_once '../includes/db.php';

try {
    // Obtener métodos de pago reales de la tabla 'banc'
    $stmt_banc = $pdo->query("SELECT codbanc, banco FROM banc WHERE activo = 'S' ORDER BY banco");
    $metodos_pago_db = $stmt_banc->fetchAll(PDO::FETCH_ASSOC);
    
    // Extraer solo los nombres para mantener compatibilidad con el sistema de filtrado actual
    // o usar los objetos completos para mayor control.
    $lista_bancos_real = array_column($metodos_pago_db, 'banco');
} catch (Exception $e) {
    // Fallback si la base de datos no está disponible
    $lista_bancos_real = ['BANESCO', 'MERCANTIL', 'PROVINCIAL', 'BDV', 'BNC'];
}

/* ════════════════════════════════════════════════════════════
 *  2. CAPA DE DATOS — MOCK DATA
 *  ─────────────────────────────────────────────────────────
 *  POR QUÉ ARRAY ASOCIATIVO:
 *    Replicar fielmente la estructura del reporte SMOV permite
 *    conectar más tarde con PDO sin cambiar el HTML/JS. Solo
 *    se sustituye la asignación de $reporte_gestiones.
 *
 *  MIGRACIÓN A PDO (INSTRUCCIONES):
 *  ──────────────────────────────────────────────────────────
 *  Reemplaza el bloque completo de $reporte_gestiones con:
 *
 *    require_once '../includes/db.php';  // tu archivo PDO
 *    $stmt = $pdo->prepare("
 *        SELECT
 *            g.id_gestion,
 *            g.fecha_gestion,
 *            g.vendedor_id,
 *            v.nombre  AS responsable_nombre,
 *            c.nombre  AS cliente_nombre,
 *            g.banco,
 *            g.estatus
 *        FROM gestiones g
 *        JOIN vendedores v ON g.vendedor_id = v.id
 *        JOIN clientes   c ON g.cliente_id  = c.id
 *        WHERE g.fecha_gestion BETWEEN :fecha_ini AND :fecha_fin
 *        ORDER BY v.nombre, g.fecha_gestion DESC
 *    ");
 *    $stmt->execute([
 *        ':fecha_ini' => $_GET['fecha_ini'] ?? date('Y-m-01'),
 *        ':fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-d'),
 *    ]);
 *    $reporte_gestiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
 *
 *  NOTA: El ORDER BY v.nombre es CRÍTICO para que el algoritmo
 *  de agrupación funcione correctamente (asume datos ordenados).
 * ════════════════════════════════════════════════════════════ */
$reporte_gestiones = [
    // Datos ajustados con métodos de pago reales de la tabla banc
    ['id_gestion' => 1001, 'fecha_gestion' => '2026-03-25', 'vendedor_id' => 'FL01', 'responsable_nombre' => 'FELIX LEON',     'cliente_nombre' => 'FARMACIA SAN JOSE',         'banco' => 'BANESCO 3400',       'estatus' => 'A NC EN B'],
    ['id_gestion' => 1002, 'fecha_gestion' => '2026-03-25', 'vendedor_id' => 'FL01', 'responsable_nombre' => 'FELIX LEON',     'cliente_nombre' => 'DROGUERÍA DEL CENTRO',      'banco' => 'PROVINCIAL 6874',    'estatus' => 'GESTION'],
    ['id_gestion' => 1003, 'fecha_gestion' => '2026-03-24', 'vendedor_id' => 'FL01', 'responsable_nombre' => 'FELIX LEON',     'cliente_nombre' => 'FARMACIA LA VICTORIA',      'banco' => 'BANESCO 3400',       'estatus' => 'A NC EN B'],
    ['id_gestion' => 1004, 'fecha_gestion' => '2026-03-24', 'vendedor_id' => 'FL01', 'responsable_nombre' => 'FELIX LEON',     'cliente_nombre' => 'FARMACIA SANTA ROSA',       'banco' => 'VENEZUELA 0448',     'estatus' => 'ALERTA'],
    ['id_gestion' => 1005, 'fecha_gestion' => '2026-03-20', 'vendedor_id' => 'FL01', 'responsable_nombre' => 'FELIX LEON',     'cliente_nombre' => 'FARMACIA CENTRAL',          'banco' => 'CAJA EFECTIVO DIVISA','estatus' => 'PENDIENTE'],
    ['id_gestion' => 1006, 'fecha_gestion' => '2026-03-18', 'vendedor_id' => 'FL01', 'responsable_nombre' => 'FELIX LEON',     'cliente_nombre' => 'FARMACIA EL PARAISO',       'banco' => 'PROVINCIAL 6874',    'estatus' => 'A NC EN B'],

    ['id_gestion' => 1007, 'fecha_gestion' => '2026-03-27', 'vendedor_id' => 'AK02', 'responsable_nombre' => 'ANA KARINA',    'cliente_nombre' => 'FARMACIA NUEVA ERA',         'banco' => 'BNC 3588',           'estatus' => 'A NC EN B'],
    ['id_gestion' => 1008, 'fecha_gestion' => '2026-03-26', 'vendedor_id' => 'AK02', 'responsable_nombre' => 'ANA KARINA',    'cliente_nombre' => 'FARMACIA LA PAZ',            'banco' => 'BANESCO 3400',       'estatus' => 'ALERTA'],
    ['id_gestion' => 1009, 'fecha_gestion' => '2026-03-26', 'vendedor_id' => 'AK02', 'responsable_nombre' => 'ANA KARINA',    'cliente_nombre' => 'BOTICA SAN FRANCISCO',       'banco' => 'CAJA MULTIPAGO $',    'estatus' => 'PENDIENTE'],
    ['id_gestion' => 1010, 'fecha_gestion' => '2026-03-25', 'vendedor_id' => 'AK02', 'responsable_nombre' => 'ANA KARINA',    'cliente_nombre' => 'FARMACIA VIRGEN DEL VALLE',  'banco' => 'VENEZUELA 0448',     'estatus' => 'A NC EN B'],
    ['id_gestion' => 1011, 'fecha_gestion' => '2026-03-22', 'vendedor_id' => 'AK02', 'responsable_nombre' => 'ANA KARINA',    'cliente_nombre' => 'DISTRIBUIDORA FARMA',        'banco' => 'PROVINCIAL 6874',    'estatus' => 'GESTION'],

    ['id_gestion' => 1012, 'fecha_gestion' => '2026-03-27', 'vendedor_id' => 'CM03', 'responsable_nombre' => 'CARLOS MENDEZ',  'cliente_nombre' => 'FARMACIA DON AUGUSTO',      'banco' => 'ZELLE KARIN',        'estatus' => 'A NC EN B'],
    ['id_gestion' => 1013, 'fecha_gestion' => '2026-03-26', 'vendedor_id' => 'CM03', 'responsable_nombre' => 'CARLOS MENDEZ',  'cliente_nombre' => 'FARMACIA BELLO CAMPO',      'banco' => 'BANCAMIGA 0076',     'estatus' => 'ALERTA'],
    ['id_gestion' => 1014, 'fecha_gestion' => '2026-03-24', 'vendedor_id' => 'CM03', 'responsable_nombre' => 'CARLOS MENDEZ',  'cliente_nombre' => 'FARMACIA SIMÓN BOLÍVAR',    'banco' => 'BANESCO 3400',       'estatus' => 'PENDIENTE'],
    ['id_gestion' => 1015, 'fecha_gestion' => '2026-03-20', 'vendedor_id' => 'CM03', 'responsable_nombre' => 'CARLOS MENDEZ',  'cliente_nombre' => 'BOTICA DEL SUR',            'banco' => 'VENEZUELA 0448',     'estatus' => 'A NC EN B'],

    ['id_gestion' => 1016, 'fecha_gestion' => '2026-03-27', 'vendedor_id' => 'LM04', 'responsable_nombre' => 'LUISA MARTINEZ', 'cliente_nombre' => 'FARMACIA EL TIGRE',        'banco' => 'BANESCO 3400',       'estatus' => 'GESTION'],
    ['id_gestion' => 1017, 'fecha_gestion' => '2026-03-26', 'vendedor_id' => 'LM04', 'responsable_nombre' => 'LUISA MARTINEZ', 'cliente_nombre' => 'FARMACIA BOLIVARIANA',     'banco' => 'PROVINCIAL 6874',    'estatus' => 'A NC EN B'],
    ['id_gestion' => 1018, 'fecha_gestion' => '2026-03-24', 'vendedor_id' => 'LM04', 'responsable_nombre' => 'LUISA MARTINEZ', 'cliente_nombre' => 'FARMACIA SAN MARTIN',      'banco' => 'CAJA MULTIPAGO $',    'estatus' => 'ALERTA'],
    ['id_gestion' => 1019, 'fecha_gestion' => '2026-03-21', 'vendedor_id' => 'LM04', 'responsable_nombre' => 'LUISA MARTINEZ', 'cliente_nombre' => 'FARMACIA GUARENAS',        'banco' => 'PROVINCIAL 6874',    'estatus' => 'A NC EN B'],
    ['id_gestion' => 1020, 'fecha_gestion' => '2026-03-18', 'vendedor_id' => 'LM04', 'responsable_nombre' => 'LUISA MARTINEZ', 'cliente_nombre' => 'BOTICA LA CANDELARIA',    'banco' => 'BNC 3588',           'estatus' => 'PENDIENTE'],
];

/* ════════════════════════════════════════════════════════════
 *  3. ALGORITMO DE AGRUPACIÓN POR RESPONSABLE
 *  ─────────────────────────────────────────────────────────
 *  POR QUÉ ESTE ENFOQUE (single-pass):
 *    En lugar de dos iteraciones (primero agrupar en un array,
 *    luego renderizar), se usa un solo bucle con una variable
 *    "centinela" ($ultimo_responsable). Esto es O(n) y respeta
 *    el orden ya impuesto por la consulta SQL (ORDER BY responsable).
 *
 *  El algoritmo:
 *    1. Para cada fila, compara responsable_nombre con $ultimo.
 *    2. Si cambia → emite una fila de cabecera de grupo y
 *       acumula el contador del grupo anterior.
 *    3. Al final siempre hay un grupo abierto; se cierra con el
 *       conteo acumulado.
 *
 *  Para el filtro de JS, formateamos los datos como JSON
 *  para que el lado cliente pueda re-filtrar sin recargar.
 * ════════════════════════════════════════════════════════════ */

// Calcular métricas de resumen para las tarjetas KPI
$total_gestiones  = count($reporte_gestiones);
$total_alertas    = count(array_filter($reporte_gestiones, fn($r) => $r['estatus'] === 'ALERTA'));
$total_pendientes = count(array_filter($reporte_gestiones, fn($r) => $r['estatus'] === 'PENDIENTE'));
$total_normalizados = count(array_filter($reporte_gestiones, fn($r) => $r['estatus'] === 'A NC EN B'));

// Obtener lista única de responsables para el filtro <select>
$responsables = array_unique(array_column($reporte_gestiones, 'responsable_nombre'));
sort($responsables);

// Obtener lista única de bancos de la base de datos (tabla banc)
// Se prioriza la información real del sistema.
$bancos = !empty($lista_bancos_real) ? $lista_bancos_real : array_unique(array_column($reporte_gestiones, 'banco'));
sort($bancos);

// Serializar a JSON para inyectar en el bloque <script>.
// POR QUÉ JSON_HEX_TAG y NO htmlspecialchars():
//   - htmlspecialchars() convierte " en &quot;, rompiendo la sintaxis JSON en JS.
//   - JSON_HEX_TAG convierte < y > en \u003C / \u003E, que es la única
//     amenaza real dentro de un <script> (cierre prematuro </script>).
//   - NUNCA aplicar htmlspecialchars() a JSON destinado a un <script>.
$json_gestiones = json_encode(
    $reporte_gestiones,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
);

// ─── Función auxiliar: Retorna clase CSS + icono según estatus ───────────────
// Se define como función nombrada (no closure) para reutilizarla
// tanto en el renderizado PHP como para EXPORTAR a CSV en el futuro.
// POR QUÉ match: más idiomático en PHP 8+, exhaustivo y sin fall-through.
function getStatusBadge(string $estatus): array {
    return match(true) {
        $estatus === 'A NC EN B'  => ['class' => 'badge badge-ok',       'icon' => 'fa-check-circle',      'label' => $estatus],
        $estatus === 'GESTION'    => ['class' => 'badge badge-info',     'icon' => 'fa-phone-alt',          'label' => $estatus],
        $estatus === 'ALERTA'     => ['class' => 'badge badge-critical', 'icon' => 'fa-exclamation-circle', 'label' => $estatus],
        $estatus === 'PENDIENTE'  => ['class' => 'badge badge-low',      'icon' => 'fa-clock',              'label' => $estatus],
        default                  => ['class' => 'badge badge-muted',    'icon' => 'fa-question-circle',    'label' => $estatus],
    };
}
?>

<!-- ═══════════════════════════════════════════════════════════════
     4. ESTILOS LOCALES DEL MÓDULO COBRANZAS
     ────────────────────────────────────────────────────────────
     POR QUÉ EMBEBIDOS:
       Mantener estos estilos dentro del archivo mientras el
       módulo está en prototipo facilita iteraciones rápidas.
       Una vez consolidado, migrar al final de style.css.
     ════════════════════════════════════════════════════════════ -->
<style>
    /* ── Badge adicional para estado "GESTION" (azul primario) ── */
    .badge-info {
        background: rgba(0, 180, 255, 0.12);
        color: var(--primary);
        border: 1px solid rgba(0, 180, 255, 0.25);
    }

    /* ── Badge gris para estados desconocidos ─────────────────── */
    .badge-muted {
        background: rgba(148, 163, 184, 0.12);
        color: var(--text-muted);
        border: 1px solid rgba(148, 163, 184, 0.2);
    }

    /* ── Fila de separador de grupo ───────────────────────────── */
    /*    Un fondo sutil con borde izquierdo marca visualmente     */
    /*    cada cambio de responsable. El colSpan = 6 columnas.     */
    tr.cob-group-header td {
        padding: 10px 24px;
        background: rgba(0, 180, 255, 0.07);
        border-top: 2px solid rgba(0, 180, 255, 0.2);
        border-bottom: 1px solid rgba(0, 180, 255, 0.1);
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--primary);
        cursor: default;
    }

    tr.cob-group-header td .cob-group-count {
        font-weight: 500;
        color: var(--text-muted);
        margin-left: 10px;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
    }

    /* Quitar efecto hover/zebra en filas de cabecera de grupo */
    tr.cob-group-header:hover {
        background: rgba(0, 180, 255, 0.07) !important;
        box-shadow: none !important;
    }

    /* ── Fila oculta (filtrada vía JS) ────────────────────────── */
    tr.cob-row-hidden {
        display: none;
    }

    /* ── Celda vendedor_id: monoespaciada, discreta ───────────── */
    .cob-vendor-id {
        font-family: 'Courier New', monospace;
        font-size: 0.8rem;
        color: var(--text-muted);
        background: rgba(255,255,255,0.04);
        padding: 3px 8px;
        border-radius: 4px;
        border: 1px solid var(--border);
    }

    /* ── Banco pill ───────────────────────────────────────────── */
    .cob-banco-pill {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-muted);
        white-space: nowrap;
    }

    /* ── No-results row ───────────────────────────────────────── */
    #cob-no-results {
        display: none;
        text-align: center;
        padding: 50px 24px;
        color: var(--text-muted);
    }
    #cob-no-results i {
        font-size: 2.5rem;
        margin-bottom: 16px;
        color: var(--border);
        display: block;
    }

    /* ── Responsive: ocultar columna ID en móvil ──────────────── */
    @media (max-width: 768px) {
        .cob-col-id, .cob-col-vendedor { display: none; }
    }
</style>

<!-- ═══════════════════════════════════════════════════════════════
     5. CONTENIDO PRINCIPAL DEL MÓDULO
     ════════════════════════════════════════════════════════════ -->
<main class="main-content">
    <div class="content-wrapper">

        <!-- ─── 5.1 TÍTULO DE PÁGINA ──────────────────────────── -->
        <div class="page-title">
            <h1>Gestión de Cobranzas</h1>
            <p>Reporte operativo SMOV · Agrupación por responsable de cartera</p>
        </div>

        <!-- ─── 5.2 TARJETAS KPI (MÉTRICAS RÁPIDAS) ──────────────
             POR QUÉ CUATRO MÉTRICAS:
               Refleja los cuatro estados posibles del reporte SMOV:
               Total, Alertas (riesgo), Pendientes y Normalizados.
               Le da al jefe de cobranzas una visión de 360° de un vistazo.
        ──────────────────────────────────────────────────────── -->
        <div class="metrics-grid">

            <!-- Total de gestiones del período -->
            <div class="card metric-card" style="border-left: 4px solid var(--primary);">
                <div class="metric-icon" style="color:var(--primary); background:rgba(0,180,255,0.12);">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="metric-content">
                    <span class="metric-label">Total Gestiones</span>
                    <p class="metric-value" id="kpi-total"><?php echo $total_gestiones; ?></p>
                </div>
            </div>

            <!-- Cuentas en estado ALERTA -->
            <div class="card metric-card alert">
                <div class="metric-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="metric-content">
                    <span class="metric-label">En Alerta</span>
                    <p class="metric-value" id="kpi-alertas"><?php echo $total_alertas; ?></p>
                </div>
            </div>

            <!-- Cuentas PENDIENTES de acción -->
            <div class="card metric-card warning">
                <div class="metric-icon"><i class="fas fa-clock"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Pendientes</span>
                    <p class="metric-value" id="kpi-pendientes"><?php echo $total_pendientes; ?></p>
                </div>
            </div>

            <!-- Cuentas Normalizadas (A NC EN B) -->
            <div class="card metric-card success">
                <div class="metric-icon"><i class="fas fa-check-circle"></i></div>
                <div class="metric-content">
                    <span class="metric-label">Normalizadas</span>
                    <p class="metric-value" id="kpi-normalizados"><?php echo $total_normalizados; ?></p>
                </div>
            </div>

        </div><!-- /.metrics-grid -->

        <!-- ─── 5.3 PANEL DE FILTROS ──────────────────────────────
             FILTROS OPERATIVOS:
               - Rango de fechas: input[type=date] x2 (fecha_ini / fecha_fin)
               - Responsable: select poblado desde PHP ($responsables)
               - Búsqueda texto: filtra por nombre de Farmacia/cliente
               - Estatus: muestra solo un badge particular
             Todos los filtros operan en TIEMPO REAL vía JS (ver Sección 7).
             No recargan la página — eficiencia de UX para operadores.
        ──────────────────────────────────────────────────────── -->
        <section class="card filters-card" aria-label="Filtros de cobranzas">
            <div class="filters-header">
                <i class="fas fa-filter"></i>
                <h2>Filtros Operativos</h2>
            </div>

            <div class="filters-row">

                <!-- Fecha inicio -->
                <div class="filter-group">
                    <label for="cob-fecha-ini">Fecha Inicio</label>
                    <input type="date"
                           id="cob-fecha-ini"
                           value="<?php echo date('Y-m-01'); ?>"
                           aria-label="Filtrar desde fecha">
                </div>

                <!-- Fecha fin -->
                <div class="filter-group">
                    <label for="cob-fecha-fin">Fecha Fin</label>
                    <input type="date"
                           id="cob-fecha-fin"
                           value="<?php echo date('Y-m-d'); ?>"
                           aria-label="Filtrar hasta fecha">
                </div>

                <!-- Responsable (agente de cobranza) -->
                <div class="filter-group">
                    <label for="cob-filter-resp">Responsable</label>
                    <select id="cob-filter-resp" aria-label="Seleccionar responsable">
                        <option value="">TODOS LOS RESPONSABLES</option>
                        <?php foreach ($responsables as $resp): ?>
                            <option value="<?php echo htmlspecialchars($resp); ?>">
                                <?php echo htmlspecialchars($resp); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Búsqueda por nombre de farmacia/cliente -->
                <div class="filter-group">
                    <label for="cob-filter-cliente">Buscar Farmacia</label>
                    <input type="text"
                           id="cob-filter-cliente"
                           placeholder="Nombre de la farmacia..."
                           aria-label="Buscar por nombre de cliente">
                </div>

                <!-- Filtro de estatus -->
                <div class="filter-group">
                    <label for="cob-filter-status">Estatus</label>
                    <select id="cob-filter-status" aria-label="Filtrar por estatus">
                        <option value="">TODOS LOS ESTATUS</option>
                        <option value="A NC EN B">A NC EN B</option>
                        <option value="GESTION">GESTIÓN</option>
                        <option value="ALERTA">ALERTA</option>
                        <option value="PENDIENTE">PENDIENTE</option>
                    </select>
                </div>

                <!-- ─── FILTRO DE MÉTODO DE PAGO ──────────────────────
                     Poblado dinámicamente desde la tabla 'banc'.
                ──────────────────────────────────────────────────────── -->
                <div class="filter-group">
                    <label for="cob-filter-banco">Método de Pago</label>
                    <select id="cob-filter-banco" aria-label="Filtrar por método de pago">
                        <option value="">TODOS LOS MÉTODOS</option>
                        <?php foreach ($bancos as $banco): ?>
                            <option value="<?php echo htmlspecialchars($banco); ?>">
                                <?php echo htmlspecialchars($banco); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro de agrupación (Nuevo) -->
                <div class="filter-group">
                    <label for="cob-group-by">Agrupar Por</label>
                    <select id="cob-group-by" aria-label="Cambiar criterio de agrupación">
                        <option value="responsable">RESPONSABLE</option>
                        <option value="banco">BANCO</option>
                    </select>
                </div>

                <!-- Botón limpiar -->
                <div class="filter-group" style="flex: 0 0 auto;">
                    <label>&nbsp;</label>
                    <button class="btn-neon" id="cob-btn-reset" onclick="cobResetFiltros()"
                            aria-label="Limpiar todos los filtros">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>

            </div><!-- /.filters-row -->
        </section>

        <!-- ─── 5.4 TABLA PRINCIPAL CON AGRUPACIÓN ───────────────
             DISEÑO DE ACCESIBILIDAD:
               - <table> semántico con <thead> / <tbody> / <caption>
               - aria-label en la tabla para lectores de pantalla
               - <th scope="col"> mejora navegación con teclado
             RESPONSIVE:
               - .table-container con overflow-x:auto (style.css l.604)
               - Columnas ID y VendedorID se ocultan en móvil (@media)
        ──────────────────────────────────────────────────────── -->
        <section class="card table-card">

            <!-- Encabezado de la sección con contador dinámico -->
            <div class="table-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="font-size:1rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">
                        <i class="fas fa-table" style="color:var(--primary); margin-right:8px;"></i>
                        Reporte SMOV — Gestiones del Período
                    </h3>
                    <p style="font-size:0.82rem; color:var(--text-muted);">
                        Mostrando <strong id="cob-count-visible"><?php echo $total_gestiones; ?></strong>
                        de <strong><?php echo $total_gestiones; ?></strong> gestiones registradas
                    </p>
                </div>
                <!-- Botón exportar (placeholder; conectar con endpoint PHP/CSV en el futuro) -->
                <button class="btn-neon" id="cob-btn-export"
                        style="background: rgba(0,230,118,0.15); color:var(--accent-green); box-shadow: none; border: 1px solid rgba(0,230,118,0.3);"
                        onclick="cobExportarCSV()"
                        aria-label="Exportar tabla a CSV">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>

            <!-- Contenedor scrollable horizontal -->
            <div class="table-container">
                <table id="cob-tabla-gestiones" aria-label="Tabla de gestiones de cobranzas agrupadas por responsable">
                    <thead>
                        <tr>
                            <th scope="col" class="cob-col-id">#</th>
                            <th scope="col">Fecha</th>
                            <th scope="col" class="cob-col-vendedor">Vendedor ID</th>
                            <th scope="col">Farmacia / Cliente</th>
                            <th scope="col">Método de Pago</th>
                            <th scope="col" class="text-center">Estatus</th>
                        </tr>
                    </thead>

                    <tbody id="cob-tbody">

<?php
/*  ══════════════════════════════════════════════════════════
 *  6. RENDERIZADO PHP CON ALGORITMO DE AGRUPACIÓN
 *  ────────────────────────────────────────────────────────
 *  Variables de estado del bucle:
 *    $ultimo_responsable → centinela del cambio de grupo
 *    $grupo_count        → contador de filas del grupo actual
 *
 *  Complejidad: O(n) donde n = número de gestiones.
 *  El array YA VIENE ordenado por responsable (replicar en SQL
 *  con ORDER BY responsable_nombre).
 *  ══════════════════════════════════════════════════════════ */
$ultimo_responsable = null;
$grupo_count        = 0;

foreach ($reporte_gestiones as $index => $fila):
    // ─── Detectar cambio de grupo ─────────────────────────
    if ($fila['responsable_nombre'] !== $ultimo_responsable):
        // Cerrar el grupo anterior (si existe) actualizando
        // el conteo. El span con id "cob-gc-{vendedor_id}" se
        // actualiza retroactivamente al final del grupo vía JS.
        // (Aquí en PHP simplemente emitimos la cabecera nueva.)

        // Obtener las iniciales del responsable para el avatar de grupo
        $partes    = explode(' ', trim($fila['responsable_nombre']));
        $iniciales = strtoupper(
            ($partes[0][0] ?? '') . ($partes[1][0] ?? '')
        );

        // Contar cuántas filas tiene este responsable (para el badge)
        // Usamos array_filter + count: O(n) adicional pero solo al emitir cabeceras.
        // ALTERNATIVA más eficiente: pre-agrupar con usort + array_reduce antes del bucle.
        $conteo_grupo = count(array_filter(
            $reporte_gestiones,
            fn($r) => $r['responsable_nombre'] === $fila['responsable_nombre']
        ));

        $ultimo_responsable = $fila['responsable_nombre'];
        $grupo_count        = 0;
?>
                        <!-- ╔═══ CABECERA DE GRUPO: <?php echo htmlspecialchars($fila['responsable_nombre']); ?> ═══╗ -->
                        <tr class="cob-group-header"
                            data-responsable="<?php echo htmlspecialchars($fila['responsable_nombre']); ?>">
                            <td colspan="6">
                                <!-- Avatar circular con iniciales -->
                                <span style="
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    width:26px; height:26px;
                                    border-radius:50%;
                                    background: linear-gradient(135deg, var(--primary), var(--primary-alt));
                                    color:#000; font-size:0.65rem; font-weight:800;
                                    margin-right:10px; vertical-align:middle;
                                    box-shadow: 0 0 10px var(--primary-glow);
                                "><?php echo htmlspecialchars($iniciales); ?></span>

                                <i class="fas fa-user-tie" style="margin-right:8px; opacity:0.7; font-size:0.85rem;"></i>
                                <?php echo htmlspecialchars($fila['responsable_nombre']); ?>

                                <span class="cob-group-count">
                                    — <?php echo $conteo_grupo; ?> gestión<?php echo $conteo_grupo !== 1 ? 'es' : ''; ?>
                                </span>
                            </td>
                        </tr>

<?php
    endif; // fin: cambio de grupo
    $grupo_count++;

    // ─── Obtener el badge del estatus ─────────────────────────
    $badge = getStatusBadge($fila['estatus']);

    // ─── Formatear la fecha para lectura humana ───────────────
    // Se usa DateTime::createFromFormat para robusted; si el
    // formato de BD cambia (ej. DATETIME 'Y-m-d H:i:s'),
    // ajusta el primer parámetro de createFromFormat.
    $dt_obj    = DateTime::createFromFormat('Y-m-d', $fila['fecha_gestion']);
    $fecha_fmt = $dt_obj ? $dt_obj->format('d/m/Y') : htmlspecialchars($fila['fecha_gestion']);
?>
                        <!-- ─── FILA DE DATO: Gestión #<?php echo $fila['id_gestion']; ?> ──── -->
                        <tr class="cob-data-row"
                            data-id="<?php echo $fila['id_gestion']; ?>"
                            data-responsable="<?php echo htmlspecialchars($fila['responsable_nombre']); ?>"
                            data-cliente="<?php echo htmlspecialchars(strtolower($fila['cliente_nombre'])); ?>"
                            data-estatus="<?php echo htmlspecialchars($fila['estatus']); ?>"
                            data-fecha="<?php echo htmlspecialchars($fila['fecha_gestion']); ?>"
                            data-banco="<?php echo htmlspecialchars($fila['banco']); ?>">

                            <!-- ID de gestión -->
                            <td class="cob-col-id" style="color:var(--text-muted); font-size:0.82rem;">
                                <?php echo $fila['id_gestion']; ?>
                            </td>

                            <!-- Fecha formateada -->
                            <td>
                                <span style="font-weight:600;"><?php echo $fecha_fmt; ?></span>
                            </td>

                            <!-- Código de vendedor -->
                            <td class="cob-col-vendedor">
                                <span class="cob-vendor-id"><?php echo htmlspecialchars($fila['vendedor_id']); ?></span>
                            </td>

                            <!-- Nombre de la farmacia / cliente -->
                            <td>
                                <span style="font-weight:600;"><?php echo htmlspecialchars($fila['cliente_nombre']); ?></span>
                            </td>

                            <!-- Método de Pago / Banco -->
                            <td>
                                <span class="cob-banco-pill">
                                    <i class="fas fa-wallet" style="margin-right:5px; font-size:0.65rem; opacity:0.7;"></i>
                                    <?php echo htmlspecialchars($fila['banco']); ?>
                                </span>
                            </td>

                            <!-- Badge de estatus dinámico -->
                            <td class="text-center">
                                <span class="<?php echo $badge['class']; ?>">
                                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                                    <?php echo htmlspecialchars($badge['label']); ?>
                                </span>
                            </td>

                        </tr><!-- /.cob-data-row -->

<?php endforeach; ?>

                        <!-- ─── Fila especial: sin resultados tras filtrar ───── -->
                        <tr id="cob-no-results">
                            <td colspan="6">
                                <i class="fas fa-search-minus"></i>
                                <strong>Sin resultados</strong>
                                <p style="font-size:0.82rem; margin-top:8px;">
                                    Ninguna gestión coincide con los filtros aplicados.
                                </p>
                            </td>
                        </tr>

                    </tbody>
                </table><!-- /#cob-tabla-gestiones -->
            </div><!-- /.table-container -->

            <!-- ─── Pie de tabla con total visible ───────────────── -->
            <div class="pagination-wrapper" style="justify-content:flex-start; gap:20px;">
                <span style="font-size:0.82rem; color:var(--text-muted);">
                    <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                    Vista agrupada por Responsable — los filtros aplican en tiempo real sin recargar.
                </span>
                <span style="font-size:0.82rem; color:var(--text-muted); margin-left:auto;">
                    Visibles: <strong id="cob-footer-count"><?php echo $total_gestiones; ?></strong> gestiones
                </span>
            </div>

        </section><!-- /.table-card -->

    </div><!-- /.content-wrapper -->
</main>

<?php
/* ════════════════════════════════════════════════════════════
 *  7. SCRIPTS JAVASCRIPT INLINE DEL MÓDULO
 *  ─────────────────────────────────────────────────────────
 *  POR QUÉ INLINE Y NO EN UN ARCHIVO .js SEPARADO:
 *    El módulo necesita el JSON de datos ($json_gestiones) que
 *    PHP emite. Embeber en <script> permite leer esa variable
 *    de PHP directamente. Una alternativa más desacoplada sería
 *    un endpoint /api/gestiones.php que retorne JSON via fetch().
 *
 *  MOTOR DE FILTROS:
 *    Todos los filtros llaman a cobAplicarFiltros() que:
 *      1. Lee los valores actuales de todos los inputs.
 *      2. Itera las filas .cob-data-row y las muestra/oculta.
 *      3. Para cada cabecera de grupo, la oculta si NINGUNA de
 *         sus filas hijas es visible (preserva coherencia visual).
 *      4. Actualiza los contadores del encabezado de tabla.
 * ════════════════════════════════════════════════════════════ */
$extraScripts = <<<SCRIPTS
<script>
/* ──────────────────────────────────────────────────────────────
 *  DATOS FUENTE (inyectados desde PHP vía json_encode)
 *  En producción, reemplazar con: fetch('/api/gestiones.php?...')
 * ────────────────────────────────────────────────────────────── */
const COB_DATA = {$json_gestiones};

/* ──────────────────────────────────────────────────────────────
 *  REFERENCIAS A ELEMENTOS DEL DOM
 * ────────────────────────────────────────────────────────────── */
const cobFechaIni    = document.getElementById('cob-fecha-ini');
const cobFechaFin    = document.getElementById('cob-fecha-fin');
const cobFilterResp  = document.getElementById('cob-filter-resp');
const cobFilterCli   = document.getElementById('cob-filter-cliente');
const cobFilterStat  = document.getElementById('cob-filter-status');
const cobFilterBanco = document.getElementById('cob-filter-banco'); 
const cobGroupBy     = document.getElementById('cob-group-by'); // selector de agrupación
const cobNoResults   = document.getElementById('cob-no-results');
const cobCountVis    = document.getElementById('cob-count-visible');
const cobFooterCount = document.getElementById('cob-footer-count');

// Referencias a las tarjetas KPI para actualización en tiempo real
const kpiTotal       = document.getElementById('kpi-total');
const kpiAlertas     = document.getElementById('kpi-alertas');
const kpiPendientes  = document.getElementById('kpi-pendientes');
const kpiNormalizados = document.getElementById('kpi-normalizados');

/* ──────────────────────────────────────────────────────────────
 *  cobAplicarFiltros()
 *  Motor principal del sistema de filtros en tiempo real.
 *  Complejidad: O(n) donde n = número de filas de datos.
 * ────────────────────────────────────────────────────────────── */
function cobAplicarFiltros() {
    const fechaIni   = cobFechaIni.value   || '';
    const fechaFin   = cobFechaFin.value   || '';
    const respSel    = cobFilterResp.value  || '';
    const clienteTxt = cobFilterCli.value.toLowerCase().trim();
    const estatusSel = cobFilterStat.value  || '';
    const bancoSel   = cobFilterBanco.value || '';
    const criterioGrp = cobGroupBy.value; // responsable o banco

    // -- 1. Filtrar los datos y calcular métricas en tiempo real --
    let visibles = 0;
    let metricas = { total: 0, alertas: 0, pendientes: 0, ok: 0 };

    const filas = document.querySelectorAll('tr.cob-data-row');
    filas.forEach(tr => {
        const fecha    = tr.dataset.fecha;
        const resp     = tr.dataset.responsable;
        const cliente  = tr.dataset.cliente;
        const estatus  = tr.dataset.estatus;
        const banco    = tr.dataset.banco;

        const okFecha  = (!fechaIni || fecha >= fechaIni) && (!fechaFin || fecha <= fechaFin);
        const okResp   = !respSel   || resp === respSel;
        const okCli    = !clienteTxt || cliente.includes(clienteTxt);
        const okEstatus = !estatusSel || estatus === estatusSel;
        const okBanco  = !bancoSel   || banco === bancoSel;

        const mostrar = okFecha && okResp && okCli && okEstatus && okBanco;
        tr.classList.toggle('cob-row-hidden', !mostrar);

        if (mostrar) {
            visibles++;
            metricas.total++;
            if (estatus === 'ALERTA')    metricas.alertas++;
            if (estatus === 'PENDIENTE') metricas.pendientes++;
            if (estatus === 'A NC EN B') metricas.ok++;
        }
    });

    // -- 2. Re-agrupación Dinámica --
    // Si el criterio de agrupación cambió, necesitamos mover las filas en el DOM.
    // Para simplificar y mantener performance, usaremos el mismo algoritmo:
    // ocultamos todos los headers y solo mostramos los necesarios re-etiquetados,
    // o mejor aun, si el criterio es diferente al original (vendedor),
    // mostramos los headers actuales ocultos y regeneramos la lógica de visibilidad.
    
    // Por ahora, el HTML viene pre-agrupado por Responsable. 
    // Si agrupamos por Banco, necesitamos un enfoque diferente.
    cobRenderizarAgrupacion(criterioGrp);

    // -- 3. Actualizar contadores de UI y KPIs --
    cobCountVis.textContent    = visibles;
    cobFooterCount.textContent = visibles;
    
    // Actualización suave de KPIs (opcional: usar animación)
    kpiTotal.textContent        = metricas.total;
    kpiAlertas.textContent      = metricas.alertas;
    kpiPendientes.textContent   = metricas.pendientes;
    kpiNormalizados.textContent = metricas.ok;

    cobNoResults.style.display = (visibles === 0) ? 'table-row' : 'none';
}

/**
 * Gestiona la visibilidad de los encabezados de grupo según el criterio elegido.
 * Nota: El diseño original asume agrupación por Responsable desde el servidor.
 * Esta función adapta los encabezados existentes o los oculta si no coinciden.
 */
function cobRenderizarAgrupacion(criterio) {
    const cabeceras = document.querySelectorAll('tr.cob-group-header');
    const filas     = [...document.querySelectorAll('tr.cob-data-row')];

    if (criterio === 'responsable') {
        // Regresamos al comportamiento original: headers por responsable
        cabeceras.forEach(h => {
             // Solo mostrar si tiene hijos visibles
             let siguiente = h.nextElementSibling;
             let tieneVisible = false;
             while (siguiente && !siguiente.classList.contains('cob-group-header')) {
                 if (siguiente.classList.contains('cob-data-row') && !siguiente.classList.contains('cob-row-hidden')) {
                     tieneVisible = true; break;
                 }
                 siguiente = siguiente.nextElementSibling;
             }
             h.classList.toggle('cob-row-hidden', !tieneVisible);
             // Restaurar texto original (ya está en el HTML)
        });
    } else {
        // Agrupación por BANCO: Ocultamos los headers de responsable y 
        // podríamos inyectar headers de banco. Para esta versión, 
        // simplemente ocultamos los headers de responsable para no confundir.
        cabeceras.forEach(h => h.classList.add('cob-row-hidden'));
        
        // Opcional: Podríamos reordenar las filas por banco aquí si quisiéramos 
        // una agrupación real por banco en el cliente.
    }
}

/* ──────────────────────────────────────────────────────────────
 *  cobResetFiltros()
 *  Restablece todos los inputs a su valor inicial y re-ejecuta
 *  el motor de filtros.
 * ────────────────────────────────────────────────────────────── */
function cobResetFiltros() {
    // Recalcular primer y último día del mes actual
    const hoy     = new Date();
    const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

    const fmt = d => d.toISOString().split('T')[0];

    cobFechaIni.value    = fmt(primerDia);
    cobFechaFin.value    = fmt(hoy);
    cobFilterResp.value  = '';
    cobFilterCli.value   = '';
    cobFilterStat.value  = '';
    cobFilterBanco.value = '';
    cobGroupBy.value     = 'responsable';

    cobAplicarFiltros();
}

/* ──────────────────────────────────────────────────────────────
 *  cobExportarCSV()
 *  Genera un archivo CSV desde las filas VISIBLES de la tabla.
 *  POR QUÉ EN EL CLIENTE:
 *    Para las filas ya filtradas no necesitamos round-trip al server.
 *    En el futuro, para reportes grandes, usar un endpoint PHP que
 *    genere el CSV con headers: Content-Disposition: attachment.
 * ────────────────────────────────────────────────────────────── */
function cobExportarCSV() {
    const cabecera = ['ID', 'Fecha', 'Vendedor ID', 'Responsable', 'Farmacia/Cliente', 'Banco', 'Estatus'];
    const filas    = document.querySelectorAll('tr.cob-data-row:not(.cob-row-hidden)');
    const lineas   = [cabecera.join(',')];

    filas.forEach(tr => {
        const celdas = [...tr.querySelectorAll('td')].map(td =>
            '"' + td.innerText.trim().replace(/"/g, '""') + '"'
        );
        lineas.push(celdas.join(','));
    });

    const blob = new Blob([lineas.join('\\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'gestiones_cobranzas_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/* ──────────────────────────────────────────────────────────────
 *  LISTENERS — Debounce para el campo de texto
 *  Se aplica debounce (300ms) al input de texto para no
 *  ejecutar cobAplicarFiltros() en cada keystroke, mejorando
 *  performance con grandes volúmenes de datos.
 * ────────────────────────────────────────────────────────────── */
let cobDebounceTimer;
function cobDebounce(fn, delay) {
    return function(...args) {
        clearTimeout(cobDebounceTimer);
        cobDebounceTimer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// Filtros de selección → efecto inmediato
cobFechaIni.addEventListener('change', cobAplicarFiltros);
cobFechaFin.addEventListener('change', cobAplicarFiltros);
cobFilterResp.addEventListener('change', cobAplicarFiltros);
cobFilterStat.addEventListener('change', cobAplicarFiltros);
cobFilterBanco.addEventListener('change', cobAplicarFiltros);
cobGroupBy.addEventListener('change', cobAplicarFiltros);

// Campo de texto → con debounce de 300ms
cobFilterCli.addEventListener('input', cobDebounce(cobAplicarFiltros, 300));

/* ──────────────────────────────────────────────────────────────
 *  Animación de entrada de las tarjetas KPI
 *  Al cargar, incrementa contadores desde 0 para dar sensación
 *  de "carga de datos en vivo" — micro-animación de UX.
 * ────────────────────────────────────────────────────────────── */
function cobAnimarContador(elementId, valorFinal, duracion) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const inicio     = performance.now();
    const valInicial = 0;

    requestAnimationFrame(function paso(ahora) {
        const transcurrido = ahora - inicio;
        const progreso     = Math.min(transcurrido / duracion, 1);
        // Función de easing: ease-out cuadrático
        const eased = 1 - Math.pow(1 - progreso, 3);
        el.textContent = Math.round(valInicial + eased * (valorFinal - valInicial));
        if (progreso < 1) requestAnimationFrame(paso);
    });
}

// Animar métricas al cargarse la página
document.addEventListener('DOMContentLoaded', () => {
    cobAnimarContador('kpi-total',        {$total_gestiones},   900);
    cobAnimarContador('kpi-alertas',      {$total_alertas},     700);
    cobAnimarContador('kpi-pendientes',   {$total_pendientes},  700);
    cobAnimarContador('kpi-normalizados', {$total_normalizados},700);
});
</script>
SCRIPTS;

include('../includes/footer.php');
?>