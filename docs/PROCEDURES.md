# Procédures

Toutes les commandes se lancent depuis la racine du dépôt. Les scripts sont déterministes (aucun modèle), sauf `agent/run.py`.

## 0. Installation
```bash
git clone https://github.com/ba-rem26007/gemma4-legacy-replay.git && cd gemma4-legacy-replay
./setup.sh                      # clone PrestaShop dans bench/ps (~1 Go, historique complet) + Playwright
cp .env.local.example .env      # PC : Gemma local (Ollama)   | serveur : cp .env.api.example .env + GEMMA_API_KEY
```
Pré-requis : Docker, Node 20, Python 3.10+, `gh` authentifié (collecte GitHub).

## 1. Collecter des bugs (`bench/select.py`)
```bash
# 9.x / 8.x (label « Bug fix ») :
python3 bench/select.py --limit 1000 --branch all --out bugs_all.jsonl
# 1.6 / 1.7 (pas de label) : type « bug fix » dans la PR ou préfixe [-], ticket = description de PR si pas d'issue
python3 bench/select.py --limit 1000 --merged 2015-01-01..2015-04-01 --branch all --label "" \
    --type-bugfix --allow-no-issue --max-lines 150 --max-files 5 --out legacy/w_2015-01-01.jsonl
```
- `gh pr list` plafonne à 1 000 résultats → découper par fenêtre `--merged`.
- Filtres : taille, fichiers, sécurité (mots-clés + labels), issue/repro, fichiers d'interface.
- PR rebasées de l'époque 1.6 : commit de merge absent → avant = parent du 1er commit de la PR, après = son dernier commit.
- Sorties : `bench/*.jsonl` + correctif officiel `bench/diffs/<pr>.diff`.

## 2. Cataloguer et qualifier
```bash
python3 bench/analyze.py --in bugs_all.jsonl bugs_train_ext.jsonl   # → bench/catalog.jsonl (+ docs/CATALOGUE.md)
python3 bench/qualify.py                                              # → bench/qualified.csv (parcours, difficulté)
python3 bench/test_pool.py --cutoff 2025-06-01 --branch 9.1.x         # → data/bugs_test.csv (vivier TEST + statuts)
```

## 3. Mettre PrestaShop dans l'état d'un bug (`bench/checkout.sh`)
```bash
PSB=2 bench/checkout.sh <pr> pre          # code AVANT le correctif
PSB=2 bench/checkout.sh <pr> post         # correctif officiel
PSB=2 bench/checkout.sh <pr> patch.diff   # patch d'un agent
```
- **Instance** : `PSB=n` → projet Docker `psbench<n>` (1 = `psbench`), port `808n`. Plusieurs instances en parallèle.
- **Image** : release la plus proche (8.x/9.x : avant le commit ; `develop` : première release qui contient le code ; 9.1.x : ≥ 9.1.0). 9.x = variante `classic`. 1.6/1.7 : image officielle (liste `bench/env/images_1x.txt`) + MySQL 5.7.
- **Montée incrémentale** : vers une version plus récente de la même branche 9.1.x, la base est conservée (schéma identique), seul le code change (1-2 min au lieu de 8). Configuration, images, `.htaccess`, modules/thèmes/langues ajoutés restaurés. Traiter les bugs **du plus ancien au plus récent**.
- **Reset** : instantané de la base juste après l'installation (`bench/env/.snap-<projet>.sql.gz`), restauré avant chaque bug. `NO_RESET=1` pour le désactiver.
- **Rattrapage de code** : si le commit de base est postérieur à la release de l'image, tous les fichiers php/tpl/twig modifiés entre les deux sont appliqués (≤ 400).
- **Isolation** : les fichiers superposés au passage précédent sont remis dans l'état de la release avant le suivant.
- BO : `http://localhost:808n/admin-dev` — `demo@prestashop.com` / `prestashop_demo` (tunnel SSH `-L 808n:localhost:808n`).
- Limite : JS/TS compilé non rejouable (les images embarquent les assets compilés) → bugs JS exclus du TEST.

## 4. Écrire / valider un oracle (vivier TEST)
Dans `bench/replay/<pr>/` :
- `oracle.spec.js` (front) ou `oracle.bo.spec.js` (back-office, session partagée via `auth.setup.js`) : vérifie le comportement **corrigé**. **Caché à l'agent.**
- `replay*.spec.js` : tests de rejeu **visibles** par l'agent en conditions B/C/D/R (produits par la chaîne de génération).
- `setup.sql` : données nécessaires (rejoué à chaque run, idempotent).
- `STATUS` : `valide` ou `exclu:<raison>` + une ligne de note.
```bash
PSB=1 bench/checkout.sh <pr> pre  && PSB=1 bench/replay/run.sh <pr>   # doit ÉCHOUER (assertion métier)
PSB=1 bench/checkout.sh <pr> post && PSB=1 bench/replay/run.sh <pr>   # doit PASSER
python3 bench/test_pool.py        # reporte STATUS dans data/bugs_test.csv
```
Pièges 9.1 : lien BO sans jeton → page « sécurité » (cliquer « comprends les risques ») ; images `classic` = thème hummingbird ; aucune règle de taxe à l'install ; mode prod (activer `_PS_MODE_DEV_` le temps d'un test si l'erreur ne se voit qu'en debug, et le remettre).

## 5. Évaluer un patch (`bench/eval.py`)
```bash
PSB=2 python3 bench/eval.py <pr> <patch.diff|pre|post>
```
Ordre : checkout (reset + patch) → **anti-régression** (accueil FO + login BO, AVANT l'oracle) → oracle(s). Verrou par instance (`bench/.eval<n>.lock`).
Réévaluer des patchs existants sans rappeler le modèle : `PSB=2 python3 bench/reeval.py runs/<run>`.

## 6. Lancer l'agent (`agent/run.py`)
```bash
python3 agent/run.py --list-models
PSB=2 python3 agent/run.py --bugs <pr…> --condition A --model gemma-4-31b-it --retries 0
PSB=3 python3 agent/run.py --bugs <pr…> --condition R --model gemma-4-31b-it --retries 0
python3 agent/run.py --bugs 35322 --condition B --policy reconstruit     # démo sans modèle (chemin reconstruit)
```
- Conditions : **A** ticket · **B** + rejeu · **C** + glossaire · **D** modèle fine-tuné · **R** fine-tuning simulé (2 corrections TRAIN similaires injectées, `agent/fewshot.py`).
- Déroulé fixe localiser → lire → éditer → (tester), 2 retours arrière max. Bugs traités du plus ancien au plus récent.
- Quotas : limiteur partagé ≤ 15 000 tokens d'entrée/min (quota Gemma 4 31B : 16 000), plafond ~13 000 par requête, bugs sautés repris en fin de run.
- **Budget** : `--budget-eur 30` (compteur `runs/_budget.json`, prix via `LLM_PRICE_IN/OUT` ; Gemma 4 sur l'API Gemini = gratuit).
- Sorties : `runs/<run>/<pr>/trace.jsonl` (tous les tours), `patch.diff`, `result.json`, `summary.json`.

## 7. Oracles automatiques (`bench/autooracle.py`) — résultat négatif
```bash
PSB=1 python3 bench/autooracle.py <pr…>
```
Différentiel pre/post des pages ciblées (mode debug). Mesure sur 12 bugs 9.1.4 validés à la main : **0/12** (bugs dépendants d'un état : commande, multiboutique, module). À réserver aux bugs visibles sur les données de démo.

## 8. Données d'entraînement et fine-tuning
```bash
python3 trajectories/reconstruct.py --cutoff 2025-06-01   # chemins reconstruits vérifiés → trajectories/train.jsonl
python3 bench/glossary_mine.py --gitlog <git log> --min-support 8   # candidats glossaire
python training/train_qlora.py --model google/gemma-4-e4b-it      # QLoRA (PC ou Kaggle), voir training/README.md
```
Étanchéité : split temporel + exclusion des fonctions touchées par un bug TEST (`data/ETANCHEITE.md`).

## 9. Site
`site/` (nginx + Traefik) sert `README.md`, `ETAT.md`, `DECISIONS.md`, `REGLES.md`, `KIT.md` et `docs/*.md` : https://kaggle.d1dev.fr (auth).

## Fine-tuning : toujours tester sur Google Colab avant Kaggle
Voir `docs/FINETUNING_KAGGLE.md` §0 : `notebook/colab_smoke_training.ipynb` en `SMOKE=1` (pré-test mémoire + 2 pas),
puis seulement `kaggle kernels push -p training/kaggle_kernel`. Le quota GPU Kaggle est réservé aux runs complets.
