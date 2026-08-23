# Workflow Git — Shopwho

Objectif : garder un historique lisible où chaque action de développement cohérente correspond à un commit atomique.

## Branches

- `main` : version stable et déployable
- `dev` : intégration
- `feature/*` : une fonctionnalité ou correction isolée

## Règle de commit

Toute modification de code, configuration, documentation, migration ou test est commitée immédiatement après validation de l'action correspondante.

Exemples :

```text
chore: bootstrap Symfony Docker environment
feat: add product catalog and product pages
feat: add protected product administration
feat: add session cart and simulated checkout
feat: instrument behavioral tracking events
feat: add product seeding and tracking export commands
test: add unit checks and GitHub Actions CI
```

Une commande qui ne modifie aucun fichier suivi (`docker compose up`, lecture de logs, lancement des tests, requête SQL de contrôle) n'a pas besoin d'un commit artificiel. En revanche, toute correction issue de ce contrôle doit être un nouveau commit.

## Cycle pour chaque nouvelle action

```bash
git switch dev
git pull
git switch -c feature/nom-action

# une action cohérente
# tests / vérifications

git status
git add <fichiers concernés>
git commit -m "feat: description concise"
git push -u origin feature/nom-action
```

Après revue/CI, merge vers `dev`. `main` ne reçoit que les versions considérées déployables.
