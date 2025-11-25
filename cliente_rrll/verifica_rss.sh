#!/bin/bash

# Script: verifica_rss.sh
# Recorre todas las emisoras en /mnt/emisoras/
# Verifica el estado de los RSS listados en cada serverlist.txt
# Envía un correo si se detectan fallos y muestra el progreso en consola

DESTINATARIO="fide@afoot.es"
INFORME="/tmp/errores_rss_$(date +%Y%m%d).log"

echo "📡 Informe de errores RSS – $(date)" | tee "$INFORME"
echo | tee -a "$INFORME"

for EMISORA in /mnt/emisoras/*; do
    SERVERLIST="$EMISORA/media/Suscripciones/serverlist.txt"
    if [[ -f "$SERVERLIST" ]]; then
        echo "🔎 Revisando ${EMISORA##*/}" | tee -a "$INFORME"
        grep -vE '^\s*#|^\s*$' "$SERVERLIST" | while read -r URL; do
            echo -n "  ⏳ $URL ... "
            STATUS=$(curl -o /dev/null -s --max-time 10 -w "%{http_code}" "$URL")
            if [[ "$STATUS" == "200" ]]; then
                echo "✔️ OK"
            else
                echo "❌ HTTP $STATUS"
                echo "  ❌ $URL → HTTP $STATUS" >> "$INFORME"
            fi
        done
        echo | tee -a "$INFORME"
    else
        echo "⚠️  No se encontró serverlist.txt en ${EMISORA##*/}"
    fi
done

# Si hay errores, enviar por correo
if grep -q "❌" "$INFORME"; then
    echo "✉️  Enviando informe a $DESTINATARIO..."
    cat "$INFORME" | msmtp --subject="Errores RSS detectados en emisoras RRLL" "$DESTINATARIO"
    echo "✅ Informe enviado."
else
    echo "✅ Todos los RSS respondieron correctamente. No se envía informe."
    rm -f "$INFORME"
fi

