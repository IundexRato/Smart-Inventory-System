// frontend/assets/js/produtos.js
import api from './api.js?v=3';
import { renderSidebar } from '../../components/sidebar.js?v=3';
import { loadingHTML, errorHTML, emptyRow, formatMoeda } from '../../components/statusBadge.js?v=3';

document.addEventListener('DOMContentLoaded', () => {
    renderSidebar('produtos');
    loadProdutos();
});

async function loadProdutos() {
    const tbody = document.getElementById('produtos-tbody');
    tbody.innerHTML = `<tr><td colspan="9">${loadingHTML()}</td></tr>`;
    try {
        const produtos = await api.produtos.list();
        document.getElementById('produtos-count').textContent = `${produtos.length} produtos`;
        tbody.innerHTML = produtos.length === 0
            ? emptyRow(9)
            : produtos.map(p => `
                <tr>
                    <td class="td-mono td-muted td-small">${p.sku}</td>
                    <td style="font-weight:500">${p.nome}</td>
                    <td><span class="tag">${p.categoria}</span></td>
                    <td class="td-mono td-small">${p.unidade_medida}</td>
                    <td class="td-mono td-small">R$ ${formatMoeda(p.preco_custo)}</td>
                    <td class="td-mono td-small">R$ ${formatMoeda(p.preco_venda)}</td>
                    <td class="td-mono td-small" style="color:var(--seguro)">${p.margem_lucro}%</td>
                    <td class="td-mono td-small" style="text-align:center">${p.total_lotes}</td>
                    <td class="td-mono td-small">${p.estoque_total ?? 0}</td>
                </tr>
            `).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="9">${errorHTML(e.message)}</td></tr>`;
    }
}
