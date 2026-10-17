# Base and dev Dockerfiles
Create the two development images, following Tent's layering.

`dockerfiles/dev_pidgey-base/Dockerfile`:
- `FROM php:8.4-apache AS base`.
- apt: `unzip git curl libzip-dev $PHPIZE_DEPS`.
- Extensions: `docker-php-ext-install zip pdo pdo_mysql`, then `pecl install pcov` and
  `docker-php-ext-enable pcov`.
- `a2enmod rewrite`; global Composer (`COPY --from=composer:2 /usr/bin/composer`).
- User `app`, uid 1000, home `/home/app`; app root `/home/app/app`.
- Point Apache's document root at `/home/app/app/public`, by a symlink of `/var/www/html` or by
  setting `APACHE_DOCUMENT_ROOT`.
- `ENV PATH="$PATH:/home/app/app/vendor/bin"`; `WORKDIR /home/app/app`; `USER app`.
- `FROM base AS builder`: `COPY --chown=app:app composer.* ./`, then `RUN composer install`.
- Final stage `FROM base`: `COPY --from=builder /home/app/app/vendor/ /home/app/app/vendor/`,
  then `CMD ["apache2-foreground"]`.
- Apache must be able to bind port 80 while the container user is `app`. Either run Apache as
  root and drop to `app` only for interactive shells, or configure Apache to run as `app` with
  `APACHE_RUN_USER`/`APACHE_RUN_GROUP`. Match Tent's working approach and keep it consistent
  with the compose services.

`dockerfiles/dev_pidgey/Dockerfile`:
- `ARG BASE_VERSION`, `ARG BASE_SUFFIX=""`.
- `FROM darthjee/dev_pidgey-base:${BASE_VERSION}${BASE_SUFFIX}`.
- `COPY --chown=app:app composer.* ./`, then `RUN composer install && composer dump-autoload`.

Both builds use `source/` as the context. The base image is built per architecture
(`<version>` on amd64, `<version>-arm64` on arm64).

## Files to Change
- `dockerfiles/dev_pidgey-base/Dockerfile` — new.
- `dockerfiles/dev_pidgey/Dockerfile` — new.
