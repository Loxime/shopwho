# Plan de déploiement futur — shopwho.falchero.fr

Le MVP local n'est pas encore destiné à être exposé tel quel sur Internet. Avant le premier déploiement public, une étape de durcissement sera réalisée.

## À faire avant production

- créer une configuration Docker de production sans bind mount du code ;
- passer `APP_ENV=prod` et désactiver le debug ;
- utiliser de vrais secrets hors Git ;
- remplacer l'authentification admin locale par une authentification adaptée à la production ;
- HTTPS obligatoire sur `shopwho.falchero.fr` ;
- ajouter une politique de collecte/consentement adaptée au tracking réellement utilisé ;
- définir durée de conservation et purge des événements ;
- sauvegarder PostgreSQL ;
- limiter/rate-limit les routes sensibles ;
- journaliser les erreurs sans stocker de données personnelles inutiles ;
- exécuter les migrations de manière contrôlée ;
- vérifier CI avant déploiement.

## Séparation des responsabilités

```text
VPS Shopwho
  Site public + PostgreSQL
  Collecte pseudonymisée
  Export contrôlé
          |
          | CSV/Parquet chiffré
          v
VM locale Data Science
  Exploration
  Feature engineering
  Entraînement
  Évaluation
```

Les notebooks, datasets bruts et modèles de recherche n'ont pas besoin d'être présents sur le VPS web.
