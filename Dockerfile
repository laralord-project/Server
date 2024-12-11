ARG APP_VERSION

FROM php:8.2.26-cli AS base

ARG APP_VERSION
ENV APP_VERSION=$APP_VERSION

RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions inotify apcu sysvmsg pcntl openswoole redis @composer
RUN apt-get update && apt-get install -y git

COPY deploy/scripts /opt/scripts
COPY deploy/scripts/docker-entrypoint.sh  /docker-entrypoint.sh
RUN chmod +x /opt/scripts/* && chown www:www /var/www

WORKDIR /var/www

ENV PATH="${PATH}:/opt/scripts"

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["laralord", "server:start"]

FROM base AS dev

WORKDIR /var/www

RUN echo "memory_limit=1024M" > /usr/local/etc/php/php.ini
RUN mkdir /secrets && chown www:www -R /var/www /secrets
USER www:www

FROM base AS test

COPY . /laralord
RUN chown www:www -R /laralord
USER www:www
WORKDIR /laralord
RUN composer install && composer bin box install
RUN ./vendor/bin/phpunit

FROM test AS compile

WORKDIR /laralord
RUN composer install --optimize-autoloader --no-dev
RUN composer bin box install

RUN ./vendor/bin/box compile  --no-parallel

FROM base AS build

COPY --from=compile /laralord/bin/laralord /usr/bin/laralord
USER root
RUN chmod o+x /usr/bin/laralord
RUN mkdir /secrets && chown www:www -R /secrets
USER www:www

FROM base AS Laravel

