/**
 * Minimal fetch wrapper for the /api/v1 backend.
 * The same API serves the web SPA and the future mobile app.
 */

const TOKEN_KEY = 'auth_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    if (token) {
        localStorage.setItem(TOKEN_KEY, token);
    } else {
        localStorage.removeItem(TOKEN_KEY);
    }
}

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || `HTTP ${status}`);
        this.status = status;
        this.data = data;
        this.errors = data?.errors || {};
    }

    firstError() {
        const first = Object.values(this.errors)[0];
        return Array.isArray(first) ? first[0] : this.message;
    }
}

async function request(method, url, { params, body, formData } = {}) {
    const query = params
        ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))
        : '';

    const headers = { Accept: 'application/json' };
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
    if (body) headers['Content-Type'] = 'application/json';

    const response = await fetch(`/api/v1${url}${query}`, {
        method,
        headers,
        body: formData ? formData : body ? JSON.stringify(body) : undefined,
    });

    const data = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        if (response.status === 401) {
            setToken(null);
            // App.vue сонсоод session-ийг цэвэрлэж login руу чиглүүлнэ
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }
        throw new ApiError(response.status, data);
    }

    return data;
}

export const api = {
    get: (url, params) => request('GET', url, { params }),
    post: (url, body) => request('POST', url, { body }),
    postForm: (url, formData) => request('POST', url, { formData }),
    put: (url, body) => request('PUT', url, { body }),
    delete: (url) => request('DELETE', url, {}),
};
