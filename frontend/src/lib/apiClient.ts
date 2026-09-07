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

export interface PageMeta {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
}

/** Formato de toda resposta da API -- `fetch().json()` não é tipado, esse é o único ponto que confia nisso. */
interface ApiEnvelope {
    data?: unknown;
    meta?: PageMeta;
    message?: string;
    errors?: Record<string, string>;
}

/**
 * `credentials: 'include'` manda o cookie de sessão; `X-CSRF-Token` é lido do
 * cookie `XSRF-TOKEN` (não HttpOnly, por isso o JS consegue ler) e ecoado de
 * volta em toda mutação -- o backend exige os dois baterem. Compartilhado por
 * `apiFetch` e `apiUpload`, que só divergem em como montam o corpo da request.
 */
async function request(path: string, options: RequestInit): Promise<ApiEnvelope> {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers);

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

    const payload = (await response.json().catch(() => null)) as ApiEnvelope | null;

    if (!response.ok) {
        throw new ApiError(payload?.message ?? 'Request failed.', response.status, payload?.errors);
    }

    return payload ?? {};
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
    const headers = new Headers(options.headers);

    if (options.body !== undefined) {
        headers.set('Content-Type', 'application/json');
    }

    const envelope = await request(path, { ...options, headers });

    return envelope.data as T;
}

/** Resposta paginada (`meta.total`/`meta.last_page`) -- listagens que aceitam `page`/`per_page`. */
export async function apiFetchPage<T>(path: string): Promise<{ data: T[]; meta: PageMeta }> {
    const envelope = await request(path, {});

    return { data: (envelope.data as T[]) ?? [], meta: envelope.meta as PageMeta };
}

/**
 * Upload multipart -- sem `Content-Type` manual: o browser define o
 * boundary sozinho a partir do `FormData`, um header fixo quebraria isso.
 */
export async function apiUpload<T>(path: string, formData: FormData): Promise<T> {
    const envelope = await request(path, { method: 'POST', body: formData });

    return envelope.data as T;
}
