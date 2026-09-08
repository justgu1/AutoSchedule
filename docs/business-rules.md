# AutoSchedule — Regras de Negócio

## Usuários

Existem três tipos de usuário:

- `admin`
- `seller`
- `customer`

### Admin

Possui acesso global à aplicação.

### Seller

É dono de uma ou mais concessionárias (`owner_user_id`, ver [Concessionária](#concessionária)). Seu acesso é limitado às concessionárias das quais é dono.

### Customer

Não precisa estar associado a uma concessionária. É identificado por nome, e-mail e telefone durante o agendamento.

## Concessionária

Toda concessionária pertence a exatamente um seller (`owner_user_id`, `NOT NULL`) -- sem tabela de associação, sem concessionária compartilhada entre sellers. Um mesmo seller pode ser dono de mais de uma concessionária.

- `seller`: cria concessionária (torna-se dono automaticamente), gerencia só as próprias -- `PATCH`/`DELETE`/etc numa concessionária que não é sua (RLS já escopa isso) respondem o mesmo `404` de "não existe", de propósito. Ler uma concessionária de outro seller (`GET /dealerships/{id}`) não vaza mais que qualquer visitante sem conta veria (ver "Página pública" abaixo);
- `admin`: gerencia qualquer concessionária, inclusive reassocia o dono (`owner_user_id` no corpo de `PATCH`, mesma rota de update -- sem endpoint paralelo só pra isso).

### Ciclo de vida (lixeira)

Mesmo modelo de três estados da conta de usuário (`active`/`trashed`/`deleted`), reaproveitado -- ver [Ciclo de vida da conta](#ciclo-de-vida-da-conta-lixeira) acima pro fluxo genérico. Duas entradas na lixeira:

```text
DELETE /dealerships/{id}                    -> lixeira manual (o dono decidiu remover)
conta do dono desativada (DELETE /me)       -> lixeira em cascata, trashed_by_owner_deactivation = true
```

Só a lixeira em cascata é restaurada automaticamente quando o dono volta a logar -- a manual fica parada até o próprio seller (ou um admin) chamar `POST /dealerships/{id}/restore`. `POST /dealerships/{id}/purge` anonimiza na hora, sem esperar os 30 dias; a rotina agendada faz o mesmo pra quem não foi recuperado a tempo.

Anonimização escruba o que localiza a porta (rua, número, complemento) e o que identifica direto (nome vira "Concessionária removida", telefone e e-mail apagados, `slug` trocado por um neutro), mas preserva CEP, cidade e UF -- não são dado pessoal, e mantêm o histórico de agendamento localizável no agregado. A regra mora em `Address::withoutStreetLevelDetail()`.

### Foto

Uma só, não galeria -- `POST /dealerships/{id}/photo` substitui a anterior (que é apagada do storage, não fica órfã); `DELETE` remove. Até 20MB por upload; validada por MIME real, não só a extensão.

Processada fora do request: o endpoint só valida o essencial (arquivo presente, tamanho) e enfileira, devolvendo `202` com um `job_id` na hora -- quem chamou acompanha o resultado por `GET /jobs/{id}` (snapshot) ou `GET /jobs/{id}/events` (SSE, evento por mudança de status: `queued` → `processing` → `done`/`failed`). O worker é quem converte pro padrão do site (WebP, redimensionada a até 1600px no lado maior) antes de gravar -- o mesmo mecanismo que o import em lote da galeria de veículo usa (ver [Galeria](#galeria)).

### Endereço e contato

Além do endereço, a concessionária tem telefone e e-mail próprios (contato do negócio -- não é o telefone/e-mail da conta do seller). CEP autopreenche endereço, bairro, cidade e UF no formulário -- o backend proxeia o ViaCEP (`GET /zip-codes/{cep}`) e guarda o resultado num cache próprio (`zip_code_cache`, sem expiração -- CEP não muda de endereço), então o mesmo CEP nunca depende do ViaCEP de novo depois da primeira vez. Quem preenche pode digitar por cima depois, não é campo travado.

### Página pública

`GET /dealerships/{id}` é a mesma rota que o gerenciamento usa -- o formato da resposta muda pra quem chama, não a URL: dono/admin recebem o perfil completo, qualquer outro caso (outro seller, customer, sem conta nenhuma) recebe um perfil enxuto (nome, endereço, telefone/e-mail da concessionária, foto, só o **nome** do vendedor -- nenhum outro dado dele) e só se a concessionária estiver `active` (trashed/deleted viram `404`, igual concessionária inexistente, de propósito). O perfil público também traz a vitrine: até 12 veículos ativos da concessionária (`vehicles`) e o total real de estoque visível (`vehicles_total`), separado -- é leitura de recurso único, não listagem paginada, então não aceita `page`/`per_page`.

A URL pública (`/concessionarias/{slug}` no front) usa um `slug` gerado a partir do nome + parte do id, nunca o `id` em si -- estável mesmo se o nome mudar depois, só é trocado por um neutro na anonimização. O mapa (Google Maps Embed, só exibição por string de endereço) usa o mesmo endereço já salvo.

## Veículos

Todo veículo pertence a uma única concessionária. Quem é o dono é sempre transitivo -- vem do `owner_user_id` da concessionária, nunca de uma cópia no próprio veículo, pra não divergir quando o admin reassocia a concessionária a outro seller.

Estados:

- `active`
- `trashed`
- `deleted`

É a mesma lixeira reversível da conta e da concessionária, com a mesma janela de 30 dias. Como veículo não tem dado pessoal, a anonimização do purge é só o estado terminal: marca `deleted` e preserva marca e modelo, que o histórico de agendamento ainda precisa exibir.

Não existe estado "vendido": o sistema não tem como saber que a venda aconteceu, e um estado que ninguém alimenta mente pra quem olha. "Agendado" também não é estado guardado -- é o veículo ter visita ativa em `appointments`, calculado na leitura. Guardar isso obrigaria todo cancelamento e toda expiração a lembrar de desfazer, e esquecer um deixaria o veículo inagendável em silêncio. Veículo com visita marcada não recebe outra, mas continua aparecendo na vitrine: esconder estoque faria a concessionária parecer vazia.

Concessionária que vai pra lixeira arrasta o estoque junto, e restaurá-la devolve só o que caiu por cascata -- veículo que o seller tinha apagado sozinho continua na lixeira. Desativar a conta do seller arrasta os dois níveis de uma vez.

Seller gerencia os veículos das próprias concessionárias; admin gerencia qualquer um.

Um veículo pode ser movido de concessionária pelo mesmo `PATCH` que edita o resto, mandando `dealership_id`. Quem move precisa alcançar as duas pontas: o seller só enxerga as próprias, então mover pro estoque alheio é `404` (a mesma resposta de concessionária inexistente); pro admin a restrição não se aplica, ele move pra qualquer uma. A movimentação gera `vehicle.dealership_reassigned` além do `vehicle.updated`, como a reassociação de dono da concessionária.

Preço é enviado e devolvido como string decimal, porque número em JSON vira ponto flutuante no cliente e perde o centavo que `numeric(12,2)` protege no banco.

### Catálogo público

`GET /vehicles` e `GET /vehicles/{id}` são as mesmas rotas que o painel usa, públicas -- sem conta, sem role. Por padrão devolvem o catálogo: todo veículo `active` de concessionária `active`, num formato enxuto (sem `dealership_id`/`status`, sem os dados de gestão). Trashed/deleted, ou de concessionária na lixeira, viram ausência do resultado -- não erro, só não aparece.

`?scope=mine` muda o comportamento pra "meu estoque": exige sessão de `admin`/`seller` (`401`/`403` senão) e devolve o perfil completo de gestão, escopado ao próprio dono (RLS reforça). É o painel chamando o mesmo endpoint com um parâmetro a mais, não uma rota paralela pra ação equivalente.

`GET /vehicles/{id}` segue o mesmo padrão de `GET /dealerships/{id}`: dono/admin recebem o perfil completo; qualquer outro recebe o perfil público, com a galeria e a concessionária aninhada (`slug`, nome, cidade, UF) pra linkar de volta.

## Galeria

Um veículo pode possuir várias imagens, no máximo 20, enviadas em lotes de até 10 por vez, 20MB cada.

As imagens são armazenadas no MinIO e referenciadas por `files`, o mesmo metadado de upload que a foto da concessionária usa. Como o arquivo é endereçado pelo checksum do conteúdo, dois anúncios com a mesma foto compartilham a linha, e remover uma imagem só apaga o objeto quando ninguém mais aponta pra ele.

A ordem é definida por `position`, sendo `0` a primeira imagem apresentada -- a capa é a posição 0, não uma coluna de destaque, que seria uma segunda verdade a sincronizar. Reordenar manda a lista completa de ids na ordem desejada: ordem parcial é recusada, porque N atualizações independentes convergem pra um estado que ninguém pediu sem dar sinal.

Otimizar e gravar sai do request, como na foto da concessionária -- o lote inteiro tem um `job_id` só, acompanhado pelo mesmo SSE. Uma transação por foto: uma imagem ruim no meio do lote não desfaz as que já entraram, e o erro vira status `failed` em vez de exceção.

## Busca

Listar e buscar veículo são a mesma requisição: `GET /vehicles` com filtros opcionais na query string, todos combináveis. Sem filtro nenhum, é a listagem de sempre.

| Filtro | Como recorta |
|---|---|
| `q` | texto livre sobre marca, modelo, versão, ano e descrição |
| `brand`, `model` | índice de texto, não igualdade |
| `year_min`, `year_max` | faixa numérica |
| `price_min`, `price_max` | faixa numérica |
| `dealership_id` | igualdade |

Marca e modelo passarem pelo índice de texto é a razão de a busca ser configurada no banco: **filtrar por "Chevrolet" encontra também o anúncio que só escreveu a marca na descrição**. E filtrar não é só recortar -- o campo próprio pesa mais que o texto livre, então quem é da marca aparece antes de quem só a cita.

A busca também tolera erro de digitação e palavra parcial (`corola` acha `Corolla`), o que a FTS sozinha não faz -- é o `pg_trgm` que cobre isso. Prefixo curto (1+ caractere) de qualquer palavra, em qualquer campo (inclusive `description`), também é encontrado, e substring no meio de uma palavra (3+ caracteres) também -- as três técnicas (FTS exata, prefixo, similaridade/substring) se somam, nunca se substituem.

`sort` ordena explicitamente o resultado (`price_desc`, `price_asc`, `year_desc`, `created_desc`, `created_asc`), substituindo a ordenação por relevância quando presente. Sugestão de digitação progressiva (autocomplete) não tem endpoint próprio -- é o mesmo `GET /vehicles?q=&per_page=5`, só com uma página pequena.

`GET /vehicles/filters` devolve as marcas, modelos e anos que existem no estoque relevante, pra tela não oferecer combinação que não devolve nada -- sem `scope`, é o catálogo público inteiro; com `scope=mine`, só o estoque de quem chama (admin vê tudo, seller só o próprio).

## Disponibilidade

Um horário somente está disponível quando:

```text
concessionária disponível
        AND
veículo disponível
        AND
horário dentro da disponibilidade do veículo
        AND
horário não bloqueado por exceção
        AND
não existe agendamento ativo no horário
```

Os horários disponíveis são definidos por data. Ao selecionar uma data, somente os horários válidos para aquele dia devem ser apresentados. Calculado por `Domain/Availability/AvailabilityCalculator`, puro, sem consulta ao banco -- quem busca as janelas, exceções e agendamentos é a Application.

Regras recorrentes (`seller`/`admin`, uma por concessionária ou por veículo, por dia da semana):

```text
GET/POST      /dealerships/{id}/availability-rules
PATCH/DELETE  /availability-rules/{id}
GET/POST      /vehicles/{id}/availability-rules
PATCH/DELETE  /vehicle-availability-rules/{id}
```

Consulta pública, sem conta (é o que a tela de agendamento usa):

```text
GET /vehicles/{id}/availability/dates?month=YYYY-MM   -- default: mês corrente
GET /vehicles/{id}/availability/slots?date=YYYY-MM-DD
```

### Exemplo

Se a concessionária estiver disponível das 09:00 às 18:00 e o veículo das 10:00 às 15:00, com duração de 60 minutos, os horários possíveis são:

```text
10:00
11:00
12:00
13:00
14:00
```

O intervalo utiliza a convenção `[start, end)`.

Se o **veículo** não tem nenhuma regra recorrente cadastrada (e nenhuma exceção pra aquela data), ele não restringe nada -- vale só a janela da concessionária. Se a **concessionária** não tem nenhuma regra cadastrada (e nenhuma exceção pra aquela data), ela usa o default segunda a sexta, 09:00 às 18:00. Uma exceção pontual, mesmo sem regra recorrente nenhuma, já conta como "configurado" -- continua bloqueando/abrindo normalmente, nenhum dos dois defaults se aplica.

## Exceções

Uma exceção pode alterar ou bloquear a disponibilidade de uma concessionária ou veículo em uma data específica.

Exemplos:

- feriado;
- manutenção;
- veículo indisponível;
- horário especial;
- fechamento excepcional.

A exceção específica da data possui prioridade sobre a regra recorrente: `is_available=false` sem horário bloqueia o dia inteiro; com horário, subtrai só aquele intervalo; `is_available=true` substitui a janela do dia inteiro (exige horário próprio). Escopo é concessionária **ou** veículo, nunca os dois nem nenhum.

```text
GET/POST      /availability-exceptions?dealership_id=|vehicle_id=
PATCH/DELETE  /availability-exceptions/{id}
```

## Agendamento

Fluxo do cliente, sem conta:

```text
visualizar veículo
        ↓
consultar datas disponíveis
        ↓
selecionar data
        ↓
consultar horários disponíveis
        ↓
selecionar horário
        ↓
informar nome, e-mail e telefone
        ↓
confirmar
        ↓
criar agendamento (POST /appointments, público)
```

A duração é sempre 60 minutos -- não é campo que o cliente escolhe. A aplicação reconfere o horário no momento da criação (a checagem que gerou a lista pode ter ficado velha); `POST /appointments` acha o `customer` por e-mail ou cria um novo (mesmo mecanismo do login social -- sem tocar no perfil se a conta já existe). Nome/e-mail/telefone ficam guardados no próprio agendamento como uma cópia do que foi digitado, independente do que já existe em `users`: uma nova solicitação nunca sobrescreve o perfil da conta.

## Ciclo de teste-drive

Agendar não é só reservar um horário -- é rastrear a retirada e a devolução reais do veículo, porque um atraso na devolução afeta quem vem depois:

```text
10:00           agendamento confirmado começa
10:15           funcionário marca "veículo retirado" (POST /appointments/{id}/pickup)
11:00           prazo de devolução -- SEMPRE scheduled_at + 60min, nunca a hora da retirada
                (chegar atrasado pra retirar não estica o prazo de devolução)
11:00 ou antes  funcionário marca "veículo devolvido" (POST /appointments/{id}/release) -- ou,
                se ele esquecer, uma rotina automática libera no prazo, no lugar dele
11:15           released_at + 15min de preparo -> dispara e-mail de confirmação pro
                PRÓXIMO agendamento daquele veículo, se houver um esperando
```

Se não existe agendamento anterior ainda em aberto (nenhum outro `pending`/`confirmed` com horário menor, ou o anterior foi `cancelled`/`no_show`), o e-mail de confirmação sai sem esperar handoff nenhum.

## Status

```text
pending
   ↓ (cliente confirma pelo e-mail, ou funcionário/admin confirma no painel)
confirmed
   ├── (funcionário marca retirada e depois devolução) ──► completed
   └── (prazo de devolução passa sem retirada, automático) ──► no_show

pending
   ↓ (cliente cancela pelo e-mail, funcionário/admin cancela no painel, ou expira)
cancelled
```

`completed`/`no_show` nunca são clique de painel isolado: `completed` nasce da liberação do veículo (`release`), que só é possível depois da retirada (`pickup`); `no_show` nasce de uma rotina automática, quando o prazo de devolução passa sem que o veículo tenha sido retirado. Cancelamento só existe a partir de `pending` -- confirmado não tem caminho de volta.

`pending` só ganha prazo de expiração (`expires_at`) quando o e-mail de confirmação de fato sai -- antes disso não faz sentido expirar uma reserva que o cliente ainda nem foi convidado a confirmar. Expirado, o agendamento vira `cancelled` e o horário volta a ficar disponível.

É o **cliente**, clicando no e-mail de confirmação (token opaco, sem login), quem normalmente confirma ou cancela -- o painel mantém as mesmas ações como *override* de funcionário (telefonema, walk-in), mesmo endpoint, dois jeitos de autorizar (sessão de admin/dono, ou o token).

## Concorrência

A aplicação verifica a disponibilidade antes da criação do agendamento.

O PostgreSQL fornece a proteção final contra duas requisições concorrentes para o mesmo veículo e horário.

Conflitos de concorrência devem resultar em `409 Conflict`.

## Cliente

O cliente informa:

- nome;
- e-mail;
- telefone.

Cada agendamento possui os dados do cliente informados no momento da solicitação -- uma cópia própria, que não sobrescreve o perfil da conta.

Um novo `customer` é criado caso ainda não exista, achado por e-mail.

## Autenticação

A senha é armazenada como hash no campo `password`.

O hash deve utilizar um algoritmo apropriado para senhas, como Argon2id.

`password_set_at` pode permanecer `NULL` enquanto a senha ainda não tiver sido definida pelo cliente.

Login: email+senha, sem PKCE/authorization code — API first-party, sem ganho real nesse handshake.

Endpoint único, sem `grant_type`: o corpo decide.

```text
POST /api/oauth/token

{ refresh_token }         -> renovação (outros campos presentes são ignorados)
{ email, password }       -> login
nenhum dos dois           -> 422
```

Resposta: `access_token` (JWT RS256, TTL curto), `refresh_token` (opaco, TTL longo, uso único — reuso revoga a família toda), `expires_in`, `scope` — no corpo (pra quem integra via script/Postman) **e** em cookies `HttpOnly`/`SameSite=Strict` (pra SPA, que nunca lê o token do corpo). Cookie evita exposição a roubo via XSS que `localStorage` teria.

Mutação autenticada por cookie exige o header `X-CSRF-Token` batendo com o cookie `XSRF-TOKEN` (double-submit) — request com `Authorization: Bearer` explícito não precisa disso, CSRF só é risco de credencial ambiente (cookie).

`client_id` identifica a aplicação (hoje só `autoschedule-web`), não o usuário — `role` vem do JWT.

```text
{ client_id, client_secret }   -> client_credentials (M2M), só client confidencial
```

`client_credentials`: sem usuário, sem refresh token (nada pra renovar) — só `access_token` com os `allowed_scopes` do client. Client tem que ser confidencial e provar o secret; mesma mensagem genérica de erro (`Invalid client credentials.`) pra secret errado ou client sem esse grant, não vaza qual é o problema.

### Registro

```text
POST /api/register

{ name, email, phone?, password, role }   -> role in (seller, customer) -- nunca admin
```

Público, sem autenticação. `admin` só é criado via `POST /api/users` (admin autenticado) — nunca uma opção auto-selecionável no registro público.

### Logout

```text
POST /api/logout
```

Lê o `refresh_token` do cookie, revoga a família inteira (mesmo mecanismo do reuso detectado), limpa os cookies `access_token`/`refresh_token`. Sem cookie de refresh, ainda limpa os cookies do lado do client — não é erro, só não tem mais nada a revogar.

### Login social (Google)

```text
{ client_id, id_token }   -> login via Google Identity Services
```

`id_token` vem assinado pelo Google (verificado via JWKS, `firebase/php-jwt`) -- `aud`/`iss`/assinatura conferidos, e `email_verified` tem que ser verdadeiro. E-mail batendo com conta existente (seller/admin inclusive) linka automaticamente, sem mudar role -- e-mail verificado pelo Google já prova posse, mesmo padrão que outros provedores usam. E-mail novo cria conta `customer` com senha aleatória inutilizável (conta social-only até um reset de senha trocar por uma real).

### Reset de senha

```text
POST /api/password-reset

{ email }   -> sempre 200 (não vaza se a conta existe); se existir, manda e-mail (Mailpit em dev) com link de redefinição
```

O link termina no mesmo endpoint de troca de senha que já existe (`PUT /me/password`), sem endpoint duplicado — o corpo decide qual dos dois caminhos:

```text
PUT /me/password

{ reset_token, password }            -> sem Bearer, valida o token (existe, não expirado, não usado)
{ current_password, password }       -> autenticado, valida a senha atual
```

Qualquer um dos dois caminhos revoga todos os refresh tokens do usuário e audita `user.password_changed` com `context.via` (`reset` ou `self`).

## Ciclo de vida da conta (lixeira)

Três estados: `active`, `trashed`, `deleted`. Modelo pensado pra ser reaproveitado por outras entidades com a mesma necessidade (concessionária, por exemplo).

```text
DELETE /me (ou /users/{id})
        │
        ▼
    trashed (revoga todo refresh token -- ninguém continua logado)
        │
        ├── login de novo dentro de 30 dias ──► active (restaurado, sem passo extra)
        ├── admin chama POST /users/{id}/restore ──► active
        ├── POST /me/purge (ou admin /users/{id}/purge) ──► deleted, agora
        └── 30 dias sem recuperação (rotina agendada) ──► deleted, automático
```

`deleted` é terminal: nome/e-mail/telefone escrubados (LGPD, direito ao esquecimento), `id`/`role`/timestamps preservados pra histórico/auditoria continuar válido. A linha nunca é removida do banco -- dado anonimizado deixa de ser dado pessoal (LGPD Art. 12), pode ser retido.

Enquanto `trashed`, a conta ainda existe pra fins de unicidade de e-mail (ninguém mais consegue se cadastrar com aquele e-mail até a purge rodar) mas não aparece como ativa em nenhum fluxo (login com sucesso a restaura antes de mais nada).

`assertNotLastAdmin` só conta admin com `status = active` -- um admin `trashed` já não protege ninguém.

## Autorização

A autorização é aplicada no backend.

- `admin`: acesso global;
- `seller`: acesso às concessionárias associadas;
- `customer`: acesso aos próprios dados e agendamentos.

Troca de role via CRUD admin (`PATCH /api/users/{id}`) aceita qualquer transição (com a trava do último admin). Self-service (`PATCH /me`) só aceita uma: `customer` virando `seller`, por vontade própria -- qualquer outra transição no caminho self é rejeitada (`403`).

Validações do frontend não são mecanismos de segurança.

## Auditoria

Operações relevantes geram registros em `audit_logs`. Implementados hoje:

```text
auth.login.succeeded
auth.login.failed
auth.refresh_token.reused
auth.service_token.issued
user.created
user.profile_updated
user.password_changed
user.deleted
user.trashed
user.restored
user.purged
dealership.created
dealership.updated
dealership.trashed
dealership.restored
dealership.purged
dealership.owner_reassigned
dealership.photo_updated
dealership.photo_removed
vehicle.created
vehicle.updated
vehicle.dealership_reassigned
vehicle.images_added
vehicle.image_removed
vehicle.images_reordered
vehicle.trashed
vehicle.restored
vehicle.purged
availability.created
availability.updated
availability.deleted
appointment.created
appointment.confirmed
appointment.cancelled
appointment.completed
```

`availability.*` cobre as três tabelas de disponibilidade (regra recorrente de concessionária, de veículo, exceção) -- `auditable_id` distingue qual. `appointment.*` não audita `pickup`/`release`/`no_show`: as próprias colunas de timestamp (`picked_up_at`, `released_at`) já são o registro, e `completed` audita através do `release`.

Os registros de auditoria são somente de leitura para a aplicação.

## Rate limiting

Toda rota passa por uma política de rate limit antes de qualquer outra verificação (sliding window, por usuário autenticado ou por IP):

- `general` (padrão 1000/min): cobre a API como um todo, com headroom generoso sobre o pico esperado;
- `auth` (padrão 5/min): `POST /oauth/token`, `POST /api/register`, `POST /api/password-reset`, `PUT /me/password`, `POST /api/appointments` e `POST /api/appointments/{id}/confirm|cancel` — proteção contra brute-force de login/registro/reset/agendamento.

Response com `429` inclui `Retry-After`. Falha do Redis não derruba a API — o rate limit fica temporariamente inativo (fail-open) em vez de bloquear todo o tráfego.

## Paginação

Endpoints de listagem aceitam `page`/`per_page` (`per_page` limitado por um máximo configurável) e respondem com `meta.total`/`meta.last_page` junto dos dados.

## Notificações

O envio de e-mails deve ser assíncrono.

Implementado, reaproveitando o mesmo mecanismo (`QueuedJob::SendEmail`) do reset de senha:

```text
criação do agendamento    -> vendedor dono da concessionária (aviso informativo)
e-mail de confirmação     -> cliente (o botão de confirmar/cancelar), timing decidido pela
                             rotina agendada (ver "Ciclo de teste-drive")
confirmado/cancelado/
concluído                 -> cliente
```

A falha no envio do e-mail não impede a criação do agendamento nem a transição de status -- o e-mail é um efeito colateral enfileirado depois, não parte da transação.

## Scheduler

O scheduler PHP executa tarefas periódicas (`ScheduledTask`), cada uma com seu próprio intervalo. O "último run" de cada tarefa fica no Redis, não em memória do processo -- sobrevive a restart e não duplica disparo se subir mais de uma réplica durante rollout.

Tarefas previstas:

- enviar o e-mail de confirmação do agendamento, quando o veículo estiver desbloqueado;
- expirar agendamentos pendentes sem confirmação a tempo;
- liberar (e concluir, ou marcar `no_show`) agendamentos confirmados cujo prazo de devolução passou;
- purgar da lixeira (usuário, concessionária e veículo) passados 30 dias sem recuperação;
- limpar dados temporários quando necessário.

## Worker

O worker PHP consome uma fila (`Queue`/Redis) e executa tarefas assíncronas, como envio de e-mails.

As tarefas devem ser idempotentes sempre que possível. Falha reenfileira com `attempts` incrementado; passadas 3 tentativas, o job vai pra uma lista de falhas (dead-letter) em vez de tentar pra sempre.

## Integridade

Toda regra crítica listada aqui também é constraint no banco -- ver [`docs/database.md#integridade`](database.md#integridade). Validação da aplicação não substitui isso.
