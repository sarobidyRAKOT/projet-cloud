# Étape 1 : Choisir une image PHP avec Apache
FROM php:8.3-apache

# Étape 2 : Installer les extensions nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql

# Étape 3 : Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Étape 4 : Copier les fichiers du projet
WORKDIR /var/www/html
COPY . .

# Étape 5 : Installer les dépendances Symfony
RUN composer install

# Étape 6 : Configurer les permissions
RUN chown -R www-data:www-data /var/www/html/var /var/www/html/public

# Étape 7 : Activer le module Apache rewrite
RUN a2enmod rewrite

# Étape 8 : Exposer le port 80
EXPOSE 80

# Commande par défaut
# CMD ["apache2-foreground"]
