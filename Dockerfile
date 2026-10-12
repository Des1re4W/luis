
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    DirectoryIndex login.php index.php index.html' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    >> /etc/apache2/apache2.conf

EXPOSE 80
