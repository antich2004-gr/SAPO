#!/bin/bash
# =============================================================================
# preflight_check.sh - Comprobación previa a la instalación de SAPO
# =============================================================================
# Verifica que el servidor destino cumple todos los requisitos para instalar
# SAPO y que no habrá conflictos con la instalación actual de AzuraCast.
#
# Uso:
#   bash preflight_check.sh [--sapo-dir /ruta/a/sapo] [--base-path /mnt/emisoras]
#
# Opciones:
#   --sapo-dir     Ruta donde se instalará SAPO (default: /var/www/html/sapo)
#   --base-path    Directorio base de emisoras (default: /mnt/emisoras)
#   --api-url      URL base de la API de AzuraCast (default: autodetect)
#   --api-key      API key de AzuraCast para validar conexión
# =============================================================================

set -euo pipefail

# ─── COLORES Y UTILIDADES ────────────────────────────────────────────────────
RED='\033[0;31m'
GRN='\033[0;32m'
YLW='\033[1;33m'
BLU='\033[0;34m'
CYN='\033[0;36m'
BOLD='\033[1m'
RST='\033[0m'

PASS=0
WARN=0
FAIL=0

ok()   { echo -e "  ${GRN}[OK]${RST}   $1"; ((PASS++)); }
warn() { echo -e "  ${YLW}[WARN]${RST} $1"; ((WARN++)); }
fail() { echo -e "  ${RED}[FAIL]${RST} $1"; ((FAIL++)); }
info() { echo -e "  ${BLU}[INFO]${RST} $1"; }
h1()   { echo; echo -e "${BOLD}${CYN}━━━  $1  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RST}"; }
h2()   { echo -e "\n  ${BOLD}$1${RST}"; }

# ─── ARGUMENTOS ──────────────────────────────────────────────────────────────
SAPO_DIR="/var/www/html/sapo"
BASE_PATH="/mnt/emisoras"
API_URL=""
API_KEY=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --sapo-dir)   SAPO_DIR="$2";  shift 2 ;;
        --base-path)  BASE_PATH="$2"; shift 2 ;;
        --api-url)    API_URL="$2";   shift 2 ;;
        --api-key)    API_KEY="$2";   shift 2 ;;
        *) echo "Opción desconocida: $1"; exit 1 ;;
    esac
done

# ─── CABECERA ─────────────────────────────────────────────────────────────────
echo
echo -e "${BOLD}╔══════════════════════════════════════════════════════════════╗"
echo -e "║         SAPO - Preflight Check (Pre-instalación)            ║"
echo -e "╚══════════════════════════════════════════════════════════════╝${RST}"
echo -e "  Servidor  : $(hostname -f 2>/dev/null || hostname)"
echo -e "  Fecha     : $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo -e "  Usuario   : $(whoami) (uid=$(id -u))"
echo -e "  SAPO dir  : ${SAPO_DIR}"
echo -e "  Base path : ${BASE_PATH}"


# =============================================================================
h1 "SISTEMA OPERATIVO"
# =============================================================================

h2 "Distribución y kernel"
OS_NAME=$(grep PRETTY_NAME /etc/os-release 2>/dev/null | cut -d= -f2 | tr -d '"' || uname -s)
info "OS: $OS_NAME"
info "Kernel: $(uname -r)"

# Verificar que no estamos dentro de un contenedor Docker
if grep -qE '(docker|lxc)' /proc/1/cgroup 2>/dev/null || [ -f /.dockerenv ]; then
    warn "Ejecutando dentro de un contenedor. Asegúrate de que este es el contenedor correcto (host de AzuraCast o el servidor web)"
else
    ok "No se detecta entorno contenedor (ejecución en host)"
fi

h2 "Timezone"
TZ_CURRENT=$(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null || echo "desconocido")
if [[ "$TZ_CURRENT" == "Europe/Madrid" ]]; then
    ok "Timezone: Europe/Madrid"
else
    warn "Timezone actual: '${TZ_CURRENT}'. SAPO usa Europe/Madrid internamente. Verifica que las horas de parrilla son correctas."
fi

h2 "Locale"
LOCALE_OK=false
for loc in es_ES.UTF-8 es_ES.utf8; do
    if locale -a 2>/dev/null | grep -qi "^${loc}$"; then
        LOCALE_OK=true; break
    fi
done
if $LOCALE_OK; then
    ok "Locale es_ES.UTF-8 disponible"
else
    warn "Locale es_ES.UTF-8 no encontrado. Algunos textos con caracteres especiales pueden fallar."
fi


# =============================================================================
h1 "BINARIOS REQUERIDOS"
# =============================================================================

h2 "Herramientas obligatorias"
check_bin() {
    local bin="$1"
    local required="${2:-required}"
    if command -v "$bin" &>/dev/null; then
        BIN_VER=$(timeout 2 "$bin" --version 2>&1 | head -1 || echo 'ok')
        ok "${bin}: $(command -v "$bin") (${BIN_VER})"
    elif [[ "$required" == "optional" ]]; then
        warn "${bin}: no encontrado (opcional, algunas funciones no estarán disponibles)"
    else
        fail "${bin}: no encontrado — REQUERIDO"
    fi
}

check_bin php
# Verificar versión mínima PHP 7.4
if command -v php &>/dev/null; then
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null)
    PHP_MAJOR=$(echo "$PHP_VER" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VER" | cut -d. -f2)
    if [[ "$PHP_MAJOR" -ge 8 ]] || [[ "$PHP_MAJOR" -eq 7 && "$PHP_MINOR" -ge 4 ]]; then
        ok "PHP versión: ${PHP_VER} (mínimo requerido: 7.4)"
    else
        fail "PHP versión ${PHP_VER} demasiado antigua. Se requiere >= 7.4"
    fi
fi

check_bin curl
check_bin jq
check_bin ffprobe
check_bin nohup
check_bin stdbuf

h2 "Herramientas opcionales"
check_bin podget optional
check_bin yt-dlp optional
check_bin ffmpeg optional

h2 "PHP — funciones requeridas"
if command -v php &>/dev/null; then
    for func in exec shell_exec file_get_contents json_encode json_decode flock; do
        if php -r "echo function_exists('${func}') ? 'ok' : 'fail';" 2>/dev/null | grep -q 'ok'; then
            ok "PHP función ${func}(): habilitada"
        else
            fail "PHP función ${func}(): DESHABILITADA (revisar disable_functions en php.ini)"
        fi
    done

    h2 "PHP — extensiones requeridas"
    for ext in json mbstring curl openssl; do
        if php -m 2>/dev/null | grep -qi "^${ext}$"; then
            ok "PHP extensión ${ext}: cargada"
        else
            fail "PHP extensión ${ext}: no encontrada"
        fi
    done

    h2 "PHP — configuración relevante"
    EXEC_DISABLED=$(php -r 'echo ini_get("disable_functions");' 2>/dev/null)
    if echo "$EXEC_DISABLED" | grep -q 'exec'; then
        fail "exec() está en disable_functions: ${EXEC_DISABLED}"
    else
        ok "exec() no está deshabilitada en disable_functions"
    fi

    MAX_UPLOAD=$(php -r 'echo ini_get("upload_max_filesize");' 2>/dev/null)
    if [[ "$MAX_UPLOAD" =~ ^([0-9]+)M$ ]] && [[ "${BASH_REMATCH[1]}" -ge 10 ]]; then
        ok "upload_max_filesize: ${MAX_UPLOAD}"
    else
        warn "upload_max_filesize: ${MAX_UPLOAD} — SAPO espera al menos 10M"
    fi
fi


# =============================================================================
h1 "SERVIDOR WEB"
# =============================================================================

h2 "Apache"
if command -v apache2 &>/dev/null || command -v httpd &>/dev/null; then
    ok "Apache encontrado"

    # Verificar módulos necesarios
    for mod in rewrite headers; do
        if apache2ctl -M 2>/dev/null | grep -q "${mod}_module" || \
           httpd -M 2>/dev/null | grep -q "${mod}_module"; then
            ok "Apache módulo mod_${mod}: activo"
        else
            fail "Apache módulo mod_${mod}: NO activo (requerido para .htaccess de SAPO)"
        fi
    done

    # AllowOverride
    CONF_FILES=$(find /etc/apache2 /etc/httpd -name "*.conf" 2>/dev/null | head -20)
    if echo "$CONF_FILES" | xargs grep -l "AllowOverride All" 2>/dev/null | head -1 | grep -q .; then
        ok "AllowOverride All encontrado en alguna configuración de Apache"
    else
        warn "No se encontró 'AllowOverride All' en configuraciones Apache. El .htaccess de SAPO no funcionará sin esto."
    fi
else
    warn "Apache no encontrado. Si usas Nginx, deberás replicar manualmente las reglas de .htaccess"

    if command -v nginx &>/dev/null; then
        info "Nginx detectado: $(nginx -v 2>&1)"
        warn "Con Nginx necesitas configurar manualmente: bloqueo de /db/, /includes/, archivos .json directos, y mod_rewrite equivalente"
    fi
fi

h2 "Directorio de instalación SAPO"
PARENT_DIR=$(dirname "$SAPO_DIR")
if [[ -d "$SAPO_DIR" ]]; then
    warn "El directorio ${SAPO_DIR} ya existe — posible instalación previa o conflicto"
    if [[ -f "${SAPO_DIR}/config.php" ]]; then
        EXISTING_VER=$(grep "SAPO_VERSION'" "${SAPO_DIR}/config.php" 2>/dev/null | grep -oP "[\d.]+" | head -1)
        warn "Instalación SAPO existente detectada (v${EXISTING_VER:-?}) en ${SAPO_DIR}"
    fi
elif [[ -d "$PARENT_DIR" ]] && [[ -w "$PARENT_DIR" ]]; then
    ok "Directorio padre ${PARENT_DIR} existe y es escribible"
else
    fail "El directorio padre ${PARENT_DIR} no existe o no es escribible"
fi


# =============================================================================
h1 "AZURACAST"
# =============================================================================

h2 "Instalación de AzuraCast"
AZURA_FOUND=false

# Buscar AzuraCast por rutas comunes
for azura_path in /var/azuracast /home/azuracast /opt/azuracast; do
    if [[ -d "$azura_path" ]]; then
        ok "AzuraCast encontrado en: ${azura_path}"
        AZURA_FOUND=true

        # Verificar subdirectorios relevantes
        for subdir in stations; do
            if [[ -d "${azura_path}/${subdir}" ]]; then
                ok "  Directorio ${azura_path}/${subdir} existe"
            else
                warn "  Directorio ${azura_path}/${subdir} NO existe"
            fi
        done
        break
    fi
done

if ! $AZURA_FOUND; then
    warn "No se detectó AzuraCast en rutas estándar (/var/azuracast, /home/azuracast, /opt/azuracast)"
fi

# Docker AzuraCast
if command -v docker &>/dev/null; then
    if docker ps 2>/dev/null | grep -qi "azuracast"; then
        ok "Contenedor AzuraCast activo en Docker"
        info "Contenedores AzuraCast: $(docker ps --format '{{.Names}}' 2>/dev/null | grep -i azura | tr '\n' ' ')"
    else
        info "Docker disponible pero sin contenedores AzuraCast activos"
    fi
fi

h2 "Conectividad API de AzuraCast"

# Autodetectar URL si no se proporcionó
if [[ -z "$API_URL" ]]; then
    # Intentar leer de una instalación SAPO existente en paths comunes
    for cfg in /var/www/html/sapo/db/global.json /var/www/html/cliente_rrll/../db/global.json \
               /home/user/SAPO/db/global.json; do
        if [[ -f "$cfg" ]]; then
            DETECTED_URL=$(jq -r '.azuracast_api_url // empty' "$cfg" 2>/dev/null)
            if [[ -n "$DETECTED_URL" ]]; then
                API_URL="$DETECTED_URL"
                info "API URL autodetectada desde ${cfg}: ${API_URL}"
                break
            fi
        fi
    done
fi

if [[ -n "$API_URL" ]]; then
    # Verificar que la URL responde
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${API_URL}/status" 2>/dev/null || echo "000")
    if [[ "$HTTP_CODE" == "200" ]]; then
        ok "API AzuraCast responde: ${API_URL}/status → HTTP ${HTTP_CODE}"
    elif [[ "$HTTP_CODE" == "401" || "$HTTP_CODE" == "403" ]]; then
        ok "API AzuraCast accesible (HTTP ${HTTP_CODE} — se necesita API key)"
    else
        fail "API AzuraCast no responde en ${API_URL}/status (HTTP ${HTTP_CODE})"
    fi

    if [[ -n "$API_KEY" ]]; then
        # Verificar autenticación
        AUTH_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 \
            -H "X-API-Key: ${API_KEY}" "${API_URL}/admin/stations" 2>/dev/null || echo "000")
        if [[ "$AUTH_CODE" == "200" ]]; then
            ok "API Key válida — acceso a /admin/stations correcto"
            # Listar estaciones
            STATIONS=$(curl -s --max-time 10 -H "X-API-Key: ${API_KEY}" \
                "${API_URL}/admin/stations" 2>/dev/null | jq -r '.[] | "    ID:\(.id) - \(.name)"' 2>/dev/null || true)
            if [[ -n "$STATIONS" ]]; then
                info "Estaciones disponibles:"
                echo "$STATIONS" | while read -r l; do info "$l"; done
            fi
        elif [[ "$AUTH_CODE" == "403" ]]; then
            fail "API Key inválida o sin permisos de administrador (HTTP 403)"
        else
            warn "Respuesta inesperada al validar API Key: HTTP ${AUTH_CODE}"
        fi
    else
        warn "No se proporcionó --api-key, no se puede validar autenticación"
    fi
else
    warn "No se pudo determinar la URL de la API de AzuraCast. Usa --api-url para especificarla."
fi

h2 "Rutas de grabaciones AzuraCast"
AZURA_STATIONS_PATH=""
for p in /var/azuracast/stations /home/azuracast/stations; do
    if [[ -d "$p" ]]; then
        AZURA_STATIONS_PATH="$p"
        break
    fi
done

if [[ -n "$AZURA_STATIONS_PATH" ]]; then
    info "Estaciones en filesystem: $(ls "$AZURA_STATIONS_PATH" 2>/dev/null | tr '\n' '  ')"
    for station_dir in "${AZURA_STATIONS_PATH}"/*/; do
        station_name=$(basename "$station_dir")
        [[ "$station_name" == "*" ]] && break
        for subdir in recordings media config; do
            if [[ -d "${station_dir}${subdir}" ]]; then
                WRITABLE=""
                [[ -w "${station_dir}${subdir}" ]] && WRITABLE=" (escribible)" || WRITABLE=" (SOLO LECTURA)"
                ok "  ${station_dir}${subdir}${WRITABLE}"
            else
                warn "  ${station_dir}${subdir} — no existe"
            fi
        done
    done
else
    warn "No se encontró directorio de estaciones AzuraCast para verificar rutas de grabaciones"
fi


# =============================================================================
h1 "DIRECTORIOS DE EMISORAS (BASE_PATH)"
# =============================================================================

h2 "Ruta base: ${BASE_PATH}"
if [[ -d "$BASE_PATH" ]]; then
    ok "Directorio base ${BASE_PATH} existe"
    WRITABLE_BASE=""
    [[ -w "$BASE_PATH" ]] && ok "${BASE_PATH} es escribible" || warn "${BASE_PATH} NO es escribible por el usuario actual ($(whoami))"

    # Listar emisoras y verificar subdirectorios esperados
    EMISORAS=$(ls "$BASE_PATH" 2>/dev/null)
    if [[ -n "$EMISORAS" ]]; then
        info "Emisoras encontradas:"
        for emisora in $EMISORAS; do
            EMISORA_PATH="${BASE_PATH}/${emisora}"
            [[ ! -d "$EMISORA_PATH" ]] && continue
            info "  → ${emisora}"
            for subdir in "media" "media/Suscripciones" "media/Podcasts" "media/Informes"; do
                FULL="${EMISORA_PATH}/${subdir}"
                if [[ -d "$FULL" ]]; then
                    W=""
                    [[ -w "$FULL" ]] && W="${GRN}rw${RST}" || W="${YLW}ro${RST}"
                    echo -e "      ${GRN}[OK]${RST}   ${subdir} [${W}]"
                    ((PASS++))
                else
                    echo -e "      ${YLW}[WARN]${RST} ${subdir} — no existe (se creará en primer uso)"
                    ((WARN++))
                fi
            done
        done
    else
        warn "No hay subdirectorios en ${BASE_PATH} (no se han configurado emisoras aún)"
    fi
else
    fail "Directorio base ${BASE_PATH} NO existe"
    warn "SAPO necesita este directorio para almacenar suscripciones, podcasts e informes"
fi


# =============================================================================
h1 "SISTEMA DE ARCHIVOS — SAPO"
# =============================================================================

h2 "Directorios que SAPO debe poder escribir"
SAPO_WRITABLE_DIRS=(
    "${SAPO_DIR}/logs"
    "${SAPO_DIR}/cache"
    "${SAPO_DIR}/data"
    "${SAPO_DIR}/db"
)

if [[ -d "$SAPO_DIR" ]]; then
    for dir in "${SAPO_WRITABLE_DIRS[@]}"; do
        if [[ -d "$dir" ]]; then
            if [[ -w "$dir" ]]; then
                ok "${dir}: existe y es escribible"
            else
                fail "${dir}: existe pero NO es escribible"
            fi
        else
            info "${dir}: no existe aún (se creará en la instalación)"
        fi
    done
else
    info "SAPO aún no está instalado en ${SAPO_DIR} — se verificará tras la instalación"
fi

h2 "Conflictos con archivos de configuración existentes"
CONFLICT_FILES=(
    "${SAPO_DIR}/db/global.json"
    "${SAPO_DIR}/db.json"
)
for f in "${CONFLICT_FILES[@]}"; do
    if [[ -f "$f" ]]; then
        warn "Archivo existente que podría sobreescribirse: ${f}"
        info "  Tamaño: $(du -sh "$f" 2>/dev/null | cut -f1), Modificado: $(stat -c '%y' "$f" 2>/dev/null | cut -d. -f1)"
    fi
done

h2 "Espacio en disco"
for path in "${SAPO_DIR%/*}" "${BASE_PATH}"; do
    [[ -d "$path" ]] || continue
    DF=$(df -h "$path" 2>/dev/null | tail -1)
    AVAIL=$(echo "$DF" | awk '{print $4}')
    USE_PCT=$(echo "$DF" | awk '{print $5}' | tr -d '%')
    if [[ "$USE_PCT" -ge 90 ]]; then
        fail "Disco casi lleno (${USE_PCT}%) en ${path} — disponible: ${AVAIL}"
    elif [[ "$USE_PCT" -ge 75 ]]; then
        warn "Disco al ${USE_PCT}% en ${path} — disponible: ${AVAIL}"
    else
        ok "Espacio en ${path}: ${AVAIL} disponibles (${USE_PCT}% usado)"
    fi
done


# =============================================================================
h1 "CRON"
# =============================================================================

h2 "Acceso a crontab"
if crontab -l &>/dev/null; then
    EXISTING_CRON=$(crontab -l 2>/dev/null)
    if echo "$EXISTING_CRON" | grep -q "sapo\|cliente_rrll\|cleanup_recordings"; then
        warn "Ya existe una entrada de cron relacionada con SAPO:"
        echo "$EXISTING_CRON" | grep "sapo\|cliente_rrll\|cleanup_recordings" | while read -r l; do
            info "  $l"
        done
        warn "Revisa que no haya duplicados al instalar"
    else
        ok "No hay entradas SAPO en crontab actual"
    fi
else
    info "No hay crontab activo para $(whoami)"
fi

h2 "Cron del sistema (/etc/cron.d/)"
if ls /etc/cron.d/ 2>/dev/null | grep -qi "sapo"; then
    warn "Existe un archivo cron en /etc/cron.d/ relacionado con SAPO:"
    ls /etc/cron.d/ | grep -i "sapo" | while read -r f; do
        info "  /etc/cron.d/${f}"
    done
else
    ok "No hay cron de SAPO en /etc/cron.d/"
fi


# =============================================================================
h1 "RED Y PUERTOS"
# =============================================================================

h2 "Puertos en escucha relevantes"
for port in 80 443 8080 8000; do
    if ss -tlnp 2>/dev/null | grep -q ":${port} " || \
       netstat -tlnp 2>/dev/null | grep -q ":${port} "; then
        info "Puerto ${port}: en uso $(ss -tlnp 2>/dev/null | grep ":${port} " | awk '{print $NF}' | head -1)"
    else
        info "Puerto ${port}: libre"
    fi
done

h2 "Conectividad saliente (RSS y descargas)"
for test_host in "github.com" "feeds.acast.com"; do
    if curl -s --max-time 5 -o /dev/null "https://${test_host}" 2>/dev/null; then
        ok "Acceso saliente HTTPS a ${test_host}"
    else
        warn "Sin acceso a ${test_host} — las suscripciones RSS externas podrían no funcionar"
    fi
done


# =============================================================================
h1 "POSIBLES CONFLICTOS CON INSTALACIÓN ORIGEN"
# =============================================================================

h2 "Verificación de rutas hardcoded en scripts SAPO"
# Si el usuario tiene la instalación origen en /home/user/SAPO, advertir
if [[ -f /home/user/SAPO/cliente_rrll/cliente_rrll.sh ]]; then
    info "Instalación origen detectada en /home/user/SAPO"

    # Buscar si el script usa rutas absolutas hardcoded
    HC_PATHS=$(grep -Eo '/[a-z/]+sapo[a-z/_.-]*' /home/user/SAPO/cliente_rrll/cliente_rrll.sh 2>/dev/null | sort -u || true)
    if [[ -n "$HC_PATHS" ]]; then
        warn "Posibles rutas absolutas hardcoded en cliente_rrll.sh:"
        echo "$HC_PATHS" | while read -r p; do info "  $p"; done
    else
        ok "No se detectan rutas absolutas hardcoded en cliente_rrll.sh"
    fi
fi

h2 "Variables de entorno críticas"
for var in AZURACAST_API_KEY AZURACAST_API_URL SAPO_LOG_FILE; do
    if [[ -n "${!var:-}" ]]; then
        ok "${var}: definida"
    else
        info "${var}: no definida en entorno actual (se configura desde SAPO)"
    fi
done

h2 "Lock files de instalación origen que podrían interferir"
LOCK_FILES=$(ls /tmp/cliente_descarga_*.lock 2>/dev/null || true)
if [[ -n "$LOCK_FILES" ]]; then
    warn "Lock files activos en /tmp:"
    echo "$LOCK_FILES" | while read -r f; do
        LOCK_AGE=$(( ( $(date +%s) - $(stat -c %Y "$f" 2>/dev/null || echo 0) ) / 60 ))
        warn "  $f (hace ${LOCK_AGE} min)"
    done
    warn "Si estos locks son del servidor origen replicado, elimínalos antes de iniciar SAPO en este servidor"
else
    ok "No hay lock files de SAPO en /tmp"
fi


# =============================================================================
h1 "RESUMEN FINAL"
# =============================================================================

echo
TOTAL=$((PASS + WARN + FAIL))
echo -e "  Comprobaciones totales : ${TOTAL}"
echo -e "  ${GRN}Correctas${RST}              : ${PASS}"
echo -e "  ${YLW}Advertencias${RST}           : ${WARN}"
echo -e "  ${RED}Fallos${RST}                 : ${FAIL}"
echo

if [[ $FAIL -gt 0 ]]; then
    echo -e "  ${RED}${BOLD}✗ NO PROCEDER con la instalación hasta resolver los ${FAIL} fallo(s) indicados.${RST}"
    echo
    EXIT_CODE=2
elif [[ $WARN -gt 0 ]]; then
    echo -e "  ${YLW}${BOLD}⚠ Instalación posible pero revisa las ${WARN} advertencia(s) antes de continuar.${RST}"
    echo
    EXIT_CODE=1
else
    echo -e "  ${GRN}${BOLD}✓ Sistema listo para instalar SAPO. No se detectaron problemas.${RST}"
    echo
    EXIT_CODE=0
fi

echo -e "  ${BLU}Próximos pasos recomendados:${RST}"
echo "  1. Clonar el repositorio en ${SAPO_DIR}"
echo "  2. Configurar el vhost Apache/Nginx apuntando a ${SAPO_DIR}"
echo "  3. Acceder al panel de administración y configurar la API de AzuraCast"
echo "  4. Configurar la base path de emisoras (${BASE_PATH})"
echo "  5. Añadir la entrada de cron para cleanup y cliente_rrll.sh --runner"
echo

exit $EXIT_CODE
