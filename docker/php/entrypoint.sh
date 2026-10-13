#!/bin/sh
#
# Preparação da aplicação antes de aceitar requisições.
#
# Faz três coisas que o avaliador não deveria precisar fazer à mão: garante
# a chave da aplicação, espera o banco aceitar conexões e aplica as
# migrations. O objetivo é que `docker compose up` baste.
set -e

cd /var/www/html

# ---------------------------------------------------------------------------
# Chave da aplicação
# ---------------------------------------------------------------------------
# A chave cifra o cookie de sessão. Ela não pode estar no repositório — o
# requisito proíbe versionar segredo — e também não pode ser sorteada a cada
# inicialização, senão toda sessão aberta cairia a cada reinício.
#
# A saída é gerá-la na primeira subida e guardá-la no volume de storage, que
# persiste entre reinícios e recriações do container.
KEY_FILE=storage/app/app.key

if [ -z "${APP_KEY}" ]; then
    if [ ! -f "$KEY_FILE" ]; then
        php artisan key:generate --show > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
        echo "Chave da aplicação gerada e guardada em $KEY_FILE"
    fi

    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# ---------------------------------------------------------------------------
# Banco de dados
# ---------------------------------------------------------------------------
# O healthcheck do compose já segura a ordem de inicialização, mas o
# `pg_isready` do PostgreSQL responde antes de o banco estar de fato pronto
# para consultas. Esta espera cobre essa janela.
echo "Aguardando o banco de dados em ${DB_HOST}:${DB_PORT}…"

tentativa=0
until php -r "
    try {
        new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        exit(0);
    } catch (Throwable \$e) {
        exit(1);
    }
" 2>/dev/null; do
    tentativa=$((tentativa + 1))

    if [ "$tentativa" -ge 30 ]; then
        echo "Banco de dados não respondeu após 30 tentativas. Abortando." >&2
        exit 1
    fi

    sleep 2
done

echo "Banco de dados disponível."

# ---------------------------------------------------------------------------
# Migrations
# ---------------------------------------------------------------------------
# `--force` porque não há terminal interativo aqui para confirmar.
#
# Deliberadamente `migrate`, nunca `migrate:fresh`: um comando destrutivo
# num entrypoint apagaria os dados a cada subida do container.
php artisan migrate --force --no-interaction

# Semeia apenas quando pedido, e apenas se ainda não houver clientes. Assim
# `docker compose up` repetido não multiplica os dados de demonstração.
if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    CLIENTES=$(php artisan tinker --execute='echo \App\Models\Customer::count();' 2>/dev/null | tr -dc '0-9')

    if [ "${CLIENTES:-0}" = "0" ]; then
        echo "Semeando dados de demonstração…"
        php artisan db:seed --force --no-interaction
    else
        echo "Dados já existentes (${CLIENTES} clientes); seeders não executados."
    fi
fi

# Caches de configuração e rotas: evitam ler e interpretar os arquivos a
# cada requisição. Gerados aqui, e não na imagem, porque dependem das
# variáveis de ambiente que só existem em tempo de execução.
php artisan config:cache
php artisan route:cache

echo "Aplicação pronta."

exec "$@"
