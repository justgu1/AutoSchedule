import { apiFetch, apiFetchPage, apiFetchPublic, apiUpload, type PageMeta } from './apiClient';
import type { PhotoJob, PhotoJobStatus } from './jobs';
import type { PublicVehicleSummary } from './vehicles';

export type DealershipStatus = 'active' | 'trashed' | 'deleted';

export interface Dealership {
    id: string;
    owner_user_id: string;
    name: string;
    slug: string;
    zip_code: string;
    address: string;
    number: string;
    complement: string | null;
    neighborhood: string;
    city: string;
    state: string;
    phone: string | null;
    email: string | null;
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
    email?: string;
    /** Só aceito pelo backend quando quem chama é admin -- seller se torna dono automaticamente. */
    owner_user_id?: string;
}

export function listDealerships(page: number, perPage: number): Promise<{ data: Dealership[]; meta: PageMeta }> {
    return apiFetchPage<Dealership>(`/dealerships?page=${page}&per_page=${perPage}`);
}

export interface PublicDealership {
    slug: string;
    name: string;
    zip_code: string;
    address: string;
    number: string;
    complement: string | null;
    neighborhood: string;
    city: string;
    state: string;
    phone: string | null;
    email: string | null;
    photo_url: string | null;
    seller_name: string | null;
    vehicles: PublicVehicleSummary[];
    vehicles_total: number;
}

/** Mesma rota de `show` (`GET /dealerships/{id_ou_slug}`) que o gerenciamento usa, só que devolve o perfil público -- o backend decide o formato pela própria request. */
export function getPublicDealership(slug: string): Promise<PublicDealership> {
    return apiFetchPublic<PublicDealership>(`/dealerships/${slug}`);
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

export type DealershipPhotoJobStatus = PhotoJobStatus<{ photo_url: string }>;
