# Modelo de ameaças

Ameaças reais consideradas e a mitigação em produção — não um exercício acadêmico, só o que
efetivamente existe no código.

| Ameaça | Mitigação |
|---|---|
| Brute-force de login/registro/reset de senha | Rate limit dedicado (`auth`, sliding window, 5/min por padrão) além do geral, cobrindo `/oauth/token`, `/register`, `/password-reset`, `/me/password` e `/appointments` (RL-003). |
| Enumeração de conta via mensagem de erro | Login com senha errada e e-mail inexistente respondem a mesma mensagem (AU-003); `/password-reset` sempre `200` (AU-011). |
| Roubo de token via XSS | Token nunca fica em `localStorage` — cookies `HttpOnly` (JS não lê), `SameSite=Strict`. |
| CSRF em mutação autenticada por cookie | Double-submit (`XSRF-TOKEN` cookie + header `X-CSRF-Token`); Bearer explícito não depende de cookie, então pula a checagem (AU-008). |
| Reuso de refresh token roubado | Uso único; reuso de um já rotacionado revoga a família inteira (AU-004). |
| Bypass de autorização por bug de aplicação | RLS reforça a mesma regra no banco, sessão de runtime sem `BYPASSRLS` ([`ADR-002`](../02-architecture/decisions/ADR-002.md)). |
| Escalonamento de privilégio via self-service | `PATCH /me` só aceita `customer` → `seller`; qualquer outra transição de role exige `admin` (US-002/US-003). |
| Perda de todos os admins | `LastAdminGuard` recusa a transição/trash que deixaria zero admin `active` (CV-007). |
| Vazamento de segredo de API | Secret de client m2m mostrado uma única vez, só hash persistido (AC-001); revogação é imediata (AC-003). |
| Upload malicioso disfarçado de imagem | MIME validado pelo conteúdo (não extensão); toda imagem é reconvertida (GD → WebP) antes de gravar — nunca o arquivo cru chega ao storage público. |
| Indisponibilidade do rate limiter (Redis fora) | Fail-open documentado — API continua respondendo sem limite temporário, em vez de ficar fora do ar (RL-002). |
| Segredo de infraestrutura versionado | `.env`/chaves nunca entram no repositório; produção usa sealed-secrets, gerado localmente por `make setup`. |
