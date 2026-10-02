# Fine-Tuning QLoRA Gemma 4 sur Kaggle GPU (Version 15)

Ce document consigne la réalisation, les paramètres, le défi mémoire résolu et les résultats du fine-tuning autonome de Gemma 4 sur GPU Tesla T4 via Kaggle.

---

## 0. Procédure obligatoire : tester sur Google Colab AVANT Kaggle (décision du 1er oct. 2026)

Le quota GPU Kaggle (≈ 30 h/semaine, sessions de 12 h) sert **uniquement au run complet**. Toute modification du script
d'entraînement ou des données est d'abord testée sur **Google Colab (T4 16 Go, même GPU que Kaggle)**.
Leçon : le 1er oct., trois versions de la v16 (kernels 16-18) ont été lancées directement sur Kaggle pour déboguer des OOM.

1. Modifier `training/kaggle_kernel/train_kaggle.py` (et/ou les données `training/kaggle_dataset/`) ; si les données changent :
   `kaggle datasets version -p training/kaggle_dataset -m "…"` (le dataset seul ne consomme pas de GPU).
2. **Depuis le serveur, avec la CLI Colab officielle** (https://github.com/googlecolab/google-colab-cli, `uv tool install google-colab-cli`,
   autorisée une fois par copier-coller d'un code OAuth ; enveloppe `colab4` = IPv4 forcé, l'IPv6 sortant du serveur ne répond pas) :
   ```
   colab4 new -s v16 --gpu A100            # ou T4 pour reproduire Kaggle
   colab4 upload -s v16 trajectories/train.jsonl /content/data/train.jsonl   # idem self.jsonl, script, ~/.kaggle/access_token
   # SMOKE=1 DATA_DIR=/content/data WORK_DIR=/content/work python3 train_kaggle.py   (lancé en nohup via colab4 exec)
   tools/colab_tail.sh v16 /content/smoke.log  # suivi du journal (3 essais si la connexion websocket se coupe)
   colab4 download -s v16 /content/work_v16/gemma4_lora_final.zip training/lora_v16.zip ; colab4 stop -s v16
   ```
3. Sans la CLI : Terminal Colab + `training/colab_smoke.sh` (script via `tools/push_colab_script.sh`), ou `notebook/colab_smoke_training.ipynb`.
4. Critère : « ✅ Pré-test OK » et 2 pas sans OOM ; noter le pic mémoire par GPU et la durée d'un pas
   (durée du run ≈ nb de pas × durée d'un pas, à comparer à la limite de 12 h).
5. Seulement alors, le run complet : sur Colab (A100 : v16 ≈ 25 min, 1 053 unités de calcul disponibles le 1er oct.) ou sur Kaggle
   (`kaggle kernels push -p training/kaggle_kernel`, T4 ≈ 10 h).
6. Consigner dans `ETAT.md` : version du kernel, résultat du test Colab, durée estimée.

Note : le script n'utilise qu'**un** GPU (`device_map={"": 0}`), comme Colab : le test Colab reproduit la configuration Kaggle.
Écart connu : Colab est passé en Python 3.13 / Ubuntu 24.04 (sept. 2026), Kaggle est en 3.12 ; le script installe ses propres
versions de transformers / peft / bitsandbytes, mais un échec purement lié à l'environnement Colab n'invalide pas Kaggle.

## 1. Contexte & Objectifs

L'objectif du fine-tuning est d'adapter le modèle Gemma 4 à l'architecture PrestaShop 9.x à partir des trajectoires de résolution vérifiées issues de la boucle d'auto-apprentissage (chemins purs sans hallucinations, sans sorties propriétaires).
* **Date d'exécution** : 28 septembre 2026 (11h10 - 13h20 UTC).
* **Plateforme** : Kaggle GPU (2x Tesla T4, 15 Go VRAM par GPU).
* **Statut final** : `KernelWorkerStatus.COMPLETE`.

---

## 2. Données d'Entraînement

* **Source** : Dataset Kaggle `rmisoubeyrand/gemma4-prestashop-trajectories`.
* **Volume brut** : 1 007 trajectoires candidates.
* **Filtrage & Équilibrage** :
  * Dédoublonnage strict et plafonnement à max 2 trajectoires par bug.
  * Masquage causal : perte calculée **uniquement sur les tokens de réponse assistant** (`labels == -100` pour tous les tours système, utilisateur et retours d'outils).
  * 585 trajectoires pures retenues.
  * Longueur de contexte calibrée à 2 048 tokens.

---

## 3. Configuration Modèle & QLoRA

* **Modèle de base** : `google/gemma-4-e4b-it` (4 milliards de paramètres).
* **Quantification** : 4-bit NormalFloat (NF4), double quantification (`bnb_4bit_use_double_quant=True`), compute dtype `bfloat16` / `float16`.
* **Couches cibles LoRA** : Ciblage exclusif des projections linéaires du sous-module textuel `language_model` :
  * `q_proj`, `k_proj`, `v_proj`, `o_proj`, `gate_proj`, `up_proj`, `down_proj`.
  * Total : **258 modules linéaires adaptés** (exclusion explicite des tours vision et audio).
* **Hyperparamètres LoRA** :
  * Rang $r = 16$, $\alpha = 32$.
  * Dropout LoRA : $0.05$.
  * Paramètres entraînables : **34 881 536** sur 7 975 982 368 (0,437% du modèle).
* **Optimiseur** : `paged_adamw_8bit` (pagination automatique des états d'optimiseur vers la RAM CPU en cas de tension VRAM).
* **Paramètres d'entraînement** :
  * Époques : 3
  * Learning rate : $5 \times 10^{-5}$ avec scheduler cosinus.
  * Gradient accumulation steps : 8 (batch effectif = 16 avec 2 GPU).
  * Max gradient norm : 0.1 (stabilisation RMSNorm Gemma 4).

---

## 4. Défi Mémoire Résolu : `ChunkedLossTrainer`

### Le problème
Le vocabulaire de Gemma 4 compte **262 144 tokens**.
Avec une longueur de séquence de 2 048 tokens, le tenseur complet des logits `(batch, seq, vocab)` requiert :
$$2 \times 2048 \times 262144 \times 4 \text{ octets} \approx 4{,}29 \text{ Go}$$
Dans Transformers standard, `ForCausalLMLoss` effectue `logits = logits.float()`, ce qui alloue instantanément 4,29 Go de mémoire additionnelle, provoquant un `OutOfMemoryError` immédiat sur Tesla T4 (où la mémoire disponible après chargement du modèle est de ~2,3 Go).

### La solution : Micro-chunks de 256 tokens
Création d'un sous-classeur de `Trainer` : `ChunkedLossTrainer`.
1. Appel au modèle avec `logits_to_keep=1` et `output_hidden_states=True` (aucun tenseur de logits 4 Go n'est généré).
2. Récupération de l'état caché final `outputs.hidden_states[-1]`.
3. Sélection par masque booléen des seuls tokens de réponse assistant (`labels != -100`).
4. Projection linéaire par micro-chunks de 256 tokens :
   $$256 \times 262144 \times 4 \text{ octets} \approx 268 \text{ Mo}$$
5. Calcul de la cross-entropy par chunk et sommation différentiable.
**Résultat** : le tenseur de logits passe de 4,29 Go à 268 Mo (≈ −94 %). La VRAM totale avant/après n'a pas été mesurée (le chiffre « 28,4 → 13,8 Go » cité auparavant n'avait pas de source et a été retiré le 2 oct.).

---

## 5. Dynamique d'Apprentissage & Résultats

* **Durée totale** : 7 209 secondes (~2h00).
* **Courbe de perte** :
  * Époque 0.89 : Perte = **1.564** (norme gradient 2.906)
  * Époque 1.71 : Perte = **1.425** (norme gradient 2.366)
  * Époque 2.53 : Perte = **0.9309** (norme gradient 1.302)
  * Époque 3.00 : Perte moyenne globale = **1.192**
* **Stabilité** : Aucune instabilité numérique, la norme des gradients a décru de manière monotone de 2.9 à 1.3.

---

## 6. Artefacts Produits & Stockage

* **Archive complète** : `gemma4_lora_final.zip` (128 Mo).
* **Emplacement local extrait** : `training/lora_final/extracted/`
  * `adapter_model.safetensors` (134 Mo)
  * `adapter_config.json`
  * `tokenizer.json` (31 Mo)
  * `tokenizer_config.json`
  * `chat_template.jinja`
* **Miroir de téléchargement public** : `https://anniv.soubeyrand.dev/lora.zip`

---

## 7. Évolution vers le Corpus Compact v2 (641 trajectoires)

L'audit de la Phase 1 ([`docs/AUDIT_PHASE1.md`](AUDIT_PHASE1.md)) a révélé que sur les 585 exemples du run v15 initial, seuls **89 avaient été tokenisés** en raison du filtre `MAX_LEN = 2048` et de fenêtres de lecture trop larges (120 lignes de boilerplate).

La Phase 2 ([`docs/RAPPORT_PHASE2.md`](RAPPORT_PHASE2.md)) a résolu ce goulot d'étranglement :
1. **Nouveau corpus [`trajectories/train_compact.jsonl`](../trajectories/train_compact.jsonl)** : **660 trajectoires reconstruites et certifiées** à 100% par patch Git en mémoire.
2. **Taux de rétention de 97,1%** : Avec un fenêtrage affiné (`WINDOW=8`, `MAX_LINES=50`) et un seuil de 4 096 tokens, **641 exemples sont conservés**, soit une multiplication par **$7{,}2\times$** du volume d'entraînement.
3. **Apprentissage de la reprise** : Intégration de 17 trajectoires multi-tours d'auto-apprentissage (erreur d'oracle $\to$ correction $\to$ succès) sans fuite du test set ([`trajectories/train_recovery.jsonl`](../trajectories/train_recovery.jsonl)).
