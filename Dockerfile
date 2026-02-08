FROM php:8.2-apache

# Activar mod_rewrite para .htaccess (rutas amigables)
RUN a2enmod rewrite

# Instalar dependencias del sistema y extensiones PHP comunes
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip \
    && docker-php-ext-install mysqli pdo_mysql \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

# Copiar tu proyecto al directorio público de Apache
COPY . /var/www/html/

# Permisos (suficiente para la mayoría de apps)
RUN chown -R www-data:www-data /var/www/html

# Asegurar AllowOverride para que Apache lea .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
