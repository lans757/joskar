<?php
/**
 * Layout autocontenido para páginas de error.
 * NO incluye db.php ni header.php para que funcione incluso si la BD/servidor fallan.
 *
 * Variables esperadas antes del include:
 *   $errorCode   (int)    — código HTTP (400, 404, 500…)
 *   $errorTitle  (string) — título corto
 *   $errorMsg    (string) — mensaje al usuario
 *   $errorIcon   (string) — clase Font Awesome (ej. "fa-plug")
 *   $errorColor  (string) — color del icono (hex)
 */
$errorCode  = $errorCode  ?? 500;
$errorTitle = $errorTitle ?? 'Error';
$errorMsg   = $errorMsg   ?? 'Ha ocurrido un error inesperado.';
$errorIcon  = $errorIcon  ?? 'fa-triangle-exclamation';
$errorColor = $errorColor ?? '#e74c3c';
http_response_code($errorCode);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProteoERP | Error <?php echo (int)$errorCode; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('proteo-theme') || 'dark';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="/noti_pro/assets/css/style.css">
    <script src="/noti_pro/assets/js/error-reporter.js"></script>
    <script src="/noti_pro/assets/js/theme.js"></script>
    <script>
        window.ProteoLog && window.ProteoLog.error(
            'Página de error <?php echo (int)$errorCode; ?>:',
            <?php echo json_encode($errorTitle); ?>,
            <?php echo json_encode($errorMsg); ?>
        );
    </script>
</head>
<body class="err-body" style="--err-color: <?php echo htmlspecialchars($errorColor); ?>;">
    <div class="err-wrap">
        <i class="fas <?php echo htmlspecialchars($errorIcon); ?> err-icon"></i>
        <h1 class="err-code"><?php echo (int)$errorCode; ?></h1>
        <h2 class="err-title"><?php echo htmlspecialchars($errorTitle); ?></h2>
        <p class="err-msg"><?php echo $errorMsg; ?></p>
        <div class="err-actions">
            <a href="../dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> Volver al inicio</a>
            <a href="javascript:history.back()" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Atrás</a>
            <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
        </div>
    </div>
</body>
</html>
