ARG BASE_VERSION=0.0.1
ARG BASE_SUFFIX=""
FROM darthjee/dev_pidgey-base:${BASE_VERSION}${BASE_SUFFIX}

# phpDocumentor is installed as a pinned, sha256-verified phar (not a
# Composer dependency). Dev image only; the CI and production images do
# not get it.
ARG PHPDOC_VERSION=3.10.0
ARG PHPDOC_SHA256=fe1e7c23ba3329aa6f19ac3c807446159a431a195ec5d9163b0c281a15105207

USER root
RUN curl -fsSL -o /tmp/phpdoc.phar \
    "https://github.com/phpDocumentor/phpDocumentor/releases/download/v${PHPDOC_VERSION}/phpDocumentor.phar" \
  && echo "${PHPDOC_SHA256}  /tmp/phpdoc.phar" | sha256sum -c - \
  && install -m 0755 /tmp/phpdoc.phar /usr/local/bin/phpdoc \
  && rm /tmp/phpdoc.phar
USER app

COPY --chown=app:app composer.* ./
RUN composer install --no-interaction --no-progress \
  && composer dump-autoload
