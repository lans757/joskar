<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página No Encontrada | ProteoERP</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-glow: rgba(14, 165, 233, 0.4);
            --bg-dark: #0f172a;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass: rgba(15, 23, 42, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            background: radial-gradient(circle at top right, #111827, #0a0f1d);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .error-wrapper {
            text-align: center;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-code {
            font-family: 'Outfit', sans-serif;
            font-size: 150px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #0ea5e9 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px var(--primary-glow));
            letter-spacing: -5px;
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        p {
            color: var(--text-muted);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        .btn-container {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 20px -5px var(--primary-glow);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -5px var(--primary-glow);
            filter: brightness(1.1);
        }

        .btn-outline {
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        .floating-objects div {
            position: absolute;
            background: var(--primary);
            filter: blur(60px);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
        }

        .obj-1 { width: 300px; height: 300px; top: -100px; left: -100px; }
        .obj-2 { width: 250px; height: 250px; bottom: -50px; right: -50px; background: #a855f7; }

        @media (max-width: 480px) {
            .error-code { font-size: 100px; }
            h1 { font-size: 24px; }
            .btn-container { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="floating-objects">
        <div class="obj-1"></div>
        <div class="obj-2"></div>
    </div>

    <?php 
        $current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
        $base_path = ($current_dir === 'errors') ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : dirname($_SERVER['SCRIPT_NAME']);
        if ($base_path === DIRECTORY_SEPARATOR) $base_path = '';
    ?>
    <div class="error-wrapper">
        <div class="error-code">404</div>
        <h1>¿Te has perdido?</h1>
        <p>No pudimos encontrar la página que buscas. Tal vez el enlace esté roto o haya sido movido permanentemente.</p>
        
        <div class="btn-container">
            <a href="<?php echo $base_path; ?>/dashboard.php" class="btn btn-primary">
                <i class="fas fa-house"></i> Ir al Dashboard
            </a>
            <a href="javascript:history.back()" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Volver atrás
            </a>
        </div>
    </div>
</body>
</html>
