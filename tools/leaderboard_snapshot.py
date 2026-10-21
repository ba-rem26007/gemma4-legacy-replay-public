#!/usr/bin/env python3
"""Instantané quotidien du classement Kaggle « Gemma 4 Developer Agent » + historique pour https://kaggle.d1dev.fr/historique

- télécharge le classement public COMPLET (CSV officiel, `kaggle competitions leaderboard --download`) ;
- archive le CSV brut du jour : runs/leaderboard/AAAA-MM-JJ.csv (un fichier par jour, réécrit si relancé le même jour) ;
- régénère public/leaderboard.json (format lu par /participants) ;
- reconstruit public/leaderboard_history.json depuis toutes les archives : par jour, nb d'équipes, n° 1, seuils top 10 /
  top 100, médiane, notre rang et score ; trajectoire jour par jour des équipes du top 15 actuel et de la nôtre.
Lancé par cron chaque matin. Aucun modèle, aucune clé publiée. Kaggle ne fournit pas d'historique : il commence au 2 oct. 2026.
Usage : python3 tools/leaderboard_snapshot.py
"""
import csv, datetime as dt, io, json, statistics, subprocess, tempfile, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
COMP = "gemma-4-developer-agent"
ARCH = ROOT / "runs" / "leaderboard"
PUB = Path("/home/elrems/kaggle.d1dev.fr/public")
OURS = "rmisoubeyrand"  # nom d'utilisateur Kaggle de Rémi
TOP = 15


def download():
    with tempfile.TemporaryDirectory() as d:
        subprocess.run(["kaggle", "competitions", "leaderboard", COMP, "--download", "-p", d],
                       check=True, capture_output=True, timeout=300)
        z = zipfile.ZipFile(next(Path(d).glob("*.zip")))
        return z.read(z.namelist()[0]).decode("utf-8-sig")


def parse(text):
    rows = []
    for r in csv.DictReader(io.StringIO(text)):
        rows.append({"rank": int(r["Rank"]), "team_id": r["TeamId"], "team_name": r["TeamName"],
                     "username": r["TeamMemberUserNames"], "score": float(r["Score"] or 0),
                     "submissions": int(r["SubmissionCount"] or 0), "last_date": r["LastSubmissionDate"]})
    return sorted(rows, key=lambda r: r["rank"])


def summary(day, rows):
    scores = [r["score"] for r in rows]
    ours = next((r for r in rows if OURS in r["username"].split(",")), None)
    at = lambda n: rows[n - 1]["score"] if len(rows) >= n else None
    return {"date": day, "teams": len(rows), "top1": at(1), "top1_team": rows[0]["team_name"] if rows else None,
            "top10": at(10), "top100": at(100), "median": round(statistics.median(scores), 4) if scores else None,
            "ours": {"rank": ours["rank"], "score": ours["score"], "submissions": ours["submissions"]} if ours else None}


def main():
    ARCH.mkdir(parents=True, exist_ok=True)
    text = download()
    today = dt.datetime.now(dt.timezone.utc).date().isoformat()
    (ARCH / f"{today}.csv").write_text(text)
    rows = parse(text)
    (PUB / "leaderboard.json").write_text(json.dumps(rows, ensure_ascii=False))

    days = {p.stem: parse(p.read_text(encoding="utf-8-sig")) for p in sorted(ARCH.glob("20*.csv"))}
    watch = [r["team_id"] for r in rows[:TOP]]
    ours = next((r for r in rows if OURS in r["username"].split(",")), None)
    if ours and ours["team_id"] not in watch:
        watch.append(ours["team_id"])
    names = {r["team_id"]: r["team_name"] for r in rows}
    series = {t: {"name": names.get(t, t), "ours": bool(ours and t == ours["team_id"]), "points": []} for t in watch}
    for day, rs in days.items():
        by = {r["team_id"]: r for r in rs}
        for t in watch:
            if t in by:
                series[t]["points"].append({"date": day, "rank": by[t]["rank"], "score": by[t]["score"]})
    hist = {"generated": dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds"), "competition": COMP,
            "days": [summary(d, rs) for d, rs in days.items()], "teams": list(series.values())}
    (PUB / "leaderboard_history.json").write_text(json.dumps(hist, ensure_ascii=False, indent=1))
    s = hist["days"][-1]
    print(f"{today} : {s['teams']} équipes, n° 1 {s['top1']} ({s['top1_team']}), top 10 {s['top10']}, "
          f"nous : {s['ours']} — {len(days)} jour(s) d'historique")


if __name__ == "__main__":
    main()
