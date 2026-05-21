# Implementación de Accesos por Área (RBAC - JSON)

Se ha completado la migración del control de accesos a una arquitectura basada en un archivo de configuración JSON local (`includes/accesos.json`). Esta estrategia garantiza **cero impacto** en la base de datos `datasis` original, facilitando enormemente los despliegues y aislando el dashboard del ERP Legacy.

## Cambios Realizados

1. **`includes/accesos.json`**: Se creó este archivo como la "fuente de la verdad" para los accesos. Contiene arreglos de usuarios permitidos para cada área (ej. `KMONTAÑEZ` solo en `TELEVENTAS` y `GERENCIA`).
2. **`includes/auth.php`**:
   - Se agregó `has_module_access(string $module): bool` — lee y evalúa los permisos en tiempo real desde `accesos.json`.
   - Se agregó `require_module_access(string $module)` — gate de acceso por área; redirige a `errors/403.php` si el usuario no tiene permiso.
   - El JSON se almacena en caché de PHP (`static`) durante la ejecución para mantener una alta velocidad.
3. **`login.php`**: Se eliminó la dependencia con la base de datos `datasis` para la autorización. Ya no se hace `JOIN` con las tablas `sida` y `modulos` al iniciar sesión; la autenticación se apoya exclusivamente en la tabla `usuario`.

> [!TIP]
> **Para dar o quitar permisos:** Simplemente abre el archivo `includes/accesos.json` y añade o elimina el "código de usuario" (ej. `KMONTAÑEZ`, `ADMIN`, `PRUEBAS`) del arreglo correspondiente al departamento. No necesitas tocar la base de datos ni ejecutar scripts SQL en tus despliegues.

## Mapeo de Vistas → Módulos RBAC

| Vista | Módulo JSON |
|---|---|
| `vistas/almacen/vista_almacen.php` | `ALMACEN` |
| `vistas/televentas/vista_televentas.php` | `TELEVENTAS` |
| `vistas/compras/vista_compras.php` | `COMPRAS` |
| `vistas/administracion/vista_administracion.php` | `ADMINISTRACION` |
| `vistas/cobranzas/vista_cobranzas.php` | `COBRANZAS` |
| `vistas/gerencia/vista_gerencia.php` | `GERENCIA` |
| `vistas/inventario_hardware/vista_inventario_hardware.php` | `INVENTARIO_HARDWARE` |
