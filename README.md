# 🚀 Guía de Configuración del Proyecto - Joskar

Esta guía detalla los pasos necesarios para clonar y configurar este proyecto en una computadora nueva utilizando **XAMPP**.

## 🌿 Estructura de Ramas

Este proyecto utiliza dos ramas principales para el flujo de trabajo:

- **`main` (Producción)**: Esta es la rama principal y estable. Contiene el código que se despliega en el entorno de producción. **No se debe desarrollar directamente en esta rama**.
- **`dev` (Desarrollo)**: Esta rama se utiliza para el desarrollo activo, integrar nuevas características y realizar pruebas antes de enviarlas a producción. Todo el desarrollo debe realizarse aquí.

## 📋 Requisitos Previos

Antes de comenzar, asegúrate de tener instalado:

- [XAMPP](https://www.apachefriends.org/es/index.html) (con PHP 7.4 o superior).
- [Git](https://git-scm.com/) configurado en tu sistema.

---

## 🛠️ Paso a Paso para la Instalación

### 1. Clonar el Proyecto

Abre una terminal (PowerShell o CMD) y navega hasta la carpeta `htdocs` de tu instalación de XAMPP (por defecto es `C:\xampp\htdocs`). Luego ejecuta:

```bash
git clone https://github.com/lans757/joskar.git
```

Entra en la carpeta del proyecto:

```bash
cd joskar
```

### 2. Cambiar a la Rama de Desarrollo (Opcional)

Si quieres trabajar en la rama de desarrollo donde están los últimos cambios:

```bash
git checkout dev
```

### 3. Configuración del Servidor y Base de Datos

Para que el sistema funcione correctamente según el manual técnico, se deben realizar los siguientes ajustes:

1.  **Configuración de PHP (`php.ini`):**
    Localiza el archivo `php.ini` (usualmente en `C:\xampp\php\php.ini`) y modifica o añade las siguientes líneas:
    ```ini
    max_input_vars = 10000
    memory_limit = 512M
    max_execution_time = 3000
    display_errors = On
    ```

2.  **Configuración de MySQL (`my.ini`):**
    Localiza el archivo `my.ini` (usualmente en `C:\xampp\mysql\bin\my.ini`) y asegura estos valores bajo la sección `[mysqld]`:
    ```ini
    [mysqld]
    max_allowed_packet = 1024M
    default-storage-engine = MyISAM
    sql_mode = NO_ENGINE_SUBSTITUTION
    ```

3.  **Importación de la Base de Datos:**
    1.  Inicia **Apache** y **MySQL** desde el Panel de Control de XAMPP.
    2.  Crea una nueva base de datos llamada `datasis` en PHPMyAdmin o por consola.
    3.  Importa tu archivo `.sql`. Si el archivo es muy grande, usa la consola de comandos:
        ```bash
        mysql -u root datasis < F:\datasis.sql
        ```

### 4. Verificar Conexión

El archivo de configuración de la base de datos se encuentra en `includes/db.php`. Asegúrate de que las credenciales coincidan con las de tu entorno local:

```php
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '', // Por defecto vacío en XAMPP
    'db'   => 'datasis'
];
```

### 5. Ejecutar la Aplicación

Abre tu navegador y accede a:
👉 [http://localhost/joskar](http://localhost/joskar)

---

## 🚀 Flujo de Trabajo con Git

Cuando realices cambios y quieras guardarlos:

1.  **Ver estado de cambios:**
    ```bash
    git status
    ```
2.  **Preparar cambios:**
    ```bash
    git add .
    ```
3.  **Confirmar cambios:**
    ```bash
    git commit -m "Descripción de lo que hiciste"
    ```
4.  **Subir a GitHub:**
    ```bash
    git push origin dev
    ```

---

✨ _Desarrollado para el sistema de gestión Joskar._
