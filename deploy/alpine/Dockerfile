# Production stage
FROM php:8.2.26-cli-alpine AS base

# Set working directory
WORKDIR /var/www

# Install minimal dependencies
RUN apk update && apk add --no-cache \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    curl \
    postgresql-dev \
    oniguruma-dev \
    supervisor

# Clear cache
RUN rm -rf /var/cache/apk/*

# Copy php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions - Laravel Dependencies
RUN install-php-extensions @composer redis yaml openssl mbstring pdo pdo_pgsql pdo_mysql zip exif gd

# LaraLord dependencies
RUN install-php-extensions openswoole inotify apcu sysvmsg
RUN install-php-extensions inotify apcu sysvmsg

#COPY ./helpers /helpers
#RUN chmod +x /helpers/*

# Add user for Laravel application
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www

COPY deploy/scripts /opt/scripts
COPY deploy/scripts/docker-entrypoint.sh  /docker-entrypoint.sh
RUN chmod +x /opt/scripts/* && chown www:www /var/www

WORKDIR /var/www

USER www:www

ENV PATH="${PATH}:/opt/scripts"

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


FROM test AS compile

RUN echo "memory_limit=1024M" > /usr/local/etc/php/php.ini \
	&& composer install --optimize-autoloader --no-dev
RUN ./vendor/bin/box compile  --no-parallel

FROM base AS build

COPY --from=compile ./laralord/bin/laralord.phar /usr/bin/laralord
USER root
RUN chmod o+x /usr/bin/laralord
RUN mkdir /secrets && chown www:www -R /secrets
USER www:www

# Development stage
FROM base AS dev

USER root
# Install additional dependencies for development
RUN apk update && apk add --no-cache \
    build-base \
    zip \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    vim \
    unzip \
    git \
    supervisor \
    nano \
    npm \
    netcat-openbsd \
    procps \
    iputils \
    bind-tools

# Clear cache
RUN rm -rf /var/cache/apk/*

USER www:www
