#!/usr/bin/env bash
# Atomic release on the VPS (DOC 7-design/2.6 section 6, NFR-OPS-01..03).
#   release.sh deploy <app dir> <release id> <database>    run by the GitHub action
#   release.sh rollback <app dir>                          run by hand, back to the previous release
# Layout: <app>/current -> releases/<id> · <app>/shared/{.env,storage,backups,backup.cnf} · <app>/incoming
# .env is linked, never read here. backup.cnf is a MySQL option file (0600) that the owner creates.
set -Eeuo pipefail
umask 027

MODE="${1:?mode}"; APP="${2:?app dir}"
PHP="${DEPLOY_PHP:-php}"
KEEP=5
DUMP="${DEPLOY_MYSQLDUMP:-/opt/mysql-8.4/bin/mysqldump}"   # the new databases are on MySQL 8.4, port 3307
RELEASES="$APP/releases"; SHARED="$APP/shared"; CURRENT="$APP/current"
[[ "$APP" == /* && "$APP" != "/" ]] || { echo "app dir must be an absolute path"; exit 1; }
[[ -d "$SHARED" ]] || { echo "missing $SHARED: prepare the server folders first"; exit 1; }

exec 9>"$SHARED/.deploy.lock"
flock -n 9 || { echo "another deploy is running"; exit 1; }

switch_to() { ln -sfn "$1" "$APP/.current.new"; mv -T "$APP/.current.new" "$CURRENT"; }

restart_processes() {
  cd "$CURRENT"
  "$PHP" artisan queue:restart
}

if [[ "$MODE" == rollback ]]; then
  NOW="$(readlink -f "$CURRENT")"
  PREVIOUS="$(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d ! -name '*.failed' | sort | awk -v now="$NOW" '$0 < now' | tail -1)"
  [[ -n "$PREVIOUS" ]] || { echo "no older release to go back to"; exit 1; }
  switch_to "$PREVIOUS"; restart_processes
  echo "rolled back to $(basename "$PREVIOUS"). The database was not changed; backups are in $SHARED/backups"
  exit 0
fi
[[ "$MODE" == deploy ]] || { echo "unknown mode"; exit 1; }

ID="${3:?release id}"; DB="${4:?database}"
[[ "$ID" =~ ^[0-9]{14}-[0-9a-f]{7,40}$ ]] || { echo "bad release id"; exit 1; }
[[ "$DB" =~ ^[A-Za-z0-9_]+$ ]] || { echo "bad database name"; exit 1; }
[[ -f "$SHARED/.env" ]] || { echo "missing $SHARED/.env"; exit 1; }
[[ -f "$SHARED/backup.cnf" ]] || { echo "missing $SHARED/backup.cnf"; exit 1; }

REL="$RELEASES/$ID"; ARCHIVE="$APP/incoming/$ID.tar.gz"
trap 'echo "deploy failed, the running release was not changed"; [[ -d "$REL" && "$(readlink -f "$CURRENT" 2>/dev/null)" != "$REL" ]] && mv "$REL" "$REL.failed"; exit 1' ERR

mkdir -p "$REL" "$SHARED/backups" "$SHARED"/storage/{app/private,app/public,logs,framework/cache/data,framework/sessions,framework/views}
tar -xzf "$ARCHIVE" -C "$REL"
rm -rf "$REL/storage"
ln -s "$SHARED/storage" "$REL/storage"
ln -s "$SHARED/.env" "$REL/.env"
mkdir -p "$REL/bootstrap/cache"

cd "$REL"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan event:cache

# The backup always comes before the migrations.
"$DUMP" --defaults-extra-file="$SHARED/backup.cnf" --single-transaction --quick --routines --triggers --no-tablespaces "$DB" \
  | gzip > "$SHARED/backups/$ID.sql.gz"
"$PHP" artisan migrate --force --no-interaction

switch_to "$REL"
trap - ERR
restart_processes

rm -f "$ARCHIVE"
# Keep the last five releases, and never the one that is live.
LIVE="$(readlink -f "$CURRENT")"
find "$RELEASES" -mindepth 1 -maxdepth 1 -type d | sort -r | awk -v live="$LIVE" '$0 != live' | tail -n +"$KEEP" | while read -r old; do rm -rf -- "$old"; done
echo "released $ID"
