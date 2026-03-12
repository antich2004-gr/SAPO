#!/bin/bash
# DEBUG DE CADUCIDADES — DRY RUN
# Muestra qué archivos serían eliminados por la lógica de caducidad
# sin borrar nada. Ejecutar como: bash debug_caducidades.sh --emisora NOMBRE

export LANG="es_ES.UTF-8"

EMISORA=""
while [[ "$#" -gt 0 ]]; do
    case "$1" in
        --emisora) EMISORA="$2"; shift 2 ;;
        *) echo "Uso: $0 --emisora NOMBRE"; exit 1 ;;
    esac
done

if [[ -z "$EMISORA" ]]; then
    echo "❌ Falta --emisora NOMBRE"
    exit 1
fi

BASE_DIR="/mnt/emisoras/$EMISORA"
MEDIA_DIR="$BASE_DIR/media"
CONFIG_DIR="$BASE_DIR/Suscripciones"
PODCASTS_DIR="$MEDIA_DIR/Podcasts"
CADUCIDADES_FILE="$CONFIG_DIR/caducidades.txt"
DEFAULT_DIAS=30

echo "=========================================="
echo " DEBUG CADUCIDADES — emisora: $EMISORA"
echo " $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# --- 1. Verificar que los directorios existen ---
echo "📂 Directorios:"
for d in "$BASE_DIR" "$CONFIG_DIR" "$PODCASTS_DIR"; do
    if [[ -d "$d" ]]; then
        echo "  ✅ $d"
    else
        echo "  ❌ NO EXISTE: $d"
    fi
done
echo ""

# --- 2. Leer y mostrar caducidades.txt ---
echo "📄 Contenido de caducidades.txt: $CADUCIDADES_FILE"
if [[ -f "$CADUCIDADES_FILE" ]]; then
    echo ""
    declare -A CADUCIDADES
    linea_num=0
    while IFS= read -r linea_raw; do
        ((linea_num++))
        # Ignorar líneas vacías y comentarios
        linea_trim=$(echo "$linea_raw" | xargs 2>/dev/null || echo "$linea_raw")
        if [[ -z "$linea_trim" || "$linea_trim" == \#* ]]; then
            echo "  [$linea_num] (ignorada): '$linea_raw'"
            continue
        fi

        carpeta=$(echo "$linea_raw" | IFS=':' cut -d':' -f1 | xargs)
        dias=$(echo "$linea_raw" | IFS=':' cut -d':' -f2 | xargs)

        if [[ -n "$carpeta" && "$dias" =~ ^[0-9]+$ ]]; then
            CADUCIDADES["$carpeta"]=$dias
            echo "  [$linea_num] ✅ '$carpeta' → $dias días"
        else
            echo "  [$linea_num] ⚠️  FORMATO INVÁLIDO: '$linea_raw' (carpeta='$carpeta', dias='$dias')"
        fi
    done < "$CADUCIDADES_FILE"
else
    echo "  ⚠️  Archivo no encontrado — todas las carpetas usarán DEFAULT_DIAS=$DEFAULT_DIAS"
    declare -A CADUCIDADES
fi
echo ""

# --- 3. Analizar cada carpeta de Podcasts ---
echo "🔍 Análisis de archivos por carpeta (umbral = ahora - días × 86400):"
echo ""
now=$(date +%s)
echo "  Timestamp actual: $now ($(date -d @$now '+%Y-%m-%d %H:%M:%S'))"
echo ""

total_a_borrar=0
total_a_conservar=0

if [[ ! -d "$PODCASTS_DIR" ]]; then
    echo "  ❌ $PODCASTS_DIR no existe, nada que analizar."
else
    while IFS= read -r subdir; do
        nombre_carpeta=$(basename "$subdir")
        dias_caducidad=${CADUCIDADES["$nombre_carpeta"]:-$DEFAULT_DIAS}
        umbral_segundos=$(( now - dias_caducidad * 86400 ))
        umbral_fecha=$(date -d @$umbral_segundos '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo "fecha inválida")

        echo "  📁 $nombre_carpeta"
        echo "     Días configurados : $dias_caducidad${CADUCIDADES["$nombre_carpeta"]+""} ${CADUCIDADES["$nombre_carpeta"]:-(DEFAULT)}"
        echo "     Umbral de borrado  : $umbral_segundos ($umbral_fecha)"

        archivos_encontrados=0
        archivos_borrar=0
        archivos_ok=0

        while IFS='|' read -r timestamp archivo; do
            ts=${timestamp%.*}
            fecha_archivo=$(date -d @$ts '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo "?")
            edad_dias=$(( (now - ts) / 86400 ))
            ((archivos_encontrados++))

            if (( ts < umbral_segundos )); then
                echo "     🗑️  BORRARÍA  [$edad_dias días > umbral $dias_caducidad días] $fecha_archivo — $(basename "$archivo")"
                ((archivos_borrar++))
                ((total_a_borrar++))
            else
                echo "     ✅  Conservar [$edad_dias días de $dias_caducidad días] $fecha_archivo — $(basename "$archivo")"
                ((archivos_ok++))
                ((total_a_conservar++))
            fi
        done < <(find "$subdir" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" 2>/dev/null)

        if (( archivos_encontrados == 0 )); then
            echo "     (sin archivos de audio)"
        else
            echo "     → Total: $archivos_encontrados | Borraría: $archivos_borrar | Conservaría: $archivos_ok"
        fi
        echo ""
    done < <(find "$PODCASTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)
fi

# --- 4. Resumen ---
echo "=========================================="
echo " RESUMEN"
echo "  Archivos que BORRARÍA : $total_a_borrar"
echo "  Archivos que conservaría: $total_a_conservar"
echo "=========================================="
