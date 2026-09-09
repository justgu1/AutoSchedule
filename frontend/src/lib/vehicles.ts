import { apiFetch, apiFetchPage, apiFetchPublic, apiFetchPublicPage, apiUpload, type PageMeta } from './apiClient';
import type { PhotoJob } from './jobs';

export type VehicleStatus = 'active' | 'trashed' | 'deleted';
export type Transmission = 'manual' | 'automatic' | 'automated' | 'cvt';
export type BodyType = 'hatch' | 'sedan' | 'suv' | 'pickup' | 'coupe' | 'convertible' | 'minivan' | 'wagon';
export type FuelType = 'flex' | 'gasoline' | 'ethanol' | 'diesel' | 'electric' | 'hybrid';
export type VehicleSort = 'price_desc' | 'price_asc' | 'year_desc' | 'created_desc' | 'created_asc';

export interface VehicleImage {
    id: string;
    position: number;
    url: string;
}

export interface VehicleAmenity {
    id: string;
    code: string;
    label: string;
}

export interface Vehicle {
    id: string;
    dealership_id: string;
    brand: string;
    model: string;
    version: string | null;
    manufacture_year: number | null;
    model_year: number | null;
    /** Sempre string decimal (ex: `"89900.00"`) -- número em JSON vira ponto flutuante e perde centavo. */
    price: string;
    description: string | null;
    mileage_km: number | null;
    transmission: Transmission | null;
    body_type: BodyType | null;
    fuel_type: FuelType | null;
    color: string | null;
    plate_end_digit: number | null;
    accepts_trade: boolean;
    ipva_paid: boolean;
    licensed: boolean;
    status: VehicleStatus;
    photo_url: string | null;
    images: VehicleImage[];
    amenities: VehicleAmenity[];
}

export interface VehicleInput {
    dealership_id?: string;
    brand: string;
    model: string;
    version?: string;
    manufacture_year?: number;
    model_year?: number;
    price: string;
    description?: string;
    mileage_km?: number;
    transmission?: Transmission;
    body_type?: BodyType;
    fuel_type?: FuelType;
    color?: string;
    plate_end_digit?: number;
    accepts_trade?: boolean;
    ipva_paid?: boolean;
    licensed?: boolean;
    amenity_ids?: string[];
}

/** Todos opcionais e combináveis -- filtro ausente vira "sem essa restrição", tanto no painel quanto no catálogo. */
export interface VehicleFilterParams {
    q?: string;
    sort?: VehicleSort;
    brand?: string;
    model?: string;
    year_min?: number;
    year_max?: number;
    price_min?: string;
    price_max?: string;
    dealership_id?: string;
    transmission?: Transmission;
    body_type?: BodyType;
    fuel_type?: FuelType;
    mileage_km_max?: number;
}

export interface VehicleFacets {
    brands: string[];
    models: string[];
    years: number[];
    transmissions: Transmission[];
    body_types: BodyType[];
    fuel_types: FuelType[];
}

export interface PublicVehicleSummary {
    id: string;
    brand: string;
    model: string;
    version: string | null;
    model_year: number | null;
    price: string;
    mileage_km: number | null;
    photo_url: string | null;
}

export interface PublicVehicleProfile {
    id: string;
    brand: string;
    model: string;
    version: string | null;
    manufacture_year: number | null;
    model_year: number | null;
    price: string;
    description: string | null;
    mileage_km: number | null;
    transmission: Transmission | null;
    body_type: BodyType | null;
    fuel_type: FuelType | null;
    color: string | null;
    plate_end_digit: number | null;
    accepts_trade: boolean;
    ipva_paid: boolean;
    licensed: boolean;
    images: VehicleImage[];
    amenities: VehicleAmenity[];
    dealership: {
        slug: string;
        name: string;
        city: string;
        state: string;
    };
}

function query(filters: VehicleFilterParams, extra: Record<string, string | number> = {}): string {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries({ ...filters, ...extra })) {
        if (value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    }

    const search = params.toString();

    return search === '' ? '' : `?${search}`;
}

/** Painel de quem gerencia -- `scope=mine` exige admin/seller, RLS já escopa pra só o próprio estoque. */
export function listVehicles(
    filters: VehicleFilterParams,
    page: number,
    perPage: number,
): Promise<{ data: Vehicle[]; meta: PageMeta }> {
    return apiFetchPage<Vehicle>(`/vehicles${query(filters, { scope: 'mine', page, per_page: perPage })}`);
}

export function getVehicleFacets(filters: VehicleFilterParams = {}): Promise<VehicleFacets> {
    return apiFetch<VehicleFacets>(`/vehicles/filters${query(filters, { scope: 'mine' })}`);
}

/** Mesma rota do catálogo público -- autenticado como dono/admin, devolve o perfil completo com a galeria. */
export function getVehicle(id: string): Promise<Vehicle> {
    return apiFetch<Vehicle>(`/vehicles/${id}`);
}

export function createVehicle(input: VehicleInput): Promise<Vehicle> {
    return apiFetch<Vehicle>('/vehicles', { method: 'POST', body: JSON.stringify(input) });
}

export function updateVehicle(id: string, input: Partial<VehicleInput>): Promise<Vehicle> {
    return apiFetch<Vehicle>(`/vehicles/${id}`, { method: 'PATCH', body: JSON.stringify(input) });
}

/** Move pra lixeira -- recuperável por 30 dias via `restoreVehicle`. */
export function trashVehicle(id: string): Promise<{ message: string }> {
    return apiFetch(`/vehicles/${id}`, { method: 'DELETE' });
}

export function restoreVehicle(id: string): Promise<{ message: string }> {
    return apiFetch(`/vehicles/${id}/restore`, { method: 'POST' });
}

/** Anonimiza em definitivo agora, sem esperar os 30 dias. */
export function purgeVehicle(id: string): Promise<{ message: string }> {
    return apiFetch(`/vehicles/${id}/purge`, { method: 'POST' });
}

/**
 * Lote inteiro num `job_id` só -- até 10 arquivos de 20MB cada, acompanhados
 * pelo mesmo mecanismo de SSE que a foto da concessionária usa.
 */
export function addVehiclePhotos(id: string, files: File[]): Promise<PhotoJob> {
    const formData = new FormData();

    for (const file of files) {
        formData.append('images[]', file);
    }

    return apiUpload<PhotoJob>(`/vehicles/${id}/photos`, formData);
}

export function removeVehiclePhoto(id: string, imageId: string): Promise<{ message: string }> {
    return apiFetch(`/vehicles/${id}/photos/${imageId}`, { method: 'DELETE' });
}

/** `order` precisa listar todo id da galeria exatamente uma vez -- ordem parcial é recusada pelo backend. */
export function reorderVehiclePhotos(id: string, order: string[]): Promise<{ message: string }> {
    return apiFetch(`/vehicles/${id}/photos`, { method: 'PATCH', body: JSON.stringify({ order }) });
}

/** Sem `scope`, é o catálogo público -- todo mundo vê o mesmo estoque ativo, dono logado ou não. */
export function listPublicVehicles(
    filters: VehicleFilterParams,
    page: number,
    perPage: number,
): Promise<{ data: PublicVehicleSummary[]; meta: PageMeta }> {
    return apiFetchPublicPage<PublicVehicleSummary>(`/vehicles${query(filters, { page, per_page: perPage })}`);
}

export function getPublicVehicleFacets(filters: VehicleFilterParams = {}): Promise<VehicleFacets> {
    return apiFetchPublic<VehicleFacets>(`/vehicles/filters${query(filters)}`);
}

export function getPublicVehicle(id: string): Promise<PublicVehicleProfile> {
    return apiFetchPublic<PublicVehicleProfile>(`/vehicles/${id}`);
}

/** Catálogo global e estático de itens/equipamentos -- cache longo, o mesmo pra todo veículo. */
export function listVehicleAmenityCatalog(): Promise<VehicleAmenity[]> {
    return apiFetchPublic<VehicleAmenity[]>('/vehicles/amenities-catalog');
}
