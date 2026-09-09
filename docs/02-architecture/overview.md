# Arquitetura — visão geral

Hexagonal/ports-and-adapters pragmático, sem framework web PHP. Modular guiada por DDD onde
compensa; sem camada ou abstração criada só pra parecer mais arquitetada.

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

**Nginx** — único ponto de entrada HTTP: serve o frontend compilado, encaminha `/api/*` pro
PHP-FPM, expõe `/health`.

**Frontend** — React, TypeScript, Vite, Material UI, TanStack Query. Build multi-stage
(`Node.js -> npm ci -> npm run build -> dist/ -> Nginx runtime`) — Node.js só existe durante o
build, a imagem final não carrega `node_modules`.

**Backend** — PHP 8.5, PHP-FPM, Composer. `public/index.php` tem três linhas: quem compõe é
`Bootstrap\HttpKernel` (config, container, router, pipeline), e `Bootstrap\CliKernel` faz o mesmo
pros processos de `bin/`. Rota é declarada de forma fluente
(`$router->get(path, [Controller::class, 'metodo'])->roles(...)->rateLimit('auth')`) e casada uma
vez por request, no `ResolveRouteMiddleware`.

**PostgreSQL** — persistência, foreign keys, constraints, índices, transações, busca textual.
Todo SQL passa por `DatabaseConnection::execute()` (bind tipado, `UNIQUE` vira `DomainException`
em vez de subir cru); leitura passa por `Row` (converte o `mixed` do PDO com verificação).

**Redis** — rate limiting (sliding window, script Lua atômico), fila de jobs (`RedisQueue`), e o
"último run" de cada tarefa do scheduler (sobrevive restart, não duplica disparo entre réplicas).

**MinIO** — `Upload -> Backend (valida MIME/tamanho) -> Job assíncrono (otimiza) -> MinIO -> tabela files`.
PostgreSQL guarda a referência do objeto, nunca o binário; upload confirmado no MinIO só depois
vira linha em `files` — nada de registro órfão apontando pra um objeto que falhou no meio.

Camadas em [`layers.md`](layers.md), regra de dependência e ports/adapters em
[`dependencies.md`](dependencies.md), decisões registradas em [`decisions/`](decisions/).
