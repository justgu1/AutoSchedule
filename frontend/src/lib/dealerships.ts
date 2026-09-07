import { apiFetch, apiFetchPage, apiUpload, type PageMeta } from './apiClient';

export type DealershipStatus = 'active' | 'trashed' | 'deleted';

export interface Dealership {
    id: string;
    owner_user_id: string;
    name: string;
    zip_code: string;
    address: string;
    number: string;
    complement: string | null;
    neighborhood: string;
    city: string;
    state: string;
    latitude: number | null;
    longitude: number | null;
    google_place_id: string | null;
    phone: string | null;
    photo_url: string | null;
    status: DealershipStatus;
}

export interface DealershipProfileInput {
    name: string;
    zip_code: string;
    address: string;
    number: string;
    complement?: string;
    neighborhood: string;
    city: string;
    state: string;
    phone?: string;
    /** Só aceito pelo backend quando quem chama é admin -- seller se torna dono automaticamente. */
    owner_user_id?: string;
}

export interface PhotoJob {
    job_id: string;
    status_url: string;
    events_url: string;
}

export function listDealerships(page: number, perPage: number): Promise<{ data: Dealership[]; meta: PageMeta }> {
    return apiFetchPage<Dealership>(`/dealerships?page=${page}&per_page=${perPage}`);
}

export function createDealership(input: DealershipProfileInput): Promise<Dealership> {
    return apiFetch<Dealership>('/dealerships', { method: 'POST', body: JSON.stringify(input) });
}

export function updateDealership(id: string, input: Partial<DealershipProfileInput>): Promise<Dealership> {
    return apiFetch<Dealership>(`/dealerships/${id}`, { method: 'PATCH', body: JSON.stringify(input) });
}

/** Move pra lixeira -- recuperável por 30 dias via `restoreDealership`. */
export function trashDealership(id: string): Promise<{ message: string }> {
    return apiFetch(`/dealerships/${id}`, { method: 'DELETE' });
}

export function restoreDealership(id: string): Promise<{ message: string }> {
    return apiFetch(`/dealerships/${id}/restore`, { method: 'POST' });
}

/** Anonimiza em definitivo agora, sem esperar os 30 dias. */
export function purgeDealership(id: string): Promise<{ message: string }> {
    return apiFetch(`/dealerships/${id}/purge`, { method: 'POST' });
}

/**
 * Só enfileira -- o processamento (otimizar pra WebP, gravar) roda no worker.
 * Acompanhe o resultado com `subscribeToPhotoJob(job.events_url, ...)`.
 */
export function setDealershipPhoto(id: string, file: File): Promise<PhotoJob> {
    const formData = new FormData();
    formData.append('image', file);

    return apiUpload<PhotoJob>(`/dealerships/${id}/photo`, formData);
}

export function removeDealershipPhoto(id: string): Promise<{ message: string }> {
    return apiFetch(`/dealerships/${id}/photo`, { method: 'DELETE' });
}

export interface PhotoJobStatus {
    status: 'queued' | 'processing' | 'done' | 'failed';
    step: string;
    progress: number;
    result?: { photo_url: string };
    error?: string;
}

/**
 * SSE -- `onUpdate` roda a cada evento (inclusive o final), `onUpdate` já
 * recebe o status terminal (`done`/`failed`); quem chama decide o que fazer.
 * Fecha a conexão sozinho assim que chega num status terminal.
 */
export function subscribeToPhotoJob(eventsUrl: string, onUpdate: (status: PhotoJobStatus) => void): () => void {
    // `eventsUrl` vem do backend sem o prefixo `/api` (mesmo formato que os
    // outros paths deste arquivo) -- `EventSource` não passa por `apiFetch`,
    // então o prefixo precisa ser somado aqui.
    const source = new EventSource(`/api${eventsUrl}`, { withCredentials: true });

    source.addEventListener('progress', (event) => {
        const status = JSON.parse((event as MessageEvent<string>).data) as PhotoJobStatus;
        onUpdate(status);

        if (status.status === 'done' || status.status === 'failed') {
            source.close();
        }
    });

    // Erro de rede/reconexão do EventSource, não um evento "failed" do job em si -- só encerra, quem chama já tem o último status conhecido.
    source.onerror = () => source.close();

    return () => source.close();
}
