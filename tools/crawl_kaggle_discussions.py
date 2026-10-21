#!/usr/bin/env python3
"""Crawler & Veille incrémentale des discussions du concours Kaggle Gemma 4 Developer Agent.

Fonctionnalités :
- Archive l'intégralité des discussions et messages en Markdown sous docs/kaggle_discussions/.
- Support du mode incrémental via un fichier d'état `state.json` :
  - Détecte les nouveaux topics créés.
  - Détecte les nouveaux messages / réponses sur les topics existants.
  - Ne re-télécharge que ce qui a changé (exécution rapide en quelques secondes).
- Met à jour l'index navigable docs/kaggle_discussions/INDEX.md.
"""
import argparse
import json
import os
import re
import subprocess
import time
from pathlib import Path

COMP = "gemma-4-developer-agent"
DOCS_DIR = Path(__file__).resolve().parent.parent / "docs" / "kaggle_discussions"
DOCS_DIR.mkdir(parents=True, exist_ok=True)
STATE_FILE = DOCS_DIR / "state.json"


def clean_html(raw_html: str) -> str:
    """Nettoie sommairement les balises HTML en texte Markdown lisible."""
    text = re.sub(r'<pre><code>(.*?)</code></pre>', r'\n```\n\1\n```\n', raw_html, flags=re.DOTALL)
    text = re.sub(r'<code>(.*?)</code>', r'`\1`', text)
    text = re.sub(r'<h2>(.*?)</h2>', r'\n## \1\n', text)
    text = re.sub(r'<h3>(.*?)</h3>', r'\n### \1\n', text)
    text = re.sub(r'<li>(.*?)</li>', r'- \1\n', text)
    text = re.sub(r'<p>(.*?)</p>', r'\1\n\n', text)
    text = re.sub(r'<br\s*/?>', r'\n', text)
    text = re.sub(r'<[^>]+>', '', text)
    text = text.replace('&quot;', '"').replace('&gt;', '>').replace('&lt;', '<').replace('&amp;', '&')
    return text.strip()


def run_cmd(cmd_list):
    res = subprocess.run(cmd_list, capture_output=True, text=True, timeout=60)
    if res.returncode != 0:
        return None
    return res.stdout


def fetch_all_topics():
    topics = []
    page_token = None
    page_num = 1

    print(f"📡 Récupération de la liste des topics pour {COMP}...")
    while True:
        cmd = ["kaggle", "competitions", "topics", "list", COMP, "--format", "json"]
        if page_token:
            cmd.extend(["--page-token", str(page_token)])
        
        out = run_cmd(cmd)
        if not out:
            break
        
        token_match = re.search(r'Next Page Token\s*=\s*([^\s\n\r]+)', out)
        next_token = token_match.group(1) if token_match else None
        
        json_str = re.sub(r'Warning:.*?\n', '', out)
        json_str = re.sub(r'Next Page Token.*?\n', '', json_str).strip()
        
        try:
            batch = json.loads(json_str)
            if not batch:
                break
            topics.extend(batch)
            print(f"  • Page {page_num} : {len(batch)} topics trouvés (Total : {len(topics)})")
        except Exception as e:
            print(f"  ⚠️ Erreur parsing JSON page {page_num}: {e}")
            break

        if not next_token or next_token == page_token:
            break
        page_token = next_token
        page_num += 1
        time.sleep(0.5)

    return topics


def fetch_topic_messages(topic_id):
    cmd = ["kaggle", "competitions", "topic-messages", COMP, str(topic_id), "--format", "json"]
    out = run_cmd(cmd)
    if not out:
        return []
    try:
        return json.loads(out)
    except:
        return []


def load_state():
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text(encoding="utf-8"))
        except:
            return {"topics": {}, "last_crawl": None}
    return {"topics": {}, "last_crawl": None}


def save_state(state):
    state["last_crawl"] = time.strftime('%Y-%m-%d %H:%M:%S UTC')
    STATE_FILE.write_text(json.dumps(state, indent=2, ensure_ascii=False), encoding="utf-8")


def main():
    parser = argparse.ArgumentParser(description="Crawler et veille incrémentale du forum Kaggle")
    parser.add_argument("--force", action="store_true", help="Re-télécharger tous les topics sans mode incrémental")
    args = parser.parse_args()

    state = load_state()
    topics = fetch_all_topics()
    print(f"\n📋 Total topics trouvés sur Kaggle : {len(topics)}")

    index_lines = [
        f"# 📚 Archives Intégrales des Discussions Kaggle — {COMP}\n",
        f"> *Dernière synchronisation : {time.strftime('%Y-%m-%d %H:%M:%S UTC')}*\n",
        "| ID | Date | Titre | Votes | Commentaires | Fichier | Statut |",
        "| :--- | :--- | :--- | :--- | :--- | :--- | :--- |",
    ]

    new_topics_count = 0
    updated_topics_count = 0
    unchanged_count = 0

    topics_state = state.get("topics", {})

    for i, t in enumerate(topics, 1):
        tid = str(t["id"])
        title = t.get("title", f"Topic {tid}").strip()
        date = t.get("postDate", "")[:10]
        votes = t.get("votes", 0)
        comments = t.get("commentCount", 0)

        clean_slug = re.sub(r'[^a-zA-Z0-9_\-]+', '_', title.lower())[:50].strip('_')
        md_filename = f"{tid}_{clean_slug}.md"
        md_path = DOCS_DIR / md_filename

        prev_info = topics_state.get(tid)
        needs_fetch = False
        statut_label = "Inchangé"

        if args.force or not prev_info:
            needs_fetch = True
            statut_label = "Nouveau" if not prev_info else "Rechargé"
            new_topics_count += 1
        elif prev_info.get("commentCount", 0) != comments or prev_info.get("votes", 0) != votes or not md_path.exists():
            needs_fetch = True
            statut_label = "Mis à jour"
            updated_topics_count += 1
        else:
            unchanged_count += 1

        if needs_fetch:
            print(f"[{i:2d}/{len(topics)}] 📥 [{statut_label}] Topic {tid} : {title[:50]}...")
            messages = fetch_topic_messages(tid)

            md_content = [
                f"# Topic {tid}: {title}\n",
                f"- **Lien Kaggle** : https://www.kaggle.com/competitions/{COMP}/discussion/{tid}",
                f"- **Date** : {t.get('postDate')}",
                f"- **Votes** : {votes} | **Commentaires** : {len(messages)}\n",
                "---\n"
            ]

            for m_idx, m in enumerate(messages, 1):
                m_author = m.get("authorName") or "Participant"
                m_date = m.get("postDate", "")
                m_votes = m.get("votes", 0)
                raw_c = m.get("content", "")
                clean_c = clean_html(raw_c)

                md_content.append(f"### Message #{m_idx} — {m_author} ({m_date}) [Votes: {m_votes}]\n")
                md_content.append(clean_c + "\n")
                md_content.append("---\n")

            md_path.write_text("\n".join(md_content), encoding="utf-8")
            time.sleep(0.4)
        else:
            # Ne rien faire pour ce topic, il est déjà à jour
            pass

        # Mise à jour état
        topics_state[tid] = {
            "title": title,
            "votes": votes,
            "commentCount": comments,
            "postDate": t.get("postDate"),
            "filename": md_filename,
            "last_synced": time.strftime('%Y-%m-%d %H:%M:%S UTC')
        }

        index_lines.append(
            f"| [{tid}](https://www.kaggle.com/competitions/{COMP}/discussion/{tid}) | {date} | {title} | {votes} | {comments} | [{md_filename}](./{md_filename}) | {statut_label} |"
        )

    state["topics"] = topics_state
    save_state(state)

    (DOCS_DIR / "INDEX.md").write_text("\n".join(index_lines), encoding="utf-8")
    print(f"\n🎉 Veille incrémentale terminée !")
    print(f"   • Nouveaux : {new_topics_count}")
    print(f"   • Mis à jour : {updated_topics_count}")
    print(f"   • Inchangés : {unchanged_count}")
    print(f"   • État sauvegardé dans {STATE_FILE}")


if __name__ == "__main__":
    main()
