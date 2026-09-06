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
| RLS: seller só enxerga/altera a própria concessionária; admin enxerga qualquer uma; sem contexto, nenhuma linha; só `admin`/`seller` inserem | `DealershipRlsPolicyTest` (5 casos) |
| Mesmo modelo de lixeira de 3 estados da conta (`active`/`trashed`/`deleted`), reversível 30 dias | `DealershipTest::register_monta_uma_concessionaria_nova_ativa_e_sem_anonimizacao`, `::is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado`, `::is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado`; `PostgresDealershipRepositoryTest::trash_move_pra_status_trashed_e_seta_trashed_at`, `::restore_volta_status_active_e_limpa_trashed_at`, `::find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado` |
| Lixeira em cascata (conta do dono desativada) só restaura automaticamente quem foi trashed por causa dela -- lixeira manual fica parada | `PostgresDealershipRepositoryTest::trash_all_owned_by_so_afeta_as_ativas_e_marca_por_desativacao_do_dono`, `::restore_auto_trashed_owned_by_so_restaura_quem_foi_trashed_por_causa_do_dono` |
| Purge anonimiza identificador direto mas preserva CEP/cidade/estado/geolocalização; rotina agendada reaproveita a mesma `ScheduledTask` genérica da conta de usuário | `DealershipTest::anonymized_escruba_identificador_direto_mas_preserva_geolocalizacao`; `PurgeTrashedDealershipsTaskTest` (3 casos) |
| Galeria: posição calculada automaticamente, única por concessionária | `PostgresDealershipRepositoryTest::galeria_insere_encontra_e_calcula_a_proxima_posicao` |
| Validação de MIME no upload de foto (`image/jpeg`/`image/png`/`image/webp`) | Validação declarada em `DealershipController::addImage()`; **sem teste automatizado direto** -- verificado manualmente via curl (ver "Lacunas" no fim deste documento) |

### Veículos, Galeria (veículo), Disponibilidade, Exemplo, Exceções, Agendamento, Status, Concorrência, Cliente

Domínio ainda não implementado (`Worklist.md`, Dia 2/3/4) -- nenhum teste existe porque nenhum código existe. Não é lacuna de cobertura, é trabalho futuro.

## Autenticação

| Regra | Testes |
|---|---|
| Senha em hash Argon2id | `UserTest::register_nao_guarda_a_senha_em_texto_puro`, `UserTest::verify_password_confere_a_senha_em_texto_puro_contra_o_hash` |
| Login: `{ email, password }` → token; senha errada e e-mail inexistente dão a mesma mensagem | `OAuthServiceTest::login_with_password_com_credenciais_corretas_emite_tokens`, `::login_with_password_com_senha_errada_falha_com_mensagem_generica`, `::login_with_password_com_email_inexistente_falha_com_a_mesma_mensagem`; E2E: `auth.spec.ts > login > credenciais erradas mostra mensagem de erro` |
| Refresh: `{ refresh_token }` → renovação; reuso de token já rotacionado revoga a família inteira | `OAuthServiceTest::refresh_rotaciona_o_token_e_o_anterior_para_de_funcionar`, `::refresh_com_token_ja_rotacionado_revoga_a_familia_inteira`; `PostgresRefreshTokenRepositoryTest::rotate_marca_o_token_anterior_como_revogado_e_substituido`, `::rotate_de_um_token_ja_revogado_falha` |
| `client_credentials`: `{ client_id, client_secret }` → token M2M, sem refresh, só client confidencial | `OAuthServiceTest::client_credentials_com_secret_correto_emite_token_sem_refresh`, `::client_credentials_com_secret_errado_falha_com_mensagem_generica`, `::client_credentials_rejeita_client_publico`, `::client_credentials_rejeita_client_sem_esse_grant` |
| Login social (Google): `{ id_token }` → linka conta existente por e-mail sem mudar role, ou cria `customer` novo; e-mail não verificado rejeitado | `OAuthServiceTest::login_with_google_com_identidade_ja_linkada_loga_na_conta_existente`, `::login_with_google_com_email_de_conta_existente_linka_sem_mudar_role`, `::login_with_google_com_email_novo_cria_conta_customer`, `::login_with_google_rejeita_email_nao_verificado`, `::login_with_google_rejeita_client_sem_esse_grant` |
| Tokens em cookie `HttpOnly`/`SameSite=Strict`, além do corpo | `ResponseTest::with_cookie_devolve_uma_nova_instancia_sem_mutar_a_original`, `::with_cookie_aceita_httponly_e_secure_configuraveis`; `AuthContextMiddlewareTest::sem_header_authorization_cai_pro_cookie_access_token`, `::header_authorization_tem_prioridade_sobre_o_cookie` |
| CSRF double-submit em mutação autenticada por cookie; Bearer explícito pula a checagem | `CsrfMiddlewareTest` (7 casos, cobre cookie sem header, header divergente, header correto, Bearer explícito) |
| Registro público: `role` só `seller`/`customer`, nunca `admin` | Validação declarada em `UserController::register()` (`in:seller,customer`); E2E: `auth.spec.ts > registro > cria conta seller e loga automaticamente`, `> cria conta customer e loga automaticamente`, `> e-mail duplicado mostra erro` |
| Logout revoga a família do refresh token e limpa os cookies | `OAuthServiceTest::logout_revoga_o_refresh_token_e_o_reuso_subsequente_falha`, `::logout_com_token_inexistente_nao_lanca_excecao`; E2E: `auth.spec.ts > logout limpa a sessão e redireciona pro login` |
| Reset de senha: `POST /password-reset` sempre 200 (não vaza se a conta existe); `PUT /me/password` aceita `reset_token` (sem Bearer) ou `current_password` (autenticado) | `PasswordResetTokenTest`, `PostgresPasswordResetTokenRepositoryTest` (4 casos); E2E: `password-reset.spec.ts > esqueci a senha -> e-mail real via Mailpit -> redefinir -> login com a senha nova` (Mailpit real, não mock) |
| Access token JWT RS256, `alg` fixo (não aceita troca pra HS256) | `JwtTokenIssuerTest::rejeita_algoritmo_diferente_de_rs256_mesmo_assinado_com_a_chave_publica`, `::rejeita_assinatura_de_uma_chave_diferente`, `::rejeita_issuer_ou_audience_inesperados`, `::rejeita_token_expirado` |

## Autorização

| Regra | Testes |
|---|---|
| Autorização aplicada no backend, papel vem da rota (`roles` declarado no registro) | `RoleMiddlewareTest` (4 casos), `RouterTest::required_roles_devolve_os_roles_declarados_no_registro_da_rota` |
| RLS: `customer` só enxerga a própria linha; `admin`/contexto de serviço enxergam qualquer uma; sem contexto, nenhuma linha | `RlsPolicyTest` (6 casos, inclui INSERT com/sem contexto de serviço) |
| Validação do frontend não é mecanismo de segurança | Garantido pela dupla-checagem: toda regra acima já é testada no backend, independente do frontend |

## Auditoria

| Regra | Testes |
|---|---|
| Eventos gravam `actor_id`/`target_user_id` separados, contexto, IP, user agent | `PostgresAuditLoggerTest::grava_o_evento_com_ip_user_agent_e_contexto`, `::grava_actor_e_target_separados_quando_um_admin_age_sobre_outro_usuario` |
| Falha ao gravar auditoria não derruba a request | `PostgresAuditLoggerTest::falha_ao_gravar_nao_propaga_excecao` |
| `auth.service_token.issued` sem actor/target (client, não usuário) | `OAuthServiceTest::client_credentials_com_secret_correto_emite_token_sem_refresh` (assert `actorId`/`targetUserId` nulos) |

## Rate limiting

| Regra | Testes |
|---|---|
| Sliding window, por usuário autenticado ou IP; `429` com `Retry-After` | `RedisRateLimiterTest` (3 casos), `RateLimitMiddlewareTest` (7 casos, inclui chave por Bearer/cookie/IP) |
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
| Job resolve suas dependências (`MailProvider`, etc.) via container, sem registro manual por classe | `SendEmailJobTest::handle_repassa_os_dados_do_payload_pro_mail_provider` |

### Busca, Imagens, Integridade

Domínio ainda não implementado -- mesma situação de Veículos/Agendamento acima.

## Ciclo de vida da conta (lixeira)

| Regra | Testes |
|---|---|
| `DELETE /me` move pra `trashed` (não anonimiza na hora), revoga todo refresh token do usuário -- ninguém continua logado depois | `PostgresUserRepositoryTest::trash_move_pra_status_trashed_e_seta_deleted_at_sem_apagar_pii`; revogação garantida por `UserController::destroy()` chamar `revokeAllForUser`, coberta indiretamente por `PostgresRefreshTokenRepositoryTest::revoke_all_for_user_revoga_todo_token_ativo_do_usuario_em_qualquer_familia`; E2E: `account-trash.spec.ts > desativar a conta -> logar de novo -> conta restaurada` |
| Conta `trashed` ainda bloqueia reuso do e-mail (ninguém mais se registra com ele até a purge rodar) | `PostgresUserRepositoryTest::trashed_ainda_e_encontrado_por_email_e_bloqueia_reuso_do_email` |
| Login com sucesso restaura a conta `trashed` automaticamente (senha ou Google), antes de emitir o token | `OAuthServiceTest::login_with_password_restaura_conta_trashed_e_audita_antes_do_login`, `::login_with_google_restaura_conta_trashed_da_identidade_ja_linkada`; E2E: `account-trash.spec.ts` (fluxo completo, sem passo extra do usuário) |
| Restore só funciona antes da anonimização definitiva (`isEligibleForRestore`) | `UserTest::is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado`; `PostgresUserRepositoryTest::restore_volta_status_active_e_limpa_deleted_at` |
| Purge (`POST /me/purge` ou rotina agendada) anonimiza PII (nome, e-mail, telefone) e marca `deleted` -- nunca hard-delete, nunca antes dos 30 dias sem ação explícita | `UserTest::anonymized_remove_pii_mas_preserva_id_role_e_timestamps`; `PostgresUserRepositoryTest::anonymize_and_soft_delete_escruba_a_pii_na_linha_persistida`, `::anonymize_and_soft_delete_some_das_buscas`, `::anonymize_and_soft_delete_e_um_no_op_quando_usuario_nao_existe` |
| Elegibilidade de purge exige `trashed` há mais de 30 dias e ainda não anonimizado | `UserTest::is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado`; `PostgresUserRepositoryTest::find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado` |
| Rotina agendada (`PurgeTrashedUsersTask`) só purga quem é elegível, audita cada purge, não é no-op quando não há ninguém | `PurgeTrashedUsersTaskTest` (3 casos) |
| Registro de auditoria (`audit_logs`) preserva histórico mesmo após a conta ser purgada (id/role/timestamps mantidos) | `UserTest::anonymized_remove_pii_mas_preserva_id_role_e_timestamps` (id/role/createdAt sobrevivem à anonimização, permitindo que `audit_logs` continue referenciando a linha) |
| Trava do último admin só considera admin `active` (um `trashed` não protege ninguém) | `PostgresUserRepositoryTest::count_by_role_nao_conta_admin_trashed` |

## Acessibilidade (WCAG 2.1 AA)

Não é seção de `business-rules.md` (é requisito não-funcional, não regra de domínio), catalogado aqui pela mesma razão que LGPD acima:

| Regra | Testes |
|---|---|
| Páginas públicas sem violação de WCAG 2.1 AA (contraste, ARIA, labels) | E2E: `accessibility.spec.ts` (axe-core, tags `wcag2a`/`wcag2aa`/`wcag21a`/`wcag21aa`, roda em `/`, `/login`, `/register`, `/forgot-password`) |
| Operável 100% por teclado, ordem de foco lógica | E2E: `keyboard-navigation.spec.ts > login é operável só com teclado, sem mouse`, `> registro é operável só com teclado até o campo de role` |
| Sem quebra de layout em viewport mobile | Toda a suíte E2E roda em 2 projetos Playwright (`chromium` desktop + `mobile`, `iPhone 13`) -- qualquer spec que falhe só no mobile pega isso |
| Indicador visual de foco (2.4.7, AA) | **Não coberto por teste automatizado** -- é visual, sem asserção confiável sem screenshot-diff; revisão manual |
| Uso sem JavaScript (texto puro / navegador reader-only) | **Não aplicável a este teste automatizado** -- SPA 100% client-rendered, sem SSR; `<noscript>` em `index.html` avisa o usuário, mas não há conteúdo funcional sem JS (resolve com migração pra SSR, ver `Worklist.md`) |

## Lacunas conhecidas

Revisão desta sessão encontrou pontos sem teste automatizado direto -- documentados aqui em vez de silenciosamente ignorados:

- `UserController` (e `OAuthController`) não tem suíte de teste própria -- toda regra de negócio que vive puramente no controller (ex: a trava do último admin em `assertNotLastAdmin()`, o dispatch por corpo em `updatePassword()`, `destroy()`/`restore()`/`purge()`) só é coberta indiretamente (pelos testes de domínio/repositório que ele orquestra) ou por verificação manual via curl, documentada nas sessões de implementação. Corrigir isso é trabalho de teste novo, não de catálogo -- fica registrado aqui como próximo passo.
- `DealershipController` também não tem suíte própria -- mesma situação: orquestração (dono automático na criação por seller vs `owner_user_id` obrigatório por admin, reassociação de dono via `PATCH`, validação de MIME no upload de foto) só é coberta indiretamente pelos testes de domínio/repositório/RLS, mais verificação manual via curl (create/list/show/update/upload/remove/trash/restore/purge, isolamento entre sellers). Mesmo próximo passo do item acima.
