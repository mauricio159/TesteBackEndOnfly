#!/bin/sh
# -----------------------------------------------------------------------------
# Entrypoint do container PHP.
#
# Apenas o container com CONTAINER_ROLE=app prepara a aplicação (dependências,
# .env, chave e migrations). O worker de fila reaproveita a mesma imagem, mas
# só sobe depois que o "app" estiver saudável (healthcheck no compose), então
# não há corrida entre os dois rodando migrations ao mesmo tempo.
# -----------------------------------------------------------------------------
set -e

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    rm -f /tmp/app-ready

    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] Instalando dependencias do Composer..."
        composer install --no-interaction --prefer-dist
    fi

    if [ ! -f .env ]; then
        echo "[entrypoint] Criando .env a partir de .env.example..."
        cp .env.example .env
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] Gerando APP_KEY..."
        php artisan key:generate --force
    fi

    echo "[entrypoint] Executando migrations..."
    php artisan migrate --force

    # Os workers do PHP-FPM rodam como www-data e precisam escrever logs,
    # cache de views etc. No bind mount os arquivos chegam como root.
    chown -R www-data:www-data storage bootstrap/cache

    # Sinaliza ao healthcheck do docker compose que a aplicação está pronta.
    touch /tmp/app-ready
fi

exec "$@"
