// frontend/assets/js/combos.js
import api from './api.js?v=4';
import { renderSidebar } from '../../components/sidebar.js?v=4';
import { statusBadge, loadingHTML, errorHTML, emptyRow, formatMoeda, formatData } from '../../components/statusBadge.js?v=3';

let statusAtivo = '';
let combos = [];
let lotes = [];
let produtos = [];

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('combos');
    setupFiltros();
    bindForm();
    await loadRefs();
    await loadCombos();
});

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

function bindForm() {
    document.getElementById('combo-form').addEventListener('submit', saveCombo);
    document.getElementById('cancel-edit').addEventListener('click', resetForm);
}

async function loadRefs() {
    [lotes, produtos] = await Promise.all([api.lotes.list(), api.produtos.list()]);

    document.getElementById('lote_id').innerHTML = '<option value="">Selecione...</option>' + lotes.map(l =>
        `<option value="${l.id}">${escapeHtml(l.codigo_lote)} - ${escapeHtml(l.produto_nome)} (${escapeHtml(l.sku)})</option>`
    ).join('');

    document.getElementById('produto_parceiro_id').innerHTML = '<option value="">Selecione...</option>' + produtos.map(p =>
        `<option value="${p.id}">${escapeHtml(p.nome)} (${escapeHtml(p.sku)})</option>`
    ).join('');
}

async function loadCombos() {
    const tbody = document.getElementById('combos-tbody');
    tbody.innerHTML = `<tr><td colspan="9">${loadingHTML()}</td></tr>`;
    try {
        combos = await api.combos.list(statusAtivo);
        document.getElementById('combos-count').textContent = `${combos.length} combos`;
        tbody.innerHTML = combos.length === 0
            ? emptyRow(9)
            : combos.map(c => `
                <tr class="${document.getElementById('combo-id').value == c.id ? 'editing' : ''}">
                    <td class="td-mono td-small">${escapeHtml(c.codigo_lote ?? c.lote_id)}</td>
                    <td>
                        <div style="font-weight:500">${escapeHtml(c.produto_origem)}</div>
                        <div class="td-mono td-muted td-small">${escapeHtml(c.sku)}</div>
                    </td>
                    <td style="font-weight:500">${escapeHtml(c.produto_parceiro)}</td>
                    <td class="td-mono td-small">R$ ${formatMoeda(c.preco_combo)}</td>
                    <td class="td-mono td-small">${formatNumber(c.desconto_combo)}%</td>
                    <td>${statusBadge(c.status)}</td>
                    <td class="td-mono td-small">${formatData(c.valido_ate)}</td>
                    <td class="td-mono td-small">${c.dias_validade}d</td>
                    <td>
                        <div class="td-actions">
                            ${c.status === 'PENDENTE' ? `<button class="btn btn-primary btn-small" data-action="aprovar" data-id="${c.id}">Aprovar</button>` : ''}
                            <button class="btn btn-secondary btn-small" data-action="edit" data-id="${c.id}">Editar</button>
                            <button class="btn btn-danger btn-small" data-action="delete" data-id="${c.id}">Remover</button>
                        </div>
                    </td>
                </tr>
            `).join('');

        tbody.querySelectorAll('[data-action="aprovar"]').forEach(btn =>
            btn.addEventListener('click', () => aprovarCombo(Number(btn.dataset.id)))
        );
        tbody.querySelectorAll('[data-action="edit"]').forEach(btn =>
            btn.addEventListener('click', () => editCombo(Number(btn.dataset.id)))
        );
        tbody.querySelectorAll('[data-action="delete"]').forEach(btn =>
            btn.addEventListener('click', () => deleteCombo(Number(btn.dataset.id)))
        );
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9">${errorHTML(e.message)}</td></tr>`;
    }
}

async function saveCombo(event) {
    event.preventDefault();
    const id = document.getElementById('combo-id').value;
    const data = {
        lote_id: Number(document.getElementById('lote_id').value),
        produto_parceiro_id: Number(document.getElementById('produto_parceiro_id').value),
        desconto_combo: Number(document.getElementById('desconto_combo').value || 0),
        preco_combo: Number(document.getElementById('preco_combo').value),
        status: document.getElementById('status').value,
        valido_ate: document.getElementById('valido_ate').value,
    };

    try {
        if (id) {
            await api.combos.update(id, data);
            toast('Combo atualizado.');
        } else {
            await api.combos.create(data);
            toast('Combo criado.');
        }
        resetForm();
        await loadCombos();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function editCombo(id) {
    const c = combos.find(item => Number(item.id) === id);
    if (!c) return;

    document.getElementById('combo-id').value = c.id;
    document.getElementById('lote_id').value = c.lote_id;
    document.getElementById('produto_parceiro_id').value = c.produto_parceiro_id;
    document.getElementById('desconto_combo').value = c.desconto_combo;
    document.getElementById('preco_combo').value = c.preco_combo;
    document.getElementById('status').value = c.status;
    document.getElementById('valido_ate').value = c.valido_ate;
    document.getElementById('form-title').textContent = 'EDITAR COMBO';
    document.getElementById('cancel-edit').hidden = false;
    loadCombos();
    document.getElementById('combo-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function aprovarCombo(id) {
    try {
        await api.combos.aprovar(id, 'gerente');
        toast('Combo aprovado.');
        await loadCombos();
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function deleteCombo(id) {
    if (!confirm('Remover este combo?')) return;

    try {
        await api.combos.delete(id);
        toast('Combo removido.');
        await loadCombos();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function resetForm() {
    document.getElementById('combo-form').reset();
    document.getElementById('combo-id').value = '';
    document.getElementById('form-title').textContent = 'NOVO COMBO';
    document.getElementById('cancel-edit').hidden = true;
    loadCombos();
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

function formatNumber(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
