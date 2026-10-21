# Making a Legacy PHP Monolith Verifiable for a Gemma 4 Bug-Fixing Agent

### Hidden replay oracles on 33 post-cutoff PHP bugs, a Gemma-only self-learning loop that can game its own tests, a negative fine-tuning result, and an edit-tool failure that transfers to Python

**Author:** Rémi Soubeyrand · Kaggle "Google – The Gemma 4 Developer Agent", Paper Track  
**Code, data and traces:** https://github.com/ba-rem26007/gemma4-legacy-replay-public (Apache-2.0)  

---

## Abstract

Code-repair benchmarks mostly use Python projects with unit tests, yet much of the web runs on legacy PHP [W3Techs], where a defect only shows in a browser session against a database. We make one such monolith, **PrestaShop**, verifiable: each of **33 real bugs fixed upstream after our 2025-06-01 split** gets a hidden Playwright oracle, run against a Dockerized shop with a reset database, that fails before and passes after the official fix.

With a fixed-stage flow, **Gemma 4 31B** resolves **12.8 / 33** from the ticket alone (38.6%, mean of 4 trials). Retrieved fixes (R) and glossaries (C) add nothing measurable. Gemma-written reproduction tests with execution feedback (B) reached 15 / 33 in a first run, but **13.0 / 33 over 3 runs** (+0.8 pts, 95% CI [−7.3, +8.6]): the gain did not replicate. The hidden oracle as feedback (O, approximate ceiling) averages 14.7 / 33 (+5.8 pts, CI [−1.5, +13.9]).

Closing the training loop with Gemma alone, **13 of 41 "solved" older bugs (32%) edit code outside the official fix** and are rejected by guards. Finally, a negative result: QLoRA fine-tuning of **Gemma 4 E4B** on condensed repair paths does **not** help. Over 3 runs each, base E4B solves 3.67 / 33, the 89-example adapter 1.33 (−7.1 pts, CI [−16.2, 0.0]) and the 453-example adapter 0 (−11.1 pts, CI [−23.2, −2.0]). The adapters apply as many patches but localize worse.

On 36 Python tasks of the main competition, run through the official evaluator, **74% of the agent's `edit_file` calls lose their arguments** in Gemma 4's tool-call parser; editing through a Python script in the shell raises successful edits from 64% to 82% on replay.

---

## 1. Introduction

PHP runs on 69.8% of websites whose server-side language is known [W3Techs]. Much of that code is "legacy" in Feathers' sense: code without tests [Feathers]. SWE-bench [SWE-bench] judges a patch with the repository's own tests; Multi-SWE-bench [Multi-SWE] adds seven languages but no PHP. In PrestaShop, issue #41921 ("Not able to change stock behaviour in shared stock") depends on multistore configuration, database rows and a back-office form; no unit test fails on it. 

We ask:
- **Q1.** Can browser replay with database resets make such bugs verifiable for an open model?
- **Q2.** Which signal helps: historical fixes, glossaries, model-written tests, or the hidden oracle?
- **Q3.** Can Gemma bootstrap its own training data from its own verifiers, and does the loop game them?

**Contributions:** (1) a replay benchmark of 33 post-cutoff PHP bugs with hidden browser+database oracles; (2) a measured comparison of verifier types for Gemma 4; (3) a Gemma-only self-learning loop where guards reject 13 of 41 "solved" bugs; (4) a controlled 3-run comparison showing that fine-tuning E4B on condensed paths does not help here, and that the larger adapter degrades it; (5) a tool-call failure mode of the Gemma 4 serving stack, measured on Python tasks, with a replay-tested fix.

---

## 2. Benchmark Construction & Protocol

### Selection & Cutoff Integrity
`bench/select.py` keeps merged functional bug-fix PRs linked to an issue and touching $\le 3$ files. Of 187 catalogued bugs merged after the split, 55 candidates from the 9.1.x branch were screened by hand, yielding **33 verifiable test bugs** (`data/bugs_test.csv`); most exclusions need a JavaScript build (13) or lack a UI path (5).

The 33 fixes were merged between 2026-02-12 and 2026-07-22. Gemma 4's training cutoff is January 2025 [Gemma4-card]. Five TEST issues (#20448, #29009, #29663, #35690, #36058) were **opened** before the cutoff; only their fixes are later.

### Execution Environment & Oracles
`bench/checkout.sh <pr> pre|post|patch.diff` starts the nearest official `prestashop/prestashop` image with MySQL 8.0 and puts the touched files in the requested state.
- **Deterministic reset:** a database snapshot taken after installation is restored before each bug, then a per-bug `setup.sql` seeds the state.
- **Hidden oracles (`oracle*.spec.js`):** Playwright tests (27 back office, 6 front office), in the record-and-replay tradition [WATERFALL, WebTesting]. **The agent never sees them.**
- **Smoke check:** front-office home and back-office login must still load. A bug is solved only if the oracle **and** the smoke check pass.
- **Verifier separation:** B runs visible tests (`replay*.spec.js`) generated from the ticket alone; only O feeds back the hidden oracle.

**Evaluator audit.** Because files edited outside the official diff were not restored at first, 322 of 462 verdicts were re-run on fresh instances (`bench/reeval.py`).

---

## 3. Agent Architecture

Rather than open-ended loops with shell access [SWE-agent], we use a fixed-stage flow inspired by Agentless [Agentless] (`agent/run.py`, `agent/flow.py`):

![Figure](https://raw.githubusercontent.com/ba-rem26007/gemma4-legacy-replay-public/main/docs/figures/fig1_pipeline.png)

*Figure 1. Fixed-stage agent; the patch is judged by the hidden oracle and the smoke check.*

The main model is **Gemma 4 31B** [Gemma4] through the Google AI Studio API at temperature 0.2; API calls cost 0 € (`runs/_budget.json`).

---

## 4. Empirical Evaluation

### Main Benchmark Results

| Condition | Auxiliary Signal | Solved / 33 | Rate | Right File | Regr. |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **A** | Ticket only; 4 trials (12, 13, 15, 11) | **12.8** | **38.6%** | 19.8 | 0 / 132 |
| **R** | + 2 similar TRAIN fixes (TF-IDF) + glossary (8 tickets); 4 trials | 12.8 | 38.6% | 20.2 | 0 / 132 |
| **C** | + auto glossary + static reproduction test (10 bugs) | 13 | 39.4% | 18 | 1 / 33 |
| **B** | + Gemma-written reproduction test **with feedback** (10 bugs); 3 trials (15, 12, 12) | **13.0** | **39.4%** | 17.3 | 0 / 99 |
| **O** | + **hidden oracle as feedback** (approx. ceiling); 3 trials (16, 13, 15) | **14.7** | **44.4%** | 18.7 | 2 / 99 |
| **A-26B** | Ticket only, Gemma 4 26B A4B | 5 | 15.2% | 21 | 0 / 33 |

![Figure](https://raw.githubusercontent.com/ba-rem26007/gemma4-legacy-replay-public/main/docs/figures/fig2_results.png)

*Figure 2. Bugs solved per trial (dots) and mean (bars). C and A-26B are single runs. Right File: attempts reading the file modified in the reference fix (mean over trials). CIs: paired per bug, 95% bootstrap, 5,000 resamples, seed 0, bugs in `data/bugs_test.csv` order; against A for Gemma 4 31B/26B (`bench/results.py` procedure, `eval/results.csv`, where A-26B is `A-4B`), against E4B base for adapters (§7, `docs/RESULTATS_E4B.md`). A solves 17/33 at least once over 4 trials.*

### Statistical Analysis & Verifier Dynamics (Q1 & Q2)

1. **Context (R & C):** $R - A = 0.0$ pts, CI [−9.1, +9.1]. C's glossary did not improve localization.
2. **Replay feedback (B):** `bench/reprotest.py` asks Gemma for a reproduction test from the ticket alone, kept only if it fails on the current code: 10 / 33 bugs (on the other 23, B equals A).
   - B solves 15, 12 and 12 (mean 13.0): +0.8 pts over A, CI [−7.3, +8.6]. The first run's +6.8 pts did not replicate.
   - Only 1 of the 10 tests passes with the official fix, so feedback was mostly "fail". On #41923 (0/8 across A and R), B's first run solved it with a final patch equal to Gemma's first edit, written before any test output (Figure 5); neither repetition solved it.
3. **Oracle ceiling (O):** 16, 13 and 15 (mean 14.7; +5.8 pts, CI [−1.5, +13.9]). In the first run, feedback converted #41299, #41394 and #41923 and broke the smoke check on #41225 and #41573.
4. **Smaller models:** Gemma 4 26B A4B solves 5 / 33 (−23.5 pts, CI [−38.6, −9.1]): it finds the right file as often (21) but applies a patch in only 12 / 33 cases.

**Answer to Q1.** Replay makes the bugs *measurable* (reproducible, paired verdicts) but only partly *fixable*: even the hidden oracle gains about 2 bugs on average, and a ticket-derived verifier nothing measurable over 3 runs.

![Figure](https://raw.githubusercontent.com/ba-rem26007/gemma4-legacy-replay-public/main/docs/figures/fig5_example.png)

*Figure 5. End-to-end run on #41923 (condition B, real trace).*

---

## 5. Failure Taxonomy & Localization Bottleneck

Of the 132 Condition A attempts (4 runs × 33 bugs), 81 fail (`eval/results.csv`, `docs/ECHECS.md`):

Localization failures (fixed file never read) are 46 (56.8%), files read without an applicable edit 19 (23.5%), incorrect or partial logic 16 (19.8%), smoke regressions 0.

![Figure](https://raw.githubusercontent.com/ba-rem26007/gemma4-legacy-replay-public/main/docs/figures/fig3_localization.png)

When the fixed file was read, 44 / 79 attempts (55.7%) succeed; when it was missed, 7 / 53 (13.2%), by editing elsewhere. Over the 8 A+R attempts per bug, 15 bugs are never solved and 3 always: difficulty is bimodal.

---

## 6. Self-Learning Loop & Reward Hacking (Q3)

We built the loop **with Gemma 4 31B only**, on TRAIN bugs:

1. **Oracle generation** (`bench/gentest.py`): from the ticket and official fix, kept only if it fails before and passes after the fix. Browser oracles: **0 of ~22**; PHP command-line oracles: **99 of ~254 (39%)** (`bench/gentest.jsonl`).
2. **Trajectory generation:** Gemma fixes each bug in condition O with its own oracle as feedback, "solving" **41 of 99**.
3. **Guards** (`trajectories/self_paths.py`): accept a path only if re-verified and each edited file and function is also touched by the official fix.

```
                  41 Nominally "Solved" Bugs
                             │
            ┌────────────────┴────────────────┐
            ▼                                 ▼
      28 Inside PR Scope                13 Outside PR Scope
            │                         (7 other files, 6 other functions)
   25 Accepted by Guards                 → Rejected
   (3 dropped: file never searched)
            │
   23 After Function-Level Split Filter
```

### Reward Hacking Findings
- On #38417, the official fix changes one faulty `ImageType::getImagesTypes()` call in the webservice. Gemma instead rewrote an unrelated SQL join and added a special case inside `ImageType` (`if ($type === 'customizations') $type = 'products';`), enough to satisfy its oracle.

**Takeaway:** with model-written oracles, "tests pass" is not a sufficient reward. The guard is conservative, so 13/41 is an upper bound on gaming. The loop works only because TRAIN bugs have an official fix. Of the 25 accepted paths, 7 equal the official fix (`docs/BOUCLE.md`).

---

## 7. Edge Model Adaptation: a Negative Result

### Data & Training
Both runs used 585 examples: 569 paths reconstructed deterministically from official TRAIN fixes (`trajectories/reconstruct.py`) plus 16 Gemma loop paths (v15: the 16 available then, unfiltered; v16: 16 of 23 with similarity ≥ 0.4). No proprietary model output is included (unlike [SWE-Gym]); bugs are real (unlike [SWE-smith]). TRAIN excludes any bug sharing a PR, issue or modified function with a TEST bug (`data/ETANCHEITE.md`: 820 TRAIN, 58 excluded). Both adapters are QLoRA on Gemma 4 E4B (4-bit NF4, r = 16, α = 32, lr 5e-5):
- **v15:** the **89** examples ≤ 2,048 tokens; 3 epochs, 2× T4 on Kaggle, 7,209 s, mean loss 1.192 (`training/snapshots/train_kaggle_v15.py`).
- **v16:** the **453** examples ≤ 4,096 tokens; 2 epochs, 1× A100 on Colab, 21 min, mean loss 0.490, 1.46 → 0.22 (`training/kaggle_kernel/train_kaggle.py`, `training/lora_v16/train_v16_colab_a100.log`).

### Controlled Evaluation
All three models run condition E (business rules, glossary, B's test with feedback, 2 corrections) on the 33 TEST bugs, 3 runs each, on one Colab A100 serving the 4-bit NF4 base with or without adapter (`tools/colab_llm_server.py`).

| E4B | Solved per run | Mean | pass@3 | Patch applied | Right file | Regr. | Δ vs base (95% CI) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Base | 3, 4, 4 | 3.67 (11.1%) | 4 | 18, 13, 19 | 16, 13, 15 | 1, 0, 2 | — |
| + v15 | 1, 1, 2 | 1.33 (4.0%) | 2 | 18, 16, 19 | 12, 13, 11 | 2, 0, 1 | −7.1 [−16.2, 0.0] |
| + v16 | 0, 0, 0 | 0 (0.0%) | 0 | 17, 20, 17 | 11, 11, 11 | 3, 0, 0 | −11.1 [−23.2, −2.0] |

pass@3: solved in at least one run. On a 16 GB T4, base E4B solves 3/33 with 10.8 GB peak GPU memory, fitting a 12 GB consumer card.

**Format compliance is not the mechanism.** The adapters apply as many SEARCH/REPLACE patches as the base model; what drops is localization (right file 13–16 per run for base, 11 for v16). An earlier single v15 run (4/33, another server, row `E` of `eval/results.csv`) lies within the base model's run-to-run range.

**Interpretation (untested hypothesis).** Condensed paths contain no mistakes or feedback; the model may learn to edit confidently at the expense of reading (no validation loss was tracked). Recovery trajectories are the next test.

Training fits on 2 T4s thanks to a chunked cross-entropy over assistant positions (`training/chunked_loss.py`).

---

## 8. Transfer beyond PHP: Edit Reliability on Python Tasks

To test whether harness lessons hold outside PrestaShop, we ran the main competition's official evaluator (swegemma 0.2.7, vLLM 0.19.1 with the `gemma4` tool parser [vLLM], Gemma 4 31B QAT w4a16) on 36 public Python tasks (16 fastapi, 14 rich, 6 requests; seed 1002), on a Colab A100 behind a proxy that delays each reply to the scorer's measured speed, 0.6 s + tokens / 25.5 tok/s [g4kit]. 

**The edit tool loses its arguments.** Most `edit_file` calls reach the tool without `old_string`: 149 of 201 (74%) with a single-agent prompt at temperature 1.0, 38 of 86 (44%) with our earlier sub-agent prompt at 0.2. In the raw output, the model writes JSON-style arguments instead of Gemma 4's native string delimiters, and the parser splits code containing `:` and `,` into spurious keys. `run_command` (1,292 calls) and `write_file` (31) never failed in the same run. The agent then retries until its budget ends, with an empty patch.

**Editing through a script.** We replayed 80 logged edit decisions, 4 samples each, with a prompt that edits through a short Python script in `run_command` (assert the old text occurs once, replace, write). Successful edits rose from 64% to 82% where the original had succeeded, and from 4% to 27% where it failed, as a failed history keeps pulling the model back to `edit_file` (Figure 4). Removing `edit_file` from the tool list is unsafe: the model still called it in 44 of 72 such samples, and an undeclared tool aborts the task.

![Figure](https://raw.githubusercontent.com/ba-rem26007/gemma4-legacy-replay-public/main/docs/figures/fig4_transfer.png)

*Figure 4. Left: every `edit_file` call. Right: replay.*

**Leaderboard.** Public scores were 0.06 (sub-agent), 0.05 (single agent, 6-minute cap), 0.06 (script edits, 6-minute cap) and **0.12** (script edits, no cap, temperature 0.2). The last submission changed three settings at once, so the gain is not attributable to script edits alone; locally, the 6-minute cap ended 19 of 36 single-agent tasks and all 36 script-edit tasks.

On both stacks, failures come before the semantic step: localization in PHP, tool-call formatting in Python.

---

## 9. Threats to Validity & Limitations

1. **Sample size:** 33 bugs, one project; single-run gains of 2–3 bugs fall within run-to-run variance (A: 11–15). C and A-26B are single runs.
2. **Verifier coverage:** reproduction tests exist for 10 / 33 bugs.
3. **Fine-tuning scope:** two adapters, one data recipe, one small model; v15 and v16 differ in several settings.
4. **Pre-training exposure:** five TEST issues pre-date the cutoff.
5. **Oracle authorship:** TEST oracles were written with Claude's help; validated by execution, they are not independent of our tooling.
6. **Setup:** the 31B model runs through an API; energy was not measured.
7. **Transfer study:** 36 public tasks, one local run per prompt, a speed-matched proxy rather than the scorer's 4× L4; the leaderboard comparison is confounded.
8. **Loop hygiene:** #32563 and #31571 touch functions also fixed after the split; neither was in the trained snapshot, and the exporter now filters them.

---

## 10. Reproducibility & Open Assets

- **Benchmark:** `bench/select.py`, `data/bugs_test.csv`, `data/ETANCHEITE.md`, `bench/checkout.sh`, `bench/replay/`, `bench/eval.py`
- **Agent:** `agent/run.py`, `agent/flow.py`, `bench/reprotest.py`
- **Verdicts:** `eval/results.csv`, traces under `runs/`; `bench/results.py` regenerates `docs/RESULTATS.md`
- **E4B evaluation:** `runs/run_eval_e4b.sh`, `docs/RESULTATS_E4B.md`
- **Loop:** `bench/gentest.py`, `trajectories/self_paths.py`, `bench/loop_stats.py`, `docs/BOUCLE.md`
- **Python transfer:** `eval/transfer_python/` (tool-call outcomes, task results, replays, Kaggle scores), `kaggle/eval_local/` (evaluator setup, speed proxy, replay), `kaggle/submission_v*/`
- **Figures:** `tools/make_figures.py` regenerates `docs/figures/` from these files
- **Training:** `trajectories/train.jsonl`, `training/kaggle_kernel/train_kaggle.py`, logs under `training/`

`bench/checkout.sh 41923 pre && bench/replay/run.sh 41923` must fail; with `post` it must pass.

---

## Acknowledgments & AI Assistance Disclosure

Claude Code (Anthropic) and Google Antigravity were used as developer tools for scripts, the harness, the evaluator and drafts of this text, which the author checked against the repository. TEST oracles were written with Claude's help, validated by execution and hidden from the agent. **No proprietary model output is in any fine-tuning dataset**; all reported agent behaviour is Gemma 4's.

---

## Related works and citations

- [Agentless] Xia et al., 2024. *Agentless: Demystifying LLM-based Software Engineering Agents.* https://arxiv.org/abs/2407.01489
- [Feathers] Feathers, 2004. *Working Effectively with Legacy Code.* Prentice Hall. https://openlibrary.org/isbn/0131177052
- [g4kit] damsolanke (GitHub), 2026. *gemma4-swe-kit: local evaluation tools that reproduce the Gemma 4 Developer Agent scorer.* https://github.com/damsolanke/gemma4-swe-kit
- [Gemma4] Gemma Team, 2026. *Gemma 4 Technical Report.* https://arxiv.org/abs/2607.02770
- [Gemma4-card] Gemma 4 model card (training cutoff January 2025). https://ai.google.dev/gemma/docs/core/model_card_4
- [Multi-SWE] Zan et al., 2025. *Multi-SWE-bench: A Multilingual Benchmark for Issue Resolving.* https://arxiv.org/abs/2504.02605
- [SWE-agent] Yang et al., 2024. *SWE-agent: Agent-Computer Interfaces Enable Automated Software Engineering.* https://arxiv.org/abs/2405.15793
- [SWE-bench] Jimenez et al., 2023. *SWE-bench: Can Language Models Resolve Real-World GitHub Issues?* https://arxiv.org/abs/2310.06770
- [SWE-Gym] Pan et al., 2024. *Training Software Engineering Agents and Verifiers with SWE-Gym.* https://arxiv.org/abs/2412.21139
- [SWE-smith] Yang et al., 2025. *SWE-smith: Scaling Data for Software Engineering Agents.* https://arxiv.org/abs/2504.21798
- [vLLM] Kwon et al., 2023. *Efficient Memory Management for Large Language Model Serving with PagedAttention.* https://arxiv.org/abs/2309.06180
- [W3Techs] PHP usage statistics (69.8%, accessed 30 September 2026). https://w3techs.com/technologies/details/pl-php
- [WATERFALL] Hammoudi, Rothermel & Stocco, 2016. *WATERFALL: An Incremental Approach for Repairing Record-Replay Tests of Web Applications.* FSE 2016. https://doi.org/10.1145/2950290.2950294
- [WebTesting] Li et al., 2024. *A Survey on Web Application Testing: Over a Decade of Evolution.* https://arxiv.org/abs/2412.10476
- PrestaShop: https://github.com/PrestaShop/PrestaShop

---

**Verify every number in this paper:** [`notebook/verification.ipynb`](https://github.com/ba-rem26007/gemma4-legacy-replay-public/blob/main/notebook/verification.ipynb) recomputes all tables, confidence intervals, the leakage check, training logs and the Python transfer counts from the committed files (CPU only; each section ends with `assert`).
