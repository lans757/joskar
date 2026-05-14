<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProteoERP | Acceso Denegado</title>
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/theme.js"></script>
    <style>
        * { box-sizing: border-box; }
        body.ad-body {
            margin: 0;
            font-family: 'Inter', Arial, sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .ad-container { max-width: 720px; width: 100%; text-align: center; position: relative; }
        .ad-theme-toggle {
            position: absolute;
            top: -20px;
            right: 0;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.85rem;
        }
        .ad-title {
            font-size: 3rem;
            font-weight: 700;
            color: var(--accent-red);
            margin: 0 0 28px;
            letter-spacing: -0.5px;
        }
        .ad-text {
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--text-muted);
            max-width: 620px;
            margin: 0 auto 22px;
        }
        .ad-text.strong { font-weight: 700; color: var(--text-main); }
        .ad-image { margin-top: 30px; display: flex; justify-content: center; }
        .ad-image img { max-width: 380px; width: 100%; height: auto; display: block; }
        .ad-image .fallback { font-size: 160px; }
        .ad-actions { margin-top: 36px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .ad-btn {
            padding: 10px 22px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: .95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .ad-btn-primary { background: var(--primary); color: #fff; }
        .ad-btn-ghost   { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); }
        @media (max-width: 600px) {
            .ad-title { font-size: 2.2rem; }
            .ad-text  { font-size: .98rem; }
        }
    </style>
</head>
<body class="ad-body">
    <div class="ad-container">
        <button type="button" class="ad-theme-toggle" data-theme-toggle title="Cambiar tema">
            <i class="fas fa-adjust"></i> Tema
        </button>
        <h1 class="ad-title">Acceso Denegado</h1>

        <p class="ad-text">
            Lo sentimos pero no tiene suficientes derechos para entrar a esta sección,
            consulte con el administrador del sistema y haga referencia al código del módulo
            o cualquier otra información que aparece más abajo.
        </p>

        <p class="ad-text strong">
            Si Ud. podía entrar a este módulo y ahora no, lo más probable es que caducó
            la sesión del navegador. Para restablecer la sesión vaya a la ventana principal,
            recargue (F5) y haga un nuevo login.
        </p>

        <div class="ad-image">
            <?php
                $png = __DIR__ . '/../assets/img/acceso_denegado.png';
                $svg = __DIR__ . '/../assets/img/acceso_denegado.svg';
                if (file_exists($png)):
            ?>
                <img src="../assets/img/acceso_denegado.png" alt="Perrito triste de Acceso Denegado">
            <?php elseif (file_exists($svg)): ?>
                <img src="../assets/img/acceso_denegado.svg" alt="Perrito triste de Acceso Denegado">
            <?php else: ?>
                <span class="fallback">🐶</span>
            <?php endif; ?>
        </div>

        <div class="ad-actions">
            <a href="../dashboard.php" class="ad-btn ad-btn-primary">Volver al inicio</a>
            <a href="javascript:history.back()" class="ad-btn ad-btn-ghost">Atrás</a>
        </div>
    </div>
</body>
</html>
