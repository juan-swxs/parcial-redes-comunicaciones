FROM jupyter/minimal-notebook:latest

USER root

# Librerías para conectarse a PostgreSQL y analizar/graficar datos
RUN pip install --no-cache-dir \
    psycopg2-binary \
    sqlalchemy \
    pandas \
    matplotlib \
    seaborn \
    plotly \
    networkx \
    jinja2 \
    "ipywidgets>=8"

USER ${NB_UID}
