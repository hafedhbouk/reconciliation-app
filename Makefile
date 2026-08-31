.PHONY: help install setup dev test lint build docker-build docker-up docker-down clean

# Default target
help: ## Show this help message
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install all dependencies
	composer install --no-interaction --prefer-dist
	npm install

setup: install ## Full application setup
	cp -n .env.example .env || true
	php artisan key:generate
	php artisan migrate --force
	php artisan db:seed --force
	php artisan storage:link --force

dev: ## Start development server
	composer run dev

test: ## Run tests
	php artisan test

test-coverage: ## Run tests with coverage
	php artisan test --coverage --min=80

lint: ## Check code style
	vendor/bin/pint --test --verbose

lint-fix: ## Fix code style
	vendor/bin/pint --verbose

build: ## Build frontend assets
	npm run build

docker-build: ## Build Docker images
	docker compose build --no-cache

docker-up: ## Start Docker containers
	docker compose up -d

docker-down: ## Stop Docker containers
	docker compose down

docker-dev-up: ## Start Docker containers (development)
	docker compose -f docker-compose.dev.yml up -d

docker-dev-down: ## Stop Docker containers (development)
	docker compose -f docker-compose.dev.yml down

docker-logs: ## View Docker logs
	docker compose logs -f

docker-exec: ## Execute command in Docker container
	docker compose exec app $(cmd)

clean: ## Clean up generated files
	rm -rf vendor/
	rm -rf node_modules/
	rm -rf public/build/
	rm -rf bootstrap/cache/*.php
	rm -rf storage/framework/cache/data/*
	rm -rf storage/framework/sessions/*
	rm -rf storage/framework/views/*
	rm -rf storage/logs/*.log

optimize: ## Optimize for production
	composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache

ci: lint test build docker-build ## Run all CI checks locally
	@echo "All CI checks passed!"
