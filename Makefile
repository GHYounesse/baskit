.PHONY: up down logs test e2e image prod-config

# Local development containers (PostgreSQL + Vite). See compose.dev.yaml.
up:
	docker compose -f compose.dev.yaml up -d

down:
	docker compose -f compose.dev.yaml down

logs:
	docker compose -f compose.dev.yaml logs -f

# Pest tests (SQLite in memory, no container needed).
test:
	php artisan test

e2e:
	npm run test:e2e

# Build the production image locally.
image:
	docker build -t ghcr.io/ghyounesse/baskit:local .

# Validate the production compose file (needs a .env next to it).
prod-config:
	docker compose config -q && echo "compose.yaml OK"
