<?php
// views/seguimiento_emision.php - Seguimiento de emisiones del mes

// ── Emisora a visualizar ───────────────────────────────────────────────────────
// Usuario normal o admin impersonando → usa la sesión actual.
// Admin directo → selector de emisora o ?station=username.
if (!isAdmin() || isImpersonating()) {
    // Usuario normal (o admin viendo como emisora): usa sus propios datos
    $trackingUsername = $_SESSION['username'];
    $trackingStation  = $_SESSION['station_name'];
} else {
    // Admin directo: leer ?station= o mostrar selector
    $allUsers     = getAllUsers();
    $stationUsers = array_values(array_filter($allUsers, fn($u) => !($u['is_admin'] ?? false)));

    $requestedStation = $_GET['station'] ?? '';
    $validUsernames   = array_column($stationUsers, 'username');

    if ($requestedStation && in_array($requestedStation, $validUsernames, true)) {
        $idx              = array_search($requestedStation, $validUsernames);
        $trackingUsername = $requestedStation;
        $trackingStation  = $stationUsers[$idx]['station_name'] ?? $requestedStation;
    } else {
        // Mostrar selector de emisora
        ?>
        <div class="card">
            <div class="nav-buttons">
                <h2 style="margin:0;">📊 Historial de Emisión</h2>
                <a href="?" class="btn btn-secondary"><span class="btn-icon">⚙️</span> Panel Admin</a>
            </div>
            <div class="section" style="max-width:400px;">
                <h3>Selecciona una emisora</h3>
                <form method="GET">
                    <input type="hidden" name="page" value="seguimiento_emision">
                    <?php if (isset($_GET['month'])): ?>
                    <input type="hidden" name="month" value="<?php echo htmlEsc($_GET['month']); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Emisora</label>
                        <select name="station" class="form-control">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($stationUsers as $u): ?>
                            <option value="<?php echo htmlEsc($u['username']); ?>">
                                <?php echo htmlEsc($u['station_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Ver seguimiento</button>
                </form>
            </div>
        </div>
        <?php
        return;
    }
}

// ── Correcciones manuales ─────────────────────────────────────────────────────
$overrides = loadOverrides($trackingUsername);

// ── Mes objetivo ──────────────────────────────────────────────────────────────
$targetMonth = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']))
    ? $_GET['month']
    : date('Y-m');

$currentMonth = date('Y-m');
$minMonth     = date('Y-m', strtotime('-2 months', strtotime($currentMonth . '-01')));
if ($targetMonth > $currentMonth) $targetMonth = $currentMonth;
if ($targetMonth < $minMonth)     $targetMonth = $minMonth;

$year        = (int)substr($targetMonth, 0, 4);
$month       = (int)substr($targetMonth, 5, 2);
$monthStart  = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $monthStart);
$monthEnd    = mktime(23, 59, 59, $month, $daysInMonth, $year);

// Construir base URL preservando station si aplica
$stationParam = (!isImpersonating() && isset($_GET['station'])) ? '&station=' . urlencode($trackingUsername) : '';
$prevMonth    = date('Y-m', strtotime('-1 month', $monthStart));
$nextMonth    = date('Y-m', strtotime('+1 month', $monthStart));
$canGoNext    = ($nextMonth <= $currentMonth);
$canGoPrev    = ($prevMonth >= $minMonth);

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$monthLabel = $monthNames[$month] . ' ' . $year;

$timezone   = new DateTimeZone('Europe/Madrid');
$today      = date('Y-m-d');

// ── 1. Schedule: qué días de semana y hora tiene cada programa ────────────────
$programSchedules = []; // [name => [[dayOfWeek, startTime, endTime], ...]]

$schedule = getAzuracastSchedule($trackingUsername, 600);

if ($schedule && is_array($schedule)) {
    foreach ($schedule as $event) {
        $name = $event['name'] ?? $event['playlist'] ?? null;
        if (!$name) continue;

        $start = $event['start_timestamp'] ?? $event['start'] ?? null;
        if ($start === null) continue;

        $dt = new DateTime('@' . (is_numeric($start) ? $start : strtotime($start)));
        $dt->setTimezone($timezone);
        $dow     = (int)$dt->format('w'); // 0=Dom … 6=Sáb
        $timeStr = $dt->format('H:i');

        $end = $event['end_timestamp'] ?? $event['end'] ?? null;
        $endTimeStr = null;
        if ($end !== null) {
            $endDt = new DateTime('@' . (is_numeric($end) ? $end : strtotime($end)));
            $endDt->setTimezone($timezone);
            $endTimeStr = $endDt->format('H:i');
        }

        if (!isset($programSchedules[$name])) {
            $programSchedules[$name] = [];
        }

        // Evitar duplicados (mismo día + hora)
        $isDuplicate = false;
        foreach ($programSchedules[$name] as $slot) {
            if ($slot['dayOfWeek'] === $dow && $slot['startTime'] === $timeStr) {
                $isDuplicate = true;
                break;
            }
        }
        if (!$isDuplicate) {
            $programSchedules[$name][] = [
                'dayOfWeek' => $dow,
                'startTime' => $timeStr,
                'endTime'   => $endTimeStr,
            ];
        }
    }
    ksort($programSchedules);
}

// ── Filtrar: excluir música, jingles, huérfanos y 'live' del schedule AzuraCast
// Los 'live' NO vienen del schedule de AzuraCast, se añaden desde schedule_slots
// de SAPO (igual que en la parrilla). Sin catalogar en SAPO → incluir.
$programsDB = loadProgramsDB($trackingUsername);
$dbPrograms = $programsDB['programs'] ?? [];

if (!empty($programSchedules)) {
    foreach (array_keys($programSchedules) as $name) {
        if (!isset($dbPrograms[$name])) continue; // Sin catalogar → incluir
        $type       = $dbPrograms[$name]['playlist_type'] ?? 'program';
        $orphaned   = $dbPrograms[$name]['orphaned'] ?? false;
        $hidden     = !empty($dbPrograms[$name]['hidden_from_schedule']);
        $lastActive = $dbPrograms[$name]['last_active_date'] ?? null;
        // Solo 'program' desde AzuraCast; live se añade aparte.
        // Excepción: mantener si fue dado de baja este mes (para mostrar historial).
        $lastActiveThisMonth = $lastActive && substr($lastActive, 0, 7) === $targetMonth;
        if ($type !== 'program') {
            unset($programSchedules[$name]);
        } elseif (($orphaned || $hidden) && !$lastActiveThisMonth) {
            unset($programSchedules[$name]);
        }
    }
}

// ── Directos (live): horario desde schedule_slots de SAPO ────────────────────
// Para el lookup de historial usamos el nombre original de la playlist
// (sin sufijo ::live). $historyNameMap traduce clave→nombre AzuraCast.
$livePrograms   = []; // [schedKey => true]
$historyNameMap = []; // [schedKey => azuraCastPlaylistName]
$displayNameMap = []; // [schedKey => displayTitle para la tabla]

foreach ($dbPrograms as $programKey => $programInfo) {
    $type       = $programInfo['playlist_type'] ?? 'program';
    $orphaned   = $programInfo['orphaned'] ?? false;
    $hidden     = !empty($programInfo['hidden_from_schedule']);
    $lastActive = $programInfo['last_active_date'] ?? null;
    $lastActiveThisMonth = $lastActive && substr($lastActive, 0, 7) === $targetMonth;
    if ($type !== 'live') continue;
    if (($orphaned || $hidden) && !$lastActiveThisMonth) continue;

    $azName       = $programInfo['original_name'] ?? getProgramNameFromKey($programKey);
    $displayTitle = $programInfo['display_title'] ?: $azName;

    // Igual que la parrilla: prioridad schedule_slots (nuevo), fallback schedule_days (antiguo)
    $rawSlots = [];
    if (!empty($programInfo['schedule_slots'])) {
        $rawSlots = $programInfo['schedule_slots'];
    } elseif (!empty($programInfo['schedule_days']) && !empty($programInfo['schedule_start_time'])) {
        $rawSlots = [[
            'days'       => $programInfo['schedule_days'],
            'start_time' => $programInfo['schedule_start_time'],
            'duration'   => (int)($programInfo['schedule_duration'] ?? 60),
        ]];
    }

    if (empty($rawSlots)) continue;

    $slots = [];
    foreach ($rawSlots as $slot) {
        $scheduleDays = $slot['days'] ?? [];
        $startTime    = $slot['start_time'] ?? '';
        $duration     = (int)($slot['duration'] ?? 60);
        if (empty($scheduleDays) || empty($startTime)) continue;

        foreach ($scheduleDays as $dow) {
            $dow     = (int)$dow;
            $startDt = DateTime::createFromFormat('H:i', $startTime);
            if (!$startDt) continue;
            $endDt = clone $startDt;
            $endDt->modify("+{$duration} minutes");
            $slots[] = [
                'dayOfWeek' => $dow,
                'startTime' => $startDt->format('H:i'),
                'endTime'   => $endDt->format('H:i'),
            ];
        }
    }

    if (!empty($slots)) {
        $programSchedules[$programKey] = $slots;
        $livePrograms[$programKey]     = true;
        $historyNameMap[$programKey]   = $azName;
        $displayNameMap[$programKey]   = $displayTitle;
    }
}

ksort($programSchedules);
$hasSchedule = !empty($programSchedules);

// ── Vincular directos con su programa automático homónimo ─────────────────────
// Si un directo tiene el mismo nombre base que una playlist automática,
// se fusionan en una sola fila (el directo absorbe la celda del automático
// cuando corresponde ese día).
// $liveForAutomated[autoKey] = liveKey
// $absorbedLive[liveKey]     = autoKey  → no muestra fila propia
function _normProgName($s) {
    $s = preg_replace('/\s*[-–]\s*\(di?recto\)\s*$/ui', '', $s);
    $s = preg_replace('/\s*\(di?recto\)\s*$/ui', '', $s);
    $s = preg_replace('/\s*\(\d+h\d*\)\s*$/u', '', $s);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($s)));
    return preg_replace('/\s+/', ' ', trim($ascii !== false ? $ascii : mb_strtolower(trim($s))));
}

$liveForAutomated = []; // [autoKey  => liveKey]
$absorbedLive     = []; // [liveKey  => autoKey]

$_autoNormIdx = [];
foreach ($programSchedules as $k => $_) {
    if (!isset($livePrograms[$k])) {
        $_autoNormIdx[_normProgName($k)] = $k;
    }
}
foreach ($livePrograms as $liveKey => $_) {
    foreach ([$displayNameMap[$liveKey] ?? '', $historyNameMap[$liveKey] ?? ''] as $candidate) {
        if ($candidate === '') continue;
        $norm = _normProgName($candidate);
        if (isset($_autoNormIdx[$norm])) {
            $autoKey = $_autoNormIdx[$norm];
            $liveForAutomated[$autoKey] = $liveKey;
            $absorbedLive[$liveKey]     = $autoKey;
            break;
        }
    }
}

// ── 2. Historial de reproducción del mes ─────────────────────────────────────
// historyMap[azPlaylistName][Y-m-d] = primera hora de emisión detectada ('HH:MM')
// liveSessionStarts[Y-m-d] = ['HH:MM', ...] → hora de INICIO de cada sesión DJ
// (agrupando canciones continuas; evita falsos positivos con entradas mid-sesión)
$historyMap        = [];
$historyDetails    = []; // [playlist][Y-m-d] = ['time'=>'HH:MM','title'=>...,'duration'=>secs]
$historyEntryByDay = []; // [Y-m-d][] = ['playlist'=>...,'ts'=>...,'duration'=>secs]
$liveSessionStarts    = [];
$liveSessionStreamers = []; // [Y-m-d => ['HH:MM' => 'nombre_streamer']]
$liveSessionDurations = []; // [Y-m-d => ['HH:MM' => duracion_en_segundos|null]]
$liveSessionStartTs   = []; // [Y-m-d => ['HH:MM' => unix_timestamp_inicio]] para broadcasts en curso
$dayTimeline          = []; // [Y-m-d => [['playlist'=>..., 'time'=>'HH:MM'], ...]]
$historyError         = false;

if ($hasSchedule) {
    // ── Fuente 1 (fiable): broadcasts por streamer desde la API de AzuraCast ──
    // Cada broadcast tiene inicio exacto, fin exacto y nombre del DJ registrado.
    $broadcasts = getAzuracastStreamerBroadcasts($trackingUsername, $monthStart, $monthEnd);
    if (is_array($broadcasts)) {
        // Ordenar por inicio para que la primera sesión del día se registre primero
        usort($broadcasts, fn($a, $b) => $a['start'] <=> $b['start']);
        foreach ($broadcasts as $bc) {
            $dt = new DateTime('@' . $bc['start']);
            $dt->setTimezone($timezone);
            $dayStr  = $dt->format('Y-m-d');
            $timeKey = $dt->format('H:i');
            $liveSessionStarts[$dayStr][]            = $timeKey;
            $liveSessionStreamers[$dayStr][$timeKey] = $bc['name'];
            $liveSessionDurations[$dayStr][$timeKey] = $bc['duration']; // null si aún en curso
            $liveSessionStartTs[$dayStr][$timeKey]   = $bc['start'];    // timestamp Unix exacto
        }
    }

    // ── Fuente 2 (historial de canciones): programas automáticos + fallback live ──
    $history = getAzuracastHistory($trackingUsername, $monthStart, $monthEnd);

    if ($history === false) {
        $historyError = true;
    } elseif (is_array($history)) {
        // Recoger timestamps con streamer por día para fallback si la API de
        // broadcasts no devolvió datos (emisoras sin streamers registrados en AzuraCast)
        $streamerTsFallback = [];

        foreach ($history as $entry) {
            $playlist = $entry['playlist'] ?? null;
            $streamer = trim($entry['streamer'] ?? '');
            $playedAt = $entry['played_at'] ?? null;
            if (!$playedAt) continue;

            $playedDt = new DateTime('@' . $playedAt);
            $playedDt->setTimezone($timezone);
            $dayStr  = $playedDt->format('Y-m-d');
            $timeStr = $playedDt->format('H:i');

            // Programas automáticos: indexar por playlist
            if ($playlist) {
                $entryTs  = (int)$playedAt;
                $entryDur = (int)($entry['duration'] ?? 0);
                if (!isset($historyMap[$playlist][$dayStr])) {
                    $historyMap[$playlist][$dayStr]     = $timeStr;
                    $historyDetails[$playlist][$dayStr] = [
                        'time'     => $timeStr,
                        'title'    => $entry['song']['text'] ?? $entry['text'] ?? '',
                        'start_ts' => $entryTs,
                        'span_end' => $entryTs + $entryDur,
                    ];
                } else {
                    // El historial de AzuraCast viene de más reciente a más antiguo:
                    // actualizar start_ts/time/title si encontramos un tema más temprano
                    if ($entryTs < $historyDetails[$playlist][$dayStr]['start_ts']) {
                        $historyDetails[$playlist][$dayStr]['start_ts'] = $entryTs;
                        $historyDetails[$playlist][$dayStr]['time']     = $timeStr;
                        $historyDetails[$playlist][$dayStr]['title']    = $entry['song']['text'] ?? $entry['text'] ?? '';
                        $historyMap[$playlist][$dayStr]                 = $timeStr;
                    }
                    // Mantener el fin más tardío para calcular duración real total
                    $spanEnd = $entryTs + $entryDur;
                    if ($spanEnd > $historyDetails[$playlist][$dayStr]['span_end']) {
                        $historyDetails[$playlist][$dayStr]['span_end'] = $spanEnd;
                    }
                }
                $historyEntryByDay[$dayStr][] = [
                    'playlist' => $playlist,
                    'ts'       => (int)$playedAt,
                    'duration' => (int)($entry['duration'] ?? 0),
                ];
                $dayTimeline[$dayStr][] = ['playlist' => $playlist, 'time' => $timeStr];
            }

            // Acumular para fallback de live (solo si broadcasts no lo cubrió)
            if ($streamer !== '') {
                $streamerTsFallback[$dayStr][] = [
                    'ts'       => (int)$playedAt,
                    'name'     => $streamer,
                    'duration' => (int)($entry['duration'] ?? 0),
                ];
            }
        }

        // Fallback por día: para días donde el API de broadcasts no aportó sesiones,
        // inferir sesiones desde el campo streamer del historial de canciones.
        // Esto cubre emisoras sin streamers registrados en AzuraCast o días concretos
        // donde el broadcast no fue capturado por el API pero sí por el historial.
        foreach ($streamerTsFallback as $dayStr => $entries) {
            if (!empty($liveSessionStarts[$dayStr])) continue; // ya cubierto por broadcasts
            usort($entries, fn($a, $b) => $a['ts'] <=> $b['ts']);
            $sessionStartTs  = null;
            $sessionStartKey = null;
            $prevTs          = null;
            $prevDur         = 0;
            foreach ($entries as $entry) {
                if ($sessionStartTs === null || ($entry['ts'] - $prevTs) > 1800) {
                    if ($sessionStartKey !== null) {
                        $liveSessionDurations[$dayStr][$sessionStartKey] = ($prevTs + $prevDur) - $sessionStartTs ?: null;
                    }
                    $dt = new DateTime('@' . $entry['ts']);
                    $dt->setTimezone($timezone);
                    $timeKey = $dt->format('H:i');
                    $liveSessionStarts[$dayStr][]            = $timeKey;
                    $liveSessionStreamers[$dayStr][$timeKey] = $entry['name'];
                    $sessionStartTs  = $entry['ts'];
                    $sessionStartKey = $timeKey;
                }
                $prevTs  = $entry['ts'];
                $prevDur = $entry['duration'];
            }
            if ($sessionStartKey !== null) {
                $liveSessionDurations[$dayStr][$sessionStartKey] = ($prevTs + $prevDur) - $sessionStartTs ?: null;
            }
        }
    }
}

// ── 3. Calcular días del mes: número, día semana, fecha Y-m-d ─────────────────
$days = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $ts     = mktime(0, 0, 0, $month, $d, $year);
    $dt     = new DateTime('@' . $ts);
    $dt->setTimezone($timezone);
    $days[] = [
        'num'    => $d,
        'dow'    => (int)$dt->format('w'),
        'date'   => $dt->format('Y-m-d'),
        'isToday'=> ($dt->format('Y-m-d') === $today),
    ];
}

$dowLabels = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];

// ── Cargar informes diarios del mes ──────────────────────────────────────────
// dailyReports[Y-m-d] = parsed report (carpetas_vacias, errores_podget, emisiones_directo…)
// liveEmissionsPerDay[Y-m-d] = ['HH:MM', ...] — horas de inicio de directos
$dailyReports        = [];
$liveEmissionsPerDay = [];

$reportsPath = getReportsPath($trackingUsername);
if ($reportsPath && is_dir($reportsPath)) {
    foreach ($days as $day) {
        if ($day['date'] > $today) continue;
        $d  = substr($day['date'], 8, 2);
        $mo = substr($day['date'], 5, 2);
        $yr = substr($day['date'], 0, 4);
        $fn = $reportsPath . '/Informe_diario_' . $d . '_' . $mo . '_' . $yr . '.log';
        if (!file_exists($fn)) continue;
        $rd = parseReportFile($fn);
        if (!$rd) continue;
        $dailyReports[$day['date']] = $rd;
        // Extraer horas de directos para la detección de emisiones en vivo
        if (!empty($rd['emisiones_directo'])) {
            $times = [];
            foreach ($rd['emisiones_directo'] as $entry) {
                if (preg_match('/^-\s+[\d-]+\s+(\d{2}:\d{2})/', $entry, $m)) {
                    $times[] = $m[1];
                }
            }
            if ($times) $liveEmissionsPerDay[$day['date']] = $times;
        }
    }
}

// ── 4. Log de Liquidsoap (diagnóstico de causa exacta) ───────────────────────
// Índice parseado [Y-m-d => líneas relevantes]. null si no disponible/sin permisos.
// Caché de 30 min; para meses pasados el log puede estar rotado (devuelve sin esas fechas).
$liquidsoapLog = $hasSchedule ? getLiquidsoapLogIndex($trackingUsername) : null;

// ── Helpers de tiempo ─────────────────────────────────────────────────────────
function timeToMinutes($time) {
    [$h, $m] = explode(':', $time);
    return (int)$h * 60 + (int)$m;
}

// Devuelve true si una emisión en directo (con startTime) se solapa con la
// ventana esperada: el directo empieza a ±45 min del horario programado.
function liveTiempoCoincide($scheduledTime, $emStart) {
    $diff = abs(timeToMinutes($scheduledTime) - timeToMinutes($emStart));
    $diff = min($diff, 1440 - $diff); // ajuste medianoche
    return $diff <= 45;
}

// ── Helper: inferir si había episodio en la playlist ese día ─────────────────
// Devuelve 'missing' | 'available' | 'unknown'
function _inferEpisodeStatus(?array $plInfo, int $schedTs): string {
    if ($plInfo === null) return 'unknown';

    $n = (int)($plInfo['num_songs'] ?? 0);
    if ($n === 0) return 'missing';

    // Método 1: mtime del fichero más antiguo de la playlist
    $mtime = $plInfo['earliest_mtime'] ?? null;
    if ($mtime !== null) {
        return $mtime > $schedTs ? 'missing' : 'available';
    }

    // Método 2: cruzar episodio antes/después con el episodio actual
    $lastBefore  = $plInfo['last_played_before'] ?? null; // ['date', 'title']
    $firstAfter  = $plInfo['first_played_after']  ?? null;
    $curTitle    = $plInfo['sample'][0]['title'] ?? '';
    $curPath     = $plInfo['sample'][0]['path']  ?? '';
    $curLabel    = $curTitle !== '' ? $curTitle : ($curPath !== '' ? basename($curPath) : '');

    if ($firstAfter !== null) {
        $afterTitle  = $firstAfter['title'] ?? '';
        if ($lastBefore !== null) {
            $beforeTitle = $lastBefore['title'] ?? '';
            if ($beforeTitle !== '' && $afterTitle !== '') {
                // Episodio distinto: el nuevo llegó después del fallo
                // Mismo episodio: seguía en la playlist → disponible ese día
                return $beforeTitle !== $afterTitle ? 'missing' : 'available';
            }
        } else {
            // Sin emisión anterior: el programa se emitió por primera vez después del fallo
            return 'missing';
        }
    }

    if ($lastBefore !== null) {
        $beforeTitle = $lastBefore['title'] ?? '';
        if ($curLabel !== '' && $beforeTitle !== '') {
            // Mismo episodio que el último emitido → seguía en playlist → disponible
            // Distinto: el anterior ya se eliminó y el nuevo no estaba listo
            return $curLabel === $beforeTitle ? 'available' : 'missing';
        }
    }

    return 'unknown';
}

// ── Helper: diagnóstico de emisión perdida ────────────────────────────────────
// Prioridad: 1. Sin episodio  2. Overrun > 15 min  3. Fallo de AzuraCast
function getMissedReason(
    $scheduledAt,
    $date,
    $dayTimeline,
    $isLive              = false,
    $programKey          = '',
    $dailyReport         = null,
    $liquidsoapLog       = null,
    $liquidsoapSrcId     = null,
    $historyEntryByDay   = [],
    $programSchedules    = [],
    $playlistContentInfo = null,
    $playlistDisplayName = []
) {
    // ── Directos ─────────────────────────────────────────────────────────────
    if ($isLive) {
        if ($liquidsoapLog !== null && $liquidsoapSrcId !== null) {
            $fromLog = diagnoseMissedFromLog($liquidsoapLog, $date, $scheduledAt, $liquidsoapSrcId);
            if ($fromLog !== null) return $fromLog;
        }
        $entries = $dayTimeline[$date] ?? [];
        return empty($entries)
            ? 'Sin actividad en AzuraCast ese día (posible corte de señal)'
            : null; // La fila en rojo sin datos ya comunica que no hubo emisión
    }

    // Timestamp Unix del inicio programado (necesario en varios puntos)
    $schedTs = mktime(
        (int)substr($scheduledAt, 0, 2),
        (int)substr($scheduledAt, 3, 2),
        0,
        (int)substr($date, 5, 2),
        (int)substr($date, 8, 2),
        (int)substr($date, 0, 4)
    );

    // ── PRIORIDAD 1: Sin episodio ─────────────────────────────────────────────
    // 1a. Informe diario: carpeta vacía
    if ($dailyReport && !empty($dailyReport['carpetas_vacias']) && $programKey !== '') {
        $normKey = _normProgName($programKey);
        foreach ($dailyReport['carpetas_vacias'] as $folder) {
            if (_normProgName($folder['nombre']) === $normKey) {
                return 'Sin episodio disponible';
            }
        }
    }
    // 1b. Informe diario: error de descarga
    if ($dailyReport && !empty($dailyReport['errores_podget']) && $programKey !== '') {
        $normKey = _normProgName($programKey);
        foreach ($dailyReport['errores_podget'] as $err) {
            if (stripos(_normProgName($err), $normKey) !== false) {
                return 'Sin episodio disponible';
            }
        }
    }
    // 1c. Estado actual de la playlist + cross-reference con historial
    $epStatus = _inferEpisodeStatus($playlistContentInfo, $schedTs);
    if ($epStatus === 'missing') return 'Sin episodio disponible';

    // ── Sin actividad en AzuraCast ese día ────────────────────────────────────
    if (empty($dayTimeline[$date] ?? [])) {
        return 'Sin actividad en AzuraCast ese día (posible corte de señal)';
    }

    // ── PRIORIDAD 2: Contenido anterior se alargó más de 15 min ──────────────
    // (umbral basado en la ventana de gracia de AzuraCast para schedules)
    $activeAtStart = null;
    foreach ($historyEntryByDay[$date] ?? [] as $he) {
        if ($he['ts'] <= $schedTs && ($he['ts'] + $he['duration']) > $schedTs) {
            $activeAtStart = $he;
            break;
        }
    }
    if ($activeAtStart !== null) {
        $overrunMin = (int)ceil(($activeAtStart['ts'] + $activeAtStart['duration'] - $schedTs) / 60);
        if ($overrunMin > 15) {
            $pl       = $activeAtStart['playlist'];
            $dispName = $playlistDisplayName[$pl] ?? $pl;
            return "«{$dispName}» se alargó {$overrunMin} min sobre su horario";
        }
    }

    // ── PRIORIDAD 3: Fallo de AzuraCast ──────────────────────────────────────
    if ($liquidsoapLog !== null && $liquidsoapSrcId !== null) {
        $fromLog = diagnoseMissedFromLog($liquidsoapLog, $date, $scheduledAt, $liquidsoapSrcId);
        if ($fromLog !== null) return $fromLog;
    }

    // Si el log no aporta evidencia de fallo técnico y el episodio es 'available'
    // (confirmado que sí había contenido), entonces sí es un fallo de AzuraCast.
    // En caso 'unknown' (no podemos confirmarlo), la causa más probable es falta de episodio.
    if ($epStatus === 'available') {
        return 'Fallo de AzuraCast — la playlist no se activó';
    }

    return 'Sin episodio disponible';
}

// ── Helper: estado de una celda ───────────────────────────────────────────────
function cellStatus($programKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides = [], $liveSessionStreamers = []) {
    $slots = array_filter(
        $programSchedules[$programKey] ?? [],
        fn($s) => $s['dayOfWeek'] === $day['dow']
    );

    if (empty($slots)) {
        return ['status' => 'none'];
    }

    $slot        = array_values($slots)[0];
    $scheduledAt = $slot['startTime'];
    $scheduledEnd = $slot['endTime'] ?? null;

    // Día futuro → esperado (gris)
    if ($day['date'] > $today) {
        return ['status' => 'expected', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd];
    }

    // Hoy: si la hora programada aún no ha pasado → esperado (gris)
    if ($day['date'] === $today && date('H:i') < $scheduledAt) {
        return ['status' => 'expected', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd];
    }

    // Directo: verificar en historial AzuraCast (streamer) e informes diarios
    if (isset($livePrograms[$programKey])) {
        // Fuente 1: historial AzuraCast — entradas con streamer activo
        foreach ($liveSessionStarts[$day['date']] ?? [] as $t) {
            if (liveTiempoCoincide($scheduledAt, $t)) {
                $streamerName = $liveSessionStreamers[$day['date']][$t] ?? '';
                return ['status' => 'played', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd, 'time' => $t, 'streamer' => $streamerName];
            }
        }
        // Fuente 2: informes diarios (sección "Emisiones en directo:")
        foreach ($liveEmissionsPerDay[$day['date']] ?? [] as $t) {
            if (liveTiempoCoincide($scheduledAt, $t)) {
                return ['status' => 'played', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd, 'time' => $t, 'streamer' => ''];
            }
        }
        // Override manual para directos
        if (isset($overrides[$programKey][$day['date']])) {
            $ov = $overrides[$programKey][$day['date']];
            return ['status' => 'played', 'manual' => true, 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd,
                    'reason' => $ov['reason'] ?? '', 'corrected_by' => $ov['corrected_by'] ?? '', 'corrected_at' => $ov['corrected_at'] ?? ''];
        }
        return ['status' => 'missed', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd];
    }

    // Programa automatizado: verificar en historial AzuraCast
    $historyKey = $historyNameMap[$programKey] ?? $programKey;
    $firstPlay  = $historyMap[$historyKey][$day['date']] ?? null;

    if ($firstPlay !== null) {
        return ['status' => 'played', 'scheduledAt' => $scheduledAt, 'time' => $firstPlay];
    }

    // Override manual para automáticos
    if (isset($overrides[$programKey][$day['date']])) {
        $ov = $overrides[$programKey][$day['date']];
        return ['status' => 'played', 'manual' => true, 'scheduledAt' => $scheduledAt,
                'reason' => $ov['reason'] ?? '', 'corrected_by' => $ov['corrected_by'] ?? '', 'corrected_at' => $ov['corrected_at'] ?? ''];
    }
    return ['status' => 'missed', 'scheduledAt' => $scheduledAt];
}

// ── Fechas de inicio y fin de cada programa ───────────────────────────────────
// programFirstSeen: no marcar como perdido días previos al alta del programa.
// programLastActive: no marcar como perdido días posteriores a la baja.
$programFirstSeen  = []; // [progKey => 'Y-m-d']
$programLastActive = []; // [progKey => 'Y-m-d']
foreach (array_keys($programSchedules) as $progKey) {
    $info = $dbPrograms[$progKey] ?? null;
    if (!$info) continue;
    if (!empty($info['first_seen_date'])) {
        $programFirstSeen[$progKey] = $info['first_seen_date'];
    } elseif (!empty($info['created_at'])) {
        $programFirstSeen[$progKey] = substr($info['created_at'], 0, 10);
    }
    if (!empty($info['last_active_date'])) {
        $programLastActive[$progKey] = $info['last_active_date'];
    }
}

// ── Pre-cálculo de totales ────────────────────────────────────────────────────
$totals = [
    'emite_ok'       => 0,
    'faltan'         => 0,
    'live_esperados' => 0,
    'live_efectivos' => 0,
];

if ($hasSchedule) {
    foreach (array_keys($programSchedules) as $progKey) {
        // Skip absorbed live programs — their stats are counted via the automated row
        if (isset($absorbedLive[$progKey])) continue;

        $isLive        = isset($livePrograms[$progKey]);
        $linkedLiveKey = $liveForAutomated[$progKey] ?? null;

        foreach ($days as $day) {
            // No contar días fuera del periodo activo del programa
            $firstSeen  = $programFirstSeen[$progKey] ?? null;
            $lastActive = $programLastActive[$progKey] ?? null;
            if ($firstSeen && $day['date'] < $firstSeen) continue;
            if ($lastActive && $day['date'] > $lastActive) continue;

            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides, $liveSessionStreamers);
            $status = $cell['status'];
            if ($isLive) {
                if ($status === 'played')     { $totals['live_esperados']++; $totals['live_efectivos']++; }
                elseif ($status === 'missed') { $totals['live_esperados']++; }
            } else {
                if ($status === 'played')     $totals['emite_ok']++;
                elseif ($status === 'missed') $totals['faltan']++;
            }

            // Count stats for the linked live program (merged into this row)
            if ($linkedLiveKey !== null) {
                $liveFirstSeen  = $programFirstSeen[$linkedLiveKey] ?? null;
                $liveLastActive = $programLastActive[$linkedLiveKey] ?? null;
                $liveActiveOnDay = (!$liveFirstSeen || $day['date'] >= $liveFirstSeen)
                                && (!$liveLastActive || $day['date'] <= $liveLastActive);
                if ($liveActiveOnDay) {
                    $liveCell   = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides, $liveSessionStreamers);
                    $liveStatus = $liveCell['status'];
                    if ($liveStatus === 'played')     { $totals['live_esperados']++; $totals['live_efectivos']++; }
                    elseif ($liveStatus === 'missed') { $totals['live_esperados']++; }
                }
            }
        }
    }
}
$totals['emitidos_azura'] = $totals['emite_ok'] + $totals['live_efectivos'];

// ── Mapa inverso: azPlaylistName → nombre de display ─────────────────────────
$playlistDisplayName = [];
foreach ($historyNameMap as $pk => $azName) {
    $playlistDisplayName[$azName] = displayName($displayNameMap[$pk] ?? $pk);
}
foreach ($displayNameMap as $pk => $dispName) {
    if (!isset($playlistDisplayName[$pk])) {
        $playlistDisplayName[$pk] = displayName($dispName);
    }
}

// ── Fuente única de verdad: listadoDetails primero, badge deriva de él ────────
// Solo se añaden días con estado definitivo ('played' o 'missed').
// Los días futuros/expected/none no se registran → el badge siempre coincide.
$listadoDetails = []; // [progKey][Y-m-d] = [...]
if ($hasSchedule) {
    foreach (array_keys($programSchedules) as $progKey) {
        if (isset($absorbedLive[$progKey])) continue;
        $linkedLiveKey = $liveForAutomated[$progKey] ?? null;
        $firstSeen     = $programFirstSeen[$progKey] ?? null;
        $lastActive    = $programLastActive[$progKey] ?? null;

        foreach ($days as $day) {
            $date = $day['date'];
            if ($date > $today) continue;

            // Fuera del rango activo del programa
            if (($firstSeen && $date < $firstSeen) || ($lastActive && $date > $lastActive)) continue;

            // ── Determinar estado: misma lógica que el antiguo progSummary ───
            $isLiveDay    = false;
            $effectiveKey = $progKey;
            $usedLive     = false;
            $cellResult   = null;

            if ($linkedLiveKey !== null) {
                $lf = $programFirstSeen[$linkedLiveKey] ?? null;
                $la = $programLastActive[$linkedLiveKey] ?? null;
                $liveActive = (!$lf || $date >= $lf) && (!$la || $date <= $la);
                if ($liveActive) {
                    $lc = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today,
                                     $historyNameMap, $livePrograms, $liveEmissionsPerDay,
                                     $liveSessionStarts, $overrides, $liveSessionStreamers);
                    if ($lc['status'] === 'played' || $lc['status'] === 'missed') {
                        $cellResult   = $lc;
                        $effectiveKey = $linkedLiveKey;
                        $isLiveDay    = true;
                        $usedLive     = true;
                    }
                    // live devuelve 'none'/'expected': no está programado ese día de la semana
                    // → NO marcar $usedLive; dejar que el bloque de automático evalúe.
                }
            }

            if (!$usedLive) {
                $c = cellStatus($progKey, $day, $programSchedules, $historyMap, $today,
                               $historyNameMap, $livePrograms, $liveEmissionsPerDay,
                               $liveSessionStarts, $overrides, $liveSessionStreamers);
                if ($c['status'] === 'played' || $c['status'] === 'missed') {
                    $cellResult = $c;
                }
            }

            // Solo registramos días con estado definitivo
            if ($cellResult === null) continue;
            $status = $cellResult['status']; // 'played' or 'missed'

            // Slot para la hora teórica (preferir automated, si no existe usar live)
            $slots = array_filter(
                $programSchedules[$progKey] ?? [],
                fn($s) => $s['dayOfWeek'] === $day['dow']
            );
            if (empty($slots) && $isLiveDay) {
                $slots = array_filter(
                    $programSchedules[$linkedLiveKey] ?? [],
                    fn($s) => $s['dayOfWeek'] === $day['dow']
                );
            }
            if (empty($slots)) continue;

            $slot      = array_values($slots)[0];
            $schTime   = $slot['startTime'];
            $schEnd    = $slot['endTime'] ?? null;
            $schDurMin = null;
            if ($schEnd) {
                $schDurMin = timeToMinutes($schEnd) - timeToMinutes($schTime);
                if ($schDurMin < 0) $schDurMin += 1440;
            }
            // Fallback: si AzuraCast no tiene duración útil, usar la configurada
            // en la ficha de SAPO (schedule_slots.duration) para ese día y hora.
            if (!$schDurMin) {
                $dbKey      = $isLiveDay ? ($linkedLiveKey ?? $progKey) : $progKey;
                $rawSlotsSS = $dbPrograms[$dbKey]['schedule_slots'] ?? [];
                if (empty($rawSlotsSS) && isset($dbPrograms[$dbKey]['schedule_duration'])) {
                    $rawSlotsSS = [[
                        'days'       => $dbPrograms[$dbKey]['schedule_days'] ?? [],
                        'start_time' => $dbPrograms[$dbKey]['schedule_start_time'] ?? '',
                        'duration'   => (int)$dbPrograms[$dbKey]['schedule_duration'],
                    ]];
                }
                foreach ($rawSlotsSS as $ss) {
                    $ssDow = array_map('intval', $ss['days'] ?? []);
                    if (!in_array($day['dow'], $ssDow, true)) continue;
                    if (($ss['start_time'] ?? '') !== $schTime) continue;
                    $dur = (int)($ss['duration'] ?? 0);
                    if ($dur > 0) {
                        $schDurMin = $dur;
                        $endDt = DateTime::createFromFormat('H:i', $schTime);
                        $endDt->modify("+{$dur} minutes");
                        $schEnd = $endDt->format('H:i');
                    }
                    break;
                }
                // Fallback 2: misma hora, cualquier día
                if (!$schDurMin && $schTime) {
                    foreach ($rawSlotsSS as $ss) {
                        if (($ss['start_time'] ?? '') !== $schTime) continue;
                        $dur = (int)($ss['duration'] ?? 0);
                        if ($dur > 0) {
                            $schDurMin = $dur;
                            $endDt = DateTime::createFromFormat('H:i', $schTime);
                            $endDt->modify("+{$dur} minutes");
                            $schEnd = $endDt->format('H:i');
                        }
                        break;
                    }
                }
                // Fallback 3: slot único configurado, usar su duración independientemente
                if (!$schDurMin && count($rawSlotsSS) === 1) {
                    $dur = (int)($rawSlotsSS[0]['duration'] ?? 0);
                    if ($dur > 0) {
                        $schDurMin = $dur;
                        $endDt = DateTime::createFromFormat('H:i', $schTime);
                        $endDt->modify("+{$dur} minutes");
                        $schEnd = $endDt->format('H:i');
                    }
                }
            }

            // Hora real e historial
            $hk2      = $historyNameMap[$effectiveKey] ?? $effectiveKey;
            $realTime = $historyMap[$hk2][$date] ?? null;

            $epTitle    = null;
            $realDurSec = null;
            if ($status === 'played') {
                $det = $historyDetails[$hk2][$date] ?? null;
                if ($det) {
                    $epTitle = $det['title'] ?: null;
                    // Duración real = span desde el primer tema hasta el final del último
                    $startTs = $det['start_ts'] ?? 0;
                    $spanEnd = $det['span_end'] ?? 0;
                    $realDurSec = ($spanEnd > $startTs) ? ($spanEnd - $startTs) : null;
                }
            }

            // Razón de fallo
            $missedReason = null;
            if ($status === 'missed') {
                $dailyRep  = $dailyReports[$date] ?? null;
                // Nombre AzuraCast de la playlist efectiva → ID de source en Liquidsoap
                $azName    = $historyNameMap[$effectiveKey] ?? $effectiveKey;
                $lsSrcId   = computeLiquidsoapSourceId($azName);
                // Contenido actual de la playlist (best-effort; null si no disponible)
                // Comprobar playlist solo si este día concreto NO es live (aunque el programa
                // tenga otros días live). En días live no hay playlist que analizar.
                $plContentInfo = (!$isLiveDay && $hasSchedule)
                    ? getPlaylistContentInfo($trackingUsername, $azName)
                    : null;

                // Enriquecer con datos del historial: último y primer episodio emitido
                // antes/después de $date — permite determinar si había episodio ese día.
                if ($plContentInfo !== null && isset($historyDetails[$azName])) {
                    $hDates = array_keys($historyDetails[$azName]);
                    sort($hDates);
                    $lastBefore = null;
                    $firstAfter = null;
                    foreach ($hDates as $hd) {
                        if ($hd < $date) {
                            $lastBefore = $hd;
                        } elseif ($hd > $date && $firstAfter === null) {
                            $firstAfter = $hd;
                        }
                    }
                    $plContentInfo['last_played_before'] = $lastBefore !== null ? [
                        'date'  => $lastBefore,
                        'title' => $historyDetails[$azName][$lastBefore]['title'] ?? '',
                    ] : null;
                    $plContentInfo['first_played_after'] = $firstAfter !== null ? [
                        'date'  => $firstAfter,
                        'title' => $historyDetails[$azName][$firstAfter]['title'] ?? '',
                    ] : null;
                }

                $missedReason = getMissedReason($schTime, $date, $dayTimeline,
                                               $isLiveDay || isset($livePrograms[$progKey]),
                                               $effectiveKey, $dailyRep,
                                               $liquidsoapLog, $lsSrcId,
                                               $historyEntryByDay, $programSchedules,
                                               $plContentInfo, $playlistDisplayName);
            }

            // Para directos: streamer, hora real y duración de la sesión.
            $liveStreamer = $cellResult['streamer'] ?? '';
            if ($isLiveDay || isset($livePrograms[$progKey])) {
                // Fuente 1 (AzuraCast): cellResult['time'] ya es la hora de sesión real
                if (!$realTime && isset($cellResult['time'])) {
                    $realTime = $cellResult['time'];
                }
                if (!$realDurSec && $realTime) {
                    $dur = $liveSessionDurations[$date][$realTime] ?? null;
                    if ($dur !== null) {
                        $realDurSec = $dur;
                    } elseif (isset($liveSessionStartTs[$date][$realTime])) {
                        // Broadcast aún en curso: mostrar tiempo transcurrido desde el inicio
                        $realDurSec = max(0, time() - $liveSessionStartTs[$date][$realTime]);
                    }
                }
                // Fuente 2/manual: cruzar por hora programada si aún faltan datos
                if (!$liveStreamer || !$realTime) {
                    foreach ($liveSessionStreamers[$date] ?? [] as $sessionTime => $sName) {
                        if (liveTiempoCoincide($schTime, $sessionTime)) {
                            if (!$liveStreamer) $liveStreamer = $sName;
                            if (!$realTime)     $realTime     = $sessionTime;
                            if (!$realDurSec) {
                                $dur = $liveSessionDurations[$date][$sessionTime] ?? null;
                                if ($dur !== null) {
                                    $realDurSec = $dur;
                                } elseif (isset($liveSessionStartTs[$date][$sessionTime])) {
                                    $realDurSec = max(0, time() - $liveSessionStartTs[$date][$sessionTime]);
                                }
                            }
                            break;
                        }
                    }
                }
            }

            $listadoDetails[$progKey][$date] = [
                'status'       => $status,
                'isLive'       => $isLiveDay || isset($livePrograms[$progKey]),
                'isManual'     => !empty($cellResult['manual']),
                'schTime'      => $schTime,
                'schEnd'       => $schEnd,
                'schDurMin'    => $schDurMin,
                'realTime'     => $realTime,
                'realDurSec'   => $realDurSec,
                'title'        => $epTitle,
                'streamer'     => $liveStreamer,
                'missedReason' => $missedReason,
            ];
        }
    }
}

// ── Badge/resumen: deriva directamente de listadoDetails ─────────────────────
$progSummary = [];
if ($hasSchedule) {
    foreach (array_keys($programSchedules) as $progKey) {
        if (isset($absorbedLive[$progKey])) continue;
        $linkedLiveKey = $liveForAutomated[$progKey] ?? null;
        $s = ['played' => 0, 'missed' => 0, 'live_played' => 0, 'live_missed' => 0, 'linkedLiveKey' => $linkedLiveKey];
        foreach ($listadoDetails[$progKey] ?? [] as $em) {
            if ($em['isLive']) {
                if ($em['status'] === 'played') $s['live_played']++;
                elseif ($em['status'] === 'missed') $s['live_missed']++;
            } else {
                if ($em['status'] === 'played') $s['played']++;
                elseif ($em['status'] === 'missed') $s['missed']++;
            }
        }
        $progSummary[$progKey] = $s;
    }
    // Ordenar: más fallos primero
    uasort($progSummary, function($a, $b) {
        return ($b['missed'] + $b['live_missed']) - ($a['missed'] + $a['live_missed']);
    });
}

// ── Vista cronológica: lista plana inversa de todas las emisiones ──────────────
$cronologicoRows = [];
if ($hasSchedule) {
    $groupIdx = 0;
    foreach ($listadoDetails as $progKey => $dates) {
        if (isset($absorbedLive[$progKey])) continue;
        $progDisplay    = $displayNameMap[$progKey] ?? $progKey;
        $linkedLiveKey  = $liveForAutomated[$progKey] ?? null;
        foreach ($dates as $date => $det) {
            if ($det['status'] !== 'played' && $det['status'] !== 'missed') continue;
            $parts = explode('-', $date);
            $dow   = (int)date('w', mktime(0, 0, 0, (int)$parts[1], (int)$parts[2], (int)$parts[0]));
            $groupIdx++;
            $cronologicoRows[] = [
                'group'        => $groupIdx,
                'date'         => $date,
                'dow'          => $dow,
                'dowLabel'     => $dowLabels[$dow] . ' - ' . sprintf('%02d/%02d', (int)$parts[2], (int)$parts[1]),
                'progKey'      => $progKey,
                'progDisplay'  => $progDisplay,
                'linkedLiveKey'=> $linkedLiveKey,
                'status'       => $det['status'],
                'isLive'       => $det['isLive'],
                'isManual'     => $det['isManual'],
                'schTime'      => $det['schTime']      ?? '',
                'schEnd'       => $det['schEnd']       ?? '',
                'schDurMin'    => $det['schDurMin']    ?? 0,
                'realTime'     => $det['realTime']     ?? '',
                'realDurSec'   => $det['realDurSec']   ?? null,
                'title'        => $det['title']        ?? '',
                'streamer'     => $det['streamer']     ?? '',
                'missedReason' => $det['missedReason'] ?? '',
            ];
        }
    }
    // Fecha DESC, luego hora teórica DESC
    usort($cronologicoRows, function($a, $b) {
        if ($a['date'] !== $b['date']) return strcmp($b['date'], $a['date']);
        return strcmp($b['schTime'], $a['schTime']);
    });
}
?>

<div class="card" id="seguimiento-card" style="padding: 0;">

    <!-- ── Cabecera ─────────────────────────────────────────────────────────── -->
    <div style="padding: 20px 24px 16px; border-bottom: 1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 2px 0;">📊 Historial de Emisión</h2>
                <span style="color:#718096; font-size:13px;">📻 <strong><?php echo htmlEsc($trackingStation); ?></strong></span>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;" class="no-print">
                <?php if (isAdmin()): ?>
                <a href="?" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">⚙️</span> Panel Admin</a>
                <?php else: ?>
                <a href="?" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">←</span> Volver</a>
                <?php endif; ?>
                <button onclick="exportarPDF()" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">🖨️</span> PDF</button>
                <button onclick="exportarImagen()" class="btn btn-secondary" style="font-size:13px;" id="btn-imagen"><span class="btn-icon">🖼️</span> Imagen</button>
                <form method="POST" style="display:inline; margin:0;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Navegación de mes ────────────────────────────────────────────────── -->
    <div id="nav-mes" style="padding:14px 24px; background:#f7fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:16px;">
        <?php if ($canGoPrev): ?>
        <a href="?page=seguimiento_emision&month=<?php echo htmlEsc($prevMonth . $stationParam); ?>"
           class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">← Anterior</a>
        <?php else: ?>
        <button class="btn btn-secondary" disabled style="font-size:13px; padding:6px 14px; opacity:.4; cursor:not-allowed;">← Anterior</button>
        <?php endif; ?>

        <span style="font-size:18px; font-weight:700; color:#2d3748; min-width:180px; text-align:center;">
            <?php echo htmlEsc($monthLabel); ?>
        </span>

        <?php if ($canGoNext): ?>
            <a href="?page=seguimiento_emision&month=<?php echo htmlEsc($nextMonth . $stationParam); ?>"
               class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Siguiente →</a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size:13px; padding:6px 14px; opacity:.4; cursor:not-allowed;">Siguiente →</button>
        <?php endif; ?>

        <!-- Toggle de vista + filtros -->
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;" class="no-print">
            <div style="display:flex; gap:3px;">
                <button id="btn-vista-resumen"   onclick="toggleVista('resumen')"   class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">≡ Vista cronológica</button>
                <button id="btn-vista-programas" onclick="toggleVista('programas')" class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">☰ Vista por programas</button>
                <button id="btn-vista-detalle"   onclick="toggleVista('detalle')"   class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">⊞ Rejilla</button>
            </div>
            <div id="filtros-cronologico" style="display:flex; align-items:center; gap:6px;">
                <span style="font-size:11px; color:#718096;">Filtrar por:</span>
                <button id="btn-filtro-fallados" onclick="filtrarCronologico('missed')" class="btn" style="font-size:11px; padding:3px 10px; background:#fc8181; color:#fff; border:1px solid #f56565; border-radius:4px;">Fallados</button>
                <button id="btn-filtro-correctos" onclick="filtrarCronologico('played')" class="btn" style="font-size:11px; padding:3px 10px; background:#68d391; color:#fff; border:1px solid #48bb78; border-radius:4px;">Correctos</button>
            </div>
        </div>

    </div>

    <!-- ── Alertas ───────────────────────────────────────────────────────────── -->
    <?php if (!$hasSchedule): ?>
    <div class="alert alert-info" style="margin:20px 24px;">
        ⚠️ No se pudo obtener la programación de AzuraCast. Comprueba que el <strong>Station ID</strong> y la <strong>URL de la API</strong> estén configurados.
    </div>
    <?php elseif ($historyError): ?>
    <div class="alert alert-warning" style="margin:20px 24px;">
        ⚠️ No se pudo obtener el historial de reproducción de AzuraCast. Los días pasados no muestran estado de emisión. Comprueba la <strong>API Key</strong>.
    </div>
    <?php endif; ?>

    <!-- ── Vista cronológica ────────────────────────────────────────────────── -->
    <div id="vista-resumen" style="padding:16px 24px 24px;">
        <?php if ($hasSchedule && !empty($cronologicoRows)): ?>
        <!-- Cabecera: stats + paginación -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #e2e8f0; gap:12px; flex-wrap:wrap;">
            <span id="crono-stats" style="font-size:13px; color:#718096;"></span>
            <div style="display:flex; align-items:center; gap:6px;">
                <button id="crono-prev" onclick="cronoPrev()" class="btn btn-secondary" style="font-size:11px; padding:3px 10px;" disabled>← Anterior</button>
                <span id="crono-page-info" style="font-size:12px; color:#4a5568; white-space:nowrap;"></span>
                <button id="crono-next" onclick="cronoNext()" class="btn btn-secondary" style="font-size:11px; padding:3px 10px;">Siguiente →</button>
            </div>
        </div>

        <!-- Tabla cronológica -->
        <div style="overflow-x:auto;">
        <table class="listado-detail-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="white-space:nowrap;">Fecha</th>
                    <th>Programa</th>
                    <th>H. teórica</th>
                    <th>H. real</th>
                    <th>Dur. teórica</th>
                    <th>Dur. real</th>
                    <th>Diferencia</th>
                    <th>Episodio emitido</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="crono-tbody">
            <?php foreach ($cronologicoRows as $crow):
                $cDurTeoStr  = ($crow['schDurMin'] > 0) ? $crow['schDurMin'] . ' min' : '—';
                $cDurRealStr = '—';
                $cDiffStr    = '—';
                $cDiffClass  = '';
                if (!empty($crow['realDurSec'])) {
                    $cRealMin    = (int)round($crow['realDurSec'] / 60);
                    $cDurRealStr = $cRealMin . ' min';
                    if ($crow['schDurMin'] > 0) {
                        $cDiff      = $cRealMin - $crow['schDurMin'];
                        $cDiffStr   = ($cDiff >= 0 ? '+' : '') . $cDiff . ' min';
                        $cDiffClass = $cDiff > 5 ? 'ld-diff-over' : ($cDiff < -5 ? 'ld-diff-under' : 'ld-diff-ok');
                    }
                }
                $cIsLive  = $crow['isLive'];
                $cIsManual= $crow['isManual'];
                $cOvKey   = ($cIsLive && $crow['linkedLiveKey']) ? $crow['linkedLiveKey'] : $crow['progKey'];
                $cRowCls  = $crow['status'] === 'played' ? 'ld-row-ok' : 'ld-row-missed';
                $cGroup   = (int)$crow['group'];
            ?>
            <tr class="crono-row <?php echo $cRowCls; ?>" data-status="<?php echo $crow['status']; ?>" data-group="<?php echo $cGroup; ?>">
                <td style="white-space:nowrap;"><?php if ($cIsLive): ?><span class="ld-badge-live" title="Emisión en directo">📡</span> <?php endif; ?><?php echo htmlEsc($crow['dowLabel']); ?></td>
                <td style="white-space:nowrap;"><?php echo htmlEsc(displayName($crow['progDisplay'])); ?></td>
                <td><?php echo htmlEsc($crow['schTime']); ?><?php if ($crow['schEnd']): ?><span class="ld-end-time"> –<?php echo htmlEsc($crow['schEnd']); ?></span><?php endif; ?></td>
                <td><?php echo $crow['realTime'] ? htmlEsc($crow['realTime']) : '<span class="ld-no-data">—</span>'; ?></td>
                <td><span class="dur-teo-edit"
                          data-progkey="<?php echo htmlEsc($cOvKey); ?>"
                          data-day="<?php echo $crow['dow']; ?>"
                          data-time="<?php echo htmlEsc($crow['schTime']); ?>"
                          data-dur="<?php echo (int)$crow['schDurMin']; ?>"
                          onclick="abrirEditDur(this)"
                          title="Clic para editar duración teórica"
                          style="cursor:pointer; border-bottom:1px dashed #94a3b8; white-space:nowrap;"
                    ><?php echo htmlEsc($cDurTeoStr); ?></span></td>
                <td><?php echo htmlEsc($cDurRealStr); ?></td>
                <td class="<?php echo $cDiffClass; ?>"><?php echo htmlEsc($cDiffStr); ?></td>
                <td class="ld-title"><?php
                    if ($cIsLive && $crow['streamer']) {
                        echo htmlEsc($crow['streamer']);
                    } elseif ($crow['title']) {
                        echo htmlEsc($crow['title']);
                    } else {
                        echo '<span class="ld-no-data">—</span>';
                    }
                ?></td>
                <td>
                    <?php if ($crow['status'] === 'played'): ?>
                        <span class="ld-status-ok">✓</span>
                    <?php else: ?>
                        <span class="ld-status-miss celda-corregible"
                              data-prog="<?php echo htmlEsc($cOvKey); ?>"
                              data-date="<?php echo htmlEsc($crow['date']); ?>"
                              data-live="<?php echo $cIsLive ? '1' : '0'; ?>"
                              data-manual="<?php echo $cIsManual ? '1' : '0'; ?>"
                              title="Clic para marcar como emitida">✗</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($crow['status'] === 'missed' && $crow['missedReason']): ?>
            <tr class="crono-row-reason ld-row-reason" data-group="<?php echo $cGroup; ?>">
                <td colspan="9">ℹ️ <?php echo htmlEsc($crow['missedReason']); ?></td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Paginación inferior -->
        <div style="display:flex; justify-content:flex-end; align-items:center; gap:6px; margin-top:14px; padding-top:10px; border-top:1px solid #e2e8f0;">
            <button id="crono-prev-bot" onclick="cronoPrev()" class="btn btn-secondary" style="font-size:11px; padding:3px 10px;" disabled>← Anterior</button>
            <span id="crono-page-info-bot" style="font-size:12px; color:#4a5568; white-space:nowrap;"></span>
            <button id="crono-next-bot" onclick="cronoNext()" class="btn btn-secondary" style="font-size:11px; padding:3px 10px;">Siguiente →</button>
        </div>

        <?php else: ?>
        <p style="color:#718096; font-size:13px; margin:0;">No hay datos de programación disponibles.</p>
        <?php endif; ?>
    </div>

    <!-- ── Vista por programas (fichas colapsables) ─────────────────────────── -->
    <div id="vista-programas" style="padding:16px 24px 24px; display:none;">
        <?php if ($hasSchedule && !empty($progSummary)):
            $resTotalFails = 0; $resTotalProgs = 0;
            foreach ($progSummary as $s2) { $resTotalFails += $s2['missed'] + $s2['live_missed']; $resTotalProgs++; }
        ?>
        <!-- Cabecera -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #e2e8f0; gap:12px; flex-wrap:wrap;">
            <span style="font-size:13px; color:#718096;">
                <strong style="color:#2d3748;"><?php echo $resTotalProgs; ?></strong> programa<?php echo $resTotalProgs !== 1 ? 's' : ''; ?>
                <?php if ($resTotalFails > 0): ?>
                · <strong style="color:#c53030;"><?php echo $resTotalFails; ?></strong> emisión<?php echo $resTotalFails !== 1 ? 'es' : ''; ?> perdida<?php echo $resTotalFails !== 1 ? 's' : ''; ?>
                <?php else: ?>
                · <strong style="color:#276749;">✓ Todo correcto</strong>
                <?php endif; ?>
            </span>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:11px; color:#a0aec0;">Ordenar:</span>
                <button id="sort-fallos" onclick="ordenarResumen('fallos')" class="btn btn-secondary btn-vista-activo" style="font-size:11px; padding:3px 10px;">% Fallos</button>
                <button id="sort-nombre" onclick="ordenarResumen('nombre')" class="btn btn-secondary" style="font-size:11px; padding:3px 10px;">Nombre</button>
            </div>
        </div>

        <div id="lista-resumen" style="display:flex; flex-direction:column; gap:6px;">
        <?php
        $dowNames = ['dom','lun','mar','mié','jue','vie','sáb'];
        $cardIdx  = 0;
        foreach ($progSummary as $progKey => $s):
            $cardIdx++;
            $bodyId        = 'lbody-' . $cardIdx;
            $progDisplay   = $displayNameMap[$progKey] ?? $progKey;
            $totalFails    = $s['missed'] + $s['live_missed'];
            $totalPlayed   = $s['played'] + $s['live_played'];
            $linkedLiveKey = $s['linkedLiveKey'];
            $totalDone     = $totalPlayed + $totalFails;
            $pct           = $totalDone > 0 ? round($totalPlayed / $totalDone * 100) : null;
            if ($totalFails === 0)     $hc = 'rh-ok';
            elseif ($totalFails <= 2)  $hc = 'rh-warn';
            else                       $hc = 'rh-crit';

            $progEmissions      = $listadoDetails[$progKey] ?? [];
            $scheduledEmissions = array_filter($progEmissions, fn($e) => $e['status'] !== 'none');
            ksort($scheduledEmissions);
        ?>
        <div class="listado-card <?php echo $hc; ?>"
             data-name="<?php echo htmlEsc(mb_strtolower(displayName($progDisplay))); ?>"
             data-fails="<?php echo $totalFails; ?>"
             data-pct="<?php echo $pct ?? 100; ?>">

            <!-- Cabecera clicable -->
            <div class="listado-card-header" onclick="toggleListadoCard('<?php echo $bodyId; ?>', this)">
                <span class="listado-chevron">▶</span>
                <span class="listado-card-nombre"><?php echo htmlEsc(displayName($progDisplay)); ?></span>
                <button type="button"
                        class="btn-edit-prog"
                        data-progkey="<?php echo htmlEsc($progKey); ?>"
                        title="Editar ficha del programa"
                        onclick="event.stopPropagation(); abrirFichaPrograma(this);"
                        style="margin-left:6px; padding:2px 8px; font-size:12px; background:none; border:1px solid #cbd5e0; border-radius:5px; cursor:pointer; color:#4a5568; line-height:1.4; flex-shrink:0;">✏️</button>
                <div class="listado-card-stats">
                    <?php if ($pct !== null): ?>
                    <span class="listado-pct <?php echo $hc; ?>"><?php echo $totalPlayed; ?>/<?php echo $totalDone; ?> · <?php echo $pct; ?>%</span>
                    <?php endif; ?>
                    <span style="font-size:12px; color:#276749; white-space:nowrap;"><?php echo $totalPlayed; ?> emitidos</span>
                    <?php if ($totalFails > 0): ?>
                    <span class="badge-falla"><?php echo $totalFails; ?> sin emitir</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cuerpo colapsable -->
            <div class="listado-card-body" id="<?php echo $bodyId; ?>">
                <?php if (!empty($scheduledEmissions)): ?>
                <table class="listado-detail-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>H. teórica</th>
                            <th>H. real</th>
                            <th>Dur. teórica</th>
                            <th>Dur. real</th>
                            <th>Diferencia</th>
                            <th>Episodio emitido</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($scheduledEmissions as $eDate => $em):
                        $eParts     = explode('-', $eDate);
                        $eDow       = (int)date('w', mktime(0,0,0,(int)$eParts[1],(int)$eParts[2],(int)$eParts[0]));
                        $eDateLabel = $dowNames[$eDow] . ' ' . (int)$eParts[2] . '/' . (int)$eParts[1];

                        $durTeoStr  = ($em['schDurMin'] > 0) ? $em['schDurMin'] . ' min' : '—';
                        $durRealStr = '—';
                        $diffStr    = '—';
                        $diffClass  = '';
                        if (!empty($em['realDurSec'])) {
                            $realMin    = (int)round($em['realDurSec'] / 60);
                            $durRealStr = $realMin . ' min';
                            if ($em['schDurMin'] > 0) {
                                $diff      = $realMin - $em['schDurMin'];
                                $diffStr   = ($diff >= 0 ? '+' : '') . $diff . ' min';
                                $diffClass = $diff > 5 ? 'ld-diff-over' : ($diff < -5 ? 'ld-diff-under' : 'ld-diff-ok');
                            }
                        }

                        $isLive   = $em['isLive'];
                        $ovKey    = ($em['isLive'] && $linkedLiveKey) ? $linkedLiveKey : $progKey;
                        $isManual = $em['isManual'];

                        if ($em['status'] === 'played')       $rowClass = 'ld-row-ok';
                        elseif ($em['status'] === 'missed')   $rowClass = 'ld-row-missed';
                        else                                   $rowClass = '';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php if ($isLive): ?><span class="ld-badge-live" title="Emisión en directo">📡</span> <?php endif; ?><?php echo htmlEsc($eDateLabel); ?></td>
                        <td><?php echo htmlEsc($em['schTime']); ?><?php if ($em['schEnd']): ?><span class="ld-end-time"> –<?php echo htmlEsc($em['schEnd']); ?></span><?php endif; ?></td>
                        <td><?php echo $em['realTime'] ? htmlEsc($em['realTime']) : '<span class="ld-no-data">—</span>'; ?></td>
                        <td><span class="dur-teo-edit"
                                  data-progkey="<?php echo htmlEsc($ovKey); ?>"
                                  data-day="<?php echo $eDow; ?>"
                                  data-time="<?php echo htmlEsc($em['schTime'] ?? ''); ?>"
                                  data-dur="<?php echo (int)($em['schDurMin'] ?? 0); ?>"
                                  onclick="abrirEditDur(this)"
                                  title="Clic para editar duración teórica"
                                  style="cursor:pointer; border-bottom:1px dashed #94a3b8; white-space:nowrap;"
                            ><?php echo htmlEsc($durTeoStr); ?></span></td>
                        <td><?php echo htmlEsc($durRealStr); ?></td>
                        <td class="<?php echo $diffClass; ?>"><?php echo htmlEsc($diffStr); ?></td>
                        <td class="ld-title"><?php
                            if ($isLive && ($em['streamer'] ?? '')) {
                                echo htmlEsc($em['streamer']);
                            } elseif ($em['title']) {
                                echo htmlEsc($em['title']);
                            } else {
                                echo '<span class="ld-no-data">—</span>';
                            }
                        ?></td>
                        <td>
                            <?php if ($em['status'] === 'played'): ?>
                                <span class="ld-status-ok">✓</span>
                            <?php elseif ($em['status'] === 'missed'): ?>
                                <span class="ld-status-miss celda-corregible"
                                      data-prog="<?php echo htmlEsc($ovKey); ?>"
                                      data-date="<?php echo htmlEsc($eDate); ?>"
                                      data-live="<?php echo $isLive ? '1' : '0'; ?>"
                                      data-manual="<?php echo $isManual ? '1' : '0'; ?>"
                                      title="Clic para marcar como emitida">✗</span>
                            <?php else: ?>
                                <span class="ld-no-data">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($em['status'] === 'missed' && $em['missedReason']): ?>
                    <tr class="ld-row-reason">
                        <td colspan="8">ℹ️ <?php echo htmlEsc($em['missedReason']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="margin:0; padding:12px 16px; font-size:13px; color:#718096;">Sin historial de emisión este mes (no se encontraron registros en AzuraCast).</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php else: ?>
        <p style="color:#718096; font-size:13px; margin:0;">No hay datos de programación disponibles.</p>
        <?php endif; ?>
    </div>

    <!-- ── Vista detalle (tabla + totales) ───────────────────────────────────── -->
    <div id="vista-detalle">

    <!-- ── Leyenda + totales (solo vista detalle) ───────────────────────────── -->
    <div style="padding:10px 24px; border-bottom:1px solid #e2e8f0; font-size:12px; color:#4a5568;">
        <!-- fila 1: leyenda -->
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#e2e8f0;border-radius:3px;display:inline-block;"></span> Esperado</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#c6f6d5;border-radius:3px;display:inline-block;"></span> Emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#fed7d7;border-radius:3px;display:inline-block;"></span> No emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#3b82f6;border-radius:3px;display:inline-block;font-size:10px;text-align:center;line-height:14px;color:#fff;">📡</span> Directo emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#ef4444;border-radius:3px;display:inline-block;font-size:10px;text-align:center;line-height:14px;color:#fff;">📡</span> Directo no emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#94a3b8;border-radius:3px;display:inline-block;font-size:10px;text-align:center;line-height:14px;color:#fff;">📡</span> Directo esperado</span>
        </div>
        <!-- fila 2: totales -->
        <?php if ($hasSchedule):
            $totalEsperados = $totals['emite_ok'] + $totals['faltan'];
            $livefaltan     = $totals['live_esperados'] - $totals['live_efectivos'];
        ?>
        <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-top:6px; color:#4a5568;">
            <span style="font-weight:600; color:#4a5568;">Emisiones:</span>
            <span>Esperadas <strong><?php echo $totalEsperados; ?></strong></span>
            <span>Emitidas <strong id="total-emite-ok" style="color:#276749;"><?php echo $totals['emite_ok']; ?></strong></span>
            <span>Faltan <strong id="total-faltan" style="color:#c53030;"><?php echo $totals['faltan']; ?></strong></span>
            <span style="color:#cbd5e0;">|</span>
            <span style="font-weight:600; color:#2563eb;">Directos:</span>
            <span>Esperados <strong><?php echo $totals['live_esperados']; ?></strong></span>
            <span>Emitidos <strong id="total-live-ef" style="color:#1d4ed8;"><?php echo $totals['live_efectivos']; ?></strong></span>
            <span>Faltan <strong id="total-live-faltan" style="color:#b91c1c;"><?php echo $livefaltan; ?></strong></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Tabla ─────────────────────────────────────────────────────────────── -->
    <?php if ($hasSchedule): ?>
    <div style="overflow-x:auto; padding: 0 0 24px 0;" class="tabla-wrapper">
        <table class="seguimiento-table">
            <thead>
                <tr>
                    <th class="col-programa">Programa</th>
                    <?php foreach ($days as $day): ?>
                    <th class="col-dia <?php echo $day['isToday'] ? 'col-hoy' : ''; ?>">
                        <div class="dia-num"><?php echo $day['num']; ?></div>
                        <div class="dia-dow"><?php echo $dowLabels[$day['dow']]; ?></div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programSchedules as $progKey => $slots):
                    // Skip live programs merged into their automated counterpart row
                    if (isset($absorbedLive[$progKey])) continue;

                    $progDisplay   = $displayNameMap[$progKey] ?? $progKey;
                    $isLiveProg    = isset($livePrograms[$progKey]);
                    $linkedLiveKey = $liveForAutomated[$progKey] ?? null;
                ?>
                <tr>
                    <td class="col-programa">
                        <span title="<?php echo htmlEsc(displayName($progDisplay)); ?>">
                            <?php if ($isLiveProg): ?><span style="color:#ef4444;font-size:10px;">🔴 </span><?php endif; ?>
                            <?php echo htmlEsc(displayName($progDisplay)); ?>
                        </span>
                    </td>
                    <?php foreach ($days as $day):
                        $cls = $tooltip = $icon = '';
                        $overrideProgKey = $progKey; // clave efectiva para el override (puede ser $linkedLiveKey)
                        $isLiveCell      = $isLiveProg;
                        $isManualCell    = false;

                        // Celda vacía si el día está fuera del periodo activo del programa
                        $firstSeen         = $programFirstSeen[$progKey] ?? null;
                        $lastActive        = $programLastActive[$progKey] ?? null;
                        $dayOutsideRange   = ($firstSeen && $day['date'] < $firstSeen)
                                          || ($lastActive && $day['date'] > $lastActive);

                        if (!$dayOutsideRange && $linkedLiveKey !== null) {
                            // If this automated program has a linked live version, check live status first
                            // (only if the live program was active on this day)
                            $liveFirstSeen  = $programFirstSeen[$linkedLiveKey] ?? null;
                            $liveLastActive = $programLastActive[$linkedLiveKey] ?? null;
                            $liveExistsOnDay = (!$liveFirstSeen || $day['date'] >= $liveFirstSeen)
                                           && (!$liveLastActive || $day['date'] <= $liveLastActive);
                            if ($liveExistsOnDay) {
                                $liveCell   = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides, $liveSessionStreamers);
                                $liveStatus = $liveCell['status'];
                                $overrideProgKey = $linkedLiveKey;
                                $isLiveCell      = true;
                                if ($liveStatus === 'played') {
                                    if (!empty($liveCell['manual'])) {
                                        $cls          = 'celda-directo-manual';
                                        $tooltip      = 'Directo — corrección manual' . ($liveCell['reason'] ? ': ' . $liveCell['reason'] : '') . ' · ' . $liveCell['corrected_by'] . ' ' . $liveCell['corrected_at'];
                                        $icon         = '📡';
                                        $isManualCell = true;
                                    } else {
                                        $cls             = 'celda-directo-emitido';
                                        $liveEnd         = $liveCell['scheduledEnd'] ?? null;
                                        $_liveTitle      = $listadoDetails[$progKey][$day['date']]['title'] ?? null;
                                        $_liveStreamer    = $liveCell['streamer'] ?? '';
                                        $tooltip    = 'Directo emitido ' . $liveCell['time'] . 'h' . ($liveEnd ? ' - ' . $liveEnd . 'h' : '') . ' (esperado ' . $liveCell['scheduledAt'] . 'h)'
                                                    . ($_liveStreamer ? ' · ' . $_liveStreamer : '')
                                                    . ($_liveTitle ? ' · ' . $_liveTitle : '');
                                        $icon       = '📡';
                                    }
                                } elseif ($liveStatus === 'missed') {
                                    $cls     = 'celda-directo-perdido';
                                    $reason  = getMissedReason($liveCell['scheduledAt'], $day['date'], $dayTimeline, true, $linkedLiveKey, $dailyReports[$day['date']] ?? null, null, null, $historyEntryByDay, $programSchedules, null, $playlistDisplayName);
                                    $tooltip = 'Directo no emitido (esperado ' . $liveCell['scheduledAt'] . 'h) · ' . $reason . ' · ✎ Clic para corregir';
                                    $icon    = '📡';
                                } elseif ($liveStatus === 'expected') {
                                    $cls     = 'celda-directo-esperado';
                                    $tooltip = 'Directo programado a las ' . $liveCell['scheduledAt'] . 'h';
                                    $icon    = '📡';
                                }
                            }
                        }

                        // Si el día está fuera del periodo activo → celda vacía
                        if ($dayOutsideRange):
                    ?>
                    <td class="col-dia <?php echo $day['isToday'] ? 'col-hoy' : ''; ?>"></td>
                    <?php else: ?>
                    <?php
                        // If no live override, use automated (or standalone live) status
                        if ($cls === '') {
                            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides, $liveSessionStreamers);
                            $status = $cell['status'];
                            switch ($status) {
                                case 'played':
                                    if (!empty($cell['manual'])) {
                                        $cls          = 'celda-manual';
                                        $tooltip      = 'Corrección manual' . ($cell['reason'] ? ': ' . $cell['reason'] : '') . ' · ' . $cell['corrected_by'] . ' ' . $cell['corrected_at'];
                                        $icon         = '✎';
                                        $isManualCell = true;
                                    } else {
                                        $cls             = $isLiveProg ? 'celda-directo-emitido' : 'celda-emitida';
                                        $_epTitle        = $listadoDetails[$progKey][$day['date']]['title'] ?? null;
                                        $_epStreamer      = $isLiveProg ? ($cell['streamer'] ?? '') : '';
                                        $tooltip  = 'Emitido a las ' . $cell['time'] . 'h (esperado ' . $cell['scheduledAt'] . 'h)'
                                                  . ($_epStreamer ? ' · ' . $_epStreamer : '')
                                                  . ($_epTitle ? ' · ' . $_epTitle : '');
                                        $icon     = $isLiveProg ? '📡' : '✓';
                                    }
                                    break;
                                case 'missed':
                                    $cls     = 'celda-perdida';
                                    $reason  = getMissedReason($cell['scheduledAt'], $day['date'], $dayTimeline, $isLiveProg, $progKey, $dailyReports[$day['date']] ?? null, null, null, $historyEntryByDay, $programSchedules, null, $playlistDisplayName);
                                    $tooltip = 'No emitido (esperado ' . $cell['scheduledAt'] . 'h) · ' . $reason . ' · ✎ Clic para corregir';
                                    $icon    = '✗';
                                    break;
                                case 'expected':
                                    $cls     = 'celda-esperada';
                                    $tooltip = 'Programado a las ' . $cell['scheduledAt'] . 'h';
                                    $icon    = '';
                                    break;
                            }
                        }
                    ?>
                    <?php
                        $esClickeable = in_array($cls, ['celda-perdida','celda-manual','celda-directo-perdido','celda-directo-manual']);
                    ?>
                    <td class="col-dia <?php echo $day['isToday'] ? 'col-hoy' : ''; ?> <?php echo $cls; ?><?php echo $esClickeable ? ' celda-corregible' : ''; ?>"
                        <?php if ($tooltip): ?>data-tooltip="<?php echo htmlEsc($tooltip); ?>"<?php endif; ?>
                        <?php if ($esClickeable): ?>
                        data-prog="<?php echo htmlEsc($overrideProgKey); ?>"
                        data-date="<?php echo $day['date']; ?>"
                        data-live="<?php echo $isLiveCell ? '1' : '0'; ?>"
                        data-manual="<?php echo $isManualCell ? '1' : '0'; ?>"
                        <?php endif; ?>>
                        <?php echo $icon; ?>
                    </td>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="padding:0 24px 8px; font-size:12px; color:#a0aec0; text-align:right;">
        <?php echo count($programSchedules); ?> programa<?php echo count($programSchedules) !== 1 ? 's' : ''; ?> con horario activo en AzuraCast
    </div>
    <?php endif; ?>


    <!-- ── Debug (solo con ?debug=1) ────────────────────────────────────────── -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <div style="padding:16px 24px 24px; border-top:2px dashed #f59e0b; background:#fffbeb; font-size:12px; font-family:monospace;">
        <strong style="color:#92400e;">🔍 DEBUG — solo visible con ?debug=1</strong>

        <p style="margin:10px 0 4px;"><strong>Programas en BD de SAPO (todos):</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:200px;"><?php
            foreach ($dbPrograms as $k => $v) {
                $t = $v['playlist_type'] ?? '?';
                $o = $v['orphaned'] ?? false;
                $slots = !empty($v['schedule_slots']) ? count($v['schedule_slots']).' slot(s)' : (!empty($v['schedule_days']) ? 'formato antiguo' : 'SIN HORARIO');
                echo htmlEsc("[$t" . ($o?' HUÉRFANO':'') . "] $k  →  $slots\n");
            }
            if (empty($dbPrograms)) echo '(vacío — no hay programas en la BD de SAPO)';
        ?></pre>

        <p style="margin:10px 0 4px;"><strong>Directos cargados en seguimiento (livePrograms):</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:160px;"><?php
            foreach ($livePrograms as $key => $_) {
                $display = $displayNameMap[$key] ?? $key;
                $slots   = $programSchedules[$key] ?? [];
                echo htmlEsc("Clave SAPO: $key\n");
                echo htmlEsc("  Display:  $display\n");
                $dowL = ['D','L','M','X','J','V','S'];
                foreach ($slots as $s) {
                    echo htmlEsc("  Slot: " . $dowL[$s['dayOfWeek']] . " " . $s['startTime'] . "-" . $s['endTime'] . "\n");
                }
            }
            if (empty($livePrograms)) echo '(ningún directo cargado)';
        ?></pre>

        <p style="margin:10px 0 4px;"><strong>Informes diarios — directos del mes:</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:120px;"><?php
            $dbgReportsPath = getReportsPath($trackingUsername);
            echo htmlEsc("Ruta informes: " . ($dbgReportsPath ?: '(base_path no configurado)') . "\n");
            if (empty($liveEmissionsPerDay)) {
                echo "(sin informes con directos para este mes)\n";
            } else {
                foreach ($liveEmissionsPerDay as $d => $times) {
                    echo htmlEsc("$d: " . implode(', ', $times) . "\n");
                }
            }
        ?></pre>

        <p style="margin:10px 0 4px;"><strong>Emisiones en directo detectadas en el log:</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:160px;"><?php
            if (empty($liveEmissionsPerDay)) {
                echo "(ninguna emisión en directo encontrada en el log para este mes)\n";
            } else {
                foreach ($liveEmissionsPerDay as $dateStr => $ems) {
                    foreach ($ems as $em) {
                        $end = $em['end'] ?? '(aún activo)';
                        echo htmlEsc("$dateStr: " . $em['start'] . " → $end\n");
                    }
                }
            }
        ?></pre>

        <p style="margin:10px 0 4px;"><strong>Sesiones de directo detectadas <?php echo isset($broadcasts) && is_array($broadcasts) && !empty($broadcasts) ? '(fuente: API broadcasts AzuraCast ✓)' : '(fuente: historial de canciones — fallback)'; ?>:</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:120px;"><?php
            if (empty($liveSessionStarts)) {
                echo "(ningún evento de streamer en el historial de este mes)\n";
            } else {
                foreach ($liveSessionStarts as $d => $times) {
                    foreach ($times as $t) {
                        $sName = $liveSessionStreamers[$d][$t] ?? '(sin nombre)';
                        $sDur  = isset($liveSessionDurations[$d][$t]) && $liveSessionDurations[$d][$t] !== null
                            ? round($liveSessionDurations[$d][$t] / 60) . ' min'
                            : '(en curso o sin datos)';
                        echo htmlEsc("$d $t → streamer: \"$sName\" | duración: $sDur\n");
                    }
                }
            }
        ?></pre>

        <p style="margin:10px 0 4px;"><strong>Historial del mes — playlists (programas automáticos):</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:160px;"><?php
            if ($historyError) {
                echo 'ERROR al obtener historial (¿API Key configurada?)';
            } elseif (empty($historyMap)) {
                echo '(vacío — sin datos de historial para este mes)';
            } else {
                foreach ($historyMap as $playlist => $dayData) {
                    echo htmlEsc("$playlist: " . count($dayData) . " día(s) — " . implode(', ', array_keys($dayData)) . "\n");
                }
            }
        ?></pre>
    </div>
    <?php endif; ?>

    </div><!-- /vista-detalle -->

</div><!-- /card -->

<!-- ── Estilos de la tabla ──────────────────────────────────────────────────── -->
<style>
.seguimiento-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    white-space: nowrap;
}
.seguimiento-table thead th {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    padding: 4px 2px;
    text-align: center;
    font-weight: 600;
    color: #4a5568;
    position: sticky;
    top: 0;
    z-index: 2;
}
.seguimiento-table .col-programa {
    min-width: 180px;
    max-width: 240px;
    text-align: left;
    padding: 5px 10px;
    border: 1px solid #e2e8f0;
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #2d3748;
    font-size: 13px;
}
.seguimiento-table thead th.col-programa {
    z-index: 3;
}
.seguimiento-table .col-dia {
    width: 30px;
    min-width: 30px;
    text-align: center;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
    cursor: default;
}
.seguimiento-table .dia-num {
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
}
.seguimiento-table .dia-dow {
    font-size: 10px;
    color: #718096;
    font-weight: 400;
    line-height: 1;
}
.seguimiento-table td.col-dia {
    height: 28px;
    padding: 0 2px;
    font-size: 12px;
    font-weight: 700;
}
/* Estados */
.celda-esperada          { background: #e2e8f0; color: #718096; }
.celda-emitida           { background: #c6f6d5; color: #276749; }
.celda-perdida           { background: #fed7d7; color: #9b2335; }
.celda-directo-emitido   { background: #3b82f6; color: #fff; }
.celda-directo-esperado  { background: #94a3b8; color: #fff; }
.celda-directo-perdido   { background: #ef4444; color: #fff; }
/* Correcciones manuales */
.celda-manual            { background: #9ae6b4; color: #22543d; outline: 2px dashed #38a169; outline-offset: -2px; }
.celda-directo-manual    { background: #63b3ed; color: #fff;    outline: 2px dashed #2b6cb0; outline-offset: -2px; }
/* Celdas clickeables (missed + manual) */
.celda-corregible        { cursor: pointer; }
.celda-corregible:hover  { filter: brightness(0.88); }
.celda-corregible:hover::before {
    content: '✎';
    position: absolute;
    top: 1px;
    right: 2px;
    font-size: 9px;
    line-height: 1;
    opacity: .75;
    pointer-events: none;
}
/* Hoy */
.col-hoy { border-left: 2px solid #667eea !important; border-right: 2px solid #667eea !important; }
/* Tooltip CSS */
[data-tooltip] { position: relative; }
[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 110%;
    left: 50%;
    transform: translateX(-50%);
    background: #2d3748;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 400;
    white-space: nowrap;
    z-index: 100;
    pointer-events: none;
    box-shadow: 0 2px 6px rgba(0,0,0,.25);
}
/* Alternar color de filas */
.seguimiento-table tbody tr:nth-child(even) .col-programa { background: #f7fafc; }
.seguimiento-table tbody tr:hover .col-programa { background: #ebf4ff; }

/* ── Tabla resumen ── */
.resumen-table {
    width: 320px;
    border-collapse: collapse;
    font-size: 13px;
    font-weight: 600;
    margin-top: 16px;
}
.resumen-table td {
    padding: 7px 14px;
    border: 1px solid rgba(0,0,0,.12);
}
.resumen-table td:last-child {
    text-align: right;
    min-width: 60px;
}
.resumen-emite-ok  td { background: #c6f6d5; color: #22543d; }
.resumen-faltan    td { background: #feb2b2; color: #742a2a; }
.resumen-azura     td { background: #bee3f8; color: #2a4365; }
.resumen-directos     td { background: #90cdf4; color: #2a4365; }
.resumen-directos-esp td { background: #2d3748; color: #fff; }

/* ── Vista listado (fichas colapsables) ── */
.listado-card {
    border-radius: 7px;
    background: #fff;
    border: 1px solid #e8edf2;
    border-left: 4px solid #cbd5e0;
    overflow: hidden;
    transition: box-shadow .12s;
}
.listado-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.listado-card.rh-ok   { border-left-color: #48bb78; }
.listado-card.rh-warn { border-left-color: #ed8936; }
.listado-card.rh-crit { border-left-color: #e53e3e; }

.listado-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px 10px 14px;
    cursor: pointer;
    user-select: none;
}
.listado-card-header:hover { background: #f7fafc; }

.listado-chevron {
    font-size: 10px;
    color: #a0aec0;
    transition: transform .2s;
    flex-shrink: 0;
}
.listado-chevron.open { transform: rotate(90deg); }

.listado-card-nombre {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
    color: #2d3748;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.listado-card-stats {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.listado-pct {
    font-size: 14px;
    font-weight: 700;
    min-width: 36px;
    text-align: right;
}
.listado-pct.rh-ok   { color: #276749; }
.listado-pct.rh-warn { color: #c05621; }
.listado-pct.rh-crit { color: #c53030; }

/* Cuerpo colapsable */
.listado-card-body {
    display: none;
    border-top: 1px solid #edf2f7;
}

/* Tabla de detalle */
.listado-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.listado-detail-table th {
    background: #f7fafc;
    padding: 6px 10px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #718096;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.listado-detail-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #f0f4f8;
    vertical-align: middle;
    white-space: nowrap;
}
.listado-detail-table tr:last-child td { border-bottom: none; }

/* Estados de fila */
.ld-row-ok   td { background: #f0fff4; }
.ld-row-missed td { background: #fff5f5; }
.ld-row-reason td {
    background: #fffbf0;
    color: #744210;
    font-size: 11px;
    padding: 4px 10px 6px 24px;
    border-bottom: 1px solid #f0e6c8;
}

/* Diferencias de duración */
.ld-diff-over  { color: #c05621; font-weight: 600; }
.ld-diff-under { color: #3182ce; font-weight: 600; }
.ld-diff-ok    { color: #276749; }

/* Título del episodio */
.ld-title {
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #4a5568;
}
.ld-end-time { color: #a0aec0; font-size: 11px; }
.ld-no-data  { color: #cbd5e0; }
.ld-status-ok   { color: #276749; font-weight: 700; font-size: 14px; }
.ld-status-miss { color: #c53030; font-weight: 700; font-size: 14px; cursor: pointer; }
.ld-status-miss:hover { text-decoration: underline; }
.ld-badge-live  { font-size: 13px; margin-left: 4px; vertical-align: middle; opacity: .85; }

/* Fechas de fallos (compatibilidad con modal) */
.fecha-fallo {
    display: inline-block;
    background: #fff5f5;
    color: #c53030;
    border: 1px solid #fed7d7;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    cursor: pointer;
    transition: background .1s, border-color .1s;
}
.fecha-fallo:hover {
    background: #fed7d7;
    border-color: #fc8181;
}
.fecha-fallo.fecha-manual {
    background: #f0fff4;
    color: #276749;
    border-color: #9ae6b4;
    text-decoration: line-through;
    opacity: .8;
}

.badge-falla {
    background: #fed7d7;
    color: #9b2335;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 12px;
    white-space: nowrap;
}
.badge-ok {
    background: #c6f6d5;
    color: #276749;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 12px;
    white-space: nowrap;
}

/* Toggle buttons active state */
.btn-vista-activo {
    background: #667eea !important;
    color: #fff !important;
    border-color: #5a67d8 !important;
}

/* ── Print / Export ── */
.no-print { }
@keyframes btn-pulso {
    0%, 100% { opacity: 1; }
    50%       { opacity: .55; }
}
.btn-generando { animation: btn-pulso 1.2s ease-in-out infinite; }
@media print {
    /* Ocultar cabecera SAPO, footer, alertas y barra de impersonación */
    .header, footer, .alert, .alert-success, .alert-error, .alert-warning,
    .no-print { display: none !important; }
    .container { padding: 0 !important; margin: 0 !important; max-width: none !important; }
    .card { box-shadow: none !important; border: none !important; padding: 0 !important; }

    /* Sticky off — necesario para que el navegador imprima bien */
    .seguimiento-table thead th,
    .seguimiento-table .col-programa { position: static !important; }

    /* Asegurarse de que la tabla cabe (escalar si hace falta) */
    .seguimiento-table { font-size: 10px; }
    .seguimiento-table .col-programa { min-width: 140px; max-width: 180px; }
    .seguimiento-table .col-dia { width: 24px; min-width: 24px; }

    /* Colores de fondo visibles al imprimir */
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* Sin scroll horizontal */
    .tabla-wrapper { overflow: visible !important; }

    /* Salto de página razonable */
    @page { size: A4 landscape; margin: 10mm; }
}
</style>

<!-- ── Exportar PDF / Imagen ─────────────────────────────────────────────────── -->
<!-- html2canvas se carga de forma diferida solo al pulsar el botón Imagen -->
<script>
function exportarPDF() {
    window.print();
}

function exportarImagen() {
    var btn = document.getElementById('btn-imagen');
    btn.disabled = true;
    btn.classList.add('btn-generando');
    btn.innerHTML = '<span class="btn-icon">⏳</span> Generando…';

    function restaurar() {
        btn.disabled = false;
        btn.classList.remove('btn-generando');
        btn.innerHTML = '<span class="btn-icon">🖼️</span> Imagen';
    }

    function capturar() {
        var card    = document.getElementById('seguimiento-card');
        var mesLabel = <?php echo json_encode($monthLabel); ?>;
        var ahora   = new Date().toLocaleString('es-ES', {dateStyle:'long', timeStyle:'short'});

        try {
            html2canvas(card, {
                scale:           2,
                useCORS:         true,
                allowTaint:      true,
                backgroundColor: '#ffffff',
                logging:         false,
                width:           card.scrollWidth,
                height:          card.scrollHeight,
                x:               0,
                y:               0,
                onclone: function(clonedDoc) {
                    // 1. Eliminar el degradado gris del body (lavaría los colores)
                    clonedDoc.body.style.background = '#ffffff';

                    // 2. Desactivar animaciones (fadeIn empieza en opacity:0)
                    var s = clonedDoc.createElement('style');
                    s.textContent = '*{animation:none!important;transition:none!important;}';
                    clonedDoc.head.appendChild(s);

                    // 3. Ocultar con display:none (no visibility) → sin hueco en blanco
                    clonedDoc.querySelectorAll('.no-print').forEach(function(el) {
                        el.style.display = 'none';
                    });

                    // 4. Ocultar barra de navegación prev/next/leyenda
                    var nav = clonedDoc.getElementById('nav-mes');
                    if (nav) nav.style.display = 'none';

                    // 5. Pie de página con mes y fecha de generación
                    var pie = clonedDoc.createElement('div');
                    pie.style.cssText = 'padding:8px 24px;font-size:11px;color:#718096;' +
                                        'text-align:right;border-top:1px solid #e2e8f0;';
                    pie.textContent = mesLabel + ' · Generado el ' + ahora + ' · SAPO';
                    clonedDoc.getElementById('seguimiento-card').appendChild(pie);
                }
            }).then(function(canvas) {
                restaurar();
                var link      = document.createElement('a');
                link.download = 'seguimiento_<?php echo htmlEsc($trackingUsername . '_' . $targetMonth); ?>.png';
                link.href     = canvas.toDataURL('image/png');
                link.click();
            }).catch(function(err) {
                restaurar();
                alert('Error al generar la imagen: ' + err);
            });
        } catch(err) {
            restaurar();
            alert('Error al generar la imagen: ' + err);
        }
    }

    // Carga diferida: solo descarga html2canvas la primera vez que se pulsa
    if (typeof html2canvas !== 'undefined') {
        capturar();
    } else {
        var s    = document.createElement('script');
        s.src    = 'assets/html2canvas.min.js';
        s.onload = capturar;
        s.onerror = function() {
            restaurar();
            alert('No se pudo cargar la librería de exportación.\n' +
                  'Comprueba que el archivo assets/html2canvas.min.js existe en el servidor.');
        };
        document.head.appendChild(s);
    }
}
</script>

<!-- ── Toggle de vistas: cronológica / por programas / rejilla ───────────────── -->
<script>
function toggleVista(vista) {
    var vistas = ['resumen', 'programas', 'detalle'];
    vistas.forEach(function(v) {
        var el  = document.getElementById('vista-' + v);
        var btn = document.getElementById('btn-vista-' + v);
        var show = (v === vista);
        if (el)  el.style.display  = show ? '' : 'none';
        if (btn) btn.classList.toggle('btn-vista-activo', show);
    });
    var fc = document.getElementById('filtros-cronologico');
    if (fc) fc.style.display = (vista === 'resumen') ? '' : 'none';
}
toggleVista('resumen');

function toggleListadoCard(bodyId, headerEl) {
    var body    = document.getElementById(bodyId);
    var chevron = headerEl ? headerEl.querySelector('.listado-chevron') : null;
    if (!body) return;
    var open = body.style.display !== 'none' && body.style.display !== '';
    body.style.display = open ? 'none' : 'block';
    if (chevron) chevron.classList.toggle('open', !open);
}

var _ordenActual = 'fallos';
var _ordenDir    = { fallos: 'desc', nombre: 'asc' };

function ordenarResumen(criterio) {
    if (criterio === _ordenActual) {
        _ordenDir[criterio] = _ordenDir[criterio] === 'asc' ? 'desc' : 'asc';
    } else {
        _ordenActual = criterio;
    }
    var dir   = _ordenDir[criterio];
    var arrow = { asc: ' ↑', desc: ' ↓' };
    var btnF  = document.getElementById('sort-fallos');
    var btnN  = document.getElementById('sort-nombre');
    if (btnF) { btnF.classList.toggle('btn-vista-activo', criterio === 'fallos'); btnF.textContent = '% Fallos' + (criterio === 'fallos' ? arrow[dir] : ''); }
    if (btnN) { btnN.classList.toggle('btn-vista-activo', criterio === 'nombre'); btnN.textContent = 'Nombre'  + (criterio === 'nombre'  ? arrow[dir] : ''); }

    var lista = document.getElementById('lista-resumen');
    if (!lista) return;
    var rows = Array.from(lista.querySelectorAll(':scope > .listado-card'));
    rows.sort(function(a, b) {
        var r = criterio === 'nombre'
            ? (a.dataset.name || '').localeCompare(b.dataset.name || '', 'es')
            : (parseInt(b.dataset.fails || 0) - parseInt(a.dataset.fails || 0)) ||
              (parseInt(a.dataset.pct   || 100) - parseInt(b.dataset.pct   || 100));
        return dir === 'asc' ? -r : r;
    });
    rows.forEach(function(r) { lista.appendChild(r); });
}

// ── Vista cronológica: filtro y paginación ────────────────────────────────────
var _cronoFiltro = null;   // null | 'played' | 'missed'
var _cronoPagina = 1;
var _cronoPorPag = 60;

function _cronoVisibleRows() {
    var all = Array.from(document.querySelectorAll('#crono-tbody .crono-row'));
    return _cronoFiltro ? all.filter(function(r) { return r.dataset.status === _cronoFiltro; }) : all;
}

function _cronoRender() {
    var all    = Array.from(document.querySelectorAll('#crono-tbody .crono-row'));
    var shown  = _cronoFiltro ? all.filter(function(r) { return r.dataset.status === _cronoFiltro; }) : all;
    var total  = shown.length;
    var pages  = Math.max(1, Math.ceil(total / _cronoPorPag));
    if (_cronoPagina > pages) _cronoPagina = pages;

    var start   = (_cronoPagina - 1) * _cronoPorPag;
    var pageRows = shown.slice(start, start + _cronoPorPag);
    var pageGroups = new Set(pageRows.map(function(r) { return r.dataset.group; }));

    // Ocultar todo
    document.querySelectorAll('#crono-tbody tr').forEach(function(tr) { tr.style.display = 'none'; });
    // Mostrar filas de la página actual (y sus filas de motivo)
    pageRows.forEach(function(r) { r.style.display = ''; });
    pageGroups.forEach(function(g) {
        document.querySelectorAll('#crono-tbody .crono-row-reason[data-group="' + g + '"]').forEach(function(rr) { rr.style.display = ''; });
    });

    // Stats
    var missed  = all.filter(function(r) { return r.dataset.status === 'missed'; }).length;
    var statsEl = document.getElementById('crono-stats');
    if (statsEl) {
        statsEl.innerHTML = '<strong style="color:#2d3748;">' + total + '</strong> emisión' + (total !== 1 ? 'es' : '') +
            (missed > 0
                ? ' · <strong style="color:#c53030;">' + missed + '</strong> no emitida' + (missed !== 1 ? 's' : '')
                : ' · <strong style="color:#276749;">✓ Todo correcto</strong>');
    }

    // Paginación
    var pageText = pages > 1 ? 'Página ' + _cronoPagina + ' de ' + pages : '';
    ['', '-bot'].forEach(function(sfx) {
        var pi   = document.getElementById('crono-page-info' + sfx);
        var prev = document.getElementById('crono-prev' + sfx);
        var next = document.getElementById('crono-next' + sfx);
        if (pi)   pi.textContent  = pageText;
        if (prev) prev.disabled   = _cronoPagina <= 1;
        if (next) next.disabled   = _cronoPagina >= pages;
    });
}

function filtrarCronologico(status) {
    _cronoFiltro = (_cronoFiltro === status) ? null : status;
    _cronoPagina = 1;
    var bf = document.getElementById('btn-filtro-fallados');
    var bc = document.getElementById('btn-filtro-correctos');
    if (bf) bf.style.opacity = (!_cronoFiltro || _cronoFiltro === 'missed') ? '1' : '0.4';
    if (bc) bc.style.opacity = (!_cronoFiltro || _cronoFiltro === 'played') ? '1' : '0.4';
    _cronoRender();
}

function cronoPrev() { if (_cronoPagina > 1) { _cronoPagina--; _cronoRender(); } }
function cronoNext() {
    var pages = Math.max(1, Math.ceil(_cronoVisibleRows().length / _cronoPorPag));
    if (_cronoPagina < pages) { _cronoPagina++; _cronoRender(); }
}

_cronoRender();
</script>

<!-- ── Modal corrección manual ───────────────────────────────────────────────── -->
<input type="hidden" id="csrf-override" value="<?php echo generateCSRFToken(); ?>">

<div id="modal-override" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;padding:24px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <h3 id="modal-title" style="margin:0 0 6px;font-size:15px;color:#2d3748;"></h3>
        <p id="modal-desc" style="color:#718096;font-size:13px;margin:0 0 16px;line-height:1.5;"></p>
        <div id="modal-form-area">
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;color:#4a5568;">Motivo (opcional)</label>
            <input id="modal-reason" type="text" maxlength="200" placeholder="Ej: API caída 14:00-14:15, sí emitió"
                   style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:4px;font-size:13px;box-sizing:border-box;">
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;">
            <button onclick="cerrarModalOverride()" class="btn btn-secondary" style="font-size:13px;">Cancelar</button>
            <button id="modal-confirm-btn" class="btn btn-primary" style="font-size:13px;">Confirmar</button>
        </div>
    </div>
</div>

<script>
(function() {
    var _cell = null; // { prog, date, isLive, isManual, td }

    function abrirModal(el) {
        var prog   = el.dataset.prog;
        var date   = el.dataset.date;
        var manual = el.dataset.manual === '1';
        var isLive = el.dataset.live === '1';
        _cell = { prog: prog, date: date, isLive: isLive, isManual: manual, td: el };

        // Nombre del programa (distinto según vista)
        var progName;
        if (el.tagName === 'TD') {
            var nameEl = el.closest('tr').querySelector('.col-programa span[title]');
            progName = nameEl ? nameEl.getAttribute('title') : prog;
        } else {
            // Vista listado (span dentro de td)
            var card = el.closest('.listado-card');
            if (card) {
                var nameEl = card.querySelector('.listado-card-nombre');
                progName = nameEl ? nameEl.textContent.trim() : prog;
            } else {
                progName = prog;
            }
        }
        // Fecha legible
        var parts = date.split('-');
        var dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        var dateStr = dateObj.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });

        document.getElementById('modal-title').textContent = progName + ' — ' + dateStr;

        var formArea   = document.getElementById('modal-form-area');
        var confirmBtn = document.getElementById('modal-confirm-btn');

        if (manual) {
            document.getElementById('modal-desc').textContent =
                'Esta emisión fue marcada manualmente como emitida. ¿Quitar la corrección y volver a marcarla como no emitida?';
            formArea.style.display = 'none';
            confirmBtn.textContent = 'Quitar corrección';
            confirmBtn.style.background = '#e53e3e';
            confirmBtn.style.borderColor = '#e53e3e';
        } else {
            document.getElementById('modal-desc').textContent =
                'AzuraCast no registró esta emisión. ¿Marcarla como emitida manualmente?';
            formArea.style.display = 'block';
            document.getElementById('modal-reason').value = '';
            confirmBtn.textContent = 'Marcar como emitida ✓';
            confirmBtn.style.background = '';
            confirmBtn.style.borderColor = '';
        }

        var modal = document.getElementById('modal-override');
        modal.style.display = 'flex';
        if (!manual) setTimeout(function() { document.getElementById('modal-reason').focus(); }, 50);
    }

    window.cerrarModalOverride = function() {
        document.getElementById('modal-override').style.display = 'none';
        var btn = document.getElementById('modal-confirm-btn');
        btn.disabled = false;
        _cell = null;
    };

    document.getElementById('modal-confirm-btn').addEventListener('click', function() {
        if (!_cell) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Guardando…';

        var data = new FormData();
        data.append('action',     'override_emision');
        data.append('csrf_token', document.getElementById('csrf-override').value);
        data.append('prog_key',   _cell.prog);
        data.append('date',       _cell.date);
        data.append('station',    '<?php echo htmlEsc($trackingUsername); ?>');
        if (_cell.isManual) {
            data.append('remove', '1');
        } else {
            data.append('reason', document.getElementById('modal-reason').value);
        }

        fetch('', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (r.ok) {
                    actualizarCelda(_cell.td, _cell.isManual, _cell.isLive);
                    cerrarModalOverride();
                } else {
                    alert('Error: ' + (r.error || 'Error desconocido'));
                    btn.disabled = false;
                    btn.textContent = _cell.isManual ? 'Quitar corrección' : 'Marcar como emitida ✓';
                }
            })
            .catch(function() {
                alert('Error de red al guardar la corrección.');
                btn.disabled = false;
                btn.textContent = _cell.isManual ? 'Quitar corrección' : 'Marcar como emitida ✓';
            });
    });

    function actualizarCelda(el, wasManual, isLive) {
        if (el.classList.contains('ld-status-miss')) {
            // Span de estado en vista cronológica o listado
            if (wasManual) {
                el.textContent = '✗';
                el.classList.remove('ld-manual');
                el.style.textDecoration = '';
                el.dataset.manual = '0';
            } else {
                el.textContent = '✎';
                el.classList.add('ld-manual');
                el.style.color = '#276749';
                el.dataset.manual = '1';
            }
            // Actualizar color de fila y data-status (para el filtro de la vista cronológica)
            var row = el.closest('tr');
            if (row) {
                var newStatus = wasManual ? 'missed' : 'played';
                row.classList.toggle('ld-row-missed', wasManual);
                row.classList.toggle('ld-row-ok', !wasManual);
                row.dataset.status = newStatus;
                // Ocultar la fila de motivo si la emisión se marca como correcta
                var group = row.dataset.group;
                if (group) {
                    var reasonRow = document.querySelector('#crono-tbody .crono-row-reason[data-group="' + group + '"]');
                    if (reasonRow) reasonRow.style.display = wasManual ? '' : 'none';
                }
            }
        } else if (el.tagName !== 'TD') {
            // Chip de fecha (compatibilidad)
            if (wasManual) {
                el.classList.remove('fecha-manual');
                el.style.textDecoration = '';
                el.dataset.manual = '0';
            } else {
                el.classList.add('fecha-manual');
                el.dataset.manual = '1';
            }
        } else {
            // Celda de tabla (TD)
            if (wasManual) {
                el.classList.remove('celda-manual', 'celda-directo-manual');
                if (isLive) {
                    el.classList.add('celda-directo-perdido');
                    el.textContent = '📡';
                } else {
                    el.classList.add('celda-perdida');
                    el.textContent = '✗';
                }
            } else {
                if (isLive) {
                    el.classList.remove('celda-directo-perdido');
                    el.classList.add('celda-directo-manual');
                    el.textContent = '📡';
                } else {
                    el.classList.remove('celda-perdida');
                    el.classList.add('celda-manual');
                    el.textContent = '✎';
                }
            }
            el.dataset.manual = wasManual ? '0' : '1';
        }
        ajustarTotales(isLive, wasManual ? -1 : +1);
    }

    function ajustarTotales(isLive, delta) {
        if (isLive) {
            var ef    = document.getElementById('total-live-ef');
            var falta = document.getElementById('total-live-faltan');
            if (ef)    ef.textContent    = Math.max(0, parseInt(ef.textContent)    + delta);
            if (falta) falta.textContent = Math.max(0, parseInt(falta.textContent) - delta);
        } else {
            var ok    = document.getElementById('total-emite-ok');
            var falta = document.getElementById('total-faltan');
            if (ok)    ok.textContent    = Math.max(0, parseInt(ok.textContent)    + delta);
            if (falta) falta.textContent = Math.max(0, parseInt(falta.textContent) - delta);
        }
    }

    // Event delegation en la tabla
    var tabla = document.querySelector('.seguimiento-table');
    if (tabla) {
        tabla.addEventListener('click', function(e) {
            var td = e.target.closest('td.celda-corregible');
            if (td) abrirModal(td);
        });
    }

    // Event delegation en vista cronológica y vista por programas
    ['vista-resumen', 'vista-programas'].forEach(function(id) {
        var contenedor = document.getElementById(id);
        if (contenedor) contenedor.addEventListener('click', function(e) {
            var el = e.target.closest('.celda-corregible');
            if (el) abrirModal(el);
        });
    });

    // Cerrar al hacer clic fuera del modal
    document.getElementById('modal-override').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalOverride();
    });

    // Cerrar con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalOverride();
            cerrarFichaPrograma();
            cerrarEditDur();
        }
    });
})();
</script>

<!-- ── Popover edición inline de duración teórica ──────────────────────────── -->
<div id="edit-dur-popover"
     style="display:none; position:fixed; z-index:3000; background:#fff; border:1px solid #cbd5e0;
            border-radius:8px; padding:14px 16px; box-shadow:0 4px 16px rgba(0,0,0,.18); min-width:200px;">
    <label for="edit-dur-input" style="font-size:12px; color:#6b7280; display:block; margin-bottom:8px; font-weight:600;">
        Duración teórica
    </label>
    <div style="display:flex; gap:8px; align-items:center;">
        <select id="edit-dur-input" style="padding:6px 8px; border:1px solid #cbd5e0; border-radius:5px; font-size:14px;">
            <option value="15">15 min</option>
            <option value="30">30 min</option>
            <option value="45">45 min</option>
            <option value="60">1 h</option>
            <option value="90">1 h 30 min</option>
            <option value="120">2 h</option>
            <option value="150">2 h 30 min</option>
            <option value="180">3 h</option>
            <option value="240">4 h</option>
            <option value="300">5 h</option>
            <option value="360">6 h</option>
        </select>
        <button onclick="guardarDur()" class="btn btn-success" style="padding:5px 12px; font-size:13px;">✓</button>
        <button onclick="cerrarEditDur()" class="btn btn-secondary" style="padding:5px 10px; font-size:13px;">✕</button>
    </div>
    <div id="edit-dur-error" style="color:#dc2626; font-size:12px; margin-top:6px; display:none;"></div>
</div>

<script>
(function() {
    var _durEl = null;

    window.abrirEditDur = function(el) {
        _durEl = el;
        var pop = document.getElementById('edit-dur-popover');
        var inp = document.getElementById('edit-dur-input');
        // Pre-seleccionar el valor actual (o el más cercano disponible)
        var cur = parseInt(el.dataset.dur, 10);
        inp.value = cur > 0 ? cur : 60;
        document.getElementById('edit-dur-error').style.display = 'none';
        var r = el.getBoundingClientRect();
        pop.style.top  = (r.bottom + 6 + window.scrollY) + 'px';
        pop.style.left = Math.min(r.left, window.innerWidth - 230) + 'px';
        pop.style.display = 'block';
        inp.focus();
    };

    window.cerrarEditDur = function() {
        document.getElementById('edit-dur-popover').style.display = 'none';
        _durEl = null;
    };

    window.guardarDur = function() {
        var dur   = parseInt(document.getElementById('edit-dur-input').value, 10);
        var errEl = document.getElementById('edit-dur-error');
        var fd = new FormData();
        fd.append('action',       'update_slot_duration');
        fd.append('csrf_token',   document.getElementById('csrf-override').value);
        fd.append('program_name', _durEl.dataset.progkey);
        fd.append('day',          _durEl.dataset.day);
        fd.append('start_time',   _durEl.dataset.time);
        fd.append('duration',     dur);
        fetch(window.location.href, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    // Actualizar todas las celdas del mismo programa y misma hora
                    var key  = _durEl.dataset.progkey;
                    var time = _durEl.dataset.time;
                    document.querySelectorAll('.dur-teo-edit').forEach(function(el) {
                        if (el.dataset.progkey === key && el.dataset.time === time) {
                            el.textContent  = dur + ' min';
                            el.dataset.dur  = dur;
                        }
                    });
                    cerrarEditDur();
                    var t = document.createElement('div');
                    t.textContent = '✓ Duración guardada';
                    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:4000;background:#166534;color:#fff;padding:10px 18px;border-radius:7px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.2);pointer-events:none;';
                    document.body.appendChild(t);
                    setTimeout(function() { t.remove(); }, 2500);
                } else {
                    errEl.textContent = d.error || 'Error al guardar';
                    errEl.style.display = 'block';
                }
            })
            .catch(function() {
                errEl.textContent = 'Error de conexión';
                errEl.style.display = 'block';
            });
    };

    // Clic fuera del popover → cerrar
    document.addEventListener('click', function(e) {
        var pop = document.getElementById('edit-dur-popover');
        if (pop.style.display !== 'none' && !pop.contains(e.target) && e.target !== _durEl) {
            cerrarEditDur();
        }
    });
})();
</script>

<!-- ── Modal iframe: edición de ficha de programa ──────────────────────────── -->
<div id="prog-edit-overlay"
     style="display:none; position:fixed; inset:0; z-index:2000; background:rgba(0,0,0,0.55); overflow-y:auto; padding:24px 16px;"
     onclick="if(event.target===this) cerrarFichaPrograma();">
    <div style="max-width:800px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); position:relative;">
        <button onclick="cerrarFichaPrograma()"
                title="Cerrar"
                style="position:absolute; top:10px; right:14px; z-index:10; background:none; border:none; font-size:22px; cursor:pointer; color:#6b7280; line-height:1;">✕</button>
        <iframe id="prog-edit-iframe"
                src=""
                style="width:100%; min-height:75vh; border:none; display:block;"
                onload="detectarGuardadoEmbed(this)"></iframe>
    </div>
</div>

<script>
function abrirFichaPrograma(btn) {
    var key    = btn.dataset.progkey;
    var iframe = document.getElementById('prog-edit-iframe');
    var overlay = document.getElementById('prog-edit-overlay');
    iframe.src = '?page=program_edit_embed&program=' + encodeURIComponent(key);
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarFichaPrograma() {
    var overlay = document.getElementById('prog-edit-overlay');
    var iframe  = document.getElementById('prog-edit-iframe');
    overlay.style.display = 'none';
    iframe.src = '';
    document.body.style.overflow = '';
}

// Recibir mensaje del iframe cuando se guarda correctamente
window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'programSaved') {
        cerrarFichaPrograma();
        // Toast de confirmación
        var toast = document.createElement('div');
        toast.textContent = '✓ Ficha guardada correctamente';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:3000;background:#166534;color:#fff;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3000);
    }
    if (e.data && e.data.type === 'programCancelled') {
        cerrarFichaPrograma();
    }
});
</script>
