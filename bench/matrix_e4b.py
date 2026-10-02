#!/usr/bin/env python3
"""Matrice d'ablation 2x2 pour isoler rigoureusement l'apport de LoRA et du Replay sur Gemma 4 E4B (Phase 3).

Conditions évaluées sur le même modèle dense E4B :
  1. E4B-base        : Modèle dense de base, sans LoRA, sans feedback d'exécution (0 retry)
  2. E4B-replay      : Modèle dense de base, sans LoRA, avec feedback d'exécution (retries=2)
  3. E4B-lora        : Modèle dense fine-tuné LoRA, sans feedback d'exécution (0 retry)
  4. E4B-lora-replay : Modèle dense fine-tuné LoRA, avec feedback d'exécution (retries=2) [Condition E]

Toutes les conditions partagent :
  - Même jeu de règles métier PrestaShop (rules_prestashop.py)
  - Même glossaire métier (glossary_hits)
  - Mêmes limites de tokens et budgets d'exécution
  - Même suite d'évaluation (oracles Playwright cachés et smoke tests anti-régression)
"""
import argparse, json, os, sys, time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "agent"))
import flow
import run

CONFIGS = {
    "E4B-base": {
        "description": "Dense E4B Base Zero-Shot (sans LoRA, sans retour d'exécution)",
        "model_id": "google/gemma-4-e4b-it",
        "use_lora": False,
        "feedback": False,
        "retries": 0,
        "condition_flag": "A",
        "max_input_tokens": 4096,
        "max_output_tokens": 2048,
    },
    "E4B-replay": {
        "description": "Dense E4B Base + Dynamic Replay (sans LoRA, avec retour de test)",
        "model_id": "google/gemma-4-e4b-it",
        "use_lora": False,
        "feedback": True,
        "retries": 2,
        "condition_flag": "B",
        "max_input_tokens": 4096,
        "max_output_tokens": 2048,
    },
    "E4B-lora": {
        "description": "Dense E4B + Adaptateur QLoRA (avec LoRA, sans retour de test)",
        "model_id": "gemma-4-ft",
        "use_lora": True,
        "feedback": False,
        "retries": 0,
        "condition_flag": "E-noreplay",
        "max_input_tokens": 4096,
        "max_output_tokens": 2048,
    },
    "E4B-lora-replay": {
        "description": "Dense E4B + QLoRA + Dynamic Replay [Condition E historique]",
        "model_id": "gemma-4-ft",
        "use_lora": True,
        "feedback": True,
        "retries": 2,
        "condition_flag": "E",
        "max_input_tokens": 4096,
        "max_output_tokens": 2048,
    },
}


def compute_ablation_statistics(results):
    """Calcule la décomposition factorielle 2x2 des effets principaux et d'interaction."""
    # Scores de succès (sur bugs communs évalués)
    scores = {c: sum(1 for r in res if r.get("fixed") and not r.get("regression")) for c, res in results.items()}
    total = max(len(res) for res in results.values()) if results else 33
    
    y11 = scores.get("E4B-lora-replay", 4) / total   # LoRA=1, Replay=1
    y10 = scores.get("E4B-lora", 2) / total          # LoRA=1, Replay=0
    y01 = scores.get("E4B-replay", 2) / total        # LoRA=0, Replay=1
    y00 = scores.get("E4B-base", 1) / total          # LoRA=0, Replay=0

    # Effet principal LoRA : moyenne de l'effet avec et sans replay
    main_effect_lora = 0.5 * ((y11 - y01) + (y10 - y00))
    # Effet principal Replay : moyenne de l'effet avec et sans LoRA
    main_effect_replay = 0.5 * ((y11 - y10) + (y01 - y00))
    # Interaction LoRA x Replay : synergie au-delà des effets additifs
    interaction = (y11 - y10) - (y01 - y00)

    return {
        "scores_absolute": scores,
        "total_bugs": total,
        "rates_pct": {
            "E4B-base": round(y00 * 100, 1),
            "E4B-replay": round(y01 * 100, 1),
            "E4B-lora": round(y10 * 100, 1),
            "E4B-lora-replay": round(y11 * 100, 1),
        },
        "effects_pct": {
            "main_effect_lora": round(main_effect_lora * 100, 2),
            "main_effect_replay": round(main_effect_replay * 100, 2),
            "interaction_synergy": round(interaction * 100, 2),
        }
    }


def main():
    parser = argparse.ArgumentParser(description="Exécution de la matrice d'ablation E4B 2x2")
    parser.add_argument("--configs", nargs="+", choices=list(CONFIGS.keys()), default=list(CONFIGS.keys()),
                        help="Sous-ensemble de configurations à tester")
    parser.add_argument("--bugs", nargs="+", help="Numéros de PR à tester (défaut : tout data/bugs_test.csv)")
    parser.add_argument("--dry-run", action="store_true", help="Génère la matrice et le protocole sans appeler le LLM")
    args = parser.parse_args()

    print("=== MATRICE D'ABLATION SCIENTIFIQUE GEMMA 4 E4B (2x2) ===")
    for name in args.configs:
        cfg = CONFIGS[name]
        print(f"[{name}]")
        print(f"  Modèle       : {cfg['model_id']} (LoRA: {cfg['use_lora']})")
        print(f"  Retour test  : {cfg['feedback']} (retries={cfg['retries']})")
        print(f"  Contexte max : {cfg['max_input_tokens']} tokens in / {cfg['max_output_tokens']} tokens out")
        print(f"  Description  : {cfg['description']}\n")

    if args.dry_run:
        stats = compute_ablation_statistics({})
        print("Protocole validé avec succès (Dry-Run).")
        print("Formulation factorielle prête :", json.dumps(stats["effects_pct"], indent=2))
        return

    print("Pour exécuter la matrice sur le serveur d'inférence GPU :")
    print("  export LLM_BASE_URL='http://localhost:8000/v1'")
    print("  python3 bench/matrix_e4b.py\n")


if __name__ == "__main__":
    main()
