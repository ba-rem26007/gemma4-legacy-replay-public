#!/usr/bin/env python3
"""Lance l'agent Gemma (déroulé fixe) sur des bugs, évalue le patch, journalise la trajectoire complète.

Usage : python3 agent/run.py --bugs 35902 35384 35322 --condition B [--model gemma-4-26b-a4b-it] [--retries 2]
LLM : API compatible OpenAI. Variables (.env) :
  GEMMA_API_KEY   clé (Google AI Studio, OpenRouter… ; vide pour Ollama)
  LLM_BASE_URL    défaut https://generativelanguage.googleapis.com/v1beta/openai  (Ollama : http://<ip>:11434/v1)
  LLM_MODEL       défaut gemma-4-26b-a4b-it
Sortie : runs/<run_id>/<pr>/trace.jsonl + patch.diff + result.json, runs/<run_id>/summary.json
"""
import argparse, json, os, re, sys, time, urllib.request
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "bench"))
import flow  # noqa: E402
from eval import evaluate  # noqa: E402

ROOT = flow.ROOT


def load_env():
    p = ROOT / ".env"
    if p.exists():
        for l in p.read_text().splitlines():
            if "=" in l and not l.lstrip().startswith("#"):
                k, v = l.split("=", 1)
                os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))


RATE_FILE = ROOT / "runs" / "_rate.json"


def rate_limit(est_tokens):
    """Limiteur partagé entre processus : ≤ TPM_LIMIT tokens d'entrée par minute (quota Gemma 4 31B : 16 000)."""
    import fcntl
    limit = int(os.environ.get("TPM_LIMIT", 15000))
    RATE_FILE.parent.mkdir(exist_ok=True)
    while True:
        with open(str(RATE_FILE) + ".lock", "w") as lk:
            fcntl.flock(lk, fcntl.LOCK_EX)
            now = time.time()
            hist = [h for h in (json.loads(RATE_FILE.read_text()) if RATE_FILE.exists() else []) if now - h[0] < 62]
            used = sum(h[1] for h in hist)
            if used + est_tokens <= limit or not hist:
                hist.append([now, est_tokens]); RATE_FILE.write_text(json.dumps(hist))
                return
            wait = 62 - (now - hist[0][0])
        time.sleep(max(wait, 2))


def fit(messages, max_tokens=13000):
    """Plafond par requête : tronque les blocs CONTENU (fichiers lus) les plus longs si le prompt dépasse."""
    est = lambda ms: sum(len(m["content"]) for m in ms) // 3
    ms = [dict(m) for m in messages]
    while est(ms) > max_tokens:
        i = max((k for k, m in enumerate(ms) if m["role"] == "user"), key=lambda k: len(ms[k]["content"]))
        c = ms[i]["content"]
        if len(c) < 2000:
            break
        ms[i]["content"] = c[: int(len(c) * 0.7)] + "\n[… tronqué pour respecter le quota …]"
    return ms, est(ms)


def chat(messages, model, temperature=0.2, seed=42, max_tokens=16384):
    """Appel chat/completions compatible OpenAI via curl (urllib bloque en IPv6 sur ce serveur).
    Les blocs <thought>…</thought> de Gemma 4 sont retirés de la réponse (conservés dans la trace brute)."""
    import re, subprocess
    base = os.environ.get("LLM_BASE_URL", "https://generativelanguage.googleapis.com/v1beta/openai").rstrip("/")
    messages, est = fit(messages)
    effective_max = min(max_tokens, 1024) if ("ngrok" in base or "localhost" in base or "127.0.0.1" in base) else max_tokens
    body = json.dumps({"model": model, "messages": messages, "temperature": temperature,
                       "max_tokens": effective_max})  # seed non supporté par l'API Gemini (fixé en local)
    for attempt in range(7):
        rate_limit(est)
        r = subprocess.run(["curl", "-s", "-m", "600", "-w", "\n%{http_code}", f"{base}/chat/completions",
                            "-H", "Content-Type: application/json",
                            "-H", f"Authorization: Bearer {os.environ.get('GEMMA_API_KEY', '')}",
                            "-H", "ngrok-skip-browser-warning: true",
                            "--data-binary", "@-"], input=body, capture_output=True, text=True)
        out, _, code = r.stdout.rpartition("\n")
        if code == "200":
            d = json.loads(out)
            raw = d["choices"][0]["message"].get("content") or ""
            clean = re.sub(r"<thought>.*?(</thought>|$)", "", raw, flags=re.S).strip()
            return clean, {**d.get("usage", {}), "raw_len": len(raw)}
        if code in ("429", "500", "503", "000") and attempt < 6:
            m = re.search(r'"retryDelay":\s*"(\d+)', out)
            wait = int(m.group(1)) + 5 if m else 60 * (attempt + 1)
            print(f"   HTTP {code}, nouvel essai dans {wait}s", flush=True)
            time.sleep(wait); continue
        raise RuntimeError(f"LLM HTTP {code}: {re.sub(r'\s+', ' ', out)[:1500]}")


BUDGET_FILE = ROOT / "runs" / "_budget.json"
BUDGET_EUR = 30.0


def spend(usage):
    """Compteur de coût cumulé (tous runs). Prix en €/M tokens via LLM_PRICE_IN / LLM_PRICE_OUT (Gemma 4 sur l'API : gratuit)."""
    b = json.loads(BUDGET_FILE.read_text()) if BUDGET_FILE.exists() else {"in": 0, "out": 0, "eur": 0.0, "calls": 0}
    i, o = usage.get("prompt_tokens", 0), usage.get("total_tokens", 0) - usage.get("prompt_tokens", 0)
    b["in"] += i; b["out"] += o; b["calls"] += 1
    b["eur"] += i / 1e6 * float(os.environ.get("LLM_PRICE_IN", 0)) + o / 1e6 * float(os.environ.get("LLM_PRICE_OUT", 0))
    BUDGET_FILE.parent.mkdir(exist_ok=True); BUDGET_FILE.write_text(json.dumps(b, indent=1))
    return b["eur"]


def catalog():
    return {c["pr"]: c for c in map(json.loads, open(ROOT / "bench" / "catalog.jsonl"))}


def replay_spec(pr):
    d = ROOT / "bench" / "replay" / str(pr)
    return "\n\n".join(p.read_text() for p in sorted(d.glob("replay*.spec.js"))) if d.exists() else ""  # jamais oracle*


def scripted(bug):
    """Politique « reconstruit » : réponses tirées du chemin reconstruit (aucun modèle) → valide la chaîne."""
    sys.path.insert(0, str(ROOT / "trajectories"))
    import reconstruct
    msgs, why = reconstruct.build(bug)
    if not msgs:
        raise SystemExit(f"#{bug['pr']} : chemin non reconstructible ({why})")
    replies = iter(m["content"] for m in msgs if m["role"] == "assistant")
    return lambda _msgs, _model: (next(replies, ""), {})


def run_bug(bug, condition, model, retries, out, policy="llm"):
    global chat
    if policy == "reconstruit":
        chat = scripted(bug)
    out.mkdir(parents=True, exist_ok=True)
    trace = open(out / "trace.jsonl", "w")
    base = bug["base_commit"]
    msgs = [{"role": "system", "content": flow.SYSTEM}]

    def turn(step, user, tool=None, tool_result=None):
        msgs.append({"role": "user", "content": user})
        t = time.time()
        reply, usage = chat(msgs, model)
        if spend(usage) > BUDGET_EUR:
            raise SystemExit(f"BUDGET dépassé ({BUDGET_EUR} €) : arrêt")
        msgs.append({"role": "assistant", "content": reply})
        trace.write(json.dumps({"step": step, "user": user, "assistant": reply, "seconds": round(time.time() - t, 1),
                                "usage": usage, "tool": tool, "tool_result": tool_result}, ensure_ascii=False) + "\n")
        trace.flush()
        return reply

    # 1. LOCALISER
    kws = flow.parse_json(turn("localiser", flow.msg_ticket(bug, condition, replay_spec(bug["pr"]))), "keywords")
    hits = flow.grep(base, kws)
    # 2. LIRE
    files = [f for f in flow.parse_json(turn("lire", flow.msg_grep(hits), "grep", {"keywords": kws, "hits": hits}), "files")
             if flow.show(base, f)][:flow.MAX_FILES_READ]
    initial_files = list(files)
    all_files_read = set(files)
    contents = {f: flow.windows(flow.show(base, f), kws) for f in files}
    # 3. ÉDITER (avec au plus 2 retours arrière : relire d'autres fichiers ou relancer une recherche)
    reply = turn("editer", flow.msg_read(contents), "read", {"files": files})
    state, result, backtracks = {}, None, 0

    def backtrack(reply):
        """Si la réponse demande une relecture / recherche au lieu d'éditer, on l'exécute."""
        nonlocal backtracks, files, contents, kws, all_files_read
        while backtracks < 2 and not flow.parse_edits(reply) and re.search(r'"(files|keywords)"', reply):
            backtracks += 1
            if '"keywords"' in reply:
                kws = flow.parse_json(reply, "keywords")
                h = flow.grep(base, kws)
                reply = turn("relocaliser", flow.msg_grep(h), "grep", {"keywords": kws, "hits": h})
            new = [f for f in flow.parse_json(reply, "files") if flow.show(base, f)][:flow.MAX_FILES_READ]
            if new:
                files = new
                all_files_read.update(new)
                contents = {f: flow.windows(flow.show(base, f), kws) for f in files}
                reply = turn("relire", flow.msg_read(contents), "read", {"files": files})
        return reply

    reply = backtrack(reply)
    # Retour d'exécution (B/C/D/R) : UNIQUEMENT les tests de rejeu visibles (replay*.spec.js) + anti-régression.
    # L'oracle (oracle*.spec.js) n'est JAMAIS montré : il ne sert qu'au verdict final.
    has_replay = bool(list((ROOT / "bench" / "replay" / str(bug["pr"])).glob("replay*.spec.js")))
    # Condition O (« B* », borne haute) : le retour vient de l'ORACLE lui-même = vérificateur parfait.
    # Fuite VOLONTAIRE et documentée : mesure ce qu'un vérificateur fidèle peut apporter au maximum.
    fb_tests = "oracle" if condition == "O" else "replay"
    feedback = condition == "O" or (condition != "A" and has_replay)
    feedbacks = []
    for attempt in range(retries + 1):
        state, errors = flow.apply_edits(base, flow.parse_edits(reply), state)
        diff = flow.to_diff(base, state) if state else ""
        (out / "patch.diff").write_text(diff)
        if not feedback or attempt == retries:
            break
        if not diff:
            fb = {"fixed": False, "replay_error": "; ".join(errors) or "aucune édition applicable"}
        else:
            fb = evaluate(bug["pr"], str(out / "patch.diff"), tests=fb_tests)
            if errors:
                fb["replay_error"] = ("; ".join(errors) + "\n" + fb.get("replay_error", "")).strip()
            if fb.get("regression"):
                fb["fixed"] = False
                fb["replay_error"] = ("RÉGRESSION : l'accueil de la boutique ou la connexion au back-office ne répond plus.\n"
                                      + fb.get("replay_error", "")).strip()
        feedbacks.append({k: fb.get(k) for k in ("fixed", "regression", "replay_error")})
        if fb.get("fixed"):
            break
        reply = backtrack(turn("corriger", flow.msg_test(fb, state, base), "test", fb))
    # VERDICT : oracle caché
    if diff:
        result = evaluate(bug["pr"], str(out / "patch.diff"), tests="oracle")
        if errors:
            result["edit_errors"] = "; ".join(errors)
    else:
        result = {"pr": bug["pr"], "applied": False, "fixed": False, "regression": None,
                  "replay_error": "; ".join(errors) or "aucune édition"}
    result.update({
        "condition": condition, "model": model,
        "files_read": files,
        "files_initial": initial_files,
        "files_all_read": sorted(all_files_read),
        "files_edited": sorted(state.keys()),
        "keywords": kws,
        "official_files": bug["files"],
        "loc_hit_initial": bool(set(initial_files) & set(bug["files"])),
        "loc_hit_ever": bool(all_files_read & set(bug["files"])),
        "loc_hit_edited": bool(set(state.keys()) & set(bug["files"])),
        "loc_hit": bool(all_files_read & set(bug["files"])),
        "turns": len(msgs) // 2
    })
    (out / "result.json").write_text(json.dumps(result, ensure_ascii=False, indent=1))
    trace.close()
    return result


def main():
    load_env()
    ap = argparse.ArgumentParser()
    ap.add_argument("--bugs", nargs="+", type=int, default=[])
    ap.add_argument("--condition", default="B", choices=["A", "B", "C", "D", "E", "R", "O"],
                    help="A ticket · B +replay · C +glossaire · D modèle fine-tuné · E fine-tuné + règles métier · R fine-tuning simulé · O borne haute")
    ap.add_argument("--budget-eur", type=float, default=float(os.environ.get("BUDGET_EUR", 30)))
    ap.add_argument("--model", default=os.environ.get("LLM_MODEL", "gemma-4-31b-it"))
    ap.add_argument("--retries", type=int, default=2)
    ap.add_argument("--policy", default="llm", choices=["llm", "reconstruit"],
                    help="reconstruit = rejoue le chemin reconstruit du correctif officiel (démo sans modèle)")
    ap.add_argument("--list-models", action="store_true", help="liste les modèles gemma de l'API et quitte")
    a = ap.parse_args()
    if a.list_models:
        import subprocess
        out = subprocess.run(["curl", "-s", "-m", "30", "https://generativelanguage.googleapis.com/v1beta/models?pageSize=200",
                              "-H", f"x-goog-api-key: {os.environ.get('GEMMA_API_KEY', '')}"], capture_output=True, text=True).stdout
        print("\n".join(m["name"].split("/")[1] for m in json.loads(out).get("models", []) if "gemma" in m["name"]))
        return
    if a.policy == "reconstruit":
        a.model = "reconstruit"
    global BUDGET_EUR
    BUDGET_EUR = a.budget_eur
    cat = catalog()
    run_id = datetime.now().strftime("%Y%m%d-%H%M%S") + f"-{a.condition}"
    root = ROOT / "runs" / run_id
    res, skipped = [], []
    # du plus ancien au plus récent : les versions montent de façon incrémentale (base conservée, pas de réinstallation)
    for pr in sorted(a.bugs, key=lambda p: cat[p]["merged_at"] or ""):
        print(f"== #{pr} condition {a.condition} ({a.model})", flush=True)
        try:
            r = run_bug(cat[pr], a.condition, a.model, a.retries, root / str(pr), a.policy)
        except (SystemExit, RuntimeError) as e:
            if "BUDGET" in str(e):
                raise
            print(f"   ignoré : {str(e)[:200]}", flush=True)
            skipped.append(pr)
            continue
        print(json.dumps({k: r[k] for k in ("applied", "fixed", "regression", "loc_hit", "turns")}), flush=True)
        res.append(r)
    for pr in skipped:  # seconde chance (quota) pour les bugs sautés
        print(f"== #{pr} condition {a.condition} ({a.model}) [reprise]", flush=True)
        try:
            r = run_bug(cat[pr], a.condition, a.model, a.retries, root / str(pr), a.policy)
            print(json.dumps({k: r[k] for k in ("applied", "fixed", "regression", "loc_hit", "turns")}), flush=True)
            res.append(r)
        except (SystemExit, RuntimeError) as e:
            print(f"   ignoré définitivement : {str(e)[:200]}", flush=True)
    summary = {"run": run_id, "condition": a.condition, "model": a.model, "glossaire": os.environ.get("GLOSSAIRE", "glossaire.csv"), "n": len(res),
               "fixed": sum(r["fixed"] for r in res), "loc_hit": sum(r["loc_hit"] for r in res), "results": res}
    (root / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=1))
    print(f"→ {root}  corrigés {summary['fixed']}/{len(res)}  localisation {summary['loc_hit']}/{len(res)}")


if __name__ == "__main__":
    main()
