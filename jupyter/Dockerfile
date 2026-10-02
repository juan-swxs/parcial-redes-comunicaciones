FROM jupyter/minimal-notebook:latest

# Librerías para conectarse a PostgreSQL y analizar/graficar datos.
# Versiones fijadas para que el build sea reproducible en cualquier máquina.
RUN pip install --no-cache-dir \
    psycopg2-binary==2.9.10 \
    "sqlalchemy==2.0.36" \
    "pandas==2.2.3" \
    "matplotlib==3.9.2" \
    "jinja2>=3.1"
