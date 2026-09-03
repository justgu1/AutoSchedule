# AutoSchedule — Arquitetura

## Visão geral

O AutoSchedule utiliza uma arquitetura modular baseada em princípios de DDD, Hexagonal Architecture e Ports & Adapters.

A arquitetura deve permanecer pragmática. Não devem ser criadas abstrações somente para aumentar a quantidade de camadas ou classes.

## Componentes

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

O Nginx funciona como gateway HTTP e é responsável por:

- servir o frontend compilado;
- encaminhar `/api/*` para o backend PHP;
- disponibilizar `/health`;
- centralizar o acesso HTTP.

## Frontend

O frontend utiliza:

- React;
- TypeScript;
- Vite;
- Material UI;
- TanStack Query.

O frontend utiliza Docker multi-stage build:

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

O Node.js é utilizado somente durante o build.

## Backend

O backend utiliza:

- PHP 8.4;
- PHP-FPM;
- Composer.

O Nginx é responsável pela camada HTTP e o PHP-FPM pela execução da aplicação PHP.

## Domínio

Os principais contextos lógicos são:

```text
User
Dealership
Vehicle
Availability
Appointment
Audit
Notification
```

## DDD pragmático

As regras devem permanecer próximas do domínio ao qual pertencem.

Controllers devem cuidar da entrada e saída HTTP e coordenar a aplicação, sem concentrar regras de negócio.

## Hexagonal Architecture

A aplicação separa regras de negócio dos detalhes externos:

```text
             ┌─────────────────────┐
             │       Domain        │
             │    Business Rules   │
             └──────────┬──────────┘
                        │
                  Application
                        │
             ┌──────────┴──────────┐
             ▼                     ▼
        PostgreSQL              MinIO
             │
             └────── Redis / APIs
```

## Ports & Adapters

Integrações externas podem ser acessadas por ports quando isso trouxer benefício real.

Exemplos:

```text
AddressProvider
GeocodingProvider
StorageProvider
MailProvider
```

Adapters possíveis:

```text
ViaCepAdapter
GoogleMapsAdapter
MinioAdapter
MailAdapter
```

Os SDKs de fornecedores permanecem na infraestrutura.

## PostgreSQL

O PostgreSQL é responsável por:

- persistência;
- foreign keys;
- constraints;
- índices;
- transações;
- concorrência;
- busca textual;
- dados de geolocalização.

As regras de negócio permanecem na aplicação e as regras de integridade são reforçadas pelo banco.

## Redis

O Redis está disponível na infraestrutura e pode ser utilizado para:

- cache;
- filas;
- locks;
- processamento assíncrono.

Sua utilização deve ocorrer conforme a necessidade da aplicação.

## MinIO

O MinIO armazena as imagens dos veículos.

```text
Upload
  │
  ▼
Backend
  │
  ├── valida arquivo
  │
  ▼
MinIO
  │
  ▼
vehicle_images
```

O PostgreSQL armazena a referência do objeto, não o conteúdo binário.

## Disponibilidade

A disponibilidade é calculada considerando:

```text
Dealership Availability
        +
Vehicle Availability
        +
Exceptions
        -
Appointments
```

A API retorna somente horários que podem receber um novo agendamento.

## Agendamento e transação

A criação do agendamento deve ocorrer em uma transação:

```text
BEGIN

validar disponibilidade
criar ou localizar customer
criar appointment
registrar auditoria

COMMIT
```

O envio de e-mail não ocorre dentro da transação.

## Concorrência

A aplicação verifica a disponibilidade antes da criação.

O PostgreSQL fornece a proteção final através de constraints e transações.

Um conflito deve ser convertido para `409 Conflict`.

## Autorização

A autorização acontece no backend.

O escopo de um seller é determinado por:

```text
seller
   │
   ▼
dealership_user
   │
   ▼
dealership
   │
   ▼
vehicles / availability / appointments
```

Administradores possuem acesso global e clientes somente aos próprios dados.

## RLS

PostgreSQL Row-Level Security pode ser utilizado como camada adicional de proteção:

```text
HTTP Request
     │
     ▼
Authentication
     │
     ▼
Authorization
     │
     ▼
Database Transaction
     │
     ▼
RLS
     │
     ▼
PostgreSQL
```

RLS não substitui a autorização da aplicação.

Se utilizado, o usuário da aplicação não deve possuir `BYPASSRLS`.

## Auditoria

Alterações relevantes são registradas em `audit_logs`, permitindo identificar usuário, entidade, operação, valores relevantes e data da alteração.

## Processamento assíncrono

O processamento assíncrono utiliza Redis quando necessário:

```text
Appointment
     │
     ▼
Redis Queue
     │
     ▼
PHP Worker
     │
     ▼
MailProvider
```

Scheduler e worker podem ser processos PHP CLI.

Não é necessário adicionar outro sistema de mensageria.

## Busca

A busca inicial utiliza PostgreSQL:

```text
Search
   │
   ▼
PostgreSQL
   ├── Full Text Search
   └── pg_trgm
```

Elasticsearch não será utilizado na primeira versão.

## Geolocalização

A concessionária mantém:

```text
latitude
longitude
google_place_id
```

A busca por proximidade pode ser realizada pelo PostgreSQL. PostGIS pode ser adicionado posteriormente se necessário.

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

## Runtime

O ambiente local utiliza Docker Compose com:

```text
nginx
backend
postgres
redis
minio
```

O frontend é servido pelo Nginx, a API é executada pelo PHP-FPM, PostgreSQL é o banco principal, Redis fornece cache e processamento assíncrono e MinIO fornece armazenamento de objetos.

## Princípios

- domínio independente de detalhes de infraestrutura;
- regras de negócio no backend;
- controllers pequenos;
- infraestrutura isolada;
- banco responsável pela integridade dos dados;
- processamento assíncrono quando necessário;
- evitar infraestrutura sem necessidade;
- evitar abstrações sem benefício real;
- manter o projeto simples para o escopo do processo seletivo.
