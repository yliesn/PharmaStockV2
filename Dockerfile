# /srv/app1/Dockerfile
FROM php:8.2-apache

# Extensions PHP nécessaires pour MySQL/MariaDB
RUN docker-php-ext-install pdo_mysql mysqli

# (optionnel) Activer mod_rewrite si tu utilises un front controller (/.htaccess)
RUN a2enmod rewrite
