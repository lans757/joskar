# Módulo de Televentas

## Propósito
Este directorio contiene las vistas y componentes correspondientes al Dashboard de Televentas. Permite hacer seguimiento al rendimiento de los televendedores, artículos facturados por este canal, top de ventas y métricas generales.

## Archivos
- `vista_televentas.php`: Vista principal que sirve de contenedor para el dashboard de televentas.
- `televentas_kpis.php`: Componente encargado de calcular y mostrar los indicadores de gestión de los operadores.
- `televentas_articulos.php`: Listado y desglose de artículos procesados a través del canal de televentas.
- `televentas_top.php`: Ranking de vendedores o artículos más destacados en este canal.

## Tablas Principales Usadas (Base de Datos)
- `pfac`: Pedidos/Facturas proforma (cabecera).
- `itpfac`: Renglones de pedidos/facturas proforma.
- `vend`: Maestro de vendedores.
