FROM cimg/php:8.4

USER root

# PHP extensions needed by the backend (zip, pdo_mysql) plus pcov for coverage.
# cimg's pecl registers extensions in /etc/php.d/circleci.ini, so pcov is only
# enabled explicitly when pecl did not already load it.
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    unzip \
    git \
    curl \
    libzip-dev \
    $PHPIZE_DEPS \
  && (php -m | grep -qi '^zip$' || docker-php-ext-install zip) \
  && (php -m | grep -qi '^pdo_mysql$' || docker-php-ext-install pdo_mysql) \
  && (php -m | grep -qi '^pcov$' || pecl install pcov) \
  && (php -m 2>/dev/null | grep -qi '^pcov$' || docker-php-ext-enable pcov) \
  && (command -v composer >/dev/null \
    || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer) \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/pear

RUN mkdir -p /home/circleci/project /home/circleci/vendor-build \
  && chown -R circleci:circleci /home/circleci

USER circleci

ENV PATH="$PATH:/home/circleci/project/vendor/bin"

# Pre-install dependencies; CI jobs copy /home/circleci/vendor into the
# checked-out project and run `composer install` to sync it.
WORKDIR /home/circleci/vendor-build
COPY --chown=circleci:circleci composer.* ./
RUN composer install --no-interaction --no-progress \
  && mv vendor /home/circleci/vendor \
  && cd /home/circleci \
  && rm -rf /home/circleci/vendor-build

WORKDIR /home/circleci/project
