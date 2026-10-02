# Résultats — vivier TEST 9.1.x (33 bugs, oracles cachés)

Gemma 4 31B (API Google AI Studio), déroulé fixe, **1 tentative par bug** (pas de retour de test), température 0,2.
« Résolu » = l'oracle passe **et** aucune régression (accueil FO + login BO) sur la base remise à zéro.
Verdicts **réévalués** sans rappeler le modèle (`bench/reeval.py`) sur instances neuves quand disponibles.
L'essai 1 a tourné sur un environnement partiellement défectueux : sa génération est valable, son verdict l'est après réévaluation.

## Taux de résolution par essai

| Condition | Essai 1 | Essai 2 | Essai 3 | Essai 4 | Moyenne | Écart-type | Résolu ≥ 1 fois (pass@4) | Bon fichier (moy.) | Régressions |
|---|---|---|---|---|---|---|---|---|---|
| A · ticket seul | 12/33 | 13/33 | 15/33 | 11/33 | 12.8 (39%) | 1.5 | 17/33 | 19.8 | 0 |
| R · ticket + 2 corrections TRAIN similaires | 14/33 | 12/33 | 14/33 | 11/33 | 12.8 (39%) | 1.3 | 15/33 | 20.2 | 0 |

## R contre A (apparié par bug)

Écart moyen du taux de résolution R − A : **+0.0%** (IC 95 % bootstrap : -9.1% à +9.1%).
Si l'intervalle contient 0, l'injection de corrections similaires n'a pas d'effet démontré.

- Bugs jamais résolus (0/8) : **15**
- Bugs toujours résolus (8/8) : **3**

## Détail par bug (nombre d'essais résolus sur 4)

| Bug | Ticket | A | R |
|---|---|---|---|
| [#40971](https://github.com/PrestaShop/PrestaShop/pull/40971) | Uploading logo in Design -> Theme & Logo in multistore with a single s | 4 | 4 |
| [#41193](https://github.com/PrestaShop/PrestaShop/pull/41193) | [9.1.0] Child theme translations are not displayed in Back Office tran | 4 | 4 |
| [#41007](https://github.com/PrestaShop/PrestaShop/pull/41007) | CountryQueryBuilder::getCountQueryBuilder() always returns 1 instead o | 4 | 4 |
| [#40853](https://github.com/PrestaShop/PrestaShop/pull/40853) | Search::find fuzzy search does not escape closest word and breaks SQL  | 3 | 4 |
| [#40651](https://github.com/PrestaShop/PrestaShop/pull/40651) | Bad product name | 3 | 4 |
| [#41299](https://github.com/PrestaShop/PrestaShop/pull/41299) | Invalid product URLs trigger Fatal in ProductController::assignPriceAn | 4 | 3 |
| [#42004](https://github.com/PrestaShop/PrestaShop/pull/42004) | Duplicating with DuplicateProductCommand: Invalid Product localized pr | 3 | 4 |
| [#41929](https://github.com/PrestaShop/PrestaShop/pull/41929) | Cannot edit a product when the experimental Catalog price rules featur | 4 | 3 |
| [#41652](https://github.com/PrestaShop/PrestaShop/pull/41652) | Changing an order's status throws "Duplicate entry '<idp>-<idpa>-0-0'  | 4 | 3 |
| [#41468](https://github.com/PrestaShop/PrestaShop/pull/41468) | Multishop: cache_default_attribute is not reset for all shops when cha | 4 | 3 |
| [#41130](https://github.com/PrestaShop/PrestaShop/pull/41130) | When the Admin API is used with multistore enabled, any write operatio | 2 | 4 |
| [#41665](https://github.com/PrestaShop/PrestaShop/pull/41665) | B O - Order view page - The Invoice prefix is displayed in the custome | 2 | 4 |
| [#40898](https://github.com/PrestaShop/PrestaShop/pull/40898) | Bug: reserved_quantity not updated when "Share available quantities fo | 2 | 3 |
| [#41320](https://github.com/PrestaShop/PrestaShop/pull/41320) | Unable to delete product from order when product is deleted from catal | 3 | 0 |
| [#41530](https://github.com/PrestaShop/PrestaShop/pull/41530) | BO - Shopping carts - Custom product image does not appear | 0 | 3 |
| [#41675](https://github.com/PrestaShop/PrestaShop/pull/41675) | HTMLPurifier through twig extension is not adhering to cache dir confi | 2 | 1 |
| [#41524](https://github.com/PrestaShop/PrestaShop/pull/41524) | BO - Order - Invoice / Payment method is not printed on invoice if the | 2 | 0 |
| [#41394](https://github.com/PrestaShop/PrestaShop/pull/41394) | [Multishop] Error when updating "Schema of URLs" for a single shop | 1 | 0 |
| [#40743](https://github.com/PrestaShop/PrestaShop/pull/40743) | Bug: when invoicing is disabled, changing order status to "paid=1" doe | 0 | 0 |
| [#40070](https://github.com/PrestaShop/PrestaShop/pull/40070) | actionCarrierUpdate not triggered on migrated carrier page | 0 | 0 |
| [#41100](https://github.com/PrestaShop/PrestaShop/pull/41100) | All non core HTML emails are empty | 0 | 0 |
| [#41327](https://github.com/PrestaShop/PrestaShop/pull/41327) | Orders are persisted with the cart totals but the items are less than  | 0 | 0 |
| [#41412](https://github.com/PrestaShop/PrestaShop/pull/41412) | Shop can't send emails from IDN domains | 0 | 0 |
| [#40999](https://github.com/PrestaShop/PrestaShop/pull/40999) | Multistore - Can't create Shop without import data | 0 | 0 |
| [#41036](https://github.com/PrestaShop/PrestaShop/pull/41036) | Error 500 if I enter a space in a customer's first or last name field | 0 | 0 |
| [#41457](https://github.com/PrestaShop/PrestaShop/pull/41457) | The prefix and the invoice number are missing from the name of the inv | 0 | 0 |
| [#41225](https://github.com/PrestaShop/PrestaShop/pull/41225) | Attribute swatches on homepage ignore position ordering | 0 | 0 |
| [#41611](https://github.com/PrestaShop/PrestaShop/pull/41611) | Cart rule compatibility search does not filter results for new cart ru | 0 | 0 |
| [#41573](https://github.com/PrestaShop/PrestaShop/pull/41573) | The Discount highlight feature no longer exists on Discount V2. | 0 | 0 |
| [#41735](https://github.com/PrestaShop/PrestaShop/pull/41735) | In Prestashop 9.1 The custom features are listed in the default featur | 0 | 0 |
| [#41727](https://github.com/PrestaShop/PrestaShop/pull/41727) | Module Development and Distribution: Prestashop deletes automatically  | 0 | 0 |
| [#41570](https://github.com/PrestaShop/PrestaShop/pull/41570) | missing column to change the position of the features on prestashop 9. | 0 | 0 |
| [#41923](https://github.com/PrestaShop/PrestaShop/pull/41923) | Not able to change stock behaviour in shared stock | 0 | 0 |

## Borne haute : l'oracle comme retour (condition O, 1 essai)

L'agent reçoit le résultat de l'**oracle caché** après chaque tentative (fuite volontaire) et peut corriger 2 fois.
C'est le meilleur retour qu'un vérificateur puisse donner : il borne l'apport de toute chaîne de tests.

| | Bugs résolus |
|---|---|
| A, moyenne des 4 essais | 12.8/33 |
| O, 1re tentative (même consigne que A) | 13/33 |
| O, après retour de l'oracle | **16/33** (+3 : #41394, #41299, #41923) |
| O, verdict final réévalué (dernier patch) | 16/33 |

Lecture : même un vérificateur parfait n'ajoute que quelques bugs ; les échecs restants ne trouvent pas le bon fichier ou ne savent pas corriger malgré le signal (voir `docs/ECHECS.md`).

## Autres conditions (1 essai) contre A

Gagné = résolu ici mais jamais par A (0/4) ; perdu = raté ici mais toujours résolu par A (4/4).

| Condition | Bugs traités | Résolus | Bon fichier | Gagnés | Perdus |
|---|---|---|---|---|---|
| A · moyenne des 4 essais | 33 | 12.8 | 19.8 | — | — |
| B · Replay tests avec feedback dynamique (Gemma 4 31B) | 33 | 15 | 17 | 1 (#41923) | 0 |
| A-4B · Zero-Shot sans LoRA (Gemma 4 4B Base) | 33 | 5 | 21 | 0 | 5 |
| E · Fine-tuné complet QLoRA (Gemma 4 4B) | 33 | 4 | 14 | 0 | 4 |
| C · + glossaire automatique (provisoire) | 33 | 13 | 18 | 2 (#41530, #41923) | 1 |
| C+pages · + glossaire auto + index des pages BO | 33 | 11 | 18 | 0 | 1 |
