FROM php:8.0-apache

RUN apt-get update && apt-get upgrade -yy \
    && apt-get install --no-install-recommends apt-utils libjpeg-dev libpng-dev libwebp-dev \
    libzip-dev zlib1g-dev libfreetype6-dev supervisor zip \
    unzip software-properties-common -yy \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN docker-php-ext-install zip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-install exif \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j "$(nproc)" gd \
    && a2enmod rewrite

RUN echo 'memory_limit = 1G' > /usr/local/etc/php/conf.d/memory-limit.ini

WORKDIR /var/www/html
COPY ./app /var/www/html/ 

RUN mkdir -p /var/www/html/wp-content/uploads/simply-static/temp-files
RUN chown -R www-data:www-data /var/www/html/wp-content/uploads
RUN chmod -R 755 /var/www/html/wp-content/uploads
