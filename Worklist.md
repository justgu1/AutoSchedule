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
- [ ] `client_credentials` (M2M) — adiado, sem consumidor real ainda
- [ ] Security headers + CORS — adiado, sem frontend real chamando a API ainda

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
- [ ] Ampliar cobertura de testes
- [ ] Configurar análise estática
- [ ] Configurar lint e formatação
- [ ] Revisar acessibilidade
- [ ] Revisar experiência do usuário
- [ ] Validar fluxo completo
- [ ] Corrigir problemas encontrados
- [ ] Configurar pipeline de CI
- [ ] Configurar build das imagens
- [ ] Configurar publicação das imagens no registry
- [ ] Estruturar manifests Kubernetes
- [ ] Configurar repositório GitOps
- [ ] Configurar ArgoCD
- [ ] Automatizar deploy no Kubernetes

### Dia 6 — Estabilização e validação

- [ ] Validar fluxo completo de CI/CD
- [ ] Validar deploy através do ArgoCD
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