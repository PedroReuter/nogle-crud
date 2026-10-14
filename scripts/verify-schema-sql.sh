#!/usr/bin/env bash
#
# Confere que database/schema.sql produz exatamente o mesmo esquema que as
# migrations do Laravel.
#
# O requisito da Nogle pede o script de criação das tabelas, e o AGENTS.md
# exige que ele reflita o esquema das migrations. Duas fontes para a mesma
# estrutura divergem com o tempo — a menos que a igualdade seja verificada.
# Este script cria dois bancos temporários, aplica um caminho em cada e
# compara os dumps.
#
# Uso:  ./scripts/verify-schema-sql.sh
set -euo pipefail

cd "$(dirname "$0")/.."

PGHOST="${DB_HOST:-127.0.0.1}"
PGPORT="${DB_PORT:-55432}"
PGUSER="${DB_USERNAME:-nogle}"
export PGPASSWORD="${DB_PASSWORD:-nogle_dev_pw}"

A=verify_schema_migrations
B=verify_schema_sql

derrubar_bancos() {
    dropdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" --if-exists "$A" 2>/dev/null || true
    dropdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" --if-exists "$B" 2>/dev/null || true
}

# Remove restos de uma execução anterior ANTES de criar o diretório
# temporário — a limpeza do final apaga o diretório, e chamá-la aqui
# destruiria o que ainda vai ser usado.
derrubar_bancos

TMP="$(mktemp -d)"
trap 'derrubar_bancos; rm -rf "$TMP"' EXIT

createdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" "$A"
createdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" "$B"

echo "Aplicando as migrations…"
(cd backend && DB_DATABASE="$A" DB_HOST="$PGHOST" DB_PORT="$PGPORT" \
    DB_USERNAME="$PGUSER" DB_PASSWORD="$PGPASSWORD" \
    php artisan migrate --force --no-interaction >/dev/null)

echo "Aplicando database/schema.sql…"
psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$B" -v ON_ERROR_STOP=1 -q \
    -f database/schema.sql

extrair() {
    # Ignora comentários, linhas em branco, diretivas de sessão do psql e os
    # tokens \restrict, que mudam a cada execução do pg_dump.
    pg_dump -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$1" \
        --schema-only --no-owner --no-privileges --no-comments \
        | grep -vE '^--|^$|^SET |^SELECT pg_catalog|^\\(restrict|unrestrict)' \
        | sed 's/[[:space:]]*$//'
}

extrair "$A" > "$TMP/migrations.sql"
extrair "$B" > "$TMP/script.sql"

if diff -u "$TMP/migrations.sql" "$TMP/script.sql"; then
    echo
    echo "OK: database/schema.sql reflete exatamente o esquema das migrations."
else
    echo
    echo "FALHA: os esquemas divergem. Atualize database/schema.sql." >&2
    exit 1
fi
