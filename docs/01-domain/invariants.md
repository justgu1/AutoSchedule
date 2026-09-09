# Invariantes

Uma regra de negócio é uma ação permitida ou proibida ("seller só edita a própria concessionária").
Um invariante é um fato que precisa ser **sempre** verdadeiro, independente de quem agiu — por isso
vira teste de propriedade e, sempre que possível, constraint de banco (nunca só validação da
aplicação — ver [`ADR-002`](../02-architecture/decisions/ADR-002.md)).

| Invariante | Reforçado por |
|---|---|
| Toda concessionária tem exatamente um dono. | `dealerships.owner_user_id NOT NULL` |
| Um veículo nunca tem dois agendamentos ativos (`pending`/`confirmed`) no mesmo horário. | `appointments_active_vehicle_time_unique` (índice parcial) |
| Uma exceção de disponibilidade tem exatamente um escopo — concessionária ou veículo, nunca os dois nem nenhum. | `availability_exceptions_exactly_one_scope` |
| Toda janela de disponibilidade (regra recorrente ou exceção com horário) tem início antes do fim. | `..._valid_interval` em cada uma das 3 tabelas |
| Um agendamento `completed` sempre tem `picked_up_at` e `released_at` preenchidos. | `Appointment::release()` só parte de `pickedUpAt` não-nulo |
| Um agendamento `no_show` nunca tem `picked_up_at` preenchido. | `Appointment::markNoShow()` rejeita se já foi retirado |
| A posição de uma foto na galeria nunca se repete dentro do mesmo veículo. | `vehicle_images_position_unique (vehicle_id, position)` |
| `vehicles.search_vector` nunca fica dessincronizado de marca/modelo/versão/ano/descrição. | coluna `GENERATED ALWAYS AS (...) STORED` |
| Um client OAuth confidencial sempre tem `secret_hash`. | `oauth_clients_secret_required_for_confidential` |
| Sempre existe pelo menos um admin `active`. | `LastAdminGuard`, contado só sobre `status = 'active'` |
| E-mail de usuário é único entre contas não deletadas. | `users.email UNIQUE` + normalização (trim + minúsculas) na entrada |
| Um arquivo em `files` só é removido do storage quando nenhuma galeria/concessionária ainda o referencia. | dedupe por checksum, checado antes do `DELETE` |
