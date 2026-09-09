# AutoSchedule

Agendamento de visitas a veículos. Desafio técnico de Engenheiro(a) Full-Stack da Loop.

Produção: **[autoschedule.justgui.dev](https://autoschedule.justgui.dev)**.

## Sobre o projeto

Cliente escolhe um veículo publicado por uma concessionária, vê os horários realmente livres e
reserva uma visita, sem precisar de conta. Detalhe completo em
[`docs/01-domain/overview.md`](docs/01-domain/overview.md).

## Quick Start

```bash
git clone https://github.com/justgu1/AutoSchedule.git
cd AutoSchedule
make setup
```

Acesse `http://localhost:8080`.

## Stack

**Frontend** — React, TypeScript, Vite, Material UI, TanStack Query.
**Backend** — PHP 8.5 sem framework, PHP-FPM, Composer, PDO (PostgreSQL).
**Infraestrutura local** — Docker Compose, Nginx, PostgreSQL, Redis, MinIO, Mailpit.
**Infraestrutura de produção** — Kubernetes, ArgoCD (GitOps), sealed-secrets, GHCR.

## Requisitos

Docker, Docker Compose, Git, Make, OpenSSL.

## Comandos

| Comando | Ação |
|---|---|
| `make up` / `make down` / `make restart` / `make build` / `make ps` / `make logs` | Ciclo de vida do ambiente |
| `make migrate` / `make rollback` / `make seed` | Migrations e seeders |
| `make test` / `make test-unit` | Testes do backend (tudo / só os puros) |
| `make e2e` | Suíte E2E (Playwright), banco isolado |
| `make load-test` | Suíte de carga (k6) |
| `make static-analysis` / `make lint` / `make rector` / `make arch` / `make comments` | Qualidade estática do backend |

Live-reload (bind mount + HMR), variáveis de ambiente e health check: ver
`docker-compose.override.yml` e `.env.example` — comentados no próprio arquivo.

## Documentação

| Pasta | Conteúdo |
|---|---|
| [`docs/01-domain/`](docs/01-domain/) | O que o sistema faz: visão geral, glossário, regras de negócio numeradas, invariantes, estados/transições, casos de uso |
| [`docs/02-architecture/`](docs/02-architecture/) | Camadas, dependências, decisões registradas (ADR) |
| [`docs/03-security/`](docs/03-security/) | Autenticação, autorização, proteção de dados, modelo de ameaças |
| [`docs/04-api/`](docs/04-api/) | Contrato HTTP — envelope, autenticação, agendamento, veículos |
| [`docs/05-data/`](docs/05-data/) | Modelo de dados e convenção de migrations |
| [`docs/06-testing/`](docs/06-testing/) | Estratégia, regra → teste, lacunas conhecidas |
| [`docs/07-operations/`](docs/07-operations/) | Deploy, configuração, observabilidade, troubleshooting |
| [`docs/code-style.md`](docs/code-style.md) | O que ferramenta não checa (comentário, tradução de erro de banco) |
| [`Worklist.md`](Worklist.md) | Backlog: epic > issue > task |

## Processo seletivo

Este projeto foi desenvolvido para o desafio técnico de Engenheiro(a) Full-Stack da Loop, em um
sprint de 7 dias.
