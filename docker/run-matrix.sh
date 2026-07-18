#!/usr/bin/env bash
# Runs the full test suite against every supported PHP 8.x version (compose.yaml's
# `matrix` profile: php83/php85 — php84 is the daily-dev `laravel.test` service and
# gets tested via normal `sail artisan test` runs, so it's included here too for a
# true single-command "test every supported version" pass).
#
# Floor is 8.3, NOT 8.2: filament/actions' openspout/openspout dependency requires
# PHP ~8.3.0 || ~8.4.0 || ~8.5.0 (confirmed via a failed `composer install` under
# 8.2 — a real upstream constraint, not something this project can lower).
#
# Exits non-zero on the first version that fails.
set -euo pipefail

VERSIONS=(83 84 85)

for v in "${VERSIONS[@]}"; do
    service="php${v}"
    if [ "$v" = "84" ]; then
        service="laravel.test"
    fi

    echo "==> PHP 8.${v:1:1} (service: ${service})"
    docker compose --profile matrix run --rm "$service" bash -lc "composer install --no-interaction && php artisan test"
done

echo "==> All PHP versions passed."
