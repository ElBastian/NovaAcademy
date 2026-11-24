#!/bin/sh
# Arranque del contenedor en Render.
# Si Render da DATABASE_URL se usa esa base (permanente).
# Si no, se levanta PostgreSQL dentro del contenedor (los datos se pierden al reiniciar).
set -e

if [ -z "$DATABASE_URL" ]; then
    echo "DATABASE_URL no definida: usando PostgreSQL local del contenedor"
    service postgresql start
    PW=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
    su postgres -c "psql -q -c \"ALTER USER postgres PASSWORD '$PW';\""
    su postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='novaacademy'\"" | grep -q 1 \
        || su postgres -c "createdb novaacademy"
    export DATABASE_URL="postgres://postgres:$PW@127.0.0.1:5432/novaacademy"
    export DB_SSLMODE=disable
fi

# Pasar las variables de conexión a PHP
printf 'PassEnv DATABASE_URL\nPassEnv DB_SSLMODE\n' > /etc/apache2/conf-enabled/db-env.conf
export DB_SSLMODE="${DB_SSLMODE:-prefer}"

# Crear/actualizar tablas (usuarios.sql es idempotente)
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f /var/www/html/usuarios.sql \
    || echo "AVISO: no se pudo aplicar usuarios.sql"

# Apache en el puerto que asigna Render
sed -i "s/Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
