FROM php:8.4-cli

WORKDIR /app

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql mysqli \
    && apt-get clean

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos del proyecto
COPY . .

# ELIMINAR vendor y composer.lock para forzar reinstalación limpia
RUN rm -rf vendor composer.lock

# Instalar dependencias con todas las opciones necesarias
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Exponer puerto
EXPOSE 8000

# Comando de inicio
CMD ["php", "-S", "0.0.0.0:8000", "index.php"]