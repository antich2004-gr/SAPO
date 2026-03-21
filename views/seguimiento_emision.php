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
                <h2 style="margin:0;">📊 Seguimiento Emisión</h2>
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
if ($targetMonth > $currentMonth) {
    $targetMonth = $currentMonth;
}

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
$historyMap       = [];
$liveSessionStarts = [];
$dayTimeline      = []; // [Y-m-d => [['playlist'=>..., 'time'=>'HH:MM'], ...]]
$historyError     = false;

if ($hasSchedule) {
    $history = getAzuracastHistory($trackingUsername, $monthStart, $monthEnd);

    if ($history === false) {
        $historyError = true;
    } elseif (is_array($history)) {
        // Recoger timestamps con streamer por día para luego agrupar en sesiones
        $streamerTs = []; // [Y-m-d => [timestamp, ...]] ordenados asc

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
                if (!isset($historyMap[$playlist][$dayStr])) {
                    $historyMap[$playlist][$dayStr] = $timeStr;
                }
                // Timeline para diagnóstico de emisiones perdidas
                $dayTimeline[$dayStr][] = ['playlist' => $playlist, 'time' => $timeStr];
            }

            // Acumular timestamps de streamer para agrupar después
            if ($streamer !== '') {
                $streamerTs[$dayStr][] = (int)$playedAt;
            }
        }

        // Agrupar timestamps de streamer en sesiones (gap > 30 min = nueva sesión)
        // y guardar solo el inicio de cada sesión
        foreach ($streamerTs as $dayStr => $timestamps) {
            sort($timestamps);
            $sessionStart = null;
            $prevTs       = null;
            foreach ($timestamps as $ts) {
                if ($sessionStart === null || ($ts - $prevTs) > 1800) {
                    // Nueva sesión: guardar inicio
                    $dt = new DateTime('@' . $ts);
                    $dt->setTimezone($timezone);
                    $liveSessionStarts[$dayStr][] = $dt->format('H:i');
                    $sessionStart = $ts;
                }
                $prevTs = $ts;
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

// ── Helper: diagnóstico de emisión perdida ────────────────────────────────────
function getMissedReason($scheduledAt, $date, $dayTimeline, $isLive = false, $programKey = '', $dailyReport = null) {
    if ($isLive) {
        $entries = $dayTimeline[$date] ?? [];
        return empty($entries)
            ? 'Sin actividad en AzuraCast ese día (posible corte de señal)'
            : 'Sin stream detectado';
    }

    // 1. Carpeta vacía: sin episodios descargados para esa playlist
    if ($dailyReport && !empty($dailyReport['carpetas_vacias']) && $programKey !== '') {
        $normKey = _normProgName($programKey);
        foreach ($dailyReport['carpetas_vacias'] as $folder) {
            if (_normProgName($folder['nombre']) === $normKey) {
                $dias = $folder['dias'] > 1 ? ' (vacía desde hace ' . $folder['dias'] . ' días)' : '';
                return 'Sin episodios disponibles — carpeta vacía' . $dias;
            }
        }
    }

    // 2. Errores de descarga ese día que podrían haber dejado la playlist sin archivo
    if ($dailyReport && !empty($dailyReport['errores_podget'])) {
        // Buscar si algún error menciona el nombre del programa
        $normKey = $programKey !== '' ? _normProgName($programKey) : '';
        foreach ($dailyReport['errores_podget'] as $err) {
            if ($normKey !== '' && stripos(_normProgName($err), $normKey) !== false) {
                return 'Error de descarga del episodio ese día';
            }
        }
    }

    // 3. Timeline de AzuraCast: qué había sonando en esa franja
    $entries = $dayTimeline[$date] ?? [];
    if (empty($entries)) {
        return 'Sin actividad en AzuraCast ese día (posible corte de señal)';
    }

    $schedMin = timeToMinutes($scheduledAt);
    $inWindow = [];
    foreach ($entries as $e) {
        $diff = abs($schedMin - timeToMinutes($e['time']));
        $diff = min($diff, 1440 - $diff);
        if ($diff <= 60 && !in_array($e['playlist'], $inWindow, true)) {
            $inWindow[] = $e['playlist'];
        }
    }

    if (!empty($inWindow)) {
        $shown = array_slice($inWindow, 0, 2);
        $label = implode(', ', $shown) . (count($inWindow) > 2 ? '…' : '');
        return 'En esa franja emitió: ' . $label;
    }

    return 'Sin emisión en esa franja (posible silencio o corte)';
}

// ── Helper: estado de una celda ───────────────────────────────────────────────
function cellStatus($programKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides = []) {
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
                return ['status' => 'played', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd, 'time' => $t];
            }
        }
        // Fuente 2: informes diarios (sección "Emisiones en directo:")
        foreach ($liveEmissionsPerDay[$day['date']] ?? [] as $t) {
            if (liveTiempoCoincide($scheduledAt, $t)) {
                return ['status' => 'played', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd, 'time' => $t];
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

            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides);
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
                    $liveCell   = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides);
                    $liveStatus = $liveCell['status'];
                    if ($liveStatus === 'played')     { $totals['live_esperados']++; $totals['live_efectivos']++; }
                    elseif ($liveStatus === 'missed') { $totals['live_esperados']++; }
                }
            }
        }
    }
}
$totals['emitidos_azura'] = $totals['emite_ok'] + $totals['live_efectivos'];

// ── Datos para la vista resumen (mini barra por programa) ─────────────────────
$progSummary = [];
if ($hasSchedule) {
    foreach (array_keys($programSchedules) as $progKey) {
        if (isset($absorbedLive[$progKey])) continue;
        $linkedLiveKey = $liveForAutomated[$progKey] ?? null;
        $firstSeen     = $programFirstSeen[$progKey] ?? null;
        $lastActive    = $programLastActive[$progKey] ?? null;
        $s = ['played' => 0, 'missed' => 0, 'live_played' => 0, 'live_missed' => 0, 'days' => [], 'linkedLiveKey' => $linkedLiveKey];
        foreach ($days as $day) {
            $outside = ($firstSeen && $day['date'] < $firstSeen) || ($lastActive && $day['date'] > $lastActive);
            if ($outside) { $s['days'][$day['date']] = 'outside'; continue; }
            $usedLive = false;
            if ($linkedLiveKey !== null) {
                $lf = $programFirstSeen[$linkedLiveKey] ?? null;
                $la = $programLastActive[$linkedLiveKey] ?? null;
                $liveActive = (!$lf || $day['date'] >= $lf) && (!$la || $day['date'] <= $la);
                if ($liveActive) {
                    $lc = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides);
                    if ($lc['status'] === 'played')      { $s['days'][$day['date']] = !empty($lc['manual']) ? 'live-manual' : 'live-played'; $s['live_played']++; }
                    elseif ($lc['status'] === 'missed')  { $s['days'][$day['date']] = 'live-missed'; $s['live_missed']++; }
                    else                                 { $s['days'][$day['date']] = 'live-expected'; }
                    $usedLive = true;
                }
            }
            if (!$usedLive) {
                $c = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides);
                if ($c['status'] === 'played')      { $s['days'][$day['date']] = !empty($c['manual']) ? 'manual' : 'played'; $s['played']++; }
                elseif ($c['status'] === 'missed')  { $s['days'][$day['date']] = 'missed'; $s['missed']++; }
                elseif ($c['status'] === 'expected'){ $s['days'][$day['date']] = 'expected'; }
                else                                { $s['days'][$day['date']] = 'none'; }
            }
        }
        $progSummary[$progKey] = $s;
    }
    // Ordenar: más fallos primero
    uasort($progSummary, function($a, $b) {
        return ($b['missed'] + $b['live_missed']) - ($a['missed'] + $a['live_missed']);
    });
}
?>

<div class="card" id="seguimiento-card" style="padding: 0;">

    <!-- ── Cabecera ─────────────────────────────────────────────────────────── -->
    <div style="padding: 20px 24px 16px; border-bottom: 1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 2px 0;">📊 Seguimiento Emisión</h2>
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
        <a href="?page=seguimiento_emision&month=<?php echo htmlEsc($prevMonth . $stationParam); ?>"
           class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">← Anterior</a>

        <span style="font-size:18px; font-weight:700; color:#2d3748; min-width:180px; text-align:center;">
            <?php echo htmlEsc($monthLabel); ?>
        </span>

        <?php if ($canGoNext): ?>
            <a href="?page=seguimiento_emision&month=<?php echo htmlEsc($nextMonth . $stationParam); ?>"
               class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Siguiente →</a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size:13px; padding:6px 14px; opacity:.4; cursor:not-allowed;">Siguiente →</button>
        <?php endif; ?>

        <!-- Toggle de vista -->
        <div style="display:flex; gap:3px;" class="no-print">
            <button id="btn-vista-resumen" onclick="toggleVista('resumen')" class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">📊 Resumen</button>
            <button id="btn-vista-detalle" onclick="toggleVista('detalle')" class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">📋 Detalle</button>
        </div>

        <!-- Leyenda -->
        <div style="margin-left:auto; display:flex; gap:12px; align-items:center; font-size:12px; color:#4a5568; flex-wrap:wrap;">
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#e2e8f0;border-radius:3px;display:inline-block;"></span> Esperado</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#c6f6d5;border-radius:3px;display:inline-block;"></span> Emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#fed7d7;border-radius:3px;display:inline-block;"></span> No emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#3b82f6;border-radius:3px;display:inline-block;font-size:10px;text-align:center;line-height:14px;color:#fff;">📡</span> Directo emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#ef4444;border-radius:3px;display:inline-block;font-size:10px;text-align:center;line-height:14px;color:#fff;">📡</span> Directo no emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#94a3b8;border-radius:3px;display:inline-block;font-size:10px;text-align:center;line-height:14px;color:#fff;">📡</span> Directo esperado</span>
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

    <!-- ── Vista resumen ─────────────────────────────────────────────────────── -->
    <div id="vista-resumen" style="padding:16px 24px 24px;">
        <?php if ($hasSchedule && !empty($progSummary)):
            // Totales del resumen para la cabecera
            $resTotalFails = 0; $resTotalProgs = 0;
            foreach ($progSummary as $s2) { $resTotalFails += $s2['missed'] + $s2['live_missed']; $resTotalProgs++; }
        ?>
        <!-- Cabecera del resumen -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #e2e8f0;">
            <span style="font-size:13px; color:#718096;">
                <strong style="color:#2d3748;"><?php echo $resTotalProgs; ?></strong> programa<?php echo $resTotalProgs !== 1 ? 's' : ''; ?>
                <?php if ($resTotalFails > 0): ?>
                · <strong style="color:#c53030;"><?php echo $resTotalFails; ?></strong> emisión<?php echo $resTotalFails !== 1 ? 'es' : ''; ?> sin registrar
                <?php else: ?>
                · <strong style="color:#276749;">✓ Todo correcto</strong>
                <?php endif; ?>
            </span>
            <span style="font-size:11px; color:#a0aec0;">Haz clic en una fecha para corregir manualmente</span>
        </div>

        <div style="display:flex; flex-direction:column; gap:5px;">
        <?php
        $dowNames = ['dom','lun','mar','mié','jue','vie','sáb'];
        foreach ($progSummary as $progKey => $s):
            $progDisplay   = $displayNameMap[$progKey] ?? $progKey;
            $totalFails    = $s['missed'] + $s['live_missed'];
            $totalPlayed   = $s['played'] + $s['live_played'];
            $linkedLiveKey = $s['linkedLiveKey'];
            $totalDone     = $totalPlayed + $totalFails;
            $pct           = $totalDone > 0 ? round($totalPlayed / $totalDone * 100) : null;
            if ($totalFails === 0)     $hc = 'rh-ok';
            elseif ($totalFails <= 2)  $hc = 'rh-warn';
            else                       $hc = 'rh-crit';

            // Recopilar días con fallo para mostrarlos como texto
            $failedDays = [];
            foreach ($s['days'] as $date => $st) {
                if (in_array($st, ['missed', 'live-missed'])) {
                    $parts = explode('-', $date);
                    $isLiveFail = ($st === 'live-missed');
                    $dow = (int) date('w', mktime(0,0,0,(int)$parts[1],(int)$parts[2],(int)$parts[0]));
                    $failedDays[] = [
                        'label'  => $dowNames[$dow] . ' ' . (int)$parts[2],
                        'date'   => $date,
                        'isLive' => $isLiveFail,
                        'ovKey'  => ($isLiveFail && $linkedLiveKey) ? $linkedLiveKey : $progKey,
                    ];
                }
            }
        ?>
        <div class="resumen-prog-row <?php echo $hc; ?>">
            <div style="display:flex; align-items:center; gap:14px; width:100%;">
                <!-- Nombre -->
                <div class="resumen-prog-nombre" title="<?php echo htmlEsc(displayName($progDisplay)); ?>">
                    <?php echo htmlEsc(displayName($progDisplay)); ?>
                </div>

                <!-- Barra de progreso -->
                <div class="resumen-barra-wrap">
                    <div class="resumen-barra-fill <?php echo $hc; ?>" style="width:<?php echo $pct ?? 0; ?>%"></div>
                </div>

                <!-- Stats -->
                <div class="resumen-prog-stats">
                    <?php if ($pct !== null): ?>
                    <span class="resumen-pct <?php echo $hc; ?>"><?php echo $pct; ?>%</span>
                    <?php endif; ?>
                    <span style="font-size:12px; color:#276749; white-space:nowrap;"><?php echo $totalPlayed; ?> emitidos</span>
                    <?php if ($totalFails > 0): ?>
                    <span class="badge-falla"><?php echo $totalFails; ?> sin emitir</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fechas de fallos -->
            <?php if (!empty($failedDays)): ?>
            <div class="resumen-fechas-fallo">
                <?php foreach ($failedDays as $fd): ?>
                <span class="fecha-fallo celda-corregible"
                      data-prog="<?php echo htmlEsc($fd['ovKey']); ?>"
                      data-date="<?php echo htmlEsc($fd['date']); ?>"
                      data-live="<?php echo $fd['isLive'] ? '1' : '0'; ?>"
                      data-manual="0"><?php echo htmlEsc($fd['label']); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <?php else: ?>
        <p style="color:#718096; font-size:13px; margin:0;">No hay datos de programación disponibles.</p>
        <?php endif; ?>
    </div>

    <!-- ── Vista detalle (tabla + totales) ───────────────────────────────────── -->
    <div id="vista-detalle">

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
                                $liveCell   = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides);
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
                                        $cls     = 'celda-directo-emitido';
                                        $liveEnd = $liveCell['scheduledEnd'] ?? null;
                                        $tooltip = 'Directo emitido ' . $liveCell['time'] . 'h' . ($liveEnd ? ' - ' . $liveEnd . 'h' : '') . ' (esperado ' . $liveCell['scheduledAt'] . 'h)';
                                        $icon    = '📡';
                                    }
                                } elseif ($liveStatus === 'missed') {
                                    $cls     = 'celda-directo-perdido';
                                    $reason  = getMissedReason($liveCell['scheduledAt'], $day['date'], $dayTimeline, true, $linkedLiveKey, $dailyReports[$day['date']] ?? null);
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
                            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts, $overrides);
                            $status = $cell['status'];
                            switch ($status) {
                                case 'played':
                                    if (!empty($cell['manual'])) {
                                        $cls          = 'celda-manual';
                                        $tooltip      = 'Corrección manual' . ($cell['reason'] ? ': ' . $cell['reason'] : '') . ' · ' . $cell['corrected_by'] . ' ' . $cell['corrected_at'];
                                        $icon         = '✎';
                                        $isManualCell = true;
                                    } else {
                                        $cls     = 'celda-emitida';
                                        $tooltip = 'Emitido a las ' . $cell['time'] . 'h (esperado ' . $cell['scheduledAt'] . 'h)';
                                        $icon    = '✓';
                                    }
                                    break;
                                case 'missed':
                                    $cls     = 'celda-perdida';
                                    $reason  = getMissedReason($cell['scheduledAt'], $day['date'], $dayTimeline, $isLiveProg, $progKey, $dailyReports[$day['date']] ?? null);
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

    <!-- ── Resumen ──────────────────────────────────────────────────────────── -->
    <?php if ($hasSchedule):
        $totalEsperados  = $totals['emite_ok'] + $totals['faltan'];
        $livefaltan      = $totals['live_esperados'] - $totals['live_efectivos'];
    ?>
    <div style="padding:0 24px 24px;">
        <div style="display:flex;gap:12px;align-items:flex-end;">
            <!-- Grupo EMISIONES -->
            <div style="display:flex;flex-direction:column;gap:0;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#fff;background:#4a5568;padding:5px 12px;border-radius:6px 6px 0 0;text-align:center;">Emisiones</div>
                <div style="display:flex;align-items:stretch;gap:0;font-size:13px;font-weight:600;border-radius:0 0 8px 8px;overflow:hidden;border:1px solid #e2e8f0;">
                    <div style="background:#f0fff4;color:#276749;padding:10px 18px;display:flex;flex-direction:column;align-items:center;gap:2px;border-right:1px solid #9ae6b4;">
                        <span style="font-size:11px;font-weight:400;color:#48bb78;text-transform:uppercase;letter-spacing:.04em;">Esperadas</span>
                        <span style="font-size:20px;"><?php echo $totalEsperados; ?></span>
                    </div>
                    <div style="background:#c6f6d5;color:#276749;padding:10px 18px;display:flex;flex-direction:column;align-items:center;gap:2px;border-right:1px solid #9ae6b4;">
                        <span style="font-size:11px;font-weight:400;color:#276749;text-transform:uppercase;letter-spacing:.04em;">Emitidas</span>
                        <span id="total-emite-ok" style="font-size:20px;"><?php echo $totals['emite_ok']; ?></span>
                    </div>
                    <div style="background:#fed7d7;color:#9b2335;padding:10px 18px;display:flex;flex-direction:column;align-items:center;gap:2px;">
                        <span style="font-size:11px;font-weight:400;color:#c53030;text-transform:uppercase;letter-spacing:.04em;">Faltan</span>
                        <span id="total-faltan" style="font-size:20px;"><?php echo $totals['faltan']; ?></span>
                    </div>
                </div>
            </div>
            <!-- Grupo DIRECTOS -->
            <div style="display:flex;flex-direction:column;gap:0;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#fff;background:#2563eb;padding:5px 12px;border-radius:6px 6px 0 0;text-align:center;">Directos</div>
                <div style="display:flex;align-items:stretch;gap:0;font-size:13px;font-weight:600;border-radius:0 0 8px 8px;overflow:hidden;border:1px solid #e2e8f0;">
                    <div style="background:#e2e8f0;color:#4a5568;padding:10px 18px;display:flex;flex-direction:column;align-items:center;gap:2px;border-right:1px solid #cbd5e0;">
                        <span style="font-size:11px;font-weight:400;color:#718096;text-transform:uppercase;letter-spacing:.04em;">📡 Esperados</span>
                        <span style="font-size:20px;"><?php echo $totals['live_esperados']; ?></span>
                    </div>
                    <div style="background:#3b82f6;color:#fff;padding:10px 18px;display:flex;flex-direction:column;align-items:center;gap:2px;border-right:1px solid #2563eb;">
                        <span style="font-size:11px;font-weight:400;color:#bfdbfe;text-transform:uppercase;letter-spacing:.04em;">📡 Emitidos</span>
                        <span id="total-live-ef" style="font-size:20px;"><?php echo $totals['live_efectivos']; ?></span>
                    </div>
                    <div style="background:#ef4444;color:#fff;padding:10px 18px;display:flex;flex-direction:column;align-items:center;gap:2px;">
                        <span style="font-size:11px;font-weight:400;color:#fecaca;text-transform:uppercase;letter-spacing:.04em;">📡 Faltan</span>
                        <span id="total-live-faltan" style="font-size:20px;"><?php echo $livefaltan; ?></span>
                    </div>
                </div>
            </div>
        </div>
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

        <p style="margin:10px 0 4px;"><strong>Directos detectados vía historial AzuraCast (inicio de sesión DJ):</strong></p>
        <pre style="background:#fff;padding:8px;border:1px solid #e2e8f0;overflow:auto;max-height:120px;"><?php
            if (empty($liveSessionStarts)) {
                echo "(ningún evento de streamer en el historial de este mes)\n";
            } else {
                foreach ($liveSessionStarts as $d => $times) {
                    echo htmlEsc("$d: " . implode(', ', $times) . "\n");
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

/* ── Vista resumen ── */
.resumen-prog-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 14px 10px 16px;
    border-radius: 7px;
    background: #fff;
    border: 1px solid #e8edf2;
    border-left: 4px solid #cbd5e0;
    transition: box-shadow .12s;
}
.resumen-prog-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.resumen-prog-row.rh-ok   { border-left-color: #48bb78; }
.resumen-prog-row.rh-warn { border-left-color: #ed8936; }
.resumen-prog-row.rh-crit { border-left-color: #e53e3e; }

.resumen-prog-nombre {
    min-width: 180px;
    max-width: 240px;
    font-size: 13px;
    font-weight: 600;
    color: #2d3748;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-shrink: 0;
}

/* Barra de progreso */
.resumen-barra-wrap {
    flex: 1;
    height: 8px;
    background: #edf2f7;
    border-radius: 4px;
    overflow: hidden;
    min-width: 80px;
}
.resumen-barra-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .3s ease;
}
.resumen-barra-fill.rh-ok   { background: #48bb78; }
.resumen-barra-fill.rh-warn { background: #ed8936; }
.resumen-barra-fill.rh-crit { background: #fc8181; }

/* Stats */
.resumen-prog-stats {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.resumen-pct {
    font-size: 14px;
    font-weight: 700;
    min-width: 36px;
    text-align: right;
}
.resumen-pct.rh-ok   { color: #276749; }
.resumen-pct.rh-warn { color: #c05621; }
.resumen-pct.rh-crit { color: #c53030; }

/* Fechas de fallos */
.resumen-fechas-fallo {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    padding-top: 2px;
}
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
/* Chip corregido manualmente */
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

<!-- ── Toggle de vista resumen / detalle ─────────────────────────────────────── -->
<script>
function toggleVista(vista) {
    var r    = document.getElementById('vista-resumen');
    var d    = document.getElementById('vista-detalle');
    var btnR = document.getElementById('btn-vista-resumen');
    var btnD = document.getElementById('btn-vista-detalle');
    if (vista === 'resumen') {
        if (r) r.style.display = '';
        if (d) d.style.display = 'none';
        if (btnR) btnR.classList.add('btn-vista-activo');
        if (btnD) btnD.classList.remove('btn-vista-activo');
    } else {
        if (r) r.style.display = 'none';
        if (d) d.style.display = '';
        if (btnR) btnR.classList.remove('btn-vista-activo');
        if (btnD) btnD.classList.add('btn-vista-activo');
    }
    try { localStorage.setItem('sapo_vista', vista); } catch(e) {}
}
// Inicializar desde localStorage
(function() {
    var v = 'resumen';
    try { v = localStorage.getItem('sapo_vista') || 'resumen'; } catch(e) {}
    toggleVista(v);
})();
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
            var row = el.closest('.resumen-prog-row');
            var nameEl = row ? row.querySelector('.resumen-prog-nombre') : null;
            progName = nameEl ? nameEl.getAttribute('title') || nameEl.textContent.trim() : prog;
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
        if (el.tagName !== 'TD') {
            // Chip de fecha en vista resumen
            if (wasManual) {
                // Revertir: volver a mostrar como fallo (quitar corrección)
                el.classList.remove('fecha-manual');
                el.style.textDecoration = '';
                el.dataset.manual = '0';
            } else {
                // Marcar como corregido: tachar el chip
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

    // Event delegation en la vista resumen (chips de fecha)
    var resumen = document.getElementById('vista-resumen');
    if (resumen) {
        resumen.addEventListener('click', function(e) {
            var chip = e.target.closest('.fecha-fallo.celda-corregible');
            if (chip) abrirModal(chip);
        });
    }

    // Cerrar al hacer clic fuera del modal
    document.getElementById('modal-override').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalOverride();
    });

    // Cerrar con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarModalOverride();
    });
})();
</script>
