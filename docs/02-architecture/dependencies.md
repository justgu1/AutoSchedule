# Dependências

Regra de dependência, checada por Deptrac (`backend/deptrac.yaml`, `make arch`) — nunca só
declarada em prosa:

```text
Http (controller) -> Application -> Domain
                     Infrastructure -> Domain, Application (implementa os ports)
                     Bootstrap -> todas (é o lugar que liga uma na outra)
Domain -> ninguém
```

- Domain não depende de Infrastructure.
- Application não recebe `Request` HTTP ([`ADR-001`](decisions/ADR-001.md)).
- Domain não conhece HTTP.
- Infrastructure implementa portas definidas pelas camadas internas.

Regra que o CI não checa decai — por isso Deptrac roda no `make arch`, não fica só descrito aqui.

## Ports & Adapters

Port só é criado quando trocar de adapter é um cenário real, não por padrão de projeto. Cada port
mora na camada que depende dele: `Domain/<Contexto>/Ports/` quando é o domínio que precisa da
abstração, `Application/Ports/` quando quem precisa é o caso de uso.

| Port | Adapter |
|---|---|
| `Domain/File/Ports/StorageProvider` | `MinioAdapter` (Flysystem S3) |
| `Domain/Notification/Ports/MailProvider` | `SymfonyMailProvider` (SMTP, Mailpit em dev) |
| `Domain/Auth/Ports/TokenIssuer` | `JwtTokenIssuer` (RS256) |
| `Application/Ports/Queue` | `RedisQueue` |
| `Application/Ports/JobProgress` | `JobStatusStore` (Redis) |
| `Application/Ports/TempFileStore` | `LocalTempFileStore` (volume compartilhado com o worker) |
| `Application/Ports/MailTemplateRenderer` | `MailTemplate` |
| `Application/Ports/Transaction` | `PdoTransaction` (reentrante) |

`Queue` é da Application, não do Domain: a assinatura é `push(QueuedJob, payload)` — um enum com o
nome do trabalho, não a classe que o executa, senão a Application apontaria pro adapter. Regra de
negócio nenhuma sabe que existe fila. `DatabaseConnection` e `ScheduledTask` nem são port de
camada de dentro: são interfaces de Infrastructure, e é lá que moram.

Google Maps Embed não virou port — é só exibição por string de endereço, chamado direto do
browser. ViaCEP é diferente: o backend proxeia (`GET /zip-codes/{cep}` → `LookupZipCode`,
cache-aside sobre `zip_code_cache`) porque cachear a resposta é o próprio motivo de existir dessa
camada — ali sim vale um port (`ZipCodeProvider`, adapter `ViaCepZipCodeProvider`), já que trocar
de provedor de CEP é um cenário real, diferente do mapa.

## Processamento assíncrono

```text
Controller -> Caso de uso -> Queue (RedisQueue, enum QueuedJob)
           -> PHP Worker (bin/worker.php) -> adapter em Infrastructure/Jobs -> Caso de uso -> MailProvider/StorageProvider/etc.
```

Falha reenfileira com `attempts` incrementado; passadas 3 tentativas vira dead-letter. Scheduler e
worker são processos PHP CLI, mesma imagem Docker do backend com outro comando.

Job que o cliente precisa acompanhar (hoje: processar foto) grava progresso num `JobStatusStore`
(Redis, chave com TTL) em vez de rodar silencioso — o controller devolve `202`+`job_id` na hora, e
`GET /jobs/{id}/events` expõe isso como SSE. Genérico de propósito: o import em lote da galeria de
veículo prova isso — as N fotos de um request viajam num envelope só, com um `job_id` só (N jobs
separados exigiriam N conexões SSE do mesmo cliente, e o navegador corta em 6 por host).
