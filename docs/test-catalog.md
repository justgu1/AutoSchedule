# AutoSchedule — Catálogo de regras de negócio → testes

`docs/testing.md` declara o princípio ("testes validam regras definidas em `docs/business-rules.md`"); este documento dá corpo rastreável a isso -- cada regra aponta pro(s) teste(s) que a valida. Mantido manualmente, atualizado junto do PR que adiciona a regra ou o teste. Uma seção por título de `docs/business-rules.md`, na mesma ordem.

Convenção: `Arquivo::método` para PHPUnit (backend); `arquivo.spec.ts > nome do teste` para Playwright (E2E, frontend).

## Usuários

| Regra | Testes |
|---|---|
| Três roles existem (`admin`, `seller`, `customer`) | `UserTest::register_monta_um_usuario_novo_com_os_dados_informados_e_estado_inicial_correto` |
| Self-service só escala `customer` → `seller`, nunca outra transição | `UserTest::is_eligible_for_self_service_role_change_permite_so_customer_virando_seller`, `UserTest::is_eligible_for_self_service_role_change_rejeita_a_partir_de_seller_ou_admin`; E2E: `become-seller.spec.ts > customer pode virar seller pelo próprio perfil` |
| Admin/CRUD pode trocar pra qualquer role, com trava do último admin | `RlsPolicyTest` (contexto admin), verificação manual documentada nas sessões de implementação -- **sem teste automatizado direto do `UserController::update()` ainda** (ver "Lacunas" no fim deste documento) |

## Concessionária

| Regra | Testes |
|---|---|
| Concessionária pertence a exatamente um seller (`owner_user_id`), sem tabela de associação; um seller pode ter mais de uma | `PostgresDealershipRepositoryTest::insere_e_encontra_por_id`, `::find_by_owner_traz_so_as_concessionarias_daquele_dono_nao_deletadas` |
| Listagem paginada tanto pro admin quanto pro seller (`page`/`per_page`, `meta.total`) | `PostgresDealershipRepositoryTest::find_by_owner_respeita_limit_e_offset`, `::count_by_owner_so_conta_as_do_dono_nao_deletadas` |
| RLS: seller só enxerga/altera a própria concessionária; admin enxerga qualquer uma; sem contexto, nenhuma linha; só `admin`/`seller` inserem; scheduler/worker (contexto de serviço) enxergam e atualizam qualquer uma; contexto de leitura pública (`GET /dealerships/{id}`, composto com o contexto autenticado, não alternativo) só enxerga concessionária `active`, mesmo pra um seller vendo a de outro | `DealershipRlsPolicyTest` (8 casos) |
| Mesmo modelo de lixeira de 3 estados da conta (`active`/`trashed`/`deleted`), reversível 30 dias | `DealershipTest::register_monta_uma_concessionaria_nova_ativa_e_sem_anonimizacao`, `::is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado`, `::is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado`; `PostgresDealershipRepositoryTest::trash_move_pra_status_trashed_e_seta_trashed_at`, `::restore_volta_status_active_e_limpa_trashed_at`, `::find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado` |
| Lixeira em cascata (conta do dono desativada) só restaura automaticamente quem foi trashed por causa dela -- lixeira manual fica parada | `PostgresDealershipRepositoryTest::trash_all_owned_by_so_afeta_as_ativas_e_marca_por_desativacao_do_dono`, `::restore_auto_trashed_owned_by_so_restaura_quem_foi_trashed_por_causa_do_dono` |
| Purge escruba o que localiza a porta (rua, número, complemento) e o que identifica direto, mas preserva CEP/cidade/UF; rotina agendada reaproveita a mesma `ScheduledTask` genérica da conta de usuário | `DealershipTest::anonymized_escruba_identificador_direto_mas_preserva_localidade_agregada`; `PurgeTrashedDealershipsTaskTest` (3 casos) |
| Foto: uma só, substituível, remove a anterior do storage ao trocar/remover | `PostgresDealershipRepositoryTest::persiste_a_foto_e_permite_substituir_por_outra_ou_remover`; `DealershipTest::with_photo_substitui_a_referencia_mas_preserva_o_resto`; `ProcessDealershipPhotoTest::substitui_a_foto_anterior_e_apaga_o_arquivo_velho_do_storage` |
| Foto processada fora do request (job assíncrono): otimiza pro padrão do site (WebP, redimensionada), reporta progresso, falha vira status `failed` sem exceção escapar | `ProcessDealershipPhotoTest` (4 casos); `GdImageOptimizerTest` (4 casos, conversão real e redimensionamento); `JobStatusStoreTest` (3 casos) |
| Limite de 20MB e whitelist de MIME (`image/webp`, único formato que o otimizador produz) no upload de foto | Limite declarado em `EnqueueDealershipPhoto`, whitelist em `UploadFile::uploadImage()`; **sem teste automatizado direto do endpoint** -- verificado manualmente via curl (ver "Lacunas" no fim deste documento) |
| `GET /zip-codes/{cep}` resolve CEP → endereço via ViaCEP e cacheia (`zip_code_cache`) -- só chama o terceiro na primeira vez que aquele CEP aparece | `LookupZipCodeTest` (3 casos: cache hit, cache miss grava, CEP inexistente devolve null sem gravar) |
| Formulário de concessionária autopreenche endereço/bairro/cidade/UF a partir do CEP, chamando o próprio backend (nunca o ViaCEP direto) | E2E: `dealerships.spec.ts > seller cria, edita...` e `> admin cria concessionária...` (`/api/zip-codes/*` mocado via `page.route`, resultado determinístico) |
| UF é um Autocomplete com busca, selecionável independente do CEP | E2E: `dealerships.spec.ts > UF é um autocomplete com busca, selecionável mesmo sem preencher o CEP` |
| Página pública (ver "Página pública" em `business-rules.md`): mesma rota do gerenciamento, perfil enxuto pra quem não é dono/admin, só concessionária `active` | `AuthContextMiddlewareTest::rota_de_leitura_publica_sem_claims_seta_a_flag_publica`, `::leitura_publica_com_claims_seta_os_dois_contextos_juntos`; `RlsPolicyTest::contexto_de_leitura_publica_enxerga_so_seller_com_concessionaria_ativa`; E2E: `dealerships.spec.ts > página pública da concessionária mostra nome, endereço e vendedor sem exigir conta` |
| Perfil público traz a vitrine (até 12 veículos ativos, `vehicles_total` separado), resolvendo a capa de todos numa consulta só | `ViewDealershipPublicVehiclesTest::perfil_publico_lista_os_veiculos_ativos_da_concessionaria`, `::perfil_publico_limita_a_vitrine_e_devolve_o_total_separado`; E2E: `public-site.spec.ts > vitrine da página pública da concessionária mostra o veículo cadastrado` |
| URL pública usa `slug` (nome + parte do id), nunca o `id` -- estável mesmo se o nome mudar, só troca na anonimização | `DealershipTest::register_gera_um_slug_a_partir_do_nome_sem_expor_o_id_inteiro`, `::with_profile_troca_os_dados_mas_preserva_dono_status_e_slug`, `::anonymized_escruba_identificador_direto_mas_preserva_localidade_agregada` (slug troca); `PostgresDealershipRepositoryTest::persiste_e_encontra_por_slug` |
| UF é uma das 27 unidades federativas, validada na borda -- sigla inexistente é 422, nunca 500 | `ValidatorTest::rejeita_sigla_de_estado_que_nao_existe` |
| Os sete campos de endereço andam juntos como um valor só (`Address`), e a anonimização é regra do próprio VO | `DealershipTest::anonymized_escruba_identificador_direto_mas_preserva_localidade_agregada`; `PostgresDealershipRepositoryTest::insere_e_encontra_por_id` (ida e volta do VO pelo banco) |

## Veículos

| Regra | Testes |
|---|---|
| Veículo pertence a uma única concessionária, e o dono é transitivo (`dealerships.owner_user_id`), sem cópia no próprio veículo | `PostgresVehicleRepositoryTest::insere_e_encontra_por_id`, `::busca_sem_filtro_traz_so_os_veiculos_das_concessionarias_daquele_dono`, `::filtro_por_concessionaria_traz_so_os_daquela_concessionaria` |
| Status é só a lixeira de 3 estados da conta e da concessionária (`active`/`trashed`/`deleted`), reversível 30 dias -- "vendido" e "agendado" não são estado guardado | `VehicleTest::register_monta_um_veiculo_novo_ativo_e_sem_anonimizacao`, `::allows_restore_permite_so_trashed_ainda_nao_anonimizado`, `::allows_purge_exige_trashed_ha_mais_de_grace_days`; `PostgresVehicleRepositoryTest::trash_move_pra_status_trashed_e_seta_trashed_at`, `::restore_volta_status_active_e_limpa_trashed_at`, `::find_trashed_so_traz_trashed_ainda_nao_anonimizado` |
| Purge só encerra o ciclo de vida -- veículo não tem PII, então marca e modelo sobrevivem pro histórico de agendamento | `VehicleTest::anonymized_encerra_o_ciclo_de_vida_sem_mexer_nos_dados_do_anuncio`; `PostgresVehicleRepositoryTest::listagem_ignora_veiculo_deletado` |
| Lixeira em cascata (concessionária ou conta do dono) só restaura automaticamente quem caiu por causa dela | `PostgresVehicleRepositoryTest::trash_all_in_dealership_so_afeta_os_ativos_e_marca_por_cascata`, `::restore_auto_trashed_in_dealership_so_restaura_quem_caiu_por_cascata`, `::cascata_por_dono_alcanca_todas_as_concessionarias_dele` |
| RLS: seller só enxerga/altera veículo das próprias concessionárias e não consegue inserir na alheia (o dono vem do payload, então o INSERT também checa); admin enxerga qualquer um; sem contexto, nenhuma linha; contexto de serviço enxerga e atualiza; leitura pública enxerga só veículo `active` de concessionária `active` | `VehicleRlsPolicyTest` (10 casos) |
| Preço em centavos inteiros, nunca float -- ida e volta pelo banco preserva o centavo, e a API troca decimal como string | `MoneyTest` (5 casos); `PostgresVehicleRepositoryTest::preco_faz_a_ida_e_volta_pelo_banco_sem_perder_centavo`; `CreateVehicleTest::ano_e_preco_chegam_como_numero_do_json_sem_quebrar` |
| Seller cria veículo só nas próprias concessionárias; admin, em qualquer uma -- concessionária alheia é 404, não 403 | `CreateVehicleTest` (4 casos) |
| Mover de concessionária pelo mesmo `PATCH`: quem move precisa alcançar as duas pontas (seller só as próprias, admin qualquer uma), e a movimentação gera evento próprio | `UpdateVehicleTest` (4 casos); `VehicleRlsPolicyTest::seller_nao_consegue_mover_o_proprio_veiculo_pra_concessionaria_de_outro_seller` |
| Lixeira da concessionária arrasta o estoque, e o restore devolve só o que caiu por cascata | `VehicleTrashCascadeTest` (3 casos) |
| Todo evento de auditoria tem tipo auditável -- prefixo novo sem braço no `match` seria 500 na primeira gravação | `AuditEventTest::todo_evento_tem_um_tipo_auditavel_correspondente` |
| Ano e preço são validados como número e faixa na borda, não como tamanho de string | `ValidatorTest::rejeita_valor_que_nao_e_numero`, `::rejeita_valor_fora_da_faixa_do_between`, `::aceita_numero_como_string_ou_como_numero` |
| `GET /vehicles`/`GET /vehicles/{id}` são públicas por padrão (catálogo, perfil enxuto); `scope=mine` exige `admin`/`seller` e devolve o próprio estoque completo -- dono/admin sempre recebem o perfil completo em `GET /vehicles/{id}`, mesmo sem `scope` | `ViewVehicleTest::dono_recebe_o_perfil_completo`, `::admin_recebe_o_perfil_completo_mesmo_sem_ser_dono`, `::visitante_sem_conta_recebe_o_perfil_publico_com_a_concessionaria_aninhada`, `::outro_seller_tambem_recebe_o_perfil_publico`; `VehicleSearchTest::catalogo_publico_traz_veiculo_de_qualquer_dono_mas_so_ativo`, `::catalogo_publico_esconde_veiculo_ativo_de_concessionaria_na_lixeira` |

## Galeria (veículo)

| Regra | Testes |
|---|---|
| A galeria referencia `files` (mesmo metadado da foto da concessionária) e vem ordenada por `position`, com a capa em `0` | `VehicleGalleryTest::galeria_vem_ordenada_por_posicao_com_a_url_de_cada_imagem`, `::capas_de_varios_veiculos_saem_indexadas_por_veiculo`; `PostgresVehicleImageRepositoryTest::find_by_vehicle_devolve_ordenado_por_posicao`, `::find_covers_for_traz_a_posicao_zero_de_varios_veiculos_numa_consulta_so` |
| Arquivo compartilhado por dedupe de checksum só sai do storage quando ninguém mais o referencia | `VehicleGalleryTest::remover_imagem_apaga_o_arquivo_quando_ninguem_mais_o_referencia`, `::remover_imagem_preserva_o_arquivo_quando_outro_veiculo_ainda_o_usa` |
| Lote assíncrono: um `job_id` pro request inteiro, uma transação por foto, falha vira status `failed` sem exceção escapar, temporários descartados sempre | `ProcessVehiclePhotosTest` (4 casos) |
| Posição é sequencial a partir do fim da galeria, e duplicada no mesmo veículo vira `409` | `ProcessVehiclePhotosTest::posicoes_sao_atribuidas_em_sequencia_a_partir_do_fim_da_galeria`; `PostgresVehicleImageRepositoryTest::posicao_duplicada_no_mesmo_veiculo_vira_conflito_de_dominio`, `::next_position_comeca_em_zero_e_segue_o_fim_da_galeria` |
| Reordenar exige a lista completa de ids e reescreve as posições em duas passadas, sem violar o UNIQUE no meio | `PostgresVehicleImageRepositoryTest::reorder_troca_as_posicoes_sem_violar_o_unique`; `VehiclePhotoRoutesTest::reordenar_reescreve_as_posicoes_na_ordem_pedida`, `::reordenar_recusa_ordem_que_nao_lista_a_galeria_inteira` |
| Imagem de outro veículo é `404`; purga manual limpa a galeria (a rotina agendada não alcança storage) | `VehiclePhotoRoutesTest::remover_imagem_de_outro_veiculo_e_404`, `::purge_limpa_a_galeria_junto`, `::purge_recusa_veiculo_que_nao_esta_na_lixeira` |
| RLS da galeria delega pro do veículo, inclusive pro contexto de serviço, que é quem grava a foto | `VehicleImageRlsPolicyTest` (6 casos) |
| `images[]` vira uma lista de arquivos; campo simples continua sendo a lista de um | `RequestFilesTest` (4 casos) |

## Busca

| Regra | Testes |
|---|---|
| Listar e buscar são a mesma consulta: sem filtro, a busca degenera na listagem inteira | `VehicleSearchTest::busca_vazia_devolve_a_listagem_inteira`; `PostgresVehicleRepositoryTest::busca_sem_filtro_traz_so_os_veiculos_das_concessionarias_daquele_dono`, `::busca_respeita_limit_e_offset` |
| Filtro de marca e modelo passa pelo índice de texto, então alcança quem cita a marca só na descrição -- e não só recorta: quem é da marca aparece antes de quem só a cita | `VehicleSearchTest::filtro_de_marca_encontra_o_veiculo_que_so_cita_a_marca_na_descricao`, `::filtro_de_marca_tambem_ordena_pelo_campo_proprio`, `::campo_proprio_ranqueia_acima_da_descricao`, `::busca_por_marca_encontra_o_veiculo_pelo_indice_de_texto` |
| Erro de digitação e caractere especial de query não quebram a busca | `VehicleSearchTest::busca_encontra_mesmo_com_erro_de_digitacao_na_marca`, `::busca_com_caractere_especial_nao_quebra` |
| Filtros empilhados se combinam por AND; faixas de ano e preço recortam pelas duas pontas | `VehicleSearchTest::filtros_empilhados_se_combinam`, `::faixa_de_ano_recorta_o_resultado_pelas_duas_pontas`; `PostgresVehicleRepositoryTest::filtro_por_concessionaria_traz_so_os_daquela_concessionaria` |
| `search_vector` é coluna gerada -- nenhum caminho de escrita precisa lembrar de recalcular | `VehicleSearchTest::search_vector_e_recalculado_quando_a_descricao_muda` |
| Paginação da busca não repete nem pula linha entre páginas (rank empata, o desempate por id resolve) | `VehicleSearchTest::paginacao_da_busca_nao_repete_nem_pula_linha_entre_paginas` |
| Busca respeita o escopo do seller e as facetas trazem só o que existe em estoque | `VehicleSearchTest::busca_nao_atravessa_a_concessionaria_de_outro_seller`, `::facetas_trazem_so_o_que_existe_em_estoque` |
| Facetas públicas ignoram veículo trashed; o catálogo é o mesmo filtro empilhado, só sem escopo de dono | `VehicleSearchTest::facetas_publicas_ignoram_veiculo_trashed` |
| Query string editável na barra de endereço vira filtro ausente, não 500 | `VehicleFilterQueryTest` (4 casos) |
| Site público: index lista o catálogo filtrável e o card abre a página do veículo, sem exigir conta; header troca "Entrar/Criar conta" por "Entrar no painel" conforme a sessão | E2E: `public-site.spec.ts > index lista o catálogo público...`, `> header oferece entrar e criar conta...` |
| Painel do vendedor: CRUD, galeria e lixeira do próprio estoque; filtro de marca encontra o veículo pelo painel; customer não acessa a rota | E2E: `vehicles.spec.ts` (3 casos) |

## Disponibilidade, Exemplo, Exceções, Agendamento, Status, Concorrência, Cliente

Domínio ainda não implementado -- nenhum teste existe porque nenhum código existe. Não é lacuna de cobertura, é trabalho futuro (ver `Worklist.md`).

## Autenticação

| Regra | Testes |
|---|---|
| E-mail é normalizado na entrada (trim + minúsculas), então caixa diferente é a mesma conta -- e cadastro simultâneo do mesmo e-mail é 409, não 500 | `PostgresUserRepositoryTest::email_duplicado_vira_conflito_de_dominio_e_nao_erro_interno`; a normalização é invariante de `Email`, exercida por todo teste que constrói um |
| Senha em hash Argon2id | `UserTest::register_nao_guarda_a_senha_em_texto_puro`, `UserTest::verify_password_confere_a_senha_em_texto_puro_contra_o_hash` |
| Login: `{ email, password }` → token; senha errada e e-mail inexistente dão a mesma mensagem | `OAuthFlowsTest::login_with_password_com_credenciais_corretas_emite_tokens`, `::login_with_password_com_senha_errada_falha_com_mensagem_generica`, `::login_with_password_com_email_inexistente_falha_com_a_mesma_mensagem`; E2E: `auth.spec.ts > login > credenciais erradas mostra mensagem de erro` |
| Refresh: `{ refresh_token }` → renovação; reuso de token já rotacionado revoga a família inteira | `OAuthFlowsTest::refresh_rotaciona_o_token_e_o_anterior_para_de_funcionar`, `::refresh_com_token_ja_rotacionado_revoga_a_familia_inteira`; `PostgresRefreshTokenRepositoryTest::rotate_marca_o_token_anterior_como_revogado_e_substituido`, `::rotate_de_um_token_ja_revogado_falha` |
| `client_credentials`: `{ client_id, client_secret }` → token M2M, sem refresh, só client confidencial | `OAuthFlowsTest::client_credentials_com_secret_correto_emite_token_sem_refresh`, `::client_credentials_com_secret_errado_falha_com_mensagem_generica`, `::client_credentials_rejeita_client_publico`, `::client_credentials_rejeita_client_sem_esse_grant` |
| Login social (Google): `{ id_token }` → linka conta existente por e-mail sem mudar role, ou cria `customer` novo; e-mail não verificado rejeitado | `OAuthFlowsTest::login_with_google_com_identidade_ja_linkada_loga_na_conta_existente`, `::login_with_google_com_email_de_conta_existente_linka_sem_mudar_role`, `::login_with_google_com_email_novo_cria_conta_customer`, `::login_with_google_rejeita_email_nao_verificado`, `::login_with_google_rejeita_client_sem_esse_grant` |
| Tokens em cookie `HttpOnly`/`SameSite=Strict`, além do corpo | `ResponseTest::with_cookie_devolve_uma_nova_instancia_sem_mutar_a_original`, `::with_cookie_aceita_httponly_e_secure_configuraveis`; `AuthenticateMiddlewareTest::sem_header_cai_pro_cookie`, `::header_tem_prioridade_sobre_o_cookie`, `::token_invalido_guarda_a_falha_e_segue_o_pipeline` (o token é decodificado uma vez só, e o erro é guardado em vez de lançado, pro rate limit contar a tentativa antes) |
| CSRF double-submit em mutação autenticada por cookie; Bearer explícito pula a checagem | `CsrfMiddlewareTest` (7 casos, cobre cookie sem header, header divergente, header correto, Bearer explícito) |
| Registro público: `role` só `seller`/`customer`, nunca `admin` | Validação declarada em `UserController::register()` (`in:seller,customer`); E2E: `auth.spec.ts > registro > cria conta seller e loga automaticamente`, `> cria conta customer e loga automaticamente`, `> e-mail duplicado mostra erro` |
| Logout revoga a família do refresh token e limpa os cookies | `OAuthFlowsTest::logout_revoga_o_refresh_token_e_o_reuso_subsequente_falha`, `::logout_com_token_inexistente_nao_lanca_excecao`; E2E: `auth.spec.ts > logout limpa a sessão e redireciona pro login` |
| Reset de senha: `POST /password-reset` sempre 200 (não vaza se a conta existe); `PUT /me/password` aceita `reset_token` (sem Bearer) ou `current_password` (autenticado) | `PasswordResetTokenTest`, `PostgresPasswordResetTokenRepositoryTest` (4 casos); E2E: `password-reset.spec.ts > esqueci a senha -> e-mail real via Mailpit -> redefinir -> login com a senha nova` (Mailpit real, não mock) |
| Access token JWT RS256, `alg` fixo (não aceita troca pra HS256) | `JwtTokenIssuerTest::rejeita_algoritmo_diferente_de_rs256_mesmo_assinado_com_a_chave_publica`, `::rejeita_assinatura_de_uma_chave_diferente`, `::rejeita_issuer_ou_audience_inesperados`, `::rejeita_token_expirado` |

## Autorização

| Regra | Testes |
|---|---|
| Autorização aplicada no backend, papel vem da rota (`roles` declarado no registro) | `RoleMiddlewareTest` (4 casos), `RouterTest::group_aplica_os_roles_a_toda_rota_registrada_dentro` |
| RLS: `customer` só enxerga a própria linha; `admin`/contexto de serviço enxergam qualquer uma; sem contexto, nenhuma linha; contexto de leitura pública só enxerga seller dono de concessionária `active` | `RlsPolicyTest` (7 casos, inclui INSERT com/sem contexto de serviço) |
| Validação do frontend não é mecanismo de segurança | Garantido pela dupla-checagem: toda regra acima já é testada no backend, independente do frontend |

## Auditoria

| Regra | Testes |
|---|---|
| Eventos gravam `actor_id`/`target_user_id` separados, contexto, IP, user agent | `PostgresAuditLoggerTest::grava_o_evento_com_ip_user_agent_e_contexto`, `::grava_actor_e_target_separados_quando_um_admin_age_sobre_outro_usuario` |
| Falha ao gravar auditoria não derruba a request | `PostgresAuditLoggerTest::falha_ao_gravar_nao_propaga_excecao` |
| `auth.service_token.issued` sem actor/target (client, não usuário) | `OAuthFlowsTest::client_credentials_com_secret_correto_emite_token_sem_refresh` (assert `actorId`/`auditableId` nulos na `AuditEntry`) |

## Rate limiting

| Regra | Testes |
|---|---|
| Sliding window, por usuário autenticado ou IP; `429` com `Retry-After` | `RedisRateLimiterTest` (3 casos), `RateLimitMiddlewareTest` (6 casos, inclui chave por usuário autenticado e por IP) |
| Fail-open quando o Redis falha | `RateLimitMiddlewareTest::falha_aberta_quando_o_limiter_lanca_excecao` |
| Política `auth` cobre login/registro/reset, não só `/oauth/token` | `RateLimitMiddlewareTest::usa_a_policy_da_rota_quando_declarada_em_vez_da_geral` |

## Paginação

| Regra | Testes |
|---|---|
| Página/tamanho com defaults e teto configuráveis, nunca abaixo de 1 | `PaginationPolicyTest` (4 casos) |

## Scheduler e Worker

| Regra | Testes |
|---|---|
| Tarefa periódica só roda de novo depois do próprio intervalo passar; "último run" sobrevive restart (guardado no Redis, não em memória) | `SchedulerTest` (3 casos: nunca rodou, intervalo não passou, intervalo passou) |
| Envio de e-mail é assíncrono (enfileira, não manda na hora) | `RedisQueueTest::push_e_pop_entregam_o_mesmo_job`; reset de senha via fila: E2E `password-reset.spec.ts` roda contra o worker real, e-mail chega no Mailpit de verdade (não mock) |
| Falha reenfileira com `attempts` incrementado; passadas 3 tentativas vira dead-letter | `RedisQueueTest::retry_or_fail_reenfileira_com_attempts_incrementado`, `::retry_or_fail_manda_pra_lista_de_falhas_apos_o_maximo_de_tentativas` |
| Escrita múltipla dependente é atômica também fora do request (worker e scheduler não têm a transação que o RLS abre) | `PurgeTrashedUsersTaskTest` e `PurgeTrashedDealershipsTaskTest` rodam a tarefa contra o Postgres real com `PdoTransaction`; a reentrância é exercida por todo teste de caso de uso que roda dentro da transação do teste |
| Job resolve suas dependências (`MailProvider`, etc.) via container, sem registro manual por classe | `SendEmailJobTest::handle_traduz_o_payload_da_fila_em_argumentos_do_caso_de_uso` |

## Ciclo de vida da conta (lixeira)

| Regra | Testes |
|---|---|
| `DELETE /me` move pra `trashed` (não anonimiza na hora), revoga todo refresh token do usuário -- ninguém continua logado depois | `PostgresUserRepositoryTest::trash_move_pra_status_trashed_e_seta_deleted_at_sem_apagar_pii`; revogação garantida por `Application/User/TrashAccount` chamar `revokeAllForUser`, coberta indiretamente por `PostgresRefreshTokenRepositoryTest::revoke_all_for_user_revoga_todo_token_ativo_do_usuario_em_qualquer_familia`; E2E: `account-trash.spec.ts > desativar a conta -> logar de novo -> conta restaurada` |
| Conta `trashed` ainda bloqueia reuso do e-mail (ninguém mais se registra com ele até a purge rodar) | `PostgresUserRepositoryTest::trashed_ainda_e_encontrado_por_email_e_bloqueia_reuso_do_email` |
| Login com sucesso restaura a conta `trashed` automaticamente (senha ou Google), antes de emitir o token | `OAuthFlowsTest::login_with_password_restaura_conta_trashed_e_audita_antes_do_login`, `::login_with_google_restaura_conta_trashed_da_identidade_ja_linkada`; E2E: `account-trash.spec.ts` (fluxo completo, sem passo extra do usuário) |
| Restore só funciona antes da anonimização definitiva (`TrashState::allowsRestore()`) | `UserTest::is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado`; `PostgresUserRepositoryTest::restore_volta_status_active_e_limpa_deleted_at` |
| Purge (`POST /me/purge` ou rotina agendada) anonimiza PII (nome, e-mail, telefone) e marca `deleted` -- nunca hard-delete, nunca antes dos 30 dias sem ação explícita | `UserTest::anonymized_remove_pii_mas_preserva_id_role_e_timestamps`; `PostgresUserRepositoryTest::purge_escruba_a_pii_na_linha_persistida`, `::purge_some_das_buscas` |
| Elegibilidade de purge exige `trashed` há mais de 30 dias e ainda não anonimizado | `UserTest::is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado`; `PostgresUserRepositoryTest::find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado` |
| Rotina agendada (`PurgeTrashedUsersTask`) só purga quem é elegível, audita cada purge, não é no-op quando não há ninguém | `PurgeTrashedUsersTaskTest` (3 casos) |
| Registro de auditoria (`audit_logs`) preserva histórico mesmo após a conta ser purgada (id/role/timestamps mantidos) | `UserTest::anonymized_remove_pii_mas_preserva_id_role_e_timestamps` (id/role/createdAt sobrevivem à anonimização, permitindo que `audit_logs` continue referenciando a linha) |
| Trava do último admin só considera admin `active` (um `trashed` não protege ninguém) | `PostgresUserRepositoryTest::count_by_role_nao_conta_admin_trashed` |

## Acessibilidade (WCAG 2.1 AA)

Não é seção de `business-rules.md` (é requisito não-funcional, não regra de domínio):

| Regra | Testes |
|---|---|
| Páginas públicas sem violação de WCAG 2.1 AA (contraste, ARIA, labels) | E2E: `accessibility.spec.ts` (axe-core, tags `wcag2a`/`wcag2aa`/`wcag21a`/`wcag21aa`, roda em `/`, `/login`, `/register`, `/forgot-password`) |
| Operável 100% por teclado, ordem de foco lógica | E2E: `keyboard-navigation.spec.ts > login é operável só com teclado, sem mouse`, `> registro é operável só com teclado até o campo de role` |
| Sem quebra de layout em viewport mobile | Toda a suíte E2E roda em 2 projetos Playwright (`chromium` desktop + `mobile`, `iPhone 13`) -- qualquer spec que falhe só no mobile pega isso |
| Indicador visual de foco (2.4.7, AA) | **Não coberto por teste automatizado** -- é visual, sem asserção confiável sem screenshot-diff; revisão manual |
| Uso sem JavaScript (texto puro / navegador reader-only) | **Não aplicável a este teste automatizado** -- SPA 100% client-rendered, sem SSR; `<noscript>` em `index.html` avisa o usuário, mas não há conteúdo funcional sem JS (resolve com migração pra SSR, ver `Worklist.md`) |

## Lacunas conhecidas

Pontos sem teste automatizado direto, documentados aqui em vez de silenciosamente ignorados.

- **`UserController` e `OAuthController` não têm suíte própria.** A regra que vive puramente no
  controller -- a trava do último admin, o dispatch por formato de corpo em `updatePassword()`, o
  ramo de `destroy()`/`restore()`/`purge()` -- é coberta indiretamente pelos testes de domínio e
  repositório que ele orquestra, mais verificação manual via curl.
- **`DealershipController` na mesma situação:** dono automático na criação por seller contra
  `owner_user_id` obrigatório por admin, reassociação de dono no `PATCH`, e o limite de 20MB mais a
  validação de MIME real no upload de foto.
- **`VehicleController` na mesma situação:** as regras de validação declaradas nele (faixa de ano,
  preço numérico, `scope=mine` exigindo `admin`/`seller`) são cobertas por `ValidatorTest` e pelos
  casos de uso, mais verificação manual via curl -- mas não pelo endpoint em si.
- **Indicador visual de foco (WCAG 2.4.7).** É visual; não há asserção confiável sem
  screenshot-diff. Revisão manual.
- **Uso sem JavaScript.** Não aplicável hoje: a SPA é 100% client-rendered, sem SSR. O `<noscript>`
  avisa, mas não há conteúdo funcional sem JS.

Duas coisas que **deixaram** de ser lacuna nesta rodada e ficam registradas para não voltarem
como surpresa:

- A regra de purga tinha duas implementações, e a do domínio **nunca rodava em produção** -- o SQL
  fazia a aritmética de data por conta própria. Hoje é uma só, e `TrashState::allowsPurge()` é
  chamada no caminho real, não só em teste.
- Violação de `UNIQUE` subia crua e virava 500. Agora tem teste
  (`PostgresUserRepositoryTest::email_duplicado_vira_conflito_de_dominio_e_nao_erro_interno`).

### Fora de escopo, de propósito

O catálogo mapeia **regra de negócio → teste**, então teste de infraestrutura não aparece aqui:
`ContainerTest`, `PipelineTest`, `RequestTest`, `ConfigTest`, `PostgresArrayTest`, `UuidTest` e
companhia existem e rodam, mas não validam regra de `docs/business-rules.md`. A ausência deles
nesta lista é escolha, não esquecimento.
