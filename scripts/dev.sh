#!/bin/bash
set -e

# Helper script for common development tasks

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Commands
install() {
    print_info "Installing dependencies..."

    print_info "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist

    print_info "Installing NPM dependencies..."
    npm install

    print_success "Dependencies installed successfully!"
}

setup() {
    print_info "Setting up the application..."

    install

    if [ ! -f .env ]; then
        print_info "Copying .env.example to .env..."
        cp .env.example .env
    fi

    print_info "Generating application key..."
    php artisan key:generate

    print_info "Running database migrations..."
    php artisan migrate --force

    print_info "Seeding database..."
    php artisan db:seed --force

    print_info "Creating storage link..."
    php artisan storage:link --force

    print_success "Application setup complete!"
}

dev() {
    print_info "Starting development server..."
    composer run dev
}

test() {
    print_info "Running tests..."
    php artisan test "$@"
}

test_coverage() {
    print_info "Running tests with coverage..."
    php artisan test --coverage --min=80 "$@"
}

lint() {
    print_info "Running Laravel Pint..."
    vendor/bin/pint --test --verbose
}

lint_fix() {
    print_info "Fixing code style..."
    vendor/bin/pint --verbose
}

build() {
    print_info "Building frontend assets..."
    npm run build
    print_success "Build complete!"
}

docker_build() {
    print_info "Building Docker images..."
    docker compose build --no-cache
    print_success "Docker images built!"
}

docker_up() {
    print_info "Starting Docker containers..."
    docker compose up -d
    print_success "Docker containers started!"
}

docker_down() {
    print_info "Stopping Docker containers..."
    docker compose down
    print_success "Docker containers stopped!"
}

docker_logs() {
    docker compose logs -f "$@"
}

docker_exec() {
    docker compose exec app "$@"
}

clean() {
    print_info "Cleaning up..."

    rm -rf vendor/
    rm -rf node_modules/
    rm -rf public/build/
    rm -rf bootstrap/cache/*.php
    rm -rf storage/framework/cache/data/*
    rm -rf storage/framework/sessions/*
    rm -rf storage/framework/views/*
    rm -rf storage/logs/*.log

    print_success "Cleanup complete!"
}

optimize() {
    print_info "Optimizing for production..."

    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    print_success "Optimization complete!"
}

# Main command handler
case "$1" in
    install)
        install
        ;;
    setup)
        setup
        ;;
    dev)
        dev
        ;;
    test)
        shift
        test "$@"
        ;;
    test-coverage)
        shift
        test_coverage "$@"
        ;;
    lint)
        lint
        ;;
    lint-fix)
        lint_fix
        ;;
    build)
        build
        ;;
    docker-build)
        docker_build
        ;;
    docker-up)
        docker_up
        ;;
    docker-down)
        docker_down
        ;;
    docker-logs)
        shift
        docker_logs "$@"
        ;;
    docker-exec)
        shift
        docker_exec "$@"
        ;;
    clean)
        clean
        ;;
    optimize)
        optimize
        ;;
    *)
        echo "Usage: ./scripts/dev.sh [command]"
        echo ""
        echo "Available commands:"
        echo "  install         Install all dependencies"
        echo "  setup           Full application setup"
        echo "  dev             Start development server"
        echo "  test            Run tests"
        echo "  test-coverage   Run tests with coverage"
        echo "  lint            Check code style"
        echo "  lint-fix        Fix code style"
        echo "  build           Build frontend assets"
        echo "  docker-build    Build Docker images"
        echo "  docker-up       Start Docker containers"
        echo "  docker-down     Stop Docker containers"
        echo "  docker-logs     View Docker logs"
        echo "  docker-exec     Execute command in Docker container"
        echo "  clean           Clean up generated files"
        echo "  optimize        Optimize for production"
        exit 1
        ;;
esac
