import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8085';
const ADMIN_EMAIL = __ENV.ADMIN_EMAIL || 'admin@autoschedule.local';
const ADMIN_PASSWORD = __ENV.ADMIN_PASSWORD || 'password';

// 429 é comportamento correto sob carga (rate limit fazendo o trabalho dele),
// não uma falha -- sem isso o http_req_failed padrão do k6 contaria todo
// bloqueio de rate limit como erro, escondendo uma falha de verdade (5xx).
http.setResponseCallback(http.expectedStatuses(200, 201, 401, 429));

const rateLimitHits = new Counter('rate_limit_429_hits');

export const options = {
    scenarios: {
        // GET /api sem autenticação -- linha de base de latência da API pública.
        baseline: {
            executor: 'constant-vus',
            exec: 'baseline',
            vus: 20,
            duration: '30s',
        },
        // Fluxo autenticado real: perfil + listagem paginada de usuários.
        authenticated_reads: {
            executor: 'constant-vus',
            exec: 'authenticatedReads',
            vus: 20,
            duration: '30s',
            startTime: '30s',
        },
        // Não mede performance -- confirma que a policy "auth" (padrão 5/min)
        // barra de verdade sob concorrência real, não só em teste sequencial.
        rate_limit_enforcement: {
            executor: 'shared-iterations',
            exec: 'rateLimitEnforcement',
            vus: 10,
            iterations: 30,
            startTime: '60s',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        'http_req_duration{scenario:baseline}': ['p(95)<200'],
        'http_req_duration{scenario:authenticated_reads}': ['p(95)<300'],
        rate_limit_429_hits: ['count>0'],
    },
};

export function setup() {
    const res = http.post(
        `${BASE_URL}/api/oauth/token`,
        JSON.stringify({ client_id: 'autoschedule-web', email: ADMIN_EMAIL, password: ADMIN_PASSWORD }),
        { headers: { 'Content-Type': 'application/json' } },
    );

    check(res, { 'setup: login succeeded': (r) => r.status === 200 });

    return { token: res.json('data.access_token') };
}

export function baseline() {
    check(http.get(`${BASE_URL}/api`), { 'GET /api: 200 ou 429': (r) => r.status === 200 || r.status === 429 });
    sleep(0.1);
}

export function authenticatedReads(data) {
    const headers = { headers: { Authorization: `Bearer ${data.token}` } };

    check(http.get(`${BASE_URL}/api/me`, headers), { 'GET /me: 200 ou 429': (r) => r.status === 200 || r.status === 429 });
    check(http.get(`${BASE_URL}/api/users?per_page=20`, headers), {
        'GET /users: 200 ou 429': (r) => r.status === 200 || r.status === 429,
    });

    sleep(0.1);
}

export function rateLimitEnforcement() {
    // Senha errada de propósito -- só interessa estourar a cota de tentativas,
    // não logar de verdade.
    const res = http.post(
        `${BASE_URL}/api/oauth/token`,
        JSON.stringify({ client_id: 'autoschedule-web', email: 'nobody@example.com', password: 'wrong' }),
        { headers: { 'Content-Type': 'application/json' } },
    );

    if (res.status === 429) {
        rateLimitHits.add(1);
    }

    check(res, { 'POST /oauth/token: 401 ou 429': (r) => r.status === 401 || r.status === 429 });
}
