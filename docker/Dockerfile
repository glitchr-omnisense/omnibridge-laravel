# PHP 8.4 with Composer 2, intl (ICU messages), bcmath (omnibase's functions ask for it), imagick (omnibase's images, the
# media tests) and SQLite: the image of the test suite.
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libmagickwand-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip exif ftp bcmath \
    && pecl install imagick && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/* \
    && git config --system --add safe.directory '*'
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 COMPOSER_HOME=/tmp/composer
WORKDIR /package
