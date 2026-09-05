# Load tests (k6)

`make load-test` roda `api-load-test.js` via Docker (imagem `grafana/k6`), sem
precisar instalar nada no host.

## O que cada cenário mede

- **baseline** (30s, 20 VUs): `GET /api` sem autenticação -- latência da API pública.
- **authenticated_reads** (30s, 20 VUs, depois do baseline): `GET /me` + `GET /users?per_page=20` -- fluxo autenticado real.
- **rate_limit_enforcement** (30 iterações, depois dos dois acima): estoura de propósito a policy `auth` (padrão 5/min) pra confirmar que ela barra sob concorrência real, não só em teste sequencial. Isso é uma checagem de comportamento, não de performance -- por isso é o único cenário cujo threshold exige pelo menos um 429.

`429` conta como resposta correta (rate limit funcionando), não como falha -- só 5xx/erro de rede conta pro threshold de `http_req_failed`.

## Rodando com o rate limit real (padrão)

```
make load-test
```

Os dois primeiros cenários competem pela mesma cota "general" (todo VU do k6
sai do mesmo container, então é o mesmo IP/usuário pro rate limiter) -- é
esperado tomar 429 quando a soma passar do limite configurado
(`RATE_LIMIT_GENERAL_MAX`, padrão 1000/min). Isso valida que a API se comporta
corretamente sob carga, não mede o teto real de throughput do backend.

## Medindo throughput bruto (sem o rate limit no meio)

Pra medir o teto de verdade da API, suba o `RATE_LIMIT_GENERAL_MAX` acima do
que o teste vai gerar antes de rodar (mesma lógica do "bypass pra tráfego
confiável" que a própria Cloudflare recomenda para monitoramento/load test):

```
RATE_LIMIT_GENERAL_MAX=1000000 docker compose up -d backend
make load-test
```

Lembre de voltar a variável ao normal (ou `docker compose up -d backend` de
novo sem o override) depois -- esse valor alto não deve ficar em produção.
