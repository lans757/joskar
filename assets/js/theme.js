/**
 * NotiPro — Theme manager
 * Maneja persistencia (localStorage) y togglear claro/oscuro.
 * El tema inicial se aplica vía script inline en <head> (ver header.php)
 * para evitar el "flash" de tema incorrecto al cargar.
 */
(function () {
    const KEY = 'proteo-theme';

    window.ProteoTheme = {
        get() {
            return document.documentElement.getAttribute('data-theme') || 'dark';
        },
        set(theme) {
            if (theme !== 'light' && theme !== 'dark') theme = 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            try { localStorage.setItem(KEY, theme); } catch (e) {}
            document.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
        },
        toggle() {
            this.set(this.get() === 'dark' ? 'light' : 'dark');
        }
    };

    // Conecta cualquier botón con [data-theme-toggle]
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-theme-toggle]');
        if (btn) {
            e.preventDefault();
            window.ProteoTheme.toggle();
        }
    });
})();
