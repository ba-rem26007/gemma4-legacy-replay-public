# Gemma 4 E4B : base vs adaptateurs LoRA v15 / v16 — condition E, 33 bugs TEST, 3 runs (1-2 oct. 2026)

Même infrastructure pour les trois modèles : `tools/colab_llm_server.py` sur une A100 Colab (base 4 bits NF4 comme à
l'entraînement, message système fusionné au 1er tour comme à l'entraînement), tunnel SSH privé, agent `agent/run.py
--condition E --retries 2`, instances psbench2/3/4. Lanceur : `runs/run_eval_e4b.sh`.
IC : bootstrap apparié par bug, **méthode exacte de `bench/results.py`** (random.choices, graine 0, 5 000 tirages, bornes 125 et 4 875) ; recalcul : `notebook/verification.ipynb` §3. Ordre des bugs = `data/bugs_test.csv` (comme `bench/results.py`) : à graine fixe, les bornes dépendent de l’ordre de la liste. (Le bloc brut ci-dessous, calculé autrement, donne des bornes légèrement différentes.)

```
base  résolus/run [3, 4, 4] moy 3.67 (11.1%) | appliqués [18, 13, 19] | bon fichier [16, 13, 15] | régressions [1, 0, 2] | pass@3 4 | ['runs/20261001-153951-E', 'runs/20261001-184740-E', 'runs/20261001-210006-E']
   bugs résolus: [40651, 41007, 41130, 41193]
v15   résolus/run [1, 1, 2] moy 1.33 (4.0%) | appliqués [18, 16, 19] | bon fichier [12, 13, 11] | régressions [2, 0, 1] | pass@3 2 | ['runs/20261001-153953-E', 'runs/20261001-184742-E', 'runs/20261001-220448-E']
   bugs résolus: [41130, 41193]
v16   résolus/run [0, 0, 0] moy 0.00 (0.0%) | appliqués [17, 20, 17] | bon fichier [11, 11, 11] | régressions [3, 0, 0] | pass@3 0 | ['runs/20261001-153955-E', 'runs/20261001-184744-E', 'runs/20261001-215229-E']
   bugs résolus: []
v15 − base : -7.1 pts, IC 95 % [-16.2 ; +0.0]
v16 − base : -11.1 pts, IC 95 % [-22.2 ; -2.0]
```

## Lecture
- **Le LoRA n'aide pas E4B ici ; il le dégrade.** v16 (453 exemples, perte d'entraînement 0,22) : 0 résolu sur 99 essais,
  −11,1 pts vs base, IC [−23,2 ; −2,0] (exclut 0). v15 (89 exemples) : −7,1 pts, IC [−16,2 ; 0,0].
- Patchs appliqués ≈ identiques (base 13-19, v15 16-19, v16 17-20) : le LoRA n'améliore **pas** le respect du format
  SEARCH/REPLACE ; il réduit la localisation (bon fichier : base 13-16, v15 11-13, v16 11).
- L'ancien résultat « E = 4/33 » (v15, 28 sept., serveur ngrok, 1 run) est dans le bruit de la base (3-4/33 par run).
- Hypothèse (non testée) : chemins condensés = trajectoires « idéales » sans erreur ni retour ; le modèle apprend à éditer
  vite et sûr de lui sur des schémas TRAIN, au détriment de la lecture (v16 = surapprentissage, perte 1,46 → 0,22).
