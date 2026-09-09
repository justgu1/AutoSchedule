# Modelo de dados

PostgreSQL é o único banco. `uuid` como identificador (`gen_random_uuid()`, gerado no banco) —
não expõe contagem de linhas, não depende de round-trip pra saber o id antes de inserir.
`timestamptz` sempre, nunca `timestamp` sem fuso — a aplicação roda em `America/Sao_Paulo`, e
"hoje"/"agora" precisam ser o horário de quem usa o sistema, não UTC cru. `numeric(12,2)` pra
dinheiro, nunca `float`/`double`.

```dbml
Enum user_role { admin  seller  customer }
Enum user_status { active  trashed  deleted }
Enum dealership_status { active  trashed  deleted }
Enum vehicle_status { active  trashed  deleted }
Enum appointment_status { pending  confirmed  completed  cancelled  no_show }
Enum vehicle_transmission { manual  automatic  automated  cvt }
Enum vehicle_body_type { hatch  sedan  suv  pickup  coupe  convertible  minivan  wagon }
Enum vehicle_fuel_type { flex  gasoline  ethanol  diesel  electric  hybrid }

Table users {
  id uuid [pk]
  name varchar(120) [not null]
  email varchar(180) [not null, unique]
  phone varchar(20)
  password varchar(255) [not null]
  role user_role [not null]
  password_set_at timestamptz
  email_verified_at timestamptz
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
  deleted_at timestamptz
  status user_status [not null, default: 'active']
  anonymized_at timestamptz
}

Table oauth_clients {
  id uuid [pk]
  client_id varchar(80) [not null, unique]
  name varchar(120) [not null]
  type varchar(20) [not null] // public | confidential
  secret_hash varchar(255)
  allowed_grant_types text_array [not null]
  redirect_uris text_array
  allowed_scopes text_array [not null, default: '{}']
  owner_user_id uuid                    // NULL = client de sistema, sempre visível
  revoked_at timestamptz
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table dealerships {
  id uuid [pk]
  owner_user_id uuid [not null]
  name varchar(160) [not null]
  slug text [not null, unique]
  zip_code varchar(10) [not null]
  address varchar(255) [not null]
  number varchar(20) [not null]
  complement varchar(120)
  neighborhood varchar(120) [not null]
  city varchar(120) [not null]
  state varchar(2) [not null]
  phone varchar(20)
  email varchar(190)
  photo_file_id uuid
  status dealership_status [not null, default: 'active']
  trashed_by_owner_deactivation boolean [not null, default: false]
  trashed_at timestamptz
  anonymized_at timestamptz
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

// Cache do ViaCEP -- sem TTL (CEP não muda de endereço), sem RLS (não é dado de usuário).
Table zip_code_cache {
  zip_code text [pk]
  street text [not null]
  neighborhood text [not null]
  city text [not null]
  state text [not null]
  created_at timestamptz [not null]
}

Table files {
  id uuid [pk]
  path varchar(500) [not null, unique]   // = checksum do conteúdo (dedupe)
  original_name varchar(255) [not null]
  mime_type varchar(100) [not null]
  size_bytes bigint [not null]
  checksum varchar(64) [not null]
  uploaded_by uuid
  created_at timestamptz [not null]
}

Table vehicles {
  id uuid [pk]
  dealership_id uuid [not null]
  brand varchar(60) [not null]
  model varchar(80) [not null]
  version varchar(80)
  manufacture_year smallint
  model_year smallint
  price numeric(12,2) [not null]
  description text
  mileage_km integer
  transmission vehicle_transmission
  body_type vehicle_body_type
  fuel_type vehicle_fuel_type
  color varchar(40)
  plate_end_digit smallint
  accepts_trade boolean [not null, default: false]
  ipva_paid boolean [not null, default: false]
  licensed boolean [not null, default: false]
  status vehicle_status [not null]
  trashed_by_dealership_trash boolean [not null]
  trashed_at timestamptz
  anonymized_at timestamptz
  search_vector tsvector               // GENERATED, ver "Busca" abaixo
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table vehicle_images {
  id uuid [pk]
  vehicle_id uuid [not null]
  file_id uuid [not null]
  position smallint [not null]
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

// Catálogo GLOBAL, curado por seed -- sem dealership_id, sem trash.
Table vehicle_amenity_catalog {
  id uuid [pk]
  code varchar(60) [not null, unique]
  label varchar(80) [not null]
  position smallint [not null]
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table vehicle_amenity_links {
  vehicle_id uuid [not null]
  amenity_id uuid [not null]
  created_at timestamptz [not null]
  Note: 'PK composta (vehicle_id, amenity_id) -- sem coluna id própria'
}

Table dealership_availability_rules {
  id uuid [pk]
  dealership_id uuid [not null]
  weekday smallint [not null]
  start_time time [not null]
  end_time time [not null]
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table vehicle_availability_rules {
  id uuid [pk]
  vehicle_id uuid [not null]
  weekday smallint [not null]
  start_time time [not null]
  end_time time [not null]
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table availability_exceptions {
  id uuid [pk]
  dealership_id uuid
  vehicle_id uuid
  date date [not null]
  start_time time
  end_time time
  is_available boolean [not null]
  reason varchar(255)
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table appointments {
  id uuid [pk]
  vehicle_id uuid [not null]
  user_id uuid [not null]
  scheduled_at timestamptz [not null]
  customer_name varchar(120) [not null]
  customer_email varchar(180) [not null]
  customer_phone varchar(20) [not null]
  status appointment_status [not null, default: 'pending']
  confirmation_token_hash varchar(64)
  confirmation_email_sent_at timestamptz
  expires_at timestamptz
  picked_up_at timestamptz
  released_at timestamptz
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table audit_logs {
  id uuid [pk]
  actor_id uuid
  user_id uuid
  event varchar(100) [not null]
  auditable_type varchar(100) [not null]
  auditable_id uuid
  old_values jsonb
  new_values jsonb
  ip_address inet
  user_agent text
  created_at timestamptz [not null]
}

Ref: oauth_clients.owner_user_id > users.id
Ref: dealerships.owner_user_id > users.id
Ref: dealerships.photo_file_id > files.id
Ref: files.uploaded_by > users.id
Ref: vehicles.dealership_id > dealerships.id
Ref: vehicle_images.vehicle_id > vehicles.id
Ref: vehicle_images.file_id > files.id
Ref: vehicle_amenity_links.vehicle_id > vehicles.id
Ref: vehicle_amenity_links.amenity_id > vehicle_amenity_catalog.id
Ref: dealership_availability_rules.dealership_id > dealerships.id
Ref: vehicle_availability_rules.vehicle_id > vehicles.id
Ref: availability_exceptions.dealership_id > dealerships.id
Ref: availability_exceptions.vehicle_id > vehicles.id
Ref: appointments.vehicle_id > vehicles.id
Ref: appointments.user_id > users.id
Ref: audit_logs.user_id > users.id
Ref: audit_logs.actor_id > users.id
```

## Significado dos campos — `appointments`

| Campo | Significado | Obrigatório | Regra |
|---|---|---|---|
| `vehicle_id` | Veículo visitado | Sim | Deve existir, `active` |
| `scheduled_at` | Início da visita | Sim | Dentro da janela calculada (AV-001) |
| `status` | Estado do ciclo de vida | Sim | Só as transições de [`state-transitions.md`](../01-domain/state-transitions.md) |
| `customer_name`/`email`/`phone` | Cópia do que o cliente digitou | Sim | Nunca sobrescreve o perfil de `users` (AG-004) |
| `confirmation_token_hash` | Hash do token do e-mail | Só depois do envio | Nasce em `markConfirmationSent()`, nunca antes (AG-009) |
| `expires_at` | Prazo pra confirmar | Só depois do envio | Conta a partir do envio, não da criação (AG-010) |
| `picked_up_at`/`released_at` | Retirada/devolução reais do veículo | Não | `released_at` exige `picked_up_at` antes (AG-006) |

## Significado dos campos — `vehicles` (specs)

| Campo | Significado | Regra |
|---|---|---|
| `manufacture_year`/`model_year` | Ano de fabricação / ano modelo | `model_year >= manufacture_year` quando os dois existem |
| `mileage_km` | Quilometragem rodada | `>= 0` |
| `transmission`/`body_type`/`fuel_type` | Câmbio / carroceria / combustível | Um dos valores do enum, ou `NULL` |
| `plate_end_digit` | Final de placa (rodízio) | `0`–`9` |
| `accepts_trade`/`ipva_paid`/`licensed` | Aceita troca / IPVA pago / licenciado | Booleano, default `false` |

## Imagens (concessionária e veículo)

Armazenadas no MinIO; o PostgreSQL mantém só a referência (`files`). Concessionária tem uma foto só
(`photo_file_id` direto, sem posição); veículo tem galeria (`vehicle_images`, com `position`).
`files.path` é o checksum do conteúdo — dois anúncios com a mesma foto compartilham a linha; apagar
uma imagem só remove do storage quando nenhuma galeria/concessionária mais aponta pra ela.

Reordenar a galeria acontece em duas passadas na mesma transação (o `UNIQUE (vehicle_id, position)`
é imediato, não `DEFERRABLE`): a primeira joga tudo pra faixa negativa (`position = -1 - position`),
a segunda escreve a ordem final.

## Ciclo de vida (lixeira)

`status` é a fonte de verdade; `trashed_at` marca quando entrou na lixeira, `anonymized_at` quando
a anonimização definitiva rodou (idempotência da purga). Reaproveitado por `users`/`dealerships`/
`vehicles` via o mesmo enum PHP (`TrashableStatus`) — só o tipo `ENUM` do Postgres é duplicado por
tabela, porque `CREATE TYPE` é local à tabela que o usa. Detalhe completo em
[`01-domain/state-transitions.md`](../01-domain/state-transitions.md).

`dealerships.trashed_by_owner_deactivation`/`vehicles.trashed_by_dealership_trash` diferenciam
lixeira manual de lixeira em cascata — só a segunda restaura sozinha.

## Disponibilidade

`dealership_availability_rules`/`vehicle_availability_rules` guardam janela por dia da semana
(`weekday`: `0` domingo .. `6` sábado); `availability_exceptions` referencia concessionária **ou**
veículo (nunca os dois, nunca nenhum), com prioridade sobre a regra recorrente daquela data:

```sql
CONSTRAINT ..._weekday_range CHECK (weekday BETWEEN 0 AND 6)
CONSTRAINT ..._valid_interval CHECK (start_time < end_time)
CONSTRAINT availability_exceptions_exactly_one_scope CHECK ((dealership_id IS NOT NULL) <> (vehicle_id IS NOT NULL))
CONSTRAINT availability_exceptions_open_needs_interval CHECK (is_available = false OR (start_time IS NOT NULL AND end_time IS NOT NULL))
```

RLS das três tabelas delega inteiramente pro RLS da concessionária/veículo (`EXISTS (...)`), sem
repetir predicado — inclusive a leitura pública que o motor de disponibilidade precisa pra rodar
sem sessão.

## Agendamentos

Duração sempre 60 minutos (`Appointment::DURATION_MINUTES`, não é coluna). Reservas concorrentes
do mesmo veículo/horário são impedidas pelo banco:

```sql
CREATE UNIQUE INDEX appointments_active_vehicle_time_unique
ON appointments (vehicle_id, scheduled_at)
WHERE status IN ('pending', 'confirmed');
```

RLS de `appointments` **não delega** pro veículo (nome/e-mail/telefone do cliente são PII) — a
policy de leitura pública é restrita a `status IN ('pending', 'confirmed')`, só o suficiente pro
motor de disponibilidade saber o que está ocupado.

## Especificações e itens de veículo

`vehicle_amenity_catalog` é global (sem `dealership_id`, sem trash) — RLS libera `SELECT` pra
qualquer contexto, `INSERT` só pra `admin` (curadoria via seed). `vehicle_amenity_links` delega RLS
pro veículo, mesmo padrão de `vehicle_images` — chave primária composta
`(vehicle_id, amenity_id)`, sem coluna `id` própria.

## Credenciais de API (`oauth_clients`)

`owner_user_id` `NULL` identifica os dois clients de sistema (`autoschedule-web`,
`autoschedule-service`), sempre visíveis independente de contexto de sessão — regressão real já
aconteceu aqui (RLS escondia o client de sistema quando havia cookie obsoleto-mas-não-expirado
durante login; corrigido com uma branch de visibilidade incondicional pra `owner_user_id IS NULL`).
`revoked_at` é revogação soft: não libera o `client_id` pra reuso, e o histórico continua auditável.

## Busca

`search_vector` é coluna **gerada** (`GENERATED ALWAYS AS (...) STORED`), nunca trigger nem escrita
da aplicação — ver [`ADR-007`](../02-architecture/decisions/ADR-007.md).

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;

ALTER TABLE vehicles ADD COLUMN search_vector tsvector
GENERATED ALWAYS AS (
    setweight(to_tsvector('simple'::regconfig, coalesce(brand, '')),               'A') ||
    setweight(to_tsvector('simple'::regconfig, coalesce(model, '')),               'A') ||
    setweight(to_tsvector('simple'::regconfig, coalesce(version, '')),              'B') ||
    setweight(to_tsvector('simple'::regconfig, coalesce(model_year::text, '')),     'B') ||
    setweight(to_tsvector('simple'::regconfig, coalesce(description, '')),          'D')
) STORED;

CREATE INDEX vehicles_search_vector_gin_idx ON vehicles USING GIN (search_vector);
CREATE INDEX vehicles_name_trgm_idx ON vehicles
USING GIN ((brand || ' ' || model || ' ' || coalesce(version, '')) gin_trgm_ops);
```

FTS (`search_vector`, dicionário `simple`) acha palavra inteira e ranqueia por peso; `pg_trgm` acha
erro de digitação, prefixo e substring. Um índice trigram de expressão (não um por coluna) porque a
query casa `%` contra a concatenação inteira. Relevância vive só no `ORDER BY`, nunca no `WHERE` —
`COUNT(*)` usa exatamente o mesmo filtro, e o total nunca discorda da página; desempate por `id`
evita duplicar/pular linha entre páginas quando o rank empata.

## Geolocalização

Não existe — ver [`ADR-008`](../02-architecture/decisions/ADR-008.md).

## Integridade

O banco garante, sempre que possível: e-mail único; foreign keys; concessionária com exatamente um
dono; posições únicas na galeria; intervalos de disponibilidade válidos; valores válidos de
status/papel; agendamentos concorrentes impedidos. Ver [`01-domain/invariants.md`](../01-domain/invariants.md)
pra lista completa com a constraint exata de cada um. A validação da aplicação nunca substitui isso.
