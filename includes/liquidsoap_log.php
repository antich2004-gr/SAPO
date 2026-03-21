<?php
// includes/liquidsoap_log.php
// Acceso, parseo y consulta del log de Liquidsoap de AzuraCast.
// Proporciona diagnóstico de causa de emisión perdida con mayor precisión
// que el historial de reproducción solo.

// ── Componentes del log que nos interesan ─────────────────────────────────────
// Filtramos en parseo para mantener el índice en caché lo más compacto posible.
const LS_RELEVANT_PREFIXES = [
    'schedule_switch', 'switch_', 'sequence_', 'autodj_fallback',
    'live_fallback', 'input_streamer',
    'lang', 'request', 'decoder',
    'clock.wallclock', 'safe_fallback', 'error_jingle', 'mksafe',
    'playlist_', 'cue_playlist_',
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
 * Solo guarda líneas de nivel ≤ 3 (Info/Warning/Fatal) de componentes relevantes.
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

        // Descartar debug/trace (nivel 4 y 5)
        if ($level > 3) continue;

        // Descartar componentes que no aportan información de diagnóstico
        $keep = false;
        foreach (LS_RELEVANT_PREFIXES as $prefix) {
            if (str_starts_with($comp, $prefix)) { $keep = true; break; }
        }
        if (!$keep) continue;

        $date            = str_replace('/', '-', $date); // YYYY/MM/DD → YYYY-MM-DD
        $index[$date][]  = compact('time', 'comp', 'level', 'msg');
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
    // Elimina caracteres no permitidos, colapsa puntos dobles, espacios→_, minúsculas
    $s = mb_ereg_replace('([^\w\s\d\-_~,;\[\]\(\).])', '', $playlistName) ?? '';
    $s = mb_ereg_replace('([\.]{2,})', '.', $s) ?? '';
    $s = str_replace(' ', '_', $s);
    $s = mb_strtolower($s);

    // ── Paso 2: cleanUpVarName('playlist_' + s) ──────────────────────────────
    $s = 'playlist_' . $s;
    // Normalizar espacios y eliminar caracteres prohibidos en var names
    $s = strtolower(
        preg_replace(['/[\r\n\t ]+/', '/[\"*\/:<>?\'|]+/'], ' ', strip_tags($s)) ?? ''
    );
    // Convertir entidades HTML (ñ → n, é → e, etc.) extrayendo la letra base
    $s = html_entity_decode($s, ENT_QUOTES, 'utf-8');
    $s = htmlentities($s, ENT_QUOTES, 'utf-8');
    $s = preg_replace('/(&)([a-z])([a-z]+;)/i', '$2', $s) ?? '';
    // URL-encode y eliminar caracteres problemáticos en identificadores Liquidsoap
    $s = rawurlencode(str_replace(' ', '_', $s));
    return str_replace(['%', '-', '.'], ['', '_', '_'], $s);
}

// ── Consulta y diagnóstico ────────────────────────────────────────────────────

/**
 * Diagnostica por qué una emisión fue perdida, usando el índice del log de Liquidsoap.
 *
 * Prioridad de causas detectadas:
 *   1. Programa arrancó tarde (overrun confirmado con minutos exactos)
 *   2. Playlist vacía o no accesible (sin episodio)
 *   3. Archivo de audio no encontrado (ruta exacta)
 *   4. Archivo de audio corrupto o no decodificable
 *   5. Servidor sobrecargado (retraso de reloj acumulado)
 *   6. Un directo tomó el control en esa franja
 *   7. Slot expiró sin activarse (overrun total del programa anterior)
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
    // Ventana de consulta: 5 min antes hasta 90 min después de la hora programada
    $relevant = _lsQueryWindow($logIndex, $date, $scheduledAt, 5, 90);
    if (empty($relevant)) return null;

    $schedMin = _lsToMin($scheduledAt);

    // 1. ¿Arrancó pero tarde? (componente = sourceId con mensaje Prepared/Playing)
    foreach ($relevant as $line) {
        if ($line['comp'] === $liquidsoapSrcId
            && (str_contains($line['msg'], 'Prepared') || str_contains($line['msg'], 'Playing'))
        ) {
            $delayMin = _lsToMin(substr($line['time'], 0, 5)) - $schedMin;
            if ($delayMin > 2) {
                return "Empezó con {$delayMin} min de retraso — el programa anterior se alargó";
            }
            // Arrancó a tiempo: el log no explica el fallo
            return null;
        }
    }

    // 2. Playlist vacía o archivo de lista no accesible
    foreach ($relevant as $line) {
        if ($line['comp'] === $liquidsoapSrcId) {
            $msg = $line['msg'];
            if (str_contains($msg, "Couldn't read playlist")
             || str_contains($msg, 'request resolution failed')
             || str_contains($msg, 'Failed to prepare track')
             || str_contains($msg, 'Queue is empty')
            ) {
                return 'Sin contenido: la playlist estaba vacía o no era accesible';
            }
        }
    }

    // 3. Archivo de audio no encontrado
    foreach ($relevant as $line) {
        if ($line['comp'] === 'request' && $line['level'] <= 2
            && (str_contains($line['msg'], 'Nonexistent file')
             || str_contains($line['msg'], 'ill-formed URI'))
        ) {
            if (preg_match('/"([^"]+)"/', $line['msg'], $m)) {
                return 'Archivo no encontrado: ' . basename($m[1]);
            }
            return 'Archivo de audio no encontrado';
        }
    }

    // 4. Archivo corrupto o no decodificable
    foreach ($relevant as $line) {
        if ($line['comp'] === 'decoder' && $line['level'] <= 2
            && str_contains($line['msg'], 'Unable to decode')
        ) {
            if (preg_match('/"([^"]+)"/', $line['msg'], $m)) {
                return 'Archivo corrupto o no decodificable: ' . basename($m[1]);
            }
            return 'Archivo de audio corrupto o no decodificable';
        }
    }

    // 5. Servidor sobrecargado (retraso de reloj acumulado)
    foreach ($relevant as $line) {
        if (str_contains($line['comp'], 'wallclock')
            && str_contains($line['msg'], 'catchup')
        ) {
            if (preg_match('/([\d.]+)\s+seconds/', $line['msg'], $m)) {
                $secs = (int)round((float)$m[1]);
                return "Servidor sobrecargado (retraso acumulado: {$secs}s)";
            }
            return 'Servidor sobrecargado en esa franja';
        }
    }

    // 6. Un directo tomó el control
    foreach ($relevant as $line) {
        if ($line['comp'] === 'lang'
            && str_contains($line['msg'], 'DJ Source connected')
        ) {
            if (preg_match('/DJ:\s*(\S+)/i', $line['msg'], $m)) {
                return 'El directo "' . htmlspecialchars($m[1], ENT_QUOTES) . '" tomó el control en esa franja';
            }
            return 'Un directo tomó el control en esa franja';
        }
    }

    // 7. Slot expirado sin activarse.
    // Si hay actividad de Liquidsoap en la ventana (otros playlist_/switch_/
    // schedule_switch activos) pero el source esperado nunca apareció, el slot
    // completo fue consumido por el programa anterior antes de que Liquidsoap
    // pudiera seleccionar este source.
    $sourceHasAnyMsg  = false;
    $otherSourceActive = false;
    foreach ($relevant as $line) {
        if ($line['comp'] === $liquidsoapSrcId) {
            $sourceHasAnyMsg = true;
        }
        if (!$otherSourceActive
            && $line['comp'] !== $liquidsoapSrcId
            && (str_starts_with($line['comp'], 'playlist_')
             || str_starts_with($line['comp'], 'cue_playlist_')
             || str_starts_with($line['comp'], 'switch_')
             || $line['comp'] === 'schedule_switch')
        ) {
            $otherSourceActive = true;
        }
    }
    if (!$sourceHasAnyMsg && $otherSourceActive) {
        return 'El slot no llegó a activarse — el programa anterior probablemente se extendió más allá del fin del slot';
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
