// frontend/assets/js/utils.js
// Funções utilitárias compartilhadas por todas as páginas.
// Centraliza o que antes estava copiado em cada arquivo JS individualmente.

/**
 * Escapa caracteres HTML especiais para evitar XSS.
 * Usado sempre que inserimos texto de usuário/banco no DOM via innerHTML.
 */
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[c]));
}

/**
 * Exibe um toast de feedback no canto da tela.
 * @param {string} elementId  — id do elemento .toast no HTML
 * @param {string} message    — texto a exibir
 * @param {'success'|'error'} type — estilo visual
 */
export function toast(elementId, message, type = 'success') {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.textContent = message;
    el.className = `toast show ${type}`;
    setTimeout(() => { el.className = 'toast'; }, 3500);
}

/**
 * Formata número no padrão pt-BR.
 * @param {number|string} value
 * @param {number} decimais — casas decimais (padrão 2)
 */
export function formatNumber(value, decimais = 2) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: decimais,
        maximumFractionDigits: decimais,
    });
}

/**
 * Formata data ISO (YYYY-MM-DD ou YYYY-MM-DDTHH:mm:ss) para dd/mm/aaaa.
 * @param {string} iso
 */
export function formatData(iso) {
    if (!iso) return '—';
    const str = String(iso).split('T')[0];
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y}`;
}

/**
 * Formata valor monetário BRL (alias de formatNumber com 2 casas).
 * Mantido separado para semântica clara nos templates.
 */
export function formatMoeda(value) {
    return Number(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}