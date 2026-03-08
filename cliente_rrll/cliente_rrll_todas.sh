#!/bin/bash

# Script para ejecutar cliente_rrll.sh en todas las emisoras activas de SAPO

# Ruta del script principal relativa a este archivo
SCRIPT_DIR="$(dirname "$(realpath "$0")")"
CLIENTE_SCRIPT="$SCRIPT_DIR/cliente_rrll.sh"

# Ruta de db.json de SAPO
SAPO_DB="$(dirname "$SCRIPT_DIR")/db.json"

# Directorio de logs
LOG_DIR="/tmp/logs_cliente_rrll"
mkdir -p "$LOG_DIR"

# Ruta base donde viven las emisoras
EMISORAS_BASE="/mnt/emisoras"

# Verificaciones previas
if [ ! -f "$CLIENTE_SCRIPT" ]; then
    echo "❌ No se encontró el script: $CLIENTE_SCRIPT"
    exit 1
fi

if [ ! -x "$CLIENTE_SCRIPT" ]; then
    echo "❌ El script no tiene permisos de ejecución: $CLIENTE_SCRIPT"
    exit 1
fi

if [ ! -f "$SAPO_DB" ]; then
    echo "❌ No se encontró la base de datos de SAPO: $SAPO_DB"
    exit 1
fi

# Leer emisoras activas y base_path desde db.json
read -r EMISORAS_BASE_DB EMISORAS_LIST < <(python3 -c "
import json
with open('$SAPO_DB') as f:
    db = json.load(f)
base = db.get('config', {}).get('base_path', '/mnt/emisoras')
users = [u['username'] for u in db.get('users', []) if not u.get('is_admin', False)]
print(base, ' '.join(users))
" 2>/dev/null)

# Usar base_path de db.json si está disponible, si no el valor por defecto
[ -n "$EMISORAS_BASE_DB" ] && EMISORAS_BASE="$EMISORAS_BASE_DB"

# Convertir lista de emisoras a array
read -ra EMISORAS <<< "$EMISORAS_LIST"

if [ ${#EMISORAS[@]} -eq 0 ]; then
    echo "❌ No se encontraron emisoras activas en SAPO"
    exit 1
fi

EMISORAS_OK=()
EMISORAS_ERROR=()

echo "🚀 Ejecutando cliente_rrll.sh en todas las emisoras activas de SAPO..."
echo "🗂️  Guardando logs en: $LOG_DIR"
echo "📋 Emisoras encontradas: ${#EMISORAS[@]}"
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

# ⚠️ Comprobar espacio libre en la partición de emisoras
if mountpoint -q "$EMISORAS_BASE" 2>/dev/null || [ -d "$EMISORAS_BASE" ]; then
    ESPACIO_DISPONIBLE=$(df --output=avail "$EMISORAS_BASE" | tail -1)
    if (( ESPACIO_DISPONIBLE < 5000000 )); then
        echo "⚠️ Espacio en $EMISORAS_BASE por debajo de 5 GB. Enviando aviso..."
        echo -e "Asunto: Espacio crítico en $EMISORAS_BASE\n\nSe detectó que el espacio libre en $EMISORAS_BASE es inferior a 5 GB." | /usr/sbin/sendmail fide@afoot.es
    fi
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
