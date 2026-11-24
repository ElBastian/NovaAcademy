FROM php:8.3-apache

# Driver PDO de PostgreSQL, cliente psql y servidor PostgreSQL (respaldo local)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev postgresql postgresql-client \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && mv /var/www/html/start.sh /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Render asigna el puerto en la variable PORT (por defecto 10000)
ENV PORT=10000
EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]
