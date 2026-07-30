# PHP-FPM image for the `nginx` compose profile (docker compose --profile nginx up).
#
# This exists to mirror the live server's actual stack (nginx + PHP-FPM, see
# deploy/nginx/oeparts.conf and deploy/php-fpm/oeparts-pool-overrides.conf) —
# laravel.test/hostingsim both run PHP's built-in server instead, which never
# exercises nginx's request routing (the index.php/oe-recovery.php-only PHP-FPM
# carve-out, static asset caching, nginx's own client_max_body_size cap).
#
# Extension list matches what the app actually requires: composer.json's
# require block (pdo_mysql, bcmath, intl) + config/imports.php's Bulk Product
# Import (zip, gd — PhpSpreadsheet/openspout read/write XLSX) + Redis being the
# default CACHE_STORE/SESSION_DRIVER/QUEUE_CONNECTION in .env.docker.overrides.
FROM php:8.3-fpm

#
# The -dev packages are NOT purged after the build (unlike a typical
# multi-stage trim): apt's dependency resolver installs each -dev package's
# real runtime .so (libpng16-16, libicu*, libzip4, ...) only as an automatic
# dependency, never as an explicitly-requested package — so an `apt-get
# purge --auto-remove` after docker-php-ext-install cascades into removing
# those runtime libraries too, leaving gd/intl/zip compiled against .so files
# that no longer exist on disk (confirmed empirically: PHP-FPM logged
# "Unable to load dynamic library 'gd'" / libpng16.so.16 missing on startup).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli bcmath gd intl zip opcache exif \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Matches production's baseline (display_errors off, etc.) rather than the
# development template — deploy/php-fpm/oeparts-pool-overrides.conf is
# bind-mounted in by compose.yaml on top of this, not copied here, so both
# environments read the exact same overrides file with no risk of drift.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html
