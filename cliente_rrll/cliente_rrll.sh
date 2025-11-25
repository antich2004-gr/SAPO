
#!/bin/bash
# CLIENTE RRLL
# Versión: 0.9.3
# Estado: BETA
# Última modificación: 13-05-2025

set -euo pipefail
umask 002
export LANG="es_ES.UTF-8"
EJECUTAR_PODGET=1

# --- PARSEAR PARÁMETROS ---
while [[ "$#" -gt 0 ]]; do
    case "$1" in
        --emisora)
            EMISORA="$2"
            shift 2
            ;;
        --sinpodget)
            EJECUTAR_PODGET=0
            shift
            ;;
        --help)
            echo "Uso: $0 --emisora NOMBRE [--sinpodget]"
            exit 0
            ;;
        *)
            echo "❌ Opción no reconocida: $1"
            echo "Uso: $0 --emisora NOMBRE [--sinpodget]"
            exit 1
            ;;
    esac
done

if [[ -z "${EMISORA:-}" ]]; then
    echo "❌ Error: debe indicar la emisora usando --emisora."
    exit 1
fi
# --- BLOQUEO POR EMISORA ---
LOCK_FILE="/tmp/cliente_descarga_${EMISORA}.lock"
MAX_RETRIES=5
WAIT_SECONDS=60

attempt=0
while [ -e "$LOCK_FILE" ]; do
    if [ $attempt -ge $MAX_RETRIES ]; then
        echo "🚫 Script ya en ejecución para $EMISORA tras $MAX_RETRIES reintentos. Abortando."
        exit 1
    fi
    echo "⏳ Esperando $WAIT_SECONDS segundos (intento $((attempt+1))/$MAX_RETRIES)..."
    sleep $WAIT_SECONDS
    ((attempt++))
done

touch "$LOCK_FILE"
trap 'rm -f "$LOCK_FILE"' EXIT


obtener_slug_azuracast() {
    local nombre_script="$1"
    local script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    local archivo_emisoras="$script_dir/emisoras.txt"
    grep -E "^[^#]*:$nombre_script$" "$archivo_emisoras" | cut -d':' -f2
}

mostrar_playlists_vacias() {
    local emisora_slug="$1"
    local api_url="https://radiobot.radioslibres.info/api"
    local api_key="fff87a384794a795:b52adf1198024d8d45e6b27539076031"

    echo
    echo "📄 Listas de reproducción vacías (según API):"

    local json=$(curl -s -H "X-API-Key: $api_key" "$api_url/station/$emisora_slug/playlists")

    if [[ -z "$json" || "$json" == "[]" ]]; then
        echo "  ❌ No se pudieron obtener listas para $emisora_slug."
        return
    fi

    local vacias=$(echo "$json" | jq -r '.[] | select(.source == "songs") | select(.is_enabled == true) | select(.num_songs == 0) | .name')

    if [[ -z "$vacias" ]]; then
        echo "  (ninguna lista vacía activa)"
    else
        echo "$vacias" | while read -r nombre; do
            echo "  - $nombre"
        done
    fi
}



# --- VARIABLES DE DIRECTORIO ---
BASE_DIR="/mnt/emisoras/$EMISORA/media"
CONFIG_DIR="$BASE_DIR/Suscripciones"
PODCASTS_DIR="$BASE_DIR/Podcasts"
INFORMES_DIR="$BASE_DIR/Informes"

mkdir -p "$INFORMES_DIR"

RENOMBRADOS_HISTORICO="$INFORMES_DIR/historico_renombrados.txt"
ELIMINADOS_HISTORICO="$INFORMES_DIR/historico_eliminados.txt"
touch "$RENOMBRADOS_HISTORICO" "$ELIMINADOS_HISTORICO"

# --- LIMPIEZA DE ENTRADAS ANTIGUAS EN LOS HISTÓRICOS ---
echo "🧽 Limpiando históricos (solo se conservarán los últimos 365 días)..."
LIMITE=$(date -d "-365 days" +"%Y-%m-%d")
for HISTORICO in "$RENOMBRADOS_HISTORICO" "$ELIMINADOS_HISTORICO"; do
    awk -F'|' -v fecha="$LIMITE" '
        NF == 3 {
            split($1, f, " ")
            if (f[1] >= fecha) print $0
        }
    ' "$HISTORICO" > "${HISTORICO}.tmp" && mv "${HISTORICO}.tmp" "$HISTORICO"
done

# --- [NUEVO] Utilidades de limpieza de locks Podget ---
# Extrae DIR_SESSION de podgetrc.$EMISORA (si existe y no comentado)
obtener_dir_session() {
    local rcfile="$CONFIG_DIR/podgetrc.$EMISORA"
    [[ -f "$rcfile" ]] || { echo ""; return 0; }
    # Toma la última asignación efectiva (por si hay varias); ignora líneas comentadas
    awk -F= '
        /^[[:space:]]*#/ {next}
        $1 ~ /^[[:space:]]*DIR_SESSION[[:space:]]*$/ {
            gsub(/[[:space:]]/,"",$2);
            val=$2
        }
        END { if (val != "") print val }
    ' "$rcfile"
}

# Borra locks de Podget con mtime > N minutos en un directorio dado
_purgar_locks_en_dir() {
    local dir="$1"
    local minutos="$2"
    [[ -d "$dir" ]] || return 0
    # Patrones típicos de locks de Podget
    mapfile -t locks < <(find "$dir" -maxdepth 1 -type f \
        \( -name 'podget.lock' -o -name '.podget.lock' -o -name 'podget.lock.*' -o -name '.podget.lock.*' -o -name 'podget*.lock*' \) \
        -mmin +"$minutos" -print 2>/dev/null || true)
    if (( ${#locks[@]} )); then
        echo "🔓 Eliminando ${#locks[@]} lock(s) Podget > ${minutos} min en: $dir"
        for f in "${locks[@]}"; do
            echo "   - $(basename "$f")"
            rm -f -- "$f" || true
        done
    fi
}

# Purga genérica: CONFIG_DIR y (si existe) DIR_SESSION de podgetrc
purgar_bloqueos_podget_antiguos() {
    local minutos="${1:-240}"  # 4 horas por defecto
    # 1) CONFIG_DIR por si acaso
    _purgar_locks_en_dir "$CONFIG_DIR" "$minutos"
    # 2) DIR_SESSION definido en podgetrc.$EMISORA
    local dir_session
    dir_session="$(obtener_dir_session)"
    if [[ -n "$dir_session" ]]; then
        _purgar_locks_en_dir "$dir_session" "$minutos"
    fi
}

# --- DESCARGA DE PODCASTS (opcional con --sinpodget) ---
PODGET_LOG="/tmp/podget_${EMISORA}.log"
if [[ "$EJECUTAR_PODGET" -eq 1 ]]; then
    echo "📅 Ejecutando podget para $EMISORA..."
# --- DESCARGA AUTOMÁTICA DE PODCASTS DESDE YOUTUBE ---
SERVERLIST="$CONFIG_DIR/serverlist.txt"
if grep -Eiq "youtube\.com|youtu\.be" "$SERVERLIST" 2>/dev/null; then
    echo "📺 Detectadas URLs de YouTube en $SERVERLIST"
    mkdir -p "$PODCASTS_DIR"

    grep -E "youtube\.com|youtu\.be" "$SERVERLIST" | while read -r url carpeta; do
        [[ -z "$url" || -z "$carpeta" ]] && continue
        destino="$PODCASTS_DIR/$carpeta"
        mkdir -p "$destino"
        echo "⬇️ Descargando desde YouTube: $url → $destino"
        if command -v yt-dlp &>/dev/null; then
            yt-dlp -x --audio-format mp3 -o "$destino/%(title)s.%(ext)s" "$url" \
                || echo "⚠️ Error al descargar $url"
        else
            echo "⚠️ yt-dlp no está instalado. Instálalo con: apt install yt-dlp"
        fi
    done
else
    echo "📺 No se detectaron URLs de YouTube en serverlist.txt"
fi

######
    cd "$CONFIG_DIR"
    podget -d . -c "podgetrc.$EMISORA" | tee "$PODGET_LOG"
    cd - >/dev/null
else
    echo "⏭️  Saltando ejecución de podget (--sinpodget activado)"
    echo "" > "$PODGET_LOG"
fi

# Limpieza post-podget (locks que queden colgados si ya no hay proceso activo)
limpieza_post_podget() {
    local minutos="${1:-10}"   # más conservador: 10 min
    local dir_session
    dir_session="$(obtener_dir_session)"
    # Si Podget no está ejecutándose para esta emisora, limpia locks recientes
    if ! pgrep -f "podget.*podgetrc\.${EMISORA}" >/dev/null 2>&1; then
        _purgar_locks_en_dir "$CONFIG_DIR" "$minutos"
        [[ -n "$dir_session" ]] && _purgar_locks_en_dir "$dir_session" "$minutos"
    fi
}

# --- CORRECCIÓN DE EXTENSIONES MALFORMADAS ---
echo "🔧 Corrigiendo extensiones malformadas..."
find "$PODCASTS_DIR" -type f \( -iname "*.mp3.*" -o -iname "*.ogg.*" -o -iname "*.wav.*" \) | while read -r file; do
    case "$file" in
        *.mp3.*) newname="${file%.*}.mp3" ;;
        *.ogg.*) newname="${file%.*}.ogg" ;;
        *.wav.*) newname="${file%.*}.wav" ;;
        *) continue ;;
    esac
    if [[ "$file" != "$newname" && ! -e "$newname" ]]; then
        mv "$file" "$newname"
        echo "  ✔ Renombrado: $(basename "$file") → $(basename "$newname")"
    fi
done

# --- RENOMBRADO, ELIMINACIÓN POR REEMPLAZO Y REGISTRO ---
DIA=$(date +"%d")
MES=$(date +"%m")
ANO=$(date +"%Y")
HOY=$(date +"%Y-%m-%d")

echo "📆 Renombrando descargas de hoy..."

START=$(date -d "$HOY 00:00:00" +%s)
END=$(date -d "$HOY 23:59:59" +%s)

find "$PODCASTS_DIR" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" | while IFS='|' read -r timestamp file; do
    ts=${timestamp%.*}
    if (( ts >= START && ts <= END )); then
        carpeta=$(basename "$(dirname "$file")")
        ext="${file##*.}"
        nuevo_nombre="${carpeta}${DIA}${MES}${ANO}.${ext}"
        nuevo_path="$(dirname "$file")/$nuevo_nombre"
        fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
        dir="$(dirname "$file")"

        if [[ "$file" != "$nuevo_path" && ! -e "$nuevo_path" ]]; then
            # 🗑️ Eliminar archivos de audio antiguos antes de renombrar
            find "$dir" -maxdepth 1 -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) ! -samefile "$file" | while read -r antiguo; do
                echo "  🗑️ Eliminando por reemplazo: $(basename "$antiguo")"
                rm -f "$antiguo"
                echo "$fecha_actual|$antiguo|REEMPLAZO" >> "$ELIMINADOS_HISTORICO"
            done

            # ✔ Renombrar archivo descargado
            mv "$file" "$nuevo_path"
            echo "  ✔ Renombrado: $(basename "$file") → $nuevo_nombre"
            echo "$fecha_actual|$nuevo_path|RENOMBRADO" >> "$RENOMBRADOS_HISTORICO"
        else
            echo "  ⚠️ No renombrado: $nuevo_nombre ya existe"
        fi
    fi
done
DIA=$(date +"%d")
MES=$(date +"%m")
ANO=$(date +"%Y")
HOY=$(date +"%Y-%m-%d")
now=$(date +%s)

# --- LIMPIEZA POR CADUCIDAD ---
echo "🧹 Limpiando archivos por caducidad..."

declare -A CADUCIDADES

CADUCIDADES_FILE="$CONFIG_DIR/caducidades.txt"
DEFAULT_DIAS=30

if [[ -f "$CADUCIDADES_FILE" ]]; then
    while IFS=':' read -r carpeta dias; do
        carpeta=$(echo "$carpeta" | xargs)
        dias=$(echo "$dias" | xargs)
        if [[ -n "$carpeta" && "$dias" =~ ^[0-9]+$ ]]; then
            CADUCIDADES["$carpeta"]=$dias
        fi
    done < "$CADUCIDADES_FILE"
fi

find "$PODCASTS_DIR" -mindepth 1 -maxdepth 1 -type d | while read -r subdir; do
    nombre_carpeta=$(basename "$subdir")
    dias_caducidad=${CADUCIDADES["$nombre_carpeta"]:-$DEFAULT_DIAS}
    umbral_segundos=$(( $(date +%s) - dias_caducidad * 86400 ))

    find "$subdir" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" | while IFS='|' read -r timestamp archivo; do
        ts=${timestamp%.*}
        if (( ts < umbral_segundos )); then
            echo "  🗑️ Eliminando por caducidad: $(basename "$archivo")"
            rm -f "$archivo"
            fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
            echo "$fecha_actual|$archivo|CADUCIDAD" >> "$ELIMINADOS_HISTORICO"
        fi
    done
done
# --- ELIMINAR ARCHIVOS ANTIGUOS, CONSERVANDO SOLO EL MÁS RECIENTE POR CARPETA ---
echo "🧹 Manteniendo solo el archivo más reciente por carpeta..."

find "$PODCASTS_DIR" -mindepth 1 -maxdepth 1 -type d | while read -r subdir; do
    if find "$subdir" -mindepth 1 -maxdepth 1 -type d | grep -q .; then
        # Tiene subcarpetas → procesar cada subcarpeta
        find "$subdir" -mindepth 1 -maxdepth 1 -type d | while read -r nested; do
            archivos=( $(find "$nested" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" | sort -n | awk -F'|' '{print $2}') )
            total=${#archivos[@]}
            if (( total > 1 )); then
                for (( i=0; i<total-1; i++ )); do
                    archivo="${archivos[i]}"
                    echo "  🗑️ Eliminando por antigüedad: $(basename "$archivo")"
                    rm -f "$archivo"
                    fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
                    echo "$fecha_actual|$archivo|REEMPLAZO" >> "$ELIMINADOS_HISTORICO"
                done
            fi
        done
    else
        # No tiene subcarpetas → procesar directamente
        archivos=( $(find "$subdir" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" | sort -n | awk -F'|' '{print $2}') )
        total=${#archivos[@]}
        if (( total > 1 )); then
            for (( i=0; i<total-1; i++ )); do
                archivo="${archivos[i]}"
                echo "  🗑️ Eliminando por antigüedad: $(basename "$archivo")"
                rm -f "$archivo"
                fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
                echo "$fecha_actual|$archivo|REEMPLAZO" >> "$ELIMINADOS_HISTORICO"
            done
        fi
    fi
done

# --- VERIFICACIÓN DE DURACIÓN EN CARPETAS ASIGNADAS ---
echo "⏱️ Verificando duración de archivos por carpeta..."

DURACIONES_FILE="$CONFIG_DIR/duraciones.txt"
declare -A MAPA_DURACIONES
declare -A MAPA_TIEMPO_SEG

# Definir límites con 5 minutos extra (en segundos)

MAPA_TIEMPO_SEG["30M"]=2100     # 35 min
MAPA_TIEMPO_SEG["1H"]=3900      # 65 min
MAPA_TIEMPO_SEG["1H30"]=5700    # 95 min
MAPA_TIEMPO_SEG["2H"]=7500      # 125 min
MAPA_TIEMPO_SEG["2H30"]=9300    # 155 min
MAPA_TIEMPO_SEG["3H"]=11100     # 185 min    # 185 min  # 185 min



# Leer archivo de configuración
if [[ -f "$DURACIONES_FILE" ]]; then
    while IFS=':' read -r carpeta clave; do
        carpeta=$(echo "$carpeta" | xargs)
        clave=$(echo "$clave" | xargs)
        if [[ -n "$carpeta" && -n "$clave" && -n "${MAPA_TIEMPO_SEG[$clave]:-}" ]]; then
            MAPA_DURACIONES["$carpeta"]="${MAPA_TIEMPO_SEG[$clave]}"
        fi
    done < "$DURACIONES_FILE"
fi

# Aplicar verificación por carpeta
for carpeta in "${!MAPA_DURACIONES[@]}"; do
    umbral=${MAPA_DURACIONES[$carpeta]}

    if [[ "$carpeta" == "$carpeta" && "${MAPA_TIEMPO_SEG[$carpeta]+x}" ]]; then
        # Carpeta tipo 1H:1H → recorrer subcarpetas de Podcast/1H/
        echo "📂 Verificando subcarpetas de $carpeta (límite: $((umbral/60)) min)"
        find "$PODCASTS_DIR/$carpeta" -mindepth 1 -maxdepth 1 -type d | while read -r subdir; do
            find "$subdir" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) | while read -r archivo; do
                duracion=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$archivo" 2>/dev/null | cut -d. -f1)
                if [[ -n "$duracion" && "$duracion" -gt "$umbral" ]]; then
                    echo "⛔ $(basename "$archivo") en $(basename "$subdir") → ${duracion}s (excede)"
                    fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
                    echo "$fecha_actual|$archivo|EXCESO_DURACION" >> "$ELIMINADOS_HISTORICO"
                    rm -f "$archivo"
                fi
            done
        done
    else
        # Carpeta específica
        ruta="$PODCASTS_DIR/$carpeta"
        [[ -d "$ruta" ]] || continue
        echo "📂 Verificando carpeta $carpeta (límite: $((umbral/60)) min)"
        find "$ruta" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) | while read -r archivo; do
            duracion=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$archivo" 2>/dev/null | cut -d. -f1)
            if [[ -n "$duracion" && "$duracion" -gt "$umbral" ]]; then
                echo "⛔ $(basename "$archivo") → ${duracion}s (excede)"
                fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
                echo "$fecha_actual|$archivo|EXCESO_DURACION" >> "$ELIMINADOS_HISTORICO"
                rm -f "$archivo"
            fi
        done
    fi
done
INFORME="$INFORMES_DIR/Informe_diario_${DIA}_${MES}_${ANO}.log"

{
    EMISORA_MAYUSCULA="$(tr '[:lower:]' '[:upper:]' <<< ${EMISORA:0:1})${EMISORA:1}"
    MES_NOMBRE=$(LC_TIME=es_ES.UTF-8 date +"%B")
    echo "📻 Informe diario – Emisora: $EMISORA_MAYUSCULA"
    echo "🗓️  Fecha: $DIA de $MES_NOMBRE de $ANO"
    echo

    total_descargados=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1' "$RENOMBRADOS_HISTORICO" | wc -l)
    total_eliminados=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1' "$ELIMINADOS_HISTORICO" | wc -l)
    total_caducidad=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1 && $3 == "CADUCIDAD"' "$ELIMINADOS_HISTORICO" | wc -l)
    total_reemplazo=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1 && $3 == "REEMPLAZO"' "$ELIMINADOS_HISTORICO" | wc -l)

    echo "• $total_descargados podcasts descargados"
    echo "• $total_eliminados archivos eliminados ($total_caducidad por caducidad, $total_reemplazo por reemplazo)"
    echo

    echo "🎷 Últimos podcasts descargados:"
    echo
    echo
         echo " Hoy"
         awk -F'|' -v hoy="$HOY" '
             BEGIN {count=0}
             $1 ~ hoy {
                 split($1, f, "[- :]")
                  n = split($2, parts, "/")
               archivo = (n > 0) ? parts[n] : $2
            podcast = toupper(gensub("_", " ", "g", parts[n-1]))
            entradas[count++] = "  " podcast " - [" sprintf("%02d-%02d-%04d %02d:%02d:%02d", f[3], f[2], f[1], f[4], f[5], f[6]) "] " archivo
        }
        END {
            for (i = count - 1; i >= 0; i--) print entradas[i]
            if (count == 0) print "  (ninguno)"
        }
    ' "$RENOMBRADOS_HISTORICO"
    echo
    echo " Días anteriores"
    awk -F'|' -v hoy="$HOY" '
        BEGIN {count=0}
        $1 !~ hoy {
            split($1, f, "[- :]")
            fecha_fmt = sprintf("%02d-%02d-%04d %02d:%02d:%02d", f[3], f[2], f[1], f[4], f[5], f[6])
            n = split($2, parts, "/")
            archivo = parts[n]
            podcast = toupper(gensub("_", " ", "g", parts[n-1]))
            entradas[count++] = "  " podcast " - [" fecha_fmt "] " archivo
        }
        END {
            for (i = count - 1; i >= 0 && i >= count - 5; i--) print entradas[i]
            if (count == 0) print "  (ninguno)"
        }
    ' "$RENOMBRADOS_HISTORICO"

    echo
    echo "🗑️ Últimos archivos eliminados:"
    echo
echo " Hoy"
awk -F'|' -v hoy="$HOY" '
    $1 ~ hoy {
        split($1, f, "[- :]")
        n = split($2, parts, "/")
        archivo = (n > 0) ? parts[n] : $2
        podcast = toupper(gensub("_", " ", "g", parts[n-1]))
        motivo = tolower($3)
        gsub("_", " ", motivo)
        printf "  %s - [%02d-%02d-%04d %02d:%02d:%02d] %s ← por %s\n",
               podcast, f[3], f[2], f[1], f[4], f[5], f[6], archivo, motivo
        encontrado = 1
    }
    END {
        if (!encontrado) print "  (ninguno)"
    }
' "$ELIMINADOS_HISTORICO"

    echo
    echo " Días anteriores"
awk -F'|' -v hoy="$HOY" '
    $1 !~ hoy {
        split($1, f, "[- :]")
        n = split($2, parts, "/")
        archivo = (n > 0) ? parts[n] : $2
        podcast = toupper(gensub("_", " ", "g", parts[n-1]))
        motivo = tolower($3)
        gsub("_", " ", motivo)
        entradas[count++] = sprintf("  %s - [%02d-%02d-%04d %02d:%02d:%02d] %s ← por %s",
            podcast, f[3], f[2], f[1], f[4], f[5], f[6], archivo, motivo)
    }
    END {
        for (i = count - 1; i >= 0 && i >= count - 5; i--) print entradas[i]
        if (count == 0) print "  (ninguno)"
    }
' "$ELIMINADOS_HISTORICO"

    echo
    echo "📂 Carpetas vacías:"
    find "$PODCASTS_DIR" -type d | while read -r carpeta; do
        if ! find "$carpeta" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) | grep -q .; then
            ultima_mod=$(stat -c %Y "$carpeta" 2>/dev/null)
            if [[ -n "$ultima_mod" ]]; then
                dias_vacio=$(( (now - ultima_mod) / 86400 ))
                echo -e "$dias_vacio\t$carpeta"
            else
                echo -e "SIN_FECHA\t$carpeta"
            fi
        fi
    done | sort -k1,1n | while IFS=$'\t' read -r dias carpeta; do
        nombre="${carpeta#$PODCASTS_DIR/}"
        if [[ "$dias" == "SIN_FECHA" ]]; then
            echo "  - $nombre (vacía sin fecha detectable)"
        else
            echo "  - $nombre (vacía desde hace $dias días)"
        fi
    done

    echo
    echo "🔍 Errores Podget:"
    awk '
        BEGIN {categoria=""; nombre=""; url=""; errores=0}
        /^Category:/ {categoria = substr($0, index($0,$2)); next}
        /^Name:/ {nombre = substr($0, index($0,$2)); next}
        /^Downloading feed index from/ {url = $NF; next}
        /Already downloaded/ {next}
        /(ERROR|Error|Error de lectura|en las cabeceras|failed|No enclosures|404|Feed not found|Error Downloading Feed)/ {
            print "  ⚠️  " categoria " | " nombre " | " url " → " $0
            errores=1
        }
        END {
            if (errores == 0) print "  Ningún error detectado"
        }
    ' "$PODGET_LOG"

LIQUIDSOAP_LOG="/mnt/emisoras/$EMISORA/config/liquidsoap.log"

    echo
echo "📡 Emisiones en directo:"
awk -v hoy="$(date +%Y/%m/%d)" '
    BEGIN {
        encontrado = 0
    }
    # Caso Liquidsoap DJ conectado
    $0 ~ hoy && /DJ Source connected!/ {
        split($1, f, "/"); split($2, h, ":");
        inicio_fecha = sprintf("%02d-%02d-%04d", f[3], f[2], f[1])
        inicio_hora = h[1] ":" h[2] ":" h[3]
        if (match($0, /Last authenticated DJ: [^ ]+/)) {
            origen = substr($0, RSTART+24, RLENGTH-24)
        } else {
            origen = "desconocido"
        }
        flag_dj = 1
        next
    }
    $0 ~ /API djoff/ && flag_dj == 1 {
        split($1, f2, "/"); split($2, h2, ":");
        fin_hora = h2[1] ":" h2[2] ":" h2[3]
        print "  - " inicio_fecha " " inicio_hora " → " fin_hora " desde DJ " origen
        encontrado = 1
        flag_dj = 0
    }

    # Caso clásico Icecast (opcional, puedes eliminar si solo usas AutoDJ)
    $0 ~ hoy && /connection initiated/ {
        split($1, f, "/"); split($2, h, ":");
        inicio_fecha = sprintf("%02d-%02d-%04d", f[3], f[2], f[1])
        inicio_hora = h[1] ":" h[2] ":" h[3]
        if (match($0, /from [^ ]+/)) {
            origen = substr($0, RSTART+5, RLENGTH-5)
        } else {
            origen = "desconocido"
        }
        flag_ice = 1
        next
    }
    $0 ~ /connection closed/ && flag_ice == 1 {
        split($1, f2, "/"); split($2, h2, ":");
        fin_hora = h2[1] ":" h2[2] ":" h2[3]
        print "  - " inicio_fecha " " inicio_hora " → " fin_hora " desde Icecast " origen
        encontrado = 1
        flag_ice = 0
    }
    END {
        if (flag_dj == 1) {
            print "  - " inicio_fecha " " inicio_hora " → (aún activo) desde DJ " origen
            encontrado = 1
        }
        if (flag_ice == 1) {
            print "  - " inicio_fecha " " inicio_hora " → (aún activo) desde Icecast " origen
            encontrado = 1
        }
        if (encontrado == 0) print "  Ninguna emisión en directo"
    }
' "$LIQUIDSOAP_LOG"

    slug_api=$(obtener_slug_azuracast "$EMISORA")
    if [[ -n "$slug_api" ]]; then
        mostrar_playlists_vacias "$slug_api"
    fi





echo
    echo "✅ Finalizado correctamente."
} > "$INFORME"

echo
echo "✅ Finalizado correctamente."
exit 0



