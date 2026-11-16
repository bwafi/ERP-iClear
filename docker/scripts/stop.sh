#!/usr/bin/env bash
# Hentikan stack (container berhenti, data & volume TETAP AMAN).
#
# Sengaja memakai `compose stop`, bukan `compose down`/`down -v`:
#   - container tidak dihapus, mudah di-start lagi
#   - network & volume tidak ikut terhapus
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

docker compose stop
echo ">> Stack berhenti. Data aman di volume erp-db-data dan folder proyek."
