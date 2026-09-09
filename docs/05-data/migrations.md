# Migrations

Sistema próprio (`Infrastructure/Persistence/Schema/MigrationRunner`), PHP puro, sem dependência de
framework. Cada arquivo em `backend/database/migrations/` devolve uma classe anônima
`up()`/`down()`; nome do arquivo é a ordem de execução (`YYYY_MM_DD_NNNNNN_descricao.php`).

## Comandos

```text
make migrate     -- aplica as pendentes, na ordem
make rollback    -- desfaz o ÚLTIMO BATCH (todas as migrations de uma mesma chamada de `make migrate`)
make seed        -- roda os seeders (idempotentes, sempre re-executam)
```

`make rollback` desfaz o **batch inteiro** da última aplicação, não uma migration por vez —
chamar duas vezes seguidas cascateia pro batch anterior. Ciclo seguro pra testar uma migration
nova: `make migrate && make rollback && make migrate`.

## Convenções

- **RLS entra junto com a tabela**, nunca em migration separada depois — uma tabela sem policy por
  uma janela de deploy é um buraco real, não teórico.
- Toda migration com `ALTER`/`CREATE TABLE` prova o próprio `down()` antes de ir pro PR.
- Enum novo (`CREATE TYPE`) é local à tabela que o usa — reaproveitar o mesmo enum PHP
  (`TrashableStatus`, por exemplo) não significa reaproveitar o `ENUM` do Postgres.
- Coluna dropada que uma coluna gerada dependia (`search_vector`) precisa recriar a gerada contra
  a nova coluna **antes** do `DROP COLUMN` da antiga — não dá pra ter as duas verdades ao mesmo
  tempo mesmo que por uma migration só.

## Bancos irmãos

Três bancos, nunca compartilhados:

| Banco | Uso |
|---|---|
| `autoschedule` | Dev/demo — o que `docker compose up` sobe por padrão. |
| `autoschedule_test` | `make test` — criado por `bin/setup_test_database.php autoschedule_test`, migrado/seedado do zero a cada rodada. |
| `autoschedule_e2e` | `make e2e` — `bin/setup_test_database.php autoschedule_e2e --fresh` **derruba e recria do zero** a cada rodada; `backend`/`worker`/`scheduler` são recriados apontando pra ele durante o teste, e restaurados pro banco de dev no final (mesmo se o Playwright falhar). |

Sessão manual ou E2E direto contra o banco de dev deixaria refresh token e estoque sintético reais
que quebram asserção alheia mais cedo ou mais tarde — isolar os três elimina essa classe de falha
por completo. O seeder de demonstração (`0004_seed_demo_catalog.php`) se recusa a rodar contra
`autoschedule_test`/`autoschedule_e2e` de propósito (nenhum dos dois precisa do catálogo sintético
inteiro).

`autoschedule_e2e` precisa do `--fresh` e `autoschedule_test` não: teste PHPUnit de integração abre
transação e dá `rollBack()` no fim (o banco nunca cresce de verdade), mas o Playwright commita cada
`register`/`create` de verdade, sem rollback nenhum — sem recriar do zero, o banco só cresce a cada
`make e2e`, e cedo ou tarde um teste que depende de paginação (ex: dropdown que só busca a
primeira página de resultados) começa a falhar por acúmulo de dado velho, não por bug.
