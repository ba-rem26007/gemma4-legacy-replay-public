# Vocabulaire

| Terme | Sens dans le projet | Où |
|---|---|---|
| **Bug** | Bug PrestaShop **déjà corrigé** en amont : une issue + une PR mergée | `bench/bugs*.jsonl` |
| **Correctif officiel** | Diff de la PR de l'équipe PrestaShop, qui sert de vérité terrain | `bench/diffs/<pr>.diff` |
| **Commit avant / pre** | État du code avant le correctif (1er parent du commit de merge) | `checkout.sh <pr> pre` |
| **Commit correctif / post** | État du code avec le correctif officiel | `checkout.sh <pr> post` |
| **Vivier** | Ensemble de bugs candidats. **TEST** = corrigés après la coupure de Gemma 4 (évaluation), **TRAIN** = plus anciens (entraînement) | `data/bugs_test.csv`, `data/bugs_train.csv` |
| **Date de coupure** | Date de connaissance déclarée de Gemma 4. Les bugs corrigés après n'ont pas pu être vus par le modèle | `DECISIONS.md` |
| **Étanchéité** | Aucun bug TEST (ni même fichier ou lignes) dans les données TRAIN | `data/ETANCHEITE.md` |
| **Parcours** | Suite de clics sur le front ou le BO, déclarée en YAML, pilotée par Playwright | `replay/` |
| **Test de rejeu (replay)** | Parcours rejoué et comparé au golden master : le **vérificateur** donné à l'agent | `bench/replay/<pr>/` |
| **Golden master** | Réponses HTTP et SQL capturées sur le code de référence, normalisées (tokens, dates, ids) | `replay/` |
| **Oracle** | Test « échoue avant / passe après » dérivé du correctif officiel, **caché à l'agent**. Il décide si un bug est résolu | `bench/replay/<pr>/oracle*.spec.js` |
| **Régression** | Un test de rejeu qui passait casse après le patch de l'agent | `eval/results.csv` |
| **Contexte PrestaShop** | Outil qui donne la connaissance métier : schéma de base, `_lang`/`_shop`, déclinaisons, ObjectModel, hooks, overrides, legacy vs Symfony | `agent/` |
| **Condition A/B/C/D** | A = ticket seul · B = + replay · C = + contexte PrestaShop · D = C + modèle fine-tuné | `docs/PROTOCOLE.md` |
| **Glossaire métier** | Vocabulaire du ticket (FR/EN) → symboles du code (classes, tables, méthodes). Contenu de l'outil de la condition C | `glossaire/glossaire.csv` |
| **Localisation** | Étape 1 de l'agent : trouver les fichiers à modifier. Mesurée par comparaison avec le diff officiel | `eval/` |
| **Catalogue** | Fiche déterministe par bug : ticket, résolution, fonctions touchées, date | `bench/catalog.jsonl` |
| **Condition R** | « Fine-tuning simulé » : les 2 corrections TRAIN les plus proches du ticket sont injectées en exemples | `agent/fewshot.py` |
| **Instance** | Une stack Docker PrestaShop indépendante : `PSB=n` → projet `psbench<n>`, port `808n` | `bench/checkout.sh` |
| **Reset** | Restauration de l'instantané de base pris après l'installation, avant chaque bug | `bench/env/.snap-*` |
| **Montée incrémentale** | Passage à une version plus récente de la même branche en gardant la base (9.1.x) | `bench/checkout.sh` |
| **Rattrapage de code** | Application des fichiers serveur modifiés entre la release de l'image et le commit de base | `bench/checkout.sh` |
| **Oracle automatique** | Test généré par différentiel pre/post des pages (sans modèle) | `bench/autooracle.py` |
| **Déroulé fixe** | Étapes imposées à l'agent : localiser → lire → éditer → tester (pas d'agent libre) | `agent/` |
| **Trajectoire** | Trace brute complète d'un run d'agent : entrée, décision, outil, résultat, temps, tokens | `runs/<run>/` |
| **Chemin** | Trajectoire **condensée** et validée : uniquement les étapes utiles, au format exact des appels d'outils. C'est l'unité d'entraînement | `trajectories/train.jsonl` |
| **Chemin AUTO** | Chemin issu d'une réussite de Gemma lui-même (auto-distillation) | `source=auto` |
| **Chemin RECONSTRUIT** | Chemin reconstruit par script depuis le correctif officiel (ticket → recherches → lectures → diff → tests) | `source=reconstruit` |
| **Condensation** | Suppression des étapes inutiles d'une trajectoire, par script déterministe ou par Gemma, **jamais** par un modèle propriétaire | `trajectories/` |
| **Masquage** | La loss ne porte que sur les tours de l'assistant ; ticket et résultats d'outils sont masqués | `training/` |
| **Fabrique** | L'ensemble des scripts qui produisent les chemins. Claude en écrit le code, jamais le contenu | `bench/`, `trajectories/` |
| **Pilote** | Les 3 premiers bugs validés (#35902, #35384, #35322), en vivier TRAIN | `docs/PILOTE.md` |

## Arborescence
```
/home/elrems/kaggle/
├── KIT.md  ETAT.md  DECISIONS.md  REGLES.md  README.md  CLAUDE.md
├── docs/           PROTOCOLE, DONNEES_FT, PILOTE, VOCABULAIRE
├── bench/
│   ├── select.py   sélection des bugs (GitHub)  → bugs.jsonl, bugs_all.jsonl, diffs/
│   ├── ps/         clone PrestaShop (blobless)
│   ├── env/        docker-compose PrestaShop + MySQL
│   ├── checkout.sh <pr> pre|post|patch.diff
│   └── replay/     Playwright : run.sh, auth.setup.js, <pr>/setup.sql, <pr>/replay*.spec.js
├── data/           (à venir) bugs_test.csv, bugs_train.csv, ETANCHEITE.md
├── agent/ runs/ trajectories/ training/ eval/   (à venir)
├── notebook/       notebook Kaggle (fine-tuning)
├── site/           https://kaggle.d1dev.fr
├── kg.sh           boucle Kaggle
└── tmux.sh         session tmux + Claude
```
