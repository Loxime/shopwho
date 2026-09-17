.RECIPEPREFIX := >

DC := docker compose
APP := $(DC) exec -T app
TEST_APP := $(DC) exec -T -e TEST_TOKEN=check app

.PHONY: \
	check \
	check-runtime \
	check-git \
	check-final-git \
	check-compose \
	check-composer \
	check-php \
	check-symfony \
	check-tests

check:
> @set -eu; \
> echo "========================================"; \
> echo " Shopwho - pre-PR validation"; \
> echo "========================================"; \
> $(MAKE) --no-print-directory check-git; \
> cleanup_reference() { \
> 	git restore --worktree -- config/reference.php 2>/dev/null || true; \
> }; \
> trap cleanup_reference EXIT INT TERM; \
> $(MAKE) --no-print-directory check-runtime; \
> $(MAKE) --no-print-directory check-compose; \
> $(MAKE) --no-print-directory check-composer; \
> $(MAKE) --no-print-directory check-php; \
> $(MAKE) --no-print-directory check-symfony; \
> $(MAKE) --no-print-directory check-tests; \
> cleanup_reference; \
> $(MAKE) --no-print-directory check-final-git; \
> trap - EXIT INT TERM; \
> echo; \
> echo "========================================"; \
> echo " CHECK PASSED - branch ready for PR"; \
> echo "========================================"

check-git:
> @echo
> @echo "[1/8] Initial Git integrity"
> @git diff --check
> @test -z "$$(git status --porcelain)" || { \
> 	echo; \
> 	echo "ERROR: working tree is not clean:"; \
> 	git status --short; \
> 	exit 1; \
> }
> @echo "Git working tree: clean"

check-runtime:
> @echo
> @echo "[2/8] Docker runtime"
> @$(DC) up -d db app
> @$(DC) ps db app

check-compose:
> @echo
> @echo "[3/8] Docker Compose configuration"
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
> @echo "[4/8] Composer"
> @$(APP) composer validate --strict
> @$(APP) composer audit --no-interaction

check-php:
> @echo
> @echo "[5/8] PHP syntax"
> @$(APP) sh -lc \
> 	"find src public migrations tests \
> 	-type f -name '*.php' -print0 \
> 	| xargs -0 -n1 php -l"

check-symfony:
> @echo
> @echo "[6/8] Symfony configuration"
> @$(APP) php bin/console lint:container
> @$(APP) php bin/console lint:twig templates
> @$(APP) php bin/console lint:yaml config

check-tests:
> @echo
> @echo "[7/8] Isolated database + Doctrine + PHPUnit"
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

check-final-git:
> @echo
> @echo "[8/8] Final Git integrity"
> @git diff --check
> @test -z "$$(git status --porcelain)" || { \
> 	echo; \
> 	echo "ERROR: checks modified the working tree:"; \
> 	git status --short; \
> 	exit 1; \
> }
> @echo "Git working tree after checks: clean"

# === Presentation workflows ===

EXPORT_DIR ?= exports
EXPORT_CSV ?= $(EXPORT_DIR)/tracking.csv
EXPORT_MANIFEST ?= $(EXPORT_DIR)/tracking.manifest.json

SINCE ?=
UNTIL ?=
EVENTS ?=

FILE ?= var/import/catalogue-demo.xlsx
DRY_RUN ?= 1

EXPORT_FILTERS = \
	$(if $(strip $(SINCE)),--since="$(SINCE)",) \
	$(if $(strip $(UNTIL)),--until="$(UNTIL)",) \
	$(foreach event,$(EVENTS),--event=$(event))

DRY_RUN_FLAG = \
	$(if $(filter 1 true yes,$(DRY_RUN)),--dry-run,)

.PHONY: \
	help \
	up \
	down \
	logs \
	export-tracking \
	import-catalog

help:
> @echo "ShopWho - commandes utiles"
> @echo
> @echo "  make up"
> @echo "      Lance l'environnement Docker."
> @echo
> @echo "  make logs"
> @echo "      Affiche les logs app + nginx."
> @echo
> @echo "  make export-tracking"
> @echo "      Exporte le tracking complet."
> @echo
> @echo "  make export-tracking SINCE=2026-09-01 UNTIL=2026-09-18"
> @echo "      Exporte une fenêtre temporelle."
> @echo
> @echo "  make export-tracking EVENTS='PRODUCT_VIEW PURCHASE'"
> @echo "      Filtre certains événements."
> @echo
> @echo "  make import-catalog FILE=var/import/catalogue-demo.xlsx"
> @echo "      Validation catalogue uniquement (dry-run par défaut)."
> @echo
> @echo "  make import-catalog FILE=var/import/catalogue-demo.xlsx DRY_RUN=0"
> @echo "      Importe catégories puis produits."
> @echo
> @echo "  make check"
> @echo "      Validation complète avant PR."

up:
> @$(DC) up -d

down:
> @$(DC) down

logs:
> @$(DC) logs -f --tail=100 app nginx

export-tracking:
> @mkdir -p "$(EXPORT_DIR)"
> @$(APP) php bin/console app:tracking:export-csv \
> 	--output="$(EXPORT_CSV)" \
> 	--manifest="$(EXPORT_MANIFEST)" \
> 	$(EXPORT_FILTERS)

import-catalog:
> @test -f "$(FILE)" || { \
> 	echo "ERROR: fichier introuvable: $(FILE)"; \
> 	exit 1; \
> }
> @echo "Import catégories..."
> @$(APP) php bin/console app:import:data \
> 	categories \
> 	"$(FILE)" \
> 	$(DRY_RUN_FLAG)
> @echo
> @echo "Import produits..."
> @$(APP) php bin/console app:import:data \
> 	products \
> 	"$(FILE)" \
> 	$(DRY_RUN_FLAG)

