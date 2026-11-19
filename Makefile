SHELL := /bin/sh
COMPOSE := docker compose

.PHONY: help env up down reset logs ps lint smoke test shell db

help:
	@echo "Targets:"
	@echo "  make env     Create .env from .env.example if missing"
	@echo "  make up      Build and start the stack"
	@echo "  make down    Stop the stack (keeps data)"
	@echo "  make reset   Stop the stack and delete the database volume"
	@echo "  make logs    Follow logs"
	@echo "  make ps      Show container status"
	@echo "  make lint    Lint PHP files"
	@echo "  make smoke   Run smoke tests against the running stack"
	@echo "  make test    Start the stack and run lint + smoke tests"
	@echo "  make shell   Open a shell in the app container"
	@echo "  make db      Open psql in the database container"

env:
	@test -f .env || cp .env.example .env

up: env
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

reset:
	$(COMPOSE) down -v

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

lint: env
	sh ./scripts/lint-php.sh

smoke: env
	sh ./tests/smoke.sh

test: env up lint
	@set -a; . ./.env; set +a; sh ./scripts/wait-for-http.sh "http://localhost:$${APP_PORT:-8081}" 120; sh ./tests/smoke.sh

shell:
	$(COMPOSE) exec app sh

db:
	@set -a; . ./.env; set +a; $(COMPOSE) exec db psql -U "$${DB_USER:-yashu}" -d "$${DB_NAME:-paintings_hall-db}"
