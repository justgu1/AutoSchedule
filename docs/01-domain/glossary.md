# Glossário

| Termo | Significado |
|---|---|
| **Concessionária** (`Dealership`) | Loja de veículos, dona de um `seller`. Tem endereço, foto, agenda própria e estoque. |
| **Veículo** (`Vehicle`) | Um anúncio, pertence a uma concessionária. Especificações (câmbio, carroceria, combustível, km, cor...) e itens (amenities) são planos, sem VO próprio. |
| **Item de veículo** (`Amenity`) | Equipamento/opcional do veículo (ex: ar-condicionado, câmera de ré). Catálogo global, curado por seed — não é criado pela API. |
| **Agendamento** (`Appointment`) | Reserva de uma visita a um veículo, num horário. Rastreia também a retirada e devolução reais do veículo (teste-drive), não só a reserva. |
| **Disponibilidade** | Cálculo de horário livre: regra recorrente da concessionária ∩ regra recorrente do veículo ∩ exceção pontual ∩ sem agendamento já ocupando o slot. |
| **Regra recorrente** (`WeeklyWindow`) | Janela de horário por dia da semana (ex: seg-sex 9h-18h), da concessionária ou do veículo. |
| **Exceção pontual** (`AvailabilityException`) | Ajuste de uma data específica (feriado, manutenção, plantão especial), com prioridade sobre a regra recorrente daquela data. |
| **Lixeira** (`TrashableStatus`) | Ciclo de vida em 3 estados (`active`/`trashed`/`deleted`), reversível por 30 dias — nunca hard delete. Ver [`state-transitions.md`](state-transitions.md). |
| **Anonimização** (`purge`) | Estado terminal da lixeira: apaga PII, preserva o que o histórico ainda referencia (id, marca/modelo, timestamps). |
| **ActorContext** | Quem está fazendo a chamada (id, role), montado pelo controller a partir do token — é o que os casos de uso usam pra autorizar/auditar, nunca o `Request` HTTP em si. |
| **RLS** (Row-Level Security) | Policy do PostgreSQL que restringe linha visível/gravável pela sessão. Sempre depois da autorização do backend, nunca no lugar dela. |
| **Contexto de serviço** (`is_service_context`) | Sessão de banco sem usuário (worker, scheduler, criação pública de agendamento) — bypassa a policy "dono", nunca a de PII. |
| **Client m2m** | Credencial OAuth2 `client_credentials` de um vendedor — autentica como o próprio dono, sem sistema de escopo próprio. |
| **Slug** | Identificador amigável de URL (nome + parte do id), usado na página pública da concessionária em vez do `id`. |
