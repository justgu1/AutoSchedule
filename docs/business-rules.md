# AutoSchedule — Regras de Negócio

## Usuários

Existem três tipos de usuário:

- `admin`
- `seller`
- `customer`

### Admin

Possui acesso global à aplicação.

### Seller

Pode estar associado a uma ou mais concessionárias. Seu acesso é limitado às concessionárias às quais está associado.

### Customer

Não precisa estar associado a uma concessionária. É identificado por nome, e-mail e telefone durante o agendamento.

## Veículos

Todo veículo pertence a uma única concessionária.

Estados:

- `available`
- `sold`
- `inactive`

Veículos `sold` ou `inactive` não podem receber novos agendamentos.

## Galeria

Um veículo pode possuir várias imagens.

As imagens são armazenadas no MinIO e referenciadas por `path`.

A ordem é definida por `position`, sendo `0` a primeira imagem apresentada.

## Disponibilidade

Um horário somente está disponível quando:

```text
concessionária disponível
        AND
veículo disponível
        AND
horário dentro da disponibilidade do veículo
        AND
horário não bloqueado por exceção
        AND
não existe agendamento ativo no horário
```

Os horários disponíveis são definidos por data. Ao selecionar uma data, somente os horários válidos para aquele dia devem ser apresentados.

## Exemplo

Se a concessionária estiver disponível das 09:00 às 18:00 e o veículo das 10:00 às 15:00, com duração de 60 minutos, os horários possíveis são:

```text
10:00
11:00
12:00
13:00
14:00
```

O intervalo utiliza a convenção `[start, end)`.

## Exceções

Uma exceção pode alterar ou bloquear a disponibilidade de uma concessionária ou veículo em uma data específica.

Exemplos:

- feriado;
- manutenção;
- veículo indisponível;
- horário especial;
- fechamento excepcional.

A exceção específica da data possui prioridade sobre a regra recorrente.

## Agendamento

Fluxo:

```text
visualizar veículo
        ↓
consultar datas disponíveis
        ↓
selecionar data
        ↓
consultar horários disponíveis
        ↓
selecionar horário
        ↓
informar nome, e-mail e telefone
        ↓
confirmar
        ↓
criar agendamento
```

A duração padrão é de 60 minutos.

## Status

```text
pending
   ↓
confirmed
   ├── completed
   └── no_show

pending
   ↓
cancelled
```

`pending` representa uma reserva temporária e possui `expires_at`.

Após a expiração, o horário volta a ficar disponível.

## Concorrência

A aplicação verifica a disponibilidade antes da criação do agendamento.

O PostgreSQL fornece a proteção final contra duas requisições concorrentes para o mesmo veículo e horário.

Conflitos de concorrência devem resultar em `409 Conflict`.

## Cliente

O cliente informa:

- nome;
- e-mail;
- telefone.

Cada agendamento possui os dados do cliente informados no momento da solicitação.

Um novo `customer` é criado caso ainda não exista.

## Autenticação

A senha é armazenada como hash no campo `password`.

O hash deve utilizar um algoritmo apropriado para senhas, como Argon2id.

`password_set_at` pode permanecer `NULL` enquanto a senha ainda não tiver sido definida pelo cliente.

Login: email+senha, sem PKCE/authorization code — API first-party, sem ganho real nesse handshake.

Endpoint único, sem `grant_type`: o corpo decide.

```text
POST /api/oauth/token

{ refresh_token }         -> renovação (outros campos presentes são ignorados)
{ email, password }       -> login
nenhum dos dois           -> 422
```

Resposta: `access_token` (JWT RS256, TTL curto), `refresh_token` (opaco, TTL longo, uso único — reuso revoga a família toda), `expires_in`, `scope` — no corpo (pra quem integra via script/Postman) **e** em cookies `HttpOnly`/`SameSite=Strict` (pra SPA, que nunca lê o token do corpo). Cookie evita exposição a roubo via XSS que `localStorage` teria.

Mutação autenticada por cookie exige o header `X-CSRF-Token` batendo com o cookie `XSRF-TOKEN` (double-submit) — request com `Authorization: Bearer` explícito não precisa disso, CSRF só é risco de credencial ambiente (cookie).

`client_id` identifica a aplicação (hoje só `autoschedule-web`), não o usuário — `role` vem do JWT.

```text
{ client_id, client_secret }   -> client_credentials (M2M), só client confidencial
```

`client_credentials`: sem usuário, sem refresh token (nada pra renovar) — só `access_token` com os `allowed_scopes` do client. Client tem que ser confidencial e provar o secret; mesma mensagem genérica de erro (`Invalid client credentials.`) pra secret errado ou client sem esse grant, não vaza qual é o problema.

### Registro

```text
POST /api/register

{ name, email, phone?, password, role }   -> role in (seller, customer) -- nunca admin
```

Público, sem autenticação. `admin` só é criado via `POST /api/users` (admin autenticado) — nunca uma opção auto-selecionável no registro público.

### Logout

```text
POST /api/logout
```

Lê o `refresh_token` do cookie, revoga a família inteira (mesmo mecanismo do reuso detectado), limpa os cookies `access_token`/`refresh_token`. Sem cookie de refresh, ainda limpa os cookies do lado do client — não é erro, só não tem mais nada a revogar.

### Login social (Google)

```text
{ client_id, id_token }   -> login via Google Identity Services
```

`id_token` vem assinado pelo Google (verificado via JWKS, `firebase/php-jwt`) -- `aud`/`iss`/assinatura conferidos, e `email_verified` tem que ser verdadeiro. E-mail batendo com conta existente (seller/admin inclusive) linka automaticamente, sem mudar role -- e-mail verificado pelo Google já prova posse, mesmo padrão que outros provedores usam. E-mail novo cria conta `customer` com senha aleatória inutilizável (conta social-only até um reset de senha trocar por uma real).

### Reset de senha

```text
POST /api/password-reset

{ email }   -> sempre 200 (não vaza se a conta existe); se existir, manda e-mail (Mailpit em dev) com link de redefinição
```

O link termina no mesmo endpoint de troca de senha que já existe (`PUT /me/password`), sem endpoint duplicado — o corpo decide qual dos dois caminhos:

```text
PUT /me/password

{ reset_token, password }            -> sem Bearer, valida o token (existe, não expirado, não usado)
{ current_password, password }       -> autenticado, valida a senha atual
```

Qualquer um dos dois caminhos revoga todos os refresh tokens do usuário e audita `user.password_changed` com `context.via` (`reset` ou `self`).

## Ciclo de vida da conta (lixeira)

Três estados: `active`, `trashed`, `deleted`. Modelo pensado pra ser reaproveitado por outras entidades com a mesma necessidade (concessionária, por exemplo).

```text
DELETE /me (ou /users/{id})
        │
        ▼
    trashed (revoga todo refresh token -- ninguém continua logado)
        │
        ├── login de novo dentro de 30 dias ──► active (restaurado, sem passo extra)
        ├── admin chama POST /users/{id}/restore ──► active
        ├── POST /me/purge (ou admin /users/{id}/purge) ──► deleted, agora
        └── 30 dias sem recuperação (rotina agendada) ──► deleted, automático
```

`deleted` é terminal: nome/e-mail/telefone escrubados (LGPD, direito ao esquecimento), `id`/`role`/timestamps preservados pra histórico/auditoria continuar válido. A linha nunca é removida do banco -- dado anonimizado deixa de ser dado pessoal (LGPD Art. 12), pode ser retido.

Enquanto `trashed`, a conta ainda existe pra fins de unicidade de e-mail (ninguém mais consegue se cadastrar com aquele e-mail até a purge rodar) mas não aparece como ativa em nenhum fluxo (login com sucesso a restaura antes de mais nada).

`assertNotLastAdmin` só conta admin com `status = active` -- um admin `trashed` já não protege ninguém.

## Autorização

A autorização é aplicada no backend.

- `admin`: acesso global;
- `seller`: acesso às concessionárias associadas;
- `customer`: acesso aos próprios dados e agendamentos.

Troca de role via CRUD admin (`PATCH /api/users/{id}`) aceita qualquer transição (com a trava do último admin). Self-service (`PATCH /me`) só aceita uma: `customer` virando `seller`, por vontade própria -- qualquer outra transição no caminho self é rejeitada (`403`).

Validações do frontend não são mecanismos de segurança.

## Auditoria

Operações relevantes geram registros em `audit_logs`. Implementados hoje:

```text
auth.login.succeeded
auth.login.failed
auth.refresh_token.reused
auth.service_token.issued
user.created
user.profile_updated
user.password_changed
user.deleted
user.trashed
user.restored
user.purged
```

Planejados conforme os domínios abaixo forem implementados:

```text
dealership.created
dealership.updated
dealership.deleted
vehicle.created
vehicle.updated
vehicle.status_changed
vehicle.deleted
availability.created
availability.updated
availability.deleted
appointment.created
appointment.confirmed
appointment.cancelled
appointment.completed
```

Os registros de auditoria são somente de leitura para a aplicação.

## Rate limiting

Toda rota passa por uma política de rate limit antes de qualquer outra verificação (sliding window, por usuário autenticado ou por IP):

- `general` (padrão 1000/min): cobre a API como um todo, com headroom generoso sobre o pico esperado;
- `auth` (padrão 5/min): `POST /oauth/token`, `POST /api/register`, `POST /api/password-reset` e `PUT /me/password` — proteção contra brute-force de login/registro/reset.

Response com `429` inclui `Retry-After`. Falha do Redis não derruba a API — o rate limit fica temporariamente inativo (fail-open) em vez de bloquear todo o tráfego.

## Paginação

Endpoints de listagem aceitam `page`/`per_page` (`per_page` limitado por um máximo configurável) e respondem com `meta.total`/`meta.last_page` junto dos dados.

## Notificações

O envio de e-mails deve ser assíncrono.

Eventos iniciais:

```text
appointment.created
appointment.confirmed
appointment.cancelled
```

O agendamento pode notificar:

- cliente;
- vendedores responsáveis pela concessionária;
- administrador.

A falha no envio do e-mail não deve impedir a criação do agendamento.

## Scheduler

O scheduler PHP executa tarefas periódicas (`ScheduledTask`), cada uma com seu próprio intervalo. O "último run" de cada tarefa fica no Redis, não em memória do processo -- sobrevive a restart e não duplica disparo se subir mais de uma réplica durante rollout.

Tarefas previstas:

- expirar agendamentos pendentes;
- purgar da lixeira (usuário e concessionária) passados 30 dias sem recuperação;
- limpar dados temporários quando necessário.

## Worker

O worker PHP consome uma fila (`Queue`/Redis) e executa tarefas assíncronas, como envio de e-mails.

As tarefas devem ser idempotentes sempre que possível. Falha reenfileira com `attempts` incrementado; passadas 3 tentativas, o job vai pra uma lista de falhas (dead-letter) em vez de tentar pra sempre.

## Busca

A busca utiliza PostgreSQL Full Text Search e `pg_trgm`.

Não é necessário utilizar Elasticsearch na primeira versão.

## Imagens

Arquivos enviados devem ser validados antes do armazenamento, considerando o conteúdo real e o MIME type.

As imagens são armazenadas no MinIO e o PostgreSQL mantém somente suas referências.

## Integridade

As regras críticas devem ser protegidas também pelo banco de dados:

- e-mail único;
- foreign keys;
- associação única entre seller e dealership;
- posição única das imagens;
- intervalos de disponibilidade válidos;
- prevenção de agendamentos concorrentes.
