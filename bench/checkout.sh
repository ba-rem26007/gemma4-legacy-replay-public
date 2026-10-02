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
import json, glob
pr = int($PR)
b = None
for f in ['$B/catalog.jsonl'] + glob.glob('$B/catalogs/*/*.jsonl'):
    try:
        for line in open(f):
            if line.strip():
                item = json.loads(line)
                if item.get('pr') == pr:
                    b = item
                    break
        if b:
            break
    except Exception:
        pass
if not b:
    raise ValueError(f'PR {pr} introuvable dans les catalogues')
print(b['base_commit'], b['merge_commit'], ','.join(b.get('files', [])), b.get('branch', ''))")

# Release la plus proche AVANT le commit de base (8.x/9.x, sans rc/beta) ; à défaut, première release APRÈS
REL="$(git -C "$PS" describe --tags --abbrev=0 --match '[89].[0-9].[0-9]' --match '1.[67].[0-9]*.[0-9]*' --exclude '*-*' --exclude '*RC*' --exclude '*rc*' "$BASE" 2>/dev/null || true)"
case "$REL" in 9.*) ;; *) [ "$(git -C "$PS" merge-base --is-ancestor 9.0.0 "$BASE" && echo y)" = y ] && REL="" ;; esac
[ -z "$REL" ] && REL="$(git -C "$PS" tag --contains "$BASE" --sort=v:refname | grep -E '^9\.[0-9]\.[0-9]$' | head -1)"
# Fichiers touchés par un patch d'agent HORS du correctif officiel : à restaurer/tracer aussi,
# sinon ils restent patchés d'une évaluation à l'autre (faux « Reversed patch », pollution du bug suivant).
if [ -f "$MODE" ]; then
  for f in $(sed -n 's#^+++ b/\([^[:space:]]*\).*#\1#p' "$MODE" | sort -u); do
    case ",$FILES," in *",$f,"*) ;; *) FILES="$FILES,$f" ;; esac
  done
fi
# Branche develop : la release pertinente est la PREMIÈRE qui contient ce code (pas le tag ancêtre, d'une autre ligne)
if [ "$BRANCH" = "develop" ]; then
  NEXT="$(git -C "$PS" tag --contains "$BASE" | grep -E '^(1\.[67]\.[0-9]+\.[0-9]+|[89]\.[0-9]\.[0-9])$' | sort -V | head -1)"
  [ -n "$NEXT" ] && REL="$NEXT"
fi
# Branche 9.1.x développée avant la release 9.1.0 : image 9.1.0 minimum
[ "$BRANCH" = "9.1.x" ] && case "$REL" in 9.1.*) ;; *) REL="9.1.0" ;; esac
# idem 9.0.x développée avant la release 9.0.0 (sinon image 8.2.x, incompatible)
[ "$BRANCH" = "9.0.x" ] && case "$REL" in 9.0.*) ;; *) REL="9.0.0" ;; esac
# Image Docker : 8.x → tag = version ; 9.x → variante « classic » (thème classic, PHP 8.1)
case "$REL" in
  9.0.*) IMG="9.0.3-3.0-classic-8.1" ;;
  9.1.0|9.1.1) IMG="$REL-4.0-classic-8.1" ;;
  9.1.*) IMG="$REL-5.0-classic-8.1" ;;
  1.*) # image officielle de la release ; sinon la suivante de la MÊME branche (1.7.8.0 → 1.7.8.1), sinon la précédente
      IMG="$REL"
      if ! grep -qx "$REL" "$ENV/images_1x.txt"; then
        IMG="$(grep -E "^${REL%.*}\.[0-9]+$" "$ENV/images_1x.txt" | { cat; echo "$REL"; } | sort -uV | awk -v r="$REL" 'f{print; exit} $0==r{f=1}')"
        [ -z "$IMG" ] && IMG="$({ cat "$ENV/images_1x.txt"; echo "$REL"; } | sort -uV | awk -v r="$REL" '$0==r{print p; exit} {p=$0}')"
      fi
      export DB_IMAGE="mysql:5.7" ;;   # 1.6/1.7 anciennes : pas de support MySQL 8
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
    # ajouts faits après installation (pack de langue, modules/thèmes importés) : restaurés sans écraser la nouvelle image
    docker exec "$OLD" sh -c 'cd /var/www/html && tar -cf - $(ls -d modules themes translations mails app/Resources/translations 2>/dev/null)' > "$KEEP/extra.tar"
    PS_INSTALL_AUTO=0 $DC up -d --no-deps ps
    C="$($DC ps -q ps)"
    echo -n "attente démarrage"; until docker exec "$C" test -d /var/www/html/admin-dev 2>/dev/null; do echo -n .; sleep 3; done; echo
    docker cp "$KEEP/parameters.php" "$C:/var/www/html/app/config/parameters.php"
    docker exec -i "$C" tar -C /var/www/html -xf - < "$KEEP/img.tar"
    docker exec -i "$C" tar -C /var/www/html --skip-old-files -xf - < "$KEEP/extra.tar" 2>/dev/null || true
    docker exec "$C" sh -c "chown -R www-data: /var/www/html/modules /var/www/html/themes /var/www/html/translations 2>/dev/null; true"
    docker exec -w /var/www/html "$C" sh -c "rm -rf install install-done; chown www-data: app/config/parameters.php; chown -R www-data: img var .htaccess 2>/dev/null; rm -rf var/cache/* 2>/dev/null; true"
  else
    $DC down -v >/dev/null 2>&1 || true
    rm -f "$ENV/.snap-$PROJ.sql.gz"
    PS_INSTALL_AUTO=1 $DC up -d
  fi
  PREV_FILES=""
fi
C="$($DC ps -q ps)"
# Remet dans l'état de la release les fichiers superposés au passage précédent (bug + rattrapage de code)
# — AVANT l'attente : un passage précédent a pu casser le FO.
IFS=, read -ra PF <<< "$PREV_FILES"
if [ "${#PF[@]}" -gt 0 ] && [ -n "${PF[0]}" ]; then
  EXIST=(); for f in "${PF[@]}"; do git -C "$PS" cat-file -e "$REL:$f" 2>/dev/null && EXIST+=("$f") || docker exec "$C" rm -f "/var/www/html/$f"; done
  [ "${#EXIST[@]}" -gt 0 ] && git -C "$PS" archive "$REL" -- "${EXIST[@]}" | docker exec -i "$C" tar -C /var/www/html -xf -
fi
echo -n "attente install"; T0=$(date +%s); until curl -sf -o /dev/null "http://localhost:$PS_PORT/"; do
  [ $(( $(date +%s) - T0 )) -gt 900 ] && { echo " (FO ne répond pas après 15 min, on continue)"; break; }; echo -n .; sleep 5; done; echo " ok"

# RESET (kit reset.sh) : base de référence = instantané pris juste après l'installation ;
# restauré avant chaque bug pour qu'aucun setup.sql / test précédent ne contamine le suivant.
SNAP="$ENV/.snap-$PROJ.sql.gz"; DB="$PROJ-db-1"
if [ ! -f "$SNAP" ]; then
  docker exec "$DB" sh -c 'mysqldump -padmin --single-transaction --no-tablespaces prestashop 2>/dev/null' | gzip > "$SNAP"
  echo "instantané de base : $(du -h "$SNAP" | cut -f1)"
elif [ "${NO_RESET:-0}" != 1 ]; then
  gunzip -c "$SNAP" | docker exec -i "$DB" sh -c 'mysql -padmin prestashop 2>/dev/null'
  echo "base restaurée depuis l'instantané"
fi


# RATTRAPAGE DE CODE : si le commit de base est POSTÉRIEUR à la release de l'image, on applique tous les fichiers
# serveur (php/tpl/twig) modifiés entre les deux → le code est exactement celui du commit (ex. méthode arrivée en 9.1.2).
DRIFT=()
# DÉSACTIVÉ par défaut (DRIFT=1 pour l'activer) : sans les dépendances Composer / la config de l'image,
# recopier les classes récentes casse le conteneur Symfony (BO 500/308) → faux négatifs à l'évaluation.
if [ "${DRIFT:-0}" = 1 ] && git -C "$PS" merge-base --is-ancestor "$REL" "$BASE" 2>/dev/null; then
  mapfile -t DRIFT < <(git -C "$PS" diff --name-only "$REL" "$BASE" -- '*.php' '*.tpl' '*.twig' '*.yml' '*.yaml' '*.xml' ':!tests/**' ':!install-dev/**' ':!vendor/**' ':!*.dist' ':!phpunit*' ':!.github/**')
  if [ "${#DRIFT[@]}" -gt 400 ]; then echo "rattrapage ignoré : ${#DRIFT[@]} fichiers (> 400)"; DRIFT=()
  elif [ "${#DRIFT[@]}" -gt 0 ]; then
    EXIST=(); for f in "${DRIFT[@]}"; do git -C "$PS" cat-file -e "$BASE:$f" 2>/dev/null && EXIST+=("$f") || docker exec "$C" rm -f "/var/www/html/$f"; done
    [ "${#EXIST[@]}" -gt 0 ] && git -C "$PS" archive "$BASE" -- "${EXIST[@]}" | docker exec -i "$C" tar -C /var/www/html -xf -
    echo "rattrapage de code : ${#DRIFT[@]} fichiers ($REL → ${BASE:0:10})"
  fi
fi
printf '%s\n%s\n' "$REL" "$(IFS=,; echo "${DRIFT[*]}${DRIFT[*]:+,}$FILES")" > "$STATE"

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

# SANTÉ : le rattrapage de code peut casser le conteneur Symfony (dépendances Composer absentes de l'image).
# Si le BO ne répond plus alors qu'un rattrapage a été appliqué → on l'annule (fichiers de la release) et on repart
# de la seule superposition des fichiers du bug.
health() { local c; c=$(curl -s -o /dev/null -w "%{http_code}" -m 60 "http://localhost:$PS_PORT/admin-dev/index.php?controller=AdminLogin"); [ "$c" != 500 ] && [ "$c" != 000 ]; }
if [ "${#DRIFT[@]}" -gt 0 ] && ! health; then
  echo "rattrapage de code annulé : le BO répond 500 (dépendances absentes de l'image)"
  EXIST=(); for f in "${DRIFT[@]}"; do git -C "$PS" cat-file -e "$REL:$f" 2>/dev/null && EXIST+=("$f") || docker exec "$C" rm -f "/var/www/html/$f"; done
  [ "${#EXIST[@]}" -gt 0 ] && git -C "$PS" archive "$REL" -- "${EXIST[@]}" | docker exec -i "$C" tar -C /var/www/html -xf -
  for f in "${FS[@]}"; do
    tmp="$(mktemp)"
    if git -C "$PS" show "$REF:$f" > "$tmp" 2>/dev/null; then docker cp "$tmp" "$C:/var/www/html/$f"; else docker exec "$C" rm -f "/var/www/html/$f"; fi
    rm -f "$tmp"
  done
  if [ -f "$MODE" ]; then docker cp "$MODE" "$C:/tmp/p.diff"; docker exec -w /var/www/html "$C" patch -p1 -i /tmp/p.diff; fi
  docker exec -w /var/www/html "$C" sh -c "rm -rf var/cache/* 2>/dev/null; true"
  printf '%s\n%s\n' "$REL" "$(IFS=,; echo "${DRIFT[*]},$FILES")" > "$STATE"
  echo "DRIFT=annule" >> "$STATE"
fi
echo "prêt : http://localhost:$PS_PORT/  BO : http://localhost:$PS_PORT/admin-dev  (demo@prestashop.com / prestashop_demo)"
