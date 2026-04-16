<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Sistema | ProteoERP</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/noti_pro/assets/css/style.css">
</head>
<body class="error-page-body error-500">
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-database"></i>
        </div>
        <div class="status-badge">Error 500</div>
        <h1>Error de Conexión</h1>
        <p>No se pudo establecer conexión con la base de datos en este momento. Por favor, reintente en unos minutos o contacte al soporte técnico.</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button onclick="window.location.reload()" class="btn-error btn-error-dark">
                <i class="fas fa-sync-alt"></i> Reintentar
            </button>
            <a href="/noti_pro/dashboard.php" class="btn-error btn-error-muted">
                <i class="fas fa-home"></i> Ir al Inicio
            </a>
        </div>
    </div>
</body>
</html>
