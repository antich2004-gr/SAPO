<?php
/**
 * emission_recorder.php — Registro en tiempo real de emisiones
 *
 * En lugar de diagnosticar retroactivamente (cuando la carpeta ya tiene
 * contenido, los logs han rotado, etc.), este módulo guarda el diagnóstico
 * en el momento del fallo — a los pocos minutos del slot programado.
 *
 * Uso principal:
 *   - Cron cada 10 min: erRecordUser($username)
 *   - Fallback en page load (con cooldown de 5 min)
 *   - Consulta desde seguimiento_emision.php: erGetRecord(...)
 */

// ─────────────────────────────────────────────────────────────────────────────
// Constantes
// ─────────────────────────────────────────────────────────────────────────────

define('ER_GRACE_MINUTES',  20);  // Minutos tras el slot antes de declarar fallo
define('ER_LOOKBACK_DAYS',   7);  // Días hacia atrás que revisa el recorder
define('ER_RETENTION_DAYS', 90);  // Días que se conservan las entradas
define('ER_TIMEZONE',      'Europe/Madrid');

// ─────────────────────────────────────────────────────────────────────────────
// Almacenamiento
// ─────────────────────────────────────────────────────────────────────────────

function erLogPath(string $username): string {
    return DATA_DIR . '/emission_logs/' . $username . '.json';
}

function erLoadLog(string $username): array {
    $path = erLogPath($username);
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function erSaveLog(string $username, array $log): bool {
    $path = erLogPath($username);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return @file_put_contents(
        $path,
        json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

/** Clave única para un slot: "Y-m-d|programa|HH:MM" */
function erKey(string $date, string $program, string $scheduledAt): string {
    return "{$date}|{$program}|{$scheduledAt}";
}

/**
 * Obtener el registro guardado para un slot (null = aún no registrado).
 * Usa caché estática para evitar lecturas repetidas en la misma request.
 */
function erGetRecord(string $username, string $date, string $program, string $scheduledAt): ?array {
    static $cache = [];
    if (!isset($cache[$username])) {
        $cache[$username] = erLoadLog($username);
    }
    return $cache[$username][erKey($date, $program, $scheduledAt)] ?? null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers internos
// ─────────────────────────────────────────────────────────────────────────────

/** Normaliza un nombre de programa para comparación (igual que en seguimiento) */
function _erNorm(string $s): string {
    $s = preg_replace('/\s*[-–]\s*\(di?recto\)\s*$/ui', '', $s);
    $s = preg_replace('/\s*\(di?recto\)\s*$/ui', '', $s);
    $s = preg_replace('/\s*\(\d+h\d*\)\s*$/u', '', $s);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtolower(trim($s)));
    return preg_replace('/\s+/', ' ', trim($ascii !== false ? $ascii : strtolower(trim($s))));
}

/**
 * Diagnostica el motivo de una emisión perdida con los datos disponibles
 * en el momento del registro.
 */
function _erDiagnose(
    string  $scheduledAt,
    string  $date,
    int     $schedTs,
    string  $programKey,
    bool    $isLive,
    ?array  $dailyReport,
    ?array  $liqLog,
    string  $lsSrcId,
    array   $histByDay,    // [Y-m-d => [{ts, duration, playlist}]]
    ?array  $plInfo        // de getPlaylistContentInfo (puede ser null)
): string {

    // ── P0: Fallo técnico en log Liquidsoap ───────────────────────────────────
    if ($liqLog !== null) {
        $fromLog = diagnoseMissedFromLog($liqLog, $date, $scheduledAt, $lsSrcId);
        if ($fromLog !== null) return $fromLog;
    }

    // ── P1a: Carpeta vacía (informe diario) ───────────────────────────────────
    if ($dailyReport && !empty($dailyReport['carpetas_vacias'])) {
        $normKey = _erNorm($programKey);
        foreach ($dailyReport['carpetas_vacias'] as $folder) {
            if (_erNorm($folder['nombre']) === $normKey) {
                $days = (int)($folder['dias'] ?? 0);
                return $days > 0
                    ? "Sin episodio (carpeta vacía desde hace {$days} días)"
                    : 'Sin episodio disponible';
            }
        }
    }

    // ── P1b: Error de descarga (informe diario) ───────────────────────────────
    if ($dailyReport && !empty($dailyReport['errores_podget'])) {
        $normKey = _erNorm($programKey);
        foreach ($dailyReport['errores_podget'] as $err) {
            if (stripos(_erNorm($err), $normKey) !== false) {
                return 'Sin episodio disponible (error de descarga)';
            }
        }
    }

    // ── P1c: Playlist vacía en el momento del fallo ───────────────────────────
    if (!$isLive && $plInfo !== null) {
        $numSongs = (int)($plInfo['num_songs'] ?? -1);
        if ($numSongs === 0) {
            return 'La playlist estaba vacía en el momento del fallo';
        }
    }

    // ── P2: Sin actividad en AzuraCast ese día ────────────────────────────────
    if (empty($histByDay[$date] ?? [])) {
        return 'Sin actividad en AzuraCast ese día (posible corte de señal)';
    }

    // ── P3: Contenido anterior agotó el slot ──────────────────────────────────
    foreach ($histByDay[$date] as $he) {
        if ($he['ts'] <= $schedTs && ($he['ts'] + $he['duration']) > $schedTs) {
            $overrunEndTs = $he['ts'] + $he['duration'];
            $overrunMin   = (int)ceil(($overrunEndTs - $schedTs) / 60);
            if ($overrunMin > 15) {
                return "«{$he['playlist']}» se alargó {$overrunMin} min sobre su horario";
            }
            break;
        }
    }

    // ── P4: Tiene episodios pero no se activó ────────────────────────────────
    if (!$isLive && $plInfo !== null) {
        $numSongs = (int)($plInfo['num_songs'] ?? 0);
        if ($numSongs > 0) {
            return "Tiene {$numSongs} episodio(s) en playlist — no se activó (causa desconocida)";
        }
    }

    return 'Sin episodio disponible';
}

// ─────────────────────────────────────────────────────────────────────────────
// Función principal de grabación
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Registrar en tiempo real el estado de las emisiones de una estación.
 *
 * Recorre los slots de los últimos ER_LOOKBACK_DAYS días que:
 *   - Han superado el periodo de gracia (ER_GRACE_MINUTES tras el inicio)
 *   - No están aún registrados en el log
 *
 * Captura el estado de la playlist, el log de Liquidsoap y el informe
 * diario EN ESE MOMENTO, antes de que cambien.
 *
 * @param  string $username
 * @return int    Número de nuevas entradas registradas
 */
function erRecordUser(string $username): int {
    $tz  = new DateTimeZone(ER_TIMEZONE);
    $now = time();

    // ── Schedule de AzuraCast (programas automáticos) ─────────────────────────
    $schedule = getAzuracastSchedule($username, 600);
    if (!$schedule) return 0;

    // Construir mapa de slots: [name => [[dow, startTime, endTime]]]
    $progSlots = [];
    foreach ($schedule as $event) {
        $name = $event['name'] ?? $event['playlist'] ?? null;
        if (!$name) continue;
        $start = $event['start_timestamp'] ?? $event['start'] ?? null;
        if ($start === null) continue;

        $dt = new DateTime('@' . (is_numeric($start) ? (int)$start : strtotime($start)));
        $dt->setTimezone($tz);
        $dow     = (int)$dt->format('w');
        $timeStr = $dt->format('H:i');

        $endStr = null;
        $end = $event['end_timestamp'] ?? $event['end'] ?? null;
        if ($end !== null) {
            $eDt = new DateTime('@' . (is_numeric($end) ? (int)$end : strtotime($end)));
            $eDt->setTimezone($tz);
            $es = $eDt->format('H:i');
            if ($es !== $timeStr) $endStr = $es; // ignorar endTime = startTime (bug AzuraCast)
        }

        if (!isset($progSlots[$name])) $progSlots[$name] = [];
        foreach ($progSlots[$name] as $s) {
            if ($s['dow'] === $dow && $s['startTime'] === $timeStr) continue 2;
        }
        $progSlots[$name][] = ['dow' => $dow, 'startTime' => $timeStr, 'endTime' => $endStr];
    }

    // ── Programas en directo (desde BD SAPO) ──────────────────────────────────
    $dbProgs  = (loadProgramsDB($username))['programs'] ?? [];
    $liveSlots = []; // [name => [[dow, startTime]]]
    foreach ($dbProgs as $progName => $pInfo) {
        if (($pInfo['playlist_type'] ?? '') !== 'live') continue;
        if (!empty($pInfo['orphaned']) || !empty($pInfo['hidden_from_schedule'])) continue;

        $rawSlots = $pInfo['schedule_slots'] ?? [];
        if (empty($rawSlots) && !empty($pInfo['schedule_days'])) {
            $rawSlots = [[
                'days'       => (array)$pInfo['schedule_days'],
                'start_time' => $pInfo['schedule_start_time'] ?? '',
            ]];
        }
        foreach ($rawSlots as $ss) {
            $st = $ss['start_time'] ?? '';
            if ($st === '') continue;
            foreach (array_map('intval', $ss['days'] ?? []) as $d) {
                $liveSlots[$progName][] = ['dow' => $d, 'startTime' => $st];
            }
        }
    }

    // ── Liquidsoap log ────────────────────────────────────────────────────────
    $liqLog = getLiquidsoapLogIndex($username);

    // ── Ruta de informes diarios ──────────────────────────────────────────────
    $reportsPath = getReportsPath($username);

    // ── Cargar log existente ──────────────────────────────────────────────────
    $log      = erLoadLog($username);
    $newCount = 0;

    // Cachés para no repetir llamadas API por mes
    $historyCache     = []; // [monthKey => history array]
    $histMapCache     = []; // [monthKey => [playlist][Y-m-d] = HH:MM]
    $histByDayCache   = []; // [monthKey => [Y-m-d => [{ts, dur, playlist}]]]
    $dailyReportCache = []; // [Y-m-d => report|null]
    $broadcastCache   = []; // [Y-m-d => broadcasts array]

    // ── Iterar los últimos N días ─────────────────────────────────────────────
    for ($daysBack = 0; $daysBack <= ER_LOOKBACK_DAYS; $daysBack++) {
        $date  = date('Y-m-d', strtotime("-{$daysBack} days"));
        $dow   = (int)date('w', strtotime($date));
        [$yr, $mo, $dy] = explode('-', $date);

        // Helpers para cargar historial del mes (con caché)
        $monthKey = "{$yr}-{$mo}";
        $ensureHistory = function() use (
            $username, $monthKey, $yr, $mo, $tz,
            &$historyCache, &$histMapCache, &$histByDayCache
        ) {
            if (isset($historyCache[$monthKey])) return;
            $mStart = mktime(0, 0, 0, (int)$mo, 1, (int)$yr);
            $mEnd   = mktime(23, 59, 59, (int)$mo + 1, 0, (int)$yr);
            $h = getAzuracastHistory($username, $mStart, $mEnd);
            $history = is_array($h) ? $h : [];
            $historyCache[$monthKey] = $history;

            $hMap   = [];
            $hByDay = [];
            foreach ($history as $entry) {
                $pl = $entry['playlist'] ?? null;
                if (!$pl) continue;
                $ts = $entry['played_at'] ?? $entry['timestamp'] ?? null;
                if (!$ts) continue;
                $eDt = new DateTime('@' . (int)$ts);
                $eDt->setTimezone($tz);
                $eDayStr  = $eDt->format('Y-m-d');
                $eTimeStr = $eDt->format('H:i');
                if (!isset($hMap[$pl][$eDayStr])) {
                    $hMap[$pl][$eDayStr] = $eTimeStr;
                }
                $hByDay[$eDayStr][] = [
                    'ts'       => (int)$ts,
                    'duration' => (int)($entry['duration'] ?? 0),
                    'playlist' => $pl,
                ];
            }
            $histMapCache[$monthKey]   = $hMap;
            $histByDayCache[$monthKey] = $hByDay;
        };

        // ── Programas automáticos ─────────────────────────────────────────────
        foreach ($progSlots as $name => $slots) {
            // Filtrar música/jingles/huérfanos
            $pInfo = $dbProgs[$name] ?? null;
            if ($pInfo !== null) {
                $type = $pInfo['playlist_type'] ?? 'program';
                if ($type !== 'program') continue;
                if (!empty($pInfo['orphaned']) || !empty($pInfo['hidden_from_schedule'])) continue;
            }

            foreach ($slots as $slot) {
                if ($slot['dow'] !== $dow) continue;
                $scheduledAt = $slot['startTime'];
                $key = erKey($date, $name, $scheduledAt);

                $schedTs = mktime(
                    (int)substr($scheduledAt, 0, 2),
                    (int)substr($scheduledAt, 3, 2),
                    0, (int)$mo, (int)$dy, (int)$yr
                );
                $elapsed = ($now - $schedTs) / 60;

                // Saltar entradas ya confirmadas como emitidas.
                // Las entradas 'missed' recientes (<2 h) se re-evalúan por
                // si la caché de historial estaba desactualizada al registrarlas.
                if (isset($log[$key])) {
                    if ($log[$key]['status'] === 'played') continue;
                    if ($elapsed > 120) continue;
                }

                if ($elapsed < ER_GRACE_MINUTES) continue;
                if ($elapsed > 60 * 48)          continue;

                $ensureHistory();
                $histMap   = $histMapCache[$monthKey]   ?? [];
                $histByDay = $histByDayCache[$monthKey] ?? [];

                // ¿Se emitió?
                $played   = false;
                $realTime = null;
                $playedTs = $histMap[$name][$date] ?? null;
                if ($playedTs !== null) {
                    $schedMins = (int)substr($scheduledAt, 0, 2) * 60 + (int)substr($scheduledAt, 3, 2);
                    $playMins  = (int)substr($playedTs,    0, 2) * 60 + (int)substr($playedTs,    3, 2);
                    $diff = $playMins - $schedMins;
                    if ($diff < 0) $diff += 1440;
                    if ($diff <= 30) { $played = true; $realTime = $playedTs; }
                }

                if ($played) {
                    $log[$key] = [
                        'date'         => $date,
                        'program'      => $name,
                        'scheduled_at' => $scheduledAt,
                        'status'       => 'played',
                        'is_live'      => false,
                        'real_time'    => $realTime,
                        'recorded_at'  => date('Y-m-d H:i:s'),
                    ];
                } else {
                    // Informe diario
                    if (!array_key_exists($date, $dailyReportCache) && $reportsPath) {
                        $fn = $reportsPath . '/Informe_diario_' . $dy . '_' . $mo . '_' . $yr . '.log';
                        $dailyReportCache[$date] = file_exists($fn) ? parseReportFile($fn) : null;
                    }
                    // Playlist (capturar AHORA con TTL=0 para forzar lectura fresca)
                    $plInfo  = getPlaylistContentInfo($username, $name, 0);
                    $lsSrcId = computeLiquidsoapSourceId($name);

                    $reason = _erDiagnose(
                        $scheduledAt, $date, $schedTs, $name, false,
                        $dailyReportCache[$date] ?? null,
                        $liqLog, $lsSrcId, $histByDay, $plInfo
                    );
                    $log[$key] = [
                        'date'           => $date,
                        'program'        => $name,
                        'scheduled_at'   => $scheduledAt,
                        'status'         => 'missed',
                        'is_live'        => false,
                        'reason'         => $reason,
                        'playlist_songs' => $plInfo !== null ? (int)($plInfo['num_songs'] ?? -1) : -1,
                        'recorded_at'    => date('Y-m-d H:i:s'),
                    ];
                }
                $newCount++;
            }
        }

        // ── Programas en directo ──────────────────────────────────────────────
        foreach ($liveSlots as $liveName => $slots) {
            foreach ($slots as $slot) {
                if ($slot['dow'] !== $dow) continue;
                $scheduledAt = $slot['startTime'];
                $key = erKey($date, $liveName, $scheduledAt);

                $schedTs = mktime(
                    (int)substr($scheduledAt, 0, 2),
                    (int)substr($scheduledAt, 3, 2),
                    0, (int)$mo, (int)$dy, (int)$yr
                );
                $elapsed = ($now - $schedTs) / 60;

                // Igual que para automáticos: re-evaluar 'missed' recientes (<2 h)
                if (isset($log[$key])) {
                    if ($log[$key]['status'] === 'played') continue;
                    if ($elapsed > 120) continue;
                }

                if ($elapsed < ER_GRACE_MINUTES) continue;
                if ($elapsed > 60 * 48)          continue;

                // Broadcasts del día (con caché)
                if (!isset($broadcastCache[$date])) {
                    $dayStart = mktime(0,  0,  0, (int)$mo, (int)$dy, (int)$yr);
                    $dayEnd   = mktime(23, 59, 59, (int)$mo, (int)$dy, (int)$yr);
                    $bc = getAzuracastStreamerBroadcasts($username, $dayStart, $dayEnd);
                    $broadcastCache[$date] = is_array($bc) ? $bc : [];
                }

                $played   = false;
                $realTime = null;
                $schedMins = (int)substr($scheduledAt, 0, 2) * 60 + (int)substr($scheduledAt, 3, 2);
                foreach ($broadcastCache[$date] as $bc) {
                    $bcDt = new DateTime('@' . (int)$bc['start']);
                    $bcDt->setTimezone($tz);
                    if ($bcDt->format('Y-m-d') !== $date) continue;
                    $bcStr  = $bcDt->format('H:i');
                    $bcMins = (int)substr($bcStr, 0, 2) * 60 + (int)substr($bcStr, 3, 2);
                    $diff   = $bcMins - $schedMins;
                    if ($diff < 0) $diff += 1440;
                    if ($diff <= 30) { $played = true; $realTime = $bcStr; break; }
                }

                // Fallback: también buscar en daily report (emisiones_directo)
                if (!$played) {
                    if (!array_key_exists($date, $dailyReportCache) && $reportsPath) {
                        $fn = $reportsPath . '/Informe_diario_' . $dy . '_' . $mo . '_' . $yr . '.log';
                        $dailyReportCache[$date] = file_exists($fn) ? parseReportFile($fn) : null;
                    }
                    $rep = $dailyReportCache[$date] ?? null;
                    if ($rep && !empty($rep['emisiones_directo'])) {
                        foreach ($rep['emisiones_directo'] as $entry) {
                            if (!preg_match('/^-\s+[\d-]+\s+(\d{2}:\d{2})/', $entry, $m)) continue;
                            $eMins = (int)substr($m[1], 0, 2) * 60 + (int)substr($m[1], 3, 2);
                            $diff  = $eMins - $schedMins;
                            if ($diff < 0) $diff += 1440;
                            if ($diff <= 30) { $played = true; $realTime = $m[1]; break; }
                        }
                    }
                }

                if ($played) {
                    $log[$key] = [
                        'date'         => $date,
                        'program'      => $liveName,
                        'scheduled_at' => $scheduledAt,
                        'status'       => 'played',
                        'is_live'      => true,
                        'real_time'    => $realTime,
                        'recorded_at'  => date('Y-m-d H:i:s'),
                    ];
                } else {
                    $ensureHistory();
                    $histByDay = $histByDayCache[$monthKey] ?? [];
                    $lsSrcId   = computeLiquidsoapSourceId($liveName);
                    if (!array_key_exists($date, $dailyReportCache) && $reportsPath) {
                        $fn = $reportsPath . '/Informe_diario_' . $dy . '_' . $mo . '_' . $yr . '.log';
                        $dailyReportCache[$date] = file_exists($fn) ? parseReportFile($fn) : null;
                    }
                    $reason = _erDiagnose(
                        $scheduledAt, $date, $schedTs, $liveName, true,
                        $dailyReportCache[$date] ?? null,
                        $liqLog, $lsSrcId, $histByDay, null
                    );
                    $log[$key] = [
                        'date'         => $date,
                        'program'      => $liveName,
                        'scheduled_at' => $scheduledAt,
                        'status'       => 'missed',
                        'is_live'      => true,
                        'reason'       => $reason,
                        'recorded_at'  => date('Y-m-d H:i:s'),
                    ];
                }
                $newCount++;
            }
        }
    }

    if ($newCount > 0) {
        // Purgar entradas antiguas
        $cutoff = date('Y-m-d', strtotime('-' . ER_RETENTION_DAYS . ' days'));
        foreach (array_keys($log) as $k) {
            if (substr($k, 0, 10) < $cutoff) unset($log[$k]);
        }
        erSaveLog($username, $log);
    }

    return $newCount;
}
