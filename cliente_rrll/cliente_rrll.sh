#!/bin/bash
# CLIENTE RRLL
# Versión: 0.9.3
# Estado: BETA
# Última modificación: 13-05-2025

set -euo pipefail
umask 002
export LANG="es_ES.UTF-8"
EJECUTAR_PODGET=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

# Leer API key/URL directamente del global.json de SAPO
SAPO_GLOBAL_JSON="$SCRIPT_DIR/../db/global.json"
AZURACAST_API_URL=""
AZURACAST_API_KEY=""
if [[ -f "$SAPO_GLOBAL_JSON" ]]; then
    AZURACAST_API_URL=$(jq -r '.config.azuracast_api_url // ""' "$SAPO_GLOBAL_JSON" 2>/dev/null)
    AZURACAST_API_KEY=$(jq -r '.config.azuracast_api_key // ""' "$SAPO_GLOBAL_JSON" 2>/dev/null)
fi

mostrar_playlists_vacias() {
    local emisora_slug="$1"

    if [[ -z "$AZURACAST_API_URL" || -z "$AZURACAST_API_KEY" ]]; then
        echo "  ⚠️  API no configurada. Introduce la URL y clave API en el panel de administración de SAPO."
        return
    fi

    echo
    echo "📄 Listas de reproducción vacías (según API):"

    local json
    json=$(curl -s -H "X-API-Key: $AZURACAST_API_KEY" "$AZURACAST_API_URL/station/$emisora_slug/playlists")

    if [[ -z "$json" || "$json" == "[]" ]]; then
        echo "  ❌ No se pudieron obtener listas para $emisora_slug."
        return
    fi

    if ! jq -e 'type == "array"' <<< "$json" >/dev/null 2>&1; then
        echo "  ⚠️  Respuesta de API no válida para $emisora_slug."
        return
    fi

    local vacias
    vacias=$(jq -r '.[] | select(.source == "songs") | select(.is_enabled == true) | select(.num_songs == 0) | .name' 2>/dev/null <<< "$json") || vacias=""

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
INFORMES_DIR="$BASE_DIR/Informes"
LIQUIDSOAP_LOG="/mnt/emisoras/$EMISORA/config/liquidsoap.log"

# Leer DIR_PODCAST o DIR_LIBRARY del podgetrc si existe, si no usar el valor por defecto
_leer_dir_podcast() {
    local rcfile="$CONFIG_DIR/podgetrc.$EMISORA"
    [[ -f "$rcfile" ]] || { echo ""; return 0; }
    awk -F= '
        /^[[:space:]]*#/ {next}
        $1 ~ /^[[:space:]]*DIR_PODCAST[[:space:]]*$/ {
            gsub(/[[:space:]]/,"",$2);
            podcast=$2
        }
        $1 ~ /^[[:space:]]*DIR_LIBRARY[[:space:]]*$/ {
            gsub(/[[:space:]]/,"",$2);
            library=$2
        }
        END { if (podcast != "") print podcast; else if (library != "") print library }
    ' "$rcfile"
}

_dir_podcast_rc=$(_leer_dir_podcast)
PODCASTS_DIR="${_dir_podcast_rc:-$BASE_DIR/Podcasts}"

echo "📂 Directorio de podcasts: $PODCASTS_DIR"

if [[ ! -d "$PODCASTS_DIR" ]]; then
    echo "❌ ERROR: El directorio de podcasts no existe: $PODCASTS_DIR"
    echo "   Configura DIR_PODCAST en $CONFIG_DIR/podgetrc.$EMISORA"
    echo "   Ejemplo:  DIR_PODCAST=/mnt/emisoras/$EMISORA/media/Suscripciones"
    exit 1
fi

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

# --- Utilidades de limpieza de locks Podget ---
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
PODGET_LOG="$INFORMES_DIR/podget_${EMISORA}.log"
if [[ "$EJECUTAR_PODGET" -eq 1 ]]; then
    echo "📅 Ejecutando podget para $EMISORA..."

    # --- DESCARGA DE SUSCRIPCIONES VÍA YT-DLP ---
    _descargar_ytdlp_feeds() {
        local feeds_file="$CONFIG_DIR/ytdlp_feeds.txt"

        if [[ ! -f "$feeds_file" ]]; then
            echo "📺 No se encontró ytdlp_feeds.txt — sin suscripciones de plataformas de vídeo."
            return 0
        fi

        if ! command -v yt-dlp &>/dev/null; then
            echo "⚠️  yt-dlp no está instalado. Instálalo con: apt install yt-dlp"
            return 0
        fi

        local archive_file="$CONFIG_DIR/ytdlp_archive_${EMISORA}.txt"
        # Cookies: primero busca una específica de la emisora, luego la global compartida
        local cookies_file="$CONFIG_DIR/youtube_cookies.txt"
        local cookies_global="/etc/sapo/youtube_cookies.txt"
        if [[ ! -f "$cookies_file" && -f "$cookies_global" ]]; then
            cookies_file="$cookies_global"
        fi
        local descargados=0
        local errores=0

        echo "📺 Procesando suscripciones de plataformas (ytdlp_feeds.txt)..."

        while IFS= read -r linea || [[ -n "$linea" ]]; do
            # Ignorar comentarios y líneas vacías (incluyendo pausadas con '# PAUSADO:')
            [[ "$linea" =~ ^[[:space:]]*# ]] && continue
            [[ -z "${linea// /}" ]] && continue

            # Parsear: URL CATEGORIA NOMBRE MAX_EPISODIOS
            read -r url categoria nombre max_ep <<< "$linea"
            [[ -z "$url" || -z "$nombre" ]] && continue

            # CATEGORIA='-' significa sin categoría
            local destino
            if [[ "$categoria" == "-" || -z "$categoria" ]]; then
                destino="$PODCASTS_DIR/$nombre"
            else
                destino="$PODCASTS_DIR/$categoria/$nombre"
            fi

            max_ep="${max_ep:-5}"
            mkdir -p "$destino"

            echo "  ⬇️  $nombre ($url) → $destino [máx. $max_ep ep.]"

            local ytdlp_cookies_arg=()
            [[ -f "$cookies_file" ]] && ytdlp_cookies_arg=(--cookies "$cookies_file")

            # Marca de tiempo para detectar archivos nuevos tras la descarga
            local ts_ref
            ts_ref=$(mktemp)

            local ytdlp_output
            ytdlp_output=$(yt-dlp \
                -x --audio-format mp3 \
                --audio-quality 5 \
                --playlist-end "$max_ep" \
                --match-filter "duration > 60" \
                --download-archive "$archive_file" \
                --no-playlist-reverse \
                "${ytdlp_cookies_arg[@]}" \
                -o "$destino/%(title)s.%(ext)s" \
                "$url" 2>&1 | grep -v "^\[download\] .*has already been recorded" || true)
            echo "$ytdlp_output"
            if echo "$ytdlp_output" | grep -qE "Sign in to confirm you're not a bot|cookies are no longer valid|account cookies.*no longer valid"; then
                echo "  🔑 AVISO: Las cookies de YouTube han caducado o han sido invalidadas. Es necesario renovarlas."
                echo "  🔑 Ruta del archivo: ${cookies_file}"
            elif echo "$ytdlp_output" | grep -q "ERROR:"; then
                echo "  ⚠️  Error al descargar $url"
                ((errores++)) || true
            fi

            # Registrar archivos nuevos descargados en el histórico
            while IFS= read -r nuevo_archivo; do
                local fecha_ytdlp
                fecha_ytdlp=$(date +"%Y-%m-%d %H:%M:%S")
                echo "$fecha_ytdlp|$nuevo_archivo|YTDLP" >> "$RENOMBRADOS_HISTORICO"
            done < <(find "$destino" -maxdepth 1 -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -newer "$ts_ref" 2>/dev/null)
            rm -f "$ts_ref"

            ((descargados++)) || true
        done < "$feeds_file"

        echo "📺 yt-dlp: $descargados fuente(s) procesada(s), $errores error(es)."
    }

    _descargar_ytdlp_feeds

    purgar_bloqueos_podget_antiguos
    cd "$CONFIG_DIR"
    podget -d . -c "podgetrc.$EMISORA" | tee "$PODGET_LOG"
    cd - >/dev/null
else
    echo "⏭️  Saltando ejecución de podget (--sinpodget activado)"
    echo "" > "$PODGET_LOG"
fi

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
now=$(date +%s)

# --- MÁXIMO DE EPISODIOS POR PODCAST (RSS) ---
declare -A MAX_EPISODIOS_RSS
MAX_EPISODIOS_RSS_FILE="$CONFIG_DIR/max_episodios_rss.txt"
if [[ -f "$MAX_EPISODIOS_RSS_FILE" ]]; then
    while IFS=':' read -r _nombre _n; do
        read -r _nombre <<< "$_nombre"
        read -r _n <<< "$_n"
        if [[ -n "$_nombre" && "$_n" =~ ^[0-9]+$ && "$_n" -ge 1 ]]; then
            MAX_EPISODIOS_RSS["$_nombre"]=$_n
        fi
    done < "$MAX_EPISODIOS_RSS_FILE"
fi

echo "📆 Renombrando descargas de hoy..."

START=$(date -d "$HOY 00:00:00" +%s)
END=$(date -d "$HOY 23:59:59" +%s)

# Construir lista de directorios gestionados por yt-dlp para excluirlos del renombrado
declare -A YTDLP_DIRS
_YTDLP_FEEDS="$CONFIG_DIR/ytdlp_feeds.txt"
if [[ -f "$_YTDLP_FEEDS" ]]; then
    while IFS= read -r _linea || [[ -n "$_linea" ]]; do
        [[ "$_linea" =~ ^[[:space:]]*# ]] && continue
        [[ -z "${_linea// /}" ]] && continue
        read -r _url _cat _nombre _max <<< "$_linea"
        [[ -z "$_nombre" ]] && continue
        if [[ "$_cat" == "-" || -z "$_cat" ]]; then
            YTDLP_DIRS["$PODCASTS_DIR/$_nombre"]=1
        else
            YTDLP_DIRS["$PODCASTS_DIR/$_cat/$_nombre"]=1
        fi
    done < "$_YTDLP_FEEDS"
fi

while IFS='|' read -r timestamp file; do
    ts=${timestamp%.*}
    if (( ts >= START && ts <= END )); then
        dir="$(dirname "$file")"

        # Saltarse archivos en directorios gestionados por yt-dlp
        if [[ "${YTDLP_DIRS[$dir]+isset}" ]]; then
            continue
        fi

        carpeta=$(basename "$dir")
        ext="${file##*.}"
        nuevo_nombre="${carpeta}${DIA}${MES}${ANO}.${ext}"
        nuevo_path="$dir/$nuevo_nombre"
        fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")

        if [[ "$file" != "$nuevo_path" ]]; then
            # 🗑️ Eliminar archivos de audio antiguos antes de renombrar (respetando max_episodios)
            _carpeta_nombre=$(basename "$dir")
            _max_ep_rename="${MAX_EPISODIOS_RSS[$_carpeta_nombre]:-1}"
            if (( _max_ep_rename <= 1 )); then
                # Comportamiento original: eliminar todos los anteriores (incluido nuevo_path si ya existe)
                while IFS= read -r antiguo; do
                    echo "  🗑️ Eliminando por reemplazo: $(basename "$antiguo")"
                    rm -f "$antiguo"
                    echo "$fecha_actual|$antiguo|REEMPLAZO" >> "$ELIMINADOS_HISTORICO"
                done < <(find "$dir" -maxdepth 1 -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) ! -samefile "$file")
            else
                # Mantener los N-1 más recientes (el nuevo será el Nth)
                mapfile -t _antiguos_arr < <(find "$dir" -maxdepth 1 -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) ! -samefile "$file" -printf "%T@|%p\n" | sort -n | awk -F'|' '{print $2}')
                _keep_count=$(( _max_ep_rename - 1 ))
                _total_antiguos=${#_antiguos_arr[@]}
                for (( _i=0; _i < _total_antiguos - _keep_count && _i < _total_antiguos; _i++ )); do
                    echo "  🗑️ Eliminando por reemplazo: $(basename "${_antiguos_arr[$_i]}")"
                    rm -f "${_antiguos_arr[$_i]}"
                    echo "$fecha_actual|${_antiguos_arr[$_i]}|REEMPLAZO" >> "$ELIMINADOS_HISTORICO"
                done
            fi

            # ✔ Renombrar archivo descargado (sobreescribe si ya existía uno anterior del mismo día)
            mv -f "$file" "$nuevo_path"
            echo "  ✔ Renombrado: $(basename "$file") → $nuevo_nombre"
            echo "$fecha_actual|$nuevo_path|RENOMBRADO" >> "$RENOMBRADOS_HISTORICO"
        fi
    fi
done < <(find "$PODCASTS_DIR" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n")

# --- LIMPIEZA POR CADUCIDAD ---
echo "🧹 Limpiando archivos por caducidad..."

declare -A CADUCIDADES

CADUCIDADES_FILE="$CONFIG_DIR/caducidades.txt"
DEFAULT_DIAS=30

if [[ -f "$CADUCIDADES_FILE" ]]; then
    while IFS=':' read -r carpeta dias; do
        read -r carpeta <<< "$carpeta"
        read -r dias <<< "$dias"
        if [[ -n "$carpeta" && "$dias" =~ ^[0-9]+$ ]]; then
            CADUCIDADES["$carpeta"]=$dias
        fi
    done < "$CADUCIDADES_FILE"
fi

while IFS= read -r subdir; do
    while IFS='|' read -r timestamp archivo; do
        ts=${timestamp%.*}
        # Extraer nombre del podcast quitando el sufijo de fecha (8 dígitos DDMMYYYY)
        nombre_base=$(basename "${archivo%.*}")
        nombre_podcast="${nombre_base%[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]}"
        dias_caducidad=${CADUCIDADES["$nombre_podcast"]:-$DEFAULT_DIAS}
        umbral_segundos=$(( now - dias_caducidad * 86400 ))
        if (( ts < umbral_segundos )); then
            echo "  🗑️ Eliminando por caducidad ($dias_caducidad días): $(basename "$archivo")"
            rm -f "$archivo"
            fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
            echo "$fecha_actual|$archivo|CADUCIDAD" >> "$ELIMINADOS_HISTORICO"
        fi
    done < <(find "$subdir" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" 2>/dev/null)
done < <(find "$PODCASTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)
# --- ELIMINAR ARCHIVOS ANTIGUOS, CONSERVANDO SOLO EL MÁS RECIENTE POR CARPETA ---
echo "🧹 Manteniendo solo el archivo más reciente por carpeta..."

_eliminar_antiguos_en_dir() {
    local dir="$1"
    local max_ep="${2:-1}"
    mapfile -t archivos < <(find "$dir" -maxdepth 1 -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) -printf "%T@|%p\n" | sort -n | awk -F'|' '{print $2}')
    local total=${#archivos[@]}
    if (( total > max_ep )); then
        local fecha_actual
        fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
        for (( i=0; i<total-max_ep; i++ )); do
            local archivo="${archivos[i]}"
            echo "  🗑️ Eliminando por antigüedad: $(basename "$archivo")"
            rm -f "$archivo"
            echo "$fecha_actual|$archivo|REEMPLAZO" >> "$ELIMINADOS_HISTORICO"
        done
    fi
}

while read -r subdir; do
    _nombre_subdir=$(basename "$subdir")
    _max_ep_subdir="${MAX_EPISODIOS_RSS[$_nombre_subdir]:-1}"
    if find "$subdir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | grep -q .; then
        # Tiene subcarpetas (categoría) → procesar cada podcast dentro
        while read -r nested; do
            _nombre_nested=$(basename "$nested")
            _max_ep_nested="${MAX_EPISODIOS_RSS[$_nombre_nested]:-1}"
            _eliminar_antiguos_en_dir "$nested" "$_max_ep_nested"
        done < <(find "$subdir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)
    else
        _eliminar_antiguos_en_dir "$subdir" "$_max_ep_subdir"
    fi
done < <(find "$PODCASTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)

# --- VERIFICACIÓN DE DURACIÓN EN CARPETAS ASIGNADAS ---
echo "⏱️ Verificando duración de archivos por carpeta..."

DURACIONES_FILE="$CONFIG_DIR/duraciones.txt"
declare -A MAPA_DURACIONES
declare -A MAPA_BASE_SEG
declare -A MAPA_MARGENES

# Duración base en segundos (sin margen)
MAPA_BASE_SEG["30M"]=1800     # 30 min
MAPA_BASE_SEG["1H"]=3600      # 60 min
MAPA_BASE_SEG["1H30"]=5400    # 90 min
MAPA_BASE_SEG["2H"]=7200      # 120 min
MAPA_BASE_SEG["2H30"]=9000    # 150 min
MAPA_BASE_SEG["3H"]=10800     # 180 min

# Leer archivo de configuración (formato: carpeta:clave  o  carpeta:clave:margen_min)
if [[ -f "$DURACIONES_FILE" ]]; then
    while IFS=':' read -r carpeta clave margen_campo; do
        read -r carpeta <<< "$carpeta"
        read -r clave <<< "$clave"
        margen_campo="${margen_campo:-5}"
        read -r margen_campo <<< "$margen_campo"
        if [[ -n "$carpeta" && -n "$clave" && -n "${MAPA_BASE_SEG[$clave]:-}" ]]; then
            margen_min=5
            if [[ "$margen_campo" =~ ^[0-9]+$ && "$margen_campo" -gt 0 ]]; then
                margen_min=$margen_campo
            fi
            MAPA_DURACIONES["$carpeta"]="${MAPA_BASE_SEG[$clave]}"
            MAPA_MARGENES["$carpeta"]=$margen_min
        fi
    done < "$DURACIONES_FILE"
fi

# Aplicar verificación por carpeta
_verificar_duracion_en_dir() {
    local dir="$1" etiqueta="$2" umbral="$3"
    find "$dir" -type f \( -iname "*.mp3" -o -iname "*.ogg" -o -iname "*.wav" \) | while read -r archivo; do
        duracion=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$archivo" 2>/dev/null | cut -d. -f1)
        if [[ -n "$duracion" && "$duracion" -gt "$umbral" ]]; then
            echo "⛔ $(basename "$archivo") en $etiqueta → ${duracion}s (excede)"
            fecha_actual=$(date +"%Y-%m-%d %H:%M:%S")
            echo "$fecha_actual|$archivo|EXCESO_DURACION" >> "$ELIMINADOS_HISTORICO"
            rm -f "$archivo"
        fi
    done
}

for carpeta in "${!MAPA_DURACIONES[@]}"; do
    base_seg=${MAPA_DURACIONES[$carpeta]}
    margen_min=${MAPA_MARGENES[$carpeta]:-5}
    umbral=$(( base_seg + margen_min * 60 ))

    ruta="$PODCASTS_DIR/$carpeta"
    [[ -d "$ruta" ]] || continue

    if find "$ruta" -mindepth 1 -maxdepth 1 -type d | grep -q .; then
        # Tiene subcarpetas → verificar cada una
        echo "📂 Verificando subcarpetas de $carpeta (límite: $((umbral/60)) min)"
        find "$ruta" -mindepth 1 -maxdepth 1 -type d | while read -r subdir; do
            _verificar_duracion_en_dir "$subdir" "$(basename "$subdir")" "$umbral"
        done
    else
        echo "📂 Verificando carpeta $carpeta (límite: $((umbral/60)) min)"
        _verificar_duracion_en_dir "$ruta" "$carpeta" "$umbral"
    fi
done
INFORME="$INFORMES_DIR/Informe_diario_${DIA}_${MES}_${ANO}.log"

{
    EMISORA_MAYUSCULA="$(tr '[:lower:]' '[:upper:]' <<< "${EMISORA:0:1}")${EMISORA:1}"
    MES_NOMBRE=$(LC_TIME=es_ES.UTF-8 date +"%B")
    echo "📻 Informe diario – Emisora: $EMISORA_MAYUSCULA"
    echo "🗓️  Fecha: $DIA de $MES_NOMBRE de $ANO"
    echo

    total_descargados=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1' "$RENOMBRADOS_HISTORICO" | wc -l)
    total_eliminados=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1' "$ELIMINADOS_HISTORICO" | wc -l)
    total_caducidad=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1 && $3 == "CADUCIDAD"' "$ELIMINADOS_HISTORICO" | wc -l)
    total_reemplazo=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1 && $3 == "REEMPLAZO"' "$ELIMINADOS_HISTORICO" | wc -l)

    total_ytdlp=$(awk -F'|' -v fecha="$HOY" 'index($1, fecha) == 1 && $3 == "YTDLP"' "$RENOMBRADOS_HISTORICO" | wc -l)
    total_rss=$(( total_descargados - total_ytdlp ))
    echo "• $total_descargados podcasts descargados ($total_rss vía RSS/podget, $total_ytdlp vía yt-dlp)"
    echo "• $total_eliminados archivos eliminados ($total_caducidad por caducidad, $total_reemplazo por reemplazo)"
    echo

    echo "🎷 Últimos podcasts descargados:"
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
                printf "%s\t%s\n" "$dias_vacio" "$carpeta"
            else
                printf "SIN_FECHA\t%s\n" "$carpeta"
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

    mostrar_playlists_vacias "$EMISORA"

    echo "✅ Finalizado correctamente."
} > "$INFORME"

echo
echo "✅ Finalizado correctamente."
exit 0
