# Módulo de Seguridad y Accesos

Este módulo gestiona los roles y accesos a los distintos departamentos del Dashboard a través del archivo de configuración JSON local (`includes/accesos.json`). 

El módulo permite, mediante una interfaz gráfica rápida y aislada de la base de datos SQL, conceder o revocar accesos en tiempo real para cualquier usuario del sistema.

## Funcionamiento Técnico

- **Lectura:** Al cargar los módulos del sistema (Cobranzas, Televentas, etc.), se valida en milisegundos si la sesión actual del usuario está registrada dentro del arreglo correspondiente en `accesos.json`.
- **Escritura:** Desde el panel de Seguridad (`vista_seguridad.php`), el administrador (por defecto restringido al usuario `321`) puede añadir o remover usuarios y guardar los cambios. Al guardar, el módulo hace una petición `POST` AJAX que reescribe el archivo `accesos.json` con los nuevos datos.

---

## ⚠️ Requisitos de Despliegue en Producción (Importante)

Dado que este módulo necesita **reescribir** el archivo `accesos.json` directamente en el disco, **el servidor web (PHP) debe tener permisos de escritura sobre ese archivo.**

Si al intentar guardar cambios en producción recibes una alerta de **"Error de red al guardar los accesos"** o **"Error al escribir el archivo accesos.json"**, significa que al hacer `git pull` el archivo se descargó con permisos restringidos.

### ¿Cómo solucionarlo en el servidor de Producción?

Inmediatamente después de hacer tu despliegue o `pull`, ingresa a la terminal de tu servidor, ubícate en la raíz del proyecto (`noti_pro`) y ejecuta **uno** de los siguientes comandos:

**Opción A (Recomendada - Cambiar el dueño al usuario del servidor web):**
```bash
sudo chown www-data:www-data includes/accesos.json
```
*(Nota: Si usas CentOS o RHEL, el usuario del servidor web podría ser `apache` o `nginx` en lugar de `www-data`)*.

**Opción B (Alternativa - Dar permisos globales de escritura al archivo):**
```bash
chmod 666 includes/accesos.json
```

Cualquiera de estas opciones asegurará que el proceso PHP pueda actualizar los roles en tiempo real de manera limpia y sin interrupciones.
