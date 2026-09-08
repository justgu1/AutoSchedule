# AutoSchedule — Arquitetura

## Visão geral

Hexagonal/ports-and-adapters pragmático, sem framework web PHP. Arquitetura modular guiada por DDD onde compensa; sem camada ou abstração criada só pra parecer mais arquitetada.

```text
Browser
   │
   ▼
 Nginx
   │
   ├──────── React SPA
   │
   └──────── /api/*
                │
                ▼
             PHP-FPM
                │
        ┌───────┼────────┐
        ▼       ▼        ▼
   PostgreSQL  Redis    MinIO
```

## Nginx

Único ponto de entrada HTTP: serve o frontend compilado, encaminha `/api/*` pro PHP-FPM, expõe `/health`.

## Frontend

React, TypeScript, Vite, Material UI, TanStack Query. Build multi-stage:

```text
Node.js -> npm ci -> npm run build -> dist/ -> Nginx runtime
```

Node.js só existe durante o build -- a imagem final não carrega `node_modules`.

## Backend

PHP 8.5, PHP-FPM, Composer. Nginx cuida da camada HTTP, PHP-FPM executa a aplicação.

`public/index.php` tem três linhas: quem compõe é `Bootstrap\HttpKernel` (config, container, router,
pipeline), e `Bootstrap\CliKernel` faz o mesmo pros processos de `bin/`. Rota é declarada de forma
fluente (`$router->get(path, [Controller::class, 'metodo'])->roles(...)->rateLimit('auth')`) e casada
uma vez por request, no `ResolveRouteMiddleware` -- os middlewares e o dispatch leem a rota do
próprio `Request` em vez de casar de novo.

## Camadas

```text
src/
├── Application/    caso de uso: orquestra o domínio, compõe contextos, abre transação
├── Domain/         entidade, value object, invariante, port do próprio contexto
├── Infrastructure/ adapter (Postgres/Redis/MinIO/SMTP), HTTP, fila, scheduler
└── Bootstrap/      composition root (ContainerFactory) + App\Config
```

**Domain e Application são organizados por contexto de negócio; Infrastructure, por tecnologia.**
Nas duas de dentro se procura por assunto ("concessionária") e regra e operação ficam lado a lado;
na de fora se procura por meio, e tudo que fala com o Postgres está em `Infrastructure/Persistence/`
(os repositórios, `Row`, `Statement`, `PdoTransaction`), com migration e seeder em
`Persistence/Schema/`, que é ferramenta de esquema e não persistência de runtime.

Regra de dependência, checada por Deptrac (`backend/deptrac.yaml`, `make arch`) e não só declarada aqui:

```text
Http (controller) -> Application -> Domain
                     Infrastructure -> Domain, Application (implementa os ports)
                     Bootstrap -> todas (é o lugar que liga uma na outra)
Domain -> ninguém
```

O controller traduz HTTP: valida o corpo (`Validator` devolve um `ValidatedInput` tipado), monta o `ActorContext` (quem, com que role, de onde) e serializa a resposta. Ele não conhece repositório nenhum. **Regra de negócio nunca fica no controller** -- vai pro caso de uso quando é sequenciamento/composição, ou pra entidade quando é invariante de um objeto só.

Onde cada coisa mora, na prática:

```text
Application/Auth/LoginWithPassword         sequência login -> restore -> emite par -> audita
Domain/Auth/RefreshToken::isExpired()      invariante do próprio token
Application/User/LastAdminGuard            invariante do CONJUNTO (não cabe em User)
Application/Dealership/ProcessDealership…  o trabalho: caso de uso disparado pela fila
Infrastructure/Jobs/…PhotoJob             o mecanismo: traduz envelope em argumento
```

Job é dividido: o trabalho é um caso de uso comum em `Application/`, e o adapter que traduz o envelope da fila em argumento fica em `Infrastructure/Jobs/`. Application contém o que precisa ser feito; Infrastructure, o mecanismo que dispara.

## Domínio

```text
User          implementado
Dealership    implementado
Audit         implementado (transversal, não é um domínio de negócio)
Notification  implementado (e-mail assíncrono)
Vehicle       implementado
Availability  planejado
Appointment   planejado
```

Pasta de contexto no singular (`Domain/Dealership/`, não `Dealerships/`) -- nomeia o contexto, não uma coleção.

## Ports & Adapters

Port só é criado quando trocar de adapter é um cenário real, não por padrão de projeto. Cada port mora na camada que depende dele: `Domain/<Contexto>/Ports/` quando é o domínio que precisa da abstração, `Application/Ports/` quando quem precisa é o caso de uso. Em uso hoje:

```text
Domain/File/Ports/StorageProvider       -> MinioAdapter (Flysystem S3)
Domain/Notification/Ports/MailProvider  -> SymfonyMailProvider (SMTP, Mailpit em dev)
Domain/Auth/Ports/TokenIssuer           -> JwtTokenIssuer (RS256)
Application/Ports/Queue                 -> RedisQueue
Application/Ports/JobProgress           -> JobStatusStore (Redis)
Application/Ports/TempFileStore         -> LocalTempFileStore (volume compartilhado com o worker)
Application/Ports/MailTemplateRenderer  -> MailTemplate
Application/Ports/Transaction           -> PdoTransaction (reentrante)
```

`Queue` é da Application, não do Domain: a assinatura é `push(QueuedJob, payload)` -- um enum com o nome do trabalho, não a classe que o executa, senão a Application apontaria pro adapter. Regra de negócio nenhuma sabe que existe fila. Já `DatabaseConnection` e `ScheduledTask` nem são port de camada de dentro: são interfaces de Infrastructure, e é lá que moram.

Google Maps Embed não virou port -- é só exibição por string de endereço (sem geocoding, sem Places autocomplete), chamado direto do browser (`DealershipMap`). ViaCEP é diferente: o backend proxeia (`GET /zip-codes/{cep}` → `LookupZipCode`, cache-aside sobre `zip_code_cache`) porque cachear a resposta é o próprio motivo de existir dessa camada -- ali sim vale um port (`ZipCodeProvider`, adapter `ViaCepZipCodeProvider`), já que trocar de provedor de CEP é um cenário real, diferente do mapa.

## PostgreSQL

Persistência, foreign keys, constraints, índices, transações e busca textual. Todo SQL passa por `DatabaseConnection::execute()`, que é onde o bind ganha tipo e onde violação de `UNIQUE` vira `DomainException(Conflict)` em vez de subir crua; a leitura passa por `Row`, que converte o `mixed` do PDO com verificação. Atomicidade é explícita via `Application/Ports/Transaction` onde há escrita múltipla dependente -- no HTTP ela vinha de graça da transação do RLS, mas worker e scheduler não têm request. Regra de negócio mora na aplicação; a integridade que o banco consegue garantir sozinho (unicidade, referência, concorrência) fica reforçada lá também -- validar só na aplicação e confiar que ninguém burla é o tipo de garantia que quebra na primeira migration mal aplicada ou acesso direto ao banco.

## Redis

Hoje: rate limiting (sliding window, script Lua atômico -- ver `docs/business-rules.md`), fila de jobs (`RedisQueue`) e o "último run" de cada tarefa do scheduler, que sobrevive restart e não duplica disparo entre réplicas por guardar o estado fora do processo.

## MinIO

```text
Upload -> Backend (valida MIME/tamanho) -> Job assíncrono (otimiza pro padrão do site) -> MinIO -> tabela `files`
```

PostgreSQL guarda a referência do objeto (`files`), nunca o binário. Upload confirmado no MinIO só depois disso vira linha em `files` -- nada de registro órfão apontando pra um objeto que falhou no meio do caminho. Toda foto de site (concessionária, galeria de veículo) é convertida pro padrão do site -- WebP, redimensionada -- pelo `ImageOptimizer` (GD) antes de gravar; nunca o arquivo cru que o cliente mandou.

## Autorização e RLS

Autorização acontece no backend, por role declarado na própria rota. O acesso de um seller é escopado pelo que ele é dono:

```text
seller -> dealership (owner_user_id) -> vehicles / availability / appointments
```

Admin tem acesso global, customer só aos próprios dados.

O elo `dealership -> vehicles` é resolvido por `EXISTS` dentro da própria policy, sem copiar `owner_user_id` pra `vehicles`. A cópia divergiria justamente quando o admin reassocia a concessionária a outro seller, e é por isso também que a policy de INSERT de veículo checa a concessionária, não só a role -- o dono ali vem do payload, não de quem está chamando.

RLS é camada adicional, nunca substitui essa checagem -- a rota já barrou quem não podia antes de qualquer SQL rodar. A role de runtime é `NOSUPERUSER NOBYPASSRLS`; `AuthContextMiddleware` seta `app.current_user_id`/`app.current_user_role` via `SET LOCAL`, só dentro da transação da própria request.

```text
Rate Limiting -> Authentication -> Authorization -> DB Transaction (SET LOCAL) -> RLS -> PostgreSQL
```

Rate limiting roda primeiro -- tráfego abusivo é barrado sem gastar uma transação no Postgres.

Scheduler e worker rodam fora de qualquer request HTTP -- sem `current_user_id`/role pra setar, as policies admin-or-owner esconderiam toda linha dessas conexões. Mesma policy de serviço que já existia pra login/registro (`app.is_service_context`) resolve: `SET` (não `SET LOCAL`, a conexão vive pelo processo inteiro) uma vez, logo depois de conectar.

`GET /dealerships/{id}` é uma exceção de propósito: é a MESMA rota que o gerenciamento usa, mas responde qualquer um -- dono/admin recebem o perfil completo, todo o resto (outro seller, customer, sem conta nenhuma) recebe o perfil público. A rota carrega uma flag própria (`app.is_public_read`, marcada no registro da rota, não no request), e essa flag é **composta** com o contexto autenticado normal, nunca alternativa a ele: com Bearer válido, `AuthContextMiddleware` seta `current_user_id`/`role` E `is_public_read` ao mesmo tempo -- sem isso, um seller autenticado batendo na concessionária de outro seller cairia em `current_user_role='seller'` sozinho, sem policy nenhuma pra liberar a leitura, e tomaria 404 em vez do fallback público. A policy em si só olha `status = 'active'`, sem checar quem é o dono -- é a rota ser a única marcada `publicRead` que garante essa visibilidade extra não vazar pras rotas de gerenciamento (`PATCH`/`DELETE`/etc nunca setam a flag).

## Auditoria

`audit_logs` é polimórfico (`auditable_type`+`auditable_id`), pensado desde o início pra suportar qualquer entidade auditável, não só conta de usuário. Semântica de coluna e convenção de query em [`docs/database.md`](database.md#auditoria).

## Processamento assíncrono

```text
Controller -> Caso de uso -> Queue (RedisQueue, enum QueuedJob)
           -> PHP Worker (bin/worker.php) -> adapter em Infrastructure/Jobs -> Caso de uso -> MailProvider/StorageProvider/etc.
```

Falha reenfileira com `attempts` incrementado; passadas 3 tentativas vira dead-letter em vez de tentar pra sempre. Scheduler e worker são processos PHP CLI, mesma imagem Docker do backend com outro comando -- cada um escala e reinicia sozinho via Deployment próprio no k8s, sem precisar de supervisor porque o orquestrador já cuida disso.

Job que o cliente precisa acompanhar (hoje: processar foto) grava progresso num `JobStatusStore` (Redis, chave com TTL) em vez de só rodar silencioso -- o controller devolve `202`+`job_id` na hora, e `GET /jobs/{id}/events` expõe isso como SSE (`StreamedResponse`, sem framework de streaming, só desliga o buffer do PHP-FPM e do nginx pra essa rota e escreve aos poucos). Genérico de propósito, e o import em lote da galeria de veículo já prova isso: as N fotos de um request viajam num envelope só, com um `job_id` só. N jobs separados exigiriam N conexões SSE do mesmo cliente, e o navegador corta em 6 por host.

## Busca e geolocalização

PostgreSQL Full Text Search + `pg_trgm` cobrem a busca de veículo, sem depender de Elasticsearch. Um caminho de SQL só serve listagem e busca: filtro ausente vira `IS NULL` no parâmetro e some da conta, então "sem filtro nenhum" é literalmente a listagem de antes -- não há duas consultas pra manter em sincronia. Detalhes de dicionário, pesos e índices em [`docs/database.md`](database.md).

Busca por proximidade ainda não existe -- ver [`docs/database.md#geolocalização`](database.md#geolocalização) pro porquê e pro que falta.

CEP autopreenche o resto do endereço no formulário via `GET /zip-codes/{cep}` (proxy cacheado do ViaCEP, ver "Ports & Adapters"), e a página pública da concessionária (`/concessionarias/{slug}` -- `slug`, nunca o `id`) mostra a localização com Google Maps Embed em modo `place` -- só exibição por string de endereço, sem geocoding nem Places autocomplete. `VITE_GOOGLE_MAPS_API_KEY` ausente não quebra a página, só omite o mapa (mesmo padrão do `VITE_GOOGLE_CLIENT_ID` do login social).

## CI/CD e Deploy

```text
push/PR -> backend/frontend (estática + lint) -> phpunit -> e2e -> load-test -> build-and-push (só main)
```

`phpunit`, `e2e` (Playwright) e `load-test` (k6) sobem contra Postgres/Redis reais via services do Actions, não mock. `build-and-push` publica `ghcr.io/justgu1/autoschedule-{backend,nginx}`.

GitOps, monorepo -- sem repositório de manifest separado. ArgoCD (`Application autoschedule`, `prune`+`selfHeal` automáticos) aponta direto pra `infra/k8s` deste repositório; `argocd-image-updater` rastreia as duas imagens por digest.

```text
merge na main -> build-and-push -> image-updater detecta o digest novo -> ArgoCD sincroniza -> apps atualizado
```

Nenhum passo manual entre o merge e produção.

## Princípios

- domínio independente de infraestrutura, regra de negócio nunca em controller;
- regra que o CI não checa decai -- fronteira de camada é checada por Deptrac, não confiada à disciplina;
- banco reforça o que a aplicação já valida, nunca é a única linha de defesa;
- assíncrono só onde falha/lentidão de terceiro (e-mail, upload) não pode travar a resposta;
- abstração só entra quando resolve um problema que já apareceu, não um hipotético.
