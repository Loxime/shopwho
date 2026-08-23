# Réinitialisation sélective des données importées

Le reset Shopwho retire une liste explicite de données de simulation/import. Ce n'est **pas un `TRUNCATE`** et ce workflow ne remplace ni la suppression RGPD, ni la désactivation d'un compte, ni l'archivage ou la suppression métier d'un produit.

## Provenance

`User` et `Product` portent un `DataOrigin` explicite :

- `native` pour toute création normale dans Shopwho ;
- `imported` pour une création réalisée par `UserImporter` ou `ProductImporter`.

La migration `Version20260824090000` classe les lignes historiques avec la règle ponctuelle suivante : `external_ref IS NOT NULL` donne `imported`, sinon `native`. Après cette migration, `externalRef` n'est plus la source de vérité de la provenance. Un import peut mettre à jour une entité `imported`, mais refuse de modifier une entité `native` portant par ailleurs la même référence.

## Contrat des fichiers

Formats acceptés : JSON et XLSX. Une cible est exclusivement identifiée par `externalRef` ; les identifiants SQL, emails, slugs et noms ne sont jamais utilisés pour supprimer.

JSON users :

```json
{"users":[{"externalRef":"USR-FICTION-001"}]}
```

JSON products :

```json
{"products":[{"externalRef":"PROD-FICTION-001"}]}
```

Un XLSX contient une feuille `users` ou `products`, selon le type demandé, et une colonne obligatoire `externalRef`. Les valeurs sont trimées, les lignes entièrement vides sont ignorées, une valeur vide est signalée en erreur et une référence dupliquée est ignorée après sa première occurrence.

## Protections

Un User est supprimable seulement s'il est `imported`, sans commande et sans avis. Les TrackingEvents ne bloquent pas : la contrainte `ON DELETE SET NULL` conserve les événements et anonymise leur relation utilisateur.

Un Product est supprimable seulement s'il est `imported`, sans avis et absent de tout historique de commande. La recherche couvre la relation `OrderItem.product`, `productIdSnapshot` et `productExternalRefSnapshot`, même lorsque la FK produit est déjà nulle.

Raisons principales : `native`, `has_orders`, `has_reviews`, `used_in_order_history`, `not_found`, `duplicate` et `empty_external_ref`. Aucune Order, Review, OrderItem ou TrackingEvent n'est supprimée pour rendre une cible supprimable : l'historique métier gagne toujours.

## Prévisualisation et application

La commande exige un mode explicite :

```bash
php bin/console app:data:reset users var/import/reset-users.json --dry-run
php bin/console app:data:reset users var/import/reset-users.json --apply
php bin/console app:data:reset products var/import/reset-products.xlsx --dry-run
php bin/console app:data:reset products var/import/reset-products.xlsx --apply
```

`--dry-run` ne mute aucune ligne. `deletable` indique ce qui serait supprimé et `deleted` reste à zéro. `--apply` recalcule toutes les protections, verrouille les cibles, puis supprime les seules entrées encore éligibles dans une transaction unique. Une panne Doctrine/infrastructure est propagée et rollbacke tout le batch ; elle n'est pas convertie en simple erreur de ligne.

## Interface web

La page `/admin/data-reset` (« Gestion des données importées ») est protégée par `ROLE_ADMIN`. Elle accepte uniquement `.json` et `.xlsx`, jusqu'à 10 MB. Le fichier uploadé sert au parsing temporaire et n'est pas conservé.

Le premier POST prévisualise sans supprimer et place en session la liste normalisée sous un nonce aléatoire valable 30 minutes. Le second POST exige une confirmation et un token CSRF lié au nonce. À l'application, les protections sont recalculées dans la transaction ; le nonce est invalidé après succès et ne peut pas être rejoué.

Le répertoire runtime `var/import/` reste ignoré. Les fixtures de test du reset sont exclusivement synthétiques (`FICTION`, `example.test`).
