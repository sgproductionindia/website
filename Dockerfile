FROM php:8.2-apache

COPY php-upload.ini /usr/local/etc/php/conf.d/sgproduction-upload.ini
COPY apache-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/audio /var/www/html/uploads/ads /var/www/html/uploads/artists /var/www/html/uploads/site /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data

EXPOSE 80

CMD mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/audio /var/www/html/uploads/ads /var/www/html/uploads/artists /var/www/html/uploads/site /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data \
    && apache2-foreground
