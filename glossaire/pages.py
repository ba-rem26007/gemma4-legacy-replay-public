#!/usr/bin/env python3
"""Index des pages du BO : nom de menu → contrôleur legacy + contrôleurs Symfony (aide à la localisation).

Source unique : le code PrestaShop (install-dev/data/xml/tab.xml, langs/en/data/tab.xml, routing/admin/**.yml).
Aucun modèle. Commit par défaut : 9.1.0 (antérieur aux bugs TEST → pas de fuite de correctif).
Sortie : glossaire/pages.csv, même format que glossaire.csv (utilisable par GLOSSAIRE=pages.csv ou liste séparée par des virgules).
Usage : python3 glossaire/pages.py [commit]
"""
import collections, csv, re, subprocess, sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PS = ROOT / "bench" / "ps"
SKIP = {"SELL", "IMPROVE", "CONFIGURE", "DEFAULT"}


def git(*a):
    return subprocess.run(["git", "-C", str(PS), *a], capture_output=True, text=True).stdout


def main(commit="9.1.0"):
    files = set(git("ls-tree", "-r", "--name-only", commit).splitlines())
    # contrôleurs Symfony par contrôleur legacy (_legacy_controller) dans les routes admin
    sf = collections.defaultdict(set)
    for f in sorted(x for x in files if re.match(r"src/PrestaShopBundle/Resources/config/routing/admin/.*\.yml$", x)):
        cur = None
        for line in git("show", f"{commit}:{f}").splitlines():
            m = re.search(r"_controller:\s*'?([\w\\]+)::", line)
            if m and "_legacy_controller" not in line:
                cur = m.group(1)
            m = re.search(r"_legacy_controller:\s*'?(\w+)", line)
            if m and cur:
                path = "src/" + cur.replace("\\", "/") + ".php"
                if path in files and "CommonController" not in path:
                    sf[m.group(1)].add(path)
    tabs = ET.fromstring(git("show", f"{commit}:install-dev/data/xml/tab.xml"))
    names = {t.get("id"): t.get("name") for t in ET.fromstring(git("show", f"{commit}:install-dev/langs/en/data/tab.xml"))}
    info = {}
    for t in tabs.iter("tab"):
        cls = (t.findtext("class_name") or "").strip()
        info[t.get("id")] = (t.get("id_parent") or "", cls, (t.findtext("wording") or names.get(t.get("id")) or "").strip())
    rows = []
    for tid, (parent, cls, label) in info.items():
        if tid in SKIP or not cls or len(label) < 4:
            continue
        legacy = f"controllers/admin/{cls}Controller.php"
        symbols = [cls] + ([legacy] if legacy in files else []) + sorted(sf.get(cls, ()))[:3]
        if len(symbols) == 1:
            continue
        plabel = info.get(parent, ("", "", ""))[2]
        syn = [f"{plabel} > {label}", f"{plabel} -> {label}"] if plabel and parent not in SKIP else []
        rows.append({"terme": label, "synonymes": "|".join(syn), "phonetique": "", "symboles": "|".join(symbols),
                     "source": f"tab.xml@{commit}"})
    out = ROOT / "glossaire" / "pages.csv"
    with open(out, "w", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=["terme", "synonymes", "phonetique", "symboles", "source"])
        w.writeheader()
        w.writerows(rows)
    print(f"{len(rows)} pages → {out} ({sum(1 for r in rows if 'src/' in r['symboles'])} avec contrôleur Symfony)")


if __name__ == "__main__":
    main(*sys.argv[1:])
