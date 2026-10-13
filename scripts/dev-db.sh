#!/usr/bin/env bash
#
# Cluster PostgreSQL de desenvolvimento, próprio do usuário.
#
# Por que existe: o PostgreSQL do sistema (porta 5432) não tem credencial
# disponível para este projeto, e o daemon do Docker não estava acessível
# quando o projeto foi iniciado. Os binários do PostgreSQL rodam sem
# privilégio, então este script sobe um cluster isolado, numa porta própria,
# sem sudo e sem tocar no cluster de sistema.
#
# A partir da Etapa 4 o ambiente de referência passa a ser o Docker Compose.
# Este script continua válido para desenvolvimento sem containers.
#
# Uso:  ./scripts/dev-db.sh {start|stop|status|psql|reset}

set -euo pipefail

PGBIN=/usr/lib/postgresql/16/bin
PGROOT="${NOGLE_PGROOT:-$HOME/.local/share/nogle-crud-pg}"
PGDATA="$PGROOT/data"
PGPORT="${NOGLE_PGPORT:-55432}"
PGUSER_APP=nogle
PGPASS_APP="${NOGLE_PGPASSWORD:-nogle_dev_pw}"
DB_DEV=nogle_crud
DB_TEST=nogle_crud_test

[ -x "$PGBIN/pg_ctl" ] || { echo "PostgreSQL 16 não encontrado em $PGBIN" >&2; exit 1; }

init_cluster() {
    [ -s "$PGDATA/PG_VERSION" ] && return 0

    echo "Criando cluster em $PGDATA"
    mkdir -p "$PGROOT"

    local pwfile
    pwfile="$(mktemp)"
    chmod 600 "$pwfile"
    printf '%s' "$PGPASS_APP" > "$pwfile"
    # O arquivo de senha é removido mesmo se o initdb falhar.
    trap 'rm -f "$pwfile"' RETURN

    "$PGBIN/initdb" -D "$PGDATA" -U "$PGUSER_APP" \
        --auth-local=trust --auth-host=md5 --pwfile="$pwfile" \
        -E UTF8 --locale=C.UTF-8 > "$PGROOT/initdb.log" 2>&1
}

start() {
    init_cluster

    if "$PGBIN/pg_isready" -h 127.0.0.1 -p "$PGPORT" -U "$PGUSER_APP" >/dev/null 2>&1; then
        echo "Já em execução na porta $PGPORT"
    else
        "$PGBIN/pg_ctl" -D "$PGDATA" -l "$PGROOT/server.log" \
            -o "-p $PGPORT -k $PGROOT -c listen_addresses=127.0.0.1" start >/dev/null
        echo "Cluster iniciado na porta $PGPORT"
    fi

    create_databases
}

create_databases() {
    export PGPASSWORD="$PGPASS_APP"
    for db in "$DB_DEV" "$DB_TEST"; do
        local existe
        existe=$(psql -h 127.0.0.1 -p "$PGPORT" -U "$PGUSER_APP" -d postgres -tAc \
            "select 1 from pg_database where datname='$db'")
        if [ "$existe" != "1" ]; then
            createdb -h 127.0.0.1 -p "$PGPORT" -U "$PGUSER_APP" "$db"
            echo "Banco $db criado"
        fi
    done
    echo "Bancos disponíveis: $DB_DEV (desenvolvimento), $DB_TEST (testes)"
}

case "${1:-start}" in
    start)  start ;;
    stop)   "$PGBIN/pg_ctl" -D "$PGDATA" stop >/dev/null && echo "Cluster parado" ;;
    status) "$PGBIN/pg_isready" -h 127.0.0.1 -p "$PGPORT" -U "$PGUSER_APP" ;;
    psql)   PGPASSWORD="$PGPASS_APP" psql -h 127.0.0.1 -p "$PGPORT" -U "$PGUSER_APP" -d "${2:-$DB_DEV}" ;;
    reset)
        # Destrutivo: exige confirmação explícita, porque apaga os dados de
        # desenvolvimento.
        read -r -p "Apagar e recriar '$DB_DEV' e '$DB_TEST'? Digite 'sim': " ok
        [ "$ok" = "sim" ] || { echo "Cancelado"; exit 1; }
        export PGPASSWORD="$PGPASS_APP"
        for db in "$DB_DEV" "$DB_TEST"; do
            dropdb -h 127.0.0.1 -p "$PGPORT" -U "$PGUSER_APP" --if-exists "$db"
        done
        create_databases
        ;;
    *) echo "Uso: $0 {start|stop|status|psql [banco]|reset}" >&2; exit 1 ;;
esac
