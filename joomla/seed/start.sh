#!/bin/bash
# =========================================================
# Envoltorio del entrypoint oficial de Joomla:
#  1. lanza en segundo plano el seeder de contenido (espera a que
#     termine la instalación desatendida y carga la portada);
#  2. cede el control al entrypoint original (instala Joomla y
#     arranca Apache como PID 1).
# =========================================================
php /opt/parcial/seed.php &
exec /entrypoint.sh "$@"
