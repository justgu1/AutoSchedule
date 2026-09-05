# AutoSchedule

Agendamento de visitas a veículos. Desafio técnico de Engenheiro(a) Full-Stack da Loop.

## Sumário

- [Sobre o projeto](#sobre-o-projeto)
- [Stack](#stack)
- [Requisitos](#requisitos)
- [Quick Start](#quick-start)
- [Desenvolvimento](#desenvolvimento)
- [Documentação](#documentação)
- [Processo seletivo](#processo-seletivo)

## Sobre o projeto

O AutoSchedule é uma aplicação para agendamento de visitas a veículos. Fluxo:

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

## Stack

**Frontend** — React, TypeScript, Vite, Material UI, TanStack Query

**Backend** — PHP 8.4, PHP-FPM, Composer

**Infraestrutura** — Docker, Docker Compose, Nginx, PostgreSQL, Redis, MinIO

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
| `make test` | Roda os testes do backend |
| `make load-test` | Roda a suíte de teste de carga (k6) |

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

## Documentação

- [Arquitetura](docs/architecture.md)
- [Regras de negócio](docs/business-rules.md)
- [Banco de dados](docs/database.md)
- [Testes](docs/testing.md)
- [Worklist do sprint](Worklist.md)

## Processo seletivo

Este projeto foi desenvolvido para o desafio técnico de Engenheiro(a) Full-Stack da Loop, em um sprint de 7 dias.
