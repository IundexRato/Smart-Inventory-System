// frontend/components/statusBadge.js
// Responsabilidade: funções puras de renderização de elementos reutilizáveis
// Nenhuma função aqui faz fetch — só recebe dados e devolve HTML string

// Badge de status colorido
export function statusBadge(status) {
    return `<span class="status s-${status}">${status}</span>`;
}

// Barra de progresso de dias restantes
export function diasBar(dias) {
    const max = 30;
    const pct = Math.max(0, Math.min(100, (dias / max) * 100));
    const cor = dias <= 3  ? '#ef4444'
              : dias <= 9  ? '#f97316'
              : dias <= 30 ? '#f59e0b'
              : '#22c55e';
    return `
        <div class="dias-bar">
            <div class="bar-bg">
                <div class="bar-fill" style="width:${pct}%;background:${cor}"></div>
            </div>
            <div class="dias-num">${dias}d</div>
        </div>
    `;
}

// Tag de preço
export function tagPreco(valor) {
    return `<span class="tag preco">R$ ${formatMoeda(valor)}</span>`;
}

// Tag de desconto
export function tagDesconto(pct) {
    return `<span class="tag desconto">-${pct}%</span>`;
}

// Tag enviado/pendente
export function tagEnviado(enviado) {
    return enviado
        ? `<span class="tag enviado">✓ Enviado</span>`
        : `<span class="tag pendente">⏳ Pendente</span>`;
}

// Formata moeda BRL
export function formatMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Formata data pt-BR
export function formatData(isoDate) {
    if (!isoDate) return '—';
    return new Date(isoDate + 'T00:00:00').toLocaleDateString('pt-BR');
}

// Estado de loading para um container
export function loadingHTML() {
    return `<div class="loading">Carregando...</div>`;
}

// Estado de erro
export function errorHTML(msg = 'Erro ao carregar dados') {
    return `<div class="error-msg">⚠ ${msg}</div>`;
}

// Linha vazia de tabela
export function emptyRow(cols, msg = 'Nenhum registro encontrado') {
    return `<tr><td colspan="${cols}" style="text-align:center;color:var(--muted);padding:2rem;font-size:.82rem">${msg}</td></tr>`;
}
