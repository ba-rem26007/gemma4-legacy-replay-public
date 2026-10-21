#!/usr/bin/env python3
"""Vignette du Writeup Kaggle (carte 560 × 280 px, plus une version ×2) : docs/figures/cover_560x280.png et cover_1120x560.png."""
from pathlib import Path
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle

OUT = Path(__file__).resolve().parent.parent / "docs" / "figures"
BG, INK, MUTED, ACC, OK, PHP = "#0f1d2b", "#f3f5f4", "#9fb0bd", "#5fb3e6", "#5cd18e", "#8f95d3"


def draw(scale):
    fig = plt.figure(figsize=(5.6, 2.8), dpi=100 * scale)
    ax = fig.add_axes([0, 0, 1, 1]); ax.set_xlim(0, 560); ax.set_ylim(0, 280); ax.axis("off")
    ax.add_patch(Rectangle((0, 0), 560, 280, color=BG))
    for x in range(0, 560, 28):                      # trame discrète « journal de rejeu »
        ax.plot([x, x], [0, 280], color="#16293b", lw=0.6, zorder=0)
    ax.text(24, 236, "GEMMA 4 · PAPER TRACK", color=ACC, fontsize=8.5, weight="bold", family="DejaVu Sans")
    ax.text(24, 196, "Making a Legacy PHP Monolith", color=INK, fontsize=16.5, weight="bold")
    ax.text(24, 170, "Verifiable for a Bug-Fixing Agent", color=INK, fontsize=16.5, weight="bold")
    ax.text(24, 142, "33 real PrestaShop bugs · hidden browser-replay oracles", color=MUTED, fontsize=8.6)
    # chaîne : ticket → agent → rejeu → verdict
    steps = [("ticket", PHP), ("Gemma 4", ACC), ("replay", ACC), ("✓ verdict", OK)]
    x = 24
    for i, (lab, c) in enumerate(steps):
        w = 96 if i != 3 else 100
        ax.add_patch(FancyBboxPatch((x, 52), w, 38, boxstyle="round,pad=0,rounding_size=7", fc=BG, ec=c, lw=1.6))
        ax.text(x + w / 2, 71, lab, color=c, fontsize=9.5, weight="bold", ha="center", va="center")
        if i < 3:
            ax.annotate("", (x + w + 22, 71), (x + w + 4, 71), arrowprops=dict(arrowstyle="-|>", color=MUTED, lw=1.3))
        x += w + 26
    ax.text(24, 22, "edit-tool failure transfers to Python  ·  negative fine-tuning result", color=MUTED, fontsize=7.6)
    return fig


for s, name in ((1, "cover_560x280.png"), (2, "cover_1120x560.png")):
    f = draw(s); f.savefig(OUT / name, dpi=100 * s, facecolor=BG); plt.close(f)
print("vignettes écrites dans", OUT)
