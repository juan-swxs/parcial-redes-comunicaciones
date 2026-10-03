#!/bin/bash
# =========================================================
# Envoltorio del entrypoint oficial de Joomla. La carpeta joomla/ del
# repositorio se monta completa en /opt/parcial (se montan carpetas, no
# archivos sueltos, para evitar que Docker Desktop cree un directorio
# vacío en lugar de un archivo que todavía no existe):
#  1. activa el log CSV de Apache enlazando apache-logs.conf;
#  2. lanza en segundo plano el seeder de contenido (espera a que
#     termine la instalación desatendida y carga la portada);
#  3. cede el control al entrypoint original (instala Joomla y
#     arranca Apache como PID 1).
# =========================================================
if [ -f /opt/parcial/apache-logs.conf ]; then
    ln -sf /opt/parcial/apache-logs.conf /etc/apache2/conf-enabled/zz-parcial-logs.conf
else
    echo "[start.sh] AVISO: no se encontró /opt/parcial/apache-logs.conf; el log CSV de Joomla queda desactivado" >&2
fi
php /opt/parcial/seed/seed.php &
exec /entrypoint.sh "$@"
