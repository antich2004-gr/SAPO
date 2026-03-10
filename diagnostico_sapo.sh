#!/bin/bash
# =============================================================================
# diagnostico_sapo.sh - Diagnóstico completo del entorno SAPO
# =============================================================================
# Recoge información sobre rutas, permisos, procesos y configuración
# relacionada con SAPO y su integración con AzuraCast.
# =============================================================================

SEP="━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
H1() { echo; echo "$SEP"; echo "  $1"; echo "$SEP"; }
H2() { echo; echo "  ── $1"; }

echo "============================================================"
echo "  SAPO - Diagnóstico de entorno   $(date '+%Y-%m-%d %H:%M:%S')"
echo "  Ejecutado como: $(whoami) (uid=$(id -u) gid=$(id -g))"
echo "============================================================"


# ─── SISTEMA ────────────────────────────────────────────────────────────────
H1 "SISTEMA"
echo "Hostname     : $(hostname -f 2>/dev/null || hostname)"
echo "Kernel       : $(uname -r)"
echo "OS           : $(cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d= -f2 | tr -d '\"' || uname -s)"
echo "Fecha/Hora   : $(date)"
echo "Uptime       : $(uptime -p 2>/dev/null || uptime)"


# ─── DOCKER ─────────────────────────────────────────────────────────────────
H1 "DOCKER / AZURACAST"
H2 "¿Estamos dentro de un contenedor?"
if [ -f /.dockerenv ]; then
    echo "  SÍ - existe /.dockerenv"
elif grep -q docker /proc/1/cgroup 2>/dev/null; then
    echo "  SÍ - detectado en /proc/1/cgroup"
else
    echo "  NO (host o entorno sin Docker)"
fi

H2 "Contenedores Docker activos (requiere acceso a docker socket)"
if command -v docker &>/dev/null; then
    docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || echo "  Sin acceso al socket Docker"
else
    echo "  Comando 'docker' no disponible en este entorno"
fi

H2 "Proceso init / PID 1"
cat /proc/1/cmdline 2>/dev/null | tr '\0' ' ' && echo || true


# ─── USUARIOS Y PROCESOS ─────────────────────────────────────────────────────
H1 "USUARIOS Y PROCESOS RELEVANTES"
H2 "Usuario actual y grupos"
id

H2 "Usuarios del sistema (shell real)"
grep -E '/bin/(ba)?sh$|/bin/zsh$' /etc/passwd 2>/dev/null | cut -d: -f1,3,6 | column -t -s:

H2 "Usuario PHP-FPM (worker)"
ps aux 2>/dev/null | grep -E 'php-fpm|php[0-9]' | grep -v grep | head -20 || echo "  No se detecta php-fpm"

H2 "Usuario nginx / apache"
ps aux 2>/dev/null | grep -E 'nginx|apache|httpd' | grep -v grep | head -10 || echo "  No se detecta nginx/apache"

H2 "Procesos SAPO/podget activos"
ps aux 2>/dev/null | grep -E 'cliente_rrll|podget|podget' | grep -v grep || echo "  Ninguno"

H2 "Crontabs activos"
for user in $(cut -d: -f1 /etc/passwd 2>/dev/null); do
    crontab -u "$user" -l 2>/dev/null | grep -v '^#' | grep -v '^$' | while read line; do
        echo "  [$user] $line"
    done
done
# También revisar /etc/cron*
echo
echo "  /etc/cron.d:"
ls -la /etc/cron.d/ 2>/dev/null | grep -v total || echo "    (vacío o sin acceso)"
for f in /etc/cron.d/*; do
    [ -f "$f" ] && echo "  ---- $f ----" && grep -v '^#' "$f" | grep -v '^$' || true
done
echo
echo "  /var/spool/cron/crontabs (si existe):"
ls -la /var/spool/cron/crontabs/ 2>/dev/null || echo "    (sin acceso)"


# ─── RUTAS PRINCIPALES ───────────────────────────────────────────────────────
H1 "RUTAS PRINCIPALES"

check_path() {
    local label="$1"
    local path="$2"
    printf "  %-45s " "$label:"
    if [ -e "$path" ]; then
        local type=""
        [ -L "$path" ] && type="SYMLINK→$(readlink -f "$path")"
        [ -d "$path" ] && [ ! -L "$path" ] && type="DIR"
        [ -f "$path" ] && [ ! -L "$path" ] && type="FILE"
        local perms owner
        perms=$(stat -c '%A' "$path" 2>/dev/null || ls -ld "$path" 2>/dev/null | awk '{print $1}')
        owner=$(stat -c '%U:%G' "$path" 2>/dev/null)
        echo "OK  [$type]  $perms  $owner"
    else
        echo "NO EXISTE"
    fi
}

check_path "/var/www/html"                              "/var/www/html"
check_path "/var/www/html/index.php"                   "/var/www/html/index.php"
check_path "/var/www/html/logs"                        "/var/www/html/logs"
check_path "/var/www/html/includes/podcasts.php"       "/var/www/html/includes/podcasts.php"
check_path "/var/www/html/cliente_rrll"                "/var/www/html/cliente_rrll"
check_path "/var/www/html/cliente_rrll/cliente_rrll.sh" "/var/www/html/cliente_rrll/cliente_rrll.sh"
check_path "/var/azuracast"                            "/var/azuracast"
check_path "/var/azuracast/www"                        "/var/azuracast/www"
check_path "/var/azuracast/www/web"                    "/var/azuracast/www/web"
check_path "/var/azuracast/www/web/sapo"               "/var/azuracast/www/web/sapo"
check_path "/mnt/emisoras"                             "/mnt/emisoras"

H2 "Contenido de /var/www/html/logs/"
ls -lah /var/www/html/logs/ 2>/dev/null || echo "  No accesible"

H2 "¿/var/www/html es symlink? (readlink)"
readlink -f /var/www/html 2>/dev/null || echo "  (no es symlink o readlink falla)"

H2 "stat /var/www/html"
stat /var/www/html 2>/dev/null || echo "  sin acceso"

H2 "Emisoras disponibles en /mnt/emisoras"
ls -la /mnt/emisoras/ 2>/dev/null || echo "  No accesible"

H2 "Log interno podget (agora)"
AGORA_LOG="/mnt/emisoras/agora/media/Informes/podget_agora.log"
if [ -f "$AGORA_LOG" ]; then
    stat "$AGORA_LOG"
    echo "  --- últimas 5 líneas ---"
    tail -5 "$AGORA_LOG"
else
    echo "  No existe: $AGORA_LOG"
fi

H2 "Log redirect PHP (agora)"
PHP_LOG="/var/www/html/logs/podget_agora.log"
if [ -f "$PHP_LOG" ]; then
    stat "$PHP_LOG"
    echo "  --- contenido completo (max 30 líneas) ---"
    head -30 "$PHP_LOG"
else
    echo "  No existe: $PHP_LOG"
fi


# ─── SCRIPT CLIENTE_RRLL ─────────────────────────────────────────────────────
H1 "SCRIPT CLIENTE_RRLL.SH"
SCRIPT="/var/www/html/cliente_rrll/cliente_rrll.sh"
H2 "Permisos y propietario"
ls -la "$SCRIPT" 2>/dev/null || echo "  No encontrado en $SCRIPT"

H2 "Primeras 40 líneas del script"
head -40 "$SCRIPT" 2>/dev/null || echo "  No legible"

H2 "Lock file actual"
LOCK="/tmp/cliente_descarga_agora.lock"
if [ -f "$LOCK" ]; then
    echo "  EXISTE: $LOCK"
    stat "$LOCK"
    PID_IN_LOCK=$(cat "$LOCK" 2>/dev/null)
    echo "  PID en lock: $PID_IN_LOCK"
    if [ -n "$PID_IN_LOCK" ] && kill -0 "$PID_IN_LOCK" 2>/dev/null; then
        echo "  Estado: PROCESO ACTIVO"
        ps -p "$PID_IN_LOCK" -o pid,user,cmd 2>/dev/null
    else
        echo "  Estado: proceso ya no existe (lock huérfano)"
    fi
else
    echo "  No existe (no hay ejecución activa)"
fi

H2 "Otros lock files en /tmp"
ls -la /tmp/*.lock 2>/dev/null || echo "  Ninguno"

H2 "¿podget instalado?"
which podget 2>/dev/null || command -v podget 2>/dev/null || echo "  'podget' no está en PATH"
podget --version 2>/dev/null || true

H2 "¿stdbuf instalado?"
which stdbuf 2>/dev/null || echo "  'stdbuf' no está en PATH"

H2 "PATH en este entorno"
echo "  $PATH"


# ─── PHP / PHP-FPM ───────────────────────────────────────────────────────────
H1 "PHP Y PHP-FPM"
H2 "Versión PHP"
php --version 2>/dev/null | head -3 || echo "  PHP no disponible en PATH"

H2 "PHP-FPM config (buscar pool activo)"
find /etc -name "*.conf" 2>/dev/null | xargs grep -l 'listen\s*=' 2>/dev/null | head -5 | while read f; do
    echo "  $f"
    grep -E 'user\s*=|group\s*=|listen\s*=' "$f" 2>/dev/null | head -5 | sed 's/^/    /'
done

H2 "PHP-FPM pools (buscar usuario)"
find /etc/php* /etc/php-fpm* -name "*.conf" 2>/dev/null | xargs grep -E '^\s*(user|group)\s*=' 2>/dev/null | head -20 || echo "  No encontrado"

H2 "Función exec() habilitada"
php -r 'echo function_exists("exec") ? "exec() DISPONIBLE\n" : "exec() DESHABILITADO\n";' 2>/dev/null || echo "  No se puede comprobar"

H2 "HOME y PATH desde PHP CLI"
php -r 'echo "HOME=".getenv("HOME")."\nPATH=".getenv("PATH")."\n";' 2>/dev/null || echo "  No se puede comprobar"


# ─── NGINX ───────────────────────────────────────────────────────────────────
H1 "NGINX"
H2 "Configuración SAPO (virtual host)"
find /etc/nginx -name "*.conf" 2>/dev/null | xargs grep -l 'sapo\|/var/www/html' 2>/dev/null | head -5 | while read f; do
    echo "  ---- $f ----"
    cat "$f" 2>/dev/null | head -40
done
if ! find /etc/nginx -name "*.conf" 2>/dev/null | xargs grep -l 'sapo\|/var/www/html' 2>/dev/null | grep -q .; then
    echo "  No se encontró config específica de SAPO"
fi


# ─── RED Y PUERTOS ───────────────────────────────────────────────────────────
H1 "RED Y PUERTOS"
H2 "Interfaces de red"
ip addr 2>/dev/null | grep -E 'inet |^[0-9]+:' || ifconfig 2>/dev/null | grep -E 'inet |^[a-z]' || echo "  Sin acceso"

H2 "Puertos escuchando (tcp)"
ss -tlnp 2>/dev/null | grep LISTEN || netstat -tlnp 2>/dev/null | grep LISTEN || echo "  Sin acceso"


# ─── PRUEBA DE ESCRITURA ──────────────────────────────────────────────────────
H1 "PRUEBA DE ESCRITURA EN LOGS"
TEST_FILE="/var/www/html/logs/test_diagnostico_$$.log"
H2 "Escribir archivo de prueba: $TEST_FILE"
if echo "test $(date)" > "$TEST_FILE" 2>/dev/null; then
    echo "  OK - escritura exitosa como $(whoami)"
    ls -la "$TEST_FILE"
    rm -f "$TEST_FILE"
else
    echo "  FALLO - no se puede escribir en /var/www/html/logs/ como $(whoami)"
fi

H2 "Probar escritura con nohup (simula PHP exec)"
NOHUP_TEST="/var/www/html/logs/test_nohup_$$.log"
export HOME=/tmp
export PATH=/usr/local/bin:/usr/bin:/bin
nohup bash -c 'for i in 1 2 3; do echo "linea $i $(date)"; sleep 1; done' >> "$NOHUP_TEST" 2>&1 &
NPID=$!
echo "  Lanzado nohup PID=$NPID"
sleep 4
echo "  Contenido tras 4s:"
cat "$NOHUP_TEST" 2>/dev/null || echo "  Archivo no existe"
ls -la "$NOHUP_TEST" 2>/dev/null
rm -f "$NOHUP_TEST"


# ─── RESUMEN ─────────────────────────────────────────────────────────────────
H1 "RESUMEN / CHECKLIST"
ok()   { printf "  [OK]  %s\n" "$1"; }
fail() { printf "  [!!]  %s\n" "$1"; }
warn() { printf "  [??]  %s\n" "$1"; }

# /var/www/html
[ -d /var/www/html ] && ok "/var/www/html existe" || fail "/var/www/html NO existe"
[ -f /var/www/html/index.php ] && ok "index.php encontrado" || fail "index.php NO encontrado"
[ -d /var/www/html/logs ] && ok "directorio logs/ existe" || fail "directorio logs/ NO existe"
[ -w /var/www/html/logs ] && ok "logs/ es escribible por $(whoami)" || fail "logs/ NO escribible por $(whoami)"
[ -f /var/www/html/cliente_rrll/cliente_rrll.sh ] && ok "cliente_rrll.sh encontrado" || fail "cliente_rrll.sh NO encontrado"
[ -x /var/www/html/cliente_rrll/cliente_rrll.sh ] && ok "cliente_rrll.sh es ejecutable" || fail "cliente_rrll.sh NO es ejecutable"

# Herramientas
command -v podget &>/dev/null && ok "podget disponible en PATH" || fail "podget NO en PATH"
command -v stdbuf &>/dev/null && ok "stdbuf disponible en PATH" || fail "stdbuf NO en PATH"
command -v nohup &>/dev/null && ok "nohup disponible en PATH" || fail "nohup NO en PATH"

# Lock
[ -f /tmp/cliente_descarga_agora.lock ] && warn "Lock file EXISTE - posible ejecución activa" || ok "Sin lock file activo"

echo
echo "============================================================"
echo "  Fin del diagnóstico - $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================================"
