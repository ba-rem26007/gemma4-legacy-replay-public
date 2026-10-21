#!/usr/bin/env python3
"""Rejeu des points de décision « édition » sous plusieurs variantes (VM Colab, vLLM sur :8000, sans relais lent).

Entrée : edits_<tag>.jsonl écrit par slow_proxy.py --dump (requête complète + réponse d'origine).
Pour chaque contexte et chaque variante : N tirages ; on classe le premier appel edit_file de la réponse :
  ok        filepath, old_string et new_string présents
  perdu     un argument obligatoire manque (le parseur gemma4 a découpé une chaîne mal délimitée)
  autre     pas d'appel edit_file (autre outil, ou texte)
Sortie : tableau par variante, séparé selon que l'appel d'origine était réussi ou raté ; détail JSONL.
Usage : python3 replay_edits.py edits_lent2.jsonl --n 4 --out replay_edits.jsonl
"""
import argparse, collections, concurrent.futures as cf, copy, json, urllib.request

SHORT = ("\n\nWhen you call edit_file, keep old_string short: one to three consecutive lines copied exactly from the file, "
         "just enough to be unique. Never pass a whole function or block as old_string; for a larger change, make several "
         "small edits. Give the arguments in the order filepath, old_string, new_string.")


def add_to_system(req, text):
    m = req["messages"][0]
    if m.get("role") != "system":
        req["messages"].insert(0, {"role": "system", "content": text.strip()})
        return
    if isinstance(m["content"], str):
        m["content"] += text
    else:
        m["content"].append({"type": "text", "text": text})


SCRIPT = ("\n\nNever call edit_file: its arguments are often lost. To change a file, use run_command with a short Python "
          "script in a heredoc that reads the file, checks that the old text occurs exactly once, replaces it and writes the "
          "file back: python3 - <<'EOF', then import pathlib; p = pathlib.Path(path); s = p.read_text(); old = (the exact "
          "lines, in a triple-quoted string); new = (the replacement); assert s.count(old) == 1; p.write_text(s.replace(old, new)); "
          "then EOF. Keep old short and unique.")

VARIANTS = {
    "origine": lambda r: r,
    "T0.2": lambda r: {**r, "temperature": 0.2, "top_k": 40},
    "court": lambda r: (add_to_system(r, SHORT), r)[1],
    "court+T0.2": lambda r: (add_to_system(r, SHORT), {**r, "temperature": 0.2, "top_k": 40})[1],
    "script": lambda r: (add_to_system(r, SCRIPT), r)[1],
    # edit_file retiré de la liste d'outils : un appel à un outil non déclaré fait échouer la tâche (ADK) → à surveiller
    "script-sans-edit": lambda r: (add_to_system(r, SCRIPT),
                                   {**r, "tools": [t for t in r.get("tools", []) if t.get("function", {}).get("name") != "edit_file"]})[1],
}


def classify(msg):
    if any(c["function"]["name"] == "edit_file" for c in msg.get("tool_calls") or []) and msg.get("_no_edit"):
        return "non-déclaré"
    for c in msg.get("tool_calls") or []:
        if c["function"]["name"] == "run_command":
            try:
                cmd = json.loads(c["function"]["arguments"] or "{}").get("command", "")
            except Exception:
                return "perdu"
            if "replace(" in cmd or "write_text" in cmd or "sed -i" in cmd:
                return "ok-script"
            continue
        if c["function"]["name"] != "edit_file":
            continue
        try:
            a = json.loads(c["function"]["arguments"] or "{}")
        except Exception:
            return "perdu"
        return "ok" if {"filepath", "old_string", "new_string"} <= set(a) else "perdu"
    return "autre"


def call(api, req):
    body = json.dumps({**req, "stream": False}).encode()
    r = urllib.request.Request(api + "/chat/completions", body, {"Content-Type": "application/json"})
    with urllib.request.urlopen(r, timeout=900) as f:
        return json.loads(f.read())["choices"][0]["message"]


def main():
    p = argparse.ArgumentParser()
    p.add_argument("dump")
    p.add_argument("--api", default="http://127.0.0.1:8000/v1")
    p.add_argument("--n", type=int, default=4)
    p.add_argument("--out", default="replay_edits.jsonl")
    p.add_argument("--workers", type=int, default=16)
    p.add_argument("--variants", default="", help="liste séparée par des virgules (défaut : toutes)")
    p.add_argument("--max-ctx", type=int, default=0)
    a = p.parse_args()
    ctx = [json.loads(l) for l in open(a.dump)]
    ctx = [c for c in ctx if any(t["function"]["name"] == "edit_file" for t in c["response"].get("tool_calls") or [])]
    if a.max_ctx:
        ctx = ctx[::max(1, len(ctx) // a.max_ctx)][:a.max_ctx]
    chosen = {k: v for k, v in VARIANTS.items() if not a.variants or k in a.variants.split(",")}
    jobs = []
    for i, c in enumerate(ctx):
        origin = classify(c["response"])
        for v, f in chosen.items():
            for k in range(a.n):
                req = f(copy.deepcopy(c["request"]))
                req.pop("seed", None)
                jobs.append((i, origin, v, k, req))
    print(f"{len(ctx)} contextes d'édition, {len(jobs)} requêtes")
    stats = collections.defaultdict(collections.Counter)
    with cf.ThreadPoolExecutor(a.workers) as ex, open(a.out, "w") as out:
        futs = {ex.submit(call, a.api, j[4]): j for j in jobs}
        for fu in cf.as_completed(futs):
            i, origin, v, k, _ = futs[fu]
            try:
                m = fu.result()
                if v == "script-sans-edit":
                    m["_no_edit"] = True
                res = classify(m)
            except Exception as e:
                res = "erreur"
            stats[(v, origin)][res] += 1
            out.write(json.dumps({"ctx": i, "origine": origin, "variante": v, "tirage": k, "resultat": res}) + "\n")
    print(f"{'variante':17} {'origine':8} {'ok':>5} {'script':>7} {'perdu':>6} {'non-décl.':>9} {'autre':>6} {'modif. réussies':>16}")
    for (v, origin), c in sorted(stats.items()):
        n = sum(c.values())
        print(f"{v:17} {origin:8} {c['ok']:5} {c['ok-script']:7} {c['perdu']:6} {c['non-déclaré']:9} {c['autre']:6} {((c['ok'] + c['ok-script']) / n if n else 0):16.0%}")


if __name__ == "__main__":
    main()
