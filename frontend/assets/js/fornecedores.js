// frontend/assets/js/fornecedores.js
import api from './api.js?v=5';
import { renderSidebar } from '../../components/sidebar.js';
import { loadingHTML, errorHTML, emptyRow } from '../../components/statusBadge.js';
import { initTheme } from './theme.js';

let fornecedores = [];
let filtroAtivo  = '';

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('fornecedores');
    initTheme();
    bindEvents();
    await loadFornecedores();
});

function bindEvents() {
    document.getElementById('fornecedor-form').addEventListener('submit', saveFornecedor);
    document.getElementById('cancel-edit').addEventListener('click', resetForm);
    document.getElementById('search-input').addEventListener('input', renderFornecedores);

    document.querySelectorAll('.filter-btn[data-ativo]').forEach(btn =>
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn[data-ativo]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filtroAtivo = btn.dataset.ativo;
            loadFornecedores();
        })
    );
}

async function loadFornecedores() {
    const tbody = document.getElementById('fornecedores-tbody');
    tbody.innerHTML = `<tr><td colspan="10">${loadingHTML()}</td></tr>`;
    try {
        const ativo = filtroAtivo === '' ? null : Number(filtroAtivo);
        fornecedores = await api.fornecedores.list(ativo);
        renderFornecedores();
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10">${errorHTML(e.message)}</td></tr>`;
    }
}

function renderFornecedores() {
    const q     = document.getElementById('search-input').value.toLowerCase();
    const tbody = document.getElementById('fornecedores-tbody');
    const editId = Number(document.getElementById('fornecedor-id').value);

    const filtrados = fornecedores.filter(f =>
        `${f.razao_social} ${f.cnpj ?? ''} ${f.nome_responsavel ?? ''} ${f.email ?? ''}`.toLowerCase().includes(q)
    );

    document.getElementById('fornecedores-count').textContent = `${filtrados.length} fornecedores`;

    tbody.innerHTML = filtrados.length === 0
        ? emptyRow(10)
        : filtrados.map(f => `
            <tr class="${editId === Number(f.id) ? 'editing' : ''}">
                <td style="font-weight:500">${escapeHtml(f.razao_social)}</td>
                <td class="td-mono td-small td-muted">${escapeHtml(f.cnpj ?? '—')}</td>
                <td class="td-small">${escapeHtml(f.nome_responsavel ?? '—')}</td>
                <td class="td-small td-muted">${escapeHtml(f.contato ?? '—')}</td>
                <td class="td-small">${f.email ? `<a href="mailto:${escapeHtml(f.email)}" style="color:var(--accent)">${escapeHtml(f.email)}</a>` : '—'}</td>
                <td class="td-mono td-small">${escapeHtml(f.telefone ?? '—')}</td>
                <td>${statusBadgeFornecedor(f.ativo)}</td>
                <td class="td-mono td-small td-muted">${formatData(f.criado_em)}</td>
                <td class="td-mono td-small td-muted">${formatData(f.atualizado_em)}</td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-secondary btn-small" data-action="edit" data-id="${f.id}">Editar</button>
                        <button class="btn btn-danger btn-small" data-action="delete" data-id="${f.id}">Remover</button>
                    </div>
                </td>
            </tr>
        `).join('');

    tbody.querySelectorAll('[data-action="edit"]').forEach(btn =>
        btn.addEventListener('click', () => editFornecedor(Number(btn.dataset.id)))
    );
    tbody.querySelectorAll('[data-action="delete"]').forEach(btn =>
        btn.addEventListener('click', () => deleteFornecedor(Number(btn.dataset.id)))
    );
}

async function saveFornecedor(e) {
    e.preventDefault();
    const id = document.getElementById('fornecedor-id').value;
    const data = {
        razao_social:     document.getElementById('razao_social').value.trim(),
        cnpj:             document.getElementById('cnpj').value.trim() || null,
        nome_responsavel: document.getElementById('nome_responsavel').value.trim() || null,
        contato:          document.getElementById('contato').value.trim() || null,
        email:            document.getElementById('email').value.trim() || null,
        telefone:         document.getElementById('telefone').value.trim() || null,
        ativo:            Number(document.getElementById('ativo').value),
    };

    try {
        if (id) {
            await api.fornecedores.update(id, data);
            toast('Fornecedor atualizado.');
        } else {
            await api.fornecedores.create(data);
            toast('Fornecedor cadastrado.');
        }
        resetForm();
        await loadFornecedores();
    } catch (err) {
        toast(err.message, 'error');
    }
}

function editFornecedor(id) {
    const f = fornecedores.find(x => Number(x.id) === id);
    if (!f) return;

    document.getElementById('fornecedor-id').value      = f.id;
    document.getElementById('razao_social').value       = f.razao_social;
    document.getElementById('cnpj').value               = f.cnpj ?? '';
    document.getElementById('nome_responsavel').value   = f.nome_responsavel ?? '';
    document.getElementById('contato').value            = f.contato ?? '';
    document.getElementById('email').value              = f.email ?? '';
    document.getElementById('telefone').value           = f.telefone ?? '';
    document.getElementById('ativo').value              = String(f.ativo);
    document.getElementById('form-title').textContent   = 'EDITAR FORNECEDOR';
    document.getElementById('cancel-edit').hidden       = false;
    document.getElementById('grupo-status').hidden      = false;

    renderFornecedores();
    document.getElementById('fornecedor-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteFornecedor(id) {
    const f = fornecedores.find(x => Number(x.id) === id);
    if (!f || !confirm(`Remover "${f.razao_social}"?\n\nSó é possível remover fornecedores sem lotes vinculados.`)) return;
    try {
        await api.fornecedores.delete(id);
        toast('Fornecedor removido.');
        await loadFornecedores();
    } catch (err) {
        toast(err.message, 'error');
    }
}

function resetForm() {
    document.getElementById('fornecedor-form').reset();
    document.getElementById('fornecedor-id').value    = '';
    document.getElementById('form-title').textContent = 'NOVO FORNECEDOR';
    document.getElementById('cancel-edit').hidden     = true;
    document.getElementById('grupo-status').hidden    = true;
    document.getElementById('ativo').value            = '1';
    renderFornecedores();
}

function statusBadgeFornecedor(ativo) {
    return ativo
        ? '<span class="status s-APROVADO">ATIVO</span>'
        : '<span class="status s-ENCERRADO">ENCERRADO</span>';
}

function formatData(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('pt-BR');
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
