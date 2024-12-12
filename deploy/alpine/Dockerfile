# Production stage
FROM php:8.2.26-cli-alpine AS base

# Set working directory
WORKDIR /var/www

# Install minimal dependencies
RUN apk update && apk add --no-cache \
    freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev \
    curl postgresql-dev oniguruma-dev supervisor bash

# Copy php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions - Laravel Dependencies
RUN install-php-extensions @composer redis openssl mbstring pdo pdo_pgsql pdo_mysql zip exif gd

# LaraLord dependencies
RUN install-php-extensions inotify apcu sysvmsg pcntl openswoole > /dev/null

# Install NPM
RUN apk add --no-cache nodejs npm

# Add user for Laravel application
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www

COPY deploy/scripts /opt/scripts
COPY deploy/scripts/docker-entrypoint.sh  /docker-entrypoint.sh
RUN chmod +x /opt/scripts/* && chmod +x /docker-entrypoint.sh \
	&& rm -R /var/www/* \
	&& chown www:www /var/www
ENV PATH="${PATH}:/opt/scripts"

WORKDIR /var/www

# Clear cache
RUN rm -rf /var/cache/apk/*

USER www:www

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["laralord", "server:start"]


FROM base AS test

USER root
COPY . /laralord
RUN chown www:www -R /laralord
USER www:www
WORKDIR /laralord
RUN composer install  && composer bin box install
RUN ./vendor/bin/phpunit


ARG APP_VERSION
FROM test AS compile

ARG APP_VERSION
ENV APP_VERSION=$APP_VERSION

RUN composer install --optimize-autoloader --no-dev  \
	&& composer bin box install

RUN  PHP_MEMORY_LIMIT=1024M ./vendor/bin/box compile  --no-parallel

# stage to pubish
FROM base AS build
ARG APP_VERSION
ENV LARALORD_VERSION=$APP_VERSION

USER root

COPY --from=compile ./laralord/bin/laralord /usr/bin/laralord

RUN chmod o+x /usr/bin/laralord

RUN mkdir /secrets && chown www:www -R /secrets

USER www:www
