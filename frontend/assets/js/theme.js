// frontend/assets/js/theme.js
// Gerencia alternância dark/light e injeta o botão no page-header.

const STORAGE_KEY = 'sis-theme';

export function initTheme() {
    const saved = localStorage.getItem(STORAGE_KEY);

    if (saved === 'light') {
        document.body.classList.add('light');
    }

    _injectButton();
}

function _injectButton() {
    const header = document.querySelector('.page-header');
    if (!header) return;

    // Evita duplicação
    if (header.querySelector('.theme-toggle')) return;

    const btn = document.createElement('button');
    btn.className = 'theme-toggle';
    btn.title = 'Alternar tema';
    btn.setAttribute('aria-label', 'Alternar tema claro/escuro');

    _updateIcon(btn);

    btn.addEventListener('click', () => {
        document.body.classList.toggle('light');

        const isLight = document.body.classList.contains('light');

        localStorage.setItem(
            STORAGE_KEY,
            isLight ? 'light' : 'dark'
        );

        _updateIcon(btn);
    });

    // Procura badge existente (LIVE, contador etc.)
    const lastChild = header.lastElementChild;

    // Cria container de ações
    const actions = document.createElement('div');
    actions.className = 'header-actions';

    if (lastChild) {
        header.removeChild(lastChild);
        actions.appendChild(btn);
        actions.appendChild(lastChild);
    } else {
        actions.appendChild(btn);
    }

    header.appendChild(actions);
}

function _updateIcon(btn) {
    const isLight = document.body.classList.contains('light');

    btn.textContent = isLight ? '🌙' : '☀️';
}