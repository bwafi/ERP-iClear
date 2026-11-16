# PHP 7.4 untuk ERP iClear
#
# Dipakai oleh service `erp-app` di compose.yaml.
# Base image dan Composer di-pin ke versi pasti agar build reproducible.
#
# Extension yang WAJIB ada (jangan dihapus):
#   - mysqli, pdo_mysql : koneksi database (CI4 Database.php memakai MySQLi)
#   - intl              : CodeIgniter 4 (locale/format)
#   - gd                : mpdf (render PDF)
#   - zip               : PhpSpreadsheet (xlsx)
#   - bcmath, exif      : dipakai modul ERP (perhitungan & foto)
FROM php:7.4.33-cli

# Debian bullseye sudah EOL, repo resmi 404.
# Arahkan apt ke archive.debian.org dan matikan validasi umur repo.
RUN set -eux; \
    sed -i 's|http://deb.debian.org|http://archive.debian.org|g' /etc/apt/sources.list; \
    sed -i '/bullseye-updates/d' /etc/apt/sources.list; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mysqli \
        pdo_mysql \
        zip; \
    rm -rf /var/lib/apt/lists/*

# Composer 2 (mendukung PHP 7.2.5+)
COPY --from=composer:2.11.2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
