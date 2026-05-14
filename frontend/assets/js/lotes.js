// frontend/assets/js/lotes.js
import api from './api.js?v=4';
import { renderSidebar } from '../../components/sidebar.js?v=4';
import { statusBadge, diasBar, loadingHTML, errorHTML, emptyRow } from '../../components/statusBadge.js?v=3';

let statusAtivo = '';
let lotes = [];

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('lotes');
    setupFiltros();
    bindForm();
    await loadRefs();
    await loadLotes();
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
    document.getElementById('lote-form').addEventListener('submit', async e => {
        e.preventDefault();
        const data = {
            produto_id: Number(document.getElementById('produto_id').value),
            fornecedor_id: document.getElementById('fornecedor_id').value || null,
            codigo_lote: document.getElementById('codigo_lote').value.trim(),
            quantidade: Number(document.getElementById('quantidade').value),
            data_validade: document.getElementById('data_validade').value,
        };

        try {
            await api.lotes.create(data);
            e.target.reset();
            toast('Lote criado.');
            await loadLotes();
        } catch (err) {
            toast(err.message, 'error');
        }
    });
}

async function loadRefs() {
    const [produtos, fornecedores] = await Promise.all([
        api.produtos.list(),
        api.fornecedores.list(),
    ]);

    document.getElementById('produto_id').innerHTML = '<option value="">Selecione...</option>' + produtos.map(p =>
        `<option value="${p.id}">${escapeHtml(p.nome)} (${escapeHtml(p.sku)})</option>`
    ).join('');

    document.getElementById('fornecedor_id').innerHTML = '<option value="">Nenhum</option>' + fornecedores.map(f =>
        `<option value="${f.id}">${escapeHtml(f.razao_social)}</option>`
    ).join('');
}

async function loadLotes() {
    const tbody = document.getElementById('lotes-tbody');
    tbody.innerHTML = `<tr><td colspan="10">${loadingHTML()}</td></tr>`;
    try {
        lotes = await api.lotes.list(statusAtivo);
        document.getElementById('lotes-count').textContent = `${lotes.length} lotes`;
        tbody.innerHTML = lotes.length === 0
            ? emptyRow(10)
            : lotes.map(l => `
                <tr>
                    <td class="td-mono td-muted td-small">${l.id}</td>
                    <td>
                        <div style="font-weight:500">${escapeHtml(l.produto_nome)}</div>
                        <div class="td-mono td-muted td-small">${escapeHtml(l.sku)}</div>
                    </td>
                    <td class="td-small td-muted">${escapeHtml(l.categoria)}</td>
                    <td class="td-small">${escapeHtml(l.fornecedor ?? '-')}</td>
                    <td class="td-mono td-small">${escapeHtml(l.codigo_lote)}</td>
                    <td class="td-mono td-small">${l.quantidade}</td>
                    <td class="td-mono td-small">${formatData(l.data_validade)}</td>
                    <td>${diasBar(calcDias(l.data_validade))}</td>
                    <td>${statusBadge(l.status_validade)}</td>
                    <td><button class="btn btn-danger btn-small" data-id="${l.id}">Remover</button></td>
                </tr>
            `).join('');

        tbody.querySelectorAll('[data-id]').forEach(btn =>
            btn.addEventListener('click', () => deleteLote(Number(btn.dataset.id)))
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
        toast('Lote removido.');
        await loadLotes();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function calcDias(dataValidade) {
    const diff = new Date(dataValidade) - new Date();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function formatData(iso) {
    if (!iso) return '-';
    return new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR');
}

function toast(message, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.className = `toast show ${type}`;
    setTimeout(() => el.className = 'toast', 3500);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[ch]));
}
