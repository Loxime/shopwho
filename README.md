# Shopwho MVP

Application e-commerce Symfony dockerisée conçue comme environnement de collecte de données comportementales pour un futur projet de data science.

## Démarrage local

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:seed-products
```

Application : http://localhost:8080

Administration produits : http://localhost:8080/admin/products

Création d'un administrateur local :

```bash
docker compose exec app php bin/console app:create-admin admin@shopwho.local
```

Le mot de passe est demandé de manière interactive et stocké uniquement sous forme hashée en base.

## Données pour la data science

Les événements de navigation sont pseudonymisés et stockés dans `tracking_event`.

Export CSV :

```bash
docker compose exec app php bin/console app:tracking:export-csv --output=exports/tracking.csv
```

Le dossier `exports/` et les fichiers CSV/Parquet sont ignorés par Git : **aucun dataset réel ne doit être versionné**.


## Consentement de mesure

Le tracking comportemental n'est activé que lorsque le visiteur accepte la mesure d'audience via le bandeau prévu à cet effet. Ce mécanisme technique devra être relu avec la politique de confidentialité et les règles applicables avant la mise en production.
