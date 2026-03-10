#!/bin/bash
# sapo_host_runner.sh — Runner del HOST para descargas SAPO
#
# Por qué existe este script:
#   El panel web de SAPO corre dentro del contenedor Docker de AzuraCast
#   (usuario 'azuracast'). Las herramientas de descarga (podget, etc.) solo
#   están instaladas en el HOST. Este script corre en el HOST via cron,
#   detecta los archivos trigger que escribe PHP y lanza cliente_rrll.sh
#   directamente en el host.
#
# Instalación (como root en el host):
#   1. chmod +x /var/www/html/sapo_host_runner.sh
#   2. Añadir al crontab del sistema (/etc/cron.d/sapo o crontab -e de root):
#        * * * * * root /var/www/html/sapo_host_runner.sh
#
# Formato del trigger:
#   Archivo: /var/www/html/logs/.sapo_trigger_EMISORA
#   Contenido: JSON con emisora, logfile, requested_at, requested_by

set -euo pipefail

TRIGGER_DIR="/var/www/html/logs"
SCRIPT="/var/www/html/cliente_rrll/cliente_rrll.sh"

# Validación de seguridad: solo nombres de emisora alfanuméricos con guiones/guiones_bajos
_es_emisora_valida() {
    [[ "$1" =~ ^[a-zA-Z0-9_-]{1,64}$ ]]
}

for trigger in "$TRIGGER_DIR"/.sapo_trigger_*; do
    # Ignorar si no hay ficheros (glob sin match)
    [ -f "$trigger" ] || continue

    emisora="${trigger##*/.sapo_trigger_}"

    # Validar nombre de emisora antes de cualquier operación
    if ! _es_emisora_valida "$emisora"; then
        echo "[sapo_host_runner] Trigger ignorado — nombre inválido: $emisora" >&2
        rm -f "$trigger"
        continue
    fi

    logfile="$TRIGGER_DIR/podget_${emisora}.log"

    # Eliminar el trigger ANTES de lanzar para evitar ejecuciones dobles
    # si el runner se ejecuta antes de que termine el script anterior
    rm -f "$trigger"

    # Verificar que no hay ya una ejecución en marcha para esta emisora
    lockfile="/tmp/cliente_descarga_${emisora}.lock"
    if [ -f "$lockfile" ]; then
        echo "[sapo_host_runner] Ya hay una ejecución en marcha para $emisora (lock: $lockfile). Omitiendo." >> "$logfile"
        continue
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando desde host runner para $emisora..." >> "$logfile"

    # Lanzar en background; SAPO_LOG_FILE hace que el script redirija su output al log
    SAPO_LOG_FILE="$logfile" nohup /bin/bash "$SCRIPT" \
        --emisora "$emisora" \
        </dev/null >> "$logfile" 2>&1 &

done
