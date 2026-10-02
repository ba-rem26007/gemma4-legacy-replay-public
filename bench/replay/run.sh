#!/usr/bin/env bash
# Rejoue les tests d'un bug contre une instance. Usage : [PSB=n] bench/replay/run.sh <pr> [oracle|replay]
# (filtre facultatif : « oracle » = verdict caché, « replay » = tests visibles par l'agent)
set -euo pipefail
cd "$(dirname "$0")"; PR="${1:?pr}"
PSB="${PSB:-1}"; PROJ="psbench$([ "$PSB" = 1 ] || echo "$PSB")"
export PS_PORT="${PS_PORT:-$((8080 + PSB))}"
if [ -f "$PR/setup.sql" ]; then
  # erreur MySQL affichée (auparavant masquée : arrêt silencieux, retour vide pour l'agent / gentest)
  if ! SQLOUT=$(docker exec -i "$PROJ-db-1" mysql -padmin prestashop < "$PR/setup.sql" 2>&1); then
    echo "ERREUR setup.sql : $(echo "$SQLOUT" | grep -v 'Using a password')"; exit 3
  fi
fi
docker exec "$PROJ-ps-1" sh -c 'rm -rf /var/www/html/var/cache/*'
# Par défaut : l'oracle seul s'il existe (verdict) ; sinon tous les tests du dossier (bugs pilotes).
FILTER="${2:-}"
[ -z "$FILTER" ] && compgen -G "$PR/oracle*" >/dev/null && FILTER=oracle
# Oracles PHP (CLI, dans le conteneur, PrestaShop chargé via config/config.inc.php) : code retour 0 = passe
if [ "$FILTER" = oracle ] && ls "$PR"/oracle*.php >/dev/null 2>&1; then
  RC=0
  for f in "$PR"/oracle*.php; do
    docker cp "$f" "$PROJ-ps-1:/var/www/html/_oracle.php"
    echo "== $f"
    # une seule exécution (l'oracle peut écrire en base) : sortie ET code retour
    if OUT=$(docker exec -u www-data -w /var/www/html "$PROJ-ps-1" timeout 120 php -d display_errors=stderr _oracle.php 2>&1); then
      echo "$OUT" | tail -40; echo "✔ $f"
    else
      echo "$OUT" | tail -40; echo "✘ $f : ÉCHEC (code retour non nul)"; RC=1
    fi
    docker exec "$PROJ-ps-1" rm -f /var/www/html/_oracle.php
  done
  ls "$PR"/oracle*.spec.js >/dev/null 2>&1 || exit $RC
  [ $RC = 0 ] || exit $RC
fi
npx playwright test "$PR/$FILTER"
