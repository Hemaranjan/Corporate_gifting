.PHONY: install start stop restart logs shell status reseed

install: ## Full install — build containers + install Bagisto + seed data
	docker compose up -d --build
	@echo "Waiting for MySQL to be ready..."
	@sleep 20
	docker compose exec app bash /var/www/scripts/install.sh

start: ## Start all containers
	docker compose up -d
	@echo "Platform running at http://localhost:8080"

stop: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

logs: ## Tail all container logs
	docker compose logs -f

logs-app: ## Tail app (PHP) logs
	docker compose logs -f app

shell: ## Open bash shell in app container
	docker compose exec app bash

mysql: ## Open MySQL shell
	docker compose exec mysql mysql -u bagisto -pbagisto123 bagisto_corporate

status: ## Show container status
	docker compose ps

reseed: ## Re-run only the sample data seeder
	docker compose exec app php /var/www/scripts/seed_data.php

artisan: ## Run artisan command — usage: make artisan CMD="route:list"
	docker compose exec app php artisan $(CMD)

cache-clear: ## Clear all Laravel caches
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan view:clear
	docker compose exec app php artisan route:clear
