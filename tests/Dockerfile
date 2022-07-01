FROM php:8.1.7-cli
RUN apt-get update && apt-get install --yes --no-install-recommends libzip-dev libbz2-dev liblzma-dev p7zip-full git
RUN docker-php-source extract \
    && git clone https://github.com/cataphract/php-rar.git \
    && cd php-rar && phpize && ./configure && make && make install && cd ../ && rm -rf php-rar \
    && git clone https://github.com/codemasher/php-ext-xz.git && cd php-ext-xz && phpize && ./configure \
    && make && make install && cd ../ && rm -rf php-ext-xz \
    && docker-php-source delete
RUN docker-php-ext-install bz2 zip
RUN docker-php-ext-enable rar xz
