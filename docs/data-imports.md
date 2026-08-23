# Imports de données

Shopwho importe exclusivement en CLI des jeux synthétiques ou pseudonymisés :

```bash
php bin/console app:import:data users var/import/users.json
php bin/console app:import:data products var/import/products.xlsx --dry-run
php bin/console app:import:data orders var/import/orders.json --report=var/import/report.json
php bin/console app:import:data reviews var/import/reviews.xlsx
```

`--dry-run` parse et valide les données, résout toutes les références et calcule le rapport, puis annule la transaction. `--report` écrit facultativement le même rapport (`total`, `created`, `updated`, `skipped`, `failed`, `errors`) en JSON. Une erreur de fichier ou au moins une ligne rejetée produit un code retour non nul.

## Formats et contrats

Les fichiers acceptés sont `.json` et `.xlsx`, détectés par extension. JSON utilise une clé racine correspondant au type. XLSX utilise une feuille du même nom et la première ligne comme headers. Les lignes vides sont ignorées. Les textes sont trimés, les entiers sont stricts et les booléens acceptent `true/false`, `1/0`, `yes/no`. Les dates sont ISO-8601 ATOM (par exemple `2026-03-20T09:00:00+00:00`).

- `users` : `externalRef`, `email`, `firstName`, `lastName`, `createdAt`. `externalRef` et `email` sont obligatoires.
- `products` : `externalRef`, `name`, `slug`, `description`, `priceCents`, `stock`, `categorySlug`, `imageUrl`, `isActive`. La catégorie doit déjà exister.
- `orders` : `externalRef`, `userExternalRef`, `status`, `orderedAt`, `totalCents`; et une seconde collection/feuille `order_items` : `orderExternalRef`, `productExternalRef`, `productNameSnapshot`, `productSlugSnapshot`, `quantity`, `unitPriceCents`. `totalCents` est facultatif mais, s’il est fourni, doit égaler la somme calculée. Statuts : `completed`, `cancelled`, `refunded`.
- `reviews` : `externalRef`, `userExternalRef`, `productExternalRef`, `rating`, `comment`, `createdAt`. L’achat vérifié est obligatoire.

JSON `orders` contient les clés `orders` et `order_items`. XLSX contient les deux feuilles homonymes. Pour tous les autres types, JSON contient une seule clé et XLSX une seule feuille.

## Idempotence et conflits

Users et Products sont créés ou mis à jour par `externalRef`. Un nouvel externalRef en conflit avec un email/slug existant est rejeté, sans rattachement implicite. Le mot de passe d’un User existant n’est jamais modifié ; un nouveau compte reçoit le hash d’un secret aléatoire non exposé.

Orders est immuable : un `externalRef` existant est ignoré. Chaque commande et ses lignes sont atomiques. Les snapshots et prix du fichier font foi ; un produit absent reste autorisé avec relation nulle et snapshots complets.

Reviews est créé ou mis à jour uniquement si l’externalRef conserve le même couple User/Product. Un second externalRef pour ce couple est rejeté. Seuls les achats `completed` ou `simulated_completed` sont vérifiés ; `cancelled` et `refunded` ne le sont pas.

## Ordre recommandé

1. users
2. products
3. orders
4. reviews

Orders dépend des Users et peut référencer Products. Reviews dépend de Users, Products et d’un achat vérifié.

## Sécurité des datasets

Les datasets runtime vont dans `var/import/`, déjà ignoré par Git. Ne jamais committer de dataset runtime, dump client, donnée personnelle réelle ou hash/mot de passe. Seules les minuscules fixtures explicitement fictives de `tests/Fixtures/Import/` sont versionnées.
