# Regra → teste

Cada ID vem de [`01-domain/business-rules.md`](../01-domain/business-rules.md) — o texto da regra
não se repete aqui, só o rastro até o teste que a prova. `Arquivo::método` é PHPUnit (backend);
`arquivo.spec.ts > nome` é Playwright (E2E, frontend). Lacunas conhecidas em
[`test-cases.md`](test-cases.md), não misturadas nesta tabela.

## Usuários (US)

| ID | Testes |
|---|---|
| US-001 | `UserTest::register_monta_um_usuario_novo_com_os_dados_informados_e_estado_inicial_correto` |
| US-002 | `UserTest::is_eligible_for_self_service_role_change_permite_so_customer_virando_seller`, `::is_eligible_for_self_service_role_change_rejeita_a_partir_de_seller_ou_admin`; E2E `become-seller.spec.ts` |
| US-003 | `RlsPolicyTest` (contexto admin) — ver lacuna em [`test-cases.md`](test-cases.md) |

## Concessionária (DL)

| ID | Testes |
|---|---|
| DL-001 | `PostgresDealershipRepositoryTest::insere_e_encontra_por_id`, `::find_by_owner_traz_so_as_concessionarias_daquele_dono_nao_deletadas` |
| DL-002 | `DealershipRlsPolicyTest` (8 casos) |
| DL-003 | Ver lacuna em [`test-cases.md`](test-cases.md) |
| DL-004 | `DealershipTest::register_monta_uma_concessionaria_nova_ativa_e_sem_anonimizacao`, `::is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado`, `::is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado`; `PostgresDealershipRepositoryTest::trash_move_pra_status_trashed_e_seta_trashed_at`, `::restore_volta_status_active_e_limpa_trashed_at`, `::find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado` |
| DL-005 | `PostgresDealershipRepositoryTest::trash_all_owned_by_so_afeta_as_ativas_e_marca_por_desativacao_do_dono`, `::restore_auto_trashed_owned_by_so_restaura_quem_foi_trashed_por_causa_do_dono` |
| DL-006 | `DealershipTest::anonymized_escruba_identificador_direto_mas_preserva_localidade_agregada` |
| DL-007 | `PostgresDealershipRepositoryTest::persiste_a_foto_e_permite_substituir_por_outra_ou_remover`; `DealershipTest::with_photo_substitui_a_referencia_mas_preserva_o_resto`; `ProcessDealershipPhotoTest::substitui_a_foto_anterior_e_apaga_o_arquivo_velho_do_storage` |
| DL-008 | `ProcessDealershipPhotoTest` (4 casos); `GdImageOptimizerTest` (4 casos); `JobStatusStoreTest` (3 casos) |
| DL-009 | Ver lacuna em [`test-cases.md`](test-cases.md) |
| DL-010 | `LookupZipCodeTest` (3 casos); E2E `dealerships.spec.ts` (`/api/zip-codes/*` mocado) |
| DL-011 | `AuthContextMiddlewareTest::rota_de_leitura_publica_sem_claims_seta_a_flag_publica`, `::leitura_publica_com_claims_seta_os_dois_contextos_juntos`; `RlsPolicyTest::contexto_de_leitura_publica_enxerga_so_seller_com_concessionaria_ativa`; E2E `dealerships.spec.ts > página pública da concessionária mostra nome, endereço e vendedor sem exigir conta` |
| DL-012 | `ViewDealershipPublicVehiclesTest` (2 casos); E2E `public-site.spec.ts > vitrine da página pública da concessionária mostra o veículo cadastrado` |
| DL-013 | `DealershipTest::register_gera_um_slug_a_partir_do_nome_sem_expor_o_id_inteiro`, `::with_profile_troca_os_dados_mas_preserva_dono_status_e_slug`, `::register_normaliza_acento_sem_quebrar_a_palavra_no_slug`; `PostgresDealershipRepositoryTest::persiste_e_encontra_por_slug` |

## Veículos (VH)

| ID | Testes |
|---|---|
| VH-001 | `PostgresVehicleRepositoryTest::insere_e_encontra_por_id`, `::busca_sem_filtro_traz_so_os_veiculos_das_concessionarias_daquele_dono`, `::filtro_por_concessionaria_traz_so_os_daquela_concessionaria` |
| VH-002 | `VehicleTest::register_monta_um_veiculo_novo_ativo_e_sem_anonimizacao`, `::allows_restore_permite_so_trashed_ainda_nao_anonimizado`, `::allows_purge_exige_trashed_ha_mais_de_grace_days`; `PostgresVehicleRepositoryTest::trash_move_pra_status_trashed_e_seta_trashed_at`, `::restore_volta_status_active_e_limpa_trashed_at`, `::find_trashed_so_traz_trashed_ainda_nao_anonimizado` |
| VH-003 | `PostgresVehicleRepositoryTest::trash_all_in_dealership_so_afeta_os_ativos_e_marca_por_cascata`, `::restore_auto_trashed_in_dealership_so_restaura_quem_caiu_por_cascata`, `::cascata_por_dono_alcanca_todas_as_concessionarias_dele` |
| VH-004 | `VehicleRlsPolicyTest` (10 casos) |
| VH-005 | `UpdateVehicleTest` (4 casos); `VehicleRlsPolicyTest::seller_nao_consegue_mover_o_proprio_veiculo_pra_concessionaria_de_outro_seller` |
| VH-006 | `MoneyTest` (5 casos); `PostgresVehicleRepositoryTest::preco_faz_a_ida_e_volta_pelo_banco_sem_perder_centavo`; `CreateVehicleTest::ano_e_preco_chegam_como_numero_do_json_sem_quebrar` |
| VH-007 | `ViewVehicleTest` (4 casos); `VehicleSearchTest::catalogo_publico_traz_veiculo_de_qualquer_dono_mas_so_ativo`, `::catalogo_publico_esconde_veiculo_ativo_de_concessionaria_na_lixeira` |

## Especificações e itens de veículo (AM)

| ID | Testes |
|---|---|
| AM-001 | `VehicleTest::with_details_troca_os_dados_mas_preserva_concessionaria_e_ciclo_de_vida`; `PostgresVehicleRepositoryTest` (ida e volta pelo banco) |
| AM-002 | `VehicleAmenityRlsPolicyTest` (9 casos) |
| AM-003 | `VehicleAmenitiesTest::replace_com_id_inexistente_no_catalogo_rejeita_com_422`, `::replace_nao_persiste_nada_quando_algum_id_e_invalido` |

## Galeria (GL)

| ID | Testes |
|---|---|
| GL-001 | Limite declarado em `VehicleController`/`ProcessVehiclePhotosTest` — sem teste direto do limite numérico |
| GL-002 | `VehicleGalleryTest::galeria_vem_ordenada_por_posicao_com_a_url_de_cada_imagem`, `::capas_de_varios_veiculos_saem_indexadas_por_veiculo`; `PostgresVehicleImageRepositoryTest::find_by_vehicle_devolve_ordenado_por_posicao`, `::find_covers_for_traz_a_posicao_zero_de_varios_veiculos_numa_consulta_so` |
| GL-003 | `PostgresVehicleImageRepositoryTest::reorder_troca_as_posicoes_sem_violar_o_unique`; `VehiclePhotoRoutesTest::reordenar_reescreve_as_posicoes_na_ordem_pedida`, `::reordenar_recusa_ordem_que_nao_lista_a_galeria_inteira` |
| GL-004 | `VehicleGalleryTest::remover_imagem_apaga_o_arquivo_quando_ninguem_mais_o_referencia`, `::remover_imagem_preserva_o_arquivo_quando_outro_veiculo_ainda_o_usa` |
| GL-005 | `ProcessVehiclePhotosTest` (4 casos) |

## Busca (SR)

| ID | Testes |
|---|---|
| SR-001 | `VehicleSearchTest::busca_vazia_devolve_a_listagem_inteira`; `PostgresVehicleRepositoryTest::busca_sem_filtro_traz_so_os_veiculos_das_concessionarias_daquele_dono`, `::busca_respeita_limit_e_offset` |
| SR-002 | `VehicleSearchTest::filtro_de_marca_encontra_o_veiculo_que_so_cita_a_marca_na_descricao`, `::filtro_de_marca_tambem_ordena_pelo_campo_proprio`, `::campo_proprio_ranqueia_acima_da_descricao`, `::busca_por_marca_encontra_o_veiculo_pelo_indice_de_texto` |
| SR-003 | `VehicleSearchTest::busca_encontra_mesmo_com_erro_de_digitacao_na_marca`, `::busca_com_caractere_especial_nao_quebra`, `::busca_por_prefixo_na_descricao_encontra_progressivamente`, `::busca_por_substring_no_meio_da_palavra_na_descricao`, `::termo_de_um_caractere_nao_quebra_a_busca` |
| SR-004 | `VehicleSearchTest::filtros_empilhados_se_combinam`, `::faixa_de_ano_recorta_o_resultado_pelas_duas_pontas`, `::filtro_de_specs_combina_cambio_carroceria_combustivel_e_km` |
| SR-005 | `VehicleSearchTest::ordenacao_explicita_por_preco_ignora_a_relevancia`, `::ordenacao_explicita_por_ano_traz_os_mais_novos_primeiro`, `::ordenacao_explicita_por_criacao_recente_e_antiga` |
| SR-006 | `VehicleSearchTest::facetas_trazem_so_o_que_existe_em_estoque`, `::facetas_publicas_ignoram_veiculo_trashed` |

## Disponibilidade (AV)

| ID | Testes |
|---|---|
| AV-001 | `AvailabilityCalculatorTest` (9 casos base, incl. o exemplo de `business-rules.md` como teste de referência) |
| AV-002 | `AvailabilityCalculatorTest` (exceção com prioridade sobre a regra recorrente) |
| AV-003 | `AvailabilityExceptionTest` (5 casos) |
| AV-004 | `AvailabilityCalculatorTest` (borda do intervalo `[start, end)`) |
| AV-005 | `AvailabilityCalculatorTest::veiculo_sem_regra_e_sem_excecao_nao_restringe_usa_so_a_janela_da_concessionaria` |
| AV-006 | `AvailabilityCalculatorTest::concessionaria_sem_regra_e_sem_excecao_usa_default_seg_sex_9_18` |
| AV-007 | `AvailabilityCalculatorTest::veiculo_sem_regra_recorrente_mas_com_excecao_pontual_respeita_a_excecao` |

## Agendamento (AG)

| ID | Testes |
|---|---|
| AG-001 | `AppointmentTest::return_deadline_e_sempre_scheduled_at_mais_60_minutos` |
| AG-002 | E2E `appointments.spec.ts > reserva concorrente do mesmo horário vira 409` |
| AG-003, AG-004 | E2E `appointments.spec.ts` (todo agendamento criado pelo fluxo público passa por isso) |
| AG-005, AG-006, AG-007 | `AppointmentTest` (14 casos, uma transição legal ou ilegal por teste) |
| AG-008 | `AppointmentTest::return_deadline_e_sempre_scheduled_at_mais_60_minutos` |
| AG-009, AG-010 | `AppointmentTest::request_monta_um_agendamento_pending_sem_token_de_confirmacao_ainda`, `::mark_confirmation_sent_gera_o_token_e_grava_o_prazo_a_partir_do_envio` |
| AG-011 | E2E `appointments.spec.ts > ciclo completo: e-mail de confirmação, token, retirada e devolução`, `> seller confirma agendamento pendente direto pelo painel, sem token` |
| AG-012 | E2E `appointments.spec.ts > reserva concorrente do mesmo horário vira 409`; índice único em [`05-data/model.md`](../05-data/model.md) |
| AG-013 | E2E `appointments.spec.ts > ciclo completo...` (Mailpit real) |
| — | RLS (AZ-002 aplicada a agendamento): `AppointmentRlsPolicyTest` (7 casos) |

## Credenciais de API — m2m (AC)

| ID | Testes |
|---|---|
| AC-001 | `OAuthClientTest::create_for_owner_monta_um_client_confidencial_m2m_sem_escopo_proprio`, `::rotate_secret_troca_o_hash_e_invalida_o_secret_anterior`, `::revoked_marca_o_client_como_revogado` |
| AC-002 | `OAuthFlowsTest::client_credentials_de_client_com_dono_autentica_como_o_dono` |
| AC-003 | `OAuthFlowsTest::client_credentials_de_client_revogado_e_negado`, `::client_credentials_de_dono_trashed_e_negado` |
| AC-004 | `CreateApiClientTest`, `ListApiClientsTest`, `RotateApiClientSecretTest`, `RevokeApiClientTest` — ver lacuna de teste de endpoint em [`test-cases.md`](test-cases.md) |

## Autenticação (AU)

| ID | Testes |
|---|---|
| AU-001 | `UserTest::register_nao_guarda_a_senha_em_texto_puro`, `::verify_password_confere_a_senha_em_texto_puro_contra_o_hash` |
| AU-002 | Exercido em todo caso de `OAuthFlowsTest` (cada grant é um corpo diferente pro mesmo endpoint) |
| AU-003 | `OAuthFlowsTest::login_with_password_com_senha_errada_falha_com_mensagem_generica`, `::login_with_password_com_email_inexistente_falha_com_a_mesma_mensagem`; E2E `auth.spec.ts > login > credenciais erradas mostra mensagem de erro` |
| AU-004 | `OAuthFlowsTest::refresh_rotaciona_o_token_e_o_anterior_para_de_funcionar`, `::refresh_com_token_ja_rotacionado_revoga_a_familia_inteira`; `PostgresRefreshTokenRepositoryTest::rotate_marca_o_token_anterior_como_revogado_e_substituido`, `::rotate_de_um_token_ja_revogado_falha` |
| AU-005 | `OAuthFlowsTest::client_credentials_rejeita_client_publico`, `::client_credentials_rejeita_client_sem_esse_grant` |
| AU-006 | `OAuthFlowsTest` (5 casos `login_with_google_*`) |
| AU-007 | `ResponseTest::with_cookie_devolve_uma_nova_instancia_sem_mutar_a_original`, `::with_cookie_aceita_httponly_e_secure_configuraveis`; `AuthenticateMiddlewareTest` (3 casos) |
| AU-008 | `CsrfMiddlewareTest` (7 casos) |
| AU-009 | Validação declarada em `UserController::register()`; E2E `auth.spec.ts > registro` (3 casos) |
| AU-010 | `OAuthFlowsTest::logout_revoga_o_refresh_token_e_o_reuso_subsequente_falha`, `::logout_com_token_inexistente_nao_lanca_excecao`; E2E `auth.spec.ts > logout limpa a sessão e redireciona pro login` |
| AU-011 | `PasswordResetTokenTest`, `PostgresPasswordResetTokenRepositoryTest` (4 casos); E2E `password-reset.spec.ts` (Mailpit real) |
| AU-012 | `JwtTokenIssuerTest::rejeita_algoritmo_diferente_de_rs256_mesmo_assinado_com_a_chave_publica`, `::rejeita_assinatura_de_uma_chave_diferente`, `::rejeita_issuer_ou_audience_inesperados`, `::rejeita_token_expirado` |

## Autorização (AZ)

| ID | Testes |
|---|---|
| AZ-001 | `RoleMiddlewareTest` (4 casos), `RouterTest::group_aplica_os_roles_a_toda_rota_registrada_dentro`; garantida pela dupla-checagem: toda regra de autorização já testada no backend, independente do frontend |
| AZ-002 | `RlsPolicyTest` (7 casos), mais o `*RlsPolicyTest` de cada tabela citado nas seções acima |

## Auditoria (AD)

| ID | Testes |
|---|---|
| AD-001 | `PostgresAuditLoggerTest::grava_o_evento_com_ip_user_agent_e_contexto`, `::grava_actor_e_target_separados_quando_um_admin_age_sobre_outro_usuario` |
| AD-002 | `PostgresAuditLoggerTest::falha_ao_gravar_nao_propaga_excecao` |
| AD-003 | `OAuthFlowsTest::client_credentials_com_secret_correto_emite_token_sem_refresh` (assert `actorId`/`auditableId` nulos) |

## Rate limiting (RL)

| ID | Testes |
|---|---|
| RL-001 | `RedisRateLimiterTest` (3 casos), `RateLimitMiddlewareTest` (6 casos) |
| RL-002 | `RateLimitMiddlewareTest::falha_aberta_quando_o_limiter_lanca_excecao` |
| RL-003 | `RateLimitMiddlewareTest::usa_a_policy_da_rota_quando_declarada_em_vez_da_geral` |

## Paginação (PG)

| ID | Testes |
|---|---|
| PG-001 | `PaginationPolicyTest` (4 casos) |

## Notificações (NT)

| ID | Testes |
|---|---|
| NT-001 | `RedisQueueTest::push_e_pop_entregam_o_mesmo_job`; E2E `password-reset.spec.ts` (worker real, Mailpit real) |
| NT-002 | Comportamento garantido pelo desenho (e-mail é job separado, fora da transação) — sem teste isolado |

## Scheduler e worker (SC)

| ID | Testes |
|---|---|
| SC-001 | `SchedulerTest` (3 casos: nunca rodou, intervalo não passou, intervalo passou) |
| SC-002 | `RedisQueueTest::retry_or_fail_reenfileira_com_attempts_incrementado`, `::retry_or_fail_manda_pra_lista_de_falhas_apos_o_maximo_de_tentativas` |

## Ciclo de vida da conta — lixeira (CV)

| ID | Testes |
|---|---|
| CV-001 | `PostgresUserRepositoryTest::trash_move_pra_status_trashed_e_seta_deleted_at_sem_apagar_pii`; `PostgresRefreshTokenRepositoryTest::revoke_all_for_user_revoga_todo_token_ativo_do_usuario_em_qualquer_familia`; E2E `account-trash.spec.ts` |
| CV-002 | `PostgresUserRepositoryTest::trashed_ainda_e_encontrado_por_email_e_bloqueia_reuso_do_email` |
| CV-003 | `OAuthFlowsTest::login_with_password_restaura_conta_trashed_e_audita_antes_do_login`, `::login_with_google_restaura_conta_trashed_da_identidade_ja_linkada`; E2E `account-trash.spec.ts` |
| CV-004 | `UserTest::is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado`; `PostgresUserRepositoryTest::restore_volta_status_active_e_limpa_deleted_at` |
| CV-005 | `UserTest::anonymized_remove_pii_mas_preserva_id_role_e_timestamps`; `PostgresUserRepositoryTest::purge_escruba_a_pii_na_linha_persistida`, `::purge_some_das_buscas` |
| CV-006 | `UserTest::is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado`; `PostgresUserRepositoryTest::find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado` |
| CV-007 | `PostgresUserRepositoryTest::count_by_role_nao_conta_admin_trashed` |

## Acessibilidade (WCAG 2.1 AA)

Requisito não-funcional, sem ID de `business-rules.md`.

| Regra | Testes |
|---|---|
| Páginas públicas estáticas e dinâmicas, e o painel autenticado inteiro, sem violação de WCAG 2.1 AA | E2E `accessibility.spec.ts` (axe-core, tags `wcag2a`/`wcag2aa`/`wcag21a`/`wcag21aa`) |
| Operável 100% por teclado, ordem de foco lógica | E2E `keyboard-navigation.spec.ts` (2 casos) |
| Sem quebra de layout em viewport mobile | Suíte E2E inteira roda em 2 projetos Playwright (`chromium` desktop + `mobile`) |
| Indicador visual de foco (2.4.7) | Ver lacuna em [`test-cases.md`](test-cases.md) |
| Uso sem JavaScript | Não aplicável — [`ADR-012`](../02-architecture/decisions/ADR-012.md) |

## Fora de escopo, de propósito

Teste de infraestrutura pura não valida regra de `business-rules.md`, então não aparece nesta
tabela: `ContainerTest`, `PipelineTest`, `RequestTest`, `ConfigTest`, `PostgresArrayTest`,
`UuidTest`, `SluggerTest` e companhia existem e rodam, mas a ausência deles aqui é escolha, não
esquecimento.
