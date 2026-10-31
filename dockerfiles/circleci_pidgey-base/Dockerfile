# Deploy tooling (deploy_frontend.sh) comes from darthjee/scripts; bump
# SCRIPTS_VERSION to pick up a new release.
ARG SCRIPTS_VERSION=0.11.0
FROM darthjee/scripts:${SCRIPTS_VERSION} AS scripts

FROM cimg/php:8.4

USER root

# PHP extensions needed by the backend (zip, pdo_mysql) plus pcov for coverage,
# and the deploy tooling (openssh-client, rsync) used by deploy_frontend.sh.
# cimg's pecl registers extensions in /etc/php.d/circleci.ini, so pcov is only
# enabled explicitly when pecl did not already load it.
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    unzip \
    git \
    curl \
    libzip-dev \
    openssh-client \
    rsync \
    $PHPIZE_DEPS \
  && (php -m | grep -qi '^zip$' || docker-php-ext-install zip) \
  && (php -m | grep -qi '^pdo_mysql$' || docker-php-ext-install pdo_mysql) \
  && (php -m | grep -qi '^pcov$' || pecl install pcov) \
  && (php -m 2>/dev/null | grep -qi '^pcov$' || docker-php-ext-enable pcov) \
  && (command -v composer >/dev/null \
    || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer) \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/pear

# Frontend deploy script (rsync over SSH), used by the deploy jobs.
COPY --from=scripts /home/scripts/sbin/deploy_frontend.sh /usr/local/sbin/deploy_frontend.sh
RUN chmod 0755 /usr/local/sbin/deploy_frontend.sh

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
