# Taxonomie des échecs — vivier TEST (4 essais × 33 bugs par condition)

Classement automatique (`bench/taxonomy.py`) à partir des traces et des verdicts réévalués.

| Catégorie | A | R |
|---|---|---|
| Résolu | 51 (39%) | 51 (39%) |
| Régression | 0 (0%) | 0 (0%) |
| Mauvais fichier (localisation) | 46 (35%) | 45 (34%) |
| Aucune édition exploitable | 19 (14%) | 18 (14%) |
| Patch inapplicable | 0 (0%) | 0 (0%) |
| Correctif appliqué mais faux | 16 (12%) | 18 (14%) |

## Lecture

- **Mauvais fichier** : l'agent ne lit jamais le fichier corrigé → cible du glossaire (condition C).
- **Aucune édition / patch inapplicable** : problème de déroulé ou de format (copie SEARCH inexacte, boucles de relecture).
- **Correctif faux** : bon endroit, mauvaise logique → cible d'un vérificateur fidèle (conditions B / O).

## Catégorie dominante par bug (8 tentatives A+R)

| Bug | Dominante | Détail |
|---|---|---|
| #40971 | Résolu | Résolu 8 |
| #41193 | Résolu | Résolu 8 |
| #41007 | Résolu | Résolu 8 |
| #40853 | Résolu | Résolu 7, Correctif appliqué mais faux 1 |
| #40651 | Résolu | Résolu 7, Aucune édition exploitable 1 |
| #41299 | Résolu | Résolu 7, Correctif appliqué mais faux 1 |
| #42004 | Résolu | Résolu 7, Correctif appliqué mais faux 1 |
| #41929 | Résolu | Résolu 7, Correctif appliqué mais faux 1 |
| #41652 | Résolu | Résolu 7, Mauvais fichier (localisation) 1 |
| #41468 | Résolu | Résolu 7, Mauvais fichier (localisation) 1 |
| #41130 | Résolu | Résolu 6, Correctif appliqué mais faux 2 |
| #41665 | Résolu | Résolu 6, Mauvais fichier (localisation) 2 |
| #40898 | Résolu | Résolu 5, Correctif appliqué mais faux 2, Aucune édition exploitable 1 |
| #41320 | Résolu | Résolu 3, Aucune édition exploitable 3, Mauvais fichier (localisation) 2 |
| #41530 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 5, Résolu 3 |
| #41675 | Correctif appliqué mais faux | Correctif appliqué mais faux 5, Résolu 3 |
| #41524 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 6, Résolu 2 |
| #41394 | Aucune édition exploitable | Aucune édition exploitable 4, Correctif appliqué mais faux 3, Résolu 1 |
| #40743 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 8 |
| #40070 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 4, Correctif appliqué mais faux 3, Aucune édition exploitable 1 |
| #41100 | Aucune édition exploitable | Aucune édition exploitable 6, Mauvais fichier (localisation) 2 |
| #41327 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 8 |
| #41412 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 8 |
| #40999 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 7, Aucune édition exploitable 1 |
| #41036 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 7, Correctif appliqué mais faux 1 |
| #41457 | Aucune édition exploitable | Aucune édition exploitable 8 |
| #41225 | Correctif appliqué mais faux | Correctif appliqué mais faux 8 |
| #41611 | Aucune édition exploitable | Aucune édition exploitable 7, Mauvais fichier (localisation) 1 |
| #41573 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 6, Aucune édition exploitable 2 |
| #41735 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 6, Correctif appliqué mais faux 2 |
| #41727 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 8 |
| #41570 | Mauvais fichier (localisation) | Mauvais fichier (localisation) 8 |
| #41923 | Correctif appliqué mais faux | Correctif appliqué mais faux 4, Aucune édition exploitable 3, Mauvais fichier (localisation) 1 |
