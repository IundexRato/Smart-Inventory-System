// frontend/assets/js/searchableSelect.js
// Transforma um <select> oculto num input de busca com dropdown.
// Correção v2: armazena o valor selecionado numa variável interna em vez de
// depender de original.value (que ignora .value = x quando não há <option> x).

export function createSearchableSelect(selectId, placeholder = 'Buscar...') {
    const original = document.getElementById(selectId);
    if (!original) return null;

    // Esconde o select original e desativa validação nativa (feita pelo JS)
    original.style.display = 'none';
    original.required = false;

    // Cria wrapper
    const wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
    original.parentNode.insertBefore(wrap, original);
    wrap.appendChild(original);

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'ss-input';
    input.placeholder = placeholder;
    input.autocomplete = 'off';

    const dropdown = document.createElement('div');
    dropdown.className = 'ss-dropdown';

    wrap.insertBefore(input, original);
    wrap.appendChild(dropdown);

    let options      = [];    // [{ value, label }]
    let selectedValue = null; // valor interno — não depende do <select>

    function render(q = '') {
        const term     = q.toLowerCase();
        const filtered = options.filter(o => o.label.toLowerCase().includes(term));

        dropdown.innerHTML = filtered.length === 0
            ? `<div class="ss-empty">Nenhum resultado</div>`
            : filtered.map(o => `
                <div class="ss-option ${String(selectedValue) === String(o.value) ? 'selected' : ''}"
                     data-value="${esc(String(o.value))}">
                    ${esc(o.label)}
                </div>`).join('');

        dropdown.querySelectorAll('.ss-option').forEach(el => {
            el.addEventListener('mousedown', e => {
                e.preventDefault();
                pickByValue(el.dataset.value);
            });
        });
    }

    function pickByValue(value) {
        const opt = options.find(o => String(o.value) === String(value));
        if (!opt) return;
        selectedValue = opt.value;   // ← fonte da verdade
        input.value   = opt.label;
        // Sincroniza o <select> inserindo/atualizando uma <option> temporária
        syncSelectOption(opt.value, opt.label);
        original.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }

    // Garante que o <select> tenha uma <option> com o valor correto
    // para compatibilidade com código legado que leia original.value
    function syncSelectOption(value, label) {
        let opt = original.querySelector(`option[value="${CSS.escape(String(value))}"]`);
        if (!opt) {
            opt = document.createElement('option');
            opt.value = String(value);
            original.appendChild(opt);
        }
        opt.textContent = label;
        original.value  = String(value);
    }

    function open() {
        wrap.classList.add('open');
        render(input.value);
    }

    function close() {
        wrap.classList.remove('open');
    }

    input.addEventListener('focus', open);

    input.addEventListener('input', () => {
        if (!wrap.classList.contains('open')) open();
        render(input.value);
        if (input.value === '') {
            selectedValue  = null;
            original.value = '';
        }
    });

    input.addEventListener('blur', () => setTimeout(close, 150));

    document.addEventListener('click', e => {
        if (!wrap.contains(e.target)) close();
    });

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c =>
            ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[c]));
    }

    return {
        setOptions(list) {
            options = list;
            render();
        },

        // Retorna o valor selecionado (fonte da verdade: variável interna)
        getValue() {
            return selectedValue !== null ? selectedValue : null;
        },

        setValue(value, label) {
            const opt = options.find(o => String(o.value) === String(value));
            if (opt) {
                pickByValue(opt.value);
            } else if (value !== null && value !== undefined && label) {
                selectedValue = value;
                input.value   = label;
                syncSelectOption(value, label);
            }
        },

        reset() {
            selectedValue  = null;
            input.value    = '';
            original.value = '';
            // Remove a option temporária para manter o select limpo
            Array.from(original.options).forEach(o => o.remove());
        },
    };
}
