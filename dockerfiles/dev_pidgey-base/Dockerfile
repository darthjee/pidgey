FROM php:8.4-apache AS base

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    unzip \
    git \
    curl \
    libzip-dev \
    $PHPIZE_DEPS \
  && docker-php-ext-install zip pdo pdo_mysql \
  && pecl install pcov \
  && docker-php-ext-enable pcov \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/pear

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -u 1000 -U -m -d /home/app -s /bin/bash app \
  && mkdir -p /home/app/app/public /home/app/app/vendor \
  && chown -R app:app /home/app

# Apache serves the mounted source/public/ and runs as the app user.
# Docker allows unprivileged containers to bind port 80, and the php image
# keeps /var/run/apache2 and /var/lock/apache2 world-writable.
ENV APACHE_DOCUMENT_ROOT=/home/app/app/public \
  APACHE_RUN_USER=app \
  APACHE_RUN_GROUP=app

RUN rm -rf /var/www/html \
  && ln -s /home/app/app/public /var/www/html \
  && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
  && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

ENV PATH="$PATH:/home/app/app/vendor/bin"

WORKDIR /home/app/app
USER app

FROM base AS builder

COPY --chown=app:app composer.* ./
RUN composer install --no-interaction --no-progress

FROM base

COPY --from=builder --chown=app:app /home/app/app/vendor/ /home/app/app/vendor/

EXPOSE 80
CMD ["apache2-foreground"]
