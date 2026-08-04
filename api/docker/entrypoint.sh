#!/bin/sh
set -e

# Não precisa esperar o MySQL aqui: o docker-compose.prod.yml só inicia
# este container depois que o "mysql" reporta healthy (depends_on +
# condition: service_healthy), então a conexão já está disponível.

echo "Rodando migrations..."
php artisan migrate --force

echo "Limpando e reconstruindo caches de config/rotas..."
php artisan config:cache
php artisan route:cache

exec supervisord -c /etc/supervisord.conf
