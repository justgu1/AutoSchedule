# AutoSchedule

Agendamento de visitas a veículos. Desafio técnico de Engenheiro(a) Full-Stack da Loop.

## Sumário

- [Sobre o projeto](#sobre-o-projeto)
- [Stack](#stack)
- [Requisitos](#requisitos)
- [Quick Start](#quick-start)
- [Desenvolvimento](#desenvolvimento)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [API](#api)
- [Documentação](#documentação)
- [Processo seletivo](#processo-seletivo)

## Sobre o projeto

O AutoSchedule é uma aplicação para agendamento de visitas a veículos. Fluxo do cliente final:

1. Visualizar os detalhes do veículo;
2. Consultar as datas disponíveis;
3. Selecionar uma data;
4. Consultar os horários disponíveis para a data selecionada;
5. Selecionar um horário;
6. Informar nome, e-mail e telefone;
7. Confirmar o agendamento;
8. Registrar o veículo, cliente e agendamento;
9. Exibir a confirmação do agendamento.

Os horários disponíveis são definidos por data — ao selecionar um dia, só os horários livres naquele dia são apresentados.

Antes desse fluxo existir, o projeto precisou de uma base de conta/autenticação, concessionária e infraestrutura por trás dele (login com role, MinIO pra foto, fila/scheduler pra e-mail assíncrono e purga da lixeira) — é o que já está implementado hoje; veículo/disponibilidade/agendamento em si são a próxima etapa (`Worklist.md`).

## Stack

**Frontend** — React, TypeScript, Vite, Material UI, TanStack Query

**Backend** — PHP 8.4, PHP-FPM, Composer, PDO (PostgreSQL)

**Infraestrutura local** — Docker, Docker Compose, Nginx, PostgreSQL, Redis, MinIO, Mailpit

**Infraestrutura de produção** — Kubernetes, ArgoCD (GitOps), sealed-secrets, GHCR

## Requisitos

- Docker
- Docker Compose
- Git
- Make
- OpenSSL

## Quick Start

```bash
git clone https://github.com/justgu1/AutoSchedule.git
cd AutoSchedule
make setup
```

Acesse `http://localhost:8080`.

## Desenvolvimento

| Comando | Ação |
|---|---|
| `make up` | Inicia os serviços |
| `make down` | Para os serviços |
| `make restart` | Reinicia os serviços |
| `make build` | Reconstrói as imagens |
| `make ps` | Verifica o status dos serviços |
| `make logs` | Acompanha os logs |
| `make migrate` / `make rollback` / `make seed` | Migrations e seeders |
| `make test` | Roda os testes do backend |
| `make load-test` | Roda a suíte de teste de carga (k6) |
| `make e2e` | Roda a suíte E2E (Playwright) contra o build real |
| `make static-analysis` | PHPStan (backend) |
| `make lint` / `make lint-fix` | PHP-CS-Fixer (backend) -- checa / aplica |
| `make rector` / `make rector-fix` | Rector (backend) -- checa / aplica |

### Live-reload (PHP + React)

`docker compose up` já mescla `docker-compose.yaml` com `docker-compose.override.yml` (sem flag). O override monta o código-fonte direto nos containers:

- **`backend`** — `./backend` bind-mounted, opcache validando timestamp a cada request: mudou um `.php`, reflete na hora, sem rebuild nem restart.
- **`frontend`** — service novo rodando `npm run dev` dentro de container, `./frontend` bind-mounted, HMR do Vite em `http://localhost:5173`.

Chamadas `/api/...` do React são proxied pelo Vite pro Nginx (`:80` interno), que segue pro `backend` via FastCGI. `http://localhost:8080` continua de pé pra bater direto na API sem o Vite no meio.

Em produção o override não deve subir:

```bash
docker compose -f docker-compose.yaml up -d --build
```

### Health Check

```bash
curl http://localhost:8080/health
```

## Variáveis de ambiente

`make setup` já gera o `.env` a partir do `.env.example` com credenciais aleatórias (DB, MinIO) e chave RSA do JWT — a lista abaixo é só quando algo precisar mudar manualmente.

- **Banco**: `DB_DRIVER`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`DB_PORT` (role admin, migrations/seeders); `DB_APP_USERNAME`/`DB_APP_PASSWORD` (role restrita `NOSUPERUSER NOBYPASSRLS` que a aplicação usa em runtime — é o que faz o RLS valer algo).
- **Aplicação**: `APP_TIMEZONE` (`America/Sao_Paulo` — o container roda em UTC, "hoje" precisa do fuso certo).
- **Rate limit**: `RATE_LIMIT_GENERAL_MAX`/`_WINDOW`, `RATE_LIMIT_AUTH_MAX`/`_WINDOW` — sliding window, geral vs. rotas sensíveis (login/registro/reset).
- **Paginação**: `PAGINATION_DEFAULT_PER_PAGE`, `PAGINATION_MAX_PER_PAGE`.
- **Segurança**: `COOKIE_SECURE`, `SECURITY_HSTS_ENABLED`, `CORS_ALLOWED_ORIGINS`.
- **Google**: `GOOGLE_CLIENT_ID` (login social).
- **E-mail**: `MAIL_FROM`, `FRONTEND_URL` (link do reset de senha), `MAILPIT_UI_PORT`.
- **MinIO**: `S3_ENDPOINT`/`S3_BUCKET`/`S3_REGION`/`S3_ACCESS_KEY`/`S3_SECRET_KEY`/`S3_PUBLIC_URL`, `TEMP_STORAGE_PATH` (backup local do upload até o MinIO confirmar).

## API

Prefixo `/api`, resposta sempre no mesmo envelope (`{ "status": "success", "data": {...} }` ou `{ "status": "error", "message": "...", "errors": {...} }`). Sem doc OpenAPI separada pra manter sincronizada: `GET /api` já devolve, em tempo de request, só as rotas que quem chamou (anônimo ou autenticado) pode de fato usar.

Contratos de cada rota (validação, roles, cascatas) ficam documentados por domínio em [`docs/business-rules.md`](docs/business-rules.md), pra não duplicar em dois lugares.

## Documentação

- [Arquitetura](docs/architecture.md)
- [Regras de negócio](docs/business-rules.md)
- [Banco de dados](docs/database.md)
- [Testes](docs/testing.md)
- [Catálogo regra → teste](docs/test-catalog.md)
- [Worklist](Worklist.md)

## Processo seletivo

Este projeto foi desenvolvido para o desafio técnico de Engenheiro(a) Full-Stack da Loop, em um sprint de 7 dias.
