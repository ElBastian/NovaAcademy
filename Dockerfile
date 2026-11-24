FROM php:8.3-apache

# Driver de PostgreSQL para PDO + cliente psql para crear las tablas al arrancar
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev postgresql-client \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Render asigna el puerto en la variable PORT (por defecto 10000)
ENV PORT=10000
EXPOSE 10000

# Al arrancar: aplica usuarios.sql (es idempotente) y levanta Apache en $PORT
CMD if [ -n "$DATABASE_URL" ]; then \
        echo "PassEnv DATABASE_URL" > /etc/apache2/conf-enabled/db-env.conf; \
        touch /tmp/dburl_set; \
        psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f /var/www/html/usuarios.sql \
        || echo "AVISO: no se pudo aplicar usuarios.sql"; \
    fi \
    && sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
