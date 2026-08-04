#!/bin/sh
set -e

# Em teoria o "depends_on: condition: service_healthy" no compose já
# garante isso, mas na prática já vimos o migrate falhar com "Connection
# refused" mesmo com o mysql marcado como healthy (a checagem do
# healthcheck roda por dentro do container mysql via socket local; a
# conexão de outro container pela rede docker pode ainda não estar
# pronta no exato instante em que o healthcheck reporta OK). Este loop
# é a rede de segurança: tenta abrir uma conexão real via Laravel/PDO
# antes de seguir.
echo "Aguardando o banco de dados aceitar conexões..."
tries=0
until php artisan db:show > /dev/null 2>&1; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    echo "Banco de dados não respondeu após 60s, abortando."
    exit 1
  fi
  sleep 2
done

echo "Rodando migrations..."
php artisan migrate --force

echo "Limpando e reconstruindo caches de config/rotas..."
php artisan config:cache
php artisan route:cache

# "storage" é um volume nomeado (sobrevive entre rebuilds) e os comandos
# acima rodam como root. Sem isso, os workers do PHP-FPM (que rodam como
# www-data, não root) não conseguem escrever em storage/logs nem em
# storage/framework/{cache,sessions} em runtime — qualquer erro depois
# do boot vira um 500 sem NENHUM log gravado, porque o próprio Monolog
# falha silenciosamente ao tentar abrir o arquivo de log para escrita.
chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisord.conf
