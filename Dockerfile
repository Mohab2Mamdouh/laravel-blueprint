# syntax=docker/dockerfile:1

# PHP CLI image for developing/testing the laravel-blueprint package.
# The package requires PHP ^8.3 (see composer.json).
FROM php:8.3-cli-alpine

# install-php-extensions: reliable helper for adding PHP extensions on Alpine.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/bin/

# Extensions needed for Laravel + the DB-backed parts of this package
# (pdo_mysql for the repository/seeder/settings features, bcmath for Laravel).
RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    intl \
    zip \
    pcntl

# Composer (pinned major) from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git + unzip are used by Composer when resolving/downloading packages.
RUN apk add --no-cache git unzip

# Run as a non-root user whose UID matches the typical host user, so files
# created inside the container (e.g. vendor/) stay writable on the host.
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app && adduser -u ${UID} -G app -D app

WORKDIR /app
USER app

CMD ["php", "-a"]
