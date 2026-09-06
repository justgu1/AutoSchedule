# Worklist

## Sprint de 7 dias

**Período:** 02/09/2026 a 09/09/2026

**Objetivo:** desenvolver, testar, documentar e publicar o primeiro release do AutoSchedule, com aplicação containerizada, pipeline automatizado e deploy em Kubernetes utilizando GitOps e ArgoCD.

---

### Dia 1 — Fundação e ambiente local

- [x] Configurar backend PHP 8.4
- [x] Configurar PHP-FPM
- [x] Configurar Composer
- [x] Configurar frontend React + TypeScript
- [x] Configurar Vite
- [x] Configurar Material UI
- [x] Configurar TanStack Query
- [x] Criar Dockerfile do backend
- [x] Criar Dockerfile multi-stage do frontend
- [x] Configurar Docker Compose
- [x] Definir variáveis de ambiente
- [x] Definir estrutura inicial da aplicação
- [x] Definir estratégia de imagens Docker
- [x] Configurar Nginx como gateway da aplicação
- [x] Configurar PostgreSQL
- [x] Configurar Redis
- [x] Configurar MinIO
- [x] Configurar armazenamento temporário para uploads
- [x] Automatizar configuração do ambiente através do Makefile
- [x] Automatizar instalação das dependências
- [x] Configurar build do frontend
- [x] Validar ambiente local completo
- [x] Validar acesso ao frontend
- [x] Validar endpoint `/health`
- [x] Criar `.env.example`
- [x] Criar `.gitignore`
- [x] Criar `.dockerignore`
- [x] Criar documentação inicial
- [x] Criar branch de setup
- [x] Aplicar Conventional Commits

### Dia 2 — Banco de dados e domínio

- [x] Implementar bootstrap da aplicação PHP (`Application`, `config/app.php`)
- [x] Corrigir duplicação da rota `/api` no Nginx
- [x] Configurar live-reload do backend (PHP) em ambiente local
- [x] Configurar live-reload do frontend (Vite) em ambiente local
- [x] Isolar ambiente de desenvolvimento via `docker-compose.override.yml`
- [x] Definir modelo de dados
- [x] Definir relacionamentos
- [x] Definir índices e constraints
- [x] Configurar banco de dados na aplicação
- [x] Criar migrations (`users`, `oauth_clients`, `oauth_refresh_tokens`, RLS/role de app, `audit_logs`)
- [ ] Criar entidade de veículo
- [ ] Criar entidade de cliente
- [ ] Criar entidade de agendamento
- [x] Implementar persistência (`users`, `oauth_clients`, `oauth_refresh_tokens`, `audit_logs` — veículo/agendamento pendente)
- [x] Criar dados iniciais para desenvolvimento (seeders: admin + oauth clients)
- [ ] Implementar regras de disponibilidade
- [ ] Validar modelo e persistência (validado só pro escopo de usuário/auth acima)

### Entregue além do escopo original — fundação de autenticação e API

Priorizado antes do domínio de veículo/agendamento porque o primeiro endpoint real do projeto precisava fixar o padrão de autenticação, autorização e segurança que o resto do sistema (concessionárias, veículos, agendamentos) vai seguir depois.

- [x] Router, pipeline de middlewares, container de injeção de dependência
- [x] Tratamento central de exceções, validação, fundação de logging e Redis
- [x] Sistema de migrations e seeders
- [x] Domínio de usuário (`User`, `UserRole`, self-service `/me`, CRUD admin `/users`)
- [x] Registro de OAuth clients, emissão de JWT (RS256)
- [x] Autenticação: endpoint único `POST /oauth/token` (login/refresh pelo formato do body, sem `grant_type`), refresh token opaco com rotação e reuse-detection
- [x] `AuthContextMiddleware` + `RoleMiddleware`: claims por request, roles declarados por rota
- [x] Row-Level Security em `users`, role de banco restrita (`autoschedule_app`, `NOSUPERUSER NOBYPASSRLS`)
- [x] Audit log (`audit_logs`, `actor_id` separado de `user_id`) nos fluxos de auth e nas rotas de usuário
- [x] Rate limiting (Redis, sliding window, policies `general`/`auth`, fail-open)
- [x] Paginação em `GET /users`
- [x] Suíte de teste de carga (k6)
- [x] Telas de login, registro, esqueci/redefinir senha e perfil (`/me`) — 3 layouts (`PublicLayout`/`AuthLayout`/`AuthenticatedLayout`), componentizado (`FormTextField`/`SubmitButton`/`FormError`)
- [x] `client_credentials` (M2M)
- [x] Login social via Google (Identity Services, conta existente linka por e-mail, e-mail novo cria customer)
- [x] Self-service: customer vira seller (`PATCH /me`)
- [x] Security headers + CORS
- [x] Cookies `HttpOnly`/`SameSite=Strict` + CSRF (double-submit `XSRF-TOKEN`)
- [x] Registro público (`POST /api/register`, role selecionável seller/customer)
- [x] Reset de senha por e-mail (Mailpit + `symfony/mailer`, template HTML próprio)
- [x] Logout (`POST /api/logout`, revoga refresh token e limpa cookies)

### Entregue além do escopo original — pré-requisitos do domínio de concessionária

Concessionária precisa de foto (→ MinIO) e a lixeira com purge após 30 dias precisa de tarefa agendada de verdade (→ Scheduler/Worker), então essas duas peças de infraestrutura entram antes do domínio em si. Ordem: MinIO → Scheduler/Worker → desativação de conta (User) → Concessionária → Endereço (ViaCEP + mapa).

- [x] `StorageProvider` port + `MinioAdapter` (Flysystem S3, bucket compartilhado de prod provisionado em `infra-k8s`)
- [x] Tabela `files` (metadado: path/mime/tamanho/checksum/uploaded_by) + `FileUploadService` — backup local até confirmar sucesso no storage, nunca antes
- [x] Fila (`Queue`/`RedisQueue`, Redis): retry automático, dead-letter (`jobs:failed`) após 3 tentativas
- [x] Scheduler (`ScheduledTask`, Redis-backed): tarefas periódicas sem duplicar disparo entre réplicas
- [x] `bin/worker.php`/`bin/scheduler.php`, deployments próprios em k8s (mesma imagem do backend, comando diferente)
- [x] Envio de e-mail assíncrono de verdade (reset de senha via fila — fecha lacuna que já era regra de negócio documentada e nunca tinha sido implementada)
- [x] Ciclo de vida de conta (lixeira): `active`/`trashed`/`deleted`, reversível por 30 dias -- `DELETE /me` move pra lixeira, login de novo restaura sozinho, admin restaura/purga explícito (`POST /users/{id}/restore`/`purge`), rotina agendada purga quem passou da janela
- [x] Modelo pensado pra ser reaproveitado por outras entidades (concessionária, próximo passo)

### Dia 3 — API e regras de negócio

- [x] Definir contratos da API (`GET /api` funciona como catálogo: lista endpoint, método, descrição e campos aceitos por role)
- [ ] Implementar API REST (feito para usuário/auth; pendente pro domínio de veículo/agendamento)
- [ ] Implementar endpoint de detalhes do veículo
- [ ] Implementar endpoint de datas disponíveis
- [ ] Implementar endpoint de horários disponíveis
- [ ] Implementar endpoint de criação de agendamento
- [x] Implementar validações (`Validator`, aplicado em toda rota de usuário/auth)
- [ ] Impedir conflitos de agendamento
- [x] Implementar tratamento de erros (`ExceptionHandler` central, `DomainException` tipado)
- [x] Padronizar respostas da API (`Response::success/error/paginated`)
- [x] Criar testes unitários (escopo de usuário/auth)
- [x] Criar testes de integração (escopo de usuário/auth; Postgres/Redis reais via docker-compose)

### Dia 4 — Aplicação frontend

- [ ] Criar página de agendamento
- [ ] Implementar detalhes do veículo
- [ ] Implementar seleção de data
- [ ] Implementar carregamento das datas disponíveis
- [ ] Implementar carregamento dos horários disponíveis
- [ ] Implementar seleção de horário
- [ ] Implementar formulário do cliente
- [ ] Integrar frontend com a API
- [ ] Implementar estados de carregamento
- [ ] Implementar tratamento de erros
- [ ] Implementar tela de confirmação
- [ ] Ajustar responsividade

### Dia 5 — Integração e infraestrutura

- [ ] Finalizar funcionalidades pendentes
- [ ] Revisar regras de negócio
- [ ] Revisar API
- [ ] Revisar banco de dados
- [x] Ampliar cobertura de testes (suíte E2E com Playwright: login, registro, reset de senha via Mailpit real, logout, self-upgrade, teclado)
- [x] Configurar análise estática (PHPStan nível 10, PHP-CS-Fixer, Rector no backend; ESLint type-aware + Prettier no frontend)
- [x] Configurar lint e formatação (mesma entrega acima)
- [x] Revisar acessibilidade (WCAG 2.1 AA via axe-core + navegação por teclado -- achou e corrigiu contraste insuficiente no botão "Cadastrar")
- [ ] Revisar experiência do usuário
- [ ] Validar fluxo completo
- [ ] Corrigir problemas encontrados
- [x] Configurar pipeline de CI (GitHub Actions: backend/frontend/phpunit/e2e/load-test/build-and-push)
- [x] Configurar build das imagens
- [x] Configurar publicação das imagens no registry (GHCR)
- [x] Estruturar manifests Kubernetes (`infra/k8s`: backend, frontend, ingressroute, sealed-secret, kustomization)
- [x] Configurar repositório GitOps (monorepo — ArgoCD aponta pro próprio `infra/k8s`, sem repo separado)
- [x] Configurar ArgoCD (Application `autoschedule`, auto-sync + prune + selfHeal, image-updater por digest)
- [x] Automatizar deploy no Kubernetes (merge na `main` → build-and-push → image-updater → ArgoCD sync, sem passo manual)

### Dia 6 — Estabilização e validação

- [x] Validar fluxo completo de CI/CD (confirmado em prod: PR #26 mergeada, pipeline verde, ArgoCD sincronizou sozinho)
- [x] Validar deploy através do ArgoCD (`autoschedule-backend`/`autoschedule-frontend` `1/1 Running` no namespace `apps`, Application Synced/Healthy)
- [ ] Revisar configuração Docker
- [ ] Revisar manifests Kubernetes
- [ ] Revisar configuração do GitOps
- [ ] Executar testes completos
- [ ] Executar análise estática
- [ ] Executar lint e validações de formatação
- [ ] Validar build das imagens
- [ ] Validar aplicação em ambiente publicado
- [ ] Revisar código
- [ ] Corrigir problemas encontrados
- [ ] Revisar performance
- [ ] Revisar segurança
- [ ] Revisar acessibilidade
- [ ] Revisar experiência do usuário

### Dia 7 — Qualidade final + Release

- [ ] Executar testes finais
- [ ] Executar análise estática final
- [ ] Executar lint e validações de formatação
- [ ] Validar build das imagens
- [ ] Validar pipeline completo
- [ ] Validar deploy no Kubernetes
- [ ] Validar fluxo completo em ambiente publicado
- [ ] Fazer revisão final do código
- [ ] Corrigir problemas encontrados na validação final
- [ ] Finalizar documentação do backend
- [ ] Finalizar documentação do frontend
- [ ] Finalizar README
- [ ] Documentar decisões arquiteturais
- [ ] Revisar instruções de execução
- [ ] Criar primeiro release
- [ ] Criar tag da versão
- [ ] Validar aplicação após o release

## Pendente ainda dentro do sprint

- [ ] **Migrar pra SSR** (Next.js/Remix ou equivalente), depois do pipeline (Dia 5-7) -- hoje o frontend é SPA 100% client-rendered (`frontend/src/main.tsx` monta tudo via `createRoot`), então um browser com JavaScript desligado (texto puro, "reader-only", Lynx) não vê conteúdo nenhum, só o `<noscript>` de aviso em `index.html`. SSR resolve isso de verdade -- entrega HTML já renderizado no primeiro request, funcional mesmo sem JS.