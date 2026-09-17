.RECIPEPREFIX := >

DEPLOY_ENV ?= local

ifeq ($(DEPLOY_ENV),sandbox)
DC := docker compose --env-file .env.sandbox.local -f compose.sandbox.yaml
else ifeq ($(DEPLOY_ENV),prod)
DC := docker compose --env-file .env.prod.local -f compose.prod.yaml
else ifeq ($(DEPLOY_ENV),local)
DC := docker compose
else
$(error DEPLOY_ENV must be local, sandbox or prod)
endif

APP := $(DC) exec -T app
DB := $(DC) exec -T db
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
CONTAINER_FILE ?= /tmp/catalogue-demo.xlsx
DRY_RUN ?= 1
APPLY ?= 0
CONFIRM ?=

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
	import-catalog \
	catalog-stage \
	catalog-sync \
	catalog-reset

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
> @echo "  make catalog-sync DEPLOY_ENV=sandbox FILE=/tmp/catalogue-demo.xlsx"
> @echo "      Synchronise réellement catégories + produits sur sandbox."
> @echo
> @echo "  make catalog-sync DEPLOY_ENV=prod FILE=/tmp/catalogue-demo.xlsx CONFIRM=PROD"
> @echo "      Synchronise réellement le catalogue en production."
> @echo
> @echo "  make catalog-reset DEPLOY_ENV=sandbox FILE=/tmp/catalogue-demo.xlsx"
> @echo "      Prévisualise la suppression des produits importés."
> @echo
> @echo "  make catalog-reset DEPLOY_ENV=sandbox FILE=/tmp/catalogue-demo.xlsx APPLY=1"
> @echo "      Supprime les produits importés et remet product.id à 1 si la table est vide."
> @echo
> @echo "  make catalog-reset DEPLOY_ENV=prod FILE=/tmp/catalogue-demo.xlsx APPLY=1 CONFIRM=PROD"
> @echo "      Même remise à zéro en production avec confirmation explicite."
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
catalog-stage:
> @test -f "$(FILE)" || { \
> 	echo "ERROR: fichier introuvable: $(FILE)"; \
> 	exit 1; \
> }
> @app_id="$$( $(DC) ps -q app )"; \
> test -n "$$app_id" || { \
> 	echo "ERROR: conteneur app introuvable pour DEPLOY_ENV=$(DEPLOY_ENV)"; \
> 	exit 1; \
> }; \
> docker cp "$(FILE)" "$$app_id:$(CONTAINER_FILE)" >/dev/null; \
> echo "Catalogue copié dans le conteneur: $(CONTAINER_FILE)"

catalog-sync: catalog-stage
> @if [ "$(DEPLOY_ENV)" = "prod" ] && [ "$(CONFIRM)" != "PROD" ]; then \
> 	echo "ERROR: la production exige CONFIRM=PROD"; \
> 	exit 1; \
> fi
> @echo "Import réel des catégories..."
> @$(APP) php bin/console app:import:data \
> 	categories \
> 	"$(CONTAINER_FILE)"
> @echo
> @echo "Validation des produits..."
> @$(APP) php bin/console app:import:data \
> 	products \
> 	"$(CONTAINER_FILE)" \
> 	--dry-run
> @echo
> @echo "Import réel des produits..."
> @$(APP) php bin/console app:import:data \
> 	products \
> 	"$(CONTAINER_FILE)"

catalog-reset: catalog-stage
> @echo "Prévisualisation de la suppression..."
> @$(APP) php bin/console app:data:reset \
> 	products \
> 	"$(CONTAINER_FILE)" \
> 	--dry-run
> @if [ "$(APPLY)" != "1" ]; then \
> 	echo; \
> 	echo "Dry-run uniquement. Relancer avec APPLY=1 pour appliquer."; \
> 	exit 0; \
> fi; \
> if [ "$(DEPLOY_ENV)" = "prod" ] && [ "$(CONFIRM)" != "PROD" ]; then \
> 	echo "ERROR: la production exige CONFIRM=PROD"; \
> 	exit 1; \
> fi; \
> echo; \
> echo "Suppression réelle des produits importés..."; \
> $(APP) php bin/console app:data:reset \
> 	products \
> 	"$(CONTAINER_FILE)" \
> 	--apply; \
> remaining="$$( \
> 	$(DB) sh -lc \
> 	'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" -Atqc "SELECT COUNT(*) FROM product;"' \
> )"; \
> echo "Produits restants: $$remaining"; \
> if [ "$$remaining" = "0" ]; then \
> 	$(DB) sh -lc \
> 	'psql -v ON_ERROR_STOP=1 -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" -c "ALTER TABLE product ALTER COLUMN id RESTART WITH 1;"'; \
> 	echo "Compteur product.id remis à 1."; \
> else \
> 	echo "Compteur non modifié: la table product n'est pas vide."; \
> fi
