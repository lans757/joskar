/** 
 * ProteoERP - Utilidades Globales
 * Este archivo contiene funciones y configuraciones compartidas entre todas las vistas.
 */

console.log('ProteoERP Common Utilities Loaded');

document.addEventListener('DOMContentLoaded', async () => {
    const isLoginPage = window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/') || !window.location.pathname.includes('.php');
    
    // Auth Check
    const checkAuth = async () => {
        const isVista = window.location.pathname.includes('vistas/');
        const apiPath = isVista ? '../api.php' : 'api.php';
        
        try {
            const resp = await fetch(`${apiPath}?action=me`);
            const user = await resp.json();
            
            if (!user.logged_in && !isLoginPage) {
                window.location.href = (window.location.pathname.includes('vistas/')) ? '../index.php' : 'index.php';
                return null;
            }
            return user;
        } catch (e) {
            console.error('Auth Check Failed', e);
            return null;
        }
    };

    const user = await checkAuth();
    if (user && user.logged_in) {
        setupNavbar(user);
        applySidebarState();
    }

    function applySidebarState() {
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            document.querySelector('.app-container').classList.add('sidebar-collapsed');
        }
    }

    function setupNavbar(user) {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;

        const navbar = document.createElement('nav');
        navbar.className = 'top-navbar';
        
        const logoutUrl = (window.location.pathname.includes('vistas/')) ? '../logout.php' : 'logout.php';
        const initials = user.user_name.split(' ').map(n => n[0]).join('').substring(0, 2);

        navbar.innerHTML = `
            <div style="display: flex; align-items: center; flex: 1;">
                <button class="sidebar-toggle-btn" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="user-profile" id="user-profile-btn">
                <div class="user-avatar">${initials}</div>
                <div class="user-info">
                    <span class="user-name">${user.user_name}</span>
                    <span class="user-role">${user.is_supervisor ? 'Supervisor' : 'Usuario'}</span>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: var(--text-muted);"></i>
                
                <div class="dropdown-menu">
                    <!--<a href="#" class="dropdown-item"><i class="fas fa-user-circle"></i> Mi Perfil</a>
                    <!--<a href="#" class="dropdown-item"><i class="fas fa-cog"></i> Configuración</a>-->
                    <hr style="border: none; border-top: 1px solid var(--border-light); margin: 8px 0;">
                    <a href="${logoutUrl}" class="dropdown-item logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                </div>
            </div>
        `;

        mainContent.prepend(navbar);

        // Sidebar toggle
        const toggleBtn = document.getElementById('sidebar-toggle');
        const appContainer = document.querySelector('.app-container');
        
        // Mobile overlay injection
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }

        toggleBtn.addEventListener('click', () => {
            const isMobile = window.innerWidth <= 991;
            
            if (isMobile) {
                appContainer.classList.toggle('sidebar-mobile-active');
                overlay.classList.toggle('active');
            } else {
                appContainer.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', appContainer.classList.contains('sidebar-collapsed'));
            }
        });

        overlay.addEventListener('click', () => {
            appContainer.classList.remove('sidebar-mobile-active');
            overlay.classList.remove('active');
        });

        // Dropdown toggle
        const btn = document.getElementById('user-profile-btn');
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            btn.classList.toggle('active');
        });

        // Auto-hide sidebar on scroll (Desktop only)
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const isMobile = window.innerWidth <= 991;
            if (isMobile) return;

            const currentScroll = window.scrollY;
            
            // Si bajamos más de 100px, colapsamos la barra automáticamente
            if (currentScroll > 100 && currentScroll > lastScroll) {
                if (!appContainer.classList.contains('sidebar-collapsed')) {
                    appContainer.classList.add('sidebar-collapsed');
                    // Opcionalmente no guardamos en localStorage para que sea solo una sesión de scroll
                }
            } 
            
            lastScroll = currentScroll;
        });

        document.addEventListener('click', () => {
            btn.classList.remove('active');
        });
    }
});
