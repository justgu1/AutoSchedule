import { apiFetch } from './apiClient';

export interface ApiClient {
    id: string;
    client_id: string;
    name: string;
    created_at: string;
    revoked_at: string | null;
}

/** `client_secret` só existe nesta resposta -- em texto puro, e nunca mais recuperável depois dela. */
export interface ApiClientWithSecret extends ApiClient {
    client_secret: string;
}

export function listApiClients(): Promise<ApiClient[]> {
    return apiFetch<ApiClient[]>('/me/api-clients');
}

export function createApiClient(name: string): Promise<ApiClientWithSecret> {
    return apiFetch<ApiClientWithSecret>('/me/api-clients', { method: 'POST', body: JSON.stringify({ name }) });
}

export function rotateApiClientSecret(id: string): Promise<ApiClientWithSecret> {
    return apiFetch<ApiClientWithSecret>(`/me/api-clients/${id}/rotate-secret`, { method: 'POST' });
}

export function revokeApiClient(id: string): Promise<{ message: string }> {
    return apiFetch(`/me/api-clients/${id}`, { method: 'DELETE' });
}
