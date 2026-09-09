# AutoSchedule — Estilo de código

O que ferramenta não checa. O que ela checa está em `docs/06-testing/strategy.md` (PHPStan, Deptrac,
PHP-CS-Fixer, Rector).

## Comentário

O código diz **o quê**. Comentário existe pra dizer **por quê** — e só quando o porquê não é óbvio.

### Uma linha

Uma linha. Duas só quando a decisão é genuinamente complexa. Se não cabe em duas, o problema não é
o comentário: o código está confuso, e é ele que precisa mudar.

```php
// Ruim -- parágrafo explicando o que o código já mostra:
/**
 * Decodifica o access token (header Bearer ou cookie access_token -- o que vier
 * primeiro) e anexa as claims ao Request. Autenticado, seta current_user_id e
 * current_user_role pro RLS.
 */

// Bom -- só o que não se lê no código:
/** Sem isso o RLS esconde toda linha: em background não existe request pra setar o contexto. */
```

### Uma vez, no dono

Cada decisão é documentada **uma vez**, na classe que a possui. Quem colabora com ela cita a classe
pelo nome, ou não diz nada.

Era o pior problema do projeto: a regra anti-enumeração de login estava escrita em 4 arquivos, a
política de leitura pública em 4, "roda no worker" em 3. Quando o comportamento muda, uma das cópias
passa a mentir — e foi exatamente o que aconteceu com 8 comentários.

### Não nomeia o que apodrece

Comentário **não** nomeia rota (`PATCH /me`), role de banco (`autoschedule_app`), caminho de
arquivo, nem posição em pipeline ("roda antes de qualquer outro middleware"). Foram essas quatro
coisas que ficaram desatualizadas. Nomeia conceito e classe: o compilador, o PHPStan e o Deptrac
mantêm isso honesto; prosa sobre ordem de execução, não.

### Docblock de tipo não é comentário

`@param array<string, mixed>`, `@return list<Foo>`, `@template T` existem porque o PHPStan nível 10
exige e o typehint não expressa. Não contam pra regra da uma linha e não se apagam — se incomodam, o
caminho é **nomear o tipo** (foi o que `Cookie` fez com o array de cookie).

### Checagem

`php tools/check-comments.php` roda no CI e reprova bloco de prosa com mais de 2 linhas, e
comentário que nomeia rota, role de banco ou posição em pipeline.

## Erro de banco: quem traduz o quê

`Statement::execute()` é o único lugar que conhece SQLSTATE. Ele traduz violação de `UNIQUE` em
`DomainException(Conflict)` porque isso não depende de quem chamou: o valor já existe, ponto.

Qualquer outra falha o repositório **descreve**, sem decidir o status: `rotate()` lança
`RefreshTokenAlreadyRotated`, e é `RefreshAccessToken` que resolve que aquilo é 401 — o significado
vem do fluxo, não do banco. Repositório não escolhe `DomainErrorType`.

## Decisões conscientes que parecem esquecimento

Registradas aqui pra ninguém "corrigir" achando que é bug:

- **`trash`/`restore`/`markUsed`/`update`/`delete` não checam `rowCount()`.** Id inexistente
  responde sucesso em vez de 404. O caminho real já carrega a entidade antes (`UserFinder`,
  `DealershipFinder`, `VehicleFinder` dão 404), então o cenário só existe numa corrida — e o custo de fechá-lo
  (exceção nova em 6 métodos, contrato de API mudando) não se paga hoje.
- **`PostgresAuditLogger` engole exceção.** Falha ao auditar não pode derrubar a resposta.
