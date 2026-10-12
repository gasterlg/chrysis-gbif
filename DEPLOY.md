# Deploying chrysis-gbif (staging)

Staging only: **https://staging.chrysis.net/gbif** on `185.205.209.183` (Ubuntu 22.04, Apache 2.4 + php8.3-fpm, MariaDB 10.6).
Production (185.206.144.73) is not deployed to; nothing here may be pointed at it.

## Layout on staging

```
/var/www/chrysis-gbif/
  releases/<utc-timestamp>-<sha>/   # one per deploy, last 3 kept
  current -> releases/...           # Apache serves current/public
  shared/.env                       # secrets (DB user password, APP_KEY) – only here
  shared/storage/                   # logs, framework cache/sessions; group www-data, g+w
```

* Apache: in `/etc/apache2/sites-available/chrysis.net-le-ssl.conf` (vhost `staging.chrysis.net`, :443) –
  ```
  Alias /gbif /var/www/chrysis-gbif/current/public
  <Directory /var/www/chrysis-gbif/current/public>
      Options -Indexes +FollowSymLinks
      AllowOverride None
      Require all granted
      FallbackResource /gbif/index.php
  </Directory>
  ```
  The vhost's `FilesMatch \.php$ → php8.3-fpm` handler applies. The :80 vhost redirects to HTTPS.
  WordPress (`/var/www/html/chrysis.net/public`) is untouched; the Alias takes precedence for `/gbif`.
* PHP 8.3 extensions needed (all present): ctype, curl, dom, fileinfo, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip, bcmath.
  No composer on the server: vendor/ is built elsewhere (`composer.json` pins `config.platform.php = 8.3.0`).
* Databases (MariaDB user `chrysis_gbif`@localhost, password only in `shared/.env`):
  * `chrysis_1` – the existing staging copy (real `accounts_api`); migrations add the `gbif_*` tables here (`DB_CHRYSIS_*`).
  * `chrysis_gbif` – app DB: sessions, cache, jobs, `account_permissions`, `gbif_audit_log`.
  * Grants: `ALL` on `chrysis_gbif.*`; `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES` on `chrysis_1.*`.
* `.env` essentials: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://staging.chrysis.net/gbif`,
  `SESSION_PATH=/gbif`, `SESSION_SECURE_COOKIE=true`, `SESSION_LIFETIME=120`, `SESSION_DRIVER=database`,
  `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `LOG_LEVEL=warning`.
* No cron, no scheduler, no resident worker. Imports are manual:
  `cd /var/www/chrysis-gbif/current && php artisan gbif:import ... && php artisan queue:work --stop-when-empty`.

## Re-deploy

```
# On the Mac (has composer + git):
bin/deploy-staging build          # -> storage/app/deploy/chrysis-gbif-<sha>.tgz
# From a machine holding the staging key (or set STAGING_SSH):
bin/deploy-staging push storage/app/deploy/chrysis-gbif-<sha>.tgz
```
`push` uploads, extracts a new release, links `.env`/`storage`, dumps `chrysis_gbif` + `chrysis_1` to
`~/backups/pre-deploy-<ts>.sql.gz`, runs `migrate --force` and `optimize`, switches `current`, reloads php-fpm,
keeps 3 releases and checks `/gbif/login` returns 200. It refuses to run on any host other than 185.205.209.183.

Rollback: `ln -sfn /var/www/chrysis-gbif/releases/<previous> /var/www/chrysis-gbif/current && sudo systemctl reload php8.3-fpm`
(+ restore the pre-deploy dump if a migration must be undone).

## First install (done 2026-10-12)

1. Backups: `~/backups/gbif-deploy-20261012/` – `chrysis_1-pre-gbif.sql.gz` and both Apache vhost files.
2. `CREATE DATABASE chrysis_gbif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`, user + grants as above.
3. `shared/.env` written, `php artisan key:generate`, first release via `bin/deploy-staging push`.
4. Data: the local `gbif_occurrences`, `gbif_import_runs`, `gbif_datasets`, `gbif_match_proposals` were transferred
   (`mysqldump --no-create-info`) instead of re-importing (staging `_specie`/`match` are identical to the local copy,
   verified by checksum). Local test history (`gbif_changes`, audit log) was not transferred.
5. Apache Alias added, `apache2ctl configtest && systemctl reload apache2`.
