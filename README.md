# Parcial 2 — Despliegue Multi-contenedor, Orquestación y Análisis OSI

Comunicaciones · Ingeniería Mecatrónica · Universidad Militar Nueva Granada

Stack de 5 servicios orquestados con Docker Compose: **Nginx** (reverse proxy) →
**Joomla** (CMS) + **PostgreSQL** (persistencia) + **Jupyter** (análisis de datos) +
**Grafana** (dashboards), con aprovisionamiento 100% automático.

## Requisitos

- Docker Engine 24+ (con el plugin `docker compose`, no el binario viejo `docker-compose`)
- En Linux, verifica con:

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
git clone <URL_DEL_REPOSITORIO>
cd parcial-redes-comunicaciones
cp .env.example .env
docker compose up -d
```

Eso es todo. Nadie necesita entrar a la UI de Grafana ni subir el notebook a mano:
todo queda provisionado por los volúmenes y archivos de configuración del repositorio.

### Ver el estado de los contenedores

```bash
docker compose ps
docker compose logs -f          # todos los servicios
docker compose logs -f joomla   # uno en particular
```

Espera ~30-60s a que Joomla termine su instalación desatendida contra PostgreSQL
(el healthcheck de `database` con `pg_isready` evita que Joomla arranque antes de tiempo).

## Accesos (todo a través del puerto 80 de Nginx)

| Servicio | URL | Credenciales |
|---|---|---|
| Joomla (portal) | http://localhost/ | admin / AdminParcial123! |
| Jupyter Lab | http://localhost/jupyter/ | token: `parcial123` (ver `.env`) |
| Grafana | http://localhost/grafana/ | admin / admin123 (ver `.env`) |

## Apagar y limpiar

```bash
docker compose down          # detiene y elimina contenedores, conserva volúmenes
docker compose down -v       # además borra los volúmenes (BD, sitio Joomla) — reinicia todo desde cero
```

## Estructura del repositorio

```
parcial-redes-comunicaciones/
├── docker-compose.yml
├── .env.example
├── README.md
├── INFORME.md
├── nginx/
│   └── default.conf
├── jupyter/
│   ├── Dockerfile
│   └── notebooks/
│       └── analisis_datos.ipynb
└── grafana/
    └── provisioning/
        ├── datasources/datasource.yml
        └── dashboards/
            ├── dashboard.yml
            └── joomla_logs.json
```

## Notas técnicas rápidas

- **Joomla** se instala de forma desatendida usando las variables `JOOMLA_ADMIN_*` y
  `JOOMLA_DB_*` que reconoce la imagen oficial — no hay que pasar por el asistente web.
- **Grafana** consulta directamente las vistas internas de PostgreSQL
  (`pg_stat_activity`, `pg_stat_user_tables`), así que las gráficas de actividad
  funcionan sin depender del prefijo de tablas aleatorio que genera Joomla.
- Ver `INFORME.md` para el análisis completo del modelo OSI de esta arquitectura.
