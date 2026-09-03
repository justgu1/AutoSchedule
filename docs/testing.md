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
