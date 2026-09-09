# Casos de uso

Um caso de uso por fluxo importante — não todo CRUD, só os que têm regra de negócio real por trás.
Implementação 1:1: cada um corresponde a uma classe em `Application/` com o mesmo nome.

## UC-001 — Criar agendamento

**Ator:** visitante sem conta (customer).

**Pré-condições:**
- veículo existe e está `active`, de concessionária `active`;
- o horário pedido está disponível (AV-001).

**Entrada:** veículo, data, horário, nome, e-mail, telefone.

**Regras:** AG-001, AG-002, AG-003, AG-004, AG-012.

**Resultado:** agendamento `pending` criado; cliente encontrado por e-mail ou criado; horário
reconferido no momento da criação (a lista que gerou a tela pode ter ficado velha).

**Erros:** veículo inexistente (`404`); horário indisponível (`422`); dados inválidos (`422`);
conflito de agendamento concorrente (`409`, AG-012).

## UC-002 — Confirmar agendamento pelo e-mail

**Ator:** o próprio cliente (token, sem login) ou o vendedor dono/admin (sessão, override).

**Pré-condições:** agendamento existe e está `pending`.

**Entrada:** token de confirmação (cliente) ou nada além da sessão (painel).

**Regras:** AG-005, AG-009, AG-011.

**Resultado:** status vira `confirmed`.

**Erros:** token errado ou ausente sem sessão de dono/admin é `404` (mesma resposta de agendamento
inexistente, não vaza que o agendamento existe); estado diferente de `pending` é `409`.

## UC-003 — Ciclo de retirada e devolução do veículo

**Ator:** vendedor dono da concessionária (ou admin), ou a rotina agendada (devolução automática).

**Pré-condições:** agendamento `confirmed`.

**Regras:** AG-006, AG-007, AG-008, AG-013.

**Resultado:** `pickup()` grava `picked_up_at`; `release()` (manual ou automático no prazo) grava
`released_at` e vira `completed`; se ninguém retirou até o prazo, a rotina marca `no_show` em vez
de `release()`. Liberação dispara o e-mail de confirmação do próximo agendamento daquele veículo,
se houver um esperando (handoff + 15min de preparo).

**Erros:** `pickup()` sem estar `confirmed` é `409`; `release()` sem `pickup()` antes é `409`.

## UC-004 — Login por senha

**Ator:** qualquer usuário com conta.

**Entrada:** e-mail, senha.

**Regras:** AU-001, AU-002, AU-003, CV-003.

**Resultado:** conta `trashed` é restaurada automaticamente antes de emitir o token; par
access/refresh token emitido (corpo + cookies `HttpOnly`).

**Erros:** senha errada ou e-mail inexistente respondem a mesma mensagem genérica (AU-003).

## UC-005 — Gerar credencial de API (m2m)

**Ator:** `seller`/`admin` autenticado, a partir do próprio `/me`.

**Regras:** AC-001, AC-004.

**Resultado:** `client_id` + `client_secret` em texto puro, devolvidos **uma única vez** nesta
resposta — nenhuma leitura posterior volta a mostrar o secret.

**Erros:** nenhum além de validação de payload — qualquer conta autenticada pode gerar a própria.

## UC-006 — Autenticar via `client_credentials` (m2m)

**Ator:** sistema externo, de posse de `client_id`/`client_secret`.

**Pré-condições:** client existe, não revogado, dono não `trashed`.

**Regras:** AC-002, AC-003, AU-005.

**Resultado:** `access_token` emitido com `subject`/`role` do **dono** do client, não do client —
RLS e `RoleMiddleware` funcionam exatamente como numa sessão normal do dono, sem código adicional.

**Erros:** secret errado, client revogado, ou dono `trashed` respondem a mesma mensagem genérica
(`Invalid client credentials.`) — não vaza qual dos três é o motivo.

## UC-007 — Upload de foto (concessionária ou galeria de veículo)

**Ator:** `seller` dono ou `admin`.

**Pré-condições:** arquivo presente, dentro do limite de tamanho.

**Regras:** DL-007, DL-008, DL-009, GL-001, GL-002, GL-005.

**Resultado:** endpoint valida o essencial e enfileira, devolvendo `202`+`job_id` na hora; o worker
converte pro padrão do site (WebP, redimensionado) e grava; progresso acompanhável por
`GET /jobs/{id}` (snapshot) ou `GET /jobs/{id}/events` (SSE).

**Erros:** arquivo maior que o limite ou MIME não reconhecido como imagem é rejeitado antes de
enfileirar (`422`); falha durante o processamento vira status `failed` do job, sem exceção subir
pro cliente que enviou o upload.
