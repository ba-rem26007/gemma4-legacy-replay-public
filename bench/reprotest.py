#!/usr/bin/env python3
"""Condition B : Gemma écrit un TEST DE REPRODUCTION à partir du TICKET SEUL (ni correctif, ni fichiers touchés).

Le test est gardé s'il ÉCHOUE sur le code d'origine par une assertion (il reproduit le bug).
La sélection n'utilise PAS la vérité terrain ; on mesure seulement, pour l'analyse, s'il passerait avec le
correctif officiel (colonne `passe_avec_correctif_officiel`).
Sortie : bench/replay/<pr>/replay_repro(.bo).spec.js (+ setup_repro.sql fusionné dans setup.sql si besoin),
          bench/reprotest.jsonl

Usage : PSB=5 python3 bench/reprotest.py <pr> [<pr>…] [--tries 3]
"""
import argparse, json, os, re, sys, time
from pathlib import Path

B = Path(__file__).resolve().parent
sys.path.insert(0, str(B))
import gentest as g  # ENV_NOTES, FORMAT, example(), parse(), sh(), catalog(), agentrun
import flow

PSB = os.environ.get("PSB", "1")
ASSERT = re.compile(r"expect\(.*\)\..*failed|Expected|Received|toHave|toContain|toBe", re.I)


def run(pr, spec_filter, mode):
    g.sh(f"PSB={PSB} {B}/checkout.sh {pr} {mode}")
    return g.sh(f"PSB={PSB} {B}/replay/run.sh {pr} {spec_filter}", 600)


def process(pr, model, tries):
    bug = g.catalog(pr)
    d = B / "replay" / str(pr)
    kind0 = "bo" if bug.get("area") == "BO" else "fo"
    prompt = (f"{g.ENV_NOTES}\n\n{g.example(kind0)}\n\nTICKET\n{flow.ticket_text(bug)[:3500]}\n\n"
              "Écris un test qui REPRODUIT ce bug : il doit ÉCHOUER sur la boutique actuelle (bug présent) "
              "et PASSER une fois le bug corrigé. Tu ne connais pas le correctif : base-toi sur le comportement "
              "attendu décrit dans le ticket.\n\n" + g.FORMAT)
    msgs = [{"role": "system", "content": "Tu écris des tests Playwright de reproduction de bugs PrestaShop. Réponds uniquement dans le format demandé."},
            {"role": "user", "content": prompt}]
    t0, log = time.time(), []
    for attempt in range(tries):
        reply, usage = g.agentrun.chat(msgs, model)
        g.agentrun.spend(usage)
        msgs.append({"role": "assistant", "content": reply})
        kind, sql, js = g.parse(reply)
        for f in d.glob("replay_repro*.spec.js"):
            f.unlink()
        if not js:
            msg = "Réponse sans bloc ```js```."
        else:
            spec = d / f"replay_repro{'.bo' if kind == 'bo' else ''}.spec.js"
            setup = ""
            if sql:  # SQL du test encapsulé dans le spec (le setup.sql du dossier appartient à l'oracle)
                setup = ("const { execSync: __x } = require('child_process');\n"
                         "const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));\n"
                         f"test.beforeAll(() => {{ __x(`docker exec -i ${{__P}}-db-1 mysql -padmin prestashop`, {{ input: {json.dumps(sql)} }}); }});\n")
            body = js.replace("const { test, expect } = require('@playwright/test');", "")
            spec.write_text(f"// Test de reproduction écrit par Gemma ({model}) à partir du TICKET SEUL — PR #{pr}\n"
                            "const { test, expect } = require('@playwright/test');\n" + setup + body + "\n")
            code, out = run(pr, "replay", "pre")
            failed_on_assert = code != 0 and bool(ASSERT.search(out)) and "SyntaxError" not in out
            if failed_on_assert:
                code_post, _ = run(pr, "replay", "post")  # analyse seulement
                r = {"pr": pr, "statut": "reproduit", "essais": attempt + 1,
                     "passe_avec_correctif_officiel": code_post == 0, "s": round(time.time() - t0)}
                (d / "STATUS_REPRO").write_text(f"reproduit\npasse avec correctif officiel : {code_post == 0}\n")
                return r
            if code == 0:
                msg = "Le test PASSE sur la boutique actuelle : il ne reproduit pas le bug. Vérifie le comportement fautif décrit dans le ticket."
            else:
                err = "\n".join(l for l in out.splitlines()
                                 if re.search(r"error|timeout|✘|syntax|locator|unknown column|doesn't exist|duplicate", l, re.I))[:2500]
                msg = f"Le test échoue pour une raison technique (pas une assertion métier) :\n{err}"
                # arbre d'accessibilité de la page au moment de l'échec (écrit par Playwright)
                port = 8080 + int(PSB)
                ctx = sorted((B / "replay" / f"test-results-{port}").glob(f"{pr}-replay_repro*/error-context.md"),
                             key=lambda p: p.stat().st_mtime)
                if ctx:
                    snap = ctx[-1].read_text(errors="ignore")
                    snap = snap[snap.find("# Page snapshot"):] if "# Page snapshot" in snap else snap
                    msg += f"\n\nÉTAT DE LA PAGE AU MOMENT DE L'ÉCHEC (arbre d'accessibilité, tronqué) :\n{snap[:5000]}"
            log.append({"attempt": attempt + 1, "msg": msg[:300]})
        msgs.append({"role": "user", "content": f"VALIDATION : {msg}\nCorrige le test. {g.FORMAT}"})
    for f in d.glob("replay_repro*.spec.js"):
        f.unlink()
    (d / "STATUS_REPRO").write_text(f"echec\n{log[-1]['msg'] if log else ''}\n")
    return {"pr": pr, "statut": "echec", "essais": tries, "s": round(time.time() - t0), "log": log}


def main():
    g.agentrun.load_env()
    ap = argparse.ArgumentParser()
    ap.add_argument("prs", nargs="+", type=int)
    ap.add_argument("--model", default=os.environ.get("LLM_MODEL", "gemma-4-31b-it"))
    ap.add_argument("--tries", type=int, default=4)
    a = ap.parse_args()
    with open(B / "reprotest.jsonl", "a") as out:
        for pr in sorted(a.prs, key=lambda p: g.catalog(p).get("merged_at") or ""):
            try:
                r = process(pr, a.model, a.tries)
            except Exception as e:
                r = {"pr": pr, "statut": "erreur", "err": str(e)[:300]}
            print(json.dumps({k: v for k, v in r.items() if k != "log"}, ensure_ascii=False), flush=True)
            out.write(json.dumps(r, ensure_ascii=False) + "\n"); out.flush()


if __name__ == "__main__":
    main()
