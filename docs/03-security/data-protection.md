# Proteção de dados

## Senhas

- Hash Argon2id (`password_hash`/`password_verify`), campo `password` — nunca texto puro.
- Nunca aparece em log, em resposta de API, nem em mensagem de auditoria.
- `password_set_at` fica `NULL` enquanto a conta ainda não teve senha própria definida (ex:
  conta criada via Google, social-only).

## PII (dado pessoal)

| Onde | O quê | Ciclo de vida |
|---|---|---|
| `users` | nome, e-mail, telefone | anonimizado na purge ([`ADR-003`](../02-architecture/decisions/ADR-003.md)) |
| `dealerships` | nome, endereço (rua/número/complemento), telefone, e-mail do negócio | anonimizado na purge, CEP/cidade/UF preservados |
| `appointments` | nome/e-mail/telefone do cliente | cópia própria do momento da reserva, não referencia `users` pra exibição |
| `vehicles` | nenhum dado pessoal | purge só marca `deleted`, marca/modelo sobrevivem pro histórico |

Anonimização nunca é hard-delete — a linha permanece (id/role/timestamps), deixando de ser dado
pessoal (LGPD Art. 12) sem quebrar referência de auditoria/histórico.

## Segredos

- Secret de credencial m2m (`oauth_clients.secret_hash`) e refresh token são hash — texto puro
  existe só na resposta de criação/rotação, uma única vez (AC-001).
- Chave privada RSA do JWT nunca sai do backend; só a pública é distribuída pra quem precisa
  validar assinatura.
- Credenciais de infraestrutura (`.env`, `backend/storage/keys/`) nunca entram no repositório —
  `make setup` gera tudo localmente; produção usa sealed-secrets (ver
  [`07-operations/deployment.md`](../07-operations/deployment.md)).

## Entrada

- Todo corpo passa por `Validator` → `ValidatedInput` tipado antes de chegar em qualquer caso de
  uso — `mixed` morre na fronteira HTTP.
- Upload de imagem: MIME validado pelo conteúdo real (não extensão), limite de tamanho antes de
  enfileirar, sempre reconvertido (GD, WebP) antes de gravar — nunca o arquivo cru do cliente.
- SQL nunca concatenado — todo bind passa por `DatabaseConnection::execute()`.

## Infraestrutura

- TLS termina no Nginx (ingress, em produção).
- Cookies de sessão `HttpOnly`, `SameSite=Strict`, `Secure` fora de dev.
- Banco roda com role `NOSUPERUSER NOBYPASSRLS` em runtime — a role de migration/seed é separada
  e não é a que a aplicação usa pra servir request.
