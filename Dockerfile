FROM php:8.2-apache

# Install PHP dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    unzip \
    && docker-php-ext-install mysqli pdo_mysql pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application directory
WORKDIR /var/www/html

# Install Google API PHP Client
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Copy application files
COPY . /var/www/html/

# Configure Apache
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    DirectoryIndex login.php index.php index.html' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    >> /etc/apache2/apache2.conf \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
