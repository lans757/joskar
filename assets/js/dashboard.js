phpdocument.addEventListener('DOMContentLoaded', () => {
    const tableAlertasBody    = document.querySelector('#table-alertas tbody');
    const tableMovimientosBody = document.querySelector('#table-movimientos tbody');
    const paginationControls  = document.getElementById('pagination-controls');
    const paginationInfo      = document.getElementById('pagination-info');
    const countLow            = document.getElementById('count-low');
    const countCritical       = document.getElementById('count-critical');
    const countOk             = document.getElementById('count-ok');
    const totalInventoryCount = document.getElementById('total-inventory-count');
    const filterSearch        = document.getElementById('filter-search');
    const filterAlerta        = document.getElementById('filter-alerta');
    const filterAlmacen       = document.getElementById('filter-almacen');
    const filterProv          = document.getElementById('filter-prov');

    const isVista  = window.location.pathname.includes('vistas/');
    const apiBase  = isVista ? '../api.php' : '../../api.php';
    const expBase  = isVista ? '../export_excel.php' : '../../export_excel.php';

    let currentTab  = 'alertas';
    let currentPage = 1;
    let sortField   = '';
    let sortDir     = '';
    let currentData = [];
    let itemsPerPage = 10;
    let isLoading   = false;

    const COLS = { alertas: 8, movimientos: 7 };

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function getFilters() {
        const urlParams = new URLSearchParams(window.location.search);
        return {
            search:  filterSearch?.value  ?? '',
            alerta:  filterAlerta?.value  ?? '',
            almacen: filterAlmacen?.value ?? '',
            codprov: filterProv?.value    ?? '',
            marca:   urlParams.get('marca') ?? '',
        };
    }

    window.switchTab = (target) => {
        currentTab  = target;
        currentPage = 1;

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === target);
        });
        document.getElementById('alertas-tab').classList.toggle('hidden', target !== 'alertas');
        document.getElementById('movimientos-tab').classList.toggle('hidden', target !== 'movimientos');

        loadData();
    };

    async function fetchData(action, page = 1) {
        if (isLoading) return null;
        isLoading = true;
        showLoading(true);

        const f      = getFilters();
        const offset = (page - 1) * itemsPerPage;
        const params = new URLSearchParams({
            action, limit: itemsPerPage, offset,
            search: f.search, alerta: f.alerta, almacen: f.almacen,
            codprov: f.codprov, marca: f.marca,
            sort_field: sortField, sort_dir: sortDir,
        });

        try {
            const response = await fetch(`${apiBase}?${params}`);
            if (!response.ok) {
                const txt = await response.text();
                throw new Error(`HTTP ${response.status}: ${txt.substring(0, 100)}`);
            }
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); }
            catch { throw new Error('El servidor devolvió una respuesta inválida.'); }

            if (result.error) { showErrorInTable(result.error); return null; }

            currentData = result.data || [];
            return result;
        } catch (err) {
            showErrorInTable('Fallo al cargar datos. Verifique la conexión o el servidor.');
            return null;
        } finally {
            isLoading = false;
            showLoading(false);
        }
    }

    function showLoading(show) {
        if (!show) return;
        const cols = COLS[currentTab];
        const body = currentTab === 'alertas' ? tableAlertasBody : tableMovimientosBody;
        body.innerHTML = `<tr><td colspan="${cols}" class="text-center" style="padding:60px;color:var(--text-muted);">
            <i class="fas fa-circle-notch fa-spin" style="font-size:2rem;margin-bottom:20px;color:var(--primary);"></i><br>
            <span style="font-weight:600;letter-spacing:0.05em;text-transform:uppercase;font-size:0.8rem;">Sincronizando Inventario...</span>
        </td></tr>`;
    }

    function showErrorInTable(msg) {
        const cols = COLS[currentTab];
        const body = currentTab === 'alertas' ? tableAlertasBody : tableMovimientosBody;
        body.innerHTML = `<tr><td colspan="${cols}" class="text-center" style="padding:60px;">
            <i class="fas fa-exclamation-circle" style="font-size:2rem;color:var(--accent-red);margin-bottom:15px;"></i><br>
            <span style="color:var(--accent-red);font-weight:700;display:block;margin-bottom:5px;">ERROR DE CONEXIÓN</span>
            <span style="color:var(--text-muted);font-size:0.85rem;">${escHtml(msg)}</span>
        </td></tr>`;
        renderMetrics({ critical: '0', low: '0', ok: '0' });
    }

    function renderAlerts(data, total, result) {
        if (!data.length) {
            tableAlertasBody.innerHTML = `<tr><td colspan="${COLS.alertas}" class="text-center">No hay alertas</td></tr>`;
            renderMetrics(result.metrics, total);
            renderPagination(0);
            return;
        }

        tableAlertasBody.innerHTML = data.map(item => {
            const exist    = parseFloat(item.existen) || 0;
            const diStock  = parseFloat(item.diasinv) || 0;
            const ventau   = parseFloat(item.ventau)  || 0;
            const vdp      = ventau / 30;
            const sugerido = Math.max(0, (vdp * 15) - exist);

            let statusLabel, statusClass;
            if (exist <= 0)       { statusLabel = 'Agotado'; statusClass = 'badge-critical'; }
            else if (diStock < 10) { statusLabel = 'Crítico'; statusClass = 'badge-critical'; }
            else if (diStock <= 30){ statusLabel = 'Atención'; statusClass = 'badge-low'; }
            else                   { statusLabel = 'Óptimo';   statusClass = 'badge-ok'; }

            return `<tr>
                <td><span class="code-badge">${escHtml(item.codigo)}</span></td>
                <td class="product-name">${escHtml(item.descrip)}</td>
                <td><small style="color:var(--text-muted);">${escHtml(item.proveedor || 'N/A')}</small></td>
                <td class="text-right"><b>${exist.toFixed(0)}</b></td>
                <td class="text-right">${vdp.toFixed(2)}</td>
                <td class="text-right">${(parseFloat(item.min)||0).toFixed(0)}</td>
                <td class="text-center"><span class="badge ${statusClass}">${statusLabel} (<b>${diStock.toFixed(0)}d</b>)</span></td>
                <td class="text-right"><span style="color:var(--accent-yellow);font-weight:800;font-size:0.9rem;">${sugerido > 0 ? sugerido.toFixed(0) : '—'}</span></td>
            </tr>`;
        }).join('');

        renderMetrics(result.metrics, total);
        renderPagination(total);
    }

    function renderMovements(data, total, result) {
        if (!data.length) {
            tableMovimientosBody.innerHTML = `<tr><td colspan="${COLS.movimientos}" class="text-center">Sin datos</td></tr>`;
            renderMetrics(result?.metrics, total);
            renderPagination(0);
            return;
        }

        tableMovimientosBody.innerHTML = data.map(item => {
            const diStock     = parseFloat(item.diasinv) || 0;
            const statusColor = diStock < 10 ? 'var(--accent-red)' : (diStock <= 30 ? 'var(--accent-yellow)' : 'var(--accent-green)');
            return `<tr>
                <td><small style="color:var(--text-muted);font-weight:600;">${escHtml(item.grupo || 'GENERAL')}</small></td>
                <td><span class="code-badge">${escHtml(item.codigo)}</span></td>
                <td class="product-name">${escHtml(item.descrip)}</td>
                <td><small style="color:var(--text-muted);">${escHtml(item.proveedor || 'N/A')}</small></td>
                <td class="text-right" style="color:var(--text-main);font-weight:600;">${(parseFloat(item.ventau)||0).toFixed(2)}</td>
                <td class="text-right"><b>${(parseFloat(item.existen)||0).toFixed(0)}</b></td>
                <td class="text-center"><b style="color:${statusColor}">${diStock.toFixed(0)} d</b></td>
            </tr>`;
        }).join('');

        renderMetrics(result.metrics, total);
        renderPagination(total);
    }

    function renderMetrics(metrics, total) {
        if (!metrics) return;
        countCritical.textContent = (metrics.critical || 0).toLocaleString('es-VE');
        countLow.textContent      = (metrics.low      || 0).toLocaleString('es-VE');
        countOk.textContent       = (metrics.ok       || 0).toLocaleString('es-VE');

        const countOut = document.getElementById('count-out');
        if (countOut) countOut.textContent = (metrics.out || 0).toLocaleString('es-VE');

        if (totalInventoryCount) {
            const display = total !== undefined ? total : metrics.totalH1;
            totalInventoryCount.textContent = (display || 0).toLocaleString('es-VE');
        }

        const valUsdEl = document.getElementById('val-usd');
        if (valUsdEl && metrics.valorUSD !== undefined) {
            valUsdEl.textContent = '$ ' + metrics.valorUSD.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    function renderPagination(total) {
        const totalPages = Math.ceil(total / itemsPerPage) || 1;
        const startIdx   = (currentPage - 1) * itemsPerPage + 1;
        const endIdx     = Math.min(currentPage * itemsPerPage, total);

        paginationInfo.innerHTML = `Mostrando <b>${startIdx}-${endIdx}</b> de <b>${total}</b> registros`;

        const atFirst = currentPage === 1;
        const atLast  = currentPage === totalPages;
        let html = `
            <div class="pager-group">
                <button class="pager-btn" ${atFirst ? 'disabled' : ''} onclick="changePage(1)" title="Primera"><i class="fas fa-angles-left"></i></button>
                <button class="pager-btn" ${atFirst ? 'disabled' : ''} onclick="changePage(${currentPage - 1})" title="Anterior"><i class="fas fa-angle-left"></i></button>
            </div>
            <div class="pager-group">`;

        let startPage = Math.max(1, currentPage - 2);
        let endPage   = Math.min(totalPages, startPage + 4);
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

        html += `</div>
            <div class="pager-group">
                <button class="pager-btn" ${atLast ? 'disabled' : ''} onclick="changePage(${currentPage + 1})" title="Siguiente"><i class="fas fa-angle-right"></i></button>
                <button class="pager-btn" ${atLast ? 'disabled' : ''} onclick="changePage(${totalPages})" title="Última"><i class="fas fa-angles-right"></i></button>
            </div>`;

        paginationControls.innerHTML = html;
    }

    async function loadData() {
        const response = await fetchData(currentTab, currentPage);
        if (!response) return;
        if (currentTab === 'alertas') renderAlerts(currentData, response.total, response);
        else renderMovements(currentData, response.total, response);
    }

    window.refreshData = () => loadData();

    window.exportData = () => {
        const f      = getFilters();
        const params = new URLSearchParams({
            action: currentTab, search: f.search,
            alerta: f.alerta, almacen: f.almacen, codprov: f.codprov,
        });
        window.location.href = `${expBase}?${params}`;
    };

    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.addEventListener('click', () => {
            const field = th.dataset.sort;
            if (!field || !currentData.length) return;

            document.querySelectorAll('th[data-sort]').forEach(h => {
                if (h !== th) {
                    h.classList.remove('active-sort');
                    h.querySelector('.sort-icon i').className = 'fas fa-sort';
                }
            });

            sortDir   = sortField === field && sortDir === 'ASC' ? 'DESC' : 'ASC';
            sortField = field;
            th.classList.add('active-sort');
            th.querySelector('.sort-icon i').className = sortDir === 'ASC' ? 'fas fa-sort-up' : 'fas fa-sort-down';

            currentPage = 1;
            loadData();
        });
    });

    window.changePage = (page) => {
        currentPage = page;
        loadData();
        document.querySelector('.table-card')?.scrollIntoView({ behavior: 'smooth' });
    };

    document.getElementById('items-per-page').addEventListener('change', e => {
        itemsPerPage = parseInt(e.target.value);
        currentPage  = 1;
        loadData();
    });

    [filterAlerta, filterAlmacen, filterProv].forEach(el => {
        el?.addEventListener('change', () => { currentPage = 1; loadData(); });
    });

    let searchTimeout;
    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadData(); }, 500);
    });

    // Sincronizar filtros con parámetros URL (deep linking desde KPI dashboard)
    const urlParams = new URLSearchParams(window.location.search);
    [['alerta', filterAlerta], ['almacen', filterAlmacen], ['codprov', filterProv], ['search', filterSearch]]
        .forEach(([key, el]) => { if (el && urlParams.has(key)) el.value = urlParams.get(key); });

    loadData();

    setInterval(() => { if (document.visibilityState === 'visible') loadData(); }, 60000);
});
