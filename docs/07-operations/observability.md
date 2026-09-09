# Observabilidade

## O que é logado

- Eventos de auditoria (`audit_logs`) — `actor_id`/`user_id` separados, contexto, IP, user agent
  (AD-001). Nunca perde a request que a originou se a gravação falhar (AD-002).
- Erro não mapeado vira `500` com mensagem genérica fora de `APP_DEBUG` — detalhe completo só em
  debug local, nunca em produção.

## O que nunca deve ser logado

- Senha em texto puro, em nenhuma hipótese (AU-001).
- `client_secret`/refresh token em texto puro fora da resposta única de criação (AC-001, AU-004).
- Corpo de request com PII além do que `audit_logs` já registra deliberadamente.

## Eventos de auditoria implementados

```text
auth.login.succeeded          auth.login.failed              auth.refresh_token.reused
auth.service_token.issued     user.created                    user.profile_updated
user.password_changed         user.deleted                    user.trashed
user.restored                 user.purged                     dealership.created
dealership.updated            dealership.trashed              dealership.restored
dealership.purged             dealership.owner_reassigned      dealership.photo_updated
dealership.photo_removed      vehicle.created                 vehicle.updated
vehicle.dealership_reassigned vehicle.images_added             vehicle.image_removed
vehicle.images_reordered      vehicle.trashed                  vehicle.restored
vehicle.purged                availability.created             availability.updated
availability.deleted          appointment.created              appointment.confirmed
appointment.cancelled         appointment.completed
```

`availability.*` cobre as três tabelas de disponibilidade — `auditable_id` distingue qual.
`appointment.*` não audita `pickup`/`release`/`no_show`: as próprias colunas de timestamp
(`picked_up_at`, `released_at`) já são o registro, e `completed` audita através do `release`.

## Progresso de job assíncrono

Job que o cliente acompanha (upload de foto) grava status num `JobStatusStore` (Redis, TTL) —
`queued` → `processing` → `done`/`failed`. `GET /jobs/{id}` devolve o snapshot; `GET /jobs/{id}/events`
expõe o mesmo dado como Server-Sent Events, um evento por mudança.

## Fila e scheduler

Falha de job reenfileira com `attempts` incrementado; 3 tentativas vira dead-letter (SC-002) — a
lista de dead-letter é o lugar pra investigar job que não completou. "Último run" de cada tarefa
do scheduler fica no Redis (SC-001), não em log — consultável direto, sobrevive restart.
