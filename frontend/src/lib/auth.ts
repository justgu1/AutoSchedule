import { apiFetch } from './apiClient';

export type UserRole = 'admin' | 'seller' | 'customer';

export interface UserProfile {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    role: UserRole;
}

const CLIENT_ID = 'autoschedule-web';

export function getMe(): Promise<UserProfile> {
    return apiFetch<UserProfile>('/me');
}

export function login(email: string, password: string): Promise<UserProfile> {
    return apiFetch<{ access_token: string }>('/oauth/token', {
        method: 'POST',
        body: JSON.stringify({ client_id: CLIENT_ID, email, password }),
    }).then(() => getMe());
}

/** id_token vem do botão do Google Identity Services -- o backend verifica a assinatura, nunca confiamos nele aqui. */
export function loginWithGoogle(idToken: string): Promise<UserProfile> {
    return apiFetch<{ access_token: string }>('/oauth/token', {
        method: 'POST',
        body: JSON.stringify({ client_id: CLIENT_ID, id_token: idToken }),
    }).then(() => getMe());
}

/** Self-service: só funciona de customer pra seller, o backend rejeita qualquer outra transição. */
export function becomeSeller(): Promise<UserProfile> {
    return apiFetch<UserProfile>('/me', { method: 'PATCH', body: JSON.stringify({ role: 'seller' }) });
}

export interface RegisterInput {
    name: string;
    email: string;
    phone?: string;
    password: string;
    role: 'seller' | 'customer';
}

/** Cria a conta e já loga em seguida -- do ponto de vista de quem usa, uma ação só. */
export async function register(input: RegisterInput): Promise<UserProfile> {
    await apiFetch('/register', { method: 'POST', body: JSON.stringify(input) });

    return login(input.email, input.password);
}

export function requestPasswordReset(email: string): Promise<{ message: string }> {
    return apiFetch('/password-reset', { method: 'POST', body: JSON.stringify({ email }) });
}

export function confirmPasswordReset(resetToken: string, password: string): Promise<{ message: string }> {
    return apiFetch('/me/password', {
        method: 'PUT',
        body: JSON.stringify({ reset_token: resetToken, password }),
    });
}

export function logout(): Promise<{ message: string }> {
    return apiFetch('/logout', { method: 'POST' });
}
