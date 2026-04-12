# 🚀 Guía de Configuración del Proyecto - Joskar

Esta guía detalla los pasos necesarios para clonar y configurar este proyecto en una computadora nueva utilizando **XAMPP**.

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

### 3. Configuración de la Base de Datos

1.  Inicia **Apache** y **MySQL** desde el Panel de Control de XAMPP.
2.  Accede a [http://localhost/phpmyadmin](http://localhost/phpmyadmin) en tu navegador.
3.  Crea una nueva base de datos llamada `datasis`.
4.  _(Opcional)_ Si tienes un archivo `.sql` de respaldo, impórtalo en la base de datos `datasis`.

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
