<?php
/**
 * Verificación de Acceso desde Red Local (LAN)
 * Bloquea el acceso si la IP del cliente no pertenece a rangos privados.
 */

function is_local_ip($ip) {
    // Si es localhost (IPv4 o IPv6)
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        return true;
    }

    // Usar filter_var para detectar si NO es una IP pública (es decir, es privada o reservada)
    // FILTER_FLAG_NO_PRIV_RANGE: Excluye rangos 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
    // FILTER_FLAG_NO_RES_RANGE: Excluye rangos reservados como 0.0.0.0/8, 169.254.0.0/16, etc.
    // Si filter_var devuelve false, significa que la IP es uno de estos rangos "protegidos" (local)
    $is_public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

    return !$is_public;
}

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!is_local_ip($client_ip)) {
    error_log("Acceso bloqueado desde IP externa: " . $client_ip . " - " . ($_SERVER['REQUEST_URI'] ?? ''));

    // Enviar código de respuesta 403 Forbidden
    http_response_code(403);
    
    // Si la solicitud espera JSON o es una petición de API, enviar respuesta en JSON
    $is_api = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
              isset($_GET['ajax']) ||
              basename($_SERVER['PHP_SELF']) === 'api.php';

    if ($is_api) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Acceso denegado. Solo se permite el acceso desde la red local.',
            'ip' => $client_ip
        ]);
        exit;
    }

    // Mostrar un mensaje de error elegante para humanos
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Acceso Restringido</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/noti_pro/assets/css/style.css">
    </head>
    <body class="error-page-body error-403">
        <div class="error-container">
            <div class="error-icon" style="color: #be123c;">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>⛔ Acceso Restringido</h1>
            <p>Este sistema solo es accesible desde la red local de la empresa.</p>
            <p>Si cree que esto es un error, contacte al administrador.</p>
            <div class="premium-badge badge-danger" style="margin-top: 10px;">
                Tu IP: <?php echo htmlspecialchars($client_ip); ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit; // Detener la ejecución del script
}
