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
 *   1.  Programa arrancó tarde (overrun confirmado con minutos exactos)
 *   2.  Playlist vacía, sin cola o no accesible
 *   3.  Archivo de audio no encontrado (ruta exacta)
 *   4.  Archivo de audio corrupto o no decodificable
 *   5.  Error interno de Liquidsoap (lang fatal/error)
 *   6.  Servidor sobrecargado (retraso de reloj acumulado)
 *   7.  Un directo tomó el control en esa franja
 *   8.  AzuraCast activó el AutoDJ fallback (sin contenido programado disponible)
 *   9.  AzuraCast cambió a fuente alternativa (switch_/safe_fallback/mksafe)
 *   10. La jingle de error sonó (indica fallo grave de contenido)
 *   11. Slot expirado: programa anterior se extendió más allá del fin del slot
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
    // Ventana: 15 min antes (ver qué estaba activo) hasta 90 min después
    $relevant = _lsQueryWindow($logIndex, $date, $scheduledAt, 15, 90);
    if (empty($relevant)) return null;

    $schedMin = _lsToMin($scheduledAt);

    // ── 1. ¿Arrancó pero tarde? ───────────────────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] === $liquidsoapSrcId
            && (str_contains($line['msg'], 'Prepared') || str_contains($line['msg'], 'Playing'))
        ) {
            $lineMin  = _lsToMin(substr($line['time'], 0, 5));
            $delayMin = $lineMin - $schedMin;
            if ($delayMin > 2) {
                return "Empezó con {$delayMin} min de retraso — el programa anterior se alargó";
            }
            return null; // Arrancó a tiempo: log no explica el fallo
        }
    }

    // ── 2. Playlist vacía, cola exhausted o no accesible ─────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] !== $liquidsoapSrcId) continue;
        $msg = $line['msg'];
        if (str_contains($msg, "Couldn't read playlist")) {
            if (preg_match('/from\s+"?([^"]+)"?/', $msg, $m)) {
                return 'No se pudo leer la playlist: ' . basename(trim($m[1]));
            }
            return 'No se pudo leer el archivo de playlist';
        }
        if (str_contains($msg, 'Could not queue any track')
         || str_contains($msg, 'Queue is empty')
         || str_contains($msg, 'No track available')
        ) {
            return 'La cola de episodios estaba vacía — no había contenido disponible';
        }
        if (str_contains($msg, 'request resolution failed')
         || str_contains($msg, 'Failed to prepare track')
        ) {
            return 'Liquidsoap no pudo preparar ningún episodio para su reproducción';
        }
        if (str_contains($msg, 'Reload time was set to 0')) {
            return 'La playlist no pudo recargarse y se quedó sin elementos';
        }
    }

    // ── 3. Archivo de audio no encontrado ────────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] !== 'request' || $line['level'] > 2) continue;
        if (str_contains($line['msg'], 'Nonexistent file')
         || str_contains($line['msg'], 'ill-formed URI')
         || str_contains($line['msg'], 'does not exist')
        ) {
            if (preg_match('/"([^"]+)"/', $line['msg'], $m)) {
                return 'Archivo no encontrado: ' . basename($m[1]);
            }
            return 'Archivo de audio no encontrado en disco';
        }
        if (str_contains($line['msg'], 'Permission denied')) {
            if (preg_match('/"([^"]+)"/', $line['msg'], $m)) {
                return 'Sin permiso de lectura: ' . basename($m[1]);
            }
            return 'Sin permiso de lectura del archivo de audio';
        }
    }

    // ── 4. Archivo corrupto o no decodificable ────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] !== 'decoder' || $line['level'] > 2) continue;
        if (str_contains($line['msg'], 'Unable to decode')
         || str_contains($line['msg'], 'could not decode')
         || str_contains($line['msg'], 'No decoder')
        ) {
            if (preg_match('/"([^"]+)"/', $line['msg'], $m)) {
                return 'Archivo corrupto o formato no soportado: ' . basename($m[1]);
            }
            return 'Archivo de audio corrupto o con formato no soportado';
        }
    }

    // ── 5. Error interno de Liquidsoap ────────────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] !== 'lang' || $line['level'] > 2) continue;
        $msg = $line['msg'];
        // Excluir el mensaje de DJ conectado (se diagnostica después)
        if (str_contains($msg, 'DJ Source connected')) continue;
        if (str_contains($msg, 'Fatal')
         || str_contains($msg, 'Error')
         || str_contains($msg, 'error')
         || str_contains($msg, 'exception')
        ) {
            // Devolver un fragmento del mensaje para dar contexto real
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', $msg), 0, 120);
            return 'Error interno de Liquidsoap: ' . $excerpt;
        }
    }

    // ── 6. Servidor sobrecargado ──────────────────────────────────────────────
    foreach ($relevant as $line) {
        if (!str_contains($line['comp'], 'wallclock')) continue;
        if (str_contains($line['msg'], 'catchup') || str_contains($line['msg'], 'late')) {
            if (preg_match('/([\d.]+)\s+seconds?/', $line['msg'], $m)) {
                $secs = (int)round((float)$m[1]);
                return "Servidor sobrecargado — retraso de reloj acumulado: {$secs}s";
            }
            return 'Servidor sobrecargado en esa franja (retraso de reloj)';
        }
    }

    // ── 7. Un directo tomó el control ─────────────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] !== 'lang') continue;
        if (str_contains($line['msg'], 'DJ Source connected')) {
            if (preg_match('/DJ[:\s]+(\S+)/i', $line['msg'], $m)) {
                return 'El directo "' . htmlspecialchars($m[1], ENT_QUOTES) . '" tomó el control en esa franja';
            }
            return 'Un directo tomó el control en esa franja';
        }
        if (str_contains($line['msg'], 'Streaming client connected')
         || str_contains($line['msg'], 'source client connected')
        ) {
            return 'Un cliente de streaming se conectó y tomó el control de la franja';
        }
    }

    // ── 8. AutoDJ fallback ────────────────────────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] === 'autodj_fallback') {
            $lineMin = _lsToMin(substr($line['time'], 0, 5));
            if ($lineMin >= $schedMin - 2) {
                return 'El AutoDJ tomó el control — no había contenido programado disponible en esa franja';
            }
        }
    }

    // ── 9. Cambio a fuente alternativa (switch_ / safe_fallback / mksafe) ────
    foreach ($relevant as $line) {
        $comp = $line['comp'];
        $msg  = $line['msg'];
        $lineMin = _lsToMin(substr($line['time'], 0, 5));
        if ($lineMin < $schedMin - 1) continue; // Solo después del inicio

        if ($comp === 'safe_fallback' && str_contains($msg, 'Switching')) {
            return 'AzuraCast activó el modo de emergencia (safe fallback): no había fuente de audio válida';
        }
        if ($comp === 'mksafe') {
            return 'AzuraCast activó la fuente segura de emergencia (mksafe)';
        }
        if (str_starts_with($comp, 'switch_')
            && (str_contains($msg, 'Switching to') || str_contains($msg, 'switch'))
            && !str_contains($msg, $liquidsoapSrcId)
        ) {
            // Extraer el nombre del destino si está en el mensaje
            if (preg_match('/Switching to\s+(\S+)/i', $msg, $m)) {
                return "AzuraCast cambió a fuente alternativa '{$m[1]}' en lugar de activar este programa";
            }
            return 'AzuraCast cambió a una fuente alternativa sin activar este programa';
        }
    }

    // ── 10. Jingle de error ───────────────────────────────────────────────────
    foreach ($relevant as $line) {
        if ($line['comp'] === 'error_jingle') {
            $lineMin = _lsToMin(substr($line['time'], 0, 5));
            if ($lineMin >= $schedMin - 1) {
                return 'Sonó el jingle de error de AzuraCast — fallo grave en la fuente de audio';
            }
        }
    }

    // ── 11. Slot expirado: source nunca apareció pero otros sí estaban activos
    $sourceHasAnyMsg  = false;
    $activeOtherComps = [];
    foreach ($relevant as $line) {
        if ($line['comp'] === $liquidsoapSrcId) {
            $sourceHasAnyMsg = true;
        } elseif (str_starts_with($line['comp'], 'playlist_')
               || str_starts_with($line['comp'], 'cue_playlist_')
               || str_starts_with($line['comp'], 'switch_')
               || $line['comp'] === 'schedule_switch'
        ) {
            $activeOtherComps[$line['comp']] = true;
        }
    }

    if (!$sourceHasAnyMsg && !empty($activeOtherComps)) {
        // Intentar identificar qué playlist estaba activa
        $otherPlaylists = array_filter(
            array_keys($activeOtherComps),
            fn($c) => str_starts_with($c, 'playlist_') || str_starts_with($c, 'cue_playlist_')
        );
        if (!empty($otherPlaylists)) {
            // Convertir ID de Liquidsoap de vuelta a nombre legible (best-effort)
            $otherName = str_replace(['playlist_', 'cue_playlist_', '_'], ['', '', ' '], array_values($otherPlaylists)[0]);
            return "El slot no llegó a activarse — '{$otherName}' seguía en emisión cuando expiró la franja";
        }
        return 'El slot no llegó a activarse — el programa anterior excedió el tiempo asignado';
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
