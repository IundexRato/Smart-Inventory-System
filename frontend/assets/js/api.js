// frontend/assets/js/api.js
// v2.0: adicionados endpoints de fornecedores (CRUD completo) e vendas/histórico

function getApiBase() {
    if (window.location.protocol === 'file:') {
        const parts = window.location.pathname.replace(/\\/g, '/').split('/');
        const frontendIndex = parts.lastIndexOf('frontend');
        const projectFolder = frontendIndex > 0 ? parts[frontendIndex - 1] : 'Smart-Inventory-System-main';
        return `http://localhost/${projectFolder}/backend/public`;
    }
    return new URL('../../../backend/public', import.meta.url).pathname;
}

const API_BASE = getApiBase();

function buildInvalidJsonError(res, endpoint, text) {
    const prefix = `API ${endpoint} retornou uma resposta invalida (HTTP ${res.status})`;
    if (text.trim().startsWith('<')) {
        return `${prefix}: veio HTML em vez de JSON. Verifique se o Apache esta usando os arquivos .htaccess do backend.`;
    }
    return prefix;
}

async function parseJsonResponse(res, endpoint) {
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch {
        throw new Error(buildInvalidJsonError(res, endpoint, text));
    }
}

async function request(method, endpoint, body = null) {
    const options = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) options.body = JSON.stringify(body);
    const res  = await fetch(`${API_BASE}${endpoint}`, options);
    const json = await parseJsonResponse(res, endpoint);
    if (!res.ok || !json.success) throw new Error(json.error || `Erro HTTP ${res.status}`);
    return json.data;
}

function buildQs(params = {}) {
    const p = Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== '');
    return p.length ? '?' + new URLSearchParams(p).toString() : '';
}

const api = {
    dashboard: {
        get: () => request('GET', '/api/dashboard'),
    },

    lotes: {
        list: (status = '') => request('GET', `/api/lotes${status ? '?status=' + encodeURIComponent(status) : ''}`),
        get:    (id)         => request('GET', `/api/lotes/${id}`),
        create: (data)       => request('POST', '/api/lotes', data),
        update: (id, data)   => request('PUT', `/api/lotes/${id}`, data),
        delete: (id)         => request('DELETE', `/api/lotes/${id}`),
    },

    combos: {
        list: (status = '') => request('GET', `/api/combos${status ? '?status=' + encodeURIComponent(status) : ''}`),
        get:    (id)         => request('GET', `/api/combos/${id}`),
        create: (data)       => request('POST', '/api/combos', data),
        update: (id, data)   => request('PUT', `/api/combos/${id}`, data),
        aprovar:(id, aprovadoPor) => request('PUT', `/api/combos/${id}/aprovar`, { aprovado_por: aprovadoPor }),
        delete: (id)         => request('DELETE', `/api/combos/${id}`),
    },

    produtos: {
        list: ()             => request('GET', '/api/produtos'),
        get:  (id)           => request('GET', `/api/produtos/${id}`),
        create: (data)       => request('POST', '/api/produtos', data),
        update: (id, data)   => request('PUT', `/api/produtos/${id}`, data),
        delete: (id)         => request('DELETE', `/api/produtos/${id}`),
    },

    categorias: {
        list: ()             => request('GET', '/api/categorias'),
        get:  (id)           => request('GET', `/api/categorias/${id}`),
        create: (data)       => request('POST', '/api/categorias', data),
        update: (id, data)   => request('PUT', `/api/categorias/${id}`, data),
        delete: (id)         => request('DELETE', `/api/categorias/${id}`),
    },

    fornecedores: {
        list: (ativo = null) => request('GET', `/api/fornecedores${ativo !== null ? '?ativo=' + ativo : ''}`),
        get:  (id)           => request('GET', `/api/fornecedores/${id}`),
        create: (data)       => request('POST', '/api/fornecedores', data),
        update: (id, data)   => request('PUT', `/api/fornecedores/${id}`, data),
        delete: (id)         => request('DELETE', `/api/fornecedores/${id}`),
    },

    vendas: {
        /**
         * Histórico de vendas — todos os params são opcionais
         * @param {object} filtros { data_ini, data_fim, mes, ano, produto_id, order }
         */
        historico: (filtros = {}) => request('GET', `/api/vendas/historico${buildQs(filtros)}`),
        resumoMensal: (meses = 12) => request('GET', `/api/vendas/resumo-mensal?meses=${meses}`),
        topProdutos: (params = {}) => request('GET', `/api/vendas/top-produtos${buildQs(params)}`),
    },

    alertas: {
        list:   (apenasNaoEnviados = false) => request('GET', `/api/alertas${apenasNaoEnviados ? '?enviado=0' : ''}`),
        marcar: (id) => request('PUT', `/api/alertas/${id}/marcar`),
    },
};

export default api;
