# Imagem de produção da API: PHP-FPM + Nginx + Supervisord no mesmo
# container. Um único container simplifica o compose (menos serviços
# para orquestrar) sem abrir mão de nginx na frente do PHP.
#
# Versão do PHP alinhada com o composer.lock: quem gerou o lock local
# tinha PHP 8.4 instalado, então pacotes como symfony/clock e
# nesbot/carbon ficaram travados em versões que exigem PHP >= 8.4.1.
# Trocar a imagem (em vez de mexer no lock) evita reintroduzir
# divergência entre o que foi testado localmente e o que roda em produção.
FROM php:8.4-fpm-alpine

# icu-dev + ext-intl: o Laravel 12 usa a classe Number (que depende de
# intl) em vários pontos de formatação — sem ela, comandos como
# "artisan db:show" abortam mesmo com a conexão funcionando.
RUN apk add --no-cache nginx supervisor icu-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_mysql intl

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copia primeiro só os manifests para cachear a camada do composer install
# entre builds (só reinstala dependências quando composer.json muda).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
