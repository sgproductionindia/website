FROM php:8.2-apache

COPY php-upload.ini /usr/local/etc/php/conf.d/sgproduction-upload.ini
COPY apache-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html/

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/audio /var/www/html/uploads/ads /var/www/html/uploads/artists /var/www/html/uploads/site /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80

CMD mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/audio /var/www/html/uploads/ads /var/www/html/uploads/artists /var/www/html/uploads/site /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data \
    && apache2-foreground
