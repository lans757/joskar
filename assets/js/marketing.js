const MONTHS_ES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const REDES = [
  { key: 'tiktok',    label: 'TikTok',    emoji: '🎵', color: '#60A5FA' },
  { key: 'youtube',   label: 'YouTube',   emoji: '▶️', color: '#38BDF8' },
  { key: 'whatsapp',  label: 'WhatsApp',  emoji: '💬', color: '#818CF8' },
  { key: 'instagram', label: 'Instagram', emoji: '📸', color: '#6366F1' },
];

let currentDate = new Date();
// Supabase data uses 2026 for now, let's adjust if no data found later, but let's default to March 2026 to see the test data we migrated.
currentDate = new Date(2026, 2, 1); // March 2026
let barChartInstance = null;
let donutChartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();

    document.getElementById('btn-prev-month').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        loadDashboardData();
    });

    document.getElementById('btn-next-month').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        loadDashboardData();
    });

    // Modals
    document.getElementById('btn-videos').addEventListener('click', () => openModal('modal-videos'));
    document.getElementById('btn-campanas').addEventListener('click', () => openModal('modal-campanas'));

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.target.closest('.marketing-modal-overlay').classList.remove('active');
        });
    });
});

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function fmt(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('es-AR');
}

function calculateDelta(curr, prev) {
    if (!prev || !curr) return null;
    if (prev == 0) return null;
    return (((curr - prev) / prev) * 100).toFixed(1);
}

function updateDeltaChip(elementId, value, higherIsBetter = true) {
    const el = document.getElementById(elementId);
    if (!value && value !== 0) {
        el.innerHTML = '';
        return;
    }
    const isGood = higherIsBetter ? value >= 0 : value <= 0;
    const arrow = value > 0 ? '▲' : '▼';
    const className = isGood ? 'delta-good' : 'delta-bad';
    
    el.innerHTML = `<span class="delta-chip ${className}">${arrow} ${Math.abs(value)}%</span>`;
}

async function loadDashboardData() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth() + 1; // 1-12
    
    document.getElementById('current-month-display').textContent = `${MONTHS_ES[month-1]} ${year}`;
    
    document.getElementById('dashboard-content').style.display = 'none';
    document.getElementById('no-data-msg').style.display = 'none';
    document.getElementById('loading-spinner').style.display = 'flex';

    try {
        const response = await fetch(`api_marketing.php?action=get_dashboard&year=${year}&month=${month}`);
        const data = await response.json();

        document.getElementById('loading-spinner').style.display = 'none';

        if (!data.metrics && (!data.campanas || data.campanas.length === 0) && (!data.videos || data.videos.length === 0)) {
            document.getElementById('no-data-msg').style.display = 'block';
            return;
        }

        document.getElementById('dashboard-content').style.display = 'block';

        // Update KPIs
        const m = data.metrics || {};
        const p = data.prev_metrics || {};

        document.getElementById('kpi-seg-val').textContent = fmt(m.seguidores_total);
        updateDeltaChip('kpi-seg-delta', calculateDelta(m.seguidores_total, p.seguidores_total));

        document.getElementById('kpi-nuevos-val').textContent = fmt(m.nuevos_seguidores);
        updateDeltaChip('kpi-nuevos-delta', calculateDelta(m.nuevos_seguidores, p.nuevos_seguidores));

        document.getElementById('kpi-alcance-val').textContent = fmt(m.alcance);
        updateDeltaChip('kpi-alcance-delta', calculateDelta(m.alcance, p.alcance));

        document.getElementById('kpi-int-val').textContent = fmt(m.interacciones);
        updateDeltaChip('kpi-int-delta', calculateDelta(m.interacciones, p.interacciones));

        // Update Charts
        updateBarChart(m, p);
        updateDonutChart(data.videos);

        // Update Modals & Actions
        document.getElementById('videos-count').textContent = `${data.videos.length} registros`;
        document.getElementById('campanas-count').textContent = `${data.campanas.length} activas`;

        renderVideosModal(data.videos);
        renderCampanasModal(data.campanas);

    } catch (error) {
        console.error("Error loading marketing data:", error);
        document.getElementById('loading-spinner').style.display = 'none';
    }
}

function updateBarChart(current, prev) {
    const ctx = document.getElementById('barChart').getContext('2d');
    if (barChartInstance) barChartInstance.destroy();

    const data = {
        labels: ['Alcance', 'Interacciones', 'Nuevos Seg.'],
        datasets: [
            {
                label: 'Mes Actual',
                data: [current.alcance || 0, current.interacciones || 0, current.nuevos_seguidores || 0],
                backgroundColor: '#38bdf8',
                borderRadius: 4
            },
            {
                label: 'Mes Anterior',
                data: [prev.alcance || 0, prev.interacciones || 0, prev.nuevos_seguidores || 0],
                backgroundColor: 'rgba(148, 163, 184, 0.2)',
                borderRadius: 4
            }
        ]
    };

    const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#94a3b8';
    const gridColor = getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || 'rgba(255,255,255,0.1)';

    barChartInstance = new Chart(ctx, {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textColor } }
            },
            scales: {
                y: { grid: { color: gridColor }, ticks: { color: textColor } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });
}

function updateDonutChart(videos) {
    const ctx = document.getElementById('donutChart').getContext('2d');
    if (donutChartInstance) donutChartInstance.destroy();

    // Group by network
    const grouped = {};
    videos.forEach(v => {
        const net = v.red_social;
        grouped[net] = (grouped[net] || 0) + parseInt(v.cantidad || 1);
    });

    const labels = [];
    const counts = [];
    const bgColors = [];

    REDES.forEach(r => {
        if (grouped[r.key]) {
            labels.push(r.label);
            counts.push(grouped[r.key]);
            bgColors.push(r.color);
        }
    });

    const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#fff';

    donutChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: bgColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor } }
            }
        }
    });
}

function renderVideosModal(videos) {
    const body = document.getElementById('modal-videos-body');
    body.innerHTML = '';
    
    if (videos.length === 0) {
        body.innerHTML = '<p style="color:var(--text-muted); text-align:center;">No hay vídeos registrados este mes.</p>';
        return;
    }

    videos.forEach(v => {
        const red = REDES.find(r => r.key === v.red_social) || REDES[3];
        const html = `
            <div style="display:flex; align-items:center; gap:16px; padding:18px; background:var(--bg-elevated); border-radius:16px; border:1px solid var(--border);">
                <span style="font-size:0.75rem; font-weight: 700; color:${red.color}; background:${red.color}15; border:1px solid ${red.color}33; border-radius:10px; padding:6px 14px; flex-shrink:0;">
                    ${red.emoji} ${red.label}
                </span>
                <div style="flex:1;">
                    <div style="font-size:1rem; font-weight:700; color:var(--text-primary); margin-bottom:4px;">${v.etiqueta}</div>
                    <div style="font-size:0.8rem; color:var(--text-muted); font-family:var(--font-mono);">${v.fecha || 'Sin fecha'}</div>
                </div>
                <div style="font-size:1.3rem; font-weight: 800; color:var(--social-color); font-family: var(--font-mono);">×${v.cantidad || 1}</div>
            </div>
        `;
        body.insertAdjacentHTML('beforeend', html);
    });
}

function renderCampanasModal(campanas) {
    const body = document.getElementById('modal-campanas-body');
    body.innerHTML = '';

    if (campanas.length === 0) {
        body.innerHTML = '<p style="color:var(--text-muted); text-align:center;">No hay campañas activas este mes.</p>';
        return;
    }

    campanas.forEach(c => {
        const p = parseFloat(c.presupuesto || 0).toLocaleString('es-AR');
        const ctr = c.alcance > 0 ? ((c.clics / c.alcance) * 100).toFixed(2) : 0;

        const html = `
            <div style="padding:24px; background:var(--bg-elevated); border-radius:20px; border:1px solid var(--border);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                    <div>
                        <div style="font-size:1.1rem; font-weight: 700; color:var(--text-primary); margin-bottom:6px;">${c.nombre}</div>
                        <div style="font-size:0.8rem; color:var(--text-muted); font-family:var(--font-mono); font-weight: 600;">
                            ${c.fecha_inicio} al ${c.fecha_fin}
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Presupuesto</div>
                        <div style="font-size:1.3rem; font-weight: 800; color:#fbbf24; font-family: var(--font-mono);">$${p}</div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; padding-top:20px; border-top:1px solid var(--border);">
                    <div>
                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight: 700; margin-bottom: 4px;">ALCANCE</div>
                        <div style="font-size:1rem; font-weight: 700; color:#3b82f6; font-family: var(--font-mono);">${fmt(c.alcance)}</div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight: 700; margin-bottom: 4px;">CLICS (CTR)</div>
                        <div style="font-size:1rem; font-weight: 700; color:var(--social-color); font-family: var(--font-mono);">
                            ${fmt(c.clics)} <span style="font-size: 0.8rem; opacity: 0.7;">(${ctr}%)</span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight: 700; margin-bottom: 4px;">CONV.</div>
                        <div style="font-size:1rem; font-weight: 700; color:#10b981; font-family: var(--font-mono);">${fmt(c.conversiones)}</div>
                    </div>
                </div>
            </div>
        `;
        body.insertAdjacentHTML('beforeend', html);
    });
}
