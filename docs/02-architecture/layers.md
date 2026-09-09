# Camadas

```text
src/
├── Application/    caso de uso: orquestra o domínio, compõe contextos, abre transação
├── Domain/         entidade, value object, invariante, port do próprio contexto
├── Infrastructure/ adapter (Postgres/Redis/MinIO/SMTP), HTTP, fila, scheduler
└── Bootstrap/      composition root (ContainerFactory) + App\Config
```

| Camada | Responsabilidade | Pode conhecer |
|---|---|---|
| **Domain** | Entidade, value object, invariante, port do próprio contexto. Regra que não depende de nada externo. | Domain |
| **Application** | Orquestra casos de uso: sequenciamento, composição de contextos, abertura de transação. Recebe DTO/`ActorContext`, nunca `Request` HTTP ([`ADR-001`](decisions/ADR-001.md)). | Domain, Application |
| **Infrastructure** | Adapter de tecnologia: Postgres, Redis, MinIO, SMTP, HTTP (controllers/middlewares), fila, scheduler. Implementa os ports que Domain/Application declaram. | Domain, Application (pra implementar os ports) |
| **Bootstrap** | Composition root — o único lugar que conhece e liga todas as outras. | Todas |

**Domain e Application são organizados por contexto de negócio; Infrastructure, por tecnologia.**
Nas duas de dentro se procura por assunto ("concessionária") e regra e operação ficam lado a lado;
na de fora se procura por meio — tudo que fala com o Postgres está em
`Infrastructure/Persistence/` (repositórios, `Row`, `Statement`, `PdoTransaction`), com migration e
seeder em `Persistence/Schema/`, que é ferramenta de esquema, não persistência de runtime.

Pasta de contexto no singular (`Domain/Dealership/`, não `Dealerships/`) — nomeia o contexto, não
uma coleção.

O controller traduz HTTP: valida o corpo (`Validator` devolve um `ValidatedInput` tipado), monta o
`ActorContext` (quem, com que role, de onde) e serializa a resposta. Não conhece repositório
nenhum. **Regra de negócio nunca fica no controller** — vai pro caso de uso quando é
sequenciamento/composição, ou pra entidade quando é invariante de um objeto só.

Onde cada coisa mora, na prática:

```text
Application/Auth/LoginWithPassword         sequência login -> restore -> emite par -> audita
Domain/Auth/RefreshToken::isExpired()      invariante do próprio token
Application/User/LastAdminGuard            invariante do CONJUNTO (não cabe em User)
Application/Dealership/ProcessDealership…  o trabalho: caso de uso disparado pela fila
Infrastructure/Jobs/…PhotoJob               o mecanismo: traduz envelope em argumento
```

Job é dividido: o trabalho é um caso de uso comum em `Application/`, e o adapter que traduz o
envelope da fila em argumento fica em `Infrastructure/Jobs/`. Application contém o que precisa ser
feito; Infrastructure, o mecanismo que dispara.

## Domínios implementados

```text
User            conta, papel, ciclo de vida (lixeira)
Dealership      concessionária, dono, foto, endereço
Vehicle         veículo, specs, itens (amenities), galeria, busca
Availability    regra recorrente + exceção, cálculo de horário livre
Appointment     agendamento, ciclo de retirada/devolução
Auth            login, tokens, credenciais m2m
Audit           transversal (não é domínio de negócio)
Notification    e-mail assíncrono
```
