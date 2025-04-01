FROM php:8.2.28-cli AS base
ARG NODE_VERSiON=20

RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install dependencies
RUN apt-get update && apt-get install -yq build-essential libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    locales zip jpegoptim optipng pngquant gifsicle vim unzip git curl libonig-dev libzip-dev libgd-dev \
    libpq-dev netcat-openbsd procps supervisor iputils-ping dnsutils nano

# Install Laravel dependencies
RUN install-php-extensions gd pdo_pgsql	pdo_mysql mbstring zip exif pcntl
# Install LaraLord dependencies
RUN install-php-extensions inotify apcu sysvmsg pcntl openswoole-^25@stable redis @composer > /dev/null

# Add Node.js repository and install Node.js (which includes npm)
RUN curl -fsSL https://deb.nodesource.com/setup_$NODE_VERSiON.x | bash - && \
    apt-get install -yq nodejs && \
    rm -rf /var/lib/apt/lists/*

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

ARG APP_VERSION

FROM test AS compile
ARG APP_VERSION
ENV LARALORD_VERSION=$APP_VERSION

WORKDIR /laralord
RUN composer install --optimize-autoloader --no-dev
RUN composer bin box install

RUN ./vendor/bin/box compile  --no-parallel

FROM base AS build
ARG APP_VERSION
ENV LARALORD_VERSION=$APP_VERSION

COPY --from=compile /laralord/bin/laralord /usr/bin/laralord
USER root
RUN chmod o+x /usr/bin/laralord
RUN mkdir /secrets && chown www:www -R /secrets
USER www:www



