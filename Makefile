.PHONY: build install shell validate lint test test-coverage audit ci fetch-up fetch-down live-check

DOCKER_COMPOSE ?= docker compose
PHP = $(DOCKER_COMPOSE) run --rm php

build:
	$(DOCKER_COMPOSE) build

install: build
	$(PHP) composer install

shell:
	$(DOCKER_COMPOSE) run --rm php bash

validate:
	$(PHP) composer validate --strict

lint:
	$(PHP) composer lint

test:
	$(PHP) composer test

test-coverage:
	$(PHP) composer test:coverage

audit:
	$(PHP) composer audit

ci:
	$(PHP) composer ci

fetch-up:
	$(DOCKER_COMPOSE) --profile fetch up -d --wait node flaresolverr

fetch-down:
	$(DOCKER_COMPOSE) --profile fetch down

live-check: fetch-up
	$(DOCKER_COMPOSE) run --rm php php tools/live-check.php $(URL)