ARG PHP_VERSION=7.4-cli
FROM php:${PHP_VERSION}
RUN apt-get update && apt-get install --yes --no-install-recommends libzip-dev libbz2-dev liblzma-dev p7zip-full git wget
RUN pecl install xdebug-3.1.5
RUN docker-php-source extract \
    && git clone https://github.com/cataphract/php-rar.git && cd php-rar && phpize && ./configure \
    && make && make install && cd ../ && rm -rf php-rar \
    && git clone https://github.com/codemasher/php-ext-xz.git && cd php-ext-xz && phpize && ./configure \
    && make && make install && cd ../ && rm -rf php-ext-xz \
    && docker-php-source delete
RUN docker-php-ext-install bz2 zip && docker-php-ext-enable rar xz xdebug

RUN echo "xdebug.mode = debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet --install-dir=/usr/local/bin --filename=composer
