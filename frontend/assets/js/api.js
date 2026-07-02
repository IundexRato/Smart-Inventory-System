// frontend/assets/js/api.js
// v3.0: factory de CRUD elimina repetição; saida adicionada em lotes

function getApiBase() {
    if (window.location.protocol === 'file:') {
        const parts = window.location.pathname.replace(/\\/g, '/').split('/');
        const frontendIndex = parts.lastIndexOf('frontend');
        const projectFolder = frontendIndex > 0
            ? parts[frontendIndex - 1]
            : 'Smart-Inventory-System-main';
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

/**
 * Factory de CRUD padrão — gera list/get/create/update/delete para um recurso.
 * Evita repetir as mesmas 5 linhas para cada entidade.
 */
function crud(resource) {
    return {
        list:   ()           => request('GET',    `/api/${resource}`),
        get:    (id)         => request('GET',    `/api/${resource}/${id}`),
        create: (data)       => request('POST',   `/api/${resource}`, data),
        update: (id, data)   => request('PUT',    `/api/${resource}/${id}`, data),
        delete: (id)         => request('DELETE', `/api/${resource}/${id}`),
    };
}

const api = {
    dashboard: {
        get: () => request('GET', '/api/dashboard'),
    },

    lotes: {
        ...crud('lotes'),
        // Sobrescreve list para aceitar filtro de status
        list:  (status = '') => request('GET', `/api/lotes${status ? '?status=' + encodeURIComponent(status) : ''}`),
        saida: (id, data)    => request('POST', `/api/lotes/${id}/saida`, data),
    },

    combos: {
        ...crud('combos'),
        list:   (status = '') => request('GET', `/api/combos${status ? '?status=' + encodeURIComponent(status) : ''}`),
        aprovar: (id, aprovadoPor) => request('PUT', `/api/combos/${id}/aprovar`, { aprovado_por: aprovadoPor }),
    },

    produtos:    crud('produtos'),
    categorias:  crud('categorias'),

    fornecedores: {
        ...crud('fornecedores'),
        list: (ativo = null) => request('GET', `/api/fornecedores${ativo !== null ? '?ativo=' + ativo : ''}`),
    },

    saidas: {
        list:    ()   => request('GET', '/api/saidas'),
        byLote:  (id) => request('GET', `/api/saidas/lote/${id}`),
    },

    vendas: {
        historico:    (filtros = {}) => request('GET', `/api/vendas/historico${buildQs(filtros)}`),
        resumoMensal: (meses = 12)   => request('GET', `/api/vendas/resumo-mensal?meses=${meses}`),
        topProdutos:  (params = {})  => request('GET', `/api/vendas/top-produtos${buildQs(params)}`),
    },

    alertas: {
        list:   (apenasNaoEnviados = false) => request('GET', `/api/alertas${apenasNaoEnviados ? '?enviado=0' : ''}`),
        marcar: (id) => request('PUT', `/api/alertas/${id}/marcar`),
    },
};

export default api;