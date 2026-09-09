# API — Autenticação

```text
POST /api/oauth/token       -- login, renovação, Google, m2m -- ver tabela abaixo
POST /api/register          -- cria conta (seller/customer)
POST /api/logout
POST /api/password-reset
PUT  /api/me/password
```

Mecanismo completo em [`03-security/authentication.md`](../03-security/authentication.md); regras
em [`01-domain/business-rules.md#autenticação-au`](../01-domain/business-rules.md).

## `POST /api/oauth/token`

Sem `grant_type` — o corpo decide:

| Corpo | Resposta |
|---|---|
| `{ email, password }` | `access_token`, `refresh_token`, `expires_in` |
| `{ refresh_token }` | novo par, o anterior é revogado |
| `{ client_id, id_token }` | mesmo par, conta linkada/criada por e-mail do Google |
| `{ client_id, client_secret }` | só `access_token` (m2m, sem refresh) |

Tokens também chegam em cookies `HttpOnly`; a SPA nunca lê o corpo pra guardar o token.

## Credenciais de API (m2m)

```text
GET    /api/me/api-clients
POST   /api/me/api-clients                       { name }
POST   /api/me/api-clients/{id}/rotate-secret
DELETE /api/me/api-clients/{id}
```

`client_secret` só aparece na resposta de `POST`/`rotate-secret`, uma única vez (AC-001). O client
resultante autentica como o dono — mesmo role/RLS de uma sessão normal dele
([`ADR-010`](../02-architecture/decisions/ADR-010.md)).
