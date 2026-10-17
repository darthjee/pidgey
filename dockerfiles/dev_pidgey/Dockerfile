ARG BASE_VERSION
ARG BASE_SUFFIX=""
FROM darthjee/dev_pidgey-base:${BASE_VERSION}${BASE_SUFFIX}

COPY --chown=app:app composer.* ./
RUN composer install --no-interaction --no-progress \
  && composer dump-autoload
