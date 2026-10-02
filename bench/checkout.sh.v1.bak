#!/usr/bin/env bash
# Prépare PrestaShop pour un bug : image release la plus proche + fichiers touchés dans l'état voulu.
# Usage : bench/checkout.sh <pr> [pre|post|patch.diff]   (défaut : pre = code AVANT le correctif)
set -euo pipefail
B="$(cd "$(dirname "$0")" && pwd)"; PS="$B/ps"; ENV="$B/env"
PR="${1:?pr}"; MODE="${2:-pre}"
# Instance : PSB=1 (défaut, psbench/8081), PSB=2 (psbench2/8082), PSB=3 (psbench3/8083)…
PSB="${PSB:-1}"; PROJ="psbench$([ "$PSB" = 1 ] || echo "$PSB")"
export PS_PORT="${PS_PORT:-$((8080 + PSB))}"
DC="docker compose -p $PROJ -f $ENV/docker-compose.yml"

read -r BASE MERGE FILES BRANCH < <(python3 -c "
import json,sys
b=next(b for b in map(json.loads,open('$B/catalog.jsonl')) if b['pr']==$PR)
print(b['base_commit'],b['merge_commit'],','.join(b['files']),b.get('branch',''))")
# Release la plus proche AVANT le commit de base (8.x/9.x, sans rc/beta) ; à défaut, première release APRÈS
REL="$(git -C "$PS" describe --tags --abbrev=0 --match '[89].[0-9].[0-9]' "$BASE" 2>/dev/null || true)"
case "$REL" in 9.*) ;; *) [ "$(git -C "$PS" merge-base --is-ancestor 9.0.0 "$BASE" && echo y)" = y ] && REL="" ;; esac
[ -z "$REL" ] && REL="$(git -C "$PS" tag --contains "$BASE" --sort=v:refname | grep -E '^9\.[0-9]\.[0-9]$' | head -1)"
# Branche 9.1.x développée avant la release 9.1.0 : image 9.1.0 minimum
[ "$BRANCH" = "9.1.x" ] && case "$REL" in 9.1.*) ;; *) REL="9.1.0" ;; esac
# Image Docker : 8.x → tag = version ; 9.x → variante « classic » (thème classic, PHP 8.1)
case "$REL" in
  9.0.*) IMG="9.0.3-3.0-classic-8.1" ;;
  9.1.0|9.1.1) IMG="$REL-4.0-classic-8.1" ;;
  9.1.*) IMG="$REL-5.0-classic-8.1" ;;
  *) IMG="$REL" ;;
esac
export PS_TAG="$IMG"
echo "PR #$PR  base=${BASE:0:10}  release=$REL  image=prestashop:$PS_TAG  mode=$MODE"

# État de l'instance (hôte) : release courante + fichiers superposés au passage précédent
STATE="$ENV/.state-$PROJ"; KEEP="$ENV/.keep-$PROJ"
CUR_REL="$(sed -n 1p "$STATE" 2>/dev/null || true)"; PREV_FILES="$(sed -n 2p "$STATE" 2>/dev/null || true)"
CUR="$($DC images ps --format json 2>/dev/null | python3 -c 'import sys,json;d=sys.stdin.read().strip();print(json.loads(d)[0]["Tag"] if d.startswith("[") and d!="[]" else "")' || true)"
newer() { [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -1)" = "$2" ]; }
if [ "$CUR" != "$PS_TAG" ]; then
  if [ -n "$CUR" ] && [ "${CUR_REL%.*}" = "${REL%.*}" ] && newer "$CUR_REL" "$REL" && [ "${REL%%.*}" = 9 ]; then
    # MONTÉE INCRÉMENTALE (même branche, version plus récente) : on garde la base MySQL,
    # seul le code change (schéma identique en 9.1.x : install-dev/data/db_structure.sql inchangé).
    echo "montée $CUR_REL → $REL (base conservée)"
    OLD="$($DC ps -q ps)"; rm -rf "$KEEP"; mkdir -p "$KEEP"
    docker cp "$OLD:/var/www/html/app/config/parameters.php" "$KEEP/parameters.php"
    # fichiers générés à l'installation (absents de l'image) : images, .htaccess (URL simplifiées), robots.txt
    docker exec "$OLD" sh -c 'cd /var/www/html && tar -cf - img $(ls -d .htaccess robots.txt 2>/dev/null)' > "$KEEP/img.tar"
    PS_INSTALL_AUTO=0 $DC up -d --no-deps ps
    C="$($DC ps -q ps)"
    echo -n "attente démarrage"; until docker exec "$C" test -d /var/www/html/admin-dev 2>/dev/null; do echo -n .; sleep 3; done; echo
    docker cp "$KEEP/parameters.php" "$C:/var/www/html/app/config/parameters.php"
    docker exec -i "$C" tar -C /var/www/html -xf - < "$KEEP/img.tar"
    docker exec -w /var/www/html "$C" sh -c "rm -rf install install-done; chown www-data: app/config/parameters.php; chown -R www-data: img var .htaccess 2>/dev/null; rm -rf var/cache/* 2>/dev/null; true"
  else
    $DC down -v >/dev/null 2>&1 || true
    PS_INSTALL_AUTO=1 $DC up -d
  fi
  PREV_FILES=""
fi
C="$($DC ps -q ps)"
echo -n "attente install"; until curl -sf -o /dev/null "http://localhost:$PS_PORT/"; do echo -n .; sleep 5; done; echo " ok"

# Remet dans l'état de la release les fichiers superposés au passage précédent (évite la contamination entre bugs)
IFS=, read -ra PF <<< "$PREV_FILES"
for f in "${PF[@]}"; do
  [ -z "$f" ] && continue
  tmp="$(mktemp)"
  if git -C "$PS" show "$REL:$f" > "$tmp" 2>/dev/null; then docker cp "$tmp" "$C:/var/www/html/$f"; docker exec "$C" chown www-data: "/var/www/html/$f"
  else docker exec "$C" rm -f "/var/www/html/$f"; fi
  rm -f "$tmp"
done
printf '%s\n%s\n' "$REL" "$FILES" > "$STATE"

# Fichiers touchés : état pre (base) ou post (merge), puis patch éventuel
REF="$BASE"; [ "$MODE" = post ] && REF="$MERGE"
IFS=, read -ra FS <<< "$FILES"
for f in "${FS[@]}"; do
  tmp="$(mktemp)"
  if git -C "$PS" show "$REF:$f" > "$tmp" 2>/dev/null; then docker cp "$tmp" "$C:/var/www/html/$f"
  else docker exec "$C" rm -f "/var/www/html/$f"; fi
  rm -f "$tmp"
done
if [ -f "$MODE" ]; then docker cp "$MODE" "$C:/tmp/p.diff"; docker exec -w /var/www/html "$C" patch -p1 < /dev/null -i /tmp/p.diff; fi
# chown ciblé (un chown -R sur tout l'arbre force la recopie overlayfs de milliers de fichiers)
docker exec -w /var/www/html "$C" sh -c "chown www-data: ${FS[*]} 2>/dev/null; rm -rf var/cache/* 2>/dev/null; true"
echo "prêt : http://localhost:$PS_PORT/  BO : http://localhost:$PS_PORT/admin-dev  (demo@prestashop.com / prestashop_demo)"
