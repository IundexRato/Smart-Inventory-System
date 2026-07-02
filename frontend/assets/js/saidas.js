// frontend/assets/js/saidas.js
import api from './api.js';
import { renderSidebar } from '../../components/sidebar.js';
import { loadingHTML, errorHTML, emptyRow } from '../../components/statusBadge.js';
import { initTheme } from './theme.js';
import { escapeHtml, formatData, formatNumber, toast } from './utils.js';

let saidas    = [];
let lotes     = [];
let lotesMap  = {}; // label → lote completo
let filtroMotivo = '';

document.addEventListener('DOMContentLoaded', async () => {
    renderSidebar('saidas');
    initTheme();
    setupFiltros();
    bindForm();
    await loadRefs();
    await loadSaidas();
    document.getElementById('saidas-search').addEventListener('input', renderSaidas);
});

function setupFiltros() {
    document.getElementById('filtros-motivo').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filtroMotivo = btn.dataset.motivo ?? '';
        renderSaidas();
    });
}

function bindForm() {
    const loteInput   = document.getElementById('lote_input');
    const loteHidden  = document.getElementById('lote_id');
    const preview     = document.getElementById('lote-preview');

    // Sincroniza o hidden e mostra preview do lote selecionado
    loteInput.addEventListener('input', function () {
        const lote = lotesMap[this.value];
        if (lote) {
            loteHidden.value = lote.id;
            preview.style.display = 'block';
            preview.textContent =
                `Estoque atual: ${lote.quantidade} un  ·  ` +
                `Validade: ${formatData(lote.data_validade)}  ·  ` +
                `Status: ${lote.status_validade}`;
        } else {
            loteHidden.value = '';
            preview.style.display = 'none';
        }
    });

    document.getElementById('btn-limpar').addEventListener('click', () => {
        loteHidden.value = '';
        preview.style.display = 'none';
    });

    document.getElementById('saida-form').addEventListener('submit', async e => {
        e.preventDefault();

        const loteId = loteHidden.value;
        if (!loteId) {
            toast('toast', 'Selecione um lote válido da lista.', 'error');
            return;
        }

        const quantidade = Number(document.getElementById('quantidade').value);
        const lote       = lotes.find(l => Number(l.id) === Number(loteId));

        if (quantidade <= 0) {
            toast('toast', 'Informe uma quantidade maior que zero.', 'error');
            return;
        }
        if (lote && quantidade > Number(lote.quantidade)) {
            toast('toast', `Quantidade maior que o estoque disponível (${lote.quantidade}).`, 'error');
            return;
        }

        const data = {
            quantidade,
            motivo:     document.getElementById('motivo').value,
            observacao: document.getElementById('observacao').value.trim(),
        };

        try {
            await api.lotes.saida(loteId, data);
            toast('toast', `Saída de ${quantidade} unidade(s) registrada com sucesso.`);
            e.target.reset();
            loteHidden.value     = '';
            preview.style.display = 'none';
            // Recarrega lotes para refletir novo estoque no datalist
            await loadRefs();
            await loadSaidas();
        } catch (err) {
            toast('toast', err.message, 'error');
        }
    });
}

async function loadRefs() {
    try {
        lotes = await api.lotes.list();
        const dl = document.getElementById('lotes-list');
        // Limpa antes de repopular (pode ser chamado após uma saída)
        dl.innerHTML = '';
        lotesMap = {};

        lotes.forEach(l => {
            const label = `${l.codigo_lote} — ${l.produto_nome} (${l.sku})`;
            lotesMap[label] = l;
            const opt = document.createElement('option');
            opt.value = label;
            dl.appendChild(opt);
        });
    } catch (err) {
        toast('toast', 'Erro ao carregar lotes: ' + err.message, 'error');
    }
}

async function loadSaidas() {
    const tbody = document.getElementById('saidas-tbody');
    tbody.innerHTML = `<tr><td colspan="7">${loadingHTML()}</td></tr>`;
    try {
        saidas = await api.saidas.list();
        renderSaidas();
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7">${errorHTML(e.message)}</td></tr>`;
    }
}

function renderSaidas() {
    const tbody = document.getElementById('saidas-tbody');
    const q     = (document.getElementById('saidas-search')?.value ?? '').toLowerCase();

    const filtrados = saidas.filter(s => {
        const matchTexto  = `${s.produto_nome} ${s.sku} ${s.codigo_lote}`.toLowerCase().includes(q);
        const matchMotivo = filtroMotivo === '' || s.motivo === filtroMotivo;
        return matchTexto && matchMotivo;
    });

    document.getElementById('saidas-count').textContent = `${filtrados.length} registros`;

    if (filtrados.length === 0) {
        tbody.innerHTML = emptyRow(7, 'Nenhuma saída registrada');
        return;
    }

    tbody.innerHTML = filtrados.map(s => `
        <tr>
            <td class="td-mono td-muted td-small">${s.id}</td>
            <td>
                <div style="font-weight:500">${escapeHtml(s.produto_nome)}</div>
                <div class="td-mono td-muted td-small">${escapeHtml(s.sku)}</div>
            </td>
            <td class="td-mono td-small">${escapeHtml(s.codigo_lote)}</td>
            <td>${motivoBadge(s.motivo)}</td>
            <td class="td-mono td-small">${formatNumber(s.quantidade, 3)}</td>
            <td class="td-small td-muted">${escapeHtml(s.observacao || '—')}</td>
            <td class="td-mono td-small">${formatData(s.criado_em)}</td>
        </tr>
    `).join('');
}

function motivoBadge(motivo) {
    const cores = {
        VENCIMENTO: 's-VENCIDO',
        QUEBRA:     's-URGENTE',
        DOACAO:     's-APROVADO',
        AJUSTE:     's-ATENCAO',
        OUTROS:     's-PENDENTE',
    };
    const labels = {
        VENCIMENTO: 'Vencimento',
        QUEBRA:     'Quebra',
        DOACAO:     'Doação',
        AJUSTE:     'Ajuste',
        OUTROS:     'Outros',
    };
    return `<span class="status ${cores[motivo] ?? ''}">${labels[motivo] ?? motivo}</span>`;
}