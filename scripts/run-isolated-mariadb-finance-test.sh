#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENDOR_DIR="${ERP_VENDOR_DIR:-$ROOT/vendor}"
if [[ ! -f "$VENDOR_DIR/bin/phpunit" ]]; then
    VENDOR_DIR="$(cd "$ROOT/.." && pwd)/ERP-iClear-official/vendor"
fi
if [[ ! -f "$VENDOR_DIR/bin/phpunit" ]]; then
    echo "Composer vendor tidak ditemukan. Jalankan composer install pada checkout ini dahulu." >&2
    exit 2
fi

docker info --format '{{.ServerVersion}}' >/dev/null
RUN_ID="iclear-finance-test-$$"
NETWORK="$RUN_ID-net"
DB="$RUN_ID-db"
PHP_IMAGE="$RUN_ID-php"
cleanup() {
    docker rm -f "$DB" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    docker image rm "$PHP_IMAGE" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

docker network create --internal "$NETWORK" >/dev/null
docker run -d --name "$DB" --network "$NETWORK" --network-alias mariadb-test \
    -e MARIADB_DATABASE=erp_finance_test \
    -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
    mariadb:11.4 --skip-networking=0 >/dev/null

ready=0
for _ in $(seq 1 90); do
    if docker exec "$DB" mariadb-admin ping -uroot --silent >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 2
done
if [[ "$ready" != 1 ]]; then
    docker logs "$DB"
    echo "MariaDB fixture did not become ready." >&2
    exit 3
fi

docker build --tag "$PHP_IMAGE" --file - "$ROOT" <<'DOCKERFILE'
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev zlib1g-dev \
    && docker-php-ext-install mysqli pdo_mysql intl zip \
    && rm -rf /var/lib/apt/lists/*
DOCKERFILE

docker run --rm --network "$NETWORK" \
    --volume "$ROOT:/workspace" \
    --volume "$VENDOR_DIR:/workspace/vendor:ro" \
    --workdir /workspace \
    -e ERP_SYNTHETIC_MARIADB_HOST=mariadb-test \
    -e ERP_SYNTHETIC_MARIADB_PORT=3306 \
    -e ERP_SYNTHETIC_MARIADB_USER=root \
    "$PHP_IMAGE" sh -euc '
        php vendor/bin/phpunit --no-coverage tests/integration/InventoryBatchMariaDbTest.php
        php vendor/bin/phpunit --no-coverage tests/integration/KartuHargaMigrationMariaDbTest.php
        php vendor/bin/phpunit --no-coverage tests/integration/SupplierReturnAvailabilityMariaDbTest.php
        php vendor/bin/phpunit --no-coverage tests/integration/LabaRugiParentPostingMariaDbTest.php
        php vendor/bin/phpunit --no-coverage tests/integration/AssetRecognitionIndexMariaDbTest.php
        php vendor/bin/phpunit --no-coverage tests/unit --filter="BalanceSheetReconciliationTest|DailyCashFlowServiceTest|FinanceMenuNavigationTest|InventoryLedgerMathTest|InventoryLedgerBridgeTest|JournalReportFiltersTest|KartuHargaWarrantyDefaultTest|SupplierCreditReconciliationTest|SupplierCreditJournalTest|SupplierCreditAllocatorTest|WarrantyJournalReconciliationTest|WarrantyServiceAccessTest|WarrantyTermTest|WarrantyClaimEligibilityTest|WarrantyExpenseJournalTest"
        php tests/integration/WarrantyStockMySql.php
        env database.tests.hostname=mariadb-test database.tests.database=erp_finance_test \
            database.tests.username=root database.tests.password= database.tests.DBDriver=MySQLi \
            database.tests.DBPrefix=db_ database.tests.port=3306 \
            php vendor/bin/phpunit --no-coverage --no-logging tests/KasBankControllerTest.php \
            --filter="testKasMasukLintasUnitMencatatUnitSumberPadaJurnal|testKasKeluarLintasUnitMencatatUnitSumberPadaJurnal"
    '
