FROM php:8.2-apache

# Installer les extensions requises : pdo, pdo_sqlite, pdo_pgsql, zip
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    sqlite3 \
    libsqlite3-dev \
    zip \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Copier le code du projet dans le dossier web Apache
WORKDIR /var/www/html
COPY . /var/www/html/

# Donner les droits d'écriture pour SQLite, sessions et uploads
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/database \
    && chmod -R 775 /var/www/html/uploads

# Port web standard
EXPOSE 80

CMD ["apache2-foreground"]