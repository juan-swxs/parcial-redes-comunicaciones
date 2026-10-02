# Parcial 2 — Despliegue Multi-contenedor, Orquestación y Análisis OSI

Comunicaciones · Ingeniería Mecatrónica · Universidad Militar Nueva Granada

Stack de 5 servicios orquestados con Docker Compose: **Nginx** (reverse proxy) →
**Joomla** (CMS) + **PostgreSQL** (persistencia) + **Jupyter** (análisis de datos) +
**Grafana** (dashboards), con aprovisionamiento 100 % automático.

## Requisitos

- Docker Engine 24+ con el plugin `docker compose` (no el binario viejo `docker-compose`).
- Puerto **80** libre en el host.

```bash
docker --version
docker compose version
```

Si no los tienes, en Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
sudo usermod -aG docker $USER   # cierra sesión y vuelve a entrar para que aplique
```

## Arranque (zero-touch)

```bash
git clone https://github.com/juan-swxs/parcial-redes-comunicaciones.git
cd parcial-redes-comunicaciones
docker compose up -d
```

Eso es todo. **No hace falta crear ni copiar el archivo `.env`**: `docker-compose.yml`
trae como valor por defecto cada credencial (`${VARIABLE:-valor}`), con los mismos valores
de `.env.example`. Tampoco hay que crear datasources o dashboards en Grafana ni subir el
notebook: todo queda provisionado por los archivos del repositorio.

> ¿Quieres otras credenciales? Copia `.env.example` a `.env`, edítalo y ejecuta
> `docker compose up -d`. Los valores de `.env` tienen prioridad sobre los de por defecto.
> El flujo del enunciado (`cp .env.example .env` y luego `docker compose up -d`) también
> funciona igual.

El primer arranque descarga las imágenes y construye la de Jupyter (2–4 min). Joomla
necesita ~1 min más para instalarse contra PostgreSQL. Comprueba que los 5 servicios
estén `healthy`:

```bash
docker compose ps
docker compose logs -f nginx    # access log legible en vivo
```

### En Windows o macOS

Funciona igual con **Docker Desktop** (en Windows, con el backend WSL 2 activado):

```powershell
git clone https://github.com/juan-swxs/parcial-redes-comunicaciones.git
cd parcial-redes-comunicaciones
docker compose up -d
```

- El repositorio incluye `.gitattributes`, que obliga a usar finales de línea **LF** aunque
  Git para Windows convierta a CRLF por defecto. Sin él, `joomla/seed/start.sh` fallaría
  dentro del contenedor Linux.
- Si el puerto 80 está ocupado (IIS, *World Wide Web Publishing Service*, otro servidor
  web), libéralo o detén ese servicio antes de `docker compose up -d`.

## Accesos (todo a través del puerto 80 de Nginx)

| Servicio | URL | Credenciales |
|---|---|---|
| Joomla (portal) | <http://localhost/> | admin del sitio: `admin` / `AdminParcial123!` (<http://localhost/administrator/>) |
| Grafana | <http://localhost/grafana/> | lectura anónima; para editar: `admin` / `admin123` |
| Jupyter Lab | <http://localhost/jupyter/> | token: `parcial123` |

Todas las credenciales están en `.env.example` y son los valores por defecto de `docker-compose.yml`.

## Qué hay en cada servicio

**Grafana** (carpeta *Parcial COMM*, se refresca cada 10 s):

- *Tráfico HTTP y logs — Nginx / Joomla* (página de inicio): peticiones, peticiones/min,
  tasa de error, latencia p95, IPs únicas y bytes; peticiones por código HTTP; latencia
  p50/p95/máx.; tráfico por servicio; top IPs; rutas más solicitadas; user-agents; log de
  Apache de Joomla; errores recientes y un visor de logs en vivo.
- *PostgreSQL — Actividad de la base de datos*: conexiones, tamaño, cache hit, escrituras
  por tabla y sesiones activas.

**Joomla** arranca con la portada ya poblada: banner, 7 artículos del proyecto con
imágenes, accesos rápidos y menú hacia Grafana/Jupyter. Lo crea `joomla/seed/seed.php`
la primera vez, sin pasos manuales.

**Jupyter** abre directamente `analisis_datos.ipynb`. Con *Run → Run All Cells* se ejecutan
sus secciones, con gráficas interactivas (Plotly) y tablas con búsqueda y orden (itables):
conexión, topología real de la red (DNS/ARP), generación de tráfico, análisis del log de
Nginx con un explorador de logs filtrable, log de Apache, actividad de PostgreSQL y resumen.

### ¿Cómo llegan los logs a Grafana?

```
nginx  ──► access.csv         (volumen nginx_logs)  ─┐
joomla ──► joomla_access.csv  (volumen joomla_logs) ─┴─► montados :ro en PostgreSQL
                                                          file_fdw → esquema "monitoring"
                                                          ──► Grafana y Jupyter (SQL)
```

Los detalles están en `INFORME.md`, sección 1.2.

## Apagar y limpiar

```bash
docker compose down          # detiene y elimina contenedores, conserva volúmenes
docker compose down -v       # además borra los volúmenes (BD, sitio Joomla, logs) — reinicia desde cero
```

> El esquema `monitoring` se crea al **inicializar** el volumen de PostgreSQL. Si
> levantaste una versión anterior del proyecto, usa `docker compose down -v` antes de
> `docker compose up -d`.

## Estructura del repositorio

```
parcial-redes-comunicaciones/
├── docker-compose.yml              # orquestación de los 5 servicios, redes y volúmenes
├── .env.example                    # credenciales por defecto (opcional: cp .env.example .env)
├── README.md
├── INFORME.md                      # documento técnico: topología + análisis OSI + verificación
├── nginx/
│   └── default.conf                # proxy inverso, WebSockets, log CSV
├── joomla/
│   ├── apache-logs.conf            # log CSV de Apache + mod_remoteip
│   └── seed/                       # contenido inicial de la portada (start.sh + seed.php)
├── database/
│   └── init/01-monitoring.sql      # file_fdw + vistas monitoring.nginx_access / joomla_access
├── jupyter/
│   ├── Dockerfile                  # minimal-notebook + psycopg2, SQLAlchemy, pandas, Plotly, itables
│   └── notebooks/
│       └── analisis_datos.ipynb
└── grafana/
    └── provisioning/
        ├── datasources/datasource.yml
        └── dashboards/
            ├── dashboard.yml
            ├── joomla_logs.json
            └── postgres_actividad.json
```
