const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';

export async function request(endpoint, options = {}) {
    const token = localStorage.getItem('rt_token');
    const headers = {
        'Accept': 'application/json',
        ...(options.headers || {})
    };

    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${API_URL}${endpoint}`, {
        ...options,
        headers,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.error || data.message || 'Terjadi kesalahan sistem.');
        error.status = response.status;
        error.details = data;
        throw error;
    }

    return data;
}

export const api = {
    get: (url, params = {}) => {
        const qs = new URLSearchParams(params).toString();
        return request(qs ? `${url}?${qs}` : url, { method: 'GET' });
    },
    post: (url, body) => request(url, {
        method: 'POST',
        body: body instanceof FormData ? body : JSON.stringify(body)
    }),
    put: (url, body) => request(url, {
        method: 'PUT',
        body: JSON.stringify(body)
    }),
    patch: (url, body) => request(url, {
        method: 'PATCH',
        body: JSON.stringify(body)
    }),
    delete: (url) => request(url, { method: 'DELETE' }),
};

export const idr = (val) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
}).format(val || 0);

export const resolveImageUrl = (path) => {
    if (!path) {
        return 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=800';
    }
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
        return path;
    }
    if (path.startsWith('/storage/')) {
        return `http://127.0.0.1:8000${path}`;
    }
    return path;
};
