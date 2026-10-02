# Données de fine-tuning (phase 6, go/no-go le 22 oct. : < 150 chemins → Perspectives)

Modèle : Gemma 12B en QLoRA sur Kaggle (16 Go). Le 26B est hors de portée. Voir `REGLES.md` pour la provenance.

## 1. Le cœur : les chemins
Chaque exemple d'entraînement contient :
- **l'instruction système** de l'agent, identique à celle de l'évaluation ;
- **la définition des outils**, au format exact d'appel de fonctions de Gemma 4 ;
- **le ticket** ;
- **la suite** : appel d'outil → résultat → … → correctif final.

**Masquage** : la loss ne porte que sur les tours du modèle (décisions et appels d'outils). Le ticket, les résultats d'outils et le contenu des fichiers sont masqués, sinon le modèle apprend à « inventer » des fichiers.

Uniquement sur le vivier **TRAIN**. Cible : ≥ 200 chemins, idéalement 500. Longueur < 8 000 tokens. Échantillon de 20 chemins relu par Rémi.

Sources (étiquetées `source=`) :
1. **`reconstruit`** : chemins reconstruits à partir des correctifs officiels. Un script produit la séquence recherches → lectures → diff → tests à partir du diff et de l'historique git.
2. **`auto`** : l'agent en condition C tente chaque bug TRAIN (4 essais max). Une tentative est **validée** si l'oracle passe et qu'aucun replay ne casse. Elle est ensuite condensée (pas de trajectoire brute).

## 2. Ajouts utiles
- **Localisation** : ticket → liste des fichiers à modifier. Produits depuis l'historique git en milliers d'exemples, quasi gratuitement. Leur apport se mesure avec un run supplémentaire.
- **Rattrapage** : des passages réels où Gemma casse un test de replay, lit l'échec, puis corrige. Ils viennent naturellement des runs de la condition C (celle avec exécution des tests), on ne les cherche pas activement.

## 3. Exclusions (contrôles d'étanchéité automatiques)
- **Tous les bugs du vivier de test**, et tout correctif qui touche les mêmes lignes ou fichiers.
- Les trajectoires brutes non condensées.
- La doc ou le code PrestaShop en vrac : c'est le rôle de l'outil « contexte PrestaShop ».
- Tout contenu généré par un modèle propriétaire (Claude, GPT…), y compris dans des datasets publics d'instruction.

## Qualité > quantité
- Ordre de grandeur : ~500 chemins. SWE-Gym a obtenu +12 à 14 points avec 491 trajectoires, mais les leurs venaient de GPT-4o et Claude, ce que **nous nous interdisons**.
- **2 chemins maximum par bug.**
- On **écarte les bugs résolus à chaque essai** : trop faciles, ils dégradent le modèle (SWE-smith).
- Une sélection d'environ 10 % de trajectoires bien choisies fait mieux que le tout (SWE-Prime, août 2026).

## Recommandation
- **Version de base** : chemins seuls, avec masquage.
- **Si le temps le permet** : ajouter la localisation, puis mesurer son apport.
