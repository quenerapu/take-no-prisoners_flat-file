# 1. Usamos la imagen oficial de PHP 8.2 con Apache
FROM php:8.2-apache

# 2. Activamos el módulo rewrite de Apache (esencial para los .htaccess y URLs amigables)
RUN a2enmod rewrite

# 3. Instalamos dependencias del sistema y extensiones de PHP necesarias
# - libicu-dev e intl: para el manejo correcto de caracteres internacionales y fechas
RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*

# 4. Establecemos el directorio de trabajo
WORKDIR /var/www/html

# 5. Copiamos los archivos del proyecto al contenedor
# Nota: Si usas volúmenes en docker-compose, estos archivos se verán 
# sobrescritos por tu carpeta local en desarrollo.
COPY . /var/www/html/

# 6. GESTIÓN DE PERMISOS (Crucial para el Indexer y Sitemap)
# Creamos la carpeta de contenido por si no existe y ajustamos el propietario
# al usuario 'www-data' (el que usa Apache por defecto).
RUN mkdir -p /var/www/html/content && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# 7. Exponemos el puerto 80
EXPOSE 80

# 8. Iniciamos Apache en el primer plano
CMD ["apache2-foreground"]
