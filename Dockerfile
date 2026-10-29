FROM php:8.3-apache

# Driver de PostgreSQL para PDO
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Render asigna el puerto en la variable PORT (por defecto 10000)
ENV PORT=10000
EXPOSE 10000

CMD sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
