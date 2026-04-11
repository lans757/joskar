<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProteoERP | Login</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body class="login-bg">
    <div class="login-container">
        <div class="card login-card">
            <div class="page-title text-center">
                <h1>Droguería Joskar C.A.</h1>
                <p class="text-muted" style="font-weight: 600; letter-spacing: 0.05em;">ACCESO AL SISTEMA</p>
            </div>
            
            <form action="login.php" method="POST" class="login-form">
                <div id="login-error" class="login-error" style="display: none;">
                    Usuario o contraseña incorrectos.
                </div>
                
                <div class="filter-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" placeholder="Ingrese su usuario" autocomplete="username" required>
                </div>
                
                <div class="filter-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                </div>
                
                <button type="submit" class="btn-neon" style="width: 100%; margin-top: 10px;">
                    Entrar <i class="fas fa-sign-in-alt"></i>
                </button>
            </form>
            
            <div class="login-footer">
                &copy; 2026 ProteoERP Dashboard. Todos los derechos reservados.
            </div>
        </div>
    </div>
    <script>
        // Use auto-focus on username for better UX
        document.getElementById('username').focus();

        // Check for login errors in URL params
        const params = new URLSearchParams(window.location.search);
        if (params.has('error')) {
            const errorBox = document.getElementById('login-error');
            errorBox.style.display = 'block';
            if (params.get('error') === 'db') {
                errorBox.textContent = 'Error de conexión con la base de datos.';
            }
        }
    </script>
    <script src="app.js"></script>
</body>
</html>
