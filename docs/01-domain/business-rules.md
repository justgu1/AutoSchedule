# Regras de negócio

Uma regra por linha, numerada e testável. Mapeamento regra → teste em
[`06-testing/business-rules.md`](../06-testing/business-rules.md); invariantes (sempre verdadeiras,
não uma ação permitida/proibida) em [`invariants.md`](invariants.md); ciclo de vida completo em
[`state-transitions.md`](state-transitions.md).

## Usuários (US)

| ID | Regra |
|---|---|
| US-001 | Existem três papéis: `admin`, `seller`, `customer`. |
| US-002 | Self-service (`PATCH /me`) só permite `customer` virando `seller` — qualquer outra transição de role é `403`. |
| US-003 | CRUD admin (`PATCH /users/{id}`) aceita qualquer transição de role, respeitando a trava do último admin (US-004 em [`invariants.md`](invariants.md)). |

## Concessionária (DL)

| ID | Regra |
|---|---|
| DL-001 | Toda concessionária pertence a exatamente um seller (`owner_user_id`); um seller pode ter mais de uma. |
| DL-002 | Seller gerencia só as próprias concessionárias — concessionária alheia responde `404`, nunca `403` (não revela que existe). |
| DL-003 | Admin gerencia qualquer concessionária, inclusive reassocia o dono, pelo mesmo `PATCH` que edita o resto. |
| DL-004 | Ciclo de vida em 3 estados (`active`/`trashed`/`deleted`), reversível por 30 dias. |
| DL-005 | Lixeira em cascata (conta do dono desativada) restaura automaticamente ao logar de novo; lixeira manual (o próprio seller apagou) só restaura por ação explícita. |
| DL-006 | Anonimização apaga o que localiza a porta (rua/número/complemento) e o que identifica direto (nome/telefone/e-mail/slug), preserva CEP/cidade/UF. |
| DL-007 | Foto é única (não galeria); trocar substitui e remove a anterior do storage. |
| DL-008 | Upload de foto processado fora do request (job assíncrono) — progresso acompanhável por `GET /jobs/{id}` ou SSE. |
| DL-009 | Upload de foto: até 20MB, MIME validado pelo conteúdo real, não pela extensão. |
| DL-010 | CEP autopreenche endereço/bairro/cidade/UF via `GET /zip-codes/{cep}` (proxy cacheado do ViaCEP) — o front nunca chama o terceiro direto. |
| DL-011 | `GET /dealerships/{id}` é a mesma rota do gerenciamento: perfil completo pro dono/admin, perfil público (nome/endereço/contato/foto/nome do vendedor, nenhum outro dado dele) pros demais, só se `active`. |
| DL-012 | Perfil público traz até 12 veículos ativos (vitrine) e o total real separado (`vehicles_total`). |
| DL-013 | URL pública usa `slug` (nome + parte do id), nunca o `id` — estável até a anonimização. |

## Veículos (VH)

| ID | Regra |
|---|---|
| VH-001 | Veículo pertence a uma única concessionária; o dono é sempre transitivo (`owner_user_id` da concessionária), nunca copiado pro veículo. |
| VH-002 | Status é só a lixeira de 3 estados — "vendido" e "agendado" nunca são estado guardado (calculado na leitura ou simplesmente não rastreado). |
| VH-003 | Lixeira em cascata (concessionária ou conta do dono foi pra lixeira) só restaura automaticamente quem caiu por causa dela. |
| VH-004 | Seller gerencia veículos das próprias concessionárias; admin, qualquer um. |
| VH-005 | Mover de concessionária é o mesmo `PATCH` que edita o resto (campo `dealership_id`); quem move precisa alcançar as duas pontas. |
| VH-006 | Preço trafega como string decimal, nunca número — número em JSON vira ponto flutuante e perde o centavo que `numeric(12,2)` protege. |
| VH-007 | `GET /vehicles`/`GET /vehicles/{id}` são públicas por padrão; `?scope=mine` exige sessão `admin`/`seller` e devolve o estoque completo do dono. |

## Especificações e itens de veículo (AM)

| ID | Regra |
|---|---|
| AM-001 | Câmbio, carroceria, combustível, cor, quilometragem, final de placa, aceita-troca, IPVA e licenciamento são campos planos do próprio veículo, todos opcionais. |
| AM-002 | Itens de veículo (amenities) são um catálogo global fora do agregado `Vehicle` — curado só por seed, a API nunca cria item novo. |
| AM-003 | `amenity_ids` fora do catálogo é `422` (validação), nunca erro de FK (`500`). |

## Galeria (GL)

| ID | Regra |
|---|---|
| GL-001 | Até 20 imagens por veículo, enviadas em lotes de até 10, 20MB cada. |
| GL-002 | Ordem definida por `position`; a capa é sempre a posição `0`, nunca uma coluna de destaque à parte. |
| GL-003 | Reordenar exige a lista completa de ids na ordem desejada — ordem parcial é recusada. |
| GL-004 | Arquivo compartilhado por dedupe de checksum só sai do storage quando nenhuma galeria/concessionária mais o referencia. |
| GL-005 | Upload processado fora do request, uma transação por foto — uma imagem ruim no lote não desfaz as que já entraram. |

## Busca (SR)

| ID | Regra |
|---|---|
| SR-001 | Listar e buscar são a mesma consulta (`GET /vehicles`) — sem filtro, degenera na listagem inteira. |
| SR-002 | Marca/modelo passam pelo índice de texto, não igualdade — alcançam quem só cita a marca na descrição, mas o campo próprio ranqueia acima do texto livre. |
| SR-003 | Erro de digitação, palavra parcial e prefixo curto (1+ caractere, em qualquer campo indexado) são tolerados — FTS, prefixo e trigram se somam, nunca se substituem. |
| SR-004 | Filtros (marca, modelo, ano, preço, câmbio, carroceria, combustível, km máximo, concessionária) combinam por AND. |
| SR-005 | `sort` (preço/ano/criação, asc/desc) substitui a ordenação por relevância quando informado. |
| SR-006 | `GET /vehicles/filters` devolve as facetas do estoque relevante — catálogo público sem `scope`, só o próprio estoque com `scope=mine`. |

## Disponibilidade (AV)

| ID | Regra |
|---|---|
| AV-001 | Horário disponível = janela da concessionária ∩ janela do veículo ∩ sem bloqueio de exceção ∩ sem agendamento ativo naquele slot. |
| AV-002 | Exceção pontual de uma data tem prioridade sobre a regra recorrente da mesma data. |
| AV-003 | Escopo de uma exceção é concessionária OU veículo — nunca os dois, nunca nenhum. |
| AV-004 | Intervalo de disponibilidade usa a convenção `[start, end)`. |
| AV-005 | Veículo sem regra recorrente e sem exceção pontual não restringe nada — vale só a janela da concessionária. |
| AV-006 | Concessionária sem regra recorrente e sem exceção usa o default segunda a sexta, 09:00–18:00. |
| AV-007 | Uma exceção pontual conta como "configurado" mesmo sem regra recorrente — nunca vira "sem restrição" só por faltar regra. |

## Agendamento (AG)

| ID | Regra |
|---|---|
| AG-001 | Duração fixa de 60 minutos — não é campo que o cliente escolhe. |
| AG-002 | Um veículo não pode ter dois agendamentos ativos (`pending`/`confirmed`) no mesmo horário. |
| AG-003 | Cliente é achado por e-mail ou criado — nunca sobrescreve o perfil de uma conta já existente. |
| AG-004 | Nome/e-mail/telefone do agendamento são uma cópia própria, independente do perfil da conta associada. |
| AG-005 | `confirmed`/`cancelled` só a partir de `pending` — confirmado não tem caminho de volta pro cancelamento. |
| AG-006 | `completed` só nasce da liberação do veículo (`release`), que só existe depois da retirada (`pickup`). |
| AG-007 | `no_show` só nasce de rotina automática, quando o prazo de devolução passa sem que o veículo tenha sido retirado. |
| AG-008 | Prazo de devolução é sempre `scheduled_at + 60min`, nunca a hora real da retirada (chegar atrasado pra retirar não estica o prazo). |
| AG-009 | O token de confirmação nasce só quando o e-mail de fato sai (`markConfirmationSent`), nunca na criação do agendamento. |
| AG-010 | `pending` só ganha prazo de expiração a partir do envio do e-mail de confirmação, não da criação. |
| AG-011 | Cliente confirma/cancela clicando no e-mail (token, sem login); o painel tem as mesmas ações como override de funcionário, mesmo endpoint. |
| AG-012 | Reserva concorrente do mesmo veículo/horário vira `409` — reconferido na aplicação antes do insert, índice único do banco é o backstop final. |
| AG-013 | O e-mail de confirmação espera o veículo ficar livre (handoff do agendamento anterior + 15min de preparo), ou sai na hora se nada bloqueia. |

## Credenciais de API — m2m (AC)

| ID | Regra |
|---|---|
| AC-001 | Client m2m nasce com secret aleatório mostrado uma vez só — só o hash é persistido. |
| AC-002 | `client_credentials` de um client com dono autentica como o próprio dono (`subject`/`role` do JWT viram os do dono, não do client). |
| AC-003 | Client revogado ou dono `trashed` nega o login, mesmo com secret correto. |
| AC-004 | CRUD de credenciais (gerar/listar/rotacionar/revogar) é escopado ao próprio dono — nunca alcança client alheio. |

## Autenticação (AU)

| ID | Regra |
|---|---|
| AU-001 | Senha em hash Argon2id, nunca persistida ou logada em texto puro. |
| AU-002 | `POST /oauth/token` é único, sem `grant_type` no corpo — o formato do corpo decide (email+senha, refresh_token, id_token, client_id+client_secret). |
| AU-003 | Senha errada e e-mail inexistente respondem a mesma mensagem genérica (não vaza qual conta existe). |
| AU-004 | Refresh token é de uso único; reuso de um token já rotacionado revoga a família inteira. |
| AU-005 | `client_credentials` exige client confidencial com esse grant habilitado. |
| AU-006 | Login social (Google) linka conta existente por e-mail sem mudar role, ou cria `customer` novo — e-mail não verificado é rejeitado. |
| AU-007 | Tokens trafegam em cookie `HttpOnly`/`SameSite=Strict`, além do corpo da resposta. |
| AU-008 | Mutação autenticada por cookie exige CSRF double-submit; `Authorization: Bearer` explícito pula essa checagem. |
| AU-009 | Registro público só aceita role `seller`/`customer` — nunca `admin`. |
| AU-010 | Logout revoga a família do refresh token e limpa os cookies. |
| AU-011 | `POST /password-reset` sempre responde `200` — nunca revela se a conta existe. |
| AU-012 | Access token é JWT RS256 com `alg` fixo — não aceita troca pra HS256. |

## Autorização (AZ)

| ID | Regra |
|---|---|
| AZ-001 | Autorização é aplicada no backend, papel vem da rota — validação de frontend nunca é mecanismo de segurança. |
| AZ-002 | RLS reforça a mesma regra de acesso no banco (admin global, seller escopado ao que é dono, customer só o próprio) — nunca é a única linha de defesa. |

## Auditoria (AD)

| ID | Regra |
|---|---|
| AD-001 | Todo evento grava `actor_id`/`target_user_id` separados, contexto, IP e user agent. |
| AD-002 | Falha ao gravar auditoria não derruba a request que a originou. |
| AD-003 | `auth.service_token.issued` de um client sem dono não tem actor/target — quem agiu foi o client, não um usuário. |

## Rate limiting (RL)

| ID | Regra |
|---|---|
| RL-001 | Sliding window, por usuário autenticado ou por IP; `429` sempre com `Retry-After`. |
| RL-002 | Falha do Redis é fail-open — rate limit fica temporariamente inativo, nunca bloqueia todo o tráfego. |
| RL-003 | A política `auth` cobre login/registro/reset/agendamento — não só `/oauth/token`. |

## Paginação (PG)

| ID | Regra |
|---|---|
| PG-001 | `page`/`per_page` com default e teto configuráveis, nunca abaixo de 1. |

## Notificações (NT)

| ID | Regra |
|---|---|
| NT-001 | E-mail é sempre assíncrono — enfileirado, nunca enviado na hora do request. |
| NT-002 | Falha no envio do e-mail não impede a criação do agendamento nem a transição de status. |

## Scheduler e worker (SC)

| ID | Regra |
|---|---|
| SC-001 | Tarefa periódica só roda de novo depois do próprio intervalo passar; o "último run" sobrevive restart (Redis, não memória do processo). |
| SC-002 | Falha de job reenfileira com `attempts` incrementado; passadas 3 tentativas vira dead-letter. |

## Ciclo de vida da conta — lixeira (CV)

| ID | Regra |
|---|---|
| CV-001 | `DELETE /me` move pra `trashed` (não anonimiza na hora) e revoga todo refresh token — ninguém continua logado depois. |
| CV-002 | Conta `trashed` ainda bloqueia reuso do e-mail, até a purge rodar. |
| CV-003 | Login com sucesso restaura a conta `trashed` automaticamente, antes de emitir qualquer token. |
| CV-004 | Restore só funciona antes da anonimização definitiva. |
| CV-005 | Purge anonimiza PII e marca `deleted` — nunca hard-delete, nunca antes dos 30 dias sem ação explícita. |
| CV-006 | Elegibilidade de purge exige `trashed` há mais de 30 dias e ainda não anonimizado. |
| CV-007 | A trava do último admin só considera admin `active` — um `trashed` já não protege ninguém. |
