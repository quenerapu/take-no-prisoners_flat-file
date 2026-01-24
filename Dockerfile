# Usamos PHP 8.2 con Apache
FROM php:8.2-apache

# Activamos el módulo rewrite de Apache para gestionar las URLs amigables del .htaccess
RUN a2enmod rewrite

# Instalamos dependencias para el procesamiento de texto (necesario para mbstring e intl)
RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install intl

# Copiamos el código fuente al contenedor
COPY . /var/www/html/

# Ajustamos permisos para que el servidor pueda generar el índice JSON y el sitemap
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
