FROM php:8.3-apache

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Instalador de extensiones
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Módulos de Apache obligatorios
RUN a2enmod rewrite ssl

# Instalación: Dependencias sistema + Extensiones PHP
RUN chmod +x /usr/local/bin/install-php-extensions && \
    apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    imagemagick \
    && \
    install-php-extensions \
    xdebug \
    mbstring \
    gd \
    zip \
    intl \
    exif \
    imagick

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Ajuste de usuario
# RUN usermod -u 1000 www-data

# OJO, que exista .podman/vhost.conf
COPY .podman/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN sed -i 's/Listen 443/Listen 8443/' /etc/apache2/ports.conf
