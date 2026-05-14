<?php
/**
 * NotiPro — Bootstrap de errores
 * Centraliza la configuración de error_reporting, logging y manejo de
 * excepciones/fatal errors para que el usuario final NUNCA vea trazas PHP.
 *
 * Lee APP_DEBUG desde .env:
 *   APP_DEBUG=true   → muestra errores en pantalla (solo para desarrollo local)
 *   APP_DEBUG=false  → oculta errores, los registra en logs/php-error.log
 *
 * Incluido automáticamente por includes/db.php.
 */

if (defined('NOTIPRO_BOOTSTRAPPED')) return;
define('NOTIPRO_BOOTSTRAPPED', true);

// ─── Cargar .env si todavía no se cargó ──────────────────────────────
if (!isset($GLOBALS['_NOTIPRO_ENV'])) {
    $envPath = __DIR__ . '/../.env';
    $envVars = [];
    if (is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $envVars[$k] = $v;
        }
    }
    $GLOBALS['_NOTIPRO_ENV'] = $envVars;
}

$debug = strtolower($GLOBALS['_NOTIPRO_ENV']['APP_DEBUG'] ?? 'false') === 'true';

// ─── Configuración de errores ────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors',          $debug ? '1' : '0');
ini_set('display_startup_errors',  $debug ? '1' : '0');
ini_set('log_errors',              '1');

$logDir  = __DIR__ . '/../logs';
$logFile = $logDir . '/php-error.log';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
ini_set('error_log', $logFile);

// ─── Helper: redirigir o renderizar errors/500.php ───────────────────
if (!function_exists('notipro_render_500')) {
    function notipro_render_500() {
        $appRoot = dirname(__DIR__);
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $appRoot = str_replace('\\', '/', $appRoot);
        $base = ($docRoot && strpos($appRoot, $docRoot) === 0)
            ? substr($appRoot, strlen($docRoot))
            : '/' . basename($appRoot);

        // ¿Es una petición AJAX/JSON?
        $isJson = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'api.php')
        );

        if ($isJson) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'Error interno del servidor']);
            return;
        }

        if (!headers_sent()) {
            header('Location: ' . $base . '/errors/500.php');
        } else {
            include __DIR__ . '/../errors/500.php';
        }
    }
}

// ─── Excepciones no capturadas ───────────────────────────────────────
set_exception_handler(function (Throwable $e) {
    error_log('[uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString());
    notipro_render_500();
    exit;
});

// ─── Fatal errors (parse, out-of-memory, etc.) ───────────────────────
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) {
        error_log('[fatal] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        notipro_render_500();
    }
});
