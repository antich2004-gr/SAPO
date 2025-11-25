#!/bin/bash

# Script para ejecutar cliente_rrll.sh en todas las emisoras

CLIENTE_SCRIPT="/home/radioslibres/cliente_rrll/cliente_rrll.sh"
LOG_DIR="/tmp/logs_cliente_rrll"
mkdir -p "$LOG_DIR"

# 🔧 Lista  de emisoras
EMISORAS=("galapagar" "cable" "omc" "sonora" "radiobot")
 
EMISORAS_OK=()
EMISORAS_ERROR=()

echo "🚀 Ejecutando cliente_rrll.sh en emisoras definidas manualmente..."
echo "🗂️  Guardando logs en: $LOG_DIR"
echo

for NOMBRE in "${EMISORAS[@]}"; do
    LOG_FILE="$LOG_DIR/$NOMBRE.log"

    echo "📡 Ejecutando para: $NOMBRE"
    echo "📝 Log: $LOG_FILE"

    if "$CLIENTE_SCRIPT" --emisora "$NOMBRE" >"$LOG_FILE" 2>&1; then
        echo "✅ $NOMBRE: OK"
        EMISORAS_OK+=("$NOMBRE")
    else
        echo "❌ $NOMBRE: ERROR (ver log)"
        EMISORAS_ERROR+=("$NOMBRE")
    fi

    echo "---------------------------------------------"
done

# ⚠️ Comprobar espacio libre tras ejecutar script
    ESPACIO_DISPONIBLE=$(df --output=avail /dev/sdb | tail -1)
    if (( ESPACIO_DISPONIBLE < 5000000 )); then
        echo "⚠️ Espacio en /dev/sdb por debajo de 5 GB. Enviando aviso..."

        echo -e "Asunto: Espacio crítico en /dev/sdb\n\nSe detectó que el espacio libre en /dev/sdb es inferior a 5 GB." | /usr/sbin/sendmail fide@afoot.es
    fi

# 📊 Resumen final
echo
echo "🧾 RESUMEN:"
echo "✔ Emisoras con ejecución correcta: ${#EMISORAS_OK[@]}"
for e in "${EMISORAS_OK[@]}"; do
    echo "  • $e"
done

echo
echo "⚠️ Emisoras con errores: ${#EMISORAS_ERROR[@]}"
for e in "${EMISORAS_ERROR[@]}"; do
    echo "  • $e → Log: $LOG_DIR/$e.log"
done

echo
echo "🏁 Finalizado."

