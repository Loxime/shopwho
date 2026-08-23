# VM locale Data Science — Shopwho

La data science reste volontairement séparée du VPS de production. Le VPS collecte et exporte des événements pseudonymisés ; la VM locale importe ces exports et exécute l'analyse, le feature engineering et l'entraînement.

## Configuration recommandée

### Profil idéal pour la première année du projet

- OS : Ubuntu Server/Desktop 26.04 LTS 64 bits
- CPU : 6 vCPU
- RAM : 16 Go
- Disque : 120 Go SSD, thin provision si l'hyperviseur le permet
- Swap : 8 Go
- Réseau : NAT par défaut ; bridge uniquement si nécessaire
- GPU : aucun au départ

Cette configuration est confortable pour Pandas/Polars, scikit-learn, Jupyter et des datasets allant de quelques centaines de milliers à plusieurs millions d'événements selon leur largeur. Pour un MVP, 4 vCPU / 8 Go / 60 Go fonctionnent aussi ; 16 Go de RAM donnent davantage de marge pour les DataFrames et les comparaisons de modèles.

Un GPU n'apporte presque rien pour la régression logistique, les arbres, Random Forest, XGBoost/LightGBM et l'analyse exploratoire. On n'ajoutera un GPU NVIDIA que si un besoin réel de deep learning apparaît.

## Logiciels

- Git
- Python 3.13
- `uv` ou `venv` pour isoler l'environnement Python
- JupyterLab
- pandas ou polars
- numpy
- scikit-learn
- matplotlib
- pyarrow
- joblib
- pytest
- ruff
- Docker facultatif pour reproduire certains traitements

## Arborescence recommandée

```text
~/projects/shopwho-datascience/
├── data/
│   ├── raw/          # exports immuables depuis la production
│   ├── interim/      # données nettoyées
│   └── processed/    # datasets de features
├── notebooks/        # exploration uniquement
├── src/
│   ├── ingest.py
│   ├── clean.py
│   ├── features.py
│   ├── train.py
│   └── evaluate.py
├── models/
├── reports/
├── tests/
└── pyproject.toml
```

`data/`, les modèles volumineux et les exports réels ne doivent jamais être poussés sur GitHub.

## Flux de données production -> VM locale

1. Sur le VPS, exporter les événements depuis Shopwho en CSV.
2. Copier le fichier de manière chiffrée vers la machine/VM locale (SSH/SCP ou SFTP).
3. Conserver le fichier brut dans `data/raw/` sans le modifier.
4. Calculer un hash SHA-256 du fichier importé pour assurer la traçabilité.
5. Transformer ensuite vers Parquet dans `data/interim/` ou `data/processed/`.
6. Les notebooks lisent les données locales uniquement.

Exemple d'export côté application :

```bash
docker compose exec app php bin/console app:tracking:export-csv \
  --since=2026-09-01 \
  --output=exports/tracking-2026-09.csv
```

## Sauvegardes

La VM n'est pas la source de vérité des données de production. Les exports bruts doivent néanmoins être sauvegardés sur un support chiffré séparé si leur conservation est nécessaire pour le projet.
