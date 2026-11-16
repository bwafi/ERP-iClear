#!/usr/bin/env bash
# Masuk ke shell container.
#
#   ./docker/scripts/shell.sh              -> shell di erp-app (PHP)
#   ./docker/scripts/shell.sh erp-db       -> shell di database
#   ./docker/scripts/shell.sh erp-app php -v
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

service="${1:-erp-app}"
shift || true

exec docker compose exec "$service" bash "$@"
