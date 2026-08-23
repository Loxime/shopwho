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

Compte admin HTTP Basic local : `admin` / `shopwho-dev`.

> Ne jamais utiliser ces identifiants en production. Les secrets de production devront être placés dans `.env.local` ou injectés par l'environnement.
