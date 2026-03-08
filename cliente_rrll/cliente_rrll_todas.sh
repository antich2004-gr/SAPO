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

# Leer config desde db.json (cada valor en su propia línea para evitar errores con campos vacíos)
SAPO_CONFIG=$(python3 -c "
import json
with open('$SAPO_DB') as f:
    db = json.load(f)
cfg = db.get('config', {})
print(cfg.get('base_path', '/mnt/emisoras'))
print(cfg.get('azuracast_api_url', ''))
print(cfg.get('azuracast_api_key', ''))
for u in db.get('users', []):
    if not u.get('is_admin', False):
        print('USER:' + u['username'])
" 2>/dev/null)

EMISORAS_BASE_DB=$(echo "$SAPO_CONFIG" | sed -n '1p')
AZ_API_URL=$(echo "$SAPO_CONFIG"      | sed -n '2p')
AZ_API_KEY=$(echo "$SAPO_CONFIG"      | sed -n '3p')
mapfile -t EMISORAS_SAPO < <(echo "$SAPO_CONFIG" | grep '^USER:' | sed 's/^USER://')

# Usar base_path de db.json si está disponible, si no el valor por defecto
[ -n "$EMISORAS_BASE_DB" ] && EMISORAS_BASE="$EMISORAS_BASE_DB"

# Obtener emisoras desde AzuraCast (/api/stations devuelve shortcode de cada emisora)
EMISORAS_AZ=()
if [ -n "$AZ_API_URL" ]; then
    AZ_STATIONS_JSON=$(curl -sf \
        ${AZ_API_KEY:+-H "X-API-Key: $AZ_API_KEY"} \
        "${AZ_API_URL}/api/stations" 2>/dev/null)
    if [ -n "$AZ_STATIONS_JSON" ]; then
        mapfile -t EMISORAS_AZ < <(python3 -c "
import json, sys
stations = json.loads('''$AZ_STATIONS_JSON''')
for s in stations:
    sc = s.get('shortcode', '')
    if sc:
        print(sc)
" 2>/dev/null)
        echo "📡 AzuraCast: ${#EMISORAS_AZ[@]} emisoras encontradas"
    else
        echo "⚠️  AzuraCast: no se pudo conectar a ${AZ_API_URL}"
    fi
fi

# Detectar emisoras en el sistema de ficheros (dirs con media/Suscripciones configurado)
EMISORAS_FS=()
EXCLUIR=("backups" "lost+found" "rrll")
if [ -d "$EMISORAS_BASE" ]; then
    while IFS= read -r -d '' dir; do
        nombre=$(basename "$dir")
        # Saltar directorios excluidos
        skip=0
        for ex in "${EXCLUIR[@]}"; do
            [ "$nombre" = "$ex" ] && skip=1 && break
        done
        [ $skip -eq 1 ] && continue
        # Solo incluir si tiene directorio de suscripciones
        if [ -d "$dir/media/Suscripciones" ]; then
            EMISORAS_FS+=("$nombre")
        fi
    done < <(find "$EMISORAS_BASE" -mindepth 1 -maxdepth 1 -type d -print0 | sort -z)
    echo "📂 Filesystem: ${#EMISORAS_FS[@]} emisoras con Suscripciones configuradas"
fi

# Combinar y deduplicar emisoras de SAPO + AzuraCast + Filesystem
declare -A _SEEN
EMISORAS=()
for e in "${EMISORAS_SAPO[@]}" "${EMISORAS_AZ[@]}" "${EMISORAS_FS[@]}"; do
    if [ -z "${_SEEN[$e]+x}" ]; then
        _SEEN[$e]=1
        EMISORAS+=("$e")
    fi
done

if [ ${#EMISORAS[@]} -eq 0 ]; then
    echo "❌ No se encontraron emisoras activas en SAPO, AzuraCast ni en $EMISORAS_BASE"
    exit 1
fi

EMISORAS_OK=()
EMISORAS_ERROR=()

echo "🚀 Ejecutando cliente_rrll.sh en todas las emisoras activas (SAPO + AzuraCast + Filesystem)..."
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
