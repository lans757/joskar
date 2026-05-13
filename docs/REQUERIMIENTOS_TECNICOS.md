# NotiPro — Requerimientos técnicos y manejo de errores

> Aplicación web de tableros (dashboards) sobre la base de datos de **ProteoERP**.
> Stock, ventas, cobranzas, compras, televentas, gerencia, inventario de hardware.

---

## 1. Requerimientos técnicos

### 1.1 Servidor

| Componente | Versión mínima | Recomendada |
|---|---|---|
| PHP            | 7.4 | 8.1+ |
| MySQL / MariaDB| 5.7 / 10.3 | 8.0 / 10.6 |
| Apache         | 2.4 | 2.4 + `mod_rewrite`, `mod_headers` |
| Sistema        | Linux (probado en openSUSE) | — |

**Extensiones PHP requeridas:** `pdo_mysql`, `mysqli`, `mbstring`, `json`, `session`.

### 1.2 Cliente / Navegador

- Chrome / Edge 110+
- Firefox 110+
- JavaScript habilitado
- Resolución mínima 1280×720 (diseñado para escritorio)

### 1.3 Permisos de filesystem

| Ruta | Permiso |
|---|---|
| `logs/` | escritura para el usuario de PHP-FPM/Apache (`wwwrun`, `www-data`, etc.) |
| `.env`  | lectura para el usuario de PHP, **denegado vía web** por `.htaccess` |
| `docs/` | lectura para el usuario de PHP |

### 1.4 Configuración — archivo `.env`

```env
DB_HOST=127.0.0.1
DB_USER=datasis
DB_PASS=********
DB_NAME=datasis
APP_DEBUG=true
```

El archivo `.env` lo carga [includes/db.php](../includes/db.php) automáticamente. No se commitea (debe estar en `.gitignore`).

### 1.5 Instalación

1. Clonar el repo en `/srv/www/htdocs/noti_pro/` (o ajustar `.htaccess` si va en otra ruta).
2. Crear `.env` con las credenciales de la BD `datasis` de ProteoERP.
3. Crear carpeta `logs/` con permisos de escritura para Apache.
4. Verificar que el VirtualHost incluye `AllowOverride All` para que `.htaccess` funcione.
5. Acceder a `http://servidor/noti_pro/` → redirige a `index.php` (login).

---

## 2. Estructura del proyecto

```
noti_pro/
├── .env                    # credenciales (NO commitear)
├── .htaccess               # ErrorDocument + bloqueo de archivos sensibles
├── index.php               # landing / login form
├── login.php               # autenticación (POST)
├── logout.php              # cerrar sesión
├── dashboard.php           # panel principal (requiere sesión)
├── api.php                 # endpoints JSON (alertas, movimientos, me)
├── export_excel.php        # exportación XLS
├── app.js / dashboard.js   # frontend
├── style.css
├── includes/
│   ├── auth.php            # require_login / require_supervisor
│   ├── db.php              # conexión PDO + carga de .env
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── vistas/                 # vistas por módulo (todas protegidas)
├── errors/                 # páginas de error (ver §3)
├── logs/                   # logs de PHP (NO commitear)
└── docs/                   # esta carpeta
```

---

## 3. Manejo de errores

### 3.1 Páginas de error en [errors/](../errors/)

| Archivo | Código HTTP | Cuándo se muestra |
|---|---|---|
| [errors/400.php](../errors/400.php) | 400 | Usuario autenticado intenta acceder a un módulo restringido (no tiene permiso de supervisor). |
| [errors/404.php](../errors/404.php) | 404 | URL inexistente. Configurada vía `ErrorDocument` en `.htaccess`. |
| [errors/500.php](../errors/500.php) | 500 | Falla la conexión a la base de datos `datasis`. Disparada desde [includes/db.php](../includes/db.php) cuando `PDO` lanza excepción. |
| [errors/_error_layout.php](../errors/_error_layout.php) | — | Layout interno compartido. **Bloqueado vía `.htaccess`** — no se puede acceder por URL directa. |

> **Nota semántica:** el código HTTP estándar para "sin permisos" es **403 (Forbidden)**, no 400 (Bad Request). Por solicitud del cliente se usa `400` como página, pero el `.htaccess` también mapea **403 → errors/400.php** para cubrir ambos casos.

### 3.2 Flujo de errores

```
┌─────────────────────────────┐
│ Usuario hace request        │
└──────────────┬──────────────┘
               │
        ┌──────▼───────┐
        │ ¿Sesión OK?  │── No ──▶ Redirect a index.php?error=session
        └──────┬───────┘
               │ Sí
        ┌──────▼───────┐
        │ ¿Tiene rol?  │── No ──▶ Redirect a /errors/400.php
        └──────┬───────┘
               │ Sí
        ┌──────▼───────┐
        │ ¿BD responde?│── No ──▶ Redirect a /errors/500.php
        └──────┬───────┘
               │ Sí
        ┌──────▼───────┐
        │ ¿Ruta válida?│── No ──▶ Apache sirve /errors/404.php
        └──────┬───────┘
               │ Sí
               ▼
         Renderiza vista
```

### 3.3 Logging

- Todos los errores PHP se escriben en `logs/php-error.log` (configurado en [api.php](../api.php) y vía `error_log()` en `db.php`).
- **No se muestran trazas al cliente** (`display_errors=0`).
- Recomendación: rotar el log con `logrotate` semanalmente.

### 3.4 Respuestas de error en `api.php` (JSON)

| Caso | Status | Body |
|---|---|---|
| Sin sesión, action ≠ `me` | `401` | `{"error":"No autenticado","logged_in":false}` |
| Acción inválida | `200` | `{"error":"Acción inválida: <x>"}` |
| Excepción SQL | `200` | `{"error":"<mensaje>"}` (logueado en `logs/`) |
| OK | `200` | `{"data":[...],"total":N,"metrics":{...}}` |

---

## 4. Autenticación y roles

### 4.1 Origen

La tabla `usuario` de ProteoERP es la fuente única:

| Columna | Uso |
|---|---|
| `us_codigo` | login (PK) |
| `us_clave`  | password **en texto plano** (legacy ERP) |
| `us_nombre` | nombre a mostrar |
| `supervisor`| `'S'` = supervisor, otro valor = usuario normal |

### 4.2 Sesión

`$_SESSION['logged_in']` (bool), `user_id`, `user_name`, `is_supervisor` (bool).

### 4.3 Helpers — [includes/auth.php](../includes/auth.php)

- `require_login()` — bloquea acceso sin sesión, redirige a `index.php`.
- `require_login_json()` — versión para `api.php`, devuelve HTTP 401 + JSON.
- `require_supervisor()` — bloquea acceso a no-supervisores, redirige a `errors/400.php`.

### 4.4 Pendientes de seguridad

| Riesgo | Estado | Plan |
|---|---|---|
| Passwords en texto plano | ⚠️ Heredado del ERP — no se puede migrar sin coordinación con ProteoERP. | Decisión de negocio. |
| Sin rate-limiting en login | ⚠️ Pendiente | Implementar contador por IP/usuario en sesión o tabla `notipro_login_attempts`. |
| Doble conexión PDO + mysqli en `api.php` | ⚠️ Pendiente | Migrar a PDO con prepared statements. |
| SQL injection mitigada por whitelist en `sort_field` pero los demás parámetros van por `real_escape_string` | ⚠️ Funcional pero frágil | Reescribir queries con bindings. |
| Roles binarios (solo `supervisor S/N`) | Heredado | Si se necesita granularidad, crear tabla `notipro_roles(us_codigo, rol)` paralela. |

---

## 5. Consideraciones operacionales

### 5.1 Performance

- Las queries de `getCounts()` en `api.php` ya fueron consolidadas (1 query en lugar de 6).
- La subquery `$vdp_expr` recorre `sitems` por fila → **agregar índice** `sitems(codigoa, fecha)` si aún no existe.
- KPIs se recalculan en cada request. Considerar caché APCu de 1–5 min para tableros muy consultados.

### 5.2 Compatibilidad con ProteoERP

- **No modificar el esquema de la BD `datasis`**: tablas `usuario`, `sinv`, `itsinv`, `sitems`, `sprv`, etc. son propiedad del ERP.
- Toda extensión de NotiPro debe ir en **tablas con prefijo `notipro_`** para no chocar con futuras actualizaciones del ERP.

### 5.3 Backups

- BD `datasis`: cobertura del backup del ERP (verificar con admin de ProteoERP).
- Repositorio NotiPro: git remoto.
- `logs/`: rotación local, no requiere backup.

### 5.4 Deploy

- `git pull` en el servidor.
- Verificar permisos de `logs/` tras cada deploy.
- Limpiar caché del navegador si cambia `style.css` (ya tiene cache-buster por `filemtime`).

---

## 6. Cambios recientes (changelog técnico)

| Fecha | Cambio |
|---|---|
| 2026-05-13 | Carga de credenciales desde `.env` en `db.php`. |
| 2026-05-13 | Consolidación de `getCounts()` de 6 queries a 1. |
| 2026-05-13 | Logging real a `logs/php-error.log` (antes silenciado). |
| 2026-05-13 | `includes/auth.php` con `require_login` / `require_supervisor`. |
| 2026-05-13 | Protección de sesión en `dashboard.php`, `export_excel.php`, 14 vistas y `api.php`. |
| 2026-05-13 | Páginas de error 400/404/500 en `errors/` + `.htaccess` ErrorDocument. |

---

## 7. Contacto

- Repositorio: `noti_pro` (rama `main`).
- Mantenedor actual: `leonardocaripadev`.
- ERP base: ProteoERP (Droguería Joskar C.A.).
