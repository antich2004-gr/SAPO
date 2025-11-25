#!/bin/bash

# =========================
# Configuración por emisora
# =========================

# Define un array asociativo con la configuración de cada emisora
declare -A EMISORAS

# Formato: EMISORAS["nombre_emisora"]="ruta_log;correo1,correo2;nombre_amigable"
EMISORAS["galapagar"]="/mnt/emisoras/galapagar/media/Informes;fide@afoot.es,radiogalapagar@gmail.com;Radio Galapagar"
EMISORAS["rvk"]="/mnt/emisoras/rvk/media/Informes;fide@afoot.es;Radio Vallekas"
EMISORAS["cable"]="/mnt/emisoras/cable/media/Informes;fide@afoot.es,fberlin@radiocable.com;Radio Cable"
EMISORAS["sonora"]="/mnt/emisoras/sonora/media/Informes;fide@afoot.es,sonora.asoc@uc3m.es;Sonora"
# =========================
# Envío de informes
# =========================

# Fecha del día anterior
FECHA_YESTERDAY=$(date -d 'yesterday' +'%d_%m_%Y')
FECHA_ASUNTO=$(date -d 'yesterday' +'%d-%m-%Y')

for EMISORA in "${!EMISORAS[@]}"; do
    IFS=';' read -r RUTA_LOG DESTINATARIOS NOMBRE_AMIGABLE <<< "${EMISORAS[$EMISORA]}"
    ARCHIVO_LOG="${RUTA_LOG}/Informe_diario_${FECHA_YESTERDAY}.log"

    if [ -f "$ARCHIVO_LOG" ]; then
        ASUNTO="Log de ${NOMBRE_AMIGABLE} del ${FECHA_ASUNTO}"
        echo "Enviando informe de ${NOMBRE_AMIGABLE} a ${DESTINATARIOS}..."

        # Crear el cuerpo del mensaje con encabezados MIME correctamente formateados para UTF-8
        {
            echo -e "Subject: $ASUNTO"
            echo "To: $DESTINATARIOS"
            echo "Content-Type: text/plain; charset=UTF-8"
            echo "Content-Transfer-Encoding: 8bit"
            echo
            cat "$ARCHIVO_LOG"
        } > "${ARCHIVO_LOG}.tmp"

        # Enviar el correo
        msmtp -t < "${ARCHIVO_LOG}.tmp"

        if [ $? -eq 0 ]; then
            echo "Correo enviado correctamente para ${NOMBRE_AMIGABLE}."
        else
            echo "Error al enviar el correo para ${NOMBRE_AMIGABLE}."
        fi

        rm "${ARCHIVO_LOG}.tmp"
    else
        echo "No se encontró el archivo de log para ${NOMBRE_AMIGABLE} en ${ARCHIVO_LOG}."
    fi
done

