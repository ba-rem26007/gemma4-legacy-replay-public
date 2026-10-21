#!/usr/bin/env python3
"""Données de la section « Transfer beyond PHP » du papier, extraites des traces de l'éval locale Leaderboard.

Source : passage `lent1` (36 tâches Python publiques du concours principal, Gemma 4 31B QAT w4a16, vLLM 0.19.1,
harnais officiel swegemma 0.2.7, relais à la vitesse de l'évaluateur), bras v2 (soumission du 2 oct.) et V3b ;
rejeu `replay_script` (80 contextes d'édition × 4 tirages, variantes edit_file / script run_command).
Archives brutes : runs/leaderboard_local/lent1/res.tgz et runs/leaderboard_local/v4/res.tgz (non versionnées, trop lourdes).
Sorties (versionnées) : tool_calls.csv, results_lent1.csv (lent1 v2/V3b + v4r1), replay_script.jsonl, replay_noedit.jsonl.
"""
import csv, glob, json, shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LB = ROOT / "runs" / "leaderboard_local"
OUT = Path(__file__).resolve().parent

rows, res = [], []
for arm in ("base", "v3b"):
    for f in sorted(glob.glob(str(LB / f"lent1/runs/lent1/{arm}_*/results_{arm}/traces/*.json"))):
        task = Path(f).stem.replace("trace_", "")
        for s in json.load(open(f))["steps"]:
            for tc in s.get("tool_calls") or []:
                o = json.dumps(s.get("observation", {}))
                outcome = "missing_argument" if "mandatory input" in o else (
                    "ok" if tc["function_name"] != "edit_file" or '\\"ok\\"' in o else "other_error")
                rows.append({"arm": "v2" if arm == "base" else "v3b", "task": task, "tool": tc["function_name"], "outcome": outcome})
for run, arm, label in (("lent1/runs/lent1", "base", "v2"), ("lent1/runs/lent1", "v3b", "v3b"), ("v4/runs/v4r1", "v4", "v4")):
    for f in sorted(glob.glob(str(LB / f"{run}/{arm}_*/results_{arm}.jsonl"))):
        for l in open(f):
            r = json.loads(l)
            res.append({"run": run.split("/")[-1], "arm": label, "task": r["id"], "resolved": int(r["resolved"]),
                        "patch_chars": r["patch_chars"], "tool_calls": r["tool_calls"], "error": (r.get("error") or "")[:80]})
for name, data in (("tool_calls.csv", rows), ("results_lent1.csv", res)):
    with open(OUT / name, "w", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=list(data[0])); w.writeheader(); w.writerows(data)
for n in ("replay_script.jsonl", "replay_noedit.jsonl"):
    shutil.copy(LB / "v4" / n, OUT / n)
print(len(rows), "appels d'outils ;", len(res), "résultats de tâches")
