# Módulo de Marketing (Social Media)

Este directorio contiene el módulo de **Dashboard de Marketing**, el cual está diseñado para visualizar y analizar los indicadores clave de rendimiento (KPIs) de las campañas publicitarias y las redes sociales de la empresa.

## Propósito

Brindar a los equipos de gerencia y marketing una vista rápida y unificada del impacto de las estrategias digitales. El módulo permite:
- Comparar el rendimiento actual contra el mes anterior.
- Visualizar de dónde provienen las interacciones (Alcance vs. Clics).
- Consultar los presupuestos invertidos en campañas publicitarias y su nivel de conversión.
- Observar el volumen de videos producidos y en qué redes sociales se publicaron.

## Arquitectura

El módulo fue adaptado desde un prototipo en React (conectado a Supabase) a una arquitectura **100% PHP Nativo** bajo la estructura del ERP (`noti_pro`).
1. **Frontend:** Usa HTML/PHP (`vista_marketing.php`, `marketing_kpis.php`), estilizado con CSS que implementa diseño *glassmorphism* (`assets/css/marketing.css`).
2. **Interactividad:** Utiliza Vanilla JS (`assets/js/marketing.js`) para cargar la información de la base de datos sin recargar la página (AJAX) y renderizar las gráficas mediante **Chart.js**.
3. **Backend / API:** Un archivo puente en `api_marketing.php` se encarga de recibir peticiones GET (año y mes) y retornar un JSON con los resultados de las consultas a MariaDB.

## Base de Datos

Toda la información del módulo reside en una única tabla consolidada en la base de datos local (MariaDB):

- **`indicadores_marketing`**: Almacena los totales mensuales y las colecciones complejas usando formato JSON.
  - *Columnas regulares:* `id`, `periodo` (YYYY-MM-01), `seguidores_total`, `nuevos_seguidores`, `alcance`, `interacciones`.
  - *Columnas JSON:* `videos` (lista de videos del mes) y `campanas` (lista de campañas publicitarias).

### Despliegue en Producción
Para instalar esta tabla y sus datos iniciales (migrados desde el antiguo entorno React/Supabase) en el servidor de **producción**:
1. Descarga o copia el archivo `indicadores_marketing.sql` ubicado en la raíz del proyecto (`/srv/www/htdocs/noti_pro/indicadores_marketing.sql`).
2. Importa el archivo en la base de datos `datasis` de producción usando la consola o una herramienta como phpMyAdmin:
   ```bash
   mysql -u tu_usuario_produccion -p datasis < indicadores_marketing.sql
   ```
Esto creará la tabla y poblará la base de datos de producción con el histórico de KPIs para que el dashboard funcione inmediatamente.

## Key Metrics (KPIs) Principales

| KPI | Descripción | Origen / Cálculo |
|---|---|---|
| **Seguidores Totales** | El tamaño actual de la comunidad en la red social principal (ej. Instagram). | `social_media_metrics.seguidores_total` |
| **Nuevos Seguidores** | Volumen de usuarios que comenzaron a seguir la cuenta en el mes. | `social_media_metrics.nuevos_seguidores` |
| **Alcance** | Cantidad de cuentas únicas que visualizaron algún contenido en el periodo. | `social_media_metrics.alcance` |
| **Interacciones** | Sumatoria de likes, comentarios, guardados y compartidos. | `social_media_metrics.interacciones` |
| **Engagement Rate (ER)** | Porcentaje de la audiencia que interactúa respecto al alcance obtenido. | `(Interacciones / Alcance) * 100` |
| **CTR de Campaña** | *Click-Through Rate*. Qué porcentaje de personas que vio la publicidad dio clic en ella. | `(Clics / Alcance) * 100` |
| **Delta (%)** | Crecimiento o decrecimiento comparando el mes actual con el inmediato anterior. | `((Mes Actual - Mes Anterior) / Mes Anterior) * 100` |

## Vista de Ofertas Aplicadas (`vista_descuentos.php`)

Este módulo fue rediseñado para enfocarse en el impacto comercial de las campañas promocionales:

### Optimizaciones y Lógica de Negocio
- **Filtro de Fechas Exhaustivo:** Los filtros "Desde" y "Hasta" cubren automáticamente el rango completo de horas del día seleccionado (añadiendo los sufijos `00:00:00` y `23:59:59` en las consultas de base de datos) asegurando la total captura de transacciones.
- **Gráfico Principal:** Consolidado para mostrar únicamente el desempeño en dólares por proveedor, eliminando datasets visuales de volumen que saturaban la pantalla.
- **Deduplicación de Datos (SQL):** Se incorporaron subconsultas con la estructura `GROUP BY numa, codigoa` en la tabla `itpfacdescu`. Esto previene el inflado de sumatorias monetarias y de inventario que ocurría cuando existían registros duplicados por línea de factura en la base de datos de ProteoERP.
- **Modal Interactivo (Drill-Down):** Al hacer clic en un proveedor, se dispara un AJAX que despliega un desglose agrupado por **Campaña/Oferta** en lugar de producto, proporcionando un análisis de "Rendimiento de Campañas" tal como es requerido por el departamento de marketing. Este modal incluye una gráfica secundaria en la misma vista, evitando abrir múltiples ventanas.
