// frontend/assets/js/categorias.js
import api from './api.js';
import { renderSidebar } from '../../components/sidebar.js';
import { initTheme } from './theme.js';
import { loadingHTML, errorHTML, emptyRow } from '../../components/statusBadge.js';

let categorias = [];

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('categorias');
    initTheme();
    document.getElementById('categoria-form').addEventListener('submit', saveCategoria);
    document.getElementById('cancel-edit').addEventListener('click', resetForm);
    await loadCategorias();
});

async function loadCategorias() {
    const tbody = document.getElementById('categorias-tbody');
    tbody.innerHTML = `<tr><td colspan="4">${loadingHTML()}</td></tr>`;

    try {
        categorias = await api.categorias.list();
        document.getElementById('categorias-count').textContent = `${categorias.length} categorias`;
        tbody.innerHTML = categorias.length === 0
            ? emptyRow(4)
            : categorias.map(c => `
                <tr class="${document.getElementById('categoria-id').value == c.id ? 'editing' : ''}">
                    <td style="font-weight:500">${escapeHtml(c.nome)}</td>
                    <td><span class="tag">${escapeHtml(c.prefixo)}</span></td>
                    <td class="td-mono td-small" style="text-align:center">${c.total_produtos}</td>
                    <td>
                        <div class="td-actions">
                            <button class="btn btn-secondary btn-small" data-action="edit" data-id="${c.id}">Editar</button>
                            <button class="btn btn-danger btn-small" data-action="delete" data-id="${c.id}">Remover</button>
                        </div>
                    </td>
                </tr>
            `).join('');

        tbody.querySelectorAll('[data-action="edit"]').forEach(btn =>
            btn.addEventListener('click', () => editCategoria(Number(btn.dataset.id)))
        );
        tbody.querySelectorAll('[data-action="delete"]').forEach(btn =>
            btn.addEventListener('click', () => deleteCategoria(Number(btn.dataset.id)))
        );
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="4">${errorHTML(e.message)}</td></tr>`;
    }
}

async function saveCategoria(event) {
    event.preventDefault();
    const id = document.getElementById('categoria-id').value;
    const data = {
        nome: document.getElementById('nome').value.trim(),
        prefixo: document.getElementById('prefixo').value.trim().toUpperCase(),
    };

    try {
        if (id) {
            await api.categorias.update(id, data);
            toast('Categoria atualizada.');
        } else {
            await api.categorias.create(data);
            toast('Categoria criada.');
        }
        resetForm();
        await loadCategorias();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function editCategoria(id) {
    const c = categorias.find(item => Number(item.id) === id);
    if (!c) return;

    document.getElementById('categoria-id').value = c.id;
    document.getElementById('nome').value = c.nome;
    document.getElementById('prefixo').value = c.prefixo;
    document.getElementById('form-title').textContent = 'EDITAR CATEGORIA';
    document.getElementById('cancel-edit').hidden = false;
    loadCategorias();
}

async function deleteCategoria(id) {
    const c = categorias.find(item => Number(item.id) === id);
    if (!c || !confirm(`Remover categoria "${c.nome}"?`)) return;

    try {
        await api.categorias.delete(id);
        toast('Categoria removida.');
        await loadCategorias();
    } catch (e) {
        toast(e.message, 'error');
    }
}

function resetForm() {
    document.getElementById('categoria-form').reset();
    document.getElementById('categoria-id').value = '';
    document.getElementById('form-title').textContent = 'NOVA CATEGORIA';
    document.getElementById('cancel-edit').hidden = true;
    loadCategorias();
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