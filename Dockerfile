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

# Configurer le répertoire de travail
WORKDIR /var/www/html

# Copier tous les fichiers du projet
COPY . /var/www/html/

# Créer les dossiers nécessaires s'ils n'existent pas et assigner les droits
RUN mkdir -p /var/www/html/database /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/database \
    && chmod -R 775 /var/www/html/uploads

# Port d'écoute standard Apache
EXPOSE 80

CMD ["apache2-foreground"]