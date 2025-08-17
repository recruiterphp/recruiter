# Build the Docker image
.PHONY: build
build:
	docker compose build

# Start the services
.PHONY: up
up:
	docker compose up -d

# Stop the services
.PHONY: down
down:
	docker compose down

# Install dependencies
.PHONY: install
install:
	docker compose run --rm php composer install

# Update dependencies
.PHONY: update
update:
	docker compose run --rm php composer update

.PHONY: composer-validate
composer-validate:
	docker compose run --rm php composer validate --strict

.PHONY: test
test: test-unit test-integration test-acceptance-short

.PHONY: test-unit
test-unit: up
	@echo 'Running unit tests'
	@docker compose exec php vendor/bin/phpunit --testsuite=unit

.PHONY: test-integration
test-integration: up
	@echo 'Running integration tests'
	@docker compose exec php vendor/bin/phpunit --testsuite=integration

.PHONY: test-acceptance-short
test-acceptance-short: up
	@echo 'Running short acceptance tests'
	@docker compose exec php vendor/bin/phpunit --testsuite=acceptance --exclude-group=long

.PHONY: test-acceptance-long
test-acceptance-long: up
	@echo 'Running long acceptance tests'
	@docker compose exec php vendor/bin/phpunit --testsuite=acceptance --group=long

.PHONY: phpstan
phpstan: up
	docker compose exec php vendor/bin/phpstan --memory-limit=2G analyse $(args)

.PHONY: rector
rector: up
	docker compose exec php vendor/bin/rector

.PHONY: fix-cs
fix-cs: up
	docker compose exec php vendor/bin/php-cs-fixer fix -v

# Open a shell in the PHP container
.PHONY: shell
shell:
	docker compose exec php bash

# View logs
.PHONY: logs
logs:
	docker compose logs -f php

# Clean up containers and volumes
.PHONY: clean
clean:
	docker compose down -v
	docker compose rm -f
