-- =========================================================
-- Esquema "monitoring": expone los logs de acceso de Nginx y de
-- Joomla (Apache) como tablas SQL, sin copiar datos.
--
-- Los archivos CSV viven en volúmenes Docker compartidos y se montan
-- en modo sólo lectura en este contenedor:
--   nginx_logs  -> /logs/nginx/access.csv
--   joomla_logs -> /logs/joomla/joomla_access.csv
-- file_fdw los lee en cada consulta, así Grafana y Jupyter ven el
-- tráfico en tiempo (casi) real.
--
-- Se ejecuta automáticamente sólo la primera vez que se inicializa el
-- volumen de datos (docker-entrypoint-initdb.d).
-- =========================================================

CREATE EXTENSION IF NOT EXISTS file_fdw;
CREATE SERVER IF NOT EXISTS logs_fs FOREIGN DATA WRAPPER file_fdw;

CREATE SCHEMA IF NOT EXISTS monitoring;

-- ---------------------------------------------------------
-- Tablas "crudas" (todo texto: una línea rara no rompe el parseo)
-- ---------------------------------------------------------
CREATE FOREIGN TABLE IF NOT EXISTS monitoring.nginx_access_raw (
    epoch          text,
    ip             text,
    servicio       text,
    metodo         text,
    uri            text,
    protocolo      text,
    status         text,
    bytes          text,
    request_time   text,
    upstream_time  text,
    upstream       text,
    referer        text,
    user_agent     text
) SERVER logs_fs
  OPTIONS (filename '/logs/nginx/access.csv', format 'csv', quote '"', escape '\');

CREATE FOREIGN TABLE IF NOT EXISTS monitoring.joomla_access_raw (
    epoch_ms     text,
    ip           text,
    ip_proxy     text,
    peticion     text,
    status       text,
    bytes        text,
    duracion_us  text,
    referer      text,
    user_agent   text
) SERVER logs_fs
  OPTIONS (filename '/logs/joomla/joomla_access.csv', format 'csv', quote '"', escape '\');

-- ---------------------------------------------------------
-- Vistas tipadas listas para Grafana / Jupyter
-- ---------------------------------------------------------
CREATE OR REPLACE VIEW monitoring.nginx_access AS
SELECT
    to_timestamp(CASE WHEN epoch ~ '^[0-9]+(\.[0-9]+)?$' THEN epoch::double precision END)                          AS ts,
    ip,
    servicio,
    metodo,
    uri,
    split_part(uri, '?', 1)                                        AS ruta,
    protocolo,
    CASE WHEN status ~ '^[0-9]{3}$' THEN status::int END  AS status,
    left(status, 1) || 'xx'                                        AS clase,
    CASE WHEN bytes ~ '^[0-9]+$' THEN bytes::bigint END  AS bytes,
    CASE WHEN request_time ~ '^[0-9.]+$' THEN request_time::numeric END  AS request_time,
    substring(upstream_time FROM '^[0-9.]+')::numeric              AS upstream_time,
    NULLIF(upstream, '')                                           AS upstream,
    NULLIF(referer, '')                                            AS referer,
    user_agent,
    CASE
        WHEN user_agent ~* 'bot|crawl|spider'               THEN 'Bot'
        WHEN user_agent ~* 'curl|wget|python|go-http|httpx' THEN 'Script / CLI'
        WHEN user_agent ~* 'edg/'                           THEN 'Edge'
        WHEN user_agent ~* 'firefox'                        THEN 'Firefox'
        WHEN user_agent ~* 'chrome|chromium'                THEN 'Chrome'
        WHEN user_agent ~* 'safari'                         THEN 'Safari'
        ELSE 'Otro'
    END  AS cliente
FROM monitoring.nginx_access_raw
WHERE epoch ~ '^[0-9]+(\.[0-9]+)?$' AND status ~ '^[0-9]{3}$';

CREATE OR REPLACE VIEW monitoring.joomla_access AS
SELECT
    to_timestamp(CASE WHEN epoch_ms ~ '^[0-9]+$' THEN epoch_ms::bigint / 1000.0 END)                        AS ts,
    ip,
    ip_proxy,
    split_part(peticion, ' ', 1)                                   AS metodo,
    split_part(split_part(peticion, ' ', 2), '?', 1)               AS ruta,
    peticion,
    CASE WHEN status ~ '^[0-9]{3}$' THEN status::int END  AS status,
    left(status, 1) || 'xx'                                        AS clase,
    CASE WHEN bytes ~ '^[0-9]+$' THEN bytes::bigint END  AS bytes,
    CASE WHEN duracion_us ~ '^[0-9]+$' THEN duracion_us::bigint / 1000.0 END  AS duracion_ms,
    NULLIF(referer, '-')                                           AS referer,
    user_agent
FROM monitoring.joomla_access_raw
WHERE epoch_ms ~ '^[0-9]+$' AND status ~ '^[0-9]{3}$';

COMMENT ON VIEW monitoring.nginx_access  IS 'Log de acceso del proxy Nginx (todas las peticiones HTTP que entran por el puerto 80)';
COMMENT ON VIEW monitoring.joomla_access IS 'Log de acceso de Apache dentro del contenedor Joomla (peticiones que llegan al CMS)';
