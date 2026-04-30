// frontend/assets/js/alertas.js
import api from './api.js?v=3';
import { renderSidebar } from '../../components/sidebar.js?v=3';
import { statusBadge, tagEnviado, loadingHTML, errorHTML, formatData } from '../../components/statusBadge.js?v=3';

document.addEventListener('DOMContentLoaded', () => {
    renderSidebar('alertas');
    setupFiltros();
    loadAlertas();
});

let apenasNaoEnviados = false;

function setupFiltros() {
    document.getElementById('filtros').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        apenasNaoEnviados = btn.dataset.filter === 'pendentes';
        loadAlertas();
    });
}

async function loadAlertas() {
    const container = document.getElementById('alertas-list');
    container.innerHTML = loadingHTML();

    const icons   = { URGENTE: '🔥', CRITICO: '⚠️', ATENCAO: '🔔' };
    const bgcores = {
        URGENTE: 'rgba(239,68,68,.15)',
        CRITICO: 'rgba(249,115,22,.15)',
        ATENCAO: 'rgba(245,158,11,.15)',
    };

    try {
        const alertas = await api.alertas.list(apenasNaoEnviados);
        document.getElementById('alertas-count').textContent = `${alertas.length} alertas`;

        if (alertas.length === 0) {
            container.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--muted);font-family:var(--mono);font-size:.8rem">Nenhum alerta encontrado</div>`;
            return;
        }

        container.innerHTML = alertas.map(a => `
            <div class="alerta-item ${a.tipo}" data-id="${a.id}">
                <div class="alerta-icon" style="background:${bgcores[a.tipo] ?? ''}">
                    ${icons[a.tipo] ?? '📌'}
                </div>
                <div class="alerta-body">
                    <div class="alerta-top">
                        <div class="alerta-prod">
                            ${a.produto}
                            <span class="td-mono td-muted td-small">(${a.sku})</span>
                        </div>
                        ${statusBadge(a.tipo)}
                    </div>
                    <div class="alerta-msg">${a.mensagem}</div>
                    <div class="alerta-meta">
                        <span class="tag">${a.dias_rest}d restantes</span>
                        <span class="tag">Validade: ${formatData(a.data_validade)}</span>
                        <span class="tag">Qtd: ${a.quantidade}</span>
                        ${tagEnviado(a.enviado)}
                        ${!a.enviado ? `<button class="btn-marcar" data-id="${a.id}">Marcar enviado</button>` : ''}
                    </div>
                </div>
            </div>
        `).join('');

        // Marcar como enviado
        container.querySelectorAll('.btn-marcar').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await api.alertas.marcar(btn.dataset.id);
                    loadAlertas();
                } catch(e) { alert('Erro: ' + e.message); }
            });
        });

    } catch(e) {
        container.innerHTML = errorHTML(e.message);
    }
}
