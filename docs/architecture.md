# AutoSchedule — Arquitetura

## Visão geral

Hexagonal/ports-and-adapters pragmático, sem framework web PHP. Arquitetura modular guiada por DDD onde compensa; sem camada ou abstração criada só pra parecer mais arquitetada.

```text
Browser
   │
   ▼
 Nginx
   │
   ├──────── React SPA
   │
   └──────── /api/*
                │
                ▼
             PHP-FPM
                │
        ┌───────┼────────┐
        ▼       ▼        ▼
   PostgreSQL  Redis    MinIO
```

## Nginx

Único ponto de entrada HTTP: serve o frontend compilado, encaminha `/api/*` pro PHP-FPM, expõe `/health`.

## Frontend

React, TypeScript, Vite, Material UI, TanStack Query. Build multi-stage:

```text
Node.js -> npm ci -> npm run build -> dist/ -> Nginx runtime
```

Node.js só existe durante o build -- a imagem final não carrega `node_modules`.

## Backend

PHP 8.4, PHP-FPM, Composer. Nginx cuida da camada HTTP, PHP-FPM executa a aplicação.

## Domínio

```text
User          implementado
Dealership    implementado
Audit         implementado (transversal, não é um domínio de negócio)
Notification  implementado (e-mail assíncrono)
Vehicle       planejado
Availability  planejado
Appointment   planejado
```

Regra de negócio fica perto do domínio a que pertence; controller cuida de entrada/saída HTTP e coordena, nunca concentra regra.

## Ports & Adapters

Port só é criado quando trocar de adapter é um cenário real, não por padrão de projeto. Em uso hoje:

```text
StorageProvider -> MinioAdapter (Flysystem S3)
MailProvider    -> SymfonyMailProvider (SMTP, Mailpit em dev)
TokenIssuer     -> JwtTokenIssuer (RS256)
Queue           -> RedisQueue
```

`AddressProvider`/`GeocodingProvider` (ViaCEP + Google Maps) entram junto do domínio de Endereço -- port planejado, sem adapter ainda.

## PostgreSQL

Persistência, foreign keys, constraints, índices, transações, busca textual e (mais adiante) geolocalização. Regra de negócio mora na aplicação; a integridade que o banco consegue garantir sozinho (unicidade, referência, concorrência) fica reforçada lá também -- validar só na aplicação e confiar que ninguém burla é o tipo de garantia que quebra na primeira migration mal aplicada ou acesso direto ao banco.

## Redis

Hoje: rate limiting (sliding window, script Lua atômico -- ver `docs/business-rules.md`), fila de jobs (`RedisQueue`) e o "último run" de cada tarefa do scheduler, que sobrevive restart e não duplica disparo entre réplicas por guardar o estado fora do processo.

## MinIO

```text
Upload -> Backend (valida MIME de verdade, não só a extensão) -> MinIO -> tabela `files`
```

PostgreSQL guarda a referência do objeto (`files`), nunca o binário. Upload confirmado no MinIO só depois disso vira linha em `files` -- nada de registro órfão apontando pra um objeto que falhou no meio do caminho.

## Autorização e RLS

Autorização acontece no backend, por role declarado na própria rota. O acesso de um seller é escopado pelo que ele é dono:

```text
seller -> dealership (owner_user_id) -> vehicles / availability / appointments
```

Admin tem acesso global, customer só aos próprios dados.

RLS é camada adicional, nunca substitui essa checagem -- a rota já barrou quem não podia antes de qualquer SQL rodar. A role de runtime é `NOSUPERUSER NOBYPASSRLS`; `AuthContextMiddleware` seta `app.current_user_id`/`app.current_user_role` via `SET LOCAL`, só dentro da transação da própria request.

```text
Rate Limiting -> Authentication -> Authorization -> DB Transaction (SET LOCAL) -> RLS -> PostgreSQL
```

Rate limiting roda primeiro -- tráfego abusivo é barrado sem gastar uma transação no Postgres.

## Auditoria

`audit_logs` é polimórfico (`auditable_type`+`auditable_id`), pensado desde o início pra suportar qualquer entidade auditável, não só conta de usuário. Semântica de coluna e convenção de query em [`docs/database.md`](database.md#auditoria).

## Processamento assíncrono

```text
Controller -> Queue (RedisQueue) -> PHP Worker (bin/worker.php) -> Job -> MailProvider/etc.
```

Falha reenfileira com `attempts` incrementado; passadas 3 tentativas vira dead-letter em vez de tentar pra sempre. Scheduler e worker são processos PHP CLI, mesma imagem Docker do backend com outro comando -- cada um escala e reinicia sozinho via Deployment próprio no k8s, sem precisar de supervisor porque o orquestrador já cuida disso.

## Busca e geolocalização

PostgreSQL Full Text Search + `pg_trgm` cobrem a busca inicial, sem depender de Elasticsearch. Concessionária guarda `latitude`/`longitude`/`google_place_id`; busca por proximidade sai do próprio Postgres, PostGIS entra depois se um dia fizer falta de verdade.

## CI/CD e Deploy

```text
push/PR -> backend/frontend (estática + lint) -> phpunit -> e2e -> load-test -> build-and-push (só main)
```

`phpunit`, `e2e` (Playwright) e `load-test` (k6) sobem contra Postgres/Redis reais via services do Actions, não mock. `build-and-push` publica `ghcr.io/justgu1/autoschedule-{backend,nginx}`.

GitOps, monorepo -- sem repositório de manifest separado. ArgoCD (`Application autoschedule`, `prune`+`selfHeal` automáticos) aponta direto pra `infra/k8s` deste repositório; `argocd-image-updater` rastreia as duas imagens por digest.

```text
merge na main -> build-and-push -> image-updater detecta o digest novo -> ArgoCD sincroniza -> apps atualizado
```

Nenhum passo manual entre o merge e produção.

## Princípios

- domínio independente de infraestrutura, regra de negócio nunca em controller;
- banco reforça o que a aplicação já valida, nunca é a única linha de defesa;
- assíncrono só onde falha/lentidão de terceiro (e-mail, upload) não pode travar a resposta;
- abstração só entra quando resolve um problema que já apareceu, não um hipotético.
