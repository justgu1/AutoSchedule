const BASE_URL = '/api';

/** Erro de resposta da API -- carrega a mensagem e, se veio, os erros por campo (422). */
export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly errors?: Record<string, string>,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

/**
 * `credentials: 'include'` manda o cookie de sessão; `X-CSRF-Token` é lido do
 * cookie `XSRF-TOKEN` (não HttpOnly, por isso o JS consegue ler) e ecoado de
 * volta em toda mutação -- o backend exige os dois baterem.
 */
export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers);

    if (options.body !== undefined) {
        headers.set('Content-Type', 'application/json');
    }

    if (MUTATING_METHODS.has(method)) {
        const csrfToken = readCookie('XSRF-TOKEN');

        if (csrfToken !== null) {
            headers.set('X-CSRF-Token', csrfToken);
        }
    }

    const response = await fetch(`${BASE_URL}${path}`, {
        ...options,
        method,
        headers,
        credentials: 'include',
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(payload?.message ?? 'Request failed.', response.status, payload?.errors);
    }

    return payload?.data as T;
}
