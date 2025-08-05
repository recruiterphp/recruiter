.PHONY: build up down install update composer-validate test test-long phpstan rector fix-cs shell logs clean

# Build the Docker image
build:
	docker compose build

# Start the services
up:
	docker compose up -d

# Stop the services
down:
	docker compose down

# Install dependencies
install:
	docker compose run --rm php composer install

# Update dependencies
update:
	docker compose run --rm php composer update

composer-validate:
	docker compose run --rm php composer validate --strict

# Run all tests except the long ones
test: up
	docker compose exec php vendor/bin/phpunit --exclude-group=long

# Run long tests specifically
test-long: up
	docker compose exec php vendor/bin/phpunit --group=long

phpstan: up
	docker compose exec php vendor/bin/phpstan --memory-limit=2G

rector: up
	docker compose exec php vendor/bin/rector

fix-cs: up
	docker compose exec php vendor/bin/php-cs-fixer fix -v

# Open a shell in the PHP container
shell:
	docker compose exec php bash

# View logs
logs:
	docker compose logs -f php

# Clean up containers and volumes
clean:
	docker compose down -v
	docker compose rm -f
