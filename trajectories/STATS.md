# Statistiques du Corpus d'Entraînement SFT

Coupure temporelle stricte (*Temporal Cutoff*) : **2025-06-01**.  
- **Vivier TRAIN historique** : ~4 800 bugs officiels PrestaShop pré-cutoff.
- **Vivier TEST d'évaluation** : 42 incidents réels (33 incidents fermés post-cutoff 9.1.x + 9 incidents cœur réévalués).

---

## 1. Composition des Trajectoires

| Source | Trajectoires Brutes | Trajectoires Retenues | Tokens Estimés | Perte Moyenne |
|---|:---:|:---:|:---:|:---:|
| **PRs Historiques Reconstruites (`train.jsonl`)** | 820 | **569** | 2 212 795 | ~1.19 |
| **Auto-Rejeu Déterministe (`self.jsonl`)** | 45 | **25** | ~98 000 | ~1.05 |
| **Corpus Final d'Entraînement QLoRA** | 865 | **585** | **~2,21 M** | **1.192** |

---

## 2. Garde-fous Anti-Triche et Motifs de Rejet

Afin d'éviter tout raccourci (*reward hacking*), chaque trajectoire est validée par les règles suivantes :
* **Correspondance stricte des fonctions** : Chaque bloc `SEARCH/REPLACE` généré doit modifier exclusivement des fonctions impactées par la pull request humaine officielle.
* **Intégrité binaire** : Le patch s'applique sans décalage (`git apply --check`) et valide la compilation syntaxique PHP (`php -l`).
* **Distribution des motifs de rejet** :
  - `ok` : 569 PRs validées
  - `exclu_etancheite` : 58 (touchant des fichiers sensibles ou post-cutoff)
  - `fichier_absent_recherche` : 54 (mots-clés n'ayant pas ramené le bon fichier)
  - `hors_format` : 93 (dérive hors du schéma strict `SEARCH/REPLACE`)
  - `trop_long` : 37 (dépassement du plafond de séquence)
  - `search_non_unique` : 5 (blocs `SEARCH` dupliqués dans le fichier)
  - `non_reproductible` : 4

---

## 3. Métrologie Séquentielle

* **Nombre moyen de tokens / exemple** : 3 889 tokens (médiane : 3 856 tokens).
* **Plafond de séquence** : 8 000 tokens (les exemples supérieurs sont écartés pour éviter toute troncature du patch final).
* **ChunkedLossTrainer** : Projection des logits calculée par micro-blocs de **256 tokens** sur `labels != -100` (assistant uniquement).
* **Pic de VRAM de la perte** : Réduit de **94%** (< 300 Mo de VRAM), permettant l'entraînement complet sur une simple Tesla T4 16 Go sans OOM.
