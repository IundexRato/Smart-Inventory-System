// frontend/assets/js/historico.js
import api from './api.js';
import { renderSidebar } from '../../components/sidebar.js';
import { loadingHTML, errorHTML, emptyRow, formatMoeda } from '../../components/statusBadge.js';
import { initTheme } from './theme.js';

let ordenacao = null; // null = sem ordenação ativa, só data DESC
let historico = [];

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('historico');
    initTheme();
    await carregarProdutos();
    bindEvents();
    await filtrar();
});

async function carregarProdutos() {
    try {
        const produtos = await api.produtos.list();
        const sel = document.getElementById('f-produto');
        produtos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.nome} (${p.sku})`;
            sel.appendChild(opt);
        });
    } catch (_) {}
}

function bindEvents() {
    document.getElementById('btn-filtrar').addEventListener('click', filtrar);
    document.getElementById('btn-limpar').addEventListener('click', limparFiltros);

    document.querySelectorAll('.ordem-btn').forEach(btn =>
        btn.addEventListener('click', () => {
            const novo = btn.dataset.order;
            if (ordenacao === novo) {
                // clica de novo → desativa
                ordenacao = null;
                document.querySelectorAll('.ordem-btn').forEach(b => b.classList.remove('active'));
            } else {
                ordenacao = novo;
                document.querySelectorAll('.ordem-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }
            filtrar();
        })
    );
    // Nenhum botão ativo por padrão
}

async function filtrar() {
    const tbody = document.getElementById('historico-tbody');
    tbody.innerHTML = `<tr><td colspan="8">${loadingHTML()}</td></tr>`;

    const filtros = {
        data_ini:   document.getElementById('f-data-ini').value || null,
        data_fim:   document.getElementById('f-data-fim').value || null,
        mes:        document.getElementById('f-mes').value || null,
        ano:        document.getElementById('f-ano').value || null,
        produto_id: document.getElementById('f-produto').value || null,
        order:      ordenacao,  // null = backend só ordena por data
    };

    try {
        historico = await api.vendas.historico(filtros);
        renderHistorico();
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8">${errorHTML(e.message)}</td></tr>`;
    }
}

function renderHistorico() {
    const tbody = document.getElementById('historico-tbody');

    const totalQtd    = historico.reduce((s, r) => s + Number(r.qtd_vendida), 0);
    const totalVendas = historico.reduce((s, r) => s + Number(r.num_vendas), 0);
    const totalRec    = historico.reduce((s, r) => s + Number(r.receita), 0);

    document.getElementById('r-registros').textContent = historico.length;
    document.getElementById('r-qtd').textContent       = formatNum(totalQtd, 0);
    document.getElementById('r-vendas').textContent    = formatNum(totalVendas, 0);
    document.getElementById('r-receita').textContent   = 'R$ ' + formatMoeda(totalRec);
    document.getElementById('historico-count').textContent = `${historico.length} registros`;

    if (historico.length === 0) {
        tbody.innerHTML = emptyRow(8, 'Nenhuma venda encontrada para os filtros selecionados');
        return;
    }

    tbody.innerHTML = historico.map(r => `
        <tr>
            <td class="td-mono td-small">${formatDataBR(r.data_venda)}</td>
            <td style="font-weight:500">${escapeHtml(r.produto_nome)}</td>
            <td><span class="tag">${escapeHtml(r.categoria)}</span></td>
            <td class="td-mono td-small td-muted">${escapeHtml(r.sku)}</td>
            <td class="td-mono td-small" style="text-align:right;color:var(--seguro)">${formatNum(r.qtd_vendida, 0)}</td>
            <td class="td-mono td-small" style="text-align:right">${formatNum(r.num_vendas, 0)}</td>
            <td class="td-mono td-small" style="text-align:right">R$ ${formatMoeda(r.preco_medio)}</td>
            <td class="td-mono td-small" style="text-align:right;color:var(--accent)">R$ ${formatMoeda(r.receita)}</td>
        </tr>
    `).join('');
}

function limparFiltros() {
    document.getElementById('f-data-ini').value = '';
    document.getElementById('f-data-fim').value = '';
    document.getElementById('f-mes').value      = '';
    document.getElementById('f-ano').value      = '';
    document.getElementById('f-produto').value  = '';
    ordenacao = null;
    document.querySelectorAll('.ordem-btn').forEach(b => b.classList.remove('active'));
    filtrar();
}

function formatDataBR(iso) {
    if (!iso) return '—';
    const [y, m, d] = String(iso).split('T')[0].split('-');
    return `${d}/${m}/${y}`;
}

function formatNum(value, decimais = 2) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: decimais, maximumFractionDigits: decimais,
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}