# Lacunas conhecidas

Pontos sem teste automatizado direto, documentados aqui em vez de silenciosamente ignorados —
todos verificados manualmente (curl ou fumaça) antes de considerados prontos.

| Área | Lacuna | Coberto indiretamente por |
|---|---|---|
| US-003, DL-003 | `UserController`/`DealershipController` não têm suíte própria de endpoint (trava do último admin, dispatch por formato de corpo, reassociação de dono, limite/MIME de foto). | Domínio + repositório que o controller orquestra, mais verificação manual via curl. |
| VH-\* | `VehicleController` idem — faixa de ano, preço numérico, `scope=mine`. | `ValidatorTest` + casos de uso, mais curl manual. |
| AC-004 | `ApiClientController` sem teste de endpoint (rotas, formato de resposta, secret só na criação/rotação). | `OAuthFlowsTest`/`OAuthClientTest` (regra de negócio por trás), curl manual (round-trip completo). |
| AG-\* | `AppointmentController`/`CreateAppointment` sem suíte de integração PHPUnit própria — concorrência, confirmação por token, timing do e-mail. | E2E `appointments.spec.ts` (Mailpit real). Expiração e no-show automático seguem só com fumaça manual. |
| GL-001 | Limite de 20 imagens/veículo não tem teste numérico direto. | Limite declarado em `VehicleController`. |
| NT-002 | Falha de envio não impedir a criação/transição não tem teste isolado. | Garantido pelo desenho (e-mail é job separado, fora da transação). |
| WCAG 2.4.7 | Indicador visual de foco — é visual, sem asserção confiável sem screenshot-diff. | Revisão manual. |
| Seeder de demo | Busca de foto real (Wikimedia Commons) é best-effort e depende de rede externa — sem teste automatizado. | Rodado manualmente contra a rede real (30 de 32 modelos trouxeram foto na última rodada); cai pro placeholder de GD em qualquer falha. |

## Resolvidas nesta rodada (registradas pra não voltarem como surpresa)

- Slug de concessionária quebrava palavra com acento em dois (`iconv` virava `"~a"` pra `"ã"`,
  nunca `"a"`) — extraído pro `Slugger` compartilhado, com `SluggerTest` e regressão em
  `DealershipTest`.
- `amenity_ids` inválido não tinha nenhum teste — `VehicleAmenitiesTest` cobre substituição válida
  e id inexistente (422, sem persistir nada); RLS delegada em `VehicleAmenityRlsPolicyTest`.
- Filtro combinado de specs (câmbio/carroceria/combustível/km) só tinha fumaça manual — agora
  `VehicleSearchTest::filtro_de_specs_combina_cambio_carroceria_combustivel_e_km`.
- E2E rodava contra o mesmo banco de dev, poluindo o catálogo de demonstração a cada rodada —
  isolado em `autoschedule_e2e` (ver [`05-data/migrations.md`](../05-data/migrations.md)).
- `autoschedule_e2e` isolado, mas nunca recriado — Playwright commita de verdade (sem rollback de
  teste), então o banco só crescia a cada rodada. Passadas ~300 contas de vendedor acumuladas, um
  dropdown que só busca a primeira página (`GET /users?role=seller&per_page=100`) parou de achar
  vendedor recém-criado, e um teste de reassociação de dono começou a falhar por acúmulo, não por
  bug. `--fresh` em `bin/setup_test_database.php` derruba e recria o banco a cada `make e2e`.
- Header (`AuthenticatedLayout`/`PublicLayout`) sem colapsar em mobile causava scroll horizontal e
  tornava a navegação inclicável; `FilterSidebar` no mobile abria o drawer de filtro mas nunca
  fechava sozinho, escondendo o resultado filtrado atrás dele; botão "Filtros" com o mesmo
  contraste insuficiente já corrigido no header, mas esquecido nesse componente novo;
  `CircularProgress` sem `aria-label` em 7 páginas (WCAG "aria-progressbar-name").
- Violação de `UNIQUE` subia crua e virava 500 — `PostgresUserRepositoryTest::email_duplicado_vira_conflito_de_dominio_e_nao_erro_interno`.
