#!/usr/bin/env python3
"""Sélection des bugs candidats : PR 'Bug fix' mergées sur PrestaShop 8.1.x.

Usage : python3 bench/select.py [--limit 400] [--branch 8.1.x|all] [--out bugs.jsonl]
Sortie : bench/<out> (+ bench/diffs/<pr>.diff = correctif officiel)
"""
import argparse, json, re, subprocess, sys
from pathlib import Path

REPO = "PrestaShop/PrestaShop"
BRANCH = "8.1.x"
MAX_LINES, MAX_FILES = 60, 3
SECURITY = re.compile(r"secur|xss|csrf|injection|sqli|vulnerab|exploit|cve|rce|privilege|forbid|sanitiz|htmlentit|strip_tags|permission|access right|escap", re.I)
NON_UI = re.compile(r"^(tests?/|\.github/|composer\.|package|\.docker|docker|install-dev/)", re.I)
HERE = Path(__file__).parent


def gh(*args):
    return subprocess.run(["gh", *args], check=True, capture_output=True, text=True).stdout


def linked_issues(body):
    # Lignes « Fixed issue » / « How to test » du template de PR PrestaShop
    rows = [l for l in body.splitlines() if re.match(r"\|\s*(Fixed|How to test)", l, re.I)]
    nums = re.findall(r"(?:#|issues/)(\d{4,6})", " ".join(rows))
    return list(dict.fromkeys(int(n) for n in nums))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=400)
    ap.add_argument("--branch", default=BRANCH, help="branche de base, ou 'all'")
    ap.add_argument("--out", default="bugs.jsonl")
    ap.add_argument("--max-lines", type=int, default=MAX_LINES)
    ap.add_argument("--max-files", type=int, default=MAX_FILES)
    ap.add_argument("--merged", default=None, help="fenêtre de merge GitHub, ex. 2022-01-01..2023-03-01")
    ap.add_argument("--label", default="Bug fix", help="label GitHub requis ('' = aucun, pour 1.6/1.7)")
    ap.add_argument("--type-bugfix", action="store_true", help="exiger « Type? | bug fix » dans la description de la PR")
    ap.add_argument("--allow-no-issue", action="store_true", help="sans issue liée, le ticket = titre + description de la PR (1.6 : tickets sur l'ancienne forge)")
    ap.add_argument("--skip", type=int, default=0, help="ignorer les N PR les plus récentes (déjà traitées)")
    a = ap.parse_args()

    prs = json.loads(gh("pr", "list", "-R", REPO, "--state", "merged", *([] if a.branch == "all" else ["--base", a.branch]),
                        *(["--label", a.label] if a.label else []), "--limit", str(a.limit),
                        *(["--search", f"merged:{a.merged}"] if a.merged else []),
                        "--json", "number,title,body,additions,deletions,changedFiles,files,labels,mergeCommit,url,baseRefName"))
    prs = prs[a.skip:]
    out, stats = [], {"total": len(prs)}
    for pr in prs:
        size = pr["additions"] + pr["deletions"]
        files = [f["path"] for f in pr["files"]]
        labels = [l["name"] for l in pr["labels"]]
        reason = None
        if size > a.max_lines or pr["changedFiles"] > a.max_files:
            reason = "trop_gros"
        elif SECURITY.search(pr["title"] + " " + " ".join(labels)):
            reason = "securite"
        elif all(NON_UI.match(f) for f in files):
            reason = "non_ui"
        if not reason and a.type_bugfix:
            m = re.search(r"\|\s*Type\?\s*\|\s*([^|\n]*)", pr["body"] or "", re.I)
            # 1.7 : ligne « Type? | bug fix » ; 1.6 (avant mi-2016) : préfixe « [-] » dans le titre
            if not (m and "bug" in m.group(1).lower()) and not re.match(r"\s*\[-\]", pr["title"]):
                reason = "pas_bugfix"
        issues = linked_issues(pr["body"] or "")
        if not reason and not issues and not a.allow_no_issue:
            reason = "sans_issue"
        if reason:
            stats[reason] = stats.get(reason, 0) + 1
            continue

        try:
            issue = json.loads(gh("issue", "view", str(issues[0]), "-R", REPO,
                              "--json", "number,title,body,labels")) if issues else None
        except subprocess.CalledProcessError:
            issue = None
        if issue is None:
            if not a.allow_no_issue:
                stats["issue_introuvable"] = stats.get("issue_introuvable", 0) + 1
                continue
            # ticket de substitution : description de la PR (ticket d'origine sur l'ancienne forge Jira)
            desc = re.search(r"\|\s*Description\?\s*\|\s*(.*)", pr["body"] or "")
            steps = re.search(r"\|\s*How to test\?\s*\|\s*(.*)", pr["body"] or "")
            issue = {"number": 0, "title": pr["title"], "labels": [],
                     "body": f"### Describe the bug\n{desc.group(1).strip() if desc else pr['title']}\n\n### Steps to reproduce\n{steps.group(1).strip() if steps else ''}"}
        ibody = issue["body"] or ""
        if SECURITY.search(issue["title"] + " " + " ".join(l["name"] for l in issue["labels"])):
            stats["securite"] = stats.get("securite", 0) + 1
            continue
        if not a.allow_no_issue and not re.search(r"steps to reproduce|how to reproduce|reproduce", ibody, re.I):
            stats["sans_repro"] = stats.get("sans_repro", 0) + 1
            continue

        mc = (pr.get("mergeCommit") or {}).get("oid")
        if not mc:  # PR anciennes : champ absent → API REST
            mc = gh("api", f"repos/{REPO}/pulls/{pr['number']}", "--jq", ".merge_commit_sha").strip()
        if not mc or mc == "null":
            stats["sans_merge_commit"] = stats.get("sans_merge_commit", 0) + 1
            continue
        local = subprocess.run(["git", "-C", str(HERE / "ps"), "cat-file", "-e", f"{mc}^{{commit}}"], capture_output=True).returncode == 0
        try:
            if not local:
                raise subprocess.CalledProcessError(1, "merge absent de l'historique")
            parents = json.loads(gh("api", f"repos/{REPO}/commits/{mc}", "--jq", "[.parents[].sha]"))
        except subprocess.CalledProcessError:
            # PR rebasées / cherry-pickées (époque 1.6) : avant = parent du 1er commit de la PR, après = dernier commit
            try:
                cs = json.loads(gh("api", f"repos/{REPO}/pulls/{pr['number']}/commits", "--jq", "[.[].sha, .[0].parents[0].sha]"))
                mc, parents = cs[-2], [cs[-1]]
            except (subprocess.CalledProcessError, IndexError):
                stats["merge_commit_introuvable"] = stats.get("merge_commit_introuvable", 0) + 1
                continue
        d = HERE / "diffs" / f"{pr['number']}.diff"
        if not d.exists(): d.write_text(gh("pr", "diff", str(pr["number"]), "-R", REPO))
        out.append({
            "pr": pr["number"], "branch": pr["baseRefName"], "url": pr["url"], "title": pr["title"],
            "issue": issue["number"], "issue_title": issue["title"], "issue_body": ibody,
            "merge_commit": mc, "base_commit": parents[0],  # code AVANT le correctif
            "files": files, "lines": size, "labels": labels,
            "area": next((l for l in ("BO", "FO") if l in [x["name"] for x in issue["labels"]]), "?"),
        })
        print(f"+ #{pr['number']} ({size}l) {pr['title'][:70]}", file=sys.stderr)

    with open(HERE / a.out, "w") as f:
        for b in out:
            f.write(json.dumps(b, ensure_ascii=False) + "\n")
    stats["retenus"] = len(out)
    print(json.dumps(stats, indent=1), file=sys.stderr)


if __name__ == "__main__":
    main()
