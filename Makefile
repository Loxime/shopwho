.RECIPEPREFIX := >

DC := docker compose
APP := $(DC) exec -T app
TEST_APP := $(DC) exec -T -e TEST_TOKEN=check app

.PHONY: \
	check \
	check-runtime \
	check-git \
	check-compose \
	check-composer \
	check-php \
	check-symfony \
	check-tests

check:
> @echo "========================================"
> @echo " Shopwho - pre-PR validation"
> @echo "========================================"
> @$(MAKE) --no-print-directory check-runtime
> @$(MAKE) --no-print-directory check-git
> @$(MAKE) --no-print-directory check-compose
> @$(MAKE) --no-print-directory check-composer
> @$(MAKE) --no-print-directory check-php
> @$(MAKE) --no-print-directory check-symfony
> @$(MAKE) --no-print-directory check-tests
> @echo
> @echo "========================================"
> @echo " CHECK PASSED - branch ready for PR"
> @echo "========================================"

check-runtime:
> @echo
> @echo "[1/7] Docker runtime"
> @$(DC) up -d db app
> @$(DC) ps db app

check-git:
> @echo
> @echo "[2/7] Git integrity"
> @git restore --worktree -- config/reference.php 2>/dev/null || true
> @git diff --check
> @test -z "$$(git status --porcelain)" || { \
> 	echo; \
> 	echo "ERROR: working tree is not clean:"; \
> 	git status --short; \
> 	exit 1; \
> }
> @echo "Git working tree: clean"

check-compose:
> @echo
> @echo "[3/7] Docker Compose configuration"
> @$(DC) config --quiet
> @SHOPWHO_IMAGE_TAG=check \
> APP_SECRET=check-only-not-a-secret \
> DATABASE_URL='postgresql://shopwho:check@db:5432/shopwho?serverVersion=16&charset=utf8' \
> POSTGRES_DB=shopwho \
> POSTGRES_USER=shopwho \
> POSTGRES_PASSWORD=check-only \
> $(DC) --env-file .env.prod.example -f compose.prod.yaml config --quiet
> @echo "Docker Compose: valid"

check-composer:
> @echo
> @echo "[4/7] Composer"
> @$(APP) composer validate --strict
> @$(APP) composer audit --no-interaction

check-php:
> @echo
> @echo "[5/7] PHP syntax"
> @$(APP) sh -lc \
> 	"find src public migrations tests \
> 	-type f -name '*.php' -print0 \
> 	| xargs -0 -n1 php -l"

check-symfony:
> @echo
> @echo "[6/7] Symfony configuration"
> @$(APP) php bin/console lint:container
> @$(APP) php bin/console lint:twig templates
> @$(APP) php bin/console lint:yaml config

check-tests:
> @echo
> @echo "[7/7] Isolated database + Doctrine + PHPUnit"
> @set -eu; \
> cleanup() { \
> 	$(TEST_APP) php bin/console doctrine:database:drop \
> 		--env=test \
> 		--force \
> 		--if-exists \
> 		>/dev/null 2>&1 || true; \
> }; \
> trap cleanup EXIT INT TERM; \
> cleanup; \
> echo "Creating isolated database: shopwho_testcheck"; \
> $(TEST_APP) php bin/console doctrine:database:create \
> 		--env=test; \
> $(TEST_APP) php bin/console doctrine:migrations:migrate \
> 		--env=test \
> 		--no-interaction; \
> $(TEST_APP) php bin/console doctrine:migrations:status \
> 		--env=test; \
> $(TEST_APP) php bin/console doctrine:schema:validate \
> 		--env=test; \
> $(TEST_APP) php bin/phpunit
