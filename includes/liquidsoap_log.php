<?php
// includes/liquidsoap_log.php
// Acceso, parseo y consulta del log de Liquidsoap de AzuraCast.
// Proporciona diagnóstico de causa de emisión perdida con mayor precisión
// que el historial de reproducción solo.
//
// Fuentes de referencia:
//   AzuraCast ConfigWriter.php  → naming conventions de sources
//   github.com/AzuraCast/AzuraCast issues #2858, #6066, #6259, #6310,
//   #6933, #7436, #7476, #4628, #5504, #1557, #7625, #7814

// ── Componentes del log que nos interesan ─────────────────────────────────────
// Filtramos en parseo para mantener el índice en caché lo más compacto posible.
const LS_RELEVANT_PREFIXES = [
    // Scheduling y switching
    'schedule_switch',
    'live_fallback',
    'interrupting_fallback',
    'requests_fallback',
    'autodj_fallback',
    'dynamic_startup',
    'safe_fallback',
    'mksafe',
    'error_jingle',
    // Tracks
    'next_song',
    'cue_next_song',
    'playlist_',
    'cue_playlist_',
    'single',
    // API calls (lang = versiones antiguas, azuracast.api = nuevas)
    'lang',
    'azuracast.api',
    // Archivos y decodificación
    'request',          // request, request.dynamic
    'decoder',
    // Reloj y rendimiento
    'clock.',           // clock.wallclock_main, clock.main, etc.
    // Directo
    'input_streamer',
    // Crossfade (puede causar vacíos)
    'cross',
];

// ── Acceso al log ─────────────────────────────────────────────────────────────

/**
 * Obtiene el índice parseado del log de Liquidsoap para una emisora.
 * Caché de 30 min (el log crece continuamente; para meses pasados el log
 * ya puede no contener esas fechas por rotación de AzuraCast).
 *
 * @return array|null  [Y-m-d => [['time','comp','level','msg'], ...]] o null si no disponible
 */
function getLiquidsoapLogIndex(string $username): ?array
{
    $cacheKey = 'lslog_idx_' . $username;
    $cached   = cacheGet($cacheKey, 1800);
    if ($cached !== null) return $cached;

    $raw = _lsFetchRaw($username);
    if ($raw === null) return null;

    $index = _lsParseLog($raw);
    cacheSet($cacheKey, $index);
    return $index;
}

/**
 * Descarga el texto crudo del log de Liquidsoap desde la API de AzuraCast.
 * Requiere API Key con permiso de lectura de logs de estación.
 */
function _lsFetchRaw(string $username): ?string
{
    $config    = getConfig();
    $apiUrl    = $config['azuracast_api_url'] ?? '';
    $apiKey    = $config['azuracast_api_key'] ?? '';
    $userData  = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($apiUrl) || empty($stationId) || empty($apiKey)) return null;

    $context = stream_context_create(['http' => [
        'timeout'    => 30,
        'user_agent' => 'SAPO/1.0',
        'header'     => 'X-API-Key: ' . $apiKey,
    ]]);

    $url      = rtrim($apiUrl, '/') . '/station/' . $stationId . '/log/liquidsoap';
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;

    $data = @json_decode($response, true);
    if (!is_array($data) || !isset($data['contents'])) return null;

    return (string)$data['contents'];
}

// ── Parseo ────────────────────────────────────────────────────────────────────

/**
 * Parsea el texto crudo del log en un índice compacto por fecha.
 * Solo guarda líneas de nivel ≤ 3 de componentes relevantes.
 *
 * Formato de cada línea del log:
 *   YYYY/MM/DD HH:MM:SS [componente:nivel] Mensaje
 *
 * @return array [Y-m-d => [['time'=>'HH:MM:SS','comp'=>...,'level'=>N,'msg'=>...], ...]]
 */
function _lsParseLog(string $raw): array
{
    $pattern = '/^(\d{4}\/\d{2}\/\d{2}) (\d{2}:\d{2}:\d{2}) \[([^:]+):(\d)\] (.+)$/';
    $index   = [];

    foreach (explode("\n", $raw) as $line) {
        $line = rtrim($line);
        if (!preg_match($pattern, $line, $m)) continue;

        [, $date, $time, $comp, $level, $msg] = $m;
        $level = (int)$level;

        if ($level > 3) continue; // descartar debug/trace

        $keep = false;
        foreach (LS_RELEVANT_PREFIXES as $prefix) {
            if (str_starts_with($comp, $prefix)) { $keep = true; break; }
        }
        if (!$keep) continue;

        $date           = str_replace('/', '-', $date); // YYYY/MM/DD → YYYY-MM-DD
        $index[$date][] = compact('time', 'comp', 'level', 'msg');
    }

    return $index;
}

// ── Mapeo nombre de playlist → ID de Liquidsoap ───────────────────────────────

/**
 * Calcula el identificador de source de Liquidsoap para una playlist de AzuraCast,
 * replicando el algoritmo de ConfigWriter::getPlaylistVariableName():
 *   1. Strings::getProgrammaticString(name)  → slug básico
 *   2. ConfigWriter::cleanUpVarName('playlist_' + slug)  → ID final
 *
 * Ejemplo: "Mi Programa (1h30)" → "playlist_mi_programa_1h30"
 *          "Enseñando los dientes" → "playlist_ensenando_los_dientes"
 */
function computeLiquidsoapSourceId(string $playlistName): string
{
    // ── Paso 1: getProgrammaticString ────────────────────────────────────────
    $s = preg_replace('/([^\w\s\d\-_~,;\[\]\(\).])/u', '', $playlistName) ?? '';
    $s = preg_replace('/([\.]{2,})/', '.', $s) ?? '';
    $s = str_replace(' ', '_', $s);
    $s = mb_strtolower($s);

    // ── Paso 2: cleanUpVarName('playlist_' + s) ──────────────────────────────
    $s = 'playlist_' . $s;
    $s = strtolower(
        preg_replace(['/[\r\n\t ]+/', '/[\"*\/:<>?\'|]+/'], ' ', strip_tags($s)) ?? ''
    );
    $s = html_entity_decode($s, ENT_QUOTES, 'utf-8');
    $s = htmlentities($s, ENT_QUOTES, 'utf-8');
    $s = preg_replace('/(&)([a-z])([a-z]+;)/i', '$2', $s) ?? '';
    $s = rawurlencode(str_replace(' ', '_', $s));
    return str_replace(['%', '-', '.'], ['', '_', '_'], $s);
}

// ── Consulta y diagnóstico ────────────────────────────────────────────────────

/**
 * Diagnostica por qué una emisión fue perdida, usando el índice del log de Liquidsoap.
 * Solo se llama cuando ya se sabe que SÍ había episodio disponible (o estado desconocido)
 * y que el overrun fue ≤ 15 min — es decir, buscamos un fallo técnico de AzuraCast.
 *
 * Causas detectadas (por orden de prioridad):
 *   1.  Playlist no accesible (M3U no encontrado o ilegible)
 *   2.  Archivo de audio no encontrado en disco
 *   3.  Archivo de audio corrupto o no decodificable
 *   4.  Error / excepción interna de Liquidsoap
 *   5.  Servidor sobrecargado (retraso de reloj acumulado)
 *   6.  Un directo tomó el control en esa franja
 *   7.  AzuraCast activó el fallback de emergencia
 *   8.  Cascada de fallback completa (AutoDJ no encontró contenido)
 *
 * @param array  $logIndex          Índice parseado (getLiquidsoapLogIndex)
 * @param string $date              Y-m-d de la emisión perdida
 * @param string $scheduledAt       HH:MM hora programada
 * @param string $liquidsoapSrcId   ID de source Liquidsoap (computeLiquidsoapSourceId)
 * @return string|null  Razón legible o null si el log no aporta evidencia
 */
function diagnoseMissedFromLog(
    array  $logIndex,
    string $date,
    string $scheduledAt,
    string $liquidsoapSrcId
): ?string {
    // Si el log no tiene ninguna línea para esa fecha, ha rotado (AzuraCast
    // conserva solo los últimos MB del log; fechas antiguas desaparecen).
    if (empty($logIndex[$date] ?? [])) {
        return 'Fallo de AzuraCast — log de Liquidsoap no disponible para esa fecha (posible rotación del log)';
    }

    // Ventana: 15 min antes hasta 90 min después del horario programado
    $relevant = _lsQueryWindow($logIndex, $date, $scheduledAt, 15, 90);
    if (empty($relevant)) {
        return 'Fallo de AzuraCast — sin actividad en el log de Liquidsoap en esa franja horaria';
    }

    $schedMin = _lsToMin($scheduledAt);

    // ── 1. Playlist no accesible (M3U no encontrado o request fallido) ────────
    // Componente = el ID de source de la playlist (playlist_xxx)
    foreach ($relevant as $line) {
        if ($line['comp'] !== $liquidsoapSrcId) continue;
        $msg = $line['msg'];

        if (str_contains($msg, "Couldn't read playlist")
         || str_contains($msg, 'request resolution failed')
        ) {
            return 'Fallo de AzuraCast — la playlist no pudo leerse (archivo M3U inaccesible o vacío)';
        }
        if (str_contains($msg, 'Reload time was set to 0')) {
            return 'Fallo de AzuraCast — la playlist no pudo recargarse';
        }
    }

    // ── 2. Archivo de audio no encontrado en disco ────────────────────────────
    // Componente = 'request' o 'request.dynamic'
    foreach ($relevant as $line) {
        if (!str_starts_with($line['comp'], 'request') || $line['level'] > 2) continue;
        $msg = $line['msg'];

        if (str_contains($msg, 'Nonexistent file')
         || str_contains($msg, 'ill-formed URI')
         || str_contains($msg, 'does not exist')
        ) {
            if (preg_match('/"([^"]+)"/', $msg, $m)) {
                return 'Fallo de AzuraCast — archivo no encontrado: ' . basename($m[1]);
            }
            return 'Fallo de AzuraCast — archivo de audio no encontrado en disco';
        }
        if (str_contains($msg, 'Permission denied')) {
            if (preg_match('/"([^"]+)"/', $msg, $m)) {
                return 'Fallo de AzuraCast — sin permiso de lectura: ' . basename($m[1]);
            }
            return 'Fallo de AzuraCast — sin permiso de lectura del archivo de audio';
        }
    }

    // ── 3. Archivo corrupto o no decodificable ────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] !== 'decoder' || $line['level'] > 2) continue;
        $msg = $line['msg'];

        if (str_contains($msg, 'Unable to decode')
         || str_contains($msg, 'could not decode')
         || str_contains($msg, 'No decoder')
        ) {
            if (preg_match('/"([^"]+)"/', $msg, $m)) {
                return 'Fallo de AzuraCast — archivo corrupto o formato no soportado: ' . basename($m[1]);
            }
            return 'Fallo de AzuraCast — archivo de audio corrupto o con formato no soportado';
        }
    }

    // ── 4. Error / excepción interna de Liquidsoap ───────────────────────────
    // Componente = 'lang' (versiones antiguas) o 'azuracast.api' (versiones nuevas)
    foreach ($relevant as $line) {
        if (!in_array($line['comp'], ['lang', 'azuracast.api'], true)) continue;
        if ($line['level'] > 2) continue;
        $msg = $line['msg'];

        if (str_contains($msg, 'DJ Source connected')
         || str_contains($msg, 'API djon')
        ) {
            continue; // DJ conectado — se diagnostica en check 6
        }

        // nextsong devolvió false = AutoDJ sin cola (no debería ocurrir si hay episodio,
        // pero podría indicar fallo de sincronización entre AzuraCast y Liquidsoap)
        if (str_contains($msg, 'API nextsong')
         && str_contains($msg, 'Response (200): false')
        ) {
            return 'Fallo de AzuraCast — el AutoDJ no encontró ningún track en la cola (nextsong = false)';
        }

        if (str_contains($msg, 'Fatal')
         || str_contains($msg, 'Error')
         || str_contains($msg, 'error')
         || str_contains($msg, 'exception')
        ) {
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', $msg), 0, 100);
            return 'Error interno de Liquidsoap: ' . $excerpt;
        }
    }

    // ── 5. Servidor sobrecargado (retraso de reloj) ───────────────────────────
    // Componentes: clock.wallclock_main, clock.main, clock.*
    foreach ($relevant as $line) {
        if (!str_starts_with($line['comp'], 'clock.')) continue;
        $msg = $line['msg'];

        if (str_contains($msg, 'Too much latency') || str_contains($msg, 'Resetting active sources')) {
            return 'Fallo de AzuraCast — sobrecarga crítica del servidor (Liquidsoap reinició las fuentes)';
        }
        if (str_contains($msg, 'catchup') || str_contains($msg, 'late')) {
            if (preg_match('/([\d.]+)\s+seconds?/', $msg, $m)) {
                $secs = (int)round((float)$m[1]);
                return "Fallo de AzuraCast — servidor sobrecargado (retraso acumulado: {$secs}s)";
            }
            return 'Fallo de AzuraCast — servidor sobrecargado (retraso de reloj)';
        }
    }

    // ── 6. Un directo tomó el control ─────────────────────────────────────────
    // Versión antigua: [lang:3] "DJ Source connected!"
    // Versión moderna: [lang:3] API djon - Response (200): true  +  [input_streamer:3] Decoding...
    foreach ($relevant as $line) {
        $msg     = $line['msg'];
        $lineMin = _lsToMin(substr($line['time'], 0, 5));
        if ($lineMin < $schedMin - 5) continue; // Solo si ocurre cerca del horario

        if ($line['comp'] === 'lang' || $line['comp'] === 'azuracast.api') {
            // Formato antiguo
            if (str_contains($msg, 'DJ Source connected')) {
                return 'Un directo tomó el control en esa franja';
            }
            // Formato moderno (API djon)
            if (str_contains($msg, 'API djon') && str_contains($msg, 'Response (200): true')) {
                return 'Un directo tomó el control en esa franja';
            }
        }

        // live_fallback cambia a DJ (con transición "forgetful" = cambio de fuente forzado)
        if ($line['comp'] === 'live_fallback'
         && str_contains($msg, 'Switch to')
         && str_contains($msg, 'forgetful')
        ) {
            return 'Un directo tomó el control en esa franja';
        }
    }

    // ── 7. AzuraCast activó el fallback de emergencia ─────────────────────────
    foreach ($relevant as $line) {
        $lineMin = _lsToMin(substr($line['time'], 0, 5));
        if ($lineMin < $schedMin - 1) continue;

        if ($line['comp'] === 'safe_fallback' && str_contains($line['msg'], 'Switch to')) {
            return 'Fallo de AzuraCast — activó el modo de emergencia (safe fallback): fuente de audio inválida';
        }
        if ($line['comp'] === 'error_jingle') {
            return 'Fallo grave de AzuraCast — sonó el jingle de error (fuente de audio completamente inválida)';
        }
        if ($line['comp'] === 'mksafe') {
            return 'Fallo de AzuraCast — activó la fuente segura de emergencia (mksafe)';
        }
    }

    // ── 7b. AutoDJ cae a blank tras la señal horaria ─────────────────────────
    // Patrón: [switch.XX] Switch to blank.1 en los primeros minutos del slot.
    // La señal horaria se emitió correctamente pero no había ningún track
    // en cola para el programa (AzuraCast no lo tenía pre-cargado en ese instante).
    foreach ($relevant as $line) {
        $lineMin = _lsToMin(substr($line['time'], 0, 5));
        if ($lineMin < $schedMin || $lineMin > $schedMin + 5) continue;
        if (!str_starts_with($line['comp'], 'switch.')) continue;
        if (str_contains($line['msg'], 'Switch to blank')) {
            return 'La señal horaria se emitió pero AzuraCast no tenía el programa en cola (AutoDJ cayó a blank sin contenido)';
        }
    }

    // ── 8. Cascada de fallback completa ───────────────────────────────────────
    // Si se ven los 3 escalones (interrupting → requests → autodj) en la ventana,
    // significa que AzuraCast intentó activar contenido y no encontró nada en ningún nivel.
    $fallbackSeen = [];
    foreach ($relevant as $line) {
        $lineMin = _lsToMin(substr($line['time'], 0, 5));
        if ($lineMin < $schedMin - 2 || $lineMin > $schedMin + 10) continue;
        if (str_contains($line['msg'], 'Switch to')) {
            $fallbackSeen[$line['comp']] = true;
        }
    }
    $cascadeComps = ['interrupting_fallback', 'requests_fallback', 'autodj_fallback'];
    $cascadeCount = count(array_intersect_key($fallbackSeen, array_flip($cascadeComps)));
    if ($cascadeCount >= 2) {
        return 'Fallo de AzuraCast — el AutoDJ recorrió la cadena de fallback sin encontrar contenido';
    }

    // ── 9. schedule_switch: ¿qué fuente activó el programador? ───────────────
    // schedule_switch logea qué source se selecciona en cada transición.
    // Si hay transiciones cerca del horario pero ninguna es nuestra playlist,
    // podemos indicar qué se activó en su lugar.
    $switchedTo = null;
    $ourSrcActivated = false;
    foreach ($relevant as $line) {
        if ($line['comp'] !== 'schedule_switch') continue;
        $lineMin = _lsToMin(substr($line['time'], 0, 5));
        if ($lineMin < $schedMin - 2 || $lineMin > $schedMin + 5) continue;
        $msg = $line['msg'];
        if (!str_contains($msg, 'Switch to')) continue;

        if (str_contains($msg, $liquidsoapSrcId)) {
            $ourSrcActivated = true; // La playlist sí se activó; el fallo es posterior
            break;
        }
        if (preg_match('/Switch to\s+(\S+)/i', $msg, $m)) {
            $switchedTo = $m[1];
        }
    }
    if (!$ourSrcActivated && $switchedTo !== null) {
        return "Fallo de AzuraCast — el programador activó «{$switchedTo}» en lugar de la playlist";
    }

    // ── 10. Nuestra playlist no aparece en el log del día ─────────────────
    // Si el source ID de la playlist no se menciona en ninguna línea de todo
    // el día, el scheduler nunca intentó activarla.
    $srcMentionedToday = false;
    foreach (($logIndex[$date] ?? []) as $line) {
        if (str_starts_with($line['comp'], $liquidsoapSrcId)
         || str_contains($line['msg'], $liquidsoapSrcId)
        ) {
            $srcMentionedToday = true;
            break;
        }
    }
    if (!$srcMentionedToday) {
        return 'Fallo de AzuraCast — la playlist no aparece en el log de ese día (el programador no la activó)';
    }

    return null;
}

// ── Utilidades internas ───────────────────────────────────────────────────────

/** Filtra las líneas del índice en la ventana temporal indicada. */
function _lsQueryWindow(
    array  $logIndex,
    string $date,
    string $scheduledAt,
    int    $beforeMin,
    int    $afterMin
): array {
    $lines = $logIndex[$date] ?? [];
    if (empty($lines)) return [];

    $schedMin = _lsToMin($scheduledAt);
    $winStart = $schedMin - $beforeMin;
    $winEnd   = $schedMin + $afterMin;

    return array_values(array_filter(
        $lines,
        fn($l) => _lsToMin(substr($l['time'], 0, 5)) >= $winStart
               && _lsToMin(substr($l['time'], 0, 5)) <= $winEnd
    ));
}

/** Convierte "HH:MM" o "HH:MM:SS" a minutos desde medianoche. */
function _lsToMin(string $t): int
{
    $p = explode(':', $t);
    return (int)$p[0] * 60 + (int)($p[1] ?? 0);
}
