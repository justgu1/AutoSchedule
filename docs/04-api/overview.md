# API — visão geral

Prefixo `/api`. Toda resposta usa o mesmo envelope:

```json
{ "status": "success", "data": { } }
{ "status": "error", "message": "...", "errors": { "campo": "motivo" } }
```

Listagem paginada acrescenta `meta` (`page`, `per_page`, `total`, `last_page`) — PG-001.

## Sem OpenAPI separada, de propósito

`GET /api` devolve, em tempo de request, só as rotas que quem chamou (anônimo ou autenticado) pode
de fato usar — descrição, método, campos aceitos. Uma spec estática divergiria da implementação na
primeira rota nova esquecida; a rota já É a própria fonte de verdade (`->describes()`/`->accepts()`
no registro, em `backend/routes/api.php`).

Este diretório documenta só os contratos com regra de negócio real por trás — envelope,
autenticação, e os dois recursos com mais regra (agendamento, veículo). Pra qualquer outra rota,
`GET /api` autenticado como o papel certo é a referência.

## Erros de domínio → HTTP

| Situação | Status |
|---|---|
| Corpo/query inválido (tipo, faixa, campo obrigatório ausente) | `422` |
| Sem papel pra rota (ou recurso de outro dono, tratado como inexistente) | `404` |
| Sem sessão nenhuma numa rota que exige uma | `401` |
| Conflito (e-mail duplicado, reserva concorrente, transição de estado ilegal) | `409` |
| Rate limit excedido | `429`, com `Retry-After` |
| Falha não mapeada | `500`, mensagem genérica fora de `APP_DEBUG` |

Recurso de outro dono responde `404`, nunca `403` — não confirma que o recurso existe pra quem não
pode vê-lo (DL-002).
