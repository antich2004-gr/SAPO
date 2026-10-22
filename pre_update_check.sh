#!/bin/bash
# =============================================================================
# pre_update_check.sh - Auditoría previa a actualizar AzuraCast (SAPO + Archikast)
# =============================================================================
# Revisa SIN MODIFICAR NADA (salvo --backup) cómo está montado el servidor:
#   - Docker / AzuraCast (versión, canal, volúmenes, override, espacio)
#   - Dónde viven SAPO y Archikast y si SOBREVIVEN a `docker.sh update`
#   - Cron, PHP, permisos y rutas que usan
#   - API de AzuraCast: clave válida y todos los endpoints que usa SAPO
#   - custom_config de Liquidsoap por emisora (el punto más frágil: Liquidsoap 2.4)
# Al final da un veredicto: SEGURO / REVISAR / NO ACTUALIZAR.
#
# Uso (en el HOST, como root):
#   bash pre_update_check.sh [opciones]
#
# Opciones:
#   --azuracast-dir DIR   Instalación de AzuraCast (default: /var/azuracast)
#   --sapo-dir DIR        Ruta de SAPO (default: autodetección)
#   --archikast-dir DIR   Ruta de Archikast (default: autodetección)
#   --api-url URL         URL de la API, p.ej. https://host/api (default: la de SAPO)
#   --api-key KEY         API key de AzuraCast con permisos de admin (default: la de SAPO)
#   --out DIR             Dónde guardar el informe y los custom_config exportados
#                         (default: ./pre_update_report_FECHA)
#   --backup              Además: copia SAPO/Archikast y lanza el backup nativo de AzuraCast
#
# Código de salida: 0 = seguro, 1 = revisar avisos, 2 = hay fallos críticos
# =============================================================================

set -uo pipefail

RED='\033[0;31m'; GRN='\033[0;32m'; YLW='\033[1;33m'; BLU='\033[0;34m'
CYN='\033[0;36m'; BOLD='\033[1m'; RST='\033[0m'
PASS=0; WARN=0; FAIL=0

ok()   { echo -e "  ${GRN}[OK]${RST}   $1"; PASS=$((PASS+1)); }
warn() { echo -e "  ${YLW}[WARN]${RST} $1"; WARN=$((WARN+1)); }
fail() { echo -e "  ${RED}[FAIL]${RST} $1"; FAIL=$((FAIL+1)); }
info() { echo -e "  ${BLU}[INFO]${RST} $1"; }
h1()   { echo; echo -e "${BOLD}${CYN}━━━  $1  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RST}"; }

AZ_DIR="/var/azuracast"
SAPO_DIR=""; ARCH_DIR=""
API_URL=""; API_KEY=""
OUT=""; DO_BACKUP=0

while [[ $# -gt 0 ]]; do
    case $1 in
        --azuracast-dir) AZ_DIR="$2"; shift 2 ;;
        --sapo-dir)      SAPO_DIR="$2"; shift 2 ;;
        --archikast-dir) ARCH_DIR="$2"; shift 2 ;;
        --api-url)       API_URL="$2"; shift 2 ;;
        --api-key)       API_KEY="$2"; shift 2 ;;
        --out)           OUT="$2"; shift 2 ;;
        --backup)        DO_BACKUP=1; shift ;;
        -h|--help)       sed -n 2,32p "$0"; exit 0 ;;
        *) echo "Opción desconocida: $1"; exit 1 ;;
    esac
done

[[ -z "$OUT" ]] && OUT="./pre_update_report_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$OUT" || { echo "No puedo crear $OUT"; exit 1; }
# Todo lo que sale por pantalla se guarda también en el informe
exec > >(tee "$OUT/informe.txt") 2>&1

echo -e "${BOLD}Auditoría previa a la actualización de AzuraCast - $(date '+%F %T')${RST}"
echo "Informe y ficheros exportados en: $OUT"

have() { command -v "$1" >/dev/null 2>&1; }

# ─── 1. HOST Y DOCKER ────────────────────────────────────────────────────────
h1 "1. Host y Docker"
[[ $EUID -eq 0 ]] && ok "Ejecutado como root" || warn "No eres root: algunas comprobaciones (docker, cron, rutas) pueden fallar"

if have docker; then
    ok "Docker: $(docker --version 2>/dev/null)"
else
    fail "Docker no está disponible en este entorno (¿estás dentro del contenedor? ejecútalo en el HOST)"
    echo; echo "No se puede continuar sin Docker."; exit 2
fi
if docker compose version >/dev/null 2>&1; then ok "docker compose: $(docker compose version --short 2>/dev/null)"
elif have docker-compose; then ok "docker-compose: $(docker-compose --version)"
else warn "No se encuentra docker compose (docker.sh lo necesita)"; fi
have jq || warn "jq no instalado: se omitirá el análisis de la API/custom_config (apt install jq)"
have curl || fail "curl no instalado"

# Espacio en disco (la actualización descarga imágenes de varios GB)
dockroot=$(docker info -f '{{.DockerRootDir}}' 2>/dev/null || echo /var/lib/docker)
avail_gb=$(df -BG --output=avail "$dockroot" 2>/dev/null | tail -1 | tr -dc '0-9')
if [[ -n "$avail_gb" ]]; then
    if   (( avail_gb < 5 ));  then fail "Solo ${avail_gb} GB libres en $dockroot (la imagen nueva necesita >5 GB)"
    elif (( avail_gb < 10 )); then warn "${avail_gb} GB libres en $dockroot (justo)"
    else ok "${avail_gb} GB libres en $dockroot"; fi
fi
mem_mb=$(awk '/MemAvailable/ {print int($2/1024)}' /proc/meminfo 2>/dev/null)
[[ -n "$mem_mb" ]] && info "Memoria disponible: ${mem_mb} MB"

# ─── 2. AZURACAST ────────────────────────────────────────────────────────────
LQ24=0
h1 "2. AzuraCast"
if [[ -f "$AZ_DIR/docker-compose.yml" ]]; then ok "Instalación encontrada en $AZ_DIR"
else fail "No existe $AZ_DIR/docker-compose.yml (usa --azuracast-dir)"; fi

C=$(docker ps --format '{{.Names}} {{.Image}}' 2>/dev/null | awk '/azuracast\/azuracast|azuracast_web|^azuracast /{print $1; exit}')
if [[ -z "$C" ]]; then
    fail "No hay contenedor AzuraCast en ejecución"
else
    ok "Contenedor: $C  (imagen: $(docker inspect -f '{{.Config.Image}}' "$C"))"
    img_id=$(docker inspect -f '{{.Image}}' "$C" | cut -c1-19)
    info "ID de imagen actual (anótalo para poder volver atrás): $img_id"
    docker inspect -f '{{json .Config.Labels}}' "$C" > "$OUT/container_labels.json" 2>/dev/null
fi

if [[ -f "$AZ_DIR/.env" ]]; then
    chan=$(grep -E '^AZURACAST_VERSION=' "$AZ_DIR/.env" | cut -d= -f2)
    info "Canal de release (.env AZURACAST_VERSION): ${chan:-no definido (latest)}"
    cp "$AZ_DIR/.env" "$OUT/env.copy" 2>/dev/null
    sed -i -E 's/(PASSWORD|SECRET|KEY)=.*/\1=***/' "$OUT/env.copy" 2>/dev/null
fi

if [[ -n "$C" ]]; then
    az_ver=$(docker exec "$C" sh -c 'grep -hE "FALLBACK_VERSION|const .*VERSION" /var/azuracast/www/src/Version.php 2>/dev/null | head -2; cat /var/azuracast/www/.git-version 2>/dev/null' 2>/dev/null | tr -s ' \n' ' ')
    [[ -n "$az_ver" ]] && info "Aplicación: $az_ver"
    lq=$(docker exec "$C" sh -c 'liquidsoap --version 2>/dev/null | head -1' 2>/dev/null)
    echo "$lq" | grep -qE 'Liquidsoap 2\.([4-9]|[1-9][0-9])' && LQ24=1
    [[ -n "$lq" ]] && info "Liquidsoap actual: $lq" || info "Liquidsoap: no se pudo leer la versión"
    phpv=$(docker exec "$C" php -r 'echo PHP_VERSION;' 2>/dev/null)
    [[ -n "$phpv" ]] && info "PHP dentro del contenedor: $phpv  (puede cambiar con la actualización)"
fi

for f in docker-compose.override.yml docker-compose.new.yml; do
    if [[ -f "$AZ_DIR/$f" ]]; then
        warn "Existe $f (personalizaciones propias; revísalas tras actualizar):"
        sed 's/^/        /' "$AZ_DIR/$f" | head -40
        cp "$AZ_DIR/$f" "$OUT/" 2>/dev/null
    fi
done
[[ -f "$AZ_DIR/docker-compose.yml" ]] && cp "$AZ_DIR/docker-compose.yml" "$OUT/docker-compose.yml.copy"

# Último backup nativo
if [[ -n "$C" ]]; then
    lastbk=$(docker exec "$C" sh -c 'ls -t /var/azuracast/backups 2>/dev/null | head -1')
    [[ -n "$lastbk" ]] && info "Último backup nativo de AzuraCast: $lastbk" || warn "No hay backups nativos en /var/azuracast/backups"
fi

# ─── 3. VOLÚMENES Y PERSISTENCIA ─────────────────────────────────────────────
h1 "3. Volúmenes (qué sobrevive a la actualización)"
MOUNTS=""
if [[ -n "$C" ]]; then
    MOUNTS=$(docker inspect -f '{{range .Mounts}}{{.Destination}}|{{.Source}}|{{.Type}}{{"\n"}}{{end}}' "$C" 2>/dev/null)
    echo "$MOUNTS" > "$OUT/mounts.txt"
    echo "$MOUNTS" | awk -F'|' 'NF>=3{printf "        %-45s <- %s (%s)\n",$1,$2,$3}'
fi

# ¿Esta ruta DENTRO del contenedor está en un volumen/bind? Devuelve el origen o vacío.
persist_src() {
    local p="$1" best="" bsrc=""
    while IFS='|' read -r dest src typ; do
        [[ -z "$dest" ]] && continue
        if [[ "$p" == "$dest" || "$p" == "$dest"/* ]]; then
            (( ${#dest} > ${#best} )) && { best="$dest"; bsrc="$src"; }
        fi
    done <<< "$MOUNTS"
    echo "$bsrc"
}

# Comprueba una app (SAPO/Archikast) ya localizada. $1 nombre, $2 ruta, $3 'container'|'host'
check_persistence() {
    local name="$1" path="$2" where="$3"
    if [[ "$where" == "host" ]]; then
        ok "$name está en el HOST ($path): la actualización de Docker no lo toca"
        return
    fi
    local src; src=$(persist_src "$path")
    if [[ -n "$src" ]]; then
        ok "$name ($path) está en un volumen persistente -> $src"
    else
        fail "$name ($path) está en la capa EFÍMERA del contenedor: docker.sh update lo BORRARÁ. Móntalo como volumen en docker-compose.override.yml o muévelo al host"
    fi
}

# ─── 4. LOCALIZAR SAPO ───────────────────────────────────────────────────────
h1 "4. SAPO"
SAPO_WHERE=""
is_sapo_host() { [[ -f "$1/version.php" && -f "$1/includes/azuracast.php" ]]; }
is_sapo_cont() { docker exec "$C" sh -c "[ -f '$1/version.php' ] && [ -f '$1/includes/azuracast.php' ]" 2>/dev/null; }

if [[ -n "$SAPO_DIR" ]]; then
    if is_sapo_host "$SAPO_DIR"; then SAPO_WHERE=host
    elif [[ -n "$C" ]] && is_sapo_cont "$SAPO_DIR"; then SAPO_WHERE=container; fi
else
    for d in /var/www/html/sapo /var/www/sapo /opt/sapo "$AZ_DIR/www/web/sapo" "$AZ_DIR/sapo"; do
        if is_sapo_host "$d"; then SAPO_DIR="$d"; SAPO_WHERE=host; break; fi
    done
    if [[ -z "$SAPO_WHERE" && -n "$C" ]]; then
        for d in /var/azuracast/www/web/sapo /var/azuracast/sapo /var/www/html/sapo; do
            if is_sapo_cont "$d"; then SAPO_DIR="$d"; SAPO_WHERE=container; break; fi
        done
    fi
fi

SAPO_EXEC() { if [[ "$SAPO_WHERE" == container ]]; then docker exec "$C" sh -c "$1" 2>/dev/null; else sh -c "$1" 2>/dev/null; fi; }

if [[ -z "$SAPO_WHERE" ]]; then
    warn "No se encontró SAPO (usa --sapo-dir). Se omiten las comprobaciones de SAPO"
else
    ok "SAPO localizado en $SAPO_DIR (${SAPO_WHERE})"
    check_persistence "SAPO" "$SAPO_DIR" "$SAPO_WHERE"
    sv=$(SAPO_EXEC "grep -E \"define\\('SAPO_VERSION'\" '$SAPO_DIR/config.php' | head -1")
    [[ -n "$sv" ]] && info "Versión de SAPO: $sv"
    [[ -d "$SAPO_DIR/.git" || "$SAPO_WHERE" == container ]] && info "Rama/commit: $(SAPO_EXEC "cd '$SAPO_DIR' && git rev-parse --abbrev-ref HEAD && git rev-parse --short HEAD" | tr '\n' ' ')"

    # Datos que hay que conservar
    for sub in db data cache logs; do
        if SAPO_EXEC "[ -d '$SAPO_DIR/$sub' ]" ; then
            sz=$(SAPO_EXEC "du -sh '$SAPO_DIR/$sub' | cut -f1")
            if [[ "$SAPO_WHERE" == container ]]; then
                src=$(persist_src "$SAPO_DIR/$sub"); [[ -z "$src" ]] && fail "SAPO/$sub ($sz) NO es persistente" || ok "SAPO/$sub ($sz) persistente"
            else ok "SAPO/$sub ($sz)"; fi
        fi
    done

    # PHP que usa SAPO
    if [[ "$SAPO_WHERE" == container ]]; then
        miss=""
        for ext in curl json mbstring xml zip; do
            docker exec "$C" php -m 2>/dev/null | grep -qi "^$ext$" || miss="$miss $ext"
        done
        [[ -z "$miss" ]] && ok "Extensiones PHP básicas presentes en el contenedor" || warn "Faltan extensiones PHP:$miss (re-verifica tras actualizar)"
    fi

    # Dependencias de binarios que SAPO invoca
    for b in ffmpeg ffprobe jq curl; do
        SAPO_EXEC "command -v $b" >/dev/null && ok "Binario '$b' disponible donde corre SAPO" || { [[ "$b" == jq ]] && info "Binario 'jq' no está en el contenedor (OK si los scripts bash corren en el host)" || warn "Binario '$b' NO disponible donde corre SAPO"; }
    done

    # Credenciales de la API
    for gj in "$SAPO_DIR/db/global.json" "$SAPO_DIR/data/global.json"; do
        if SAPO_EXEC "[ -f '$gj' ]"; then
            gdata=$(SAPO_EXEC "cat '$gj'")
            if have jq; then
                [[ -z "$API_URL" ]] && API_URL=$(echo "$gdata" | jq -r '.config.azuracast_api_url // empty')
                [[ -z "$API_KEY" ]] && API_KEY=$(echo "$gdata" | jq -r '.config.azuracast_api_key // empty')
            fi
            ok "Configuración global leída de $gj"
            break
        fi
    done
fi

# ─── 5. LOCALIZAR ARCHIKAST ──────────────────────────────────────────────────
h1 "5. Archikast"
ARCH_WHERE=""
if [[ -n "$ARCH_DIR" ]]; then
    [[ -d "$ARCH_DIR" ]] && ARCH_WHERE=host
    [[ -z "$ARCH_WHERE" && -n "$C" ]] && docker exec "$C" test -d "$ARCH_DIR" 2>/dev/null && ARCH_WHERE=container
else
    # Plugins de AzuraCast montados (Archikast suele ser uno)
    pl_src=$(persist_src /var/azuracast/www/plugins)
    if [[ -n "$pl_src" && -d "$pl_src" ]]; then
        ok "Plugins de AzuraCast en $pl_src:"
        for d in "$pl_src"/*/; do
            [[ -d "$d" ]] || continue
            echo "        - $(basename "$d")  $(grep -hoE '"(version|azuracast[^"]*)": *"[^"]+"' "$d/composer.json" 2>/dev/null | tr '\n' ' ')"
            if [[ -z "$ARCH_DIR" ]] && basename "$d" | grep -qi archikast; then ARCH_DIR="${d%/}"; ARCH_WHERE=host; fi
        done
        warn "Los plugins se enganchan a clases internas de AzuraCast: pueden romperse con cualquier salto de versión (PHP/Symfony). Pruébalos en una copia antes"
    fi
    # Contenedores propios
    other=$(docker ps --format '{{.Names}} ({{.Image}})' | grep -i archikast)
    [[ -n "$other" ]] && { ok "Contenedor(es) de Archikast:"; echo "$other" | sed 's/^/        /'; }
    # Directorios en host
    [[ -z "$ARCH_WHERE" ]] && ARCH_DIR=$(find /var/www /opt /srv /home /root "$AZ_DIR" -maxdepth 4 -type d -iname '*archikast*' 2>/dev/null | head -1)
    [[ -z "$ARCH_WHERE" && -n "$ARCH_DIR" ]] && ARCH_WHERE=host
    if [[ -z "$ARCH_WHERE" && -n "$C" ]]; then
        ARCH_DIR=$(docker exec "$C" find /var/azuracast /var/www -maxdepth 5 -type d -iname '*archikast*' 2>/dev/null | head -1)
        [[ -n "$ARCH_DIR" ]] && ARCH_WHERE=container
    fi
fi

if [[ -z "$ARCH_WHERE" ]]; then
    warn "No se encontró Archikast (usa --archikast-dir DIR). Compruébalo a mano"
else
    ok "Archikast localizado en $ARCH_DIR ($ARCH_WHERE)"
    check_persistence "Archikast" "$ARCH_DIR" "$ARCH_WHERE"
    if [[ "$ARCH_WHERE" == host ]]; then
        refs=$(grep -rIlE "api/station|X-API-Key|/var/azuracast|azuracast" "$ARCH_DIR" 2>/dev/null | head -15)
        eps=$(grep -rIhoE "/(api/)?(admin/)?station/[^'\" ?)]*" "$ARCH_DIR" 2>/dev/null | sed -E 's#[0-9]+#{id}#g' | sort | uniq -c | sort -rn | head -20)
    else
        refs=$(docker exec "$C" grep -rIlE "api/station|X-API-Key|/var/azuracast" "$ARCH_DIR" 2>/dev/null | head -15)
        eps=$(docker exec "$C" grep -rIhoE "/(api/)?(admin/)?station/[^'\" ?)]*" "$ARCH_DIR" 2>/dev/null | sed -E 's#[0-9]+#{id}#g' | sort | uniq -c | sort -rn | head -20)
    fi
    if [[ -n "$refs" ]]; then
        info "Archivos de Archikast que hablan con AzuraCast:"; echo "$refs" | sed 's/^/        /'
        [[ -n "$eps" ]] && { info "Endpoints de AzuraCast que usa (compáralos con las notas de la versión):"; echo "$eps" | sed 's/^/        /'; echo "$eps" > "$OUT/archikast_endpoints.txt"; }
    else
        info "No se detectaron referencias directas a la API/rutas de AzuraCast en Archikast"
    fi
fi

# ─── 6. CRON ─────────────────────────────────────────────────────────────────
h1 "6. Tareas programadas (¿viven fuera del contenedor?)"
hostcron=$( { crontab -l 2>/dev/null; cat /etc/cron.d/* 2>/dev/null; } | grep -E '^[[:space:]]*([0-9*/,-]+[[:space:]]+){5}' | grep -iE 'sapo|archikast|record_emissions|cliente_rrll|rss_preload' )
[[ -n "$hostcron" ]] && { ok "Cron en el HOST (no se pierde al actualizar):"; echo "$hostcron" | sed 's/^/        /'; }
if [[ -n "$C" ]]; then
    contcron=$(docker exec "$C" sh -c 'for u in azuracast root www-data; do crontab -l -u $u 2>/dev/null; done; cat /etc/cron.d/* 2>/dev/null' | grep -E '^[[:space:]]*([0-9*/,-]+[[:space:]]+){5}' | grep -iE 'sapo|archikast|record_emissions|cliente_rrll|rss_preload')
    if [[ -n "$contcron" ]]; then
        fail "Cron de SAPO/Archikast DENTRO del contenedor (se pierde al recrearlo; pásalo al host o a un volumen):"
        echo "$contcron" | sed 's/^/        /'
    fi
fi
[[ -z "$hostcron" && -z "${contcron:-}" ]] && warn "No se detectó cron de SAPO/Archikast; verifica cómo se lanzan las tareas"

# ─── 7. API DE AZURACAST ─────────────────────────────────────────────────────
h1 "7. API de AzuraCast (endpoints que usa SAPO)"
api() { curl -s -m 20 -o "$2" -w '%{http_code}' -H "X-API-Key: $API_KEY" "$API_URL$1" 2>/dev/null; }

if [[ -z "$API_URL" || -z "$API_KEY" ]]; then
    warn "Sin API_URL/API_KEY (pásalas con --api-url/--api-key). Se omite esta sección"
else
    API_URL="${API_URL%/}"
    [[ "$API_URL" != */api ]] && API_URL="$API_URL/api" && info "Normalizo la URL a $API_URL (SAPO usa 'apiUrl/station/..', confirma que su config incluye /api)"
    api_dir="$OUT/api"; mkdir -p "$api_dir"
    code=$(api /status "$api_dir/status.json")
    [[ "$code" == 200 ]] && ok "GET /status -> 200" || fail "GET /status -> $code (API inaccesible)"
    code=$(api /admin/stations "$api_dir/stations.json")
    if [[ "$code" == 200 ]]; then
        ok "GET /admin/stations -> 200 (la clave tiene permisos de admin)"
    elif [[ "$code" == 403 || "$code" == 401 ]]; then
        fail "GET /admin/stations -> $code: la clave NO es admin; SAPO no podrá leer/escribir custom_config"
    else
        fail "GET /admin/stations -> $code"
    fi

    if have jq && [[ -s "$api_dir/stations.json" ]]; then
        ids=$(jq -r '.[]? | .id' "$api_dir/stations.json" 2>/dev/null)
        info "Emisoras: $(jq -r '[.[]? | "\(.id):\(.short_name)"] | join(", ")' "$api_dir/stations.json")"
        for id in $ids; do
            echo -e "\n  ${BOLD}Emisora $id${RST}"
            # Snapshot de formato de respuesta, para comparar tras actualizar (diff -r)
            for ep in "station/$id" "station/$id/schedule" "station/$id/playlists" \
                      "station/$id/files?rowCount=5" "station/$id/history?rows=5" \
                      "station/$id/nowplaying" "station/$id/listeners" \
                      "station/$id/streamers" "station/$id/log/liquidsoap" "admin/station/$id"; do
                fn="$api_dir/$(echo "$ep" | tr '/?=' '___').json"
                code=$(api "/$ep" "$fn")
                if [[ "$code" == 200 ]]; then ok "GET /$ep -> 200"
                elif [[ "$ep" == */log/liquidsoap || "$ep" == */streamers ]]; then
                    warn "GET /$ep -> $code (estado ANTES de actualizar; si ya falla hoy, no es regresión. Cuerpo: $(head -c 160 "$fn" | tr '\n' ' '))"
                else fail "GET /$ep -> $code"; fi
            done
            # Estructura (claves) para comparar luego con el post-update
            jq -r 'paths | map(tostring) | join(".")' "$api_dir/admin_station_$id.json" 2>/dev/null | sort -u > "$OUT/schema_admin_station_$id.txt"
            jq -r '.[0]? // .rows[0]? // empty | keys[]' "$api_dir/station_${id}_history_rows_5.json" 2>/dev/null | sort > "$OUT/schema_history_$id.txt"

            # ── custom_config de Liquidsoap ──
            for key in $(jq -r '.backend_config // {} | keys[] | select(startswith("custom_config"))' "$api_dir/admin_station_$id.json" 2>/dev/null); do
                body=$(jq -r ".backend_config.\"$key\" // \"\"" "$api_dir/admin_station_$id.json")
                [[ -z "$body" ]] && continue
                f="$OUT/station_${id}_${key}.liq"; printf '%s\n' "$body" > "$f"
                lines=$(wc -l < "$f")
                info "backend_config.$key: $lines líneas -> $f"
                # Construcciones que cambian/rompen con Liquidsoap 2.3/2.4
                hits=""
                grep -qE '\bnull\(\)'                            "$f" && hits="$hits null()"
                grep -qE '\b(fallback|switch|sequence|add|random|rotate)\s*\(' "$f" && hits="$hits fallback/switch/add(..)(¿falta synchronous?)"
                grep -qE '\bsource\.dynamic|request\.dynamic'    "$f" && hits="$hits source.dynamic"
                grep -qE '\b(system|process\.run|process\.read)\s*\(' "$f" && hits="$hits ejecución-de-procesos"
                grep -qE '%include|%ifdef|%ifencoder'            "$f" && hits="$hits directivas-%"
                grep -qiE 'senal|señal|time_?signal|SAPO'         "$f" && info "  contiene el bloque de señales horarias de SAPO"
                if [[ -n "$hits" ]]; then
                    if (( LQ24 )); then info "  Construcciones a vigilar:$hits (ya corre Liquidsoap 2.4.x con esta config; sin cambios esperados)"
                    else warn "  Revisar contra Liquidsoap 2.4:$hits"; fi
                else
                    ok "  Sin construcciones conocidas como problemáticas"
                fi
            done
        done
    fi
fi

# ─── 8. ACCESO A FICHEROS QUE SAPO USA ───────────────────────────────────────
h1 "8. Rutas de datos de emisoras"
if [[ -n "$C" ]]; then
    n=$(docker exec "$C" sh -c 'ls -d /var/azuracast/stations/*/ 2>/dev/null | wc -l')
    [[ "$n" -gt 0 ]] && ok "$n emisoras en /var/azuracast/stations" || warn "No hay emisoras en /var/azuracast/stations"
    src=$(persist_src /var/azuracast/stations)
    [[ -n "$src" ]] && ok "/var/azuracast/stations es persistente ($src)" || fail "/var/azuracast/stations NO es persistente"
    docker exec "$C" sh -c 'ls /var/azuracast/stations/*/config/liquidsoap.liq' >/dev/null 2>&1 \
        && ok "liquidsoap.liq presente (SAPO lo edita directamente)" \
        || warn "No se ven liquidsoap.liq en config/ (SAPO los edita directamente)"
    # Permisos: SAPO escribe media/senales_horarias
    docker exec "$C" sh -c 'for d in /var/azuracast/stations/*/media; do [ -w "$d" ] || echo "$d"; done' 2>/dev/null | head -5 \
        | while read -r d; do warn "Sin escritura para el usuario por defecto: $d"; done
fi

# ─── 9. BACKUP OPCIONAL ──────────────────────────────────────────────────────
if (( DO_BACKUP )); then
    h1 "9. Backup (--backup)"
    bk="$OUT/backup"; mkdir -p "$bk"
    [[ -n "$SAPO_WHERE" ]] && {
        if [[ "$SAPO_WHERE" == host ]]; then tar czf "$bk/sapo.tgz" -C "$(dirname "$SAPO_DIR")" "$(basename "$SAPO_DIR")" 2>/dev/null
        else docker exec "$C" tar czf - -C "$(dirname "$SAPO_DIR")" "$(basename "$SAPO_DIR")" > "$bk/sapo.tgz" 2>/dev/null; fi
        [[ -s "$bk/sapo.tgz" ]] && ok "Copia de SAPO -> $bk/sapo.tgz" || fail "No se pudo copiar SAPO"
    }
    [[ -n "$ARCH_WHERE" ]] && {
        if [[ "$ARCH_WHERE" == host ]]; then tar czf "$bk/archikast.tgz" -C "$(dirname "$ARCH_DIR")" "$(basename "$ARCH_DIR")" 2>/dev/null
        else docker exec "$C" tar czf - -C "$(dirname "$ARCH_DIR")" "$(basename "$ARCH_DIR")" > "$bk/archikast.tgz" 2>/dev/null; fi
        [[ -s "$bk/archikast.tgz" ]] && ok "Copia de Archikast -> $bk/archikast.tgz" || fail "No se pudo copiar Archikast"
    }
    if [[ -n "$C" ]]; then
        name="pre_update_$(date +%Y%m%d_%H%M%S).zip"
        if docker exec --user=azuracast "$C" azuracast_cli azuracast:backup "/var/azuracast/backups/$name" >/dev/null 2>&1; then
            ok "Backup nativo de AzuraCast: /var/azuracast/backups/$name"
        else fail "Falló el backup nativo de AzuraCast (hazlo desde el panel: Administration > Backups)"; fi
    fi
    warn "Copia $bk fuera del servidor antes de actualizar"
fi

# ─── VEREDICTO ───────────────────────────────────────────────────────────────
h1 "Resumen"
echo -e "  ${GRN}OK: $PASS${RST}   ${YLW}Avisos: $WARN${RST}   ${RED}Fallos: $FAIL${RST}"
echo
if (( FAIL > 0 )); then
    echo -e "  ${RED}${BOLD}NO ACTUALIZAR todavía:${RST} resuelve los [FAIL] (normalmente volúmenes/cron dentro del contenedor)."
    rc=2
elif (( WARN > 0 )); then
    echo -e "  ${YLW}${BOLD}REVISAR avisos antes de actualizar${RST} (especialmente custom_config de Liquidsoap)."
    rc=1
else
    echo -e "  ${GRN}${BOLD}Todo en orden para actualizar.${RST}"
    rc=0
fi
cat <<EOF

Siguientes pasos recomendados:
  1. bash $0 --backup ...   (si aún no hiciste copia)
  2. Lee las release notes entre tu versión y la última: https://github.com/AzuraCast/AzuraCast/releases
  3. cd $AZ_DIR && ./docker.sh update
  4. Vuelve a ejecutar este script con --out distinto y compara:
       diff -r $OUT/api <nuevo>/api ; diff $OUT/schema_history_*.txt <nuevo>/schema_history_*.txt
     Cualquier endpoint que pase de 200 a otro código, o claves que desaparezcan, es una ruptura.
  5. Prueba en SAPO: sincronizar parrilla, aplicar señal horaria, cron de emisiones, cliente_rrll.
EOF
exit $rc
