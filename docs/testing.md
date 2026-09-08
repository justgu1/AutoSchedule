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
- testes em `backend/tests/`, espelhando o namespace de `backend/src/` (`tests/Support/` é a exceção: dublê compartilhado entre suítes, como `FakeAuditLogger`);
- nome do teste descreve comportamento, não implementação;
- código em inglês; nomes de teste e comentários podem ficar em português;
- `make test` roda a suíte inteira.

### Puro vs. integração

Teste que abre conexão real (Postgres, Redis, MinIO) carrega `#[Group('integration')]` na classe; o resto é puro e não precisa de nada de pé. A suíte padrão continua rodando tudo -- é o que o CI usa --, mas dá pra rodar só metade:

```text
make test        -> tudo (prepara o banco de teste antes)
make test-unit   -> só os puros, sem depender de banco/Redis/MinIO no ar
```

O critério não é a pasta, é a dependência: `ProcessDealershipPhotoJobTest` mora em `tests/Application/` e mesmo assim é integração, porque usa `JobStatusStore` contra o Redis de verdade.

Nunca contra o banco de dev, nem local: `phpunit.xml` força `DB_DATABASE=autoschedule_test`, um banco irmão no mesmo servidor Postgres (`bin/setup_test_database.php` cria da primeira vez, `make test` migra/seeda de novo a cada rodada -- idempotente). Sessão manual/E2E contra o banco de dev deixa `oauth_refresh_tokens` reais que quebram teste algum dia (ex: `SeederRunnerTest` resetando o admin seedado) -- isolar o banco elimina essa classe de falha por completo, sem precisar resetar nada na mão. CI já era isolado por natureza (Postgres efêmero via `services:`), só o nome do banco (`autoschedule_test`) foi alinhado por consistência.

## Testes de carga

`backend/load-tests/` (k6, via Docker) valida performance e o comportamento do rate limiting sob concorrência real — não substitui a suíte PHPUnit, que valida regra de negócio. `make load-test` roda a suíte; detalhes de cada cenário em `backend/load-tests/README.md`.

## Testes E2E (Playwright)

`frontend/e2e/` valida os fluxos reais pelo browser contra o build de produção (nginx, não o dev server) -- login, registro, reset de senha (Mailpit real, não mock), logout, self-upgrade pra seller, navegação por teclado e WCAG 2.1 AA (axe-core). Roda em 2 viewports (desktop + mobile). `make e2e` sobe o ambiente e roda a suíte via Docker; detalhes do rate limit relaxado durante o teste em `Makefile`.

Mailpit é dependência real do próprio stack (container do compose) -- mocar não faria sentido. Já um serviço de terceiro genuinamente externo (ex: ViaCEP no formulário de concessionária) é mocado via `page.route()`: resultado determinístico, sem depender da rede/disponibilidade de quem não é dono nem opera.

## Qualidade estática

- **PHPStan** (nível 10, backend): `make static-analysis`. Débito pré-existente fica em `backend/phpstan-baseline.neon` -- código novo não entra nessa baseline, só o nível 10 direto.
- **PHP-CS-Fixer** (backend): `make lint` (checa), `make lint-fix` (aplica).
- **Rector** (backend): `make rector` (checa), `make rector-fix` (aplica) -- refatoração automatizada pra PHP moderno, sempre revisada como qualquer outro diff antes de commitar.
- **Comentários** (backend): `php tools/check-comments.php`. Checa as regras de `docs/code-style.md` que nenhuma ferramenta pronta cobre.
- **Deptrac** (backend): `make arch`. Checa a regra de dependência entre as camadas (`backend/deptrac.yaml`, ver `docs/architecture.md`). Sem baseline de propósito -- entrou com o código já passando, então qualquer violação é nova.
- **ESLint** (`typescript-eslint`, type-aware) + **Prettier** (frontend): `npm run lint`, `npm run format`/`format:fix`.

## Catálogo regra → teste

`docs/test-catalog.md` mapeia cada regra de `docs/business-rules.md` pros testes que a validam -- dá corpo rastreável ao princípio acima, em vez de só o princípio declarado.
