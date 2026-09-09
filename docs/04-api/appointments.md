# API — Agendamentos

```text
GET    /api/vehicles/{id}/availability/dates?month=YYYY-MM   -- público
GET    /api/vehicles/{id}/availability/slots?date=YYYY-MM-DD -- público
POST   /api/appointments                                     -- público, cria (UC-001)
GET    /api/appointments/{id}?token=...                       -- dono/admin (sessão) ou cliente (token)
POST   /api/appointments/{id}/confirm                         -- idem (UC-002)
POST   /api/appointments/{id}/cancel                          -- idem
GET    /api/appointments                                      -- admin/seller (painel)
POST   /api/appointments/{id}/pickup                          -- admin/seller (UC-003)
POST   /api/appointments/{id}/release                         -- admin/seller (UC-003)
```

Regras: [`01-domain/business-rules.md`](../01-domain/business-rules.md) — AG-\*. Ciclo completo em
[`01-domain/state-transitions.md`](../01-domain/state-transitions.md).

## `POST /api/appointments`

```text
{ vehicle_id, scheduled_at, customer_name, customer_email, customer_phone }
```

Sem conta, sem token. Disponibilidade é reconferida no momento da criação, não confiada só na
tela (AG-002/AG-012) — reserva concorrente do mesmo veículo/horário responde `409`.

## Confirmar/cancelar

```text
{ token }   -- do e-mail de confirmação, quem não tem sessão de dono/admin
{ }         -- sessão de dono/admin, override de painel
```

Token errado ou ausente sem sessão responde `404` — a mesma resposta de agendamento inexistente,
nunca confirma que o id existe pra quem não tem como prová-lo (AG-011).

## Retirada e devolução (painel)

`pickup` exige `confirmed`; `release` exige `pickup` já feito, e vira `completed` (AG-006). Sem
ação nenhuma até o prazo (`scheduled_at + 60min`), a rotina agendada marca `no_show` (AG-007/AG-008).
