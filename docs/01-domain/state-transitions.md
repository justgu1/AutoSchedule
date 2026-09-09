# Estados e transições

## Ciclo de vida — usuário, concessionária, veículo

Os três agregados compartilham o mesmo enum (`TrashableStatus`), a mesma janela de 30 dias e a
mesma semântica — ver [`ADR-003`](../02-architecture/decisions/ADR-003.md) pro porquê de lixeira
em vez de hard delete.

```text
ACTIVE
   │
   ├── DELETE (manual, ou cascata do dono) ──> TRASHED
   │
TRASHED
   ├── login de novo dentro de 30 dias ──────> ACTIVE   (só conta de usuário)
   ├── POST .../restore ─────────────────────> ACTIVE   (só se ainda não passou por purge)
   ├── POST .../purge (explícito) ───────────> DELETED
   └── 30 dias sem recuperação (rotina) ─────> DELETED

DELETED — terminal, nunca volta.
```

| Estado atual | Ação | Próximo estado | Permitido? |
|---|---|---|---|
| `active` | Mover pra lixeira | `trashed` | Sim |
| `trashed` | Restaurar (login ou `restore`) | `active` | Sim, se ainda não anonimizado |
| `trashed` | Purgar | `deleted` | Sim |
| `deleted` | Restaurar | — | Não — estado terminal |
| `deleted` | Purgar de novo | — | Não — idempotente, não é erro, só não faz nada |
| `active` | Purgar direto | — | Não — precisa passar por `trashed` primeiro |

Concessionária e veículo têm uma segunda dimensão: `trashed_by_owner_deactivation`/
`trashed_by_dealership_trash` distingue lixeira **manual** (o dono apagou aquele registro) de
lixeira em **cascata** (o dono da conta/concessionária foi pra lixeira, arrastando o resto) — só a
cascata restaura sozinha quando a origem volta a ficar ativa.

## Agendamento (`AppointmentStatus`)

```text
PENDING
   │
   ├── cliente confirma (token) ou painel confirma ──> CONFIRMED
   │
   └── cliente cancela, painel cancela, ou expira ───> CANCELLED

CONFIRMED
   │
   ├── pickup() então release() ─────────────────────> COMPLETED
   └── prazo de devolução passa sem pickup (rotina) ──> NO_SHOW

CANCELLED, COMPLETED, NO_SHOW — terminais, nunca voltam.
```

| Estado atual | Ação | Próximo estado | Permitido? |
|---|---|---|---|
| `pending` | Confirmar | `confirmed` | Sim |
| `pending` | Cancelar | `cancelled` | Sim |
| `confirmed` | Cancelar | — | Não — cancelamento só existe a partir de `pending` |
| `confirmed` | Marcar retirada (`pickup`) | `confirmed` (com `picked_up_at`) | Sim |
| `confirmed` (retirado) | Marcar devolução (`release`) | `completed` | Sim |
| `confirmed` (não retirado) | `release` | — | Não — `release()` exige `picked_up_at` preenchido |
| `confirmed` (não retirado) | `no_show` automático | `no_show` | Sim, só a rotina dispara |
| `confirmed` (retirado) | `no_show` | — | Não — já foi retirado, não é falta |
| `completed`/`cancelled`/`no_show` | Qualquer transição | — | Não — estados terminais |

Ver [`business-rules.md#agendamento-ag`](business-rules.md#agendamento-ag) (AG-001 a AG-013) pro
detalhe de cada regra por trás dessas setas.
