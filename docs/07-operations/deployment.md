# Deploy

## Ambiente

Produção hospedada em **`autoschedule.justgui.dev`**.

## Pipeline

```text
push/PR -> backend/frontend (estática + lint) -> phpunit -> e2e -> load-test -> build-and-push (só main)
```

`phpunit`, `e2e` (Playwright) e `load-test` (k6) sobem contra Postgres/Redis reais via services do
Actions, não mock. `build-and-push` publica `ghcr.io/justgu1/autoschedule-{backend,nginx}`.

## GitOps

Monorepo, sem repositório de manifest separado. ArgoCD (`Application autoschedule`, `prune`+
`selfHeal` automáticos) aponta direto pra `infra/k8s` deste repositório; `argocd-image-updater`
rastreia as duas imagens por digest.

```text
merge na main -> build-and-push -> image-updater detecta o digest novo -> ArgoCD sincroniza -> apps atualizado
```

Nenhum passo manual entre o merge e produção.

## Kubernetes

`infra/k8s`: manifests de `backend`, `worker`, `scheduler`, `frontend`, `ingressroute`, e
`sealed-secret` (segredo nunca em texto puro no repositório — sealed-secrets criptografa com a
chave pública do cluster, só o controller em produção consegue decifrar).

Worker e scheduler são a mesma imagem do backend, com outro comando — cada um com o próprio
Deployment, escala e reinicia sozinho via o orquestrador, sem precisar de supervisor de processo.

## Local (dev)

Docker Compose — ver [`../../README.md`](../../README.md) pro quick start. `docker-compose.override.yml`
nunca sobe em produção (`docker compose -f docker-compose.yaml up -d --build` ignora o override).
