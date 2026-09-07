# AutoSchedule — Banco de Dados

## Banco de dados

PostgreSQL é o único banco do projeto. `uuid` como identificador (gerado no banco, `gen_random_uuid()`) em vez de serial -- não expõe contagem de linhas, não depende de round-trip pra saber o id antes de inserir. `timestamptz` sempre, nunca `timestamp` sem fuso -- o container roda em UTC e "hoje" muda de acordo com quem pergunta. `numeric(12,2)` pra dinheiro, nunca `float`/`double` (arredondamento binário não é problema que se quer perto de preço).

## Modelo de dados

```dbml
Enum user_role {
  admin
  seller
  customer
}

Enum user_status {
  active
  trashed
  deleted
}

Enum dealership_status {
  active
  trashed
  deleted
}

Enum vehicle_status {
  available
  sold
  inactive
}

Enum appointment_status {
  pending
  confirmed
  completed
  cancelled
  no_show
}

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

Table dealerships {
  id uuid [pk]
  owner_user_id uuid [not null]
  name varchar(160) [not null]
  zip_code varchar(10) [not null]
  address varchar(255) [not null]
  number varchar(20) [not null]
  complement varchar(120)
  neighborhood varchar(120) [not null]
  city varchar(120) [not null]
  state varchar(2) [not null]
  latitude decimal(10,7)
  longitude decimal(10,7)
  google_place_id varchar(255)
  phone varchar(20)
  photo_file_id uuid
  status dealership_status [not null, default: 'active']
  trashed_by_owner_deactivation boolean [not null, default: false]
  trashed_at timestamptz
  anonymized_at timestamptz
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
}

Table files {
  id uuid [pk]
  path varchar(500) [not null, unique]
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
  year smallint
  price numeric(12,2) [not null]
  thumbnail_path varchar(500)
  status vehicle_status [not null]
  search_vector tsvector
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
  deleted_at timestamptz
}

Table vehicle_images {
  id uuid [pk]
  vehicle_id uuid [not null]
  path varchar(500) [not null]
  position smallint [not null]
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
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
  duration_minutes smallint [not null, default: 60]
  expires_at timestamptz
  customer_name varchar(120) [not null]
  customer_email varchar(180) [not null]
  customer_phone varchar(20) [not null]
  status appointment_status [not null]
  created_at timestamptz [not null]
  updated_at timestamptz [not null]
  deleted_at timestamptz
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

Ref: dealerships.owner_user_id > users.id
Ref: dealerships.photo_file_id > files.id
Ref: files.uploaded_by > users.id
Ref: vehicles.dealership_id > dealerships.id
Ref: vehicle_images.vehicle_id > vehicles.id
Ref: dealership_availability_rules.dealership_id > dealerships.id
Ref: vehicle_availability_rules.vehicle_id > vehicles.id
Ref: availability_exceptions.dealership_id > dealerships.id
Ref: availability_exceptions.vehicle_id > vehicles.id
Ref: appointments.vehicle_id > vehicles.id
Ref: appointments.user_id > users.id
Ref: audit_logs.user_id > users.id
Ref: audit_logs.actor_id > users.id
```

## Imagens (concessionária e veículo)

As imagens são armazenadas no MinIO. O PostgreSQL mantém somente a referência ao objeto.

Concessionária tem só uma foto -- `dealerships.photo_file_id` referencia `files` (metadado genérico de upload, reaproveitado por qualquer domínio) direto, sem tabela de junção nem posição. Veículo terá galeria de verdade (várias fotos, ordenadas por `position`) quando esse domínio for implementado -- `vehicle_images` na modelagem abaixo ainda não existe de fato.

```text
position = 0 → primeira imagem
position = 1 → segunda imagem
position = 2 → terceira imagem
```

## Senhas

A senha é armazenada somente como hash no campo `password`.

`password_set_at` pode ser `NULL` enquanto a senha ainda não tiver sido definida pelo próprio cliente.

A senha em texto puro nunca deve ser persistida ou registrada em logs.

## Ciclo de vida (lixeira)

`status` (`active`/`trashed`/`deleted`) é a fonte de verdade do ciclo de vida -- `deleted_at`/`trashed_at` marca quando entrou na lixeira (não é mais "deletado = deleted_at preenchido" sozinho). `anonymized_at` marca quando a anonimização definitiva rodou, idempotência da rotina de purge.

O mesmo modelo de três estados vale pra `users` e `dealerships` -- na aplicação os dois reaproveitam o mesmo enum PHP (`App\Domain\Shared\TrashableStatus`), só o tipo `ENUM` do Postgres é duplicado por tabela (`user_status`/`dealership_status`) porque cada `CREATE TYPE` é local à tabela que o usa.

`dealerships.trashed_by_owner_deactivation` diferencia lixeira manual (o seller apagou a própria concessionária) de lixeira em cascata (o seller desativou a conta) -- só a segunda é restaurada automaticamente quando o seller volta a logar.

Índice em `status` (`users_status_idx`/`dealerships_status_idx`) -- toda query de listagem/login filtra por ele.

Convenção de visibilidade nas queries:

```text
users:       findByEmail/findById/existsByEmail/findPage/count       -> status <> 'deleted'  (mostra active + trashed)
users:       countByRole (trava do último admin)                     -> status = 'active'    (só ativo protege)
dealerships: findByOwner/findPage/count/countByOwner                 -> status <> 'deleted'  (mostra active + trashed)
dealerships: findById                                                -> sem filtro           (admin/restore/purge acham mesmo depois de anonimizado)
```

## Auditoria

`user_id` é a conta afetada pela ação; `actor_id` é quem a executou. Numa ação sobre a própria conta os dois são o mesmo id; quando um admin age sobre outro usuário, divergem — sem essa separação não dava pra saber quem de fato agiu.

## Disponibilidade

A disponibilidade combina a disponibilidade da concessionária, a disponibilidade do veículo, exceções e agendamentos existentes.

Os intervalos devem respeitar:

```sql
CHECK (start_time < end_time)
```

Convenção de `weekday`:

```text
0 = Domingo
1 = Segunda-feira
2 = Terça-feira
3 = Quarta-feira
4 = Quinta-feira
5 = Sexta-feira
6 = Sábado
```

## Agendamentos

O PostgreSQL deve impedir reservas concorrentes do mesmo veículo e horário:

```sql
CREATE UNIQUE INDEX appointments_active_vehicle_time_unique
ON appointments (vehicle_id, scheduled_at)
WHERE deleted_at IS NULL
  AND status IN ('pending', 'confirmed');
```

## Imagens

Cada posição da galeria de veículo deve ser única dentro do próprio veículo (concessionária não tem galeria, só uma foto, sem posição pra ter conflito):

```sql
CREATE UNIQUE INDEX vehicle_images_position_unique
ON vehicle_images (vehicle_id, position);
```

## Busca

A busca inicial utiliza PostgreSQL Full Text Search e `pg_trgm`.

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX vehicles_search_vector_gin_idx
ON vehicles USING GIN (search_vector);

CREATE INDEX vehicles_brand_trgm_idx
ON vehicles USING GIN (brand gin_trgm_ops);

CREATE INDEX vehicles_model_trgm_idx
ON vehicles USING GIN (model gin_trgm_ops);

CREATE INDEX vehicles_version_trgm_idx
ON vehicles USING GIN (version gin_trgm_ops);
```

## Geolocalização

A concessionária mantém latitude, longitude e Google Place ID.

PostGIS pode ser adicionado posteriormente caso seja necessário.

## Integridade

O banco deve garantir, sempre que possível:

- e-mail único;
- integridade das foreign keys;
- cada concessionária pertence a exatamente um seller (`owner_user_id` `NOT NULL`, sem tabela de associação);
- posições únicas na galeria;
- intervalos de disponibilidade válidos;
- valores válidos para status e papéis;
- proteção contra agendamentos concorrentes.

A validação da aplicação não substitui as constraints do banco.
