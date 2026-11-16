#!/usr/bin/env bash
# Nyalakan stack development ERP iClear.
#
# Aman dijalankan berulang kali (idempotent). Tidak pernah memakai `down -v`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: plugin 'docker compose' belum tersedia." >&2
  echo "Install di Arch: sudo pacman -S docker-compose" >&2
  exit 1
fi

# .env lokal tidak ikut Git; buat dari contoh bila belum ada.
if [ ! -f .env ]; then
  cp .env.example .env
  echo ">> .env dibuat dari .env.example (silakan sesuaikan bila perlu)"
fi

# Volume database bersifat "external" sehingga compose tidak membuatnya.
# Tanpa ini, clone baru gagal dengan: external volume "erp-db-data" not found.
if ! docker volume inspect erp-db-data >/dev/null 2>&1; then
  docker volume create erp-db-data >/dev/null
  echo ">> volume erp-db-data dibuat (masih kosong)"
fi

# Jangan start kalau container lama masih memegang port / volume yang sama.
for c in erp-app erp-db erp-adminer; do
  if [ "$(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null || true)" = "true" ]; then
    echo "ERROR: container lama '$c' masih berjalan." >&2
    echo "Hentikan dulu: docker stop erp-adminer erp-app erp-db" >&2
    exit 1
  fi
done

docker compose up -d --build
docker compose ps

echo
echo "App     : http://localhost:8080"
echo "Adminer : http://localhost:8081  (server: erp-db, user: erp, db: erp_local)"
