// frontend/assets/js/produtos.js
import api from './api.js';
import { renderSidebar } from '../../components/sidebar.js';
import { loadingHTML, errorHTML, emptyRow, formatMoeda } from '../../components/statusBadge.js';
import { initTheme } from './theme.js';

let produtos   = [];
let categorias = [];
let filtroCategoria = '';

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('produtos');
    initTheme();
    bindEvents();
    await loadCategorias();
    await loadProdutos();
});

function bindEvents() {
    document.getElementById('produto-form').addEventListener('submit', saveProduto);
    document.getElementById('cancel-edit').addEventListener('click', resetForm);
    document.getElementById('search-input').addEventListener('input', renderProdutos);
    document.getElementById('filter-categoria').addEventListener('change', e => {
        filtroCategoria = e.target.value;
        renderProdutos();
    });
}

async function loadCategorias() {
    categorias = await api.categorias.list();
    const select = document.getElementById('categoria_id');
    select.innerHTML = '<option value="">Selecione...</option>' + categorias.map(c =>
        `<option value="${c.id}">${escapeHtml(c.nome)} (${escapeHtml(c.prefixo ?? 'PRD')})</option>`
    ).join('');

    const filterSel = document.getElementById('filter-categoria');
    filterSel.innerHTML = '<option value="">Todas as categorias</option>' + categorias.map(c =>
        `<option value="${c.id}">${escapeHtml(c.nome)}</option>`
    ).join('');
}

async function loadProdutos() {
    const tbody = document.getElementById('produtos-tbody');
    tbody.innerHTML = `<tr><td colspan="11">${loadingHTML()}</td></tr>`;
    try {
        produtos = await api.produtos.list();
        renderProdutos();
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="11">${errorHTML(e.message)}</td></tr>`;
    }
}

function renderProdutos() {
    const q     = document.getElementById('search-input').value.toLowerCase();
    const tbody = document.getElementById('produtos-tbody');

    const filtrados = produtos.filter(p => {
        const matchTexto = `${p.nome} ${p.sku} ${p.categoria}`.toLowerCase().includes(q);
        const matchCat   = filtroCategoria === '' || String(p.categoria_id) === filtroCategoria;
        return matchTexto && matchCat;
    });

    document.getElementById('produtos-count').textContent = `${filtrados.length} produtos`;
    tbody.innerHTML = filtrados.length === 0
        ? emptyRow(11)
        : filtrados.map(p => `
            <tr data-id="${p.id}" class="${document.getElementById('produto-id').value == p.id ? 'editing' : ''}">
                <td class="td-mono td-muted td-small">${escapeHtml(p.sku)}</td>
                <td style="font-weight:500">${escapeHtml(p.nome)}</td>
                <td><span class="tag">${escapeHtml(p.categoria)}</span></td>
                <td class="td-mono td-small">${escapeHtml(p.unidade_medida)}</td>
                <td class="td-mono td-small">${Number(p.peso || 0) > 0 ? formatNumber(p.peso, 3) + ' kg' : '-'}</td>
                <td class="td-mono td-small">R$ ${formatMoeda(p.preco_custo)}</td>
                <td class="td-mono td-small">R$ ${formatMoeda(p.preco_venda)}</td>
                <td class="td-mono td-small" style="color:var(--seguro)">${p.margem_lucro}%</td>
                <td class="td-mono td-small" style="text-align:center">${p.total_lotes}</td>
                <td class="td-mono td-small">${p.estoque_total ?? 0}</td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-secondary btn-small" data-action="edit" data-id="${p.id}">Editar</button>
                        <button class="btn btn-danger btn-small" data-action="delete" data-id="${p.id}">Remover</button>
                    </div>
                </td>
            </tr>
        `).join('');

    tbody.querySelectorAll('[data-action="edit"]').forEach(btn =>
        btn.addEventListener('click', () => editProduto(Number(btn.dataset.id))));
    tbody.querySelectorAll('[data-action="delete"]').forEach(btn =>
        btn.addEventListener('click', () => deleteProduto(Number(btn.dataset.id))));
}

async function saveProduto(event) {
    event.preventDefault();
    const id   = document.getElementById('produto-id').value;
    const data = {
        categoria_id:   Number(document.getElementById('categoria_id').value),
        sku:            document.getElementById('sku').value.trim(),
        nome:           document.getElementById('nome').value.trim(),
        unidade_medida: document.getElementById('unidade_medida').value,
        peso:           Number(document.getElementById('peso').value || 0),
        preco_custo:    Number(document.getElementById('preco_custo').value || 0),
        preco_venda:    Number(document.getElementById('preco_venda').value || 0),
    };

    try {
        if (id) { await api.produtos.update(id, data); toast('Produto atualizado.'); }
        else    { await api.produtos.create(data);     toast('Produto criado.'); }
        resetForm();
        await loadProdutos();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function editProduto(id) {
    const p = produtos.find(item => Number(item.id) === id);
    if (!p) return;
    document.getElementById('produto-id').value         = p.id;
    document.getElementById('categoria_id').value       = p.categoria_id;
    document.getElementById('sku').value                = p.sku;
    document.getElementById('nome').value               = p.nome;
    document.getElementById('unidade_medida').value     = p.unidade_medida;
    document.getElementById('peso').value               = p.peso ?? 0;
    document.getElementById('preco_custo').value        = p.preco_custo;
    document.getElementById('preco_venda').value        = p.preco_venda;
    document.getElementById('form-title').textContent   = 'EDITAR PRODUTO';
    document.getElementById('cancel-edit').hidden       = false;
    renderProdutos();
    document.getElementById('produto-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteProduto(id) {
    const p = produtos.find(item => Number(item.id) === id);
    if (!p || !confirm(`Remover "${p.nome}"?`)) return;
    try {
        await api.produtos.delete(id);
        toast('Produto removido.');
        await loadProdutos();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function resetForm() {
    document.getElementById('produto-form').reset();
    document.getElementById('produto-id').value         = '';
    document.getElementById('form-title').textContent   = 'NOVO PRODUTO';
    document.getElementById('cancel-edit').hidden       = true;
    renderProdutos();
}

function toast(message, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.className = `toast show ${type}`;
    setTimeout(() => el.className = 'toast', 3500);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function formatNumber(value, digits = 2) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: digits, maximumFractionDigits: digits,
    });
}