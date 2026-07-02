// frontend/assets/js/lotes.js
import api from './api.js';
import { renderSidebar } from '../../components/sidebar.js';
import { statusBadge, diasBar, loadingHTML, errorHTML, emptyRow } from '../../components/statusBadge.js';
import { initTheme } from './theme.js';
import { escapeHtml, formatData, toast } from './utils.js';

let statusAtivo = '';
let lotes       = [];
let produtosMap = {}; // label → id

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('lotes');
    initTheme();
    setupFiltros();
    bindForm();
    await loadRefs();
    await loadLotes();
    document.getElementById('lotes-search').addEventListener('input', loadLotes);
});

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

function bindForm() {
    // Sincroniza hidden produto_id quando o usuário escolhe do datalist
    document.getElementById('produto_input').addEventListener('input', function () {
        document.getElementById('produto_id').value = produtosMap[this.value] ?? '';
    });

    document.getElementById('lote-form').addEventListener('submit', async e => {
        e.preventDefault();

        const prodId = document.getElementById('produto_id').value;
        if (!prodId) {
            toast('toast', 'Selecione um produto válido da lista.', 'error');
            return;
        }

        const data = {
            produto_id:    Number(prodId),
            fornecedor_id: document.getElementById('fornecedor_id').value || null,
            codigo_lote:   document.getElementById('codigo_lote').value.trim(),
            quantidade:    Number(document.getElementById('quantidade').value),
            data_validade: document.getElementById('data_validade').value,
        };

        try {
            await api.lotes.create(data);
            e.target.reset();
            document.getElementById('produto_id').value = '';
            toast('toast', 'Lote criado.');
            await loadLotes();
        } catch (err) {
            toast('toast', err.message, 'error');
        }
    });
}

async function loadRefs() {
    try {
        const [produtos, fornecedores] = await Promise.all([
            api.produtos.list(),
            api.fornecedores.list(),
        ]);

        // Popula datalist de produtos
        const dl = document.getElementById('produtos-list');
        produtosMap = {};
        produtos.forEach(p => {
            const label = `${p.nome} (${p.sku})`;
            produtosMap[label] = p.id;
            const opt = document.createElement('option');
            opt.value = label;
            dl.appendChild(opt);
        });

        // Popula select de fornecedor
        document.getElementById('fornecedor_id').innerHTML =
            '<option value="">Nenhum</option>' +
            fornecedores.map(f =>
                `<option value="${f.id}">${escapeHtml(f.razao_social)}</option>`
            ).join('');
    } catch (err) {
        toast('toast', 'Erro ao carregar dados de referência: ' + err.message, 'error');
    }
}

async function loadLotes() {
    const tbody = document.getElementById('lotes-tbody');
    tbody.innerHTML = `<tr><td colspan="10">${loadingHTML()}</td></tr>`;
    try {
        lotes = await api.lotes.list(statusAtivo);

        const q = (document.getElementById('lotes-search')?.value ?? '').toLowerCase();
        const filtrados = q
            ? lotes.filter(l =>
                `${l.produto_nome} ${l.sku} ${l.codigo_lote}`.toLowerCase().includes(q))
            : lotes;

        document.getElementById('lotes-count').textContent = `${filtrados.length} lotes`;

        tbody.innerHTML = filtrados.length === 0
            ? emptyRow(10)
            : filtrados.map(l => `
                <tr>
                    <td class="td-mono td-muted td-small">${l.id}</td>
                    <td>
                        <div style="font-weight:500">${escapeHtml(l.produto_nome)}</div>
                        <div class="td-mono td-muted td-small">${escapeHtml(l.sku)}</div>
                    </td>
                    <td class="td-small td-muted">${escapeHtml(l.categoria)}</td>
                    <td class="td-small">${escapeHtml(l.fornecedor ?? '—')}</td>
                    <td class="td-mono td-small">${escapeHtml(l.codigo_lote)}</td>
                    <td class="td-mono td-small">${l.quantidade}</td>
                    <td class="td-mono td-small">${formatData(l.data_validade)}</td>
                    <td>${diasBar(calcDias(l.data_validade))}</td>
                    <td>${statusBadge(l.status_validade)}</td>
                    <td>
                        <button class="btn btn-danger btn-small" data-remover="${l.id}">
                            Remover
                        </button>
                    </td>
                </tr>
            `).join('');

        tbody.querySelectorAll('[data-remover]').forEach(btn =>
            btn.addEventListener('click', () => deleteLote(Number(btn.dataset.remover)))
        );
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10">${errorHTML(e.message)}</td></tr>`;
    }
}

async function deleteLote(id) {
    const lote = lotes.find(item => Number(item.id) === id);
    if (!lote || !confirm(`Remover lote "${lote.codigo_lote}"?`)) return;
    try {
        await api.lotes.delete(id);
        toast('toast', 'Lote removido.');
        await loadLotes();
    } catch (e) {
        toast('toast', e.message, 'error');
    }
}

function calcDias(dataValidade) {
    return Math.ceil((new Date(dataValidade + 'T00:00:00') - new Date()) / 86400000);
}