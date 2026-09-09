# Visão geral

## Objetivo

Agendamento de visitas a veículos. Cliente escolhe um veículo publicado por uma concessionária,
vê os horários realmente livres e reserva uma visita, sem precisar de conta.

## Problema resolvido

Marcar uma visita a um veículo hoje depende de telefone/WhatsApp e checagem manual de agenda.
O sistema publica o estoque de várias concessionárias, calcula o horário livre de verdade
(cruzando agenda da concessionária, do veículo e agendamentos já feitos) e confirma por e-mail,
sem exigir cadastro do cliente.

## Escopo

- conta com três papéis (`admin`/`seller`/`customer`) e ciclo de vida reversível (lixeira);
- concessionária e veículo, cada um com dono, foto/galeria, busca e lixeira própria;
- disponibilidade combinando regra recorrente + exceção pontual, por concessionária e por veículo;
- agendamento público (sem conta), com confirmação por e-mail e ciclo de retirada/devolução real
  do veículo (teste-drive);
- credenciais de API (`client_credentials`) pra integração máquina-a-máquina, autenticando como
  o próprio vendedor dono.

## Fora do escopo

- pagamento ou financiamento (nenhuma tela de parcelas, nenhuma cobrança);
- favoritar veículo (não existe estado de "favorito" persistido);
- denúncia de anúncio;
- geolocalização/busca por proximidade (endereço é só texto + mapa de exibição, sem coordenadas);
- SSR (a SPA é 100% client-rendered — ver [`ADR-012`](../02-architecture/decisions/ADR-012.md)).

## Principais atores

| Ator | O que é |
|---|---|
| **Admin** | Acesso irrestrito; único papel que não se autoatribui (nasce por seed ou por outro admin). |
| **Seller** | Dono de uma ou mais concessionárias e do estoque delas. Todo `customer` pode virar `seller` sozinho. |
| **Customer** | Cliente final. Agenda uma visita sem precisar existir como conta antes — é criado ou reconhecido pelo e-mail no próprio agendamento. |
| **Visitante sem conta** | Vê o catálogo público, a página da concessionária e do veículo, e agenda visita, sem nunca autenticar. |

## Principais fluxos

1. Visitante navega o catálogo público, abre um veículo;
2. Consulta as datas e horários disponíveis pra aquele veículo;
3. Escolhe data/horário, informa nome/e-mail/telefone, confirma;
4. Recebe e-mail e confirma clicando num link (sem senha);
5. No dia, o vendedor marca retirada e devolução do veículo — o sistema fecha o ciclo sozinho
   (`completed`) ou marca falta (`no_show`) se ninguém retirou.

Ver [`use-cases.md`](use-cases.md) pra especificação de cada fluxo, e
[`state-transitions.md`](state-transitions.md) pra o ciclo de vida do agendamento.

## Stack

**Frontend** — React, TypeScript, Vite, Material UI, TanStack Query.
**Backend** — PHP 8.5 sem framework, PHP-FPM, PDO (PostgreSQL).
**Infraestrutura** — Docker Compose (dev), Kubernetes + ArgoCD (produção) — ver
[`07-operations/deployment.md`](../07-operations/deployment.md).

## Arquitetura

Hexagonal pragmático (Domain → Application → Infrastructure), sem camada criada só pra parecer
mais arquitetada. Detalhe completo em [`02-architecture/overview.md`](../02-architecture/overview.md).
