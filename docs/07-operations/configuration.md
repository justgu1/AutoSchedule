# Configuração

`make setup` gera o `.env` a partir do `.env.example` com credenciais aleatórias (DB, MinIO) e
chave RSA do JWT — a lista abaixo é só quando algo precisar mudar manualmente.

| Grupo | Variáveis | Nota |
|---|---|---|
| Banco | `DB_DRIVER`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`DB_PORT` | Role admin, migrations/seeders |
| Banco (runtime) | `DB_APP_USERNAME`/`DB_APP_PASSWORD` | Role restrita `NOSUPERUSER NOBYPASSRLS` — é o que faz o RLS valer algo |
| Aplicação | `APP_TIMEZONE` | `America/Sao_Paulo` — o container roda em UTC, "hoje" precisa do fuso certo |
| Rate limit | `RATE_LIMIT_GENERAL_MAX`/`_WINDOW`, `RATE_LIMIT_AUTH_MAX`/`_WINDOW` | Sliding window, geral vs. rotas sensíveis (RL-\*) |
| Paginação | `PAGINATION_DEFAULT_PER_PAGE`, `PAGINATION_MAX_PER_PAGE` | PG-001 |
| Segurança | `COOKIE_SECURE`, `SECURITY_HSTS_ENABLED`, `CORS_ALLOWED_ORIGINS` | |
| Google | `GOOGLE_CLIENT_ID` (login social), `GOOGLE_MAPS_API_KEY` (mapa read-only) | Ausente não quebra a página, só omite a feature |
| E-mail | `MAIL_FROM`, `FRONTEND_URL` (link do reset), `MAILPIT_UI_PORT` | |
| MinIO | `S3_ENDPOINT`/`S3_BUCKET`/`S3_REGION`/`S3_ACCESS_KEY`/`S3_SECRET_KEY`/`S3_PUBLIC_URL`, `TEMP_STORAGE_PATH` | Backup local do upload até o MinIO confirmar |

Comandos completos em [`../../README.md`](../../README.md).
