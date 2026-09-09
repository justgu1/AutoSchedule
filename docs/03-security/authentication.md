# Autenticação

Regras completas em [`01-domain/business-rules.md#autenticação-au`](../01-domain/business-rules.md).
Aqui: mecanismo, não regra de negócio.

## Mecanismo

- **Sessão/token**: JWT RS256 (access token, TTL curto) + refresh token opaco (TTL longo, uso
  único). Chave privada nunca sai do backend; a pública valida assinatura sem redistribuir segredo.
- **Onde trafega**: corpo da resposta (pra quem integra via script) **e** cookies
  `HttpOnly`/`SameSite=Strict` (pra SPA, que nunca lê o token do corpo — evita exposição a roubo
  via XSS que `localStorage` teria).
- **Expiração**: access token curto por natureza (JWT não é revogável antes de expirar); refresh
  token de uso único — reuso de um já rotacionado revoga a família inteira (indício de token
  roubado).
- **Renovação**: `{ refresh_token }` em `POST /oauth/token`, mesmo endpoint do login.
- **CSRF**: mutação autenticada por cookie exige `X-CSRF-Token` batendo com o cookie `XSRF-TOKEN`
  (double-submit); `Authorization: Bearer` explícito pula essa checagem — CSRF só é risco de
  credencial ambiente (cookie).

## Grants (`POST /oauth/token`, sem `grant_type` — o corpo decide)

| Corpo | Grant | Observação |
|---|---|---|
| `{ email, password }` | login | mesma mensagem genérica pra senha errada ou e-mail inexistente |
| `{ refresh_token }` | renovação | reuso de token rotacionado revoga a família |
| `{ client_id, id_token }` | Google Identity Services | linka por e-mail verificado, ou cria `customer` |
| `{ client_id, client_secret }` | `client_credentials` (m2m) | só client confidencial; com dono, autentica como o dono ([`ADR-010`](../02-architecture/decisions/ADR-010.md)) |

## Login social (Google)

`id_token` verificado via JWKS (`firebase/php-jwt`) — `aud`/`iss`/assinatura conferidos,
`email_verified` obrigatório. E-mail batendo com conta existente linka sem mudar role; e-mail novo
cria `customer` com senha aleatória inutilizável (social-only até um reset trocar por uma real).

## Reset de senha

`POST /password-reset` sempre `200`, nunca revela se a conta existe. Link termina no mesmo
endpoint de troca de senha (`PUT /me/password`), que aceita `reset_token` (sem Bearer) ou
`current_password` (autenticado) — o corpo decide qual caminho. Qualquer um dos dois revoga todos
os refresh tokens do usuário.
