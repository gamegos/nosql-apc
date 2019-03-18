FROM php:7.3

RUN apt-get update && apt-get install -y \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/* \
    && php -r "readfile('https://getcomposer.org/download/1.8.4/composer.phar');" > /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

RUN pecl install apcu-5.1.17 xdebug-2.7.0 \
    && docker-php-ext-enable apcu xdebug \
    && rm -rf /tmp/pear

COPY ci/test/php.ini /usr/local/etc/php/conf.d/

ENV COMPOSER_DISABLE_XDEBUG_WARN 1

COPY ci/composer-install.sh /usr/local/bin/composer-install
RUN chmod +x /usr/local/bin/composer-install

ARG user=docker
ARG uid=1000
RUN adduser --disabled-password --gecos '' --uid $uid $user
USER $user

WORKDIR /app
