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
- [x] `FileUploadService` -- backup local até o MinIO confirmar sucesso

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
- [x] `ImageOptimizer` (GD): converte pro padrão do site (WebP, redimensionada a até 1600px), reaproveitável por qualquer domínio futuro (import em lote da galeria de veículo, por exemplo)
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
- [x] E2E (seller: ciclo completo incl. foto assíncrona; admin: reassociação de dono; customer: sem acesso)

## Epic: Endereço

- [ ] Autocomplete de CEP no formulário de concessionária (ViaCEP)
- [ ] Exibição em mapa pro cliente (Google Maps Embed, read-only)

## Epic: Veículo

- [ ] `Vehicle` (marca/modelo/versão/ano/preço/status), pertence a uma concessionária
- [ ] Galeria de fotos (mesmo padrão de `dealership_images`)
- [ ] Busca (PostgreSQL Full Text Search + `pg_trgm`)
- [ ] CRUD (seller gerencia os das próprias concessionárias, admin qualquer um)

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
- [ ] Suíte própria pro `UserController`/`DealershipController` (hoje cobertos indiretamente -- ver `docs/test-catalog.md`)
- [x] E2E do domínio de concessionária

## Epic: CI/CD e deploy

- [x] GitHub Actions (backend, frontend, phpunit, e2e, load-test, build-and-push)
- [x] Publicação das imagens no GHCR
- [x] Manifests Kubernetes (`infra/k8s`: backend, worker, scheduler, frontend, ingressroute, sealed-secret)
- [x] GitOps via ArgoCD (auto-sync + prune + selfHeal, image-updater por digest, monorepo sem repositório separado)
- [x] Deploy validado em produção, sem passo manual entre merge e release

## Pendente fora da sequência acima

- [ ] Migrar o frontend pra SSR (Next.js/Remix ou equivalente) -- hoje é SPA 100% client-rendered (`frontend/src/main.tsx` monta tudo via `createRoot`), então um navegador sem JavaScript não vê conteúdo nenhum além do `<noscript>` de aviso em `index.html`. SSR entrega HTML já renderizado no primeiro request.
