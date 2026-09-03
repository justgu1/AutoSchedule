# AutoSchedule

Sistema de agendamento de visitas a veículos, desenvolvido como parte do processo seletivo da Loop.

## Sobre o projeto

O AutoSchedule será uma aplicação para agendamento de visitas a veículos.

O fluxo planejado consiste em:

1. Visualizar os detalhes do veículo;
2. Consultar as datas disponíveis;
3. Selecionar uma data;
4. Consultar os horários disponíveis para a data selecionada;
5. Selecionar um horário;
6. Informar nome, e-mail e telefone;
7. Confirmar o agendamento;
8. Registrar o veículo, cliente e agendamento;
9. Exibir a confirmação do agendamento.

Os horários disponíveis serão definidos por data. Ao selecionar um dia, somente os horários disponíveis para aquele dia serão apresentados.

## Stack

### Frontend

- React
- TypeScript
- Vite
- Material UI
- TanStack Query

### Backend

- PHP 8.4
- PHP-FPM
- Composer

### Infraestrutura

- Docker
- Docker Compose
- Nginx
- PostgreSQL
- Redis
- MinIO

## Arquitetura

A aplicação utiliza um único Nginx como gateway HTTP.

```text
                         Browser
                            │
                            ▼
                     ┌─────────────┐
                     │    Nginx    │
                     │   Gateway   │
                     └──────┬──────┘
                            │
                  ┌─────────┴─────────┐
                  │                   │
                  ▼                   ▼
             React SPA             /api/*
                                      │
                                      ▼
                                  PHP-FPM
                                      │
                         ┌────────────┼────────────┐
                         │            │            │
                         ▼            ▼            ▼
                    PostgreSQL      Redis        MinIO
```

### Nginx

O Nginx atua como gateway da aplicação e é responsável por:

- Servir os arquivos estáticos do React;
- Encaminhar requisições `/api/*` para o PHP-FPM;
- Disponibilizar o endpoint `/health`;
- Centralizar o acesso HTTP à aplicação.

### PHP-FPM

O backend utiliza PHP-FPM como runtime da aplicação.

A execução do PHP é separada do servidor HTTP, permitindo que o Nginx seja responsável pela camada HTTP enquanto o PHP-FPM fica responsável pela execução da aplicação PHP.

### Frontend

O frontend utiliza um Docker multi-stage build:

```text
Node.js
   │
   ├── npm ci
   └── npm run build
            │
            ▼
          dist/
            │
            ▼
      Nginx runtime
```

O Node.js é utilizado somente durante o processo de build. A imagem final contém o Nginx e os arquivos estáticos compilados.

## Estrutura do projeto

```text
.
├── backend/
│   ├── public/
│   └── src/
├── frontend/
│   └── src/
├── infra/
│   ├── docker/
│   │   ├── backend/
│   │   │   └── Dockerfile
│   │   ├── frontend/
│   │   │   └── Dockerfile
│   │   └── nginx/
│   │       └── nginx.conf
│   └── k8s/
├── docker-compose.yaml
├── Makefile
├── README.md
└── Worklist.md
```

## Requisitos

Para executar o projeto localmente:

- Docker
- Docker Compose
- Git
- Make
- OpenSSL

## Quick Start

Clone o projeto:

```bash
git clone https://github.com/justgu1/AutoSchedule.git
cd AutoSchedule
```

Execute o setup:

```bash
make setup
```

O setup irá:

1. Criar o arquivo `.env` a partir do `.env.example`;
2. Gerar credenciais locais;
3. Instalar as dependências do frontend;
4. Instalar as dependências do backend;
5. Construir as imagens Docker;
6. Iniciar os serviços.

Após a conclusão, acesse:

```text
http://localhost:8080
```

## Desenvolvimento

### Iniciar o ambiente

```bash
make up
```

### Parar o ambiente

```bash
make down
```

### Reiniciar o ambiente

```bash
make restart
```

### Reconstruir as imagens

```bash
make build
```

### Verificar os serviços

```bash
make ps
```

### Visualizar logs

```bash
make logs
```

## Health Check

A aplicação disponibiliza um endpoint para verificar se o gateway HTTP está respondendo:

```text
GET /health
```

Localmente:

```bash
curl http://localhost:8080/health
```

Resposta esperada:

```text
ok
```

## Serviços locais

O Docker Compose fornece os seguintes serviços:

| Serviço | Responsabilidade |
|---|---|
| `nginx` | Gateway HTTP e frontend |
| `backend` | API PHP via PHP-FPM |
| `postgres` | Banco de dados |
| `redis` | Cache e infraestrutura |
| `minio` | Armazenamento de objetos |

## Variáveis de ambiente

O projeto utiliza variáveis de ambiente para configuração.

O arquivo de referência é:

```text
.env.example
```

Para ambiente local:

```text
.env
```

O `.env` não deve ser versionado.

As configurações incluem:

- Ambiente da aplicação;
- Banco de dados;
- Redis;
- MinIO;
- S3;
- Portas dos serviços.

## Desenvolvimento

O projeto está sendo desenvolvido em um sprint de 7 dias.

O progresso e as tarefas planejadas estão disponíveis em:

```text
Worklist.md
```

## Processo seletivo

Este projeto foi desenvolvido para o desafio técnico de Engenheiro(a) Full-Stack da Loop.
