# Worklist

Backlog do projeto: epic > issue > task. Cada `[x]` já está em `main`; `[ ]` é o que falta. A ordem das epics é a ordem de dependência real (uma concessionária precisa existir antes de ter veículo; um veículo precisa de disponibilidade antes de agendamento), não um cronograma fixo.

## Epic: Fundação

### Issue: Ambiente local

- [x] Docker Compose (backend, frontend, nginx, postgres, redis, minio, mailpit)
- [x] `docker-compose.override.yml` -- bind mount + HMR do Vite, nunca em produção
- [x] Makefile (`setup`/`up`/`down`/`build`/`logs`/`ps` + geração de credenciais e chaves RSA)
- [x] `.env.example`, `.gitignore`, `.dockerignore`

### Issue: Backend PHP sem framework

- [x] Bootstrap da aplicação (`Application`, `config/*.php`)
- [x] Router + pipeline de middlewares
- [x] Container de injeção de dependência (`ContainerFactory`, bindings explícitos)
- [x] Sistema de migrations e seeders (PHP, idempotente)

### Issue: Frontend

- [x] Vite + React + TypeScript + Material UI + TanStack Query
- [x] Dockerfile multi-stage (build Node -> runtime Nginx)

## Epic: Conta e autenticação

### Issue: Domínio de usuário

- [x] `User`/`UserRole` (`admin`/`seller`/`customer`)
- [x] Registro público (`POST /register`, role `seller`/`customer`, nunca `admin`)
- [x] Self-service (`GET`/`PATCH /me`, self-upgrade `customer` -> `seller`)
- [x] CRUD admin (`GET`/`POST /users`, `PATCH`/`DELETE /users/{id}`, trava do último admin)

### Issue: OAuth e sessão

- [x] `POST /oauth/token` único, sem `grant_type` -- corpo decide (`email`+`password`, `refresh_token`, `id_token`, `client_id`+`client_secret`)
- [x] JWT RS256 (`TokenIssuer`), refresh token opaco com rotação e reuse-detection
- [x] `client_credentials` (M2M)
- [x] Login social via Google (Identity Services, linka conta existente por e-mail ou cria `customer`)
- [x] Cookies `HttpOnly`/`SameSite=Strict` + CSRF double-submit
- [x] Reset de senha por e-mail (Mailpit em dev, `symfony/mailer`)
- [x] Logout (revoga a família do refresh token)

### Issue: Segurança e limites de acesso

- [x] Row-Level Security em `users` (`autoschedule_app`, `NOSUPERUSER NOBYPASSRLS`)
- [x] Rate limiting (Redis, sliding window, policies `general`/`auth`, fail-open)
- [x] Security headers + CORS
- [x] Paginação (`GET /users`)
- [x] Audit log (`audit_logs`, ator separado do alvo da ação)

### Issue: Ciclo de vida da conta (lixeira)

- [x] Estados `active`/`trashed`/`deleted`, reversível por 30 dias
- [x] `DELETE /me` move pra lixeira e revoga toda sessão ativa
- [x] Login restaura automaticamente dentro da janela
- [x] `POST /users/{id}/restore`/`purge` (admin) e `/me/purge` (self-service)
- [x] Rotina agendada purga quem passou dos 30 dias

## Epic: Armazenamento de arquivos

- [x] `StorageProvider` port + `MinioAdapter` (Flysystem S3)
- [x] Tabela `files` (metadado: path/mime/tamanho/checksum/uploaded_by)
- [x] `UploadFile` -- backup local até o MinIO confirmar sucesso

## Epic: Processamento assíncrono

- [x] Fila (`Queue`/`RedisQueue`) com retry e dead-letter após 3 tentativas
- [x] Scheduler (`ScheduledTask`, estado em Redis -- sobrevive restart, não duplica disparo entre réplicas)
- [x] `bin/worker.php`/`bin/scheduler.php`, deployments próprios no k8s
- [x] Envio de e-mail de fato assíncrono (reset de senha via fila)

## Epic: Concessionária

### Issue: Domínio

- [x] `Dealership`, dono via `owner_user_id` (sem tabela de associação)
- [x] CRUD (`GET`/`POST /dealerships`, `PATCH`/`DELETE /dealerships/{id}`) -- seller só as próprias, admin qualquer uma
- [x] RLS em `dealerships` (admin-or-owner + policy de serviço pra scheduler/worker)
- [x] Foto única (não galeria) -- `POST`/`DELETE /dealerships/{id}/photo`, substitui a anterior e apaga do storage

### Issue: Ciclo de vida

- [x] Mesmo modelo de lixeira da conta, reaproveitado (`TrashableStatus` compartilhado)
- [x] Lixeira manual e em cascata (conta do dono desativada), só a cascata restaura sozinha
- [x] `AuditLogger` generalizado pra qualquer `auditableType`, não só `User`
- [x] Rotina de purga genérica (`PurgeTrashedEntitiesTask`), reaproveitada entre usuário e concessionária
- [x] Listagem paginada tanto pro admin quanto pro seller

### Issue: Upload assíncrono de foto

- [x] `POST /dealerships/{id}/photo` só enfileira (`202`+`job_id`) -- processamento roda no worker, não no request
- [x] `ImageOptimizer` (GD): converte pro padrão do site (WebP, redimensionada a até 1600px), reaproveitado sem alteração pelo import em lote da galeria de veículo
- [x] Limite de 20MB, validado antes de enfileirar
- [x] `JobStatusStore` (Redis) + `GET /jobs/{id}` (snapshot) e `GET /jobs/{id}/events` (SSE) -- genérico, não específico de foto de concessionária
- [x] RLS: scheduler/worker (contexto de serviço) faltava em `dealerships` desde a Epic acima -- achado e corrigido só agora que um job de verdade passou a rodar em background sobre essa tabela

### Issue: Frontend

- [x] Tela de seller/admin (adaptativa por role, listar/criar/editar/lixeira, foto)
- [x] Admin reassocia dono (campo `owner_user_id` visível só pro admin, mesmo form)
- [x] Miniatura da foto na listagem
- [x] Upload de foto acompanhado ao vivo (SSE) com barra de progresso
- [x] UF como campo de busca (Autocomplete), não texto livre
- [x] Número da concessionária só aceita dígito
- [x] Atalho "usar o meu" pra copiar o telefone do próprio seller no formulário
- [x] Link "Ver página pública" na listagem, pra concessionária ativa (ver Epic Endereço abaixo)
- [x] E2E (seller: ciclo completo incl. foto assíncrona; admin: reassociação de dono; customer: sem acesso)

## Epic: Endereço

- [x] Autocomplete de CEP no formulário de concessionária -- `GET /zip-codes/{cep}` no próprio backend, proxy do ViaCEP cacheado (`zip_code_cache`, sem TTL) depois da primeira consulta, front nunca chama o terceiro direto
- [x] Concessionária ganha `email` próprio (contato do negócio, ao lado do `phone` que já existia)
- [x] Página pública da concessionária (`/concessionarias/{slug}`, sem conta) -- banner, endereço, contato, nome do vendedor (só o nome, nenhum outro dado dele), veículos reservado pra quando a vitrine pública do estoque existir
- [x] `GET /dealerships/{id}` unificado -- mesma rota do gerenciamento, resposta muda conforme quem chama (perfil completo pro dono/admin, perfil público pro resto); RLS com contexto de leitura pública próprio (`app.is_public_read`, composto com o contexto autenticado normal, só concessionária `active` visível)
- [x] `slug` amigável (nome + parte do id) substitui o `id` na URL pública -- estável mesmo se o nome mudar, só troca na anonimização
- [x] Exibição em mapa pro cliente (Google Maps Embed, read-only, modo `place` por endereço)

## Epic: Veículo

- [x] `Vehicle` (marca/modelo/versão/ano/preço/descrição/status), pertence a uma concessionária -- status é só a lixeira (`active`/`trashed`/`deleted`), idêntica à da conta e da concessionária; sem estado "vendido" nem "agendado" guardado; dono transitivo pela concessionária (RLS por `EXISTS`, sem `owner_user_id` duplicado)
- [x] Galeria de fotos -- referencia `files` como a foto da concessionária, upload em lote pela fila com um `job_id` só, reordenação em duas passadas, arquivo compartilhado só sai do storage quando ninguém mais aponta pra ele
- [ ] GC de `files` órfãos: a purga agendada limpa linha, não storage, então arquivo sem referência sobra pago no MinIO
- [x] Busca (PostgreSQL Full Text Search + `pg_trgm`) -- filtros empilhados na mesma rota da listagem, `search_vector` gerado, filtro de marca alcançando a descrição, facetas do estoque em `GET /vehicles/filters`
- [x] CRUD (seller gerencia os das próprias concessionárias, admin qualquer um) -- lixeira/restore/purge iguais aos outros domínios, cascata de dois níveis (conta -> concessionária -> veículo), auditoria `vehicle.*`
- [x] Painel do vendedor -- tabela com filtros empilhados, form, galeria e lixeira, mesmo molde do painel de concessionárias
- [x] Catálogo público -- `GET /vehicles`/`GET /vehicles/{id}` viram públicas por padrão (`scope=mine` reusa a mesma rota pro painel), index do site com filtros e vitrine no perfil da concessionária (até 12 veículos, `vehicles_total` separado)

## Epic: Disponibilidade e agendamento

Fluxo do cliente final -- o motivo de tudo acima existir:

### Issue: Disponibilidade

- [ ] Regras recorrentes por concessionária e por veículo (dia da semana + janela de horário)
- [ ] Exceções pontuais por data (feriado, manutenção, horário especial)
- [ ] Cálculo do horário efetivo: concessionária ∩ veículo ∩ exceção ∩ sem conflito de agendamento

### Issue: Agendamento do cliente

- [ ] Detalhe do veículo, datas e horários disponíveis
- [ ] Formulário (nome, e-mail, telefone), sem exigir conta
- [ ] Criação transacional (valida disponibilidade -> cria/localiza customer -> cria appointment -> audita)
- [ ] Proteção contra reserva concorrente do mesmo veículo/horário (`409 Conflict`)
- [ ] Notificação por e-mail (cliente, vendedores da concessionária, admin)

## Epic: Qualidade

- [x] PHPStan nível 10, PHP-CS-Fixer, Rector (backend)
- [x] ESLint type-aware + Prettier (frontend)
- [x] Suíte E2E (Playwright): login, registro, reset de senha, logout, self-upgrade, lixeira de conta, teclado, acessibilidade
- [x] Suíte de carga (k6)
- [x] Acessibilidade WCAG 2.1 AA (axe-core)
- [ ] Suíte própria pro `UserController`/`DealershipController`/`VehicleController` (hoje cobertos indiretamente -- ver `docs/test-catalog.md`)
- [x] E2E do domínio de concessionária

### Issue: Camada de aplicação explícita

- [x] `src/Application/`: caso de uso por ação (`LoginWithPassword`, `CreateDealership`, `TrashAccount`, ...), tirando a regra de negócio dos controllers
- [x] `Domain/Ports/` dissolvido: cada port foi pra camada de quem depende dele (`StorageProvider` -> `Domain/File/Ports/`, `Queue`/`Job` -> `Application/Ports/`, `DatabaseConnection`/`ScheduledTask` -> `Infrastructure/`)
- [x] `OAuthService` (237 linhas, 8 ports) quebrado em 5 casos de uso + 3 colaboradores; o acoplamento `Domain\Auth -> Domain\Dealership` virou composição legítima na Application
- [x] `Application.php` (era um config holder, não bootstrap) renomeado pra `Config`, com acesso tipado por caminho (`$app->int('auth.access_token_ttl')`)
- [x] `Validator` devolve `ValidatedInput` tipado: `mixed` morre na fronteira HTTP em vez de vazar até a entidade
- [x] Módulos no singular (`Domain/User/`, `Infrastructure/Dealership/`, ...) e `Domain/Support/` fundido em `Domain/Shared/`
- [x] Deptrac no CI (`make arch`) -- a regra de dependência entre camadas deixou de ser só prosa no `docs/architecture.md`
- [x] `#[Group('integration')]` separa os testes que precisam de Postgres/Redis/MinIO dos puros (`make test-unit`)
- [x] `phpstan-baseline.neon` reduzida, zero entrada em `Domain/`, `Application/` e `Bootstrap/`

## Epic: CI/CD e deploy

- [x] GitHub Actions (backend, frontend, phpunit, e2e, load-test, build-and-push)
- [x] Publicação das imagens no GHCR
- [x] Manifests Kubernetes (`infra/k8s`: backend, worker, scheduler, frontend, ingressroute, sealed-secret)
- [x] GitOps via ArgoCD (auto-sync + prune + selfHeal, image-updater por digest, monorepo sem repositório separado)
- [x] Deploy validado em produção, sem passo manual entre merge e release

## Pendente fora da sequência acima

- [ ] Migrar o frontend pra SSR (Next.js/Remix ou equivalente) -- hoje é SPA 100% client-rendered (`frontend/src/main.tsx` monta tudo via `createRoot`), então um navegador sem JavaScript não vê conteúdo nenhum além do `<noscript>` de aviso em `index.html`. SSR entrega HTML já renderizado no primeiro request.
