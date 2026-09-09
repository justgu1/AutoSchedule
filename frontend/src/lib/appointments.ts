import { apiFetch, apiFetchPage, apiFetchPublic, type PageMeta } from './apiClient';

export type AppointmentStatus = 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';

export interface Appointment {
    id: string;
    vehicle_id: string;
    scheduled_at: string;
    duration_minutes: number;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    status: AppointmentStatus;
    picked_up_at: string | null;
    released_at: string | null;
    created_at: string;
}

export interface CreateAppointmentInput {
    vehicle_id: string;
    scheduled_at: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
}

/** Fluxo do cliente, sem conta -- acha ou cria o customer por e-mail, reconfere o slot antes de gravar. */
export function createAppointment(input: CreateAppointmentInput): Promise<Appointment> {
    return apiFetchPublic<Appointment>('/appointments', { method: 'POST', body: JSON.stringify(input) });
}

/** Resumo pra mostrar antes dos botões -- mesma autorização de confirmar/cancelar (sessão ou token). */
export function getAppointmentByToken(id: string, token: string): Promise<Appointment> {
    return apiFetchPublic<Appointment>(`/appointments/${id}?token=${encodeURIComponent(token)}`);
}

/** Clique do cliente no e-mail de confirmação -- token opaco, sem login. */
export function confirmAppointmentByToken(id: string, token: string): Promise<Appointment> {
    return apiFetchPublic<Appointment>(`/appointments/${id}/confirm`, {
        method: 'POST',
        body: JSON.stringify({ token }),
    });
}

export function cancelAppointmentByToken(id: string, token: string): Promise<Appointment> {
    return apiFetchPublic<Appointment>(`/appointments/${id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ token }),
    });
}

/** Painel: override de funcionário/admin -- mesmo endpoint do clique por token, autorizado pela sessão. */
export function confirmAppointment(id: string): Promise<Appointment> {
    return apiFetch<Appointment>(`/appointments/${id}/confirm`, { method: 'POST' });
}

export function cancelAppointment(id: string): Promise<Appointment> {
    return apiFetch<Appointment>(`/appointments/${id}/cancel`, { method: 'POST' });
}

export function pickupAppointment(id: string): Promise<Appointment> {
    return apiFetch<Appointment>(`/appointments/${id}/pickup`, { method: 'POST' });
}

export function releaseAppointment(id: string): Promise<Appointment> {
    return apiFetch<Appointment>(`/appointments/${id}/release`, { method: 'POST' });
}

export interface AppointmentFilterParams {
    status?: AppointmentStatus;
    vehicle_id?: string;
}

/** Admin vê tudo; seller, só os agendamentos dos próprios veículos (RLS já escopa). */
export function listAppointments(
    filters: AppointmentFilterParams,
    page: number,
    perPage: number,
): Promise<{ data: Appointment[]; meta: PageMeta }> {
    const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });

    if (filters.status !== undefined) {
        params.set('status', filters.status);
    }

    if (filters.vehicle_id !== undefined) {
        params.set('vehicle_id', filters.vehicle_id);
    }

    return apiFetchPage<Appointment>(`/appointments?${params.toString()}`);
}
