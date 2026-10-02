#!/usr/bin/env python3
"""Contrôle hors ligne du micro-index (agent/symbols.py) : pointe-t-il le fichier corrigé ? Aucun modèle. Usage : python3 bench/symcheck.py"""
import csv,json,sys,time; sys.path.insert(0,'agent')
import flow, symbols
cat={c["pr"]:c for c in map(json.loads,open("bench/catalog.jsonl"))}
bugs=[int(r["pr"]) for r in csv.DictReader(open("data/bugs_test.csv")) if r["statut"]=="valide"]
t0=time.time(); tick=ikw=g25=g3=0
for pr in bugs:
    b=cat[pr]; base=b["base_commit"]; fixed=set(b["files"])
    # mots-clés réellement proposés par Gemma (essai A n°2)
    try: kws=json.loads(open(f"runs/20260925-114612-A/{pr}/trace.jsonl").readline()).get("assistant","")
    except Exception: kws=""
    kws=flow.parse_json(kws,"keywords")
    t=symbols.lookup(base, symbols.identifiers(flow.ticket_text(b)))
    k=symbols.lookup(base, symbols.identifiers(flow.ticket_text(b))+kws)
    hit=lambda L: any(any(f+":" in l for f in fixed) for l in L)
    tick+=hit(t); ikw+=hit(k)
    h=list(flow.grep(base,kws)); g25+=bool(fixed & set(h)); g3+=bool(fixed & set(h[:3]))
    print(pr, "ticket" if hit(t) else "", "t+kw" if hit(k) else "", "grep25" if fixed&set(h) else "", len(k), flush=True)
print(f"index(ticket) {tick}/33  index(ticket+mots-clés) {ikw}/33  grep top25 {g25}/33  grep top3 {g3}/33  {time.time()-t0:.0f}s")
