<?php
if (!isset($path_prefix)) $path_prefix = "";
?>
<nav class="top-navbar">
    <div class="sidebar-toggle-btn" id="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="user-profile" id="user-profile">
        <div class="user-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="user-role"><?php echo !empty($_SESSION['is_supervisor']) ? 'Supervisor' : 'Usuario'; ?></span>
        </div>
        <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: var(--text-muted);"></i>
        
        <div class="dropdown-menu">
            <a href="#" class="dropdown-item">
                <i class="fas fa-user-circle"></i> Mi Perfil
            </a>
            <a href="#" class="dropdown-item">
                <i class="fas fa-cog"></i> Configuración
            </a>
            <hr style="border: none; border-top: 1px solid var(--border); margin: 5px 0;">
            <a href="<?php echo $path_prefix; ?>logout.php" class="dropdown-item logout">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profile = document.getElementById('user-profile');
    if (profile) {
        profile.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    }

    document.addEventListener('click', function() {
        if (profile) profile.classList.remove('active');
    });

    const toggleBtn = document.getElementById('sidebar-toggle');
    const container = document.querySelector('.app-container');
    
    if (toggleBtn && container) {
        toggleBtn.addEventListener('click', function() {
            container.classList.toggle('sidebar-collapsed');
        });
    }
});
</script>
