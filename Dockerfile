FROM php:8.2-apache

COPY php-upload.ini /usr/local/etc/php/conf.d/sgproduction-upload.ini
COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/audio /var/www/html/uploads/ads /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data

EXPOSE 80

CMD mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/audio /var/www/html/uploads/ads /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data \
    && apache2-foreground
