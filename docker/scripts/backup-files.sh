#!/usr/bin/env bash
# Backup file upload & template ke backups/ (gitignored), format .tgz.
#
#   ./docker/scripts/backup-files.sh
#
# Yang diarsipkan adalah folder yang memang ada. Kalau sebuah folder belum
# pernah dibuat (misal aplikasi belum menerima upload), folder itu dilewati
# tanpa membuat script gagal.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

BACKUP_DIR="${BACKUP_DIR:-$ROOT/backups}"
mkdir -p "$BACKUP_DIR"

candidates=(
  public/foto_nota
  public/foto_presensi
  public/foto_tugas
  public/foto_kk
  public/foto_ktp
  public/format_excell
  public/qr_service
  public/uploads
  writable/uploads
)

existing=()
for dir in "${candidates[@]}"; do
  [ -d "$dir" ] && existing+=("$dir")
done

if [ "${#existing[@]}" -eq 0 ]; then
  echo "Tidak ada folder upload yang ditemukan. Tidak ada yang dibackup." >&2
  exit 1
fi

stamp="$(date +%Y-%m-%d_%H%M%S)"
out="$BACKUP_DIR/uploads_${stamp}.tgz"

tar -czf "$out" "${existing[@]}"

# Validasi: arsip bisa dibaca dan tidak kosong.
gzip -t "$out"
members="$(tar -tzf "$out" | wc -l)"
if [ "${members:-0}" -eq 0 ]; then
  echo "ERROR: arsip kosong. File dihapus: $out" >&2
  rm -f "$out"
  exit 1
fi

size="$(du -h "$out" | cut -f1)"
echo "OK  $out"
echo "    ukuran: $size, entri: $members"
echo "    folder: ${existing[*]}"
