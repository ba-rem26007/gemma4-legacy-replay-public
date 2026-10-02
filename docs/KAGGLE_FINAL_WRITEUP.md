# Making a Legacy PHP Monolith Verifiable for a Gemma 4 Bug-Fixing Agent

### Hidden replay oracles on 33 post-cutoff PHP bugs, a Gemma-only self-learning loop that can game its own tests, and a negative fine-tuning result

**Author:** Rémi Soubeyrand · Kaggle "Google – The Gemma 4 Developer Agent", Paper Track  
**Code, data and traces:** https://github.com/ba-rem26007/gemma4-legacy-replay-public (Apache-2.0)  

---

## Abstract

Code-repair benchmarks mostly use Python projects with unit tests, yet much of the web runs on legacy PHP [W3Techs], where a defect only shows in a browser session against a database. We make one such monolith, **PrestaShop**, verifiable: each of **33 real bugs fixed upstream after our 2025-06-01 split** gets a hidden Playwright oracle, run against a Dockerized shop with a reset database, that fails before and passes after the official fix.

With a fixed-stage flow, **Gemma 4 31B** resolves **12.8 / 33** from the ticket alone (38.6%, mean of 4 trials). Retrieved historical fixes (R) and glossaries (C) add nothing measurable. Gemma-written reproduction tests with execution feedback (B) reach **15 / 33** in a single run (+6.8 pts, 95% CI [−2.3, +16.7], $p \approx 0.11$, not significant). The hidden oracle as feedback (O, approximate ceiling) reaches 16 / 33.

Closing the training loop with Gemma alone, **13 of 41 "solved" older bugs (32%) edit code outside the official fix** and are rejected by guards. Finally, a negative result: QLoRA fine-tuning of **Gemma 4 E4B** on condensed repair paths does **not** help. Over 3 runs each on identical infrastructure, base E4B solves 3.67 / 33, the 89-example adapter 1.33 (−7.1 pts, CI [−16.2, 0.0]) and the 453-example adapter 0 (−11.1 pts, CI [−23.2, −2.0]). The adapters apply as many patches but localize worse.

---

## 1. Problem & Motivation

PHP runs on 69.8% of websites whose server-side language is known [W3Techs]. Much of that code is "legacy" in Feathers' sense: code without tests [Feathers]. SWE-bench [SWE-bench] judges a patch with the repository's own tests; Multi-SWE-bench [Multi-SWE] adds seven languages but no PHP. In PrestaShop, an issue such as #41921 ("Not able to change stock behaviour in shared stock") depends on multistore configuration, database rows and a back-office form; no upstream unit test fails on it. Teams that cannot send private code to third-party APIs need pipelines that run on open models.

We ask:
- **Q1.** Can browser replay with database resets make such bugs verifiable for an open model?
- **Q2.** Which signal helps: historical fixes, glossaries, model-written tests, or the hidden oracle?
- **Q3.** Can Gemma bootstrap its own training data from its own verifiers, and does the loop game them?

**Contributions:** (1) a replay benchmark of 33 post-cutoff PHP bugs with hidden browser+database oracles; (2) a measured comparison of verifier types for Gemma 4; (3) a Gemma-only self-learning loop where guards reject 13 of 41 "solved" bugs; (4) a controlled 3-run comparison showing that fine-tuning E4B on condensed paths does not help here, and that the larger adapter degrades it.

---

## 2. Benchmark Construction & Protocol

### Selection & Cutoff Integrity
`bench/select.py` keeps merged functional bug-fix PRs linked to an issue and touching $\le 3$ files. Of 187 catalogued bugs merged after the split, 55 candidates from the 9.1.x branch were screened by hand, yielding **33 verifiable test bugs** (`data/bugs_test.csv`); most exclusions need a JavaScript build (13) or have no reproducible UI path (5).

The 33 fixes were merged between 2026-02-12 and 2026-07-22. Gemma 4's declared training cutoff is January 2025 [Gemma4-card], so the split leaves 5 months of margin. Five TEST issues (#20448, #29009, #29663, #35690, #36058) were **opened** before the cutoff; only their fixes are later.

### Execution Environment & Oracles
`bench/checkout.sh <pr> pre|post|patch.diff` starts the nearest official `prestashop/prestashop` image with MySQL 8.0 and puts the touched files in the requested state.
- **Deterministic reset:** a database snapshot taken after installation is restored before each bug, then a per-bug `setup.sql` seeds the state.
- **Hidden oracles (`oracle*.spec.js`):** Playwright tests (27 back office, 6 front office), in the record-and-replay tradition [WATERFALL, WebTesting]. **The agent never sees them.**
- **Smoke check:** front-office home and back-office login must still load. A bug is solved only if the oracle **and** the smoke check pass.
- **Verifier separation:** B runs visible tests (`replay*.spec.js`) generated from the ticket alone; only O feeds back the hidden oracle.

**Evaluator audit.** Files edited outside the official diff were initially not restored between bugs; 322 of the 462 verdicts in `eval/results.csv` were re-run on fresh instances without calling the model again (`bench/reeval.py`).

---

## 3. Agent Architecture

Rather than open-ended loops with shell access [SWE-agent], we use a fixed-stage flow inspired by Agentless [Agentless] (`agent/run.py`, `agent/flow.py`):

```
[Issue Ticket] 
      │
      ▼
1. LOCATE  ──► Model outputs 3–8 keywords ──► git grep & path ranking
      │
      ▼
2. READ    ──► Model selects ≤ 3 files ──► keyword-ranked code windows
      │
      ▼
3. EDIT    ──► Model outputs SEARCH/REPLACE blocks (exact match required);
               may backtrack twice to search or read again
      │
      ▼
4. TEST    ──► (Feedback conditions only) Test output + edited files shown;
               up to 2 corrections.
```

The main model is **Gemma 4 31B** [Gemma4] through the Google AI Studio API at temperature 0.2; API calls cost 0 € (`runs/_budget.json`).

---

## 4. Empirical Evaluation

### Main Benchmark Results

| Condition | Auxiliary Signal | Solved / 33 | Rate | Right File | Regr. |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **A** | Ticket only; 4 trials (12, 13, 15, 11) | **12.8** | **38.6%** | 19.8 | 0 / 132 |
| **R** | + 2 similar TRAIN fixes (TF-IDF) + glossary (8 tickets); 4 trials | 12.8 | 38.6% | 20.2 | 0 / 132 |
| **C** | + auto glossary + static reproduction test (10 bugs) | 13 | 39.4% | 18 | 1 / 33 |
| **B** | + Gemma-written reproduction test **with feedback** (10 bugs) | **15** | **45.5%** | 17 | 0 / 33 |
| **O** | + **hidden oracle as feedback** (approx. ceiling) | **16** | **48.5%** | 19 | 2 / 33 |
| **A-26B** | Ticket only, Gemma 4 26B A4B | 5 | 15.2% | 21 | 0 / 33 |

*C, B, O and A-26B are single runs. Right File: attempts reading the file modified in the reference fix (mean over trials). CIs: paired per bug, 95% bootstrap, 5,000 resamples, seed 0, bugs in `data/bugs_test.csv` order; against A for Gemma 4 31B/26B (`bench/results.py` procedure, `eval/results.csv`, where A-26B is `A-4B`), against E4B base for adapters (§7, `docs/RESULTATS_E4B.md`). A solves 17/33 at least once over 4 trials.*

### Statistical Analysis & Verifier Dynamics (Q1 & Q2)

1. **Context (R & C):** $R - A = 0.0$ pts, CI [−9.1, +9.1]. C's glossary matched 16 tickets without improving localization (18 vs 19.8 right files).
2. **Replay feedback (B):** `bench/reprotest.py` asks Gemma for a reproduction test from the ticket alone, kept only if it fails on the current code: 10 / 33 bugs (on the other 23, B equals A).
   - B solves 15 / 33: +6.8 pts over A's mean, CI [−2.3, +16.7], one-sided sign-flip permutation $p \approx 0.11$ (two-sided 0.23), **not significant**. 15 equals A's best trial (11–15), and B reads the right file less often (17 vs 19.8). On the 10 feedback bugs B solves 6 vs 4.5 for A. Two earlier exploratory B runs on the 10 bugs, with an older flow, solved 4 and 3.
   - Only 1 of the 10 tests passes with the official fix; on the other 9 no patch ever passed it, so feedback was a constant "fail". On #41923 (0/8 across A and R, solved in all 3 B runs) the final patch equals Gemma's first edit, written before any test output: the gain comes from seeing the test, not from execution feedback.
3. **Oracle ceiling (O):** 16 / 33 (+9.8 pts, CI [+0.8, +20.5]). Feedback converted #41299, #41394 and #41923, and broke the smoke check on #41225 and #41573.
4. **Smaller models:** Gemma 4 26B A4B solves 5 / 33 (−23.5 pts, CI [−38.6, −9.1]). It reads the right file as often as the 31B model (21 vs 19.8) but applies a patch in only 12 / 33 cases.

**Answer to Q1.** Replay makes the bugs *measurable* (every verdict reproducible and paired) but only partly *fixable*: the hidden oracle gains about 3 bugs, and a ticket-derived verifier gained less in our single run, without statistical support at $N=33$.

---

## 5. Failure Taxonomy & Localization Bottleneck

Of the 132 Condition A attempts (4 runs × 33 bugs), 81 fail (`eval/results.csv`, `docs/ECHECS.md`):

```
┌─────────────────────────────────────────────────┬───────┬────────────┐
│ Failure Mode (81 failures)                      │ Count │ Percentage │
├─────────────────────────────────────────────────┼───────┼────────────┤
│ 1. Localization failure (fixed file never read) │  46   │   56.8%    │
│ 2. File read, no applicable edit                │  19   │   23.5%    │
│ 3. Incorrect logic / partial patch              │  16   │   19.8%    │
│ 4. Smoke regressions                            │   0   │    0.0%    │
└─────────────────────────────────────────────────┴───────┴────────────┘
```

| Condition A Attempts | Fixed File Read | Fixed File Missed | Total |
| :--- | :---: | :---: | :---: |
| **Solved** | 44 | 7 | 51 |
| **Unsolved** | 35 | 46 | 81 |
| **Total** | 79 | 53 | 132 |

When the fixed file was read, 44 / 79 attempts (55.7%) succeed; when it was missed, 7 / 53 (13.2%), by editing elsewhere. Category 2 comes from inexact SEARCH copies or read-loops. Over the 8 A+R attempts per bug, 15 bugs are never solved and 3 always: difficulty is bimodal.

---

## 6. Self-Learning Loop & Reward Hacking (Q3)

We built the loop **with Gemma 4 31B only**, on TRAIN bugs merged before the split:

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
- On #38168, it edited the right file but a different method, making a query return nothing.

**Takeaway:** when the oracle is model-written, "tests pass" is not a sufficient reward; the two outside-scope cases we inspected are symptomatic patches. The guard is conservative (it would also reject a valid fix placed elsewhere), so 13/41 is an upper bound on gaming, not a measured rate. The loop is usable only because TRAIN bugs have an official fix. Of the 25 accepted paths, 7 equal the official fix and 17 reach ≥ 0.4 similarity (`trajectories/self.jsonl`, `docs/BOUCLE.md`).

---

## 7. Edge Model Adaptation: a Negative Result

### Data & Training
Both runs used 585 examples: 569 paths reconstructed deterministically from official TRAIN fixes (`trajectories/reconstruct.py`) plus 16 Gemma loop paths (v15: the 16 available then, unfiltered; v16: 16 of 23 with similarity ≥ 0.4). No proprietary model output is included (unlike [SWE-Gym]), and bugs are real (unlike [SWE-smith]). TRAIN excludes any bug sharing a PR, issue or modified function with a TEST bug (`data/ETANCHEITE.md`: 820 TRAIN, 58 excluded). Both adapters are QLoRA on Gemma 4 E4B (4-bit NF4, r = 16, α = 32, lr 5e-5):
- **v15:** the **89** examples ≤ 2,048 tokens; 3 epochs, 2× T4 on Kaggle, 7,209 s, mean loss 1.192 (`training/snapshots/train_kaggle_v15.py`).
- **v16:** the **453** examples ≤ 4,096 tokens; 2 epochs, 1× A100 on Colab, 21 min, mean loss 0.490, 1.46 → 0.22 (`training/kaggle_kernel/train_kaggle.py`, `training/lora_v16/train_v16_colab_a100.log`).

### Controlled Evaluation
All three models run condition E (business rules, glossary, B's reproduction test with feedback on 10 bugs, 2 corrections) on the 33 TEST bugs, 3 runs each, on the same infrastructure: one Colab A100 serving the 4-bit NF4 base with or without adapter (`tools/colab_llm_server.py`, `runs/run_eval_e4b.sh`).

| E4B | Solved per run | Mean | pass@3 | Patch applied | Right file | Regr. | Δ vs base (95% CI) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Base | 3, 4, 4 | 3.67 (11.1%) | 4 | 18, 13, 19 | 16, 13, 15 | 1, 0, 2 | — |
| + v15 | 1, 1, 2 | 1.33 (4.0%) | 2 | 18, 16, 19 | 12, 13, 11 | 2, 0, 1 | −7.1 [−16.2, 0.0] |
| + v16 | 0, 0, 0 | 0 (0.0%) | 0 | 17, 20, 17 | 11, 11, 11 | 3, 0, 0 | −11.1 [−23.2, −2.0] |

pass@3: solved in at least one of the 3 runs. Base E4B solves #40651, #41007, #41130 and #41193 at least once; v15 only #41130 and #41193; v16 nothing in 99 attempts.

**Format compliance is not the mechanism.** The adapters apply as many SEARCH/REPLACE patches as the base model; what drops is localization (right file 13–16 per run for base, 11 for v16). An earlier single v15 run (4/33, another server, row `E` of `eval/results.csv`) lies within the base model's run-to-run range.

**Interpretation (untested hypothesis).** Condensed paths are ideal trajectories without mistakes or feedback; the model may learn to edit confidently on TRAIN patterns at the expense of reading (no validation loss was tracked; v16's training loss fell from 1.46 to 0.22). On this benchmark, fine-tuning a small model on condensed paths does not help; recovery trajectories or more varied data are the next test.

### Memory-Efficient Chunked Loss
Gemma 4's vocabulary has 262,144 entries. Upcasting full float32 logits takes 2.15 GB per 2,048-token sequence, 4.29 GB for a micro-batch of 2 on 2 T4s, which ran out of memory (`docs/FINETUNING_KAGGLE.md`). `training/chunked_loss.py` projects only assistant positions, 256 tokens at a time:

```python
for i in range(0, active_h.size(0), chunk_size):
    logits_chunk = head(active_h[i:i+chunk_size]).float()
    total_loss += F.cross_entropy(logits_chunk, active_l[i:i+chunk_size], reduction="sum")
```

The peak logits tensor drops from 4.29 GB to about 268 MB (≈ −94%) **for that tensor**; total VRAM was not measured on T4.

---

## 8. Threats to Validity & Limitations

1. **Sample size:** 33 bugs, one project; power to detect +6.8 pts is limited ($p \approx 0.11$). B, C, O and A-26B are single runs.
2. **Verifier coverage:** generated reproduction tests exist for 10 / 33 bugs, and 9 never passed.
3. **Fine-tuning scope:** two adapters, one data recipe, one small model; v15 and v16 differ in length, data size, epochs (3 vs 2), Gemma-path filter and hardware.
4. **Pre-training exposure:** five TEST issue texts pre-date the model cutoff.
5. **Oracle authorship:** TEST oracles were written with Claude's help; validated by execution and hidden from the agent, they are not independent of our tooling.
6. **Setup:** the 31B model is evaluated through an API, not a local build. Energy was not measured.
7. **Loop hygiene:** #32563 and #31571 touch functions also fixed after the split; neither was in the trained snapshot, and the exporter now filters them.

---

## 9. Reproducibility & Open Assets

- **Benchmark:** `bench/select.py`, `data/bugs_test.csv`, `data/ETANCHEITE.md`, `bench/env/docker-compose.yml`, `bench/checkout.sh`, `bench/replay/`, `bench/eval.py`, `bench/reeval.py`
- **Agent:** `agent/run.py`, `agent/flow.py`, `bench/reprotest.py`
- **Verdicts:** `eval/results.csv`, traces under `runs/`; `bench/results.py` regenerates `docs/RESULTATS.md`
- **E4B evaluation:** `runs/run_eval_e4b.sh`, `tools/colab_llm_server.py`, `docs/RESULTATS_E4B.md` (run folders, CIs)
- **Loop:** `bench/gentest.py`, `trajectories/self_paths.py`, `bench/loop_stats.py`, `docs/BOUCLE.md`
- **Training:** `trajectories/train.jsonl`, `training/chunked_loss.py`, `training/snapshots/train_kaggle_v15.py`, `training/kaggle_kernel/train_kaggle.py`, `training/lora_v16/train_v16_colab_a100.log`

`bench/checkout.sh 41923 pre && bench/replay/run.sh 41923` must fail; with `post` it must pass.

---

## Acknowledgments & AI Assistance Disclosure

Claude Code (Anthropic) and Google Antigravity were used as developer tools for scripts, the Docker harness, the evaluator and drafts of this text, which the author checked against the repository. The TEST oracles were written with Claude's help, validated by execution, and hidden from the evaluated agent. **No proprietary model output is in any fine-tuning dataset.** Training examples are reconstructed deterministically from official fixes or produced by Gemma 4 and validated by execution. All reported agent behaviour is Gemma 4's.

---

## Related works and citations

- [Agentless] Xia et al., 2024. *Agentless: Demystifying LLM-based Software Engineering Agents.* https://arxiv.org/abs/2407.01489
- [Feathers] Feathers, 2004. *Working Effectively with Legacy Code.* Prentice Hall. https://openlibrary.org/isbn/0131177052
- [Gemma4] Gemma Team, 2026. *Gemma 4 Technical Report.* https://arxiv.org/abs/2607.02770
- [Gemma4-card] Gemma 4 model card (training cutoff January 2025). https://ai.google.dev/gemma/docs/core/model_card_4
- [Multi-SWE] Zan et al., 2025. *Multi-SWE-bench: A Multilingual Benchmark for Issue Resolving.* https://arxiv.org/abs/2504.02605
- [SWE-agent] Yang et al., 2024. *SWE-agent: Agent-Computer Interfaces Enable Automated Software Engineering.* https://arxiv.org/abs/2405.15793
- [SWE-bench] Jimenez et al., 2023. *SWE-bench: Can Language Models Resolve Real-World GitHub Issues?* https://arxiv.org/abs/2310.06770
- [SWE-Gym] Pan et al., 2024. *Training Software Engineering Agents and Verifiers with SWE-Gym.* https://arxiv.org/abs/2412.21139
- [SWE-smith] Yang et al., 2025. *SWE-smith: Scaling Data for Software Engineering Agents.* https://arxiv.org/abs/2504.21798
- [W3Techs] PHP usage statistics (69.8%, accessed 30 September 2026). https://w3techs.com/technologies/details/pl-php
- [WATERFALL] Hammoudi, Rothermel & Stocco, 2016. *WATERFALL: An Incremental Approach for Repairing Record-Replay Tests of Web Applications.* FSE 2016. https://doi.org/10.1145/2950290.2950294
- [WebTesting] Li et al., 2024. *A Survey on Web Application Testing: Over a Decade of Evolution.* https://arxiv.org/abs/2412.10476
- PrestaShop source and fixes: https://github.com/PrestaShop/PrestaShop

---

**Verify every number in this paper:** [`notebook/verification.ipynb`](https://github.com/ba-rem26007/gemma4-legacy-replay-public/blob/main/notebook/verification.ipynb) recomputes all tables, confidence intervals, the leakage check and the training logs from the committed files (CPU only, about 2 seconds; each section ends with `assert`).
