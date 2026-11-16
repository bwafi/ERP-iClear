#!/usr/bin/env bash
# Tampilkan log container.
#
#   ./docker/scripts/logs.sh              -> semua service
#   ./docker/scripts/logs.sh erp-app      -> hanya aplikasi
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

exec docker compose logs -f --tail=200 "$@"
