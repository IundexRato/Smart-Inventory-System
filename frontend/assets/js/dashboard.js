// frontend/assets/js/dashboard.js
// Responsabilidade: buscar dados da API e renderizar o dashboard

import api from './api.js?v=3';
import { renderSidebar } from '../../components/sidebar.js?v=3';
import { statusBadge, diasBar, loadingHTML, errorHTML, formatMoeda } from '../../components/statusBadge.js?v=3';

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderSidebar('dashboard');
    loadDashboard();
});

async function loadDashboard() {
    try {
        setLoading(true);
        const data = await api.dashboard.get();
        renderKpis(data.kpis);
        renderDistribuicao(data.distribuicao);
        renderLotesRisco(data.lotes_risco);
        renderCombos(data.combos);
        renderAlertas(data.alertas);
    } catch (e) {
        document.querySelector('.main').innerHTML = errorHTML(e.message);
    } finally {
        setLoading(false);
    }
}

// ── KPIs ──────────────────────────────────────────────────
function renderKpis(kpis) {
    const items = [
        { label: 'Total de Lotes', value: kpis.total_lotes, color: 'accent' },
        { label: 'Seguro',         value: kpis.seguro  ?? 0, color: 'seguro' },
        { label: 'Atenção',        value: kpis.atencao ?? 0, color: 'atencao' },
        { label: 'Crítico',        value: kpis.critico ?? 0, color: 'critico' },
        { label: 'Urgente',        value: kpis.urgente ?? 0, color: 'urgente' },
    ];

    document.getElementById('kpis').innerHTML = items.map(i => `
        <div class="kpi" data-color="${i.color}">
            <div class="kpi-label">${i.label}</div>
            <div class="kpi-value">${i.value}</div>
        </div>
    `).join('');
}

// ── Distribuição ──────────────────────────────────────────
function renderDistribuicao(dist) {
    const cores = {
        SEGURO: '#22c55e', ATENCAO: '#f59e0b',
        CRITICO: '#f97316', URGENTE: '#ef4444'
    };
    const total = dist.reduce((s, d) => s + Number(d.total), 0) || 1;
    const map   = Object.fromEntries(dist.map(d => [d.status_validade, d.total]));

    document.getElementById('distribuicao').innerHTML = `
        <div class="chart-bars">
            ${Object.entries(cores).map(([k, c]) => {
                const v   = map[k] ?? 0;
                const pct = Math.round((v / total) * 100);
                return `
                    <div class="chart-row">
                        <div class="chart-label">${k}</div>
                        <div class="chart-bar-bg">
                            <div class="chart-bar-fill" style="width:${pct}%;background:${c}">${v}</div>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// ── Lotes em risco ────────────────────────────────────────
function renderLotesRisco(lotes) {
    const tbody = lotes.length === 0
        ? `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">Nenhum lote em risco</td></tr>`
        : lotes.map(l => `
            <tr>
                <td>
                    <div style="font-weight:500">${l.produto}</div>
                    <div class="td-mono td-muted td-small">${l.sku}</div>
                </td>
                <td>${statusBadge(l.status_validade)}</td>
                <td>${diasBar(l.dias_restantes)}</td>
                <td class="td-mono td-small">${l.quantidade}</td>
                <td>${l.combo_id ? statusBadge(l.status_combo) : '<span style="color:var(--muted)">—</span>'}</td>
            </tr>
        `).join('');

    document.getElementById('lotes-risco-body').innerHTML = tbody;
    document.getElementById('lotes-risco-count').textContent = `${lotes.length} lotes`;
}

// ── Combos ────────────────────────────────────────────────
function renderCombos(combos) {
    document.getElementById('combos-count').textContent = `${combos.length} ativos`;
    document.getElementById('combos-grid').innerHTML = combos.map(c => `
        <div style="padding:.9rem 1.4rem;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                <div style="font-size:.82rem;font-weight:500">
                    ${c.produto_origem}
                    <span style="color:var(--accent);font-family:var(--mono)"> + </span>
                    ${c.produto_parceiro}
                </div>
                ${statusBadge(c.status)}
            </div>
            <div style="display:flex;gap:.5rem;margin-top:.4rem;flex-wrap:wrap">
                <span class="tag preco">R$ ${formatMoeda(c.preco_combo)}</span>
                <span class="tag desconto">-${c.desconto_combo}%</span>
                <span class="tag">vence em ${c.dias_validade}d</span>
            </div>
        </div>
    `).join('');
}

// ── Alertas ───────────────────────────────────────────────
function renderAlertas(alertas) {
    const icons   = { URGENTE: '🔥', CRITICO: '⚠️', ATENCAO: '🔔' };
    const bgcores = {
        URGENTE: 'rgba(239,68,68,.15)',
        CRITICO: 'rgba(249,115,22,.15)',
        ATENCAO: 'rgba(245,158,11,.15)',
    };

    document.getElementById('alertas-list').innerHTML = alertas.map(a => `
        <div style="display:flex;gap:1rem;padding:.9rem 1.4rem;border-bottom:1px solid var(--border);align-items:flex-start">
            <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;background:${bgcores[a.tipo] ?? ''}">
                ${icons[a.tipo] ?? '📌'}
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.82rem">${a.produto}</div>
                <div style="font-size:.75rem;color:var(--muted);margin-top:.1rem">${a.mensagem}</div>
                <div style="display:flex;gap:.4rem;margin-top:.3rem;flex-wrap:wrap">
                    ${statusBadge(a.tipo)}
                    <span class="tag">${a.dias_rest}d restantes</span>
                </div>
            </div>
        </div>
    `).join('');
}

function setLoading(on) {
    const el = document.getElementById('loading');
    if (el) el.style.display = on ? 'flex' : 'none';
}
