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
        $type     = $dbPrograms[$name]['playlist_type'] ?? 'program';
        $orphaned = $dbPrograms[$name]['orphaned'] ?? false;
        // Solo 'program' desde AzuraCast; live se añade aparte
        if ($orphaned || $type !== 'program') {
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
    $type     = $programInfo['playlist_type'] ?? 'program';
    $orphaned = $programInfo['orphaned'] ?? false;
    if ($type !== 'live' || $orphaned) continue;

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

// ── Cargar emisiones en directo desde informes diarios ───────────────────────
// Los informes diarios son ficheros pequeños ya procesados, mucho más rápidos
// que escanear el log de Liquidsoap completo.
// liveEmissionsPerDay[Y-m-d] = ['HH:MM', ...] — horas de inicio de directos
$liveEmissionsPerDay = [];

if (!empty($livePrograms)) {
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
            if (empty($rd['emisiones_directo'])) continue;
            $times = [];
            foreach ($rd['emisiones_directo'] as $entry) {
                // "- DD-MM-YYYY HH:MM:SS → HH:MM:SS desde DJ origin"
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

// ── Helper: estado de una celda ───────────────────────────────────────────────
function cellStatus($programKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts) {
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
        return ['status' => 'missed', 'scheduledAt' => $scheduledAt, 'scheduledEnd' => $scheduledEnd];
    }

    // Programa automatizado: verificar en historial AzuraCast
    $historyKey = $historyNameMap[$programKey] ?? $programKey;
    $firstPlay  = $historyMap[$historyKey][$day['date']] ?? null;

    if ($firstPlay !== null) {
        return ['status' => 'played', 'scheduledAt' => $scheduledAt, 'time' => $firstPlay];
    }

    return ['status' => 'missed', 'scheduledAt' => $scheduledAt];
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
            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts);
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
                $liveCell   = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts);
                $liveStatus = $liveCell['status'];
                if ($liveStatus === 'played')     { $totals['live_esperados']++; $totals['live_efectivos']++; }
                elseif ($liveStatus === 'missed') { $totals['live_esperados']++; }
            }
        }
    }
}
$totals['emitidos_azura'] = $totals['emite_ok'] + $totals['live_efectivos'];
?>

<div class="card" style="padding: 0;">

    <!-- ── Cabecera ─────────────────────────────────────────────────────────── -->
    <div style="padding: 20px 24px 16px; border-bottom: 1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0 0 2px 0;">📊 Seguimiento Emisión</h2>
                <span style="color:#718096; font-size:13px;">📻 <strong><?php echo htmlEsc($trackingStation); ?></strong></span>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <?php if (isAdmin()): ?>
                <a href="?" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">⚙️</span> Panel Admin</a>
                <?php else: ?>
                <a href="?" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">←</span> Volver</a>
                <?php endif; ?>
                <form method="POST" style="display:inline; margin:0;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Navegación de mes ────────────────────────────────────────────────── -->
    <div style="padding:14px 24px; background:#f7fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:16px;">
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

    <!-- ── Tabla ─────────────────────────────────────────────────────────────── -->
    <?php if ($hasSchedule): ?>
    <div style="overflow-x:auto; padding: 0 0 24px 0;">
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
                        <span title="<?php echo htmlEsc($progDisplay); ?>">
                            <?php if ($isLiveProg): ?><span style="color:#ef4444;font-size:10px;">🔴 </span><?php endif; ?>
                            <?php echo htmlEsc($progDisplay); ?>
                        </span>
                    </td>
                    <?php foreach ($days as $day):
                        $cls = $tooltip = $icon = '';

                        // If this automated program has a linked live version, check live status first
                        if ($linkedLiveKey !== null) {
                            $liveCell   = cellStatus($linkedLiveKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts);
                            $liveStatus = $liveCell['status'];
                            if ($liveStatus === 'played') {
                                $cls     = 'celda-directo-emitido';
                                $liveEnd = $liveCell['scheduledEnd'] ?? null;
                                $tooltip = 'Directo emitido ' . $liveCell['time'] . 'h' . ($liveEnd ? ' - ' . $liveEnd . 'h' : '') . ' (esperado ' . $liveCell['scheduledAt'] . 'h)';
                                $icon    = '📡';
                            } elseif ($liveStatus === 'missed') {
                                $cls     = 'celda-directo-perdido';
                                $tooltip = 'Directo no emitido (esperado ' . $liveCell['scheduledAt'] . 'h)';
                                $icon    = '📡';
                            } elseif ($liveStatus === 'expected') {
                                $cls     = 'celda-directo-esperado';
                                $tooltip = 'Directo programado a las ' . $liveCell['scheduledAt'] . 'h';
                                $icon    = '📡';
                            }
                        }

                        // If no live override, use automated (or standalone live) status
                        if ($cls === '') {
                            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap, $livePrograms, $liveEmissionsPerDay, $liveSessionStarts);
                            $status = $cell['status'];
                            switch ($status) {
                                case 'played':
                                    $cls     = 'celda-emitida';
                                    $tooltip = 'Emitido a las ' . $cell['time'] . 'h (esperado ' . $cell['scheduledAt'] . 'h)';
                                    $icon    = '✓';
                                    break;
                                case 'missed':
                                    $cls     = 'celda-perdida';
                                    $tooltip = 'Sin emisión registrada (esperado ' . $cell['scheduledAt'] . 'h)';
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
                    <td class="col-dia <?php echo $day['isToday'] ? 'col-hoy' : ''; ?> <?php echo $cls; ?>"
                        <?php if ($tooltip): ?>data-tooltip="<?php echo htmlEsc($tooltip); ?>"<?php endif; ?>>
                        <?php echo $icon; ?>
                    </td>
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

    <!-- ── Tabla de resumen ──────────────────────────────────────────────────── -->
    <?php if ($hasSchedule): ?>
    <div style="padding:0 24px 24px;">
        <table class="resumen-table">
            <tbody>
                <tr class="resumen-emite-ok">
                    <td>Total SE EMITE OK</td>
                    <td><?php echo $totals['emite_ok']; ?></td>
                </tr>
                <tr class="resumen-faltan">
                    <td>Total FALTAN</td>
                    <td><?php echo $totals['faltan']; ?></td>
                </tr>
                <tr class="resumen-azura">
                    <td>Total EMITIDOS Azura</td>
                    <td><?php echo $totals['emitidos_azura']; ?></td>
                </tr>
                <tr class="resumen-directos">
                    <td>Total <span style="font-size:16px;">·</span> Directos emitidos</td>
                    <td><?php echo $totals['live_efectivos']; ?></td>
                </tr>
                <tr class="resumen-directos-esp">
                    <td>Directos esperados</td>
                    <td><?php echo $totals['live_esperados']; ?></td>
                </tr>
            </tbody>
        </table>
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

</div>

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
</style>
