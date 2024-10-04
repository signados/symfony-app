# Etapa para instalar las dependencias definidas en Composer.
FROM composer:lts as deps

WORKDIR /app

# Copiar composer.json y composer.lock para aprovechar la cache de Docker.
COPY composer.json composer.lock ./

# Descargar dependencias sin ejecutar los scripts automáticos de Composer.
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-scripts --no-interaction

################################################################################

# Etapa final para ejecutar la aplicación con las dependencias mínimas necesarias.
FROM php:8.3-apache as final

# Instalar cualquier extensión adicional que requiera tu aplicación PHP.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    && docker-php-ext-install zip

# Usar la configuración de producción de PHP.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN a2enmod rewrite

# Configuración de Apache
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copiar las dependencias de la etapa anterior.
COPY --from=deps /app/vendor /var/www/html/vendor

# Copiar todo el resto de los archivos de la aplicación, incluyendo la carpeta bin.
COPY . /var/www/html

# Asignar permisos adecuados.
RUN chown -R www-data:www-data /var/www/html

# Asegurar permisos correctos para el archivo bin/console.
RUN chmod +x /var/www/html/bin/console

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Asegurarse de que la carpeta var/cache y var/log tienen los permisos correctos.
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var

# Cambiar al usuario no privilegiado para ejecutar la aplicación.
USER www-data

# Exponer el puerto 80 para Apache.
EXPOSE 80

# Comando para ejecutar Symfony y Apache.
CMD ["apache2-foreground"]
