// frontend/assets/js/combos.js
import api from './api.js';
import { renderSidebar } from '../../components/sidebar.js';
import { statusBadge, loadingHTML, errorHTML, emptyRow, formatMoeda, formatData } from '../../components/statusBadge.js';
import { initTheme } from './theme.js';
import { createSearchableSelect } from './searchableSelect.js';

let statusAtivo = '';
let combos      = [];
let lotes       = [];
let produtos    = [];
let ssLote      = null;
let ssParceiro  = null;

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('combos');
    initTheme();
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

    // Searchable select para lote
    ssLote = createSearchableSelect('lote_id', 'Buscar lote por produto ou código...');
    ssLote.setOptions(lotes.map(l => ({
        value: l.id,
        label: `${l.codigo_lote} — ${l.produto_nome} (${l.sku})`,
    })));

    // Searchable select para produto parceiro (opcional)
    ssParceiro = createSearchableSelect('produto_parceiro_id', 'Buscar por nome ou SKU (opcional)...');
    ssParceiro.setOptions(produtos.map(p => ({
        value: p.id,
        label: `${p.nome} (${p.sku})`,
    })));
}

async function loadCombos() {
    const tbody = document.getElementById('combos-tbody');
    tbody.innerHTML = `<tr><td colspan="9">${loadingHTML()}</td></tr>`;
    try {
        combos = await api.combos.list(statusAtivo);
        document.getElementById('combos-count').textContent = `${combos.length} combos`;
        const editId = document.getElementById('combo-id').value;
        tbody.innerHTML = combos.length === 0
            ? emptyRow(9)
            : combos.map(c => `
                <tr class="${editId == c.id ? 'editing' : ''}">
                    <td class="td-mono td-small">${escapeHtml(c.codigo_lote ?? c.lote_id)}</td>
                    <td>
                        <div style="font-weight:500">${escapeHtml(c.produto_origem)}</div>
                        <div class="td-mono td-muted td-small">${escapeHtml(c.sku)}</div>
                    </td>
                    <td style="font-weight:500">${c.produto_parceiro ? escapeHtml(c.produto_parceiro) : '<span class="td-muted">—</span>'}</td>
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
            btn.addEventListener('click', () => aprovarCombo(Number(btn.dataset.id))));
        tbody.querySelectorAll('[data-action="edit"]').forEach(btn =>
            btn.addEventListener('click', () => editCombo(Number(btn.dataset.id))));
        tbody.querySelectorAll('[data-action="delete"]').forEach(btn =>
            btn.addEventListener('click', () => deleteCombo(Number(btn.dataset.id))));
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9">${errorHTML(e.message)}</td></tr>`;
    }
}

async function saveCombo(event) {
    event.preventDefault();
    const id        = document.getElementById('combo-id').value;
    const loteId    = ssLote?.getValue();
    const parceiroId = ssParceiro?.getValue() || null;

    if (!loteId) { toast('Selecione um lote.', 'error'); return; }

    const desconto   = Number(document.getElementById('desconto_combo').value || 0);
    const precoRaw   = document.getElementById('preco_combo').value.trim();
    // Preço vazio → backend calcula automaticamente
    const precoCombo = precoRaw !== '' ? Number(precoRaw) : undefined;

    const data = {
        lote_id:             Number(loteId),
        produto_parceiro_id: parceiroId ? Number(parceiroId) : null,
        desconto_combo:      desconto,
        status:              document.getElementById('status').value,
        valido_ate:          document.getElementById('valido_ate').value,
    };
    if (precoCombo !== undefined) data.preco_combo = precoCombo;

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

    document.getElementById('combo-id').value          = c.id;
    document.getElementById('desconto_combo').value    = c.desconto_combo;
    document.getElementById('preco_combo').value       = c.preco_combo;
    document.getElementById('status').value            = c.status;
    document.getElementById('valido_ate').value        = c.valido_ate;
    document.getElementById('form-title').textContent  = 'EDITAR COMBO';
    document.getElementById('cancel-edit').hidden      = false;

    ssLote?.setValue(c.lote_id, `${c.codigo_lote} — ${c.produto_origem} (${c.sku})`);
    if (c.produto_parceiro_id) {
        ssParceiro?.setValue(c.produto_parceiro_id, c.produto_parceiro);
    } else {
        ssParceiro?.reset();
    }

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
    document.getElementById('combo-id').value         = '';
    document.getElementById('form-title').textContent = 'NOVO COMBO';
    document.getElementById('cancel-edit').hidden     = true;
    ssLote?.reset();
    ssParceiro?.reset();
    loadCombos();
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

function formatNumber(value) {
    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}