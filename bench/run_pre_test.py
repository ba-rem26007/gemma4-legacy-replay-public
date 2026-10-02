#!/usr/bin/env python3
"""Exécution des tests en état AVANT (pre) pour valider l'échec initial du bug ou de l'évolution.

Usage :
  PSB=1 python3 bench/run_pre_test.py <pr>
  PSB=1 python3 bench/run_pre_test.py --catalog bench/catalogs/8.1.x/improvements.jsonl --limit 5
"""

import argparse
import json
import os
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent


def get_pr_metadata(pr_num):
    """Recherche la PR dans l'ensemble des sous-catalogues."""
    for cat_file in HERE.glob("catalogs/*/*.jsonl"):
        with open(cat_file, "r", encoding="utf-8") as f:
            for line in f:
                if line.strip():
                    item = json.loads(line)
                    if item.get("pr") == pr_num:
                        return item
    # Fallback catalog.jsonl
    if (HERE / "catalog.jsonl").exists():
        with open(HERE / "catalog.jsonl", "r", encoding="utf-8") as f:
            for line in f:
                if line.strip():
                    item = json.loads(line)
                    if item.get("pr") == pr_num:
                        return item
    return None


def run_checkout_pre(pr_num, psb=1):
    """Met en place l'environnement PrestaShop sur l'état AVANT le patch."""
    cmd = ["bash", str(HERE / "checkout.sh"), str(pr_num), "pre"]
    env = os.environ.copy()
    env["PSB"] = str(psb)
    print(f"[{pr_num}] Checkout état 'pre' (avant patch)...")
    res = subprocess.run(cmd, env=env, capture_output=True, text=True, timeout=600)
    return res.returncode == 0, res.stdout + res.stderr


def run_test_in_container(pr_num, psb=1):
    """Exécute l'oracle PHP ou le test correspondant dans le conteneur PrestaShop."""
    proj = "psbench" if psb == 1 else f"psbench{psb}"
    container = f"{proj}-ps-1"

    # Vérifie si un oracle PHP existe déjà pour cette PR
    oracle_candidates = [
        HERE / "replay" / f"g{pr_num}" / "oracle_gemma.php",
        HERE / "replay" / str(pr_num) / "oracle.php",
        HERE / "replay" / str(pr_num) / "oracle_auto.php"
    ]
    oracle_file = next((p for p in oracle_candidates if p.exists()), None)

    if not oracle_file:
        return False, "Aucun fichier oracle PHP trouvé (nécessite gentest.py)"

    # Copie de l'oracle dans le conteneur
    copy_cmd = ["docker", "cp", str(oracle_file), f"{container}:/tmp/oracle_check.php"]
    cp_res = subprocess.run(copy_cmd, capture_output=True, text=True)
    if cp_res.returncode != 0:
        return False, f"Erreur copie docker: {cp_res.stderr}"

    # Exécution dans le conteneur
    exec_cmd = ["docker", "exec", container, "php", "/tmp/oracle_check.php"]
    exec_res = subprocess.run(exec_cmd, capture_output=True, text=True, timeout=120)

    # En état PRE : le test DOIT échouer (code retour != 0)
    fails_in_pre = (exec_res.returncode != 0)
    return fails_in_pre, exec_res.stdout + exec_res.stderr


def main():
    parser = argparse.ArgumentParser(description="Lancement des tests sur l'état avant (pre)")
    parser.add_argument("pr", nargs="?", type=int, help="Numéro de PR unique")
    parser.add_argument("--catalog", help="Chemin d'un sous-catalogue JSONL")
    parser.add_argument("--limit", type=int, default=10, help="Nombre max de PRs à traiter depuis le catalogue")
    parser.add_argument("--psb", type=int, default=int(os.environ.get("PSB", "1")), help="Numéro d'instance psbench (1..4)")
    args = parser.parse_args()

    prs_to_test = []
    if args.pr:
        prs_to_test.append(args.pr)
    elif args.catalog:
        cat_p = Path(args.catalog)
        if not cat_p.exists():
            print(f"Catalogue introuvable : {args.catalog}")
            sys.exit(1)
        with open(cat_p, "r", encoding="utf-8") as f:
            for line in f:
                if line.strip():
                    prs_to_test.append(json.loads(line)["pr"])
                if len(prs_to_test) >= args.limit:
                    break
    else:
        print("Spécifiez un numéro de PR ou un catalogue avec --catalog.")
        sys.exit(1)

    print(f"=== Vérification des tests en état AVANT (pre) pour {len(prs_to_test)} ticket(s) (PSB={args.psb}) ===\n")
    results = []

    for pr in prs_to_test:
        meta = get_pr_metadata(pr)
        title = meta.get("title", "") if meta else ""
        print(f"\n--- PR #{pr} : {title[:60]} ---")

        # 1. Checkout en PRE
        ok_co, msg_co = run_checkout_pre(pr, args.psb)
        if not ok_co:
            print(f"❌ Échec checkout pre: {msg_co[:200]}")
            results.append({"pr": pr, "status": "checkout_error", "fails_in_pre": False})
            continue

        # 2. Exécution du test en PRE
        fails_pre, out_test = run_test_in_container(pr, args.psb)
        if fails_pre:
            print(f"✅ SUCCÈS : Le test ÉCHOUE bien en 'pre' (le bug/manque est capté fidèlement).")
            status = "reproduced_ok"
        else:
            print(f"⚠️ ATTENTION : Le test passe ou n'a pas pu s'exécuter en 'pre'.")
            status = "not_failing_or_missing"

        results.append({
            "pr": pr,
            "title": title,
            "status": status,
            "fails_in_pre": fails_pre,
            "output_preview": out_test[:300]
        })

    # Sauvegarde des résultats
    out_log = HERE / "pre_test_results.jsonl"
    with open(out_log, "a", encoding="utf-8") as f:
        for r in results:
            f.write(json.dumps(r, ensure_ascii=False) + "\n")

    print(f"\nRésultats consignés dans {out_log}")


if __name__ == "__main__":
    main()
