FROM php:8.1.7-cli
RUN apt-get update && apt-get install --yes --no-install-recommends libzip-dev libbz2-dev p7zip-full git
RUN docker-php-source extract \
    && git clone https://github.com/cataphract/php-rar.git \
    && cd php-rar && phpize && ./configure && make && make install && cd ../ && rm -rf php-rar \
    && docker-php-source delete
RUN docker-php-ext-install bz2 zip
RUN docker-php-ext-enable rar
