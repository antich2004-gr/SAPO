<?php
// views/seguimiento_emision.php - Seguimiento de emisiones del mes (solo admin)

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

$prevMonth  = date('Y-m', strtotime('-1 month', $monthStart));
$nextMonth  = date('Y-m', strtotime('+1 month', $monthStart));
$canGoNext  = ($nextMonth <= $currentMonth);

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$monthLabel = $monthNames[$month] . ' ' . $year;

$timezone   = new DateTimeZone('Europe/Madrid');
$today      = date('Y-m-d');

// ── 1. Schedule: qué días de semana y hora tiene cada programa ────────────────
$programSchedules = []; // [name => [[dayOfWeek, startTime, endTime], ...]]

$schedule = getAzuracastSchedule($_SESSION['username'], 600);

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
$programsDB = loadProgramsDB($_SESSION['username']);
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
    if (empty($programInfo['schedule_slots'])) continue;

    $azName       = $programInfo['original_name'] ?? getProgramNameFromKey($programKey);
    $displayTitle = $programInfo['display_title'] ?: $azName;

    $slots = [];
    foreach ($programInfo['schedule_slots'] as $slot) {
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

// ── 2. Historial de reproducción del mes ─────────────────────────────────────
// historyMap[azPlaylistName][Y-m-d] = primera hora de emisión detectada ('HH:MM')
$historyMap   = [];
$historyError = false;

if ($hasSchedule) {
    $history = getAzuracastHistory($_SESSION['username'], $monthStart, $monthEnd);

    if ($history === false) {
        $historyError = true;
    } elseif (is_array($history)) {
        foreach ($history as $entry) {
            $playlist = $entry['playlist'] ?? null;
            $playedAt = $entry['played_at'] ?? null;
            if (!$playlist || !$playedAt) continue;

            $playedDt = new DateTime('@' . $playedAt);
            $playedDt->setTimezone($timezone);
            $dayStr  = $playedDt->format('Y-m-d');
            $timeStr = $playedDt->format('H:i');

            if (!isset($historyMap[$playlist][$dayStr])) {
                $historyMap[$playlist][$dayStr] = $timeStr;
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

// ── Helper: estado de una celda ───────────────────────────────────────────────
// Para directos, busca en historyMap por el nombre real de AzuraCast.
function cellStatus($programKey, $day, $programSchedules, $historyMap, $today, $historyNameMap) {
    $slots = array_filter(
        $programSchedules[$programKey] ?? [],
        fn($s) => $s['dayOfWeek'] === $day['dow']
    );

    if (empty($slots)) {
        return ['status' => 'none'];
    }

    $slot        = array_values($slots)[0];
    $scheduledAt = $slot['startTime'];

    if ($day['date'] > $today) {
        return ['status' => 'expected', 'scheduledAt' => $scheduledAt];
    }

    // Para live, el historyMap usa el nombre original de AzuraCast
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
        $isLive = isset($livePrograms[$progKey]);
        foreach ($days as $day) {
            $cell   = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap);
            $status = $cell['status'];
            if ($isLive) {
                if ($status === 'played')       { $totals['live_esperados']++; $totals['live_efectivos']++; }
                elseif ($status === 'missed')   { $totals['live_esperados']++; }
            } else {
                if ($status === 'played')       $totals['emite_ok']++;
                elseif ($status === 'missed')   $totals['faltan']++;
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
                <span style="color:#718096; font-size:13px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></span>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a href="?" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">⚙️</span> Panel Admin</a>
                <form method="POST" style="display:inline; margin:0;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary" style="font-size:13px;"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Navegación de mes ────────────────────────────────────────────────── -->
    <div style="padding:14px 24px; background:#f7fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:16px;">
        <a href="?page=seguimiento_emision&month=<?php echo htmlEsc($prevMonth); ?>"
           class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">← Anterior</a>

        <span style="font-size:18px; font-weight:700; color:#2d3748; min-width:180px; text-align:center;">
            <?php echo htmlEsc($monthLabel); ?>
        </span>

        <?php if ($canGoNext): ?>
            <a href="?page=seguimiento_emision&month=<?php echo htmlEsc($nextMonth); ?>"
               class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Siguiente →</a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled style="font-size:13px; padding:6px 14px; opacity:.4; cursor:not-allowed;">Siguiente →</button>
        <?php endif; ?>

        <!-- Leyenda -->
        <div style="margin-left:auto; display:flex; gap:12px; align-items:center; font-size:12px; color:#4a5568; flex-wrap:wrap;">
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#e2e8f0;border-radius:3px;display:inline-block;"></span> Esperado</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#c6f6d5;border-radius:3px;display:inline-block;"></span> Emitido</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:14px;background:#fed7d7;border-radius:3px;display:inline-block;"></span> No emitido</span>
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
                    $progDisplay = $displayNameMap[$progKey] ?? $progKey;
                    $isLiveProg  = isset($livePrograms[$progKey]);
                ?>
                <tr>
                    <td class="col-programa">
                        <span title="<?php echo htmlEsc($progDisplay); ?>">
                            <?php if ($isLiveProg): ?><span style="color:#ef4444;font-size:10px;">🔴 </span><?php endif; ?>
                            <?php echo htmlEsc($progDisplay); ?>
                        </span>
                    </td>
                    <?php foreach ($days as $day):
                        $cell = cellStatus($progKey, $day, $programSchedules, $historyMap, $today, $historyNameMap);
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
                            default:
                                $cls     = '';
                                $tooltip = '';
                                $icon    = '';
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
.celda-esperada { background: #e2e8f0; color: #718096; }
.celda-emitida  { background: #c6f6d5; color: #276749; }
.celda-perdida  { background: #fed7d7; color: #9b2335; }
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
