#!/usr/bin/env bash
# Backup database ke folder backups/ (gitignored), format .sql.gz.
#
#   ./docker/scripts/backup-db.sh
#
# Bisa dipakai sebelum migrasi (container lama `erp-db`) maupun setelah
# migrasi (service compose `erp-db`).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

BACKUP_DIR="${BACKUP_DIR:-$ROOT/backups}"
DB_NAME="${MARIADB_DATABASE:-erp_local}"
DB_ROOT_PASSWORD="${MARIADB_ROOT_PASSWORD:-root}"

mkdir -p "$BACKUP_DIR"
stamp="$(date +%Y-%m-%d_%H%M%S)"
out="$BACKUP_DIR/${DB_NAME}_${stamp}.sql.gz"

# Utamakan service compose; fallback ke container lama dengan nama erp-db.
if [ -n "$(docker compose ps -q erp-db 2>/dev/null || true)" ]; then
  dump=(docker compose exec -T erp-db mariadb-dump)
else
  dump=(docker exec -i erp-db mariadb-dump)
fi

"${dump[@]}" \
  -uroot -p"$DB_ROOT_PASSWORD" \
  --single-transaction --quick \
  --routines --triggers --events \
  --default-character-set=utf8mb4 \
  --add-drop-table \
  "$DB_NAME" | gzip > "$out"

# Validasi: gzip utuh dan dump benar-benar berisi tabel.
gzip -t "$out"
tables="$(gunzip -c "$out" | grep -c '^CREATE TABLE' || true)"

if [ "${tables:-0}" -eq 0 ]; then
  echo "ERROR: dump tidak berisi CREATE TABLE. File dihapus: $out" >&2
  rm -f "$out"
  exit 1
fi

size="$(du -h "$out" | cut -f1)"
echo "OK  $out"
echo "    ukuran: $size, tabel: $tables"
echo
echo "Backup terbaru:"
ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | head -5
