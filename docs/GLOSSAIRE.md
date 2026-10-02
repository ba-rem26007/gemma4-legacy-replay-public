# Glossaire métier : vocabulaire du ticket → symboles du code

C'est le contenu concret de l'outil « contexte PrestaShop » en **condition C** (Q2).

## Pourquoi
Les tickets PrestaShop parlent **métier** (souvent en français) : déclinaison, règle panier, prix barré, transporteur. Le code parle **anglais technique** : `Combination`, `CartRule`, `SpecificPrice`, `Carrier`. Un petit modèle qui cherche `declinaison` dans le code ne trouve rien et part dans le décor. C'est l'étape de **localisation**, celle qui plafonne.
→ Le glossaire fait le pont. Son apport se **mesure** : localisation (précision et rappel des fichiers) en **B vs C**. Il relève du levier « connaissance du domaine » du cadrage (vérificateur + connaissance métier).

## Format unique (une brique, trois usages)
`glossaire/glossaire.csv` :

| Colonne | Contenu | Utilisée par le papier |
|---|---|---|
| `terme` | terme métier canonique | ✓ |
| `synonymes` | synonymes et variantes d'écriture, FR et EN, séparés par `\|` | ✓ |
| `phonetique` | variantes phonétiques (« D3 » → « Détroit », « dé trois ») | ✗ (réservé à la transcription vocale et au RAG, hors papier) |
| `symboles` | classes, tables, méthodes, hooks du code, séparés par `\|` | ✓ |
| `source` | `remi` (écrit à la main), `mine` (extrait des bugs TRAIN) ou `doc` (doc PrestaShop publique) | traçabilité |

La dictée phonétique reste **hors du papier** : le concours porte sur des agents de code.

## Construction
1. **Graines écrites par Rémi** : son expertise PrestaShop, ce qui en fait un apport original et défendable.
2. **Extraction automatique** (`bench/glossary_mine.py`), déterministe et sans modèle :
   - mode *bugs* : mots des tickets ↔ classes et méthodes touchées par le correctif officiel (`catalog.jsonl`, `--before <coupure>`). Signal trop faible sur ~250 bugs.
   - mode *gitlog* : messages de commit ↔ classes des fichiers touchés, sur **tout l'historique antérieur à la coupure** (`git log --no-merges --before=<coupure>`). Premier run (coupure provisoire au 2025-06-01) : 61 851 commits, 42 958 exploitables, **1 723 paires candidates dont 1 086 non triviales** (le terme n'est pas simplement le nom de la classe) → `glossaire/candidats_gitlog.csv`.
   - Exemples trouvés : `voucher → CartRule`, `credit slip → HTMLTemplateOrderSlip / AdminOrdersController`, `partial refund → AdminOrdersController`, `opc → OrderOpcController`, `ecotax → OrderInvoice`, `guest → AuthController`, `carrier wizard → AdminCarrierWizardController`.
   - Limite : les classes centrales (`Product`, `Cart`) dominent. `combination → Combination` ne sort pas en tête. D'où la nécessité des graines et de la relecture de Rémi.
   - **Étanchéité** : l'extraction ne porte que sur ce qui est antérieur à la coupure. À relancer une fois la date réelle connue (phase 1).
3. Relecture par Rémi. Les entrées `mine` ne sont conservées qu'une fois validées.

## Règles
- **Étanchéité** : aucune entrée dérivée d'un bug TEST.
- Le glossaire est une ressource **d'inférence** (outil de la condition C), pas une donnée d'entraînement. Il n'est pas généré par un modèle propriétaire. Claude écrit uniquement le script d'extraction.
- Il est publié avec le papier comme ressource (positionnement « Best New Resource »).

## Index des pages du BO (`glossaire/pages.csv`)
Généré par `python3 glossaire/pages.py [commit]` (défaut 9.1.0, antérieur aux bugs TEST) depuis le code seul :
`install-dev/data/xml/tab.xml` (menu, `class_name`) + libellés anglais + routes Symfony (`_legacy_controller` → `_controller`).
79 pages : « Shopping Carts » → `AdminCarts`, `src/PrestaShopBundle/Controller/Admin/Sell/Order/CartController.php`.
Contrôle hors ligne (sans modèle) sur les 33 tickets TEST : entrée pour 28 tickets, fichier corrigé (ou classe homonyme) pointé pour 7 (glossaire automatique : 4).
Combinable : `GLOSSAIRE=glossaire_auto.csv,pages.csv` (run « C+pages », `runs/test_Cp1.log`).
