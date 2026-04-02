<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProteoERP | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-bg">
    <div class="login-container">
        <div class="card login-card">
            <div class="page-title" style="margin-bottom: 20px;">
                <h1>Droguería Joskar C.A.</h1>
                <p style="text-align: center;">Acceso al Sistema</p>
            </div>
            
            <form action="login.php" method="POST">
                <div class="filters-row" style="flex-direction: column; align-items: stretch; gap: 15px;">
                    <div id="login-error" style="color: var(--accent-red); font-size: 0.85rem; text-align: center; display: none; margin-bottom: 10px;">
                        Usuario o contraseña incorrectos.
                    </div>
                    <div class="filter-group">
                        <label for="username">Usuario</label>
                        <input type="text" id="username" name="username" placeholder="Ingrese su usuario" required>
                    </div>
                    <div class="filter-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required 
                               style="width: 100%; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-main); padding: 14px 18px; border-radius: var(--radius-sm); outline: none;">
                    </div>
                    
                    <button type="submit" class="btn-neon" style="width: 100%; margin-top: 10px;">
                        Entrar <i class="fas fa-sign-in-alt"></i>
                    </button>
                </div>
            </form>
            
            <div class="login-footer">
                &copy; 2026 ProteoERP Dashboard. Todos los derechos reservados.
            </div>
        </div>
    </div>
    <script>
        // Check for login errors
        const params = new URLSearchParams(window.location.search);
        if (params.has('error')) {
            document.getElementById('login-error').style.display = 'block';
            if (params.get('error') === 'db') {
                document.getElementById('login-error').textContent = 'Error de conexión con la base de datos.';
            }
        }
    </script>
    <script src="app.js"></script>
</body>
</html>
