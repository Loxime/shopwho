# Déploiement Docker de production

Cette configuration prépare les conteneurs immuables de Shopwho. La
configuration du VPS, du Nginx hôte, de TLS/Certbot, du DNS et de la CI de
déploiement reste volontairement hors de ce périmètre.

## Architecture

```text
Internet
  -> Nginx hôte :443 (HTTPS)
  -> 127.0.0.1:3030
  -> Nginx Docker :80
  -> PHP-FPM (service app :9000, non publié)
  -> PostgreSQL (service db :5432, non publié)
```

Le Nginx hôte devra remplacer, et non simplement relayer, les en-têtes
`X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port` et
`X-Forwarded-Proto`. Le Nginx Docker les transmet à PHP-FPM. Symfony ne fait
confiance qu'au proxy directement connecté (`REMOTE_ADDR`, ainsi qu'à
`127.0.0.1`) : aucune plage publique n'est approuvée.

## Variables requises

Copier `.env.prod.example` vers un fichier non versionné, par exemple
`.env.prod.local`, puis renseigner :

- `SHOPWHO_IMAGE_TAG` : tag immuable commun aux images applicative et web ;
- `APP_SECRET` : secret Symfony aléatoire et propre à la production ;
- `DATABASE_URL` : URL Doctrine complète vers `db:5432` ;
- `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` : initialisation de la
  base PostgreSQL.

`SHOPWHO_HTTP_PORT` vaut `3030` par défaut. `DEFAULT_URI` est fixé à
`https://shopwho.falchero.fr` dans le compose de production. Ne jamais
committer le fichier local, des identifiants, des tokens ou des clés. Compose
interrompt explicitement sa validation si une variable obligatoire manque.

Toutes les commandes ci-dessous supposent :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml ...
```

## Construction et publication des images

Depuis la racine du dépôt :

```bash
docker build -f Dockerfile.prod \
  -t ghcr.io/loxime/shopwho-app:<tag-immuable> .

docker build -f docker/nginx/Dockerfile.prod \
  -t ghcr.io/loxime/shopwho-web:<tag-immuable> .
```

L'image `app` contient le code, les dépendances Composer sans dépendances de
développement et PHP-FPM. L'image `web` contient uniquement la configuration
Nginx et `public/`. Aucune des deux ne dépend d'un bind mount du dépôt.

## Premier démarrage et migrations

Démarrer PostgreSQL et attendre son healthcheck :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d db
```

Faire une sauvegarde PostgreSQL avant toute migration importante, puis lancer
explicitement les migrations avec l'image à déployer :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml run --rm app \
  php bin/console doctrine:migrations:migrate \
  --no-interaction --env=prod
```

Enfin, démarrer les services applicatifs :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d app nginx
```

Les migrations ne sont exécutées ni pendant le build, ni dans l'entrypoint,
ni dans un healthcheck.

## Premier administrateur

Aucun compte n'est créé automatiquement. Après le démarrage :

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml exec app \
  php bin/console app:create-admin <email>
```

Le mot de passe est saisi interactivement ; ne placer aucun identifiant dans
le dépôt ou dans l'image.

## Santé et exploitation

PostgreSQL est contrôlé avec `pg_isready`. Nginx expose `/healthz` à
l'intérieur de son conteneur et sur le port HTTP lié à la boucle locale. Cette
URL vérifie le serveur web sans imposer une requête métier ni un accès base.
PHP-FPM n'a pas de healthcheck artificiel : Nginx attend simplement que le
service `app` soit démarré.

Le cache, les logs et les sessions Symfony sont écrits dans `var/` dans la
couche inscriptible du conteneur `app`. Avec le stockage natif actuel, les
sessions résident sous `var/cache/prod/sessions`. Un remplacement du conteneur
efface donc sessions, paniers, cache et logs locaux. Les données PostgreSQL
restent dans le volume `shopwho-prod_postgres_data`. Un stockage de session et
une collecte de logs externes pourront être étudiés plus tard, sans ajouter
Redis dans cette étape.

## Retour arrière

Un rollback consiste à remettre `SHOPWHO_IMAGE_TAG` sur un tag immuable
précédemment validé, puis à recréer `app` et `nginx`. Les migrations de schéma
ne sont pas automatiquement annulées : leur compatibilité doit être évaluée
avant le déploiement, avec sauvegarde PostgreSQL disponible.
