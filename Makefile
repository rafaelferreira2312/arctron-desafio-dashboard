COMPOSE = docker compose
PHP = $(COMPOSE) exec php

.PHONY: up down logs test test-unit seed shell reset front-test woo-orders

up: ## Sobe tudo (API, worker, frontend, Postgres, RabbitMQ e WooCommerce)
	$(COMPOSE) up -d --build
	@echo ""
	@echo "Dashboard:   http://localhost:5173"
	@echo "API:         http://localhost:8000/health"
	@echo "WooCommerce: http://localhost:8080 (admin/admin) - o setup leva alguns minutos na primeira vez"
	@echo "RabbitMQ:    http://localhost:15672 (guest/guest)"

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f php worker woocommerce-setup

seed: ## Gera compras fictícias para o dashboard
	$(PHP) bin/console app:seed-purchases --count=200 --days=90

test: ## Testes unitários e funcionais do backend (usa o banco purchases_test)
	$(PHP) bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --env=test --no-interaction
	$(PHP) vendor/bin/phpunit

test-unit:
	$(PHP) vendor/bin/phpunit --testsuite unit

front-test:
	$(COMPOSE) exec frontend npm test

woo-orders: ## Cria mais 3 pedidos no WooCommerce (disparam o webhook)
	$(COMPOSE) run --rm --entrypoint wp woocommerce-setup --path=/var/www/html eval-file /setup/seed-orders.php

shell:
	$(PHP) sh

reset: ## Apaga volumes e começa do zero
	$(COMPOSE) down -v
