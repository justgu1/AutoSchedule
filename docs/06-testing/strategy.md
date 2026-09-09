# Estratégia de testes

## Objetivo

Testes validam arquitetura e regra de negócio, não sintaxe. Um teste que só confirma que o
construtor guardou o valor recebido não protege nada.

## O que testar

- invariantes de arquitetura (imutabilidade, normalização de rota, contratos de erro);
- regras de negócio de [`01-domain/business-rules.md`](../01-domain/business-rules.md);
- casos de borda relevantes ao domínio.

## O que não testar

- getter sem lógica;
- comportamento da linguagem ou do framework;
- detalhe de implementação interna.

## Um comportamento por teste

Um teste deve falhar por um motivo só. Testar criação e depois alteração do mesmo objeto no mesmo
teste impede saber qual das duas quebrou.

```text
objetivo       -> um comportamento por teste
resiliência    -> testa o resultado observável, não a implementação
velocidade     -> roda em milissegundos
legibilidade   -> o nome do teste documenta a falha
confiabilidade -> determinístico, sem flakiness
isolamento     -> independente, roda em qualquer ordem
```

## Convenções

- PHPUnit em `backend/phpunit.xml`, dependência de dev — nunca entra na imagem de produção
  (`--no-dev` no Dockerfile);
- testes em `backend/tests/`, espelhando o namespace de `backend/src/` (`tests/Support/` é a
  exceção: dublê compartilhado entre suítes, como `FakeAuditLogger`);
- nome do teste descreve comportamento, não implementação;
- código em inglês; nome de teste e comentário podem ficar em português;
- `make test` roda a suíte inteira.

## Puro vs. integração

Teste que abre conexão real (Postgres, Redis, MinIO) carrega `#[Group('integration')]` na classe;
o resto é puro e não precisa de nada de pé.

```text
make test        -> tudo (prepara o banco de teste antes)
make test-unit    -> só os puros, sem depender de banco/Redis/MinIO no ar
```

O critério não é a pasta, é a dependência: `ProcessDealershipPhotoTest` mora em
`tests/Application/` e mesmo assim é integração, porque usa `JobStatusStore` contra o Redis de
verdade.

## Bancos isolados

`phpunit.xml` força `DB_DATABASE=autoschedule_test`; `make e2e` usa `autoschedule_e2e`. Nunca o
banco de dev — detalhe completo em [`05-data/migrations.md`](../05-data/migrations.md).

## Testes E2E (Playwright)

`frontend/e2e/` valida fluxo real pelo browser contra o build de produção (nginx, não o dev
server) — login, registro, reset de senha (Mailpit real), logout, self-upgrade, navegação por
teclado, WCAG 2.1 AA (axe-core), e o fluxo de agendamento inteiro. Roda em 2 viewports
(`chromium` desktop + `mobile`, iPhone 13). `make e2e` sobe o ambiente isolado e roda a suíte.

Mailpit é dependência real do próprio stack — mocar não faria sentido. Um serviço de terceiro
genuinamente externo (ViaCEP no formulário de concessionária, Wikimedia no seeder de demo) é
mocado via `page.route()` (E2E) ou tem fallback (seeder): resultado determinístico, sem depender
da rede/disponibilidade de quem não é dono nem opera.

## Testes de carga

`backend/load-tests/` (k6, via Docker) valida performance e rate limiting sob concorrência real —
não substitui a suíte PHPUnit. `make load-test` roda a suíte.

## Qualidade estática

| Ferramenta | Comando | Cobre |
|---|---|---|
| PHPStan (nível 10) | `make static-analysis` | Tipagem estrita do backend; débito pré-existente em `phpstan-baseline.neon`, código novo nunca entra na baseline |
| PHP-CS-Fixer | `make lint` / `make lint-fix` | Formatação do backend |
| Rector | `make rector` / `make rector-fix` | Refatoração automatizada pra PHP moderno, sempre revisada como qualquer diff |
| Deptrac | `make arch` | Regra de dependência entre camadas ([`02-architecture/dependencies.md`](../02-architecture/dependencies.md)), sem baseline de propósito |
| Comentários | `php tools/check-comments.php` (`make comments`) | Regras de [`docs/code-style.md`](../code-style.md) que nenhuma ferramenta pronta cobre |
| ESLint (type-aware) + Prettier | `npm run lint` / `npm run format` | Frontend |
