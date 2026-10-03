#!/bin/sh
# =========================================================
# Filtro de logs para file_fdw (opción "program").
# Uso: leer-log.sh <archivo.csv> <número de columnas>
#
# Entrega a PostgreSQL sólo líneas CSV válidas:
#  - quita bytes nulos (0x00): aparecen si el contenedor o el equipo se
#    apagan de golpe mientras se escribe el log (frecuente en Docker Desktop);
#  - quita bytes que no son UTF-8 válido (peticiones con basura binaria);
#  - descarta líneas cortadas o mezcladas: cada línea debe empezar y terminar
#    con comillas y tener exactamente <columnas> campos "...","...".
# Si el archivo todavía no existe, no devuelve nada (en vez de un error).
# =========================================================
archivo="$1"
columnas="$2"
[ -r "$archivo" ] || exit 0

tr -d '\000' < "$archivo" \
  | iconv -c -f UTF-8 -t UTF-8 \
  | awk -v n="$columnas" '{
        l = $0
        gsub(/\\\\/, "", l)            # \\ escapado
        gsub(/\\"/, "", l)             # \" escapado
        if (l ~ /^".*"$/ && gsub(/","/, "", l) == n - 1) print
    }'
exit 0
