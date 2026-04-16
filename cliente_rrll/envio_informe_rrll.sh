#!/bin/bash

# =========================
# Configuración por emisora
# =========================

# Define un array asociativo con la configuración de cada emisora
declare -A EMISORAS

# Formato: EMISORAS["nombre_emisora"]="ruta_log;correo1,correo2;nombre_amigable"
EMISORAS["agora"]="/mnt/emisoras/agora/media/Informes;fide@afoot.es,continuidad@agorasolradio.org;Ágora Sol Radio"
EMISORAS["almaina"]="/mnt/emisoras/almaina/media/Informes;programacion@radioalmaina.org,fide@afoot.es;Radio Almaina"
EMISORAS["argayo"]="/mnt/emisoras/argayo/media/Informes;radio_argayo@riseup.net,fide@afoot.es;Radio Argayo"
EMISORAS["contrabanda"]="/mnt/emisoras/contrabanda/media/Informes;contrabanda@contrabanda.org,fide@afoot.es;Contrabanda FM"
EMISORAS["gallinera"]="/mnt/emisoras/gallinera/media/Informes;radiogallinera@protonmail.com,fide@afoot.es;Radio Gallinera"
EMISORAS["mistelera"]="/mnt/emisoras/mistelera/media/Informes;radiomistelera@protonmail.com,fide@afoot.es;Radio Mistelera"
EMISORAS["ruidofeminista"]="/mnt/emisoras/ruidofeminista/media/Informes;ruidofeministaradio@riseup.net,fide@afoot.es;Radio Ruido Feminista"
EMISORAS["espiritrompa"]="/mnt/emisoras/espiritrompa/media/Informes;radioespiritrompa@sindominio.net,fide@afoot.es;Radio Espiritrompa"
EMISORAS["topo"]="/mnt/emisoras/topo/media/Informes;estudioscentrales@radiotopo.org,fide@afoot.es;Radio Topo"
EMISORAS["cadenazo"]="/mnt/emisoras/cadenazo/media/Informes;fide@afoot.es;Radio Cadenazo"
EMISORAS["granja"]="/mnt/emisoras/granja/media/Informes;emisora@radiolagranja.es,fide@afoot.es;Radio La Granja"
EMISORAS["irola"]="/mnt/emisoras/irola/media/Informes;irolairratia@riseup.net,fide@afoot.es;Irola Irratia"

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
