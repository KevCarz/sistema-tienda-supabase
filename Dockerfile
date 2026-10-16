FROM php:8.2-apache

# Instalar extensiones necesarias (PostgreSQL y otras)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar tu código al servidor
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html

# Puerto que usa Render
EXPOSE 80