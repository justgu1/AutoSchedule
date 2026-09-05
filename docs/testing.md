# AutoSchedule — Testes

## Objetivo

Testes validam arquitetura e regras de negócio, não sintaxe.

Um teste que só confirma que o construtor guardou o valor recebido não protege nada.

## O que testar

- invariantes de arquitetura (imutabilidade, normalização de rota, contratos de erro);
- regras de negócio definidas em `docs/business-rules.md`;
- casos de borda relevantes ao domínio.

## O que não testar

- getter sem lógica;
- comportamento da linguagem ou do framework;
- detalhe de implementação interna.

## Um comportamento por teste

Um teste deve falhar por um motivo só.

Testar criação e depois alteração do mesmo objeto no mesmo teste impede saber qual das duas quebrou.

## Características de um bom teste

```text
objetivo       -> um comportamento por teste
resiliência    -> testa o resultado observável, não a implementação
velocidade     -> roda em milissegundos
legibilidade   -> o nome do teste documenta a falha
confiabilidade -> determinístico, sem flakiness
isolamento     -> independente, roda em qualquer ordem
```

## Convenções

- PHPUnit configurado em `backend/phpunit.xml`, dependência de dev (`backend/composer.json`) — nunca entra na imagem de produção (`--no-dev` no `Dockerfile`);
- testes em `backend/tests/`, espelhando o namespace de `backend/src/`;
- nome do teste descreve comportamento, não implementação;
- código em inglês; nomes de teste e comentários podem ficar em português;
- `make test` roda a suíte.

## Testes de carga

`backend/load-tests/` (k6, via Docker) valida performance e o comportamento do rate limiting sob concorrência real — não substitui a suíte PHPUnit, que valida regra de negócio. `make load-test` roda a suíte; detalhes de cada cenário em `backend/load-tests/README.md`.

## Testes E2E (Playwright)

`frontend/e2e/` valida os fluxos reais pelo browser contra o build de produção (nginx, não o dev server) -- login, registro, reset de senha (Mailpit real, não mock), logout, self-upgrade pra seller, navegação por teclado e WCAG 2.1 AA (axe-core). Roda em 2 viewports (desktop + mobile). `make e2e` sobe o ambiente e roda a suíte via Docker; detalhes do rate limit relaxado durante o teste em `Makefile`.

## Qualidade estática

- **PHPStan** (nível 10, backend): `make static-analysis`. Débito pré-existente fica em `backend/phpstan-baseline.neon` -- código novo não entra nessa baseline, só o nível 10 direto.
- **PHP-CS-Fixer** (backend): `make lint` (checa), `make lint-fix` (aplica).
- **Rector** (backend): `make rector` (checa), `make rector-fix` (aplica) -- refatoração automatizada pra PHP moderno, sempre revisada como qualquer outro diff antes de commitar.
- **ESLint** (`typescript-eslint`, type-aware) + **Prettier** (frontend): `npm run lint`, `npm run format`/`format:fix`.

## Catálogo regra → teste

`docs/test-catalog.md` mapeia cada regra de `docs/business-rules.md` pros testes que a validam -- dá corpo rastreável ao princípio acima, em vez de só o princípio declarado.
