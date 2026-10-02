# Protocole expérimental

## Bench
- PrestaShop **8.1.x**, image Docker officielle de la release la plus proche du commit, avec les fichiers du correctif remis dans leur état d'avant (`bench/checkout.sh`).
- Vivier : `bench/select.py` retient **106 candidats** sur 206 PR « Bug fix » mergées en 8.1.x.
  - Critères : ≤ 60 lignes, ≤ 3 fichiers, issue liée avec étapes de repro, pas de sécurité.
  - Écartées : 57 trop grosses, 22 sans issue, 17 hors interface, 3 sécurité, 1 sans repro.
- Cible : **20 à 40 bugs** retenus à la main, reproductibles via l'interface (FO/BO).
- Un bug n'est gardé que si son test de replay **échoue en `pre`** et **passe en `post`** (correctif officiel).

## Viviers (split temporel)
- **TEST** : 30 à 40 bugs corrigés **après** la date de coupure de Gemma 4, sur une seule branche dockerisable, reproductibles via l'interface ou HTTP, 1 à 5 fichiers PHP.
- **TRAIN** : bugs plus anciens, 300 à 1 000 candidats, en excluant tout ce qui touche les mêmes lignes qu'un bug TEST.
- Seuil GO : ≥ 20 bugs TEST valides et ≥ 200 bugs TRAIN.

## Conditions (sur TEST)
| | Ticket | Tests de replay | Contexte PrestaShop (outil) | Modèle |
|---|---|---|---|---|
| **A** | ✓ | — | — | base |
| **B** | ✓ | ✓ | — | base |
| **C** | ✓ | ✓ | ✓ (glossaire métier → symboles, voir `GLOSSAIRE.md`) | base |
| **D** | ✓ | ✓ | ✓ | fine-tuné (chemins AUTO + RECONSTRUITS) |
| D-auto (option) | ✓ | ✓ | ✓ | fine-tuné sur chemins AUTO seuls |

Paramètres figés : température, seed, budget de tours et de tokens, timeout. **3 runs par bug et par condition.**
**Oracle** : test « échoue avant / passe après » dérivé du correctif officiel, caché à l'agent.

## Agent : déroulé FIXE, pas d'agent libre
- Étapes imposées : **1. localiser** (fichiers à modifier) → **2. lire** → **3. éditer** (diff) → **4. tester** (avec les tests de replay en B/C/D, puis retour à l'étape 3, N fois maximum).
- Pourquoi : dans SWE-Gym, l'auto-apprentissage échoue avec un agent libre et ne marche qu'avec un déroulé court et simple (7B : 7 % en déroulé simple contre 1 % en agent libre). C'est aussi la parade à l'effet plancher.
- Gemma 4 via API, en appel d'outils au format Gemma 4 à chaque étape. Le contexte PrestaShop est fourni comme outil.
- L'instruction système et la définition des outils sont **identiques** en évaluation et à l'entraînement.
- Chaque run est journalisé en entier dans `bench/runs/<run>/traj/` (source des données de fine-tuning).

## Métriques (par condition)
- **Localisation** : fichiers proposés à l'étape 1 comparés aux fichiers du diff officiel (précision et rappel). **B vs C = apport mesuré du glossaire métier.**
- **% de bugs corrigés** : le replay du bug passe.
- **% de régressions** : la suite anti-régression `replay/_smoke` casse.
- **% de patchs non applicables.**
- Comparaison qualitative avec le diff officiel : mêmes fichiers, même approche.

## Validation du harness
- `eval.py` avec le diff officiel → 100 % corrigé, 0 régression. Avec un patch vide → 0 %.

## Ressources
- Serveur sd-187494 (sans GPU) : Docker, tests, agent (API).
- Kaggle : fine-tuning uniquement. P100 ou 2×T4, 16 Go par GPU, sessions de 12 h, ~30 h/semaine ; checkpoints réguliers ; lancement en « Save & Run All ».

## Cadrage théorique (section « pourquoi ça marche » du writeup)
- D'après l'analyse ARC Prize 2025, un domaine devient automatisable quand le modèle **connaît assez le domaine** et que la tâche fournit **un signal de retour vérifiable**. Rassembler cette connaissance métier et construire les vérificateurs demande un travail coûteux et spécialisé.
- Chez nous : **la chaîne de replay est le vérificateur**, **l'outil contexte PrestaShop apporte la connaissance métier**.
- Matériel grand public valorisé : le 3e prix papier d'ARC 2025 tournait sur une seule RTX 4070.

---

## Cahier de Recette Détaillé (42 Tests & Grille d'Audit)
L'exécution formelle du banc d'évaluation consolidé à **42 tests déterministes** (33 Cœur + 9 Modules Tiers) et l'audit de conformité Kaggle en 5 piliers sont consignés dans :
👉 **[`docs/PLAN_DE_TESTS.md`](PLAN_DE_TESTS.md)**.
