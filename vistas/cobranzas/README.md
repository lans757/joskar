# Módulo de Cobranzas

## Propósito
Este directorio maneja el Dashboard de Cobranzas. Está diseñado para monitorear el flujo de pagos de clientes, conciliaciones con cuentas bancarias, conversión multimoneda (USD/BS) y gestión de la cartera activa.

## Archivos
- `vista_cobranzas.php`: Archivo principal que muestra la tabla consolidada, KPIs y filtros de gestión de cobranzas.

## Tablas Principales Usadas (Base de Datos)
- `gecli`: Gestión de clientes (reportes de gestión).
- `sfac`: Facturas relacionadas a los cobros.
- `smov`: Movimientos de saldo (pagos, abonos, notas de crédito/débito).
- `banc`: Maestro de bancos para conciliación.
- `monecam`: Histórico de tasas de cambio para conversión a dólares.
- `scli`: Maestro de clientes.
