# Catalogue des bugs candidats

Généré par `bench/analyze.py` (extraction déterministe, aucun modèle). 1007 bugs, dont 976 avec étapes de repro. Données : `bench/catalog.jsonl`.

## Par branche

| Branche | Bugs |
|---|---|
| develop | 353 |
| 1.7.8.x | 173 |
| 8.1.x | 110 |
| 9.0.x | 106 |
| 8.0.x | 93 |
| 9.1.x | 55 |
| 8.2.x | 54 |
| 1.7.7.x | 42 |
| 9.2.x | 12 |
| 1.7.8.0-build | 7 |
| 1.7.6.x | 1 |
| 8.0.0-rc | 1 |

## Par mois de merge

| Mois | Bugs |
|---|---|
| 2019-07 | 1 |
| 2019-08 | 2 |
| 2020-03 | 1 |
| 2020-04 | 1 |
| 2020-06 | 1 |
| 2020-08 | 1 |
| 2021-02 | 4 |
| 2021-03 | 26 |
| 2021-04 | 23 |
| 2021-05 | 38 |
| 2021-06 | 48 |
| 2021-07 | 14 |
| 2021-08 | 21 |
| 2021-09 | 26 |
| 2021-10 | 31 |
| 2021-11 | 18 |
| 2021-12 | 20 |
| 2022-01 | 21 |
| 2022-02 | 20 |
| 2022-03 | 28 |
| 2022-04 | 20 |
| 2022-05 | 15 |
| 2022-06 | 12 |
| 2022-07 | 24 |
| 2022-08 | 14 |
| 2022-09 | 38 |
| 2022-10 | 27 |
| 2022-11 | 25 |
| 2022-12 | 5 |
| 2023-01 | 22 |
| 2023-02 | 19 |
| 2023-03 | 16 |
| 2023-04 | 11 |
| 2023-05 | 21 |
| 2023-06 | 13 |
| 2023-07 | 31 |
| 2023-08 | 6 |
| 2023-09 | 13 |
| 2023-10 | 8 |
| 2023-11 | 12 |
| 2023-12 | 5 |
| 2024-01 | 5 |
| 2024-02 | 8 |
| 2024-03 | 6 |
| 2024-04 | 7 |
| 2024-05 | 5 |
| 2024-06 | 3 |
| 2024-07 | 8 |
| 2024-08 | 3 |
| 2024-09 | 9 |
| 2024-10 | 8 |
| 2024-11 | 6 |
| 2024-12 | 4 |
| 2025-01 | 13 |
| 2025-02 | 6 |
| 2025-03 | 13 |
| 2025-04 | 12 |
| 2025-05 | 2 |
| 2025-06 | 12 |
| 2025-07 | 8 |
| 2025-08 | 18 |
| 2025-09 | 8 |
| 2025-10 | 11 |
| 2025-11 | 19 |
| 2025-12 | 9 |
| 2026-01 | 14 |
| 2026-02 | 10 |
| 2026-03 | 6 |
| 2026-04 | 7 |
| 2026-05 | 22 |
| 2026-06 | 17 |
| 2026-07 | 9 |
| 2026-08 | 8 |
| 2026-09 | 9 |

## Catégorie (template de PR)

| Cat. | Bugs |
|---|---|
| BO | 611 |
| FO | 215 |
| CO | 134 |
| WS | 22 |
| IN | 13 |
| TE | 6 |
| BO | | 2 |
| CO                             | 1 |
| CO | | 1 |
| PM | 1 |
| LO | 1 |

## Dossiers les plus touchés

| Dossier | Fichiers |
|---|---|
| `src/PrestaShopBundle` | 328 |
| `admin-dev/themes` | 252 |
| `classes` | 180 |
| `src/Adapter` | 130 |
| `src/Core` | 102 |
| `themes/classic` | 86 |
| `controllers/admin` | 70 |
| `tests/Integration` | 59 |
| `controllers/front` | 46 |
| `classes/controller` | 37 |
| `tests/Unit` | 30 |
| `tests/UI` | 19 |
| `classes/order` | 16 |
| `classes/form` | 13 |
| `classes/webservice` | 12 |

## Classes les plus touchées

| Classe | Modifs |
|---|---|
| `ProductCore` | 40 |
| `FrontControllerCore` | 31 |
| `ProductController` | 26 |
| `CartCore` | 23 |
| `ProductControllerCore` | 20 |
| `ModuleCore` | 20 |
| `ModuleRepository` | 14 |
| `MetaController` | 14 |
| `AdminControllerCore` | 13 |
| `ProductLazyArray` | 13 |
| `CategoryControllerCore` | 12 |
| `CategoryCore` | 11 |
| `HookCore` | 11 |
| `RequestSqlCore` | 10 |
| `CarrierCore` | 9 |
| `AdminCartRulesControllerCore` | 9 |
| `CartControllerCore` | 9 |
| `Install` | 9 |
| `ToolsCore` | 9 |
| `CartRuleCore` | 8 |
