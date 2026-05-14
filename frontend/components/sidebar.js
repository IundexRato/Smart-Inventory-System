// frontend/components/sidebar.js
// Injeta a sidebar compartilhada em todas as paginas.

function pageUrl(path) {
    return new URL(`../${path}`, import.meta.url).pathname;
}

export function renderSidebar(activePage = '') {
    const links = [
        { id: 'dashboard', icon: '▣', label: 'Dashboard', href: pageUrl('index.html') },
        { id: 'lotes', icon: '□', label: 'Lotes', href: pageUrl('pages/lotes.html') },
        { id: 'combos', icon: '+', label: 'Combos', href: pageUrl('pages/combos.html') },
        { id: 'produtos', icon: '#', label: 'Produtos', href: pageUrl('pages/produtos.html') },
        { id: 'categorias', icon: '/', label: 'Categorias', href: pageUrl('pages/categorias.html') },
        { id: 'alertas', icon: '!', label: 'Alertas', href: pageUrl('pages/alertas.html') },
    ];

    const nav = links.map(l => `
        <a href="${l.href}" class="${l.id === activePage ? 'active' : ''}">
            ${l.icon} ${l.label}
        </a>
    `).join('');

    const html = `
        <aside class="sidebar">
            <div class="sidebar-logo">
                SMART<br>INVENTORY
                <span>Sistema v2.0</span>
            </div>
            <nav class="sidebar-nav">
                ${nav}
            </nav>
            <div class="sidebar-footer">
                ${new Date().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}<br>
                PHP - MySQL - REST API
            </div>
        </aside>
    `;

    const layout = document.querySelector('.layout');
    if (layout) layout.insertAdjacentHTML('afterbegin', html);
}
