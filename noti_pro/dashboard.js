document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tableAlertasBody = document.querySelector('#table-alertas tbody');
    const tableMovimientosBody = document.querySelector('#table-movimientos tbody');
    const paginationControls = document.getElementById('pagination-controls');
    const paginationInfo = document.getElementById('pagination-info');
    
    // Metrics Elements
    const countLow = document.getElementById('count-low');
    const countCritical = document.getElementById('count-critical');
    const countOk = document.getElementById('count-ok');
    const totalInventoryCount = document.getElementById('total-inventory-count');

    /* 
       ESTADO GLOBAL DE LA APLICACIÓN
       - currentTab: Pestaña activa.
       - currentPage: Página para paginación.
       - sortField/sortDir: Control de ordenamiento.
       - currentData: Almacén local de datos para ordenamiento instatáneo en cliente.
    */
    let currentTab = 'alertas';
    let currentPage = 1;
    let sortField = '';
    let sortDir = ''; 
    let currentData = []; // Guardamos aquí los registros obtenidos de la API
    let itemsPerPage = 10;
    let lastTotalRecords = 0; // Total de registros del último fetch
    let isLoading = false;

    // Tab Switching
    window.switchTab = (target) => {
        currentTab = target;
        currentPage = 1; // Reset to page 1
        
        tabBtns.forEach(btn => btn.classList.remove('active'));
        const activeBtn = Array.from(tabBtns).find(b => b.getAttribute('onclick').includes(target));
        if (activeBtn) activeBtn.classList.add('active');
        
        document.getElementById('alertas-tab').classList.toggle('hidden', target !== 'alertas');
        document.getElementById('movimientos-tab').classList.toggle('hidden', target !== 'movimientos');
        
        loadData();
    };

    /**
     * FUNCIÓN DE OBTENCIÓN DE DATOS (fetchData)
     * Obtiene los registros básicos del servidor sin forzar el ordenamiento en SQL
     * para permitir que el cliente (JS) lo maneje de forma instantánea.
     */
    async function fetchData(action, page = 1) {
        if (isLoading) return;
        isLoading = true;
        showLoading(true);

        const offset = (page - 1) * itemsPerPage;
        const search = document.getElementById('filter-search').value;
        const alerta = document.getElementById('filter-alerta').value;
        const almacen = document.getElementById('filter-almacen').value;
        
        const isVista = window.location.pathname.includes('vistas/');
        const apiPath = isVista ? '../api.php' : 'api.php';
        let url = `${apiPath}?action=${action}&limit=${itemsPerPage}&offset=${offset}&search=${encodeURIComponent(search)}&alerta=${alerta}&almacen=${almacen}&sort_field=${sortField}&sort_dir=${sortDir}`;
        
        try {
            const response = await fetch(url);
            if (!response.ok) {
                const errText = await response.text();
                throw new Error(`Error HTTP ${response.status}: ${response.statusText}. ${errText.substring(0, 100)}`);
            }
            let result;
            const text = await response.text();
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON from server:', text);
                throw new Error('El servidor devolvió una respuesta inválida.');
            }
            
            if (result.error) {
                console.error('API Error:', result.error);
                showErrorInTable(result.error);
                return null;
            }

            // Guardamos los datos en el estado global para poder ordenarlos localmente
            currentData = result.data || [];
            return result;
        } catch (error) {
            console.error('Fetch Error:', error);
            showErrorInTable('Fallo al cargar datos. Verifique la conexión o el servidor.');
            return null;
        } finally {
            isLoading = false;
            showLoading(false);
        }
    }

    function showLoading(show) {
        const targetBody = currentTab === 'alertas' ? tableAlertasBody : tableMovimientosBody;
        if (show) {
            targetBody.innerHTML = `<tr><td colspan="${currentTab === 'alertas' ? 7 : 6}" class="text-center" style="padding: 60px; color: var(--text-muted);">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 2rem; margin-bottom: 20px; color: var(--primary);"></i><br>
                <span style="font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.8rem;">Sincronizando Inventario...</span>
            </td></tr>`;
        }
    }

    function showErrorInTable(msg) {
        const targetBody = currentTab === 'alertas' ? tableAlertasBody : tableMovimientosBody;
        const colSpan = currentTab === 'alertas' ? 7 : 6;
        targetBody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center" style="padding: 60px;">
            <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: var(--accent-red); margin-bottom: 15px;"></i><br>
            <span style="color: var(--accent-red); font-weight: 700; display: block; margin-bottom: 5px;">ERROR DE CONEXIÓN</span>
            <span style="color: var(--text-muted); font-size: 0.85rem;">${msg}</span>
        </td></tr>`;
        renderMetrics({ critical: '0', low: '0', ok: '0' });
    }

    // FUNCIONES DE RENDERIZADO DE TABLAS
    // Estas funciones transforman el array sortedData en el HTML final

    function renderAlerts(data, total, result) {
        if (data.length === 0) {
            tableAlertasBody.innerHTML = '<tr><td colspan="7" class="text-center">No hay alertas</td></tr>';
            renderMetrics(result.metrics);
            renderPagination(0);
            return;
        }

        tableAlertasBody.innerHTML = data.map(item => {
            const diStock = parseFloat(item.diasinv) || 0;
            const exist = parseFloat(item.existen) || 0;
            const minStock = parseFloat(item.min) || 0;
            const maxStock = parseFloat(item.max) || 0;
            const ventau = parseFloat(item.ventau) || 0;
            const vdp = ventau / 30;
            
            // Sugerido de compra para cubrir 15 días: (VDP * 15) - existencia
            const sugerido = Math.max(0, (vdp * 15) - exist);
            
            let statusLabel, statusClass;
            if (diStock < 10 || exist <= 0) {
                statusLabel = 'Crítico'; statusClass = 'badge-critical';
            } else if (diStock <= 30) {
                statusLabel = 'Atención'; statusClass = 'badge-low';
            } else {
                statusLabel = 'Óptimo'; statusClass = 'badge-ok';
            }

            return `
                <tr>
                    <td><span class="code-badge">${item.codigo}</span></td>
                    <td class="product-name">${item.descrip}</td>
                    <td class="text-right"><b>${exist.toFixed(0)}</b></td>
                    <td class="text-right">${vdp.toFixed(2)}</td>
                    <td class="text-right">${minStock.toFixed(0)}</td>
                    <td class="text-center"><span class="badge ${statusClass}">${statusLabel} (<b>${diStock.toFixed(0)}d</b>)</span></td>
                    <td class="text-right"><span style="color: var(--accent-yellow); font-weight: 800; font-size: 0.9rem;">${sugerido > 0 ? sugerido.toFixed(0) : '—'}</span></td>
                </tr>
            `;
        }).join('');

        renderMetrics(result.metrics);
        renderPagination(total);
    }

    function renderMetrics(metrics) {
        if (!metrics) return;
        countCritical.textContent = metrics.critical || 0;
        countLow.textContent = metrics.low || 0;
        countOk.textContent = metrics.ok || 0;
        if (totalInventoryCount) totalInventoryCount.textContent = metrics.totalH1 || 0;
    }

    function renderMovements(data, total, result) {
        if (data.length === 0) {
            tableMovimientosBody.innerHTML = '<tr><td colspan="6" class="text-center">Sin datos</td></tr>';
            renderMetrics(result ? result.metrics : null);
            renderPagination(0);
            return;
        }

        tableMovimientosBody.innerHTML = data.map(item => {
            const diStock = parseFloat(item.diasinv) || 0;
            const statusColor = diStock < 10 ? 'var(--accent-red)' : (diStock <= 30 ? 'var(--accent-yellow)' : 'var(--accent-green)');
            return `
                <tr>
                    <td><small style="color: var(--text-muted); font-weight: 600;">${item.grupo || 'GENERAL'}</small></td>
                    <td><span class="code-badge">${item.codigo}</span></td>
                    <td class="product-name">${item.descrip}</td>
                    <td class="text-right" style="color: var(--text-main); font-weight: 600;">${(parseFloat(item.ventau) || 0).toFixed(2)}</td>
                    <td class="text-right"><b>${parseFloat(item.existen).toFixed(0)}</b></td>
                    <td class="text-center"><b style="color:${statusColor}">${diStock.toFixed(0)} d</b></td>
                </tr>
            `;
        }).join('');
        renderMetrics(result.metrics);
        renderPagination(total);
    }

    // Render Data Main function
    async function loadData() {
        const response = await fetchData(currentTab, currentPage);
        if (!response) return;
        
        if (currentTab === 'alertas') {
            renderAlerts(currentData, response.total, response);
        } else {
            renderMovements(currentData, response.total, response);
        }
    }
    
    // Expose loadData globally for the Refresh button
    window.refreshData = () => {
        loadData();
    };

    window.exportData = () => {
        const search = document.getElementById('filter-search').value;
        const alerta = document.getElementById('filter-alerta').value;
        const almacen = document.getElementById('filter-almacen').value;
        
        const isVista = window.location.pathname.includes('vistas/');
        const exportPath = isVista ? '../export_excel.php' : 'export_excel.php';
        
        let url = `${exportPath}?action=${currentTab}&search=${encodeURIComponent(search)}&alerta=${alerta}&almacen=${almacen}`;
        window.location.href = url;
    };

    function renderPagination(total) {
        lastTotalRecords = total;
        const totalPages = Math.ceil(total / itemsPerPage) || 1;
        const startIdx = (currentPage - 1) * itemsPerPage + 1;
        const endIdx = Math.min(currentPage * itemsPerPage, total);

        paginationInfo.innerHTML = `Mostrando <b>${startIdx}-${endIdx}</b> de <b>${total}</b> registros`;

        let html = '';
        
        // Botones de navegación (Primero y Anterior)
        html += `
            <div class="pager-group">
                <button class="pager-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(1)" title="Primera"><i class="fas fa-angles-left"></i></button>
                <button class="pager-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})" title="Anterior"><i class="fas fa-angle-left"></i></button>
            </div>
        `;

        // Generar lista de páginas
        html += `<div class="pager-group">`;
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

        if (startPage > 1) {
            html += `<button class="pager-btn" onclick="changePage(1)">1</button>`;
            if (startPage > 2) html += `<span class="page-ellipsis">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pager-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span class="page-ellipsis">...</span>`;
            html += `<button class="pager-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
        }
        html += `</div>`;

        // Botones de navegación (Siguiente y Último)
        html += `
            <div class="pager-group">
                <button class="pager-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})" title="Siguiente"><i class="fas fa-angle-right"></i></button>
                <button class="pager-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${totalPages})" title="Última"><i class="fas fa-angles-right"></i></button>
            </div>
        `;
        
        paginationControls.innerHTML = html;
    }

    /**
     * LÓGICA DE ORDENAMIENTO EN CLIENTE (handleHeaderSort)
     * Capturamos el clic en <th> y procesamos el array currentData localmente.
     */
    const handleHeaderSort = (e) => {
        const th = e.currentTarget;
        const field = th.dataset.sort; 
        const type = th.dataset.type; // Capturamos si es 'number' o 'text'
        if (!field || !currentData.length) return;

        // Limpieza visual de otros encabezados
        document.querySelectorAll('th[data-sort]').forEach(h => {
            if (h !== th) {
                h.classList.remove('active-sort');
                h.querySelector('.sort-icon i').className = 'fas fa-sort';
            }
        });

        // Toggle de dirección
        if (sortField === field) {
            sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            sortField = field;
            sortDir = 'ASC';
            th.classList.add('active-sort');
        }

        // Actualizamos iconos visuales
        const icon = th.querySelector('.sort-icon i');
        icon.className = sortDir === 'ASC' ? 'fas fa-sort-up' : 'fas fa-sort-down';

        // RE-CARGA SERVER-SIDE: En lugar de ordenar localmente, pedimos los datos ordenados al servidor
        currentPage = 1; // Volvemos a la página 1 al cambiar el orden
        loadData();
    };

    /**
     * VINCULACIÓN DE EVENTOS (Event Listeners)
     * Confirmamos que cada <th> tiene su listener vinculado correctamente.
     */
    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.addEventListener('click', handleHeaderSort);
    });

    window.changePage = (page) => {
        currentPage = page;
        loadData();
        // Scroll smoothly to the top of the table
        document.querySelector('.table-card').scrollIntoView({ behavior: 'smooth' });
    };

    // Filter Events
    document.getElementById('items-per-page').addEventListener('change', (e) => {
        itemsPerPage = parseInt(e.target.value);
        currentPage = 1;
        loadData();
    });

    document.getElementById('filter-alerta').addEventListener('change', () => {
        currentPage = 1;
        loadData();
    });

    document.getElementById('filter-almacen').addEventListener('change', () => {
        currentPage = 1;
        loadData();
    });

    let searchTimeout;
    document.getElementById('filter-search').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadData();
        }, 500);
    });

    // Initial Load
    loadData();

    // Auto Refresh (solo cuando la pestaña está visible)
    setInterval(() => {
        if (document.visibilityState === 'visible') loadData();
    }, 60000);
});
