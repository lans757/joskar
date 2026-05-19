# Módulo de Almacén

## Propósito
Este directorio contiene las vistas y componentes correspondientes al Dashboard de Almacén. Su objetivo principal es monitorear el inventario, artículos comprados, ventas principales y presentar los KPIs relevantes para la gestión de depósito y logística.

## Archivos
- `vista_almacen.php`: Vista principal que consolida el dashboard de almacén.
- `almacen_kpis.php`: Lógica para el cálculo y visualización de indicadores clave de rendimiento (KPIs) del almacén.
- `almacen_articulos_comprados.php`: Reporte o vista detallada de los artículos que han ingresado por compras.
- `almacen_compras_fecha.php`: Filtro y reporte de compras organizadas por fecha.
- `almacen_top_vendidos.php`: Ranking de los productos con mayor rotación o más vendidos.

## Tablas Principales Usadas (Base de Datos)
- `sinv`: Maestro de inventario.
- `scst`: Costos de artículos.
- `itpfac`: Detalles/ítems de pedidos o facturas.
- `sprv`: Maestro de proveedores.
