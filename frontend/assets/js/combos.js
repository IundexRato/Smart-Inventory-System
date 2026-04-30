// frontend/assets/js/combos.js
import api from './api.js?v=3';
import { renderSidebar } from '../../components/sidebar.js?v=3';
import { statusBadge, loadingHTML, errorHTML, formatMoeda, formatData } from '../../components/statusBadge.js?v=3';

document.addEventListener('DOMContentLoaded', () => {
    renderSidebar('combos');
    setupFiltros();
    loadCombos();
});

let statusAtivo = '';

function setupFiltros() {
    document.getElementById('filtros').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        statusAtivo = btn.dataset.status ?? '';
        loadCombos();
    });
}

async function loadCombos() {
    const grid = document.getElementById('combos-grid');
    grid.innerHTML = loadingHTML();
    try {
        const combos = await api.combos.list(statusAtivo);
        document.getElementById('combos-count').textContent = `${combos.length} combos`;

        if (combos.length === 0) {
            grid.innerHTML = `<div style="color:var(--muted);font-size:.82rem;padding:2rem;font-family:var(--mono)">Nenhum combo encontrado</div>`;
            return;
        }

        grid.innerHTML = combos.map(c => `
            <div class="combo-card ${c.status}" data-id="${c.id}">
                <div class="combo-header">
                    <div class="combo-produtos">
                        ${c.produto_origem}
                        <span class="plus"> + </span>
                        ${c.produto_parceiro}
                    </div>
                    ${statusBadge(c.status)}
                </div>
                <div class="combo-stats">
                    <div class="stat">
                        <div class="stat-label">PREÇO COMBO</div>
                        <div class="stat-value accent">R$ ${formatMoeda(c.preco_combo)}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">DESCONTO</div>
                        <div class="stat-value green">${c.desconto_combo}%</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">VALIDADE LOTE</div>
                        <div class="stat-value sm">${formatData(c.data_validade)}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">EXPIRA COMBO</div>
                        <div class="stat-value sm ${c.dias_validade <= 3 ? 'urgent' : ''}">${c.dias_validade}d</div>
                    </div>
                </div>
                <div class="combo-footer">
                    <span class="tag">${c.sku}</span>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        ${statusBadge(c.status_validade)}
                        ${c.status === 'PENDENTE' ? `<button class="btn-aprovar" data-id="${c.id}">Aprovar</button>` : ''}
                    </div>
                </div>
            </div>
        `).join('');

        // Botões de aprovação
        grid.querySelectorAll('.btn-aprovar').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    await api.combos.aprovar(id, 'gerente');
                    loadCombos();
                } catch(e) { alert('Erro ao aprovar: ' + e.message); }
            });
        });

    } catch(e) {
        grid.innerHTML = errorHTML(e.message);
    }
}
