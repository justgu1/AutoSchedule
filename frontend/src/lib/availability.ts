import { apiFetch, apiFetchPublic } from './apiClient';

export interface AvailabilityRuleInput {
    weekday: number;
    start_time: string;
    end_time: string;
}

export interface DealershipAvailabilityRule extends AvailabilityRuleInput {
    id: string;
    dealership_id: string;
}

export interface VehicleAvailabilityRule extends AvailabilityRuleInput {
    id: string;
    vehicle_id: string;
}

export interface AvailabilityExceptionInput {
    dealership_id?: string;
    vehicle_id?: string;
    date: string;
    start_time?: string;
    end_time?: string;
    is_available: boolean;
    reason?: string;
}

export interface AvailabilityException {
    id: string;
    dealership_id: string | null;
    vehicle_id: string | null;
    date: string;
    start_time: string | null;
    end_time: string | null;
    is_available: boolean;
    reason: string | null;
}

export function listDealershipAvailabilityRules(dealershipId: string): Promise<DealershipAvailabilityRule[]> {
    return apiFetch<DealershipAvailabilityRule[]>(`/dealerships/${dealershipId}/availability-rules`);
}

export function createDealershipAvailabilityRule(
    dealershipId: string,
    input: AvailabilityRuleInput,
): Promise<DealershipAvailabilityRule> {
    return apiFetch<DealershipAvailabilityRule>(`/dealerships/${dealershipId}/availability-rules`, {
        method: 'POST',
        body: JSON.stringify(input),
    });
}

export function deleteDealershipAvailabilityRule(id: string): Promise<{ message: string }> {
    return apiFetch(`/availability-rules/${id}`, { method: 'DELETE' });
}

export function listVehicleAvailabilityRules(vehicleId: string): Promise<VehicleAvailabilityRule[]> {
    return apiFetch<VehicleAvailabilityRule[]>(`/vehicles/${vehicleId}/availability-rules`);
}

export function createVehicleAvailabilityRule(
    vehicleId: string,
    input: AvailabilityRuleInput,
): Promise<VehicleAvailabilityRule> {
    return apiFetch<VehicleAvailabilityRule>(`/vehicles/${vehicleId}/availability-rules`, {
        method: 'POST',
        body: JSON.stringify(input),
    });
}

export function deleteVehicleAvailabilityRule(id: string): Promise<{ message: string }> {
    return apiFetch(`/vehicle-availability-rules/${id}`, { method: 'DELETE' });
}

/** Exatamente um dos dois -- concessionária ou veículo, nunca os dois nem nenhum. */
export function listAvailabilityExceptions(scope: {
    dealership_id?: string;
    vehicle_id?: string;
}): Promise<AvailabilityException[]> {
    const params = new URLSearchParams(scope);

    return apiFetch<AvailabilityException[]>(`/availability-exceptions?${params.toString()}`);
}

export function createAvailabilityException(input: AvailabilityExceptionInput): Promise<AvailabilityException> {
    return apiFetch<AvailabilityException>('/availability-exceptions', { method: 'POST', body: JSON.stringify(input) });
}

export function deleteAvailabilityException(id: string): Promise<{ message: string }> {
    return apiFetch(`/availability-exceptions/${id}`, { method: 'DELETE' });
}

/** Sem `month`, o backend usa o mês corrente. */
export async function listAvailableDates(vehicleId: string, month?: string): Promise<string[]> {
    const query = month ? `?month=${month}` : '';
    const result = await apiFetchPublic<{ dates: string[] }>(`/vehicles/${vehicleId}/availability/dates${query}`);

    return result.dates;
}

export async function listAvailableSlots(vehicleId: string, date: string): Promise<string[]> {
    const result = await apiFetchPublic<{ slots: string[] }>(`/vehicles/${vehicleId}/availability/slots?date=${date}`);

    return result.slots;
}
