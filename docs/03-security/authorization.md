# Autorização

Aplicada sempre no backend, papel declarado na própria rota (`$router->group([UserRole::...], ...)`)
— nunca confiada à disciplina do frontend (AZ-001). RLS reforça a mesma regra no banco, nunca
substitui essa checagem ([`ADR-002`](../02-architecture/decisions/ADR-002.md)).

## Por papel

| Papel | Acesso |
|---|---|
| `admin` | Global — qualquer concessionária, veículo, usuário, agendamento. |
| `seller` | Só o que é dono, transitivamente: concessionária → veículo → disponibilidade → agendamento. |
| `customer` | Só os próprios dados; agendamento é por token de e-mail, não por sessão de papel. |
| Sem conta | Catálogo público, página de concessionária/veículo, consulta de disponibilidade, criação de agendamento. |

## Por ação

| Ação | Admin | Seller | Customer | Sem conta |
|---|---|---|---|---|
| Criar concessionária | ✓ | ✓ | ✗ | ✗ |
| Editar/remover concessionária | ✓ (qualquer) | ✓ (só a própria) | ✗ | ✗ |
| Reassociar dono da concessionária | ✓ | ✗ | ✗ | ✗ |
| Ver concessionária — perfil completo | ✓ (qualquer) | ✓ (só a própria) | ✗ | ✗ |
| Ver concessionária — perfil público | ✓ | ✓ | ✓ | ✓ |
| Criar/editar/remover veículo | ✓ (qualquer) | ✓ (só das próprias concessionárias) | ✗ | ✗ |
| Mover veículo de concessionária | ✓ (qualquer par) | ✓ (só entre as próprias) | ✗ | ✗ |
| Consultar catálogo público de veículos | ✓ | ✓ | ✓ | ✓ |
| Consultar próprio estoque (`scope=mine`) | ✓ | ✓ | ✗ | ✗ |
| Gerenciar disponibilidade (regra/exceção) | ✓ (qualquer) | ✓ (só a própria) | ✗ | ✗ |
| Consultar datas/horários disponíveis | ✓ | ✓ | ✓ | ✓ |
| Criar agendamento | ✓ | ✓ | ✓ | ✓ |
| Confirmar/cancelar agendamento | ✓ (dono/admin, sessão) | ✓ (dono, sessão) | ✓ (só o próprio, via token do e-mail) | ✓ (via token) |
| Marcar retirada/devolução do veículo | ✓ | ✓ (só dos próprios veículos) | ✗ | ✗ |
| Listar agendamentos | ✓ (todos) | ✓ (só dos próprios veículos) | ✗ | ✗ |
| Gerar/rotacionar/revogar a própria credencial de API | ✓ | ✓ | ✓ | ✗ |
| Trocar role de outro usuário | ✓ | ✗ | ✗ | ✗ |
| Autoescalar `customer` → `seller` | n/a | n/a | ✓ (a si mesmo) | ✗ |
| Criar usuário `admin` | ✓ | ✗ | ✗ | ✗ (registro público nunca aceita `admin`) |

## RLS como espelho

```text
seller -> dealership (owner_user_id) -> vehicles / availability / appointments
```

Cada policy de RLS repete a mesma árvore de posse acima — nunca uma regra nova, só o mesmo grafo
aplicado no banco. Scheduler/worker (sem sessão HTTP) usam `app.is_service_context` pras rotinas
automáticas que precisam ler/escrever qualquer linha (purga, notificação, cálculo de
disponibilidade). Ver [`05-data/model.md`](../05-data/model.md) pra RLS tabela por tabela.
