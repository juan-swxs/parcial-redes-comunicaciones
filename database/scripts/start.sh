#!/bin/bash
# =========================================================
# Envoltorio del entrypoint oficial de PostgreSQL.
#
# PostgreSQL sólo ejecuta /docker-entrypoint-initdb.d/*.sql cuando el
# volumen de datos está vacío (primera inicialización). Si el volumen ya
# existía (p. ej. creado por una versión anterior del proyecto), el
# esquema "monitoring" nunca se crearía. Por eso, en cada arranque, este
# script espera a que el servidor definitivo acepte conexiones TCP y vuelve
# a aplicar esos .sql, que son idempotentes (IF NOT EXISTS / OR REPLACE).
#
# Se espera por TCP (127.0.0.1) a propósito: durante la primera
# inicialización el entrypoint levanta un servidor temporal que sólo
# escucha por socket Unix, así que no hay carrera con initdb.
# =========================================================
(
    export PGPASSWORD="$POSTGRES_PASSWORD"
    export PGOPTIONS="-c client_min_messages=warning"   # sin avisos "already exists"
    until pg_isready -q -h 127.0.0.1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"; do
        sleep 2
    done
    for f in /docker-entrypoint-initdb.d/*.sql; do
        [ -f "$f" ] || continue
        if psql -q -v ON_ERROR_STOP=1 -h 127.0.0.1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f "$f" >/dev/null; then
            echo "[start.sh] Esquema verificado: $(basename "$f")"
        else
            echo "[start.sh] ERROR aplicando $(basename "$f")" >&2
        fi
    done
) &

exec docker-entrypoint.sh "$@"
