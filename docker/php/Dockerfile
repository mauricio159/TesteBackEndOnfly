# -----------------------------------------------------------------------------
# Imagem PHP do microsserviço de pedidos de viagem.
#
# - php-fpm: processo que executa o PHP; o Nginx (outro container) repassa as
#   requisições HTTP para ele via FastCGI na porta 9000.
# - alpine: imagem base mínima (menor superfície de ataque e download rápido).
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine

# Instalador oficial da comunidade que resolve dependências de sistema de cada
# extensão automaticamente (evita apk add/del manuais e frágeis).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# pdo_mysql -> conexão com MySQL
# bcmath/intl -> usados por bibliotecas comuns (números/locale)
# zip -> composer extrai pacotes mais rápido
# pcntl -> permite ao queue:work encerrar jobs de forma graciosa (SIGTERM)
# opcache -> cache de bytecode (performance)
RUN install-php-extensions pdo_mysql bcmath intl zip pcntl opcache

# Composer copiado da imagem oficial (multi-stage), sem instalar nada extra.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-pedidos-viagem.ini

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
