# gemma4-legacy-replay

Code, data and traces for the Kaggle *Gemma 4 Developer Agent* Paper Track submission
**"Making a Legacy PHP Monolith Verifiable for a Gemma 4 Bug-Fixing Agent"** — paper: [`docs/KAGGLE_FINAL_WRITEUP.md`](docs/KAGGLE_FINAL_WRITEUP.md).

A Gemma 4 agent fixes **known, already-fixed** PrestaShop bugs (8.x/9.x, PHP). Each of the 33 TEST bugs was merged upstream after
our 2025-06-01 split (Gemma 4's declared training cutoff is January 2025) and has a hidden end-to-end oracle (Playwright or PHP CLI)
that fails before the official fix and passes after it, on a Docker PrestaShop reset for every attempt.

## Results in one table (33 TEST bugs, Gemma 4 31B unless noted)

| Condition | Solved | Notes |
|---|---|---|
| A · ticket only (mean of 4 runs) | 12.8 (38.6%) | 0 regressions |
| R · + 2 similar TRAIN fixes (4 runs) | 12.8 (38.6%) | R − A = 0.0, CI [−9.1, +9.1] |
| C · + domain glossary (1 run) | 13 | 1 regression |
| B · + Gemma-written repro test as feedback (1 run) | 15 (45.5%) | +6.8 pts vs A, CI [−2.3, +16.7], not significant |
| O · + hidden oracle as feedback (1 run) | 16 (48.5%) | approximate ceiling of this flow; 2 regressions |
| A · Gemma 4 26B A4B | 5 | |
| E · Gemma 4 E4B, rules (3 runs) | 3.67 (base) | + QLoRA v15: 1.33, + QLoRA v16: 0 — fine-tuning hurts (`docs/RESULTATS_E4B.md`) |

Verify every number: `notebook/verification.ipynb` (CPU, ~2 s). Regenerate: `python3 bench/results.py` → `docs/RESULTATS.md`, `eval/results.csv` (offline, no model call).

Self-learning loop (Gemma only, TRAIN bugs): Gemma writes an oracle (99 validated), fixes the bug with it (41 "solved"), and
13 of 41 (32%) edit code outside the official fix — "tests pass" is not a sufficient reward. See `docs/BOUCLE.md`.

## Layout

| Path | Content |
|---|---|
| `bench/` | bug selection (`select.py`), catalogue, official fix diffs, Docker env (`env/`), `checkout.sh`, replay oracles (`replay/<pr>/`), evaluation (`eval.py`, `reeval.py`, `results.py`), oracle generation (`gentest.py`) |
| `agent/` | fixed-flow agent (`run.py`, `flow.py`): localise → read → edit (SEARCH/REPLACE) → test |
| `runs/` | full traces of every run (`<run>/<pr>/trace.jsonl`, `patch.diff`, `result*.json`) |
| `trajectories/` | training data: `reconstruct.py` (paths rebuilt from official TRAIN fixes), `self_paths.py` (guarded Gemma paths) |
| `training/` | QLoRA on Kaggle (`kaggle_kernel/train_kaggle.py`, `chunked_loss.py`), training log, exact training snapshots (`snapshots/` for the evaluated v15 adapter, `kaggle_dataset/` for the current run) |
| `data/` | TEST list (`bugs_test.csv`), leakage check (`ETANCHEITE.md`) |
| `docs/` | paper, results, procedures (most internal docs are in French) |

## Reproduce

Requirements: Docker, Node 20, Python 3.10+.

```bash
./setup.sh                                   # clones PrestaShop into bench/ps + Playwright
cp .env.api.example .env                     # then set GEMMA_API_KEY (Google AI Studio), or use .env.local.example (Ollama)
bench/checkout.sh 41007 pre  && bench/replay/run.sh 41007    # oracle fails on the buggy code
bench/checkout.sh 41007 post && bench/replay/run.sh 41007    # oracle passes with the official fix
python3 agent/run.py --bugs 41007 --condition A              # agent attempt, trace in runs/<timestamp>-A/
python3 bench/eval.py 41007 runs/<timestamp>-A/41007/patch.diff
```

All commands: `docs/PROCEDURES.md`.

## Rules we followed

- Only known bugs already fixed upstream; nothing security-related is selected (`bench/select.py` filters it out).
- No proprietary-model output in training data: TRAIN paths are rebuilt deterministically from official fixes or produced by Gemma
  and verified by execution. Claude Code was used to build the tooling and to help write the hidden TEST oracles, which the
  evaluated agent never sees.
- Temporal TEST/TRAIN split with a function-level leakage filter.

## License

Apache-2.0 (`LICENSE`). PrestaShop is © PrestaShop SA and contributors, OSL-3.0; `bench/diffs/` are excerpts of its public history.
