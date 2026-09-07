.PHONY: setup up down restart build logs ps test migrate rollback seed keys load-test static-analysis lint lint-fix rector rector-fix e2e e2e-setup

setup:
	@if [ -f .env ]; then \
		echo ""; \
		echo "WARNING: O ambiente já foi configurado."; \
		echo ""; \
		echo "Executar 'make setup' novamente irá:"; \
		echo "  - substituir o arquivo .env"; \
		echo "  - gerar novas credenciais"; \
		echo "  - remover os volumes Docker"; \
		echo "  - apagar os dados locais do PostgreSQL"; \
		echo "  - apagar os dados locais do Redis"; \
		echo "  - apagar os dados locais do MinIO"; \
		echo "  - recriar todo o ambiente"; \
		echo ""; \
		printf "Deseja continuar? Digite 'yes' para confirmar: "; \
		read confirmation; \
		if [ "$$confirmation" != "yes" ]; then \
			echo "Setup cancelado."; \
			exit 1; \
		fi; \
	fi

	@echo "==> Resetando ambiente..."
	@docker compose down -v --remove-orphans

	@echo "==> Criando .env..."
	@cp .env.example .env

	@echo "==> Gerando credenciais aleatórias..."
	@DB_PASSWORD=$$(openssl rand -hex 24); \
	DB_APP_PASSWORD=$$(openssl rand -hex 24); \
	MINIO_ROOT_PASSWORD=$$(openssl rand -hex 24); \
	sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$$DB_PASSWORD/" .env; \
	sed -i "s/^DB_APP_PASSWORD=.*/DB_APP_PASSWORD=$$DB_APP_PASSWORD/" .env; \
	sed -i "s/^MINIO_ROOT_PASSWORD=.*/MINIO_ROOT_PASSWORD=$$MINIO_ROOT_PASSWORD/" .env

	@$(MAKE) keys

	@echo "==> Instalando dependências do frontend..."
	@docker run --rm \
		-u "$$(id -u):$$(id -g)" \
		-v "$$(pwd)/frontend:/app" \
		-w /app \
		node:22-alpine \
		npm install

	@echo "==> Instalando dependências do backend..."
	@docker run --rm \
		-u "$$(id -u):$$(id -g)" \
		-v "$$(pwd)/backend:/app" \
		-w /app \
		composer:2 \
		composer install --no-interaction --prefer-dist

	@echo "==> Construindo as imagens..."
	@docker compose build

	@echo "==> Subindo a aplicação..."
	@docker compose up -d

	@echo ""
	@echo "========================================"
	@echo " Ambiente configurado e iniciado!"
	@echo "========================================"
	@echo ""
	@docker compose ps

up:
	@docker compose up -d

down:
	@docker compose down

restart:
	@docker compose down
	@docker compose up -d

build:
	@docker compose build

logs:
	@watch -n 2 'docker compose logs --tail=100'

ps:
	@watch -n 2 'docker compose ps'

test:
	@docker compose exec backend vendor/bin/phpunit

static-analysis:
	@docker compose exec backend vendor/bin/phpstan analyse --no-progress

lint:
	@docker compose exec backend vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix:
	@docker compose exec backend vendor/bin/php-cs-fixer fix

rector:
	@docker compose exec backend vendor/bin/rector process --dry-run

rector-fix:
	@docker compose exec backend vendor/bin/rector process

migrate:
	@docker compose exec backend php bin/migrate.php

rollback:
	@docker compose exec backend php bin/migrate.php --rollback

seed:
	@docker compose exec backend php bin/seed.php

load-test:
	@docker compose run --rm k6 run /scripts/api-load-test.js

e2e-setup:
	@docker compose build nginx
	@RATE_LIMIT_AUTH_MAX=1000 docker compose up -d nginx mailpit minio backend worker
	@echo "==> instalando dependências do backend (imagem é --no-dev, bind-mount local some com o vendor/)..."
	@docker compose exec backend composer install --no-interaction --prefer-dist
	@echo "==> aplicando migrations e seeders (banco pode estar vazio -- ambiente novo/CI)..."
	@docker compose exec backend php bin/migrate.php
	@docker compose exec backend php bin/seed.php
	@echo "==> criando o bucket do MinIO (upload de foto de concessionária precisa dele)..."
	@MINIO_PORT=$$(docker compose port minio 9000 | cut -d: -f2); \
	MINIO_USER=$$(docker compose exec minio printenv MINIO_ROOT_USER); \
	MINIO_PASS=$$(docker compose exec minio printenv MINIO_ROOT_PASSWORD); \
	for i in $$(seq 1 20); do curl -sf http://127.0.0.1:$$MINIO_PORT/minio/health/live && break; sleep 1; done; \
	docker run --rm --network host --entrypoint sh minio/mc -c "\
		mc alias set local http://127.0.0.1:$$MINIO_PORT $$MINIO_USER $$MINIO_PASS && \
		mc mb --ignore-existing local/autoschedule && \
		mc anonymous set download local/autoschedule"
	@docker compose run --rm --no-deps e2e npm ci

# `--no-deps`: sem isso, `docker compose run` reconcilia as dependências do
# serviço `e2e` a cada chamada -- e recriar `backend` sozinho (sem recriar
# `nginx` junto) deixa o `nginx` com a resolução de DNS antiga em cache,
# apontando pro IP morto do container anterior (502 Bad Gateway em toda
# rota da API, apesar do backend novo estar de pé e saudável).
e2e: e2e-setup
	@RATE_LIMIT_AUTH_MAX=1000 docker compose run --rm --no-deps e2e npx playwright test
	@echo "==> restaurando rate limit padrão do backend..."
	@docker compose up -d backend

keys:
	@mkdir -p backend/storage/keys
	@if [ -f backend/storage/keys/oauth-private.pem ]; then \
		echo "==> Chaves RSA do JWT já existem em backend/storage/keys/ -- nada a fazer."; \
	else \
		echo "==> Gerando chaves RSA do JWT..."; \
		openssl genrsa -out backend/storage/keys/oauth-private.pem 2048 2>/dev/null; \
		openssl rsa -in backend/storage/keys/oauth-private.pem -pubout -out backend/storage/keys/oauth-public.pem 2>/dev/null; \
		chmod 644 backend/storage/keys/oauth-private.pem; \
	fi