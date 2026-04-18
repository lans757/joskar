<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Error del Sistema | ProteoERP</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --danger: #f43f5e;
            --danger-glow: rgba(244, 63, 94, 0.4);
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

        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            color: var(--danger);
            filter: drop-shadow(0 0 15px var(--danger-glow));
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); filter: drop-shadow(0 0 15px var(--danger-glow)); }
            50% { transform: scale(1.05); filter: drop-shadow(0 0 25px var(--danger-glow)); }
            100% { transform: scale(1); filter: drop-shadow(0 0 15px var(--danger-glow)); }
        }

        .error-code {
            font-family: 'Outfit', sans-serif;
            font-size: 80px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f43f5e 0%, #fb923c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--danger);
            color: white;
            box-shadow: 0 10px 20px -5px var(--danger-glow);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -5px var(--danger-glow);
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

        .alert-box {
            margin-top: 25px;
            padding: 15px;
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid rgba(244, 63, 94, 0.2);
            border-radius: 12px;
            font-size: 14px;
            color: #fda4af;
            text-align: left;
        }

        @media (max-width: 480px) {
            h1 { font-size: 24px; }
            .btn-container { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="error-wrapper">
        <div class="error-icon">
            <i class="fas fa-triangle-exclamation"></i>
        </div>
        <div class="error-code">500</div>
        <h1>Error Crítico de Sistema</h1>
        <p>Parece que algo salió mal en nuestros servidores. No te preocupes, el equipo técnico ha sido notificado.</p>
        
        <div class="btn-container">
            <button onclick="window.location.reload()" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Reintentar ahora
            </a>
            <a href="/joskar/dashboard.php" class="btn btn-outline">
                <i class="fas fa-house"></i> Volver al Inicio
            </a>
        </div>

        <div class="alert-box">
            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
            Tip: A veces una actualización rápida de la página soluciona problemas temporales de conexión.
        </div>
    </div>
</body>
</html>
