# Installer-import rehearsal ("mig-b")

A FRESH, UNINSTALLED copy of the app with its own MySQL, used by `tests/e2e/migration/installer-import.spec.js` (Gate B). It never
touches the dev stack. `playwright.config.js` and `tests/e2e/global-setup.js` refuse the dev host and any already-installed copy.

```
docker compose -f docker/rehearsal/migration/compose.yaml -p mig-b up -d     # app on 127.0.0.1:8213, MySQL "mysql"
# put the code, vendor/ and public/build in the app container's /var/www/html (git archive HEAD | docker exec -i mig-b-laravel.test-1 tar -x -C /var/www/html),
# DELETE any .env (the app makes its own on the first request), make storage/ and bootstrap/cache writable for user sail,
# put a package in storage/app/import/ (older than a minute), create an empty database:
docker exec mig-b-mysql-1 mysql -uroot -pmigb -e "create database mig_b character set utf8mb4 collate utf8mb4_unicode_ci"

OE_E2E_MIGRATION=1 OE_E2E_MIGRATION_URL=http://127.0.0.1:8213 OE_E2E_MIGRATION_PROJECT=mig-b \
OE_E2E_MIGRATION_COMPOSE_FILE=docker/rehearsal/migration/compose.yaml \
OE_E2E_MIGRATION_PACKAGE=rehearsal.oeparts OE_E2E_MIGRATION_PASSPHRASE=... \
OE_E2E_MIGRATION_DB_HOST=mysql OE_E2E_MIGRATION_DB_NAME=mig_b OE_E2E_MIGRATION_DB_USER=root OE_E2E_MIGRATION_DB_PASSWORD=migb \
OE_E2E_MIGRATION_LAUNCH=staging OE_E2E_MIGRATION_PHP_SERVICE=laravel.test \
npx playwright test --project=migration installer-import
```

Use `OE_E2E_MIGRATION_LAUNCH=staging` when the site is reached by a number (127.0.0.1): a number address is only valid for a test copy.
The package needs a super administrator that HAS the super_admin role (the installer refuses a package where nobody could sign in).

Afterwards the same site is installed and can run `admin-share-and-upload.spec.js` with a saved admin session
(`OE_E2E_ADMIN_STATE`, `OE_E2E_ADMIN_PASSWORD`) and `OE_E2E_SMALL_PACKAGE` pointing at a real small package.
Tear down with `docker compose -f docker/rehearsal/migration/compose.yaml -p mig-b down -v`.
