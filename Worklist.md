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

- [ ] Definir modelo de dados
- [ ] Definir relacionamentos
- [ ] Definir índices e constraints
- [ ] Configurar banco de dados na aplicação
- [ ] Criar migrations
- [ ] Criar entidade de veículo
- [ ] Criar entidade de cliente
- [ ] Criar entidade de agendamento
- [ ] Implementar persistência
- [ ] Criar dados iniciais para desenvolvimento
- [ ] Implementar regras de disponibilidade
- [ ] Validar modelo e persistência

### Dia 3 — API e regras de negócio

- [ ] Definir contratos da API
- [ ] Implementar API REST
- [ ] Implementar endpoint de detalhes do veículo
- [ ] Implementar endpoint de datas disponíveis
- [ ] Implementar endpoint de horários disponíveis
- [ ] Implementar endpoint de criação de agendamento
- [ ] Implementar validações
- [ ] Impedir conflitos de agendamento
- [ ] Implementar tratamento de erros
- [ ] Padronizar respostas da API
- [ ] Criar testes unitários
- [ ] Criar testes de integração

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