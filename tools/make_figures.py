#!/usr/bin/env python3
"""Figures du papier (docs/figures/*.png), recalculées depuis les fichiers du dépôt. Aucun modèle.

fig1_pipeline.png   chaîne benchmark + agent + vérification (schéma)
fig2_results.png    bugs résolus / 33 par condition (31B, 26B) et E4B base / v15 / v16 (un point par essai)
fig3_localization.png  réussite selon que le fichier corrigé a été lu ou non (condition A, 132 essais)
fig4_transfer.png   tâches Python : sort des appels edit_file, et rejeu edit_file vs script run_command
Usage : python3 tools/make_figures.py   (matplotlib requis)
"""
import collections, csv, json
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "docs" / "figures"
OUT.mkdir(parents=True, exist_ok=True)
INK, MUTED, ACC, OK, BAD, WARN = "#1c2220", "#6b7570", "#1f5f8b", "#2e8b57", "#c0392b", "#d68910"
plt.rcParams.update({"font.size": 10, "axes.spines.top": False, "axes.spines.right": False,
                     "axes.edgecolor": MUTED, "axes.labelcolor": INK, "xtick.color": INK, "ytick.color": INK})


def fig1():
    fig, ax = plt.subplots(figsize=(10, 3.5)); ax.set_xlim(0, 10); ax.set_ylim(0, 3.5); ax.axis("off")
    def box(x, y, w, h, title, sub, color):
        ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.04,rounding_size=0.08", fc="white", ec=color, lw=1.6))
        ax.text(x + w / 2, y + h * 0.66, title, ha="center", va="center", fontsize=10, weight="bold", color=color)
        ax.text(x + w / 2, y + h * 0.3, sub, ha="center", va="center", fontsize=8, color=INK)
    def arrow(x1, y1, x2, y2, label=""):
        ax.annotate("", (x2, y2), (x1, y1), arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1.3))
        if label:
            ax.text((x1 + x2) / 2, (y1 + y2) / 2 + 0.12, label, ha="center", fontsize=7.5, color=MUTED)
    ax.text(0.05, 3.2, "Agent (Gemma 4, fixed stages)", fontsize=9, color=ACC, weight="bold")
    box(0.05, 1.95, 1.3, 0.95, "Ticket", "issue text only", INK)
    box(1.7, 1.95, 1.4, 0.95, "1. LOCATE", "keywords → git grep", ACC)
    box(3.45, 1.95, 1.4, 0.95, "2. READ", "≤ 3 files, windows", ACC)
    box(5.2, 1.95, 1.5, 0.95, "3. EDIT", "SEARCH/REPLACE,\n≤ 2 backtracks", ACC)
    box(7.05, 1.95, 1.4, 0.95, "4. TEST", "feedback conditions\n(B visible, O hidden)", ACC)
    for a, b in ((1.35, 1.7), (3.1, 3.45), (4.85, 5.2), (6.7, 7.05)):
        arrow(a, 2.42, b, 2.42)
    ax.annotate("", (5.95, 2.95), (7.75, 2.95), arrowprops=dict(arrowstyle="-|>", color=WARN, lw=1.2, connectionstyle="arc3,rad=0.35"))
    ax.text(6.85, 3.3, "≤ 2 corrections", ha="center", fontsize=7.5, color=WARN)
    ax.text(0.05, 1.45, "Verification (hidden from the agent)", fontsize=9, color=OK, weight="bold")
    box(1.7, 0.2, 2.2, 1.0, "checkout.sh", "nearest PrestaShop image\n+ MySQL, DB snapshot reset", OK)
    box(4.3, 0.2, 2.3, 1.0, "Hidden oracle", "Playwright BO/FO replay\n+ per-bug setup.sql", OK)
    box(7.0, 0.2, 1.6, 1.0, "Smoke check", "home + BO login", OK)
    box(8.85, 0.2, 1.1, 1.0, "Verdict", "solved =\nboth pass", INK)
    arrow(7.75, 1.95, 2.8, 1.22, "patch")
    for a, b in ((3.9, 4.3), (6.6, 7.0), (8.6, 8.85)):
        arrow(a, 0.7, b, 0.7)
    fig.tight_layout(); fig.savefig(OUT / "fig1_pipeline.png", dpi=180); plt.close(fig)


def jitter(vals):
    """Décale horizontalement les points de même valeur pour qu'ils restent visibles."""
    seen = collections.Counter(); out = []
    for v in vals:
        out.append((seen[v] - (vals.count(v) - 1) / 2) * 0.09); seen[v] += 1
    return out


def fig2():
    per = collections.defaultdict(lambda: collections.Counter())
    for r in csv.DictReader(open(ROOT / "eval" / "results.csv")):
        per[r["condition"]][r["essai"]] += r["resolu"] in ("1", "True", "true")
    conds = [("A", "A\nticket"), ("R", "R\n+fixes"), ("C", "C\n+glossary"), ("B", "B\n+repro test"), ("O", "O\n+oracle"), ("A-4B", "26B A4B\nticket")]
    E4B = {"base": ["20261001-153951-E", "20261001-184740-E", "20261001-210006-E"],
           "v15": ["20261001-153953-E", "20261001-184742-E", "20261001-220448-E"],
           "v16": ["20261001-153955-E", "20261001-184744-E", "20261001-215229-E"]}
    e = {m: [sum(bool(x.get("fixed")) for x in json.load(open(ROOT / "runs" / d / "summary.json"))["results"]) for d in ds] for m, ds in E4B.items()}
    fig, (a1, a2) = plt.subplots(1, 2, figsize=(10, 3.6), gridspec_kw={"width_ratios": [2.1, 1]})
    for i, (c, lab) in enumerate(conds):
        vals = list(per[c].values()); mean = sum(vals) / len(vals)
        color = ACC if c != "A-4B" else MUTED
        a1.bar(i, mean, color=color, alpha=0.25 if len(vals) > 1 else 0.6, width=0.6)
        a1.scatter([i + d for d in jitter(vals)], vals, color=color, zorder=3, s=22)
        a1.text(i + 0.33, mean, f"{mean:.1f}", ha="left", va="center", fontsize=9, color=INK)
    a1.set_xticks(range(len(conds)), [l for _, l in conds]); a1.set_ylabel("bugs solved / 33"); a1.set_ylim(0, 20)
    a1.set_title("Gemma 4 31B (and 26B A4B), 33 TEST bugs", fontsize=10, color=INK)
    a1.text(0.99, 0.97, "dots = trials; A, R: 4 trials; B, O: 3; C, 26B: 1", transform=a1.transAxes, ha="right", va="top", fontsize=8, color=MUTED)
    for i, (m, vals) in enumerate(e.items()):
        mean = sum(vals) / 3
        a2.bar(i, mean, color=[ACC, WARN, BAD][i], alpha=0.3, width=0.6)
        a2.scatter([i + d for d in jitter(vals)], vals, color=[ACC, WARN, BAD][i], zorder=3, s=22)
        a2.text(i + 0.33, max(mean, 0.15), f"{mean:.2f}", ha="left", va="center", fontsize=9)
    a2.set_xticks(range(3), ["E4B base", "+ v15\n(89 ex.)", "+ v16\n(453 ex.)"]); a2.set_ylim(0, 6)
    a2.set_title("E4B, condition E, 3 runs each", fontsize=10, color=INK)
    fig.tight_layout(); fig.savefig(OUT / "fig2_results.png", dpi=180); plt.close(fig)
    return per, e


def fig3():
    rows = [r for r in csv.DictReader(open(ROOT / "eval" / "results.csv")) if r["condition"] == "A"]
    t = collections.Counter((r["bon_fichier"] in ("1", "True", "true"), r["resolu"] in ("1", "True", "true")) for r in rows)
    read, miss = (t[(True, True)], t[(True, True)] + t[(True, False)]), (t[(False, True)], t[(False, True)] + t[(False, False)])
    fig, ax = plt.subplots(figsize=(6, 2.6))
    for i, (lab, (s, n)) in enumerate((("fixed file read", read), ("fixed file missed", miss))):
        ax.barh(i, n, color="#e6e9e7"); ax.barh(i, s, color=OK if i == 0 else BAD)
        ax.text(n + 1, i, f"{s} / {n} solved ({s / n:.0%})", va="center", fontsize=9)
    ax.set_yticks([0, 1], ["fixed file read", "fixed file missed"]); ax.invert_yaxis(); ax.set_xlim(0, 100)
    ax.set_xlabel("condition A attempts (4 trials × 33 bugs)")
    fig.tight_layout(); fig.savefig(OUT / "fig3_localization.png", dpi=180); plt.close(fig)
    return read, miss


def fig4():
    tc = collections.Counter((r["arm"], r["tool"], r["outcome"]) for r in csv.DictReader(open(ROOT / "eval" / "transfer_python" / "tool_calls.csv")))
    rep = collections.defaultdict(collections.Counter)
    for l in open(ROOT / "eval" / "transfer_python" / "replay_script.jsonl"):
        r = json.loads(l); rep[(r["variante"], r["origine"])][r["resultat"]] += 1
    fig, (a1, a2) = plt.subplots(1, 2, figsize=(10, 3.4))
    for i, (arm, lab) in enumerate((("v2", "sub-agent prompt\nT 0.2"), ("v3b", "single-agent prompt\nT 1.0"))):
        ok, miss, oth = (tc[(arm, "edit_file", k)] for k in ("ok", "missing_argument", "other_error"))
        n = ok + miss + oth; left = 0
        for v, c, name in ((ok, OK, "applied"), (miss, BAD, "argument lost"), (oth, MUTED, "other error")):
            a1.barh(i, v / n * 100, left=left, color=c, label=name if i == 0 else None)
            if v / n > 0.08:
                a1.text(left + v / n * 50, i, f"{v}", ha="center", va="center", color="white", fontsize=9, weight="bold")
            left += v / n * 100
        a1.text(101, i, f"n = {n}", va="center", fontsize=8.5, color=MUTED)
    a1.set_yticks([0, 1], ["sub-agent prompt, T 0.2", "single agent, T 1.0"]); a1.invert_yaxis(); a1.set_xlim(0, 112)
    a1.set_xlabel("% of edit_file calls"); a1.legend(frameon=False, fontsize=8, loc="lower center", bbox_to_anchor=(0.45, 1.0), ncol=3)
    a1.set_title("edit_file outcomes, 36 Python tasks", fontsize=10, color=INK, pad=22)
    labels, xs = [], []
    for j, (orig, lab) in enumerate((("ok", "original call\nsucceeded"), ("perdu", "original call\nlost arguments"))):
        for k, (var, c) in enumerate((("origine", MUTED), ("script", ACC))):
            cnt = rep[(var, orig)]; n = sum(cnt.values()); v = (cnt["ok"] + cnt["ok-script"]) / n * 100
            x = j * 2.6 + k
            a2.bar(x, v, color=c, width=0.8, label=("edit_file (as is)" if var == "origine" else "edit via Python script in run_command") if j == 0 else None)
            a2.text(x, v + 2, f"{v:.0f}%", ha="center", fontsize=9)
        xs.append(j * 2.6 + 0.5); labels.append(lab)
    a2.set_xticks(xs, labels); a2.set_ylim(0, 125); a2.set_yticks(range(0, 101, 20)); a2.set_ylabel("successful edits (%)")
    a2.legend(frameon=False, fontsize=8, loc="upper center", ncol=1); a2.set_title("Replay of 80 edit decisions × 4 samples", fontsize=10, color=INK)
    fig.tight_layout(); fig.savefig(OUT / "fig4_transfer.png", dpi=180); plt.close(fig)
    return tc, rep


def fig5():
    """Exemple de bout en bout : #41923 (ticket #41921), condition B, run 20260928-092011-B (trace réelle)."""
    run = ROOT / "runs" / "20260928-092011-B" / "41923"
    steps = [json.loads(l) for l in open(run / "trace.jsonl")]
    res = json.loads((run / "result.json").read_text())
    kw0 = json.loads(steps[0]["assistant"].strip("`").replace("json", "", 1))["keywords"]
    files1 = json.loads(steps[1]["assistant"].strip("`").replace("json", "", 1))["files"]
    kw2 = json.loads(steps[2]["assistant"].strip("`").replace("json", "", 1))["keywords"]
    files3 = json.loads(steps[3]["assistant"].strip("`").replace("json", "", 1))["files"]
    tests = [s for s in steps if s["step"] == "corriger"]
    short = lambda p: p.split("/")[-1]
    rows = [
        ("Ticket #41921", INK, "Multistore + shared stock: changing \"deny orders when out of stock\" updates\nthe base product only, not its combinations (issue text only is given to the agent)."),
        ("1. LOCATE", ACC, "keywords: " + ", ".join(kw0) + "\n→ git grep ranks 25 files"),
        ("2. READ", ACC, "reads " + ", ".join(short(f) for f in files1) + "\n(right area, wrong files)"),
        ("backtrack", WARN, f"asks for a new keyword: {kw2[0]}\n→ re-ranks → reads {short(files3[0])}  (the file of the official fix)"),
        ("3. EDIT", ACC, "SEARCH/REPLACE in updateCombinationOutOfStockType(): the raw UPDATE on stock_available\nis replaced by StockAvailable::setProductOutOfStock(), which handles shared-stock groups"),
        ("4. TEST (visible)", WARN, f"Gemma's own reproduction test fails {len(tests)} times on a navigation assertion of the test itself;\nthe final patch is the first edit, written before any test output (§4)"),
        ("Hidden oracle", OK, "Playwright BO replay + SQL check on combinations (never shown to the agent): "
         + ("PASS" if res["fixed"] in (True, "True") else "FAIL") + "\nsmoke check: PASS · solved in " + str(res["turns"]) + " model turns · official fix: same method, StockAvailable::addSqlShopParams()"),
    ]
    fig, ax = plt.subplots(figsize=(10, 6.2)); ax.set_xlim(0, 10); ax.set_ylim(0, len(rows)); ax.axis("off")
    for i, (title, color, text) in enumerate(rows):
        y = len(rows) - i - 0.9
        ax.add_patch(FancyBboxPatch((0.05, y), 1.85, 0.75, boxstyle="round,pad=0.02,rounding_size=0.06", fc="white", ec=color, lw=1.6))
        ax.text(0.975, y + 0.375, title, ha="center", va="center", fontsize=9.5, weight="bold", color=color)
        ax.text(2.1, y + 0.375, text, ha="left", va="center", fontsize=8.6, color=INK, linespacing=1.4)
        if i < len(rows) - 1:
            ax.annotate("", (0.975, y - 0.12), (0.975, y), arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1.1))
    fig.tight_layout(); fig.savefig(OUT / "fig5_example.png", dpi=180); plt.close(fig)
    return kw0, files1, kw2, files3, res, len(tests)


if __name__ == "__main__":
    fig1(); per, e = fig2(); read, miss = fig3(); tc, rep = fig4()
    kw0, files1, kw2, files3, res, ntests = fig5()
    assert kw2 == ["updateCombinationOutOfStockType"] and files3 == ["src/Adapter/Product/Combination/Repository/CombinationRepository.php"]
    assert res["fixed"] in (True, "True") and ntests == 2
    assert sorted(per["A"].values()) == [11, 12, 13, 15] and e == {"base": [3, 4, 4], "v15": [1, 1, 2], "v16": [0, 0, 0]}
    assert read == (44, 79) and miss == (7, 53), (read, miss)
    assert tc[("v3b", "edit_file", "missing_argument")] == 149 and tc[("v2", "edit_file", "missing_argument")] == 38
    print("5 figures écrites dans", OUT)
