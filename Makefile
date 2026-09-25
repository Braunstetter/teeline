# Host user (override with `make up USER_ID=1005` or an exported variable)
export USER_ID  ?= $(shell id -u)
export GROUP_ID ?= $(shell id -g)

# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
NPM      = $(DOCKER_COMP) run --rm node npm
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test npm dev assets \
                lint lint-composer lint-twig lint-yaml lint-container ecs ecs-fix phpstan psalm rector rector-fix \
                code-analysis code-check full-check

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: ## Start tests with phpunit, pass the parameter "c=" to add options to phpunit, example: make test c="--group e2e --stop-on-failure"
	@$(eval c ?=)
	@# There is no suite yet. Saying so beats `make full-check` dying on a missing binary.
	@if [ -f bin/phpunit ]; then \
		$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c); \
	else \
		echo "No test suite yet -- install symfony/test-pack to get one."; \
	fi


## —— Quality 🔍 ———————————————————————————————————————————————————————————————
lint-twig: ## Lint Twig templates
	@$(SYMFONY) lint:twig templates

lint-yaml: ## Lint YAML configs and translations
	@$(SYMFONY) lint:yaml config translations

lint-container: ## Check that services are wired with compatible types
	@$(SYMFONY) lint:container

lint-composer: ## Check that composer.json and composer.lock agree
	@$(COMPOSER) validate --strict

lint: lint-composer lint-twig lint-yaml lint-container ## Run all linters

ecs: ## Run ECS (check only)
	@$(PHP) vendor/bin/ecs check

ecs-fix: ## Run ECS and apply fixes
	@# Twice: breaking a long line leaves indentation for the next pass to settle.
	@$(PHP) vendor/bin/ecs check --fix
	@$(PHP) vendor/bin/ecs check --fix

phpstan: ## Run PHPStan
	@$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

psalm: ## Run Psalm, then its taint analysis
	@$(PHP) vendor/bin/psalm --no-progress
	@$(PHP) vendor/bin/psalm --no-progress --show-info=true --taint-analysis

rector: ## Run Rector (dry-run)
	@$(PHP) vendor/bin/rector --dry-run

verify-prod-image: ## Build the prod stage; fails if Imagick's coder modules didn't make it in
	@docker build --target frankenphp_prod_verify -t teeline-prod-verify . --quiet
	@docker rmi teeline-prod-verify --force > /dev/null

rector-fix: ## Run Rector and apply changes
	@$(PHP) vendor/bin/rector

code-analysis: phpstan psalm ## Run all static analysis tools

code-check: lint ecs rector code-analysis ## Run every quality gate, no fixes

full-check: code-check test ## Run the quality gates and the tests

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

## —— Frontend 📦 ——————————————————————————————————————————————————————————————
npm: ## Run npm, pass the parameter "c=" to run a given command, example: make npm c='install'
	@$(eval c ?=)
	@$(NPM) $(c)

node_modules: package.json ## Install npm packages according to package-lock.json
	@$(NPM) install

dev: node_modules ## Start the vite dev server with HMR on https://localhost:5173 (Ctrl+C stops it)
	@$(DOCKER_COMP) run --rm --service-ports node npm run dev

assets: c=run build ## Build the assets into public/build
assets: node_modules npm
