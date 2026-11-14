# EduKSX-Glossary – Produktions-Image (aus sek-intranet übernommen, SQLite statt JSON-Dateien)
# Build: Composer + Node bauen Abhängigkeiten und Frontend; Laufzeit: PHP 8.4 + Apache.

# ---------- Build ----------
FROM php:8.4-cli AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /app

# PHP-Abhängigkeiten (ohne Entwicklungswerkzeuge)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# Frontend-Abhängigkeiten
COPY package.json package-lock.json* .npmrc ./
RUN if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi

# Quellcode, Autoloader, Frontend-Build (Wayfinder braucht php artisan)
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules tests

# ---------- Laufzeit ----------
FROM php:8.4-apache AS app

# zip: Excel-Dateien (.xlsx) für Import/Export; sqlite3: Sicherung der Datenbank (.backup); libpq/pdo_pgsql:
# PostgreSQL (#191, #357)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libpq-dev sqlite3 \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install opcache zip pdo_pgsql \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        'ServerName localhost' \
        'ServerTokens Prod' \
        'ServerSignature Off' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '</Directory>' \
        'Header always set X-Content-Type-Options "nosniff"' \
        'Header always set Referrer-Policy "strict-origin-when-cross-origin"' \
        > /etc/apache2/conf-enabled/glossary.conf \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
        'expose_php=Off' \
        'upload_max_filesize=10M' \
        'post_max_size=12M' \
        'memory_limit=256M' \
        'opcache.enable=1' \
        'opcache.validate_timestamps=0' \
        > "$PHP_INI_DIR/conf.d/glossary.ini"

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/glossary-entrypoint
RUN chmod +x /usr/local/bin/glossary-entrypoint

# Stand des Images (#175): aus der CI (Workflow «image»); ein Deployment kann die Werte über .env überschreiben
ARG GLOSSARY_BUILD=""
ARG GLOSSARY_BUILD_DATE=""
ARG GLOSSARY_BUILD_DESCRIBE=""
ENV GLOSSARY_BUILD=$GLOSSARY_BUILD \
    GLOSSARY_BUILD_DATE=$GLOSSARY_BUILD_DATE \
    GLOSSARY_BUILD_DESCRIBE=$GLOSSARY_BUILD_DESCRIBE

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_FORMAT=json \
    SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/app/database/glossary.sqlite

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'

ENTRYPOINT ["glossary-entrypoint"]
CMD ["apache2-foreground"]
