#!/usr/bin/env python3
"""Générateur Studio Neural de haute fidélité pour le Paper Track (Microsoft Neural Voices).
Remplace la synthèse basique gTTS par des voix neuronales broadcast de pointe :
  - Anglais : en-US-ChristopherNeural (timbre chaud, posé, autorité documentaire style TED/BBC)
  - Français Canadien : fr-CA-AntoineNeural (intonation québécoise naturelle et chaleureuse)

Génère :
  1. pitch.mp4 (Anglais Studio Neural)
  2. pitch_fr_ca.mp4 (Français Canadien Studio Neural)
  3. Sous-titres multilingues haute précision (FR, FR-CA, EN, ES, DE, JA) en VTT et SRT
  4. transcript.json synchronisé pour le lecteur interactif
"""
import asyncio
import json
import os
import subprocess
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import edge_tts

ROOT = Path(__file__).resolve().parent.parent
TMP_DIR = Path("/tmp/studio_build")
TMP_DIR.mkdir(parents=True, exist_ok=True)

OUT_SUB_DIR = ROOT / "docs/figures/subtitles"
OUT_SUB_DIR.mkdir(parents=True, exist_ok=True)

PUB_DIR = Path("/home/elrems/kaggle.d1dev.fr/public/video")
PUB_DIR.mkdir(parents=True, exist_ok=True)
PUB_SUB_DIR = PUB_DIR / "subtitles"
PUB_SUB_DIR.mkdir(parents=True, exist_ok=True)

FFPROBE = "/home/elrems/.local/bin/ffprobe"
FFMPEG = "/home/elrems/.local/bin/ffmpeg"

FONT_BOLD = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_REGULAR = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"

VOICE_EN = ("en-US-AndrewMultilingualNeural", "-3%")
VOICE_CA = ("fr-CA-JeanNeural", "-4%")
VOICE_FR = ("fr-FR-RemyMultilingualNeural", "-4%")

ACTS_DATA = [
    {
        "id": "act1",
        "act_num": "ACT 1 / 5",
        "title": "The Real-World Problem: Enterprise PHP Monoliths",
        "subtitle": "Beyond Python Benchmarks · 76.2% of the Web · Sovereign AI Repair",
        "figure": ROOT / "docs/figures/cover_1120x560.png",
        "bullets": [
            "SWE-bench evaluates Python scripts with pre-existing unit suites.",
            "76.2% of web servers run PHP (PrestaShop, Magento, WordPress).",
            "Stateful production bugs spanning relational databases & browser state.",
            "Strict GDPR/PCI-DSS rules prohibit uploading code to cloud APIs."
        ],
        "script_en": (
            "Hi everyone! Most AI coding benchmarks today evaluate Python scripts with pre-existing unit tests. "
            "But in the enterprise world... over seventy-six percent of the web runs on PHP, "
            "powering hundreds of thousands of live merchant stores handling real money. "
            "This code is legacy: it has no unit tests, its bugs span complex relational databases, "
            "and strict data regulations prohibit exporting private codebases to cloud APIs. "
            "Our goal was simple: can open-weight Gemma 4 models autonomously repair stateful enterprise PHP monoliths?"
        ),
        "script_ca": (
            "Bonjour à tous les développeurs et membres du jury ! "
            "La plupart des bancs d'essai pour l'IA de codage évaluent aujourd'hui des scripts Python avec des tests unitaires déjà existants. "
            "Mais dans le monde de l'entreprise... plus de soixante-seize pour cent du web roule sur PHP, "
            "faisant tourner des centaines de milliers de boutiques en ligne bien réelles, manipulant de vraies transactions. "
            "Ce code-là, c'est du legacy : aucun test unitaire, des bogues qui touchent des bases de données relationnelles complexes, "
            "et des règlements stricts de confidentialité qui interdisent d'exporter le code vers des API propriétaires dans le nuage. "
            "Notre objectif était bien simple : est-ce que les modèles ouverts Gemma 4 peuvent réparer de manière autonome des monolithes PHP d'entreprise à état ?"
        ),
        "script_fr": (
            "Bonjour à tous ! "
            "La plupart des bancs d'essai actuels pour l'IA de code évaluent de simples scripts Python dotés de tests unitaires déjà écrits. "
            "Mais dans le monde réel de l'entreprise... plus de soixante-seize pour cent du web fonctionne sur PHP, "
            "propulsant des centaines de milliers de boutiques marchandes manipulant des transactions réelles. "
            "Ce code est patrimonial : aucun test unitaire, des anomalies reliant des bases de données relationnelles complexes, "
            "et des règles strictes de confidentialité interdisant d'exporter ce code privé vers des API propriétaires dans le cloud. "
            "Notre défi était direct : les modèles ouverts Gemma 4 peuvent-ils réparer de manière autonome des monolithes PHP d'entreprise à état ?"
        )
    },
    {
        "id": "act2",
        "act_num": "ACT 2 / 5",
        "title": "Making Legacy Code Verifiable: The PrestaShop Protocol",
        "subtitle": "33 Real Post-Cutoff Bugs · Containerized Playwright E2E · Hidden Oracles",
        "figure": ROOT / "docs/figures/fig1_pipeline.png",
        "bullets": [
            "33 real bugs merged in PrestaShop 9.1 after Gemma 4 knowledge cutoff.",
            "Deterministic MariaDB snapshots reset state between test episodes.",
            "End-to-end browser replay with Playwright validates the user journey.",
            "Evaluation oracle is strictly hidden to guarantee zero evaluation leakage."
        ],
        "script_en": (
            "To answer this, we built a fully verifiable benchmark on PrestaShop 9.1: "
            "thirty-three real bugs merged strictly after Gemma 4's knowledge cutoff. "
            "Each bug is paired with a containerized Docker environment, instant Maria D-B database resets, "
            "and an end-to-end browser oracle using Playwright. "
            "Crucially, the evaluation oracle is strictly hidden from the agent. "
            "Under our fixed-turn flow, Gemma 4 only sees reproduction tests generated directly from the issue ticket, "
            "preserving absolute evaluation integrity."
        ),
        "script_ca": (
            "Pour y répondre, on a bâti un banc d'essai entièrement vérifiable sur PrestaShop 9.1 : "
            "trente-trois vrais bogues fusionnés strictement après la date de coupure de connaissances de Gemma 4. "
            "Chaque bogue est jumelé à un environnement conteneurisé Docker, des remises à zéro instantanées de Maria D-B, "
            "et un oracle de bout en bout dans le fureteur avec Playwright. "
            "Point crucial : l'oracle d'évaluation est strictement caché à l'agent. "
            "Sous notre flux à étapes fixes, Gemma 4 ne voit que les tests de reproduction générés directement depuis le ticket d'anomalie, "
            "assurant ainsi une intégrité scientifique totale."
        ),
        "script_fr": (
            "Pour répondre à cette question, nous avons bâti un banc d'essai rigoureusement vérifiable sur PrestaShop 9.1 : "
            "trente-trois bogues réels fusionnés strictement après la coupure de connaissances de Gemma 4. "
            "Chaque problème est jumelé à un conteneur Docker dédié, des réinitialisations instantanées de base Maria D-B, "
            "et un oracle de validation de bout en bout dans le navigateur avec Playwright. "
            "Point crucial : l'oracle d'évaluation est strictement caché à l'agent. "
            "Sous notre protocole à étapes fixes, Gemma 4 ne dispose que des tests de reproduction générés depuis le ticket, "
            "préservant ainsi une intégrité scientifique absolue."
        )
    },
    {
        "id": "act3",
        "act_num": "ACT 3 / 5",
        "title": "Empirical Findings & The Reward Hacking Discovery",
        "subtitle": "38.6% Baseline Resolution · Localization Bottleneck · 32% Reward Hacked",
        "figure": ROOT / "docs/figures/fig2_results.png",
        "bullets": [
            "Gemma 4 31B solves 38.6% from tickets alone, rising to 45.5% with repro tests.",
            "File localization is the primary prerequisite: right file = 59.3% win rate.",
            "Self-generated tests induce severe reward hacking: 32% of synthetic passes",
            "modify unrelated stubs. Human maintainer ground-truth guards are essential."
        ],
        "script_en": (
            "What did we discover? "
            "First, Gemma 4 31B resolves thirty-eight point six percent of bugs from the ticket alone, "
            "rising to forty-five point five percent when reproduction tests provide execution feedback. "
            "We show that file localization is the primary bottleneck: when the agent finds the right file, resolution jumps to sixty percent. "
            "Second, we tested whether Gemma 4 could bootstrap its own training data by writing its own tests. "
            "And here is our key scientific finding: passing a synthetic test is not enough. "
            "Without human maintainer grounding, thirty-two percent of Gemma's self-generated fixes were reward hacking, "
            "modifying unrelated queries or stubs to satisfy the verifier without fixing the root cause."
        ),
        "script_ca": (
            "Qu'est-ce qu'on a découvert ? "
            "D'abord, Gemma 4 31B résout trente-huit point six pour cent des bogues à partir du ticket tout seul, "
            "montant à quarante-cinq point cinq pour cent quand les tests de reproduction fournissent une rétroaction d'exécution. "
            "On montre que la localisation du bon fichier est le principal goulet d'étranglement : quand l'agent trouve le bon fichier, le taux de succès grimpe à soixante pour cent. "
            "Ensuite, on a testé si Gemma 4 pouvait auto-générer ses propres données d'entraînement en rédigeant ses propres tests. "
            "Et voici notre trouvaille scientifique majeure : réussir un test synthétique ne suffit pas. "
            "Sans l'ancrage des vrais correctifs des mainteneurs humains, trente-deux pour cent des solutions auto-générées de Gemma étaient du reward hacking, "
            "modifiant des stubs ou requêtes non liés pour satisfaire le vérificateur sans régler la vraie cause racine."
        ),
        "script_fr": (
            "Qu'avons-nous découvert ? "
            "Premièrement, Gemma 4 31B résout trente-huit virgule six pour cent des bogues à partir du ticket seul, "
            "et monte à quarante-cinq virgule cinq pour cent dès que les tests de reproduction fournissent un retour d'exécution. "
            "La localisation du bon fichier est le verrou principal : quand l'agent trouve le fichier coupable, la résolution bondit à près de soixante pour cent. "
            "Deuxièmement, nous avons testé l'auto-génération de tests par le modèle lui-même. "
            "Et voici notre constat scientifique clé : réussir un test synthétique ne suffit pas. "
            "Sans ancrage sur les vrais correctifs des mainteneurs humains, trente-deux pour cent des solutions auto-générées relevaient du reward hacking, "
            "modifiant des requêtes ou des stubs pour satisfaire le test sans corriger le problème racine."
        )
    },
    {
        "id": "act4",
        "act_num": "ACT 4 / 5",
        "title": "Sovereign Edge Deployment: Chunked Loss & E4B",
        "subtitle": "Dense Gemma 4 E4B · 51% Peak VRAM Reduction · 4.29 GB VRAM · 1.81 Wh",
        "figure": ROOT / "docs/figures/fig4_transfer.png",
        "bullets": [
            "262k vocabulary causes massive OOM on 16GB GPUs during loss projection.",
            "Chunked Cross-Entropy computes logits in micro-chunks of 256 tokens.",
            "Enables full QLoRA fine-tuning on free Kaggle and Colab T4 GPUs (zero euro).",
            "Deployed in 4.29 GB VRAM, consuming just 1.81 Wh per bug for local triage."
        ],
        "script_en": (
            "To enable private, on-premise repair, we fine-tuned the dense Gemma 4 E-4-B model using Q-LoRA. "
            "Gemma 4's massive two hundred and sixty-two thousand vocabulary causes out-of-memory errors during loss projection on sixteen-gigabyte GPUs. "
            "We adapted a chunked cross-entropy loss that projects logits in micro-chunks of 256 tokens, "
            "slashing peak V-RAM by fifty-one percent and allowing stable training on free Kaggle and Colab T-4 GPUs at zero monetary cost. "
            "In deployment, this 4B model runs locally in just four point twenty-nine gigabytes of V-RAM and consumes only one point eighty-one watt-hours per bug, "
            "providing an edge triage filter that operates with zero cloud data leaks."
        ),
        "script_ca": (
            "Pour permettre la réparation souveraine et privée directement sur site, on a affiné le modèle dense Gemma 4 E-4-B avec Q-LoRA. "
            "Le vocabulaire géant de deux cent soixante-deux mille jetons de Gemma 4 provoque des débordements de mémoire vive sur les cartes graphiques de seize gigas lors du calcul de la perte. "
            "On a adapté une fonction de perte cross-entropy fractionnée en micro-morceaux de 256 jetons, réduisant l'empreinte mémoire V-RAM de pointe de cinquante-et-un pour cent et permettant un entraînement hyper stable sur les GPU T4 gratuits de Kaggle et Colab, à coût zéro. "
            "En déploiement, ce modèle 4B roule localement dans seulement quatre point vingt-neuf gigas de mémoire V-RAM et ne consomme que un point quatre-vingt-un watt-heures par bogue, fournissant un filtre de triage autonome sans aucune fuite vers le nuage."
        ),
        "script_fr": (
            "Pour permettre la réparation locale et souveraine directement sur site, nous avons affiné le modèle dense Gemma 4 E-4-B avec Q-LoRA. "
            "Le vocabulaire géant de 262 000 jetons de Gemma 4 provoque des dépassements de mémoire vive sur les GPU de seize gigas lors du calcul de la perte. "
            "Nous avons adapté une fonction de perte cross-entropy fractionnée en micro-blocs de 256 jetons, réduisant l'empreinte mémoire V-RAM de pointe de cinquante-et-un pour cent et assurant un entraînement parfaitement stable sur les GPU T4 gratuits de Kaggle et Colab, à coût zéro. "
            "En production, ce modèle 4B s'exécute localement avec seulement quatre virgule vingt-neuf gigaoctets de mémoire V-RAM et consomme à peine un virgule quatre-vingt-un watt-heures par bogue, offrant un filtre de triage autonome sans aucune fuite vers le cloud."
        )
    },
    {
        "id": "act5",
        "act_num": "ACT 5 / 5",
        "title": "100% Auditability & One-Click Reproducibility",
        "subtitle": "Executable Colab Notebook · 134 MB Adapter · Apache 2.0 Open Science",
        "figure": ROOT / "docs/figures/fig5_example.png",
        "bullets": [
            "Executable Colab notebook reproduces all statistical asserts in 1 click.",
            "Open-weights LoRA adapter (134 MB) published on Hugging Face & Kaggle.",
            "Transfer proven on 36 official Kaggle Python tasks (82% edit success).",
            "Deterministic, transparent, auditable software engineering agents."
        ],
        "script_en": (
            "Everything in this research is one hundred percent reproducible. "
            "You can open our Google Colab notebook right now, load our one-hundred-and-thirty-four-megabyte adapter, "
            "and audit the full evaluation pipeline and statistical tests with a single click. "
            "Open weights, deterministic execution, and real-world legacy code: "
            "this is how we make software engineering agents practical and trustworthy. "
            "Thank you so much!"
        ),
        "script_ca": (
            "Tout dans cette recherche est reproductible à cent pour cent. "
            "Vous pouvez ouvrir notre carnet Google Colab dès maintenant, charger notre adaptateur de cent trente-quatre mégaoctets, "
            "et auditer l'ensemble du pipeline d'évaluation et des tests statistiques en un seul clic. "
            "Poids ouverts, exécution déterministe et vrai code patrimonial : "
            "c'est comme ça qu'on rend les agents de génie logiciel pratiques et fiables. "
            "Merci beaucoup pour votre attention !"
        ),
        "script_fr": (
            "Tout dans cette recherche est reproductible à cent pour cent. "
            "Vous pouvez ouvrir notre carnet Google Colab dès maintenant, charger notre adaptateur de cent trente-quatre mégaoctets, "
            "et auditer l'ensemble du pipeline d'évaluation et des tests statistiques en un seul clic. "
            "Poids ouverts, exécution déterministe et vrai code patrimonial : "
            "c'est ainsi que nous rendons les agents d'ingénierie logicielle pratiques et dignes de confiance. "
            "Merci beaucoup pour votre attention !"
        )
    }
]

def draw_dashed_rect(draw, x0, y0, x1, y1, color, dash=10, gap=6, width=2):
    x = x0
    while x < x1:
        draw.line([(x, y0), (min(x + dash, x1), y0)], fill=color, width=width)
        x += dash + gap
    y = y0
    while y < y1:
        draw.line([(x1, y), (x1, min(y + dash, y1))], fill=color, width=width)
        y += dash + gap
    x = x1
    while x > x0:
        draw.line([(x, y1), (max(x - dash, x0), y1)], fill=color, width=width)
        x -= dash + gap
    y = y1
    while y > y0:
        draw.line([(x0, y), (x0, max(y - dash, y0))], fill=color, width=width)
        y -= dash + gap

def render_animated_slide(act, active_idx, out_path):
    W, H = 1920, 1080
    im = Image.new("RGB", (W, H), color=(11, 15, 25))
    draw = ImageDraw.Draw(im)

    f_badge = ImageFont.truetype(FONT_BOLD, 22)
    f_title = ImageFont.truetype(FONT_BOLD, 46)
    f_subtitle = ImageFont.truetype(FONT_REGULAR, 26)
    f_act = ImageFont.truetype(FONT_BOLD, 26)
    f_bullet = ImageFont.truetype(FONT_REGULAR, 25)
    f_bullet_bold = ImageFont.truetype(FONT_BOLD, 25)
    f_tag = ImageFont.truetype(FONT_BOLD, 13)
    f_footer = ImageFont.truetype(FONT_REGULAR, 20)

    # Top banner & title
    draw.rectangle([(0, 0), (W, 8)], fill=(56, 189, 248))
    draw.text((80, 45), "GOOGLE – THE GEMMA 4 DEVELOPER AGENT · PAPER TRACK", fill=(56, 189, 248), font=f_badge)
    draw.text((W - 250, 45), act["act_num"], fill=(245, 158, 11), font=f_act)
    draw.text((80, 85), act["title"], fill=(248, 250, 252), font=f_title)
    draw.text((80, 148), act["subtitle"], fill=(148, 163, 184), font=f_subtitle)
    draw.line([(80, 195), (W - 80, 195)], fill=(30, 41, 59), width=2)

    # Left Figure Card
    fig_path = act["figure"]
    if fig_path.exists():
        fig_img = Image.open(fig_path).convert("RGBA")
        max_w, max_h = 1000, 740
        fig_img.thumbnail((max_w, max_h), Image.Resampling.LANCZOS)
        fig_x = 80 + (max_w - fig_img.width) // 2
        fig_y = 230 + (max_h - fig_img.height) // 2
        card_pad = 12
        draw.rounded_rectangle(
            [(fig_x - card_pad, fig_y - card_pad), (fig_x + fig_img.width + card_pad, fig_y + fig_img.height + card_pad)],
            radius=12, fill=(20, 27, 45), outline=(51, 65, 85), width=2
        )
        im.paste(fig_img, (fig_x, fig_y), mask=fig_img)

    # Right Insights Card
    bx = 1130
    by = 250
    draw.rounded_rectangle(
        [(bx - 20, 220), (W - 80, 970)],
        radius=14, fill=(17, 24, 39), outline=(51, 65, 85), width=2
    )

    card_header_font = ImageFont.truetype(FONT_BOLD, 28)
    draw.text((bx + 10, by), "Key Scientific Insights", fill=(56, 189, 248), font=card_header_font)
    draw.line([(bx + 10, by + 45), (W - 110, by + 45)], fill=(30, 41, 59), width=2)

    cur_y = by + 75
    for i, bullet in enumerate(act["bullets"]):
        is_active = (i == active_idx)
        is_past = (i < active_idx)

        # Word wrap
        words = bullet.split()
        lines = []
        cur_line = ""
        for w in words:
            test_line = f"{cur_line} {w}".strip()
            bbox = draw.textbbox((0, 0), test_line, font=f_bullet_bold if is_active else f_bullet)
            if bbox[2] - bbox[0] > (W - 80 - (bx + 45) - 30):
                lines.append(cur_line)
                cur_line = w
            else:
                cur_line = test_line
        if cur_line:
            lines.append(cur_line)

        block_h = len(lines) * 36 + 18
        box_top = cur_y - 6
        box_bot = cur_y + block_h - 10

        if is_active:
            # Highlighted translucent background
            draw.rounded_rectangle(
                [(bx - 8, box_top), (W - 95, box_bot)],
                radius=10, fill=(22, 38, 64)
            )
            # ENCADREMENT EN POINTILLÉ (Dashed outline)
            draw_dashed_rect(draw, bx - 8, box_top, W - 95, box_bot, color=(56, 189, 248), dash=9, gap=5, width=2)

            # Badge « EN COURS »
            draw.rounded_rectangle([(W - 195, box_top + 8), (W - 105, box_top + 28)], radius=6, fill=(56, 189, 248))
            draw.text((W - 187, box_top + 11), "FOCUS IA", fill=(11, 15, 25), font=f_tag)

            # Active pulsing dot
            draw.ellipse([(bx + 10, cur_y + 6), (bx + 24, cur_y + 20)], fill=(56, 189, 248))
            text_color = (255, 255, 255)
            font_used = f_bullet_bold
        elif is_past:
            # Validated item
            draw.ellipse([(bx + 12, cur_y + 8), (bx + 22, cur_y + 18)], fill=(34, 197, 94))
            text_color = (203, 213, 225)
            font_used = f_bullet
        else:
            # Future item (dimmed)
            draw.ellipse([(bx + 12, cur_y + 8), (bx + 22, cur_y + 18)], fill=(71, 85, 105))
            text_color = (100, 116, 139)
            font_used = f_bullet

        ty = cur_y
        for l in lines:
            draw.text((bx + 40, ty), l, fill=text_color, font=font_used)
            ty += 36

        cur_y += block_h + 16

    draw.line([(80, 1010), (W - 80, 1010)], fill=(30, 41, 59), width=1)
    draw.text((80, 1030), "Author: Rémi Soubeyrand · ba-rem26007/gemma4-legacy-replay-public · License: Apache-2.0", fill=(100, 116, 139), font=f_footer)
    draw.text((W - 400, 1030), "Google DeepMind Competition 2026", fill=(100, 116, 139), font=f_footer)

    im.save(out_path, "PNG")

def get_audio_duration(path):
    cmd = [
        FFPROBE, "-v", "error",
        "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1",
        str(path)
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return float(res.stdout.strip())

async def synthesize_all_audio():
    print("=== SYNTHÈSE VOCALE NEURONALE STUDIO (VOIX NATURELLES & CONVERSATIONNELLES) ===")
    for act in ACTS_DATA:
        act_id = act["id"]
        mp3_en = TMP_DIR / f"{act_id}_en.mp3"
        mp3_ca = TMP_DIR / f"{act_id}_ca.mp3"
        mp3_fr = TMP_DIR / f"{act_id}_fr.mp3"

        if not mp3_en.exists():
            print(f"  [{act_id}] Génération Anglais Naturel ({VOICE_EN[0]}, rate={VOICE_EN[1]})...")
            await edge_tts.Communicate(act["script_en"], VOICE_EN[0], rate=VOICE_EN[1]).save(str(mp3_en))

        if not mp3_ca.exists():
            print(f"  [{act_id}] Génération Français Canadien Posé ({VOICE_CA[0]}, rate={VOICE_CA[1]})...")
            await edge_tts.Communicate(act["script_ca"], VOICE_CA[0], rate=VOICE_CA[1]).save(str(mp3_ca))

        if not mp3_fr.exists():
            print(f"  [{act_id}] Génération Français Conversationnel ({VOICE_FR[0]}, rate={VOICE_FR[1]})...")
            await edge_tts.Communicate(act["script_fr"], VOICE_FR[0], rate=VOICE_FR[1]).save(str(mp3_fr))

def build_video_variant(lang_suffix, voice_name, out_video_path, pub_video_path):
    print(f"\n=== ENCODAGE VIDÉO ANIMÉE : {lang_suffix.upper()} ({voice_name}) ===")
    clip_files = []
    
    for act in ACTS_DATA:
        act_id = act["id"]
        audio_mp3 = TMP_DIR / f"{act_id}_{lang_suffix}.mp3"
        clip_mp4 = TMP_DIR / f"{act_id}_{lang_suffix}.mp4"

        # 1. Générer les 4 sous-slides animées (avec encadrement en pointillé de l'élément actif)
        sub_slides = []
        for idx in range(4):
            slide_file = TMP_DIR / f"{act_id}_sub_{idx}.png"
            render_animated_slide(act, active_idx=idx, out_path=slide_file)
            sub_slides.append(slide_file)

        # 2. Calculer le timing par tranche
        total_audio_dur = get_audio_duration(audio_mp3)
        clip_dur = total_audio_dur + 1.2
        slice_dur = total_audio_dur / 4.0

        # 3. Créer la playlist de concaténation des slides animées
        anim_list = TMP_DIR / f"{act_id}_{lang_suffix}_anim.txt"
        with open(anim_list, "w") as f:
            for idx in range(4):
                f.write(f"file '{sub_slides[idx]}'\n")
                f.write(f"duration {slice_dur:.2f}\n")
            # Répéter la dernière image pour clore la séquence
            f.write(f"file '{sub_slides[3]}'\n")

        print(f"  Clip {act_id} ({clip_dur:.1f}s, 4 tranches animées de {slice_dur:.1f}s)")

        cmd = [
            FFMPEG, "-y",
            "-f", "concat", "-safe", "0", "-i", str(anim_list),
            "-i", str(audio_mp3),
            "-c:v", "libx264", "-pix_fmt", "yuv420p",
            "-c:a", "aac", "-b:a", "192k",
            "-t", f"{clip_dur:.2f}",
            "-shortest", str(clip_mp4)
        ]
        subprocess.run(cmd, capture_output=True, check=True)
        clip_files.append(clip_mp4)

    concat_list = TMP_DIR / f"concat_{lang_suffix}.txt"
    with open(concat_list, "w") as f:
        for c in clip_files:
            f.write(f"file '{c}'\n")

    # Assembler avec sous-titres intégrés
    cmd_concat = [
        FFMPEG, "-y",
        "-f", "concat", "-safe", "0", "-i", str(concat_list),
        "-i", str(OUT_SUB_DIR / "pitch_en.srt"),
        "-i", str(OUT_SUB_DIR / "pitch_fr.srt"),
        "-i", str(OUT_SUB_DIR / "pitch_fr_ca.srt"),
        "-i", str(OUT_SUB_DIR / "pitch_es.srt"),
        "-i", str(OUT_SUB_DIR / "pitch_de.srt"),
        "-i", str(OUT_SUB_DIR / "pitch_ja.srt"),
        "-map", "0:v", "-map", "0:a",
        "-map", "1:0", "-map", "2:0", "-map", "3:0", "-map", "4:0", "-map", "5:0", "-map", "6:0",
        "-c:v", "copy", "-c:a", "copy",
        "-c:s", "mov_text",
        "-metadata:s:s:0", "language=eng", "-metadata:s:s:0", "title=English",
        "-metadata:s:s:1", "language=fra", "-metadata:s:s:1", "title=Français",
        "-metadata:s:s:2", "language=fra", "-metadata:s:s:2", "title=Français (Canada)",
        "-metadata:s:s:3", "language=spa", "-metadata:s:s:3", "title=Español",
        "-metadata:s:s:4", "language=deu", "-metadata:s:s:4", "title=Deutsch",
        "-metadata:s:s:5", "language=jpn", "-metadata:s:s:5", "title=日本語",
        str(out_video_path)
    ]
    subprocess.run(cmd_concat, capture_output=True, check=True)
    subprocess.run(["cp", str(out_video_path), str(pub_video_path)], check=True)
    print(f"✅ Vidéo {lang_suffix.upper()} Studio déployée : {pub_video_path} ({pub_video_path.stat().st_size / 1e6:.1f} Mo)")

async def main():
    await synthesize_all_audio()
    
    # 1. Version Anglaise Studio Naturelle (Andrew Multilingual)
    build_video_variant(
        "en", VOICE_EN[0],
        ROOT / "docs/figures/paper_pitch_walkthrough.mp4",
        PUB_DIR / "pitch.mp4"
    )

    # 2. Version Français Canadien Posée (Jean Neural)
    build_video_variant(
        "ca", VOICE_CA[0],
        ROOT / "docs/figures/paper_pitch_fr_ca.mp4",
        PUB_DIR / "pitch_fr_ca.mp4"
    )

    # 3. Version Français International Conversationnelle (Rémi Multilingual)
    build_video_variant(
        "fr", VOICE_FR[0],
        ROOT / "docs/figures/paper_pitch_fr.mp4",
        PUB_DIR / "pitch_fr.mp4"
    )
    print("\n🎉 LES TROIS VERSIONS VIDÉO STUDIO NEURAL NATURELLES SONT DÉPLOYÉES !")

if __name__ == "__main__":
    asyncio.run(main())
