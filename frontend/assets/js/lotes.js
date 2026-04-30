// frontend/assets/js/lotes.js
import api from './api.js?v=3';
import { renderSidebar } from '../../components/sidebar.js?v=3';
import { statusBadge, diasBar, loadingHTML, errorHTML, emptyRow } from '../../components/statusBadge.js?v=3';

document.addEventListener('DOMContentLoaded', () => {
    renderSidebar('lotes');
    setupFiltros();
    loadLotes();
});

let statusAtivo = '';

function setupFiltros() {
    document.getElementById('filtros').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        statusAtivo = btn.dataset.status ?? '';
        loadLotes();
    });
}

async function loadLotes() {
    const tbody = document.getElementById('lotes-tbody');
    tbody.innerHTML = `<tr><td colspan="9">${loadingHTML()}</td></tr>`;
    try {
        const lotes = await api.lotes.list(statusAtivo);
        document.getElementById('lotes-count').textContent = `${lotes.length} lotes`;
        tbody.innerHTML = lotes.length === 0
            ? emptyRow(9)
            : lotes.map(l => `
                <tr>
                    <td class="td-mono td-muted td-small">${l.id}</td>
                    <td>
                        <div style="font-weight:500">${l.produto_nome}</div>
                        <div class="td-mono td-muted td-small">${l.sku}</div>
                    </td>
                    <td class="td-small td-muted">${l.categoria}</td>
                    <td class="td-small">${l.fornecedor ?? '—'}</td>
                    <td class="td-mono td-small">${l.codigo_lote}</td>
                    <td class="td-mono td-small">${l.quantidade}</td>
                    <td class="td-mono td-small">${formatData(l.data_validade)}</td>
                    <td>${diasBar(calcDias(l.data_validade))}</td>
                    <td>${statusBadge(l.status_validade)}</td>
                </tr>
            `).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="9">${errorHTML(e.message)}</td></tr>`;
    }
}

function calcDias(dataValidade) {
    const diff = new Date(dataValidade) - new Date();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function formatData(iso) {
    if (!iso) return '—';
    return new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR');
}
