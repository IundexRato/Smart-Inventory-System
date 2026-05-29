// frontend/assets/js/theme.js
// Gerencia alternância dark/light e injeta o botão no page-header.
// Importado por todos os JS de página.

const STORAGE_KEY = 'sis-theme';

export function initTheme() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'light') document.body.classList.add('light');
    _injectButton();
}

function _injectButton() {
    // Aguarda o DOM estar pronto (sidebar já injetada)
    const header = document.querySelector('.page-header');
    if (!header) return;

    // Não duplica se já existir
    if (header.querySelector('.theme-toggle')) return;

    const btn = document.createElement('button');
    btn.className = 'theme-toggle';
    btn.title = 'Alternar tema';
    btn.setAttribute('aria-label', 'Alternar tema claro/escuro');
    _updateIcon(btn);

    btn.addEventListener('click', () => {
        document.body.classList.toggle('light');
        const isLight = document.body.classList.contains('light');
        localStorage.setItem(STORAGE_KEY, isLight ? 'light' : 'dark');
        _updateIcon(btn);
    });

    // Insere à esquerda do badge-live ou card-count (último filho do header)
    const lastChild = header.lastElementChild;
    header.insertBefore(btn, lastChild);
}

function _updateIcon(btn) {
    const isLight = document.body.classList.contains('light');
    // Sol no modo escuro (clica para ir ao claro), Lua no modo claro (clica para ir ao escuro)
    btn.textContent = isLight ? '🌙' : '☀️';
}
