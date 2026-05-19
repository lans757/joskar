# Módulo de Gerencia

## Propósito
Este directorio agrupa las vistas correspondientes al Dashboard Gerencial. Proporciona una visión de alto nivel sobre los principales pilares del negocio: ventas, compras, inventario y la cartera de cuentas por cobrar.

## Archivos
- `vista_gerencia.php`: Vista principal o contenedor del dashboard gerencial.
- `gerencia_ventas.php`: Resumen y KPIs relacionados con las ventas globales.
- `gerencia_compras.php`: Resumen y KPIs de las compras a proveedores.
- `gerencia_inventario.php`: Visión global del estado, valor y rotación del inventario.
- `gerencia_cartera.php`: Análisis de las cuentas por cobrar y estatus de deuda de clientes.

## Tablas Principales Usadas (Base de Datos)
- `sfac`: Facturas de venta.
- `itpfac`: Renglones de pedidos.
- `smov`: Movimientos de cuentas por cobrar.
- `sinv`: Maestro de inventario.
