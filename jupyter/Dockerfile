FROM jupyter/minimal-notebook:latest

# Librerías para conectarse a PostgreSQL y analizar/graficar datos de forma interactiva.
# Versiones fijadas para que el build sea reproducible en cualquier máquina.
RUN pip install --no-cache-dir \
    psycopg2-binary==2.9.10 \
    "sqlalchemy==2.0.36" \
    "pandas==2.2.3" \
    "jupyterlab==4.2.5" \
    "notebook==7.2.2" \
    "plotly==5.24.1" \
    "itables==2.2.4" \
    "ipywidgets==8.1.5" \
    "jinja2>=3.1"

# Ajustes de JupyterLab:
#  - sin ventana emergente de "Jupyter news";
#  - sin virtualizar celdas, para que las gráficas de Plotly tomen el ancho
#    real del cuaderno aunque se ejecuten fuera de pantalla con "Run All".
RUN mkdir -p /opt/conda/share/jupyter/lab/settings && \
    echo '{"@jupyterlab/apputils-extension:notification": {"fetchNews": "false"}, \
           "@jupyterlab/notebook-extension:tracker": {"windowingMode": "none"}}' \
      > /opt/conda/share/jupyter/lab/settings/overrides.json
