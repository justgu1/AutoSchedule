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

export interface LoginResult {
    profile: UserProfile;
    /** Conta estava na lixeira e foi restaurada por este login -- tela de destino avisa o usuário disso. */
    accountRestored: boolean;
}

function toLoginResult(tokenResponse: { account_restored: boolean }): Promise<LoginResult> {
    return getMe().then((profile) => ({ profile, accountRestored: tokenResponse.account_restored }));
}

export function login(email: string, password: string): Promise<LoginResult> {
    return apiFetch<{ access_token: string; account_restored: boolean }>('/oauth/token', {
        method: 'POST',
        body: JSON.stringify({ client_id: CLIENT_ID, email, password }),
    }).then(toLoginResult);
}

/** id_token vem do botão do Google Identity Services -- o backend verifica a assinatura, nunca confiamos nele aqui. */
export function loginWithGoogle(idToken: string): Promise<LoginResult> {
    return apiFetch<{ access_token: string; account_restored: boolean }>('/oauth/token', {
        method: 'POST',
        body: JSON.stringify({ client_id: CLIENT_ID, id_token: idToken }),
    }).then(toLoginResult);
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

/** Cria a conta e já loga em seguida -- do ponto de vista de quem usa, uma ação só. Conta recém-criada nunca vem da lixeira. */
export async function register(input: RegisterInput): Promise<UserProfile> {
    await apiFetch('/register', { method: 'POST', body: JSON.stringify(input) });

    return login(input.email, input.password).then((result) => result.profile);
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

/** Move a conta pra lixeira -- recuperável por 30 dias fazendo login de novo. Revoga a sessão, igual logout. */
export function deactivateAccount(): Promise<{ message: string }> {
    return apiFetch('/me', { method: 'DELETE' });
}
