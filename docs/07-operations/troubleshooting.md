# Troubleshooting

Armadilhas operacionais reais, já vividas neste projeto — registradas pra não repetir.

## `docker compose up` recria serviço que eu nem citei

Qualquer `docker compose up -d <serviço>` recalcula a config resolvida de **todo** serviço do
arquivo, inclusive dependências não citadas (`depends_on`). Uma variável de ambiente passada só
numa chamada anterior (`RATE_LIMIT_AUTH_MAX=1000 docker compose up -d backend`) não "gruda" —
a próxima chamada sem essa variável recria o container de volta ao valor default do compose.
Sempre reaplicar a variável em **toda** chamada que precisa dela, não só na primeira.

## `make rollback` desfaz o batch inteiro, não uma migration

Chamar duas vezes seguidas cascateia pro batch anterior — pode voltar muito mais longe do que a
intenção. Recuperação segura: `make migrate` (idempotente) + `make seed`.

## Worker/scheduler não recarregam código sozinhos

`backend` (PHP-FPM) valida timestamp do opcache a cada request — editar um `.php` reflete na hora.
`worker`/`scheduler` são processos CLI de vida longa: **não** recarregam sozinhos.
`docker compose restart worker scheduler` depois de qualquer mudança de backend, ou eles seguem
rodando código velho em memória (já causou erro real de coluna inexistente após uma migration).

## E2E roda contra o build de produção do frontend

`make e2e` bate no Nginx servindo o `dist/` compilado, não o dev server com HMR. Depois de
qualquer mudança de frontend: `docker compose build nginx` antes de rodar E2E, senão o teste
exercita a versão antiga.

**`build` sozinho não basta.** Ele só atualiza a imagem — o container `nginx` já rodando continua
de pé com a imagem antiga até ser recriado (`docker compose up -d nginx`). Confirmar sempre com
`docker exec autoschedule-nginx grep -o '<algo do código novo>' /usr/share/nginx/html/assets/*.js`
antes de rodar teste, em vez de assumir que o `build` sozinho já bastou.

## Banco de E2E precisa ser recriado do zero a cada rodada

Ao contrário de `autoschedule_test` (PHPUnit de integração abre transação e dá `rollBack()`, nunca
cresce de verdade), o Playwright commita cada `register`/`create` de fato. `make e2e` usa
`bin/setup_test_database.php autoschedule_e2e --fresh`, que derruba e recria — sem isso, o banco só
cresce a cada rodada, e um dia um teste que depende de paginação (dropdown, listagem) começa a
falhar por acúmulo de linhas velhas, não por bug de verdade (já aconteceu: ~300 vendedores
acumulados esconderam um recém-criado da primeira página do dropdown de dono).

## MUI X `TimePicker`/`DatePicker` não são `<input>` simples

Renderizam como `role="group"` com `spinbutton`s por dentro — `locator.fill()` não funciona.
Clicar no grupo e usar `page.keyboard.type()` com os dígitos (formato 24h com `adapterLocale="pt-br"`,
sem seção AM/PM).

## Rótulo obrigatório do MUI quebra `getByLabel(..., {exact: true})`

O asterisco de campo obrigatório é um nó real no DOM (`aria-hidden`), que ainda entra no nome
acessível calculado pelo Chromium — `exact: true` contra o texto sem asterisco nunca casa. Usar
regex ancorada (`getByLabel(/^Campo/)`) em vez de `exact: true` quando há campo com prefixo igual.

## Bancos de teste/E2E isolados — nunca o de dev

Ver [`05-data/migrations.md`](../05-data/migrations.md). Rodar teste manual contra o banco de dev
polui o catálogo de demonstração com dado sintético (nome com timestamp, preço de fixture) — já
aconteceu, e a correção foi isolar de vez, não limpar manualmente depois de cada rodada.
