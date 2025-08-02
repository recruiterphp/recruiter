.PHONY: build up down test phpstan rector fix-cs install update shell logs clean

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

# Run all tests except the long ones
test: up
	docker compose exec php vendor/bin/phpunit

phpstan: up
	docker compose exec php vendor/bin/phpstan

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
