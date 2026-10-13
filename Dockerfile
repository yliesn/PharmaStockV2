# /srv/app1/Dockerfile
FROM php:8.2-apache

# Extensions PHP nécessaires pour MySQL/MariaDB
RUN docker-php-ext-install pdo_mysql mysqli

# mod_rewrite : front controller éventuel (/.htaccess)
# mod_headers : en-têtes Cache-Control définis dans src/.htaccess
RUN a2enmod rewrite headers
