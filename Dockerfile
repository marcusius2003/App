FROM php:8.2-apache

# Apache: .htaccess / rewrite
RUN a2enmod rewrite

# Dependencias + extensiones PHP típicas
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install zip mysqli pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

# Composer (opcional pero recomendable si usas composer.json)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Instala dependencias si hay composer.json
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Permisos
RUN chown -R www-data:www-data /var/www/html

# Permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
