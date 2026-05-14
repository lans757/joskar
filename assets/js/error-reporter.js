/**
 * NotiPro — Error reporter universal
 * Imprime en la consola del navegador cualquier error:
 *   - JS no capturado (window.onerror)
 *   - Promesas rechazadas (unhandledrejection)
 *   - Respuestas fetch con status >= 400 o con clave `error` en el JSON
 * Uso: cargar este archivo ANTES que cualquier otro JS de la app.
 */
(function () {
    const TAG = '%c[ProteoERP]';
    const STYLE_ERR  = 'background:#e74c3c;color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;';
    const STYLE_WARN = 'background:#f39c12;color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;';
    const STYLE_INFO = 'background:#3498db;color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;';

    window.ProteoLog = {
        error: (...args) => console.error(TAG, STYLE_ERR, ...args),
        warn:  (...args) => console.warn(TAG, STYLE_WARN, ...args),
        info:  (...args) => console.info(TAG, STYLE_INFO, ...args)
    };

    // 1) Errores JS no capturados
    window.addEventListener('error', (ev) => {
        window.ProteoLog.error('JS Error:', ev.message, `at ${ev.filename}:${ev.lineno}:${ev.colno}`, ev.error || '');
    });

    // 2) Promesas rechazadas sin .catch
    window.addEventListener('unhandledrejection', (ev) => {
        window.ProteoLog.error('Promise rechazada:', ev.reason);
    });

    // 3) Interceptor global de fetch
    const _fetch = window.fetch;
    window.fetch = async function (...args) {
        const url = (args[0] && args[0].url) || args[0];
        try {
            const resp = await _fetch.apply(this, args);
            if (!resp.ok) {
                window.ProteoLog.error(`HTTP ${resp.status} ${resp.statusText}`, url);
            } else {
                // Clonamos para no consumir el body original
                const ct = resp.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    resp.clone().json().then((json) => {
                        if (json && json.error) {
                            window.ProteoLog.error('API:', json.error, '→', url);
                        }
                    }).catch(() => { /* JSON inválido, ignorar */ });
                }
            }
            return resp;
        } catch (e) {
            window.ProteoLog.error('Fetch falló:', url, e);
            throw e;
        }
    };

    window.ProteoLog.info('Error reporter activo');
})();
