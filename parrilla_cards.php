<?php
// parrilla_cards.php - Widget de parrilla estilo fichas por días (optimizado)

// Iniciar output buffering con compresión gzip
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

// Generar nonce para CSP
$cspNonce = base64_encode(random_bytes(16));

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
// X-Frame-Options removed to allow iframe embedding on external sites
// CSP frame-ancestors handles this more flexibly
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$cspNonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'self' *; base-uri 'self'; form-action 'self'");

// Headers de caché para navegadores (2 minutos)
header("Cache-Control: public, max-age=120");
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 120) . ' GMT');

// Temporalmente desactivado para permitir HTTP
// if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
//     header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
// }

// Configurar zona horaria a CET/CEST (Europe/Madrid)
date_default_timezone_set('Europe/Madrid');

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';
require_once INCLUDES_DIR . '/utils.php';

// SEGURIDAD: Rate limiting por IP para archivo público
session_start();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'parrilla_' . md5($ip);

if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'window_start' => time()];
}

$rateData = $_SESSION[$rateLimitKey];

// Resetear contador si pasó la ventana de tiempo (60 segundos)
if (time() - $rateData['window_start'] >= 60) {
    $_SESSION[$rateLimitKey] = ['count' => 1, 'window_start' => time()];
} else {
    // Incrementar contador
    $_SESSION[$rateLimitKey]['count']++;

    // Límite: 20 peticiones por minuto por IP
    if ($_SESSION[$rateLimitKey]['count'] > 20) {
        http_response_code(429);
        header('Content-Type: text/html; charset=UTF-8');
        die('<h1>429 Too Many Requests</h1><p>Límite de peticiones excedido. Espera 1 minuto.</p>');
    }
}

// Medición de rendimiento
$_START_TIME = microtime(true);

$station = $_GET['station'] ?? '';
if (empty($station) || !validateInput($station, 'username')) {
    die('Error: Estación inválida');
}

$user = findUserByUsername($station);
if (!$user) {
    die('Error: Estación no encontrada');
}

$azConfig = getAzuracastConfig($station);
$stationName = $user['station_name'] ?? $station;
$widgetColor = $azConfig['widget_color'] ?? '#10b981';
$widgetBackgroundColor = $azConfig['widget_background_color'] ?? '#ffffff';
$widgetStyle = $azConfig['widget_style'] ?? 'modern';
$widgetFontSize = $azConfig['widget_font_size'] ?? 'medium';
$streamUrl = $azConfig['stream_url'] ?? '';

// Función para calcular color de texto legible basado en el color del widget
function getReadableLinkColor($hexColor) {
    // Convertir hex a RGB
    $hex = ltrim($hexColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Calcular luminancia relativa (fórmula WCAG)
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    // Si el color es muy claro (luminancia > 0.6), oscurecerlo para que sea legible
    if ($luminance > 0.6) {
        // Oscurecer el color multiplicando por un factor
        $factor = 0.5;
        $r = max(0, floor($r * $factor));
        $g = max(0, floor($g * $factor));
        $b = max(0, floor($b * $factor));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    // El color original es suficientemente oscuro
    return $hexColor;
}

$linkHoverColor = getReadableLinkColor($widgetColor);

// SEGURIDAD: Limitar parámetro de refresh para prevenir abuso
$forceRefresh = false;
if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    // Verificar que no se abuse del refresh (máximo 1 cada 5 minutos por IP)
    $refreshKey = 'parrilla_refresh_' . md5($ip);
    if (!isset($_SESSION[$refreshKey]) || (time() - $_SESSION[$refreshKey]) > 300) {
        $forceRefresh = true;
        $_SESSION[$refreshKey] = time();
    } else {
        error_log("[SAPO-Security] Refresh abuse blocked from IP: $ip");
    }
}
$cacheTTL = $forceRefresh ? 0 : 600;

$schedule = getAzuracastSchedule($station, $cacheTTL);
if ($schedule === false) $schedule = [];

// SEGURIDAD: Debug solo en desarrollo, no en producción
$isDevelopment = (getenv('ENVIRONMENT') === 'development');

if (isset($_GET['debug_cache']) && $isDevelopment) {
    header('Content-Type: application/json');
    $cacheInfo = [
        'force_refresh' => $forceRefresh,
        'cache_ttl' => $cacheTTL,
        'total_events' => count($schedule),
        'sample_events' => array_slice($schedule, 0, 3)
    ];
    die(json_encode($cacheInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
} elseif (isset($_GET['debug_cache']) && !$isDevelopment) {
    http_response_code(403);
    die('Forbidden: Debug mode disabled in production');
}

// Debug: rastrear un programa específico (solo en desarrollo)
$traceProgram = '';
if ($isDevelopment && isset($_GET['trace'])) {
    $traceProgram = $_GET['trace'];
}
$traceLog = [];

$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];

// Organizar eventos por día de la semana
$eventsByDay = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 0 => []];
$musicBlocksByDay = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 0 => []];

// PRIMERO: Añadir programas en directo (live) manuales de SAPO
foreach ($programsData as $programKey => $programInfo) {
    if (($programInfo['playlist_type'] ?? '') === 'live') {
        $scheduleDays = $programInfo['schedule_days'] ?? [];
        $startTime = $programInfo['schedule_start_time'] ?? '';
        $duration = (int)($programInfo['schedule_duration'] ?? 60);

        // Obtener nombre original del programa (sin sufijo ::live)
        $programName = $programInfo['original_name'] ?? getProgramNameFromKey($programKey);

        // Solo añadir si tiene horario configurado
        if (!empty($scheduleDays) && !empty($startTime)) {
            foreach ($scheduleDays as $day) {
                // Convertir día a integer para evitar problemas con el valor '0' (domingo)
                $day = (int)$day;

                // Calcular hora de fin
                $startDateTime = DateTime::createFromFormat('H:i', $startTime);

                // Validar que el parsing fue exitoso
                if ($startDateTime === false) {
                    error_log("SAPO: Error parsing time '$startTime' for program '$programName'");
                    continue; // Saltar este día si la hora es inválida
                }

                $endDateTime = clone $startDateTime;
                $endDateTime->modify("+{$duration} minutes");

                $hour = (int)$startDateTime->format('H');
                $minute = (int)$startDateTime->format('i');
                $normalizedTimestamp = ($hour * 3600) + ($minute * 60);

                $eventsByDay[$day][] = [
                    'title' => $programInfo['display_title'] ?: $programName,
                    'original_title' => $programName,
                    'start_time' => $startDateTime->format('H:i'),
                    'end_time' => $endDateTime->format('H:i'),
                    'start_timestamp' => $normalizedTimestamp,
                    'description' => $programInfo['short_description'] ?? '',
                    'long_description' => $programInfo['long_description'] ?? '',
                    'image' => $programInfo['image'] ?? '',
                    'presenters' => $programInfo['presenters'] ?? '',
                    'url' => $programInfo['url'] ?? '',
                    'rss_feed' => $programInfo['rss_feed'] ?? '',
                    'social_twitter' => $programInfo['social_twitter'] ?? '',
                    'social_instagram' => $programInfo['social_instagram'] ?? '',
                    'social_mastodon' => $programInfo['social_mastodon'] ?? '',
                    'social_bluesky' => $programInfo['social_bluesky'] ?? '',
                    'social_facebook' => $programInfo['social_facebook'] ?? '',
                    'playlist_type' => 'live'
                ];
            }
        }
    }
}

// SEGUNDO: Añadir eventos de Radiobot
foreach ($schedule as $event) {
    $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
    $start = $event['start_timestamp'] ?? $event['start'] ?? null;
    if ($start === null) continue;

    $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);

    // Establecer zona horaria local (CET/CEST)
    $timezone = new DateTimeZone('Europe/Madrid');
    $startDateTime->setTimezone($timezone);

    $dayOfWeek = (int)$startDateTime->format('w');

    $programInfo = $programsData[$title] ?? null;
    $playlistType = $programInfo['playlist_type'] ?? 'program';

    // Trace: registrar evento si coincide con el programa buscado
    $isTracked = !empty($traceProgram) && stripos($title, $traceProgram) !== false;
    if ($isTracked) {
        $traceLog[] = [
            'stage' => '1_api_event',
            'title' => $title,
            'day' => $dayOfWeek,
            'time' => $startDateTime->format('H:i'),
            'date' => $startDateTime->format('Y-m-d'),
            'playlist_type' => $playlistType,
            'program_in_db' => $programInfo !== null
        ];
    }

    // Omitir jingles y programas ocultos
    if ($playlistType === 'jingles') {
        if ($isTracked) $traceLog[] = ['stage' => '2_filtered', 'reason' => 'jingles'];
        continue;
    }
    if (!empty($programInfo['hidden_from_schedule'])) {
        if ($isTracked) $traceLog[] = ['stage' => '2_filtered', 'reason' => 'hidden_from_schedule'];
        continue;
    }

    // Verificar si hay duración personalizada configurada
    $customDuration = isset($programInfo['schedule_duration']) ? (int)$programInfo['schedule_duration'] : 0;

    if ($customDuration > 0) {
        // Usar duración personalizada del gestor de programas
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$customDuration} minutes");
    } else {
        // Usar duración de Radiobot o defecto 1 hora
        $end = $event['end_timestamp'] ?? $event['end'] ?? null;
        $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

        if ($endDateTime) {
            $endDateTime->setTimezone($timezone);
        }

        if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+1 hour');
        }
    }

    $hour = (int)$startDateTime->format('H');
    $minute = (int)$startDateTime->format('i');
    $normalizedTimestamp = ($hour * 3600) + ($minute * 60);

    // Usar título personalizado si existe, sino el nombre de la playlist
    $displayTitle = !empty($programInfo['display_title']) ? $programInfo['display_title'] : $title;

    // Crear evento
    $eventData = [
        'title' => $displayTitle,
        'original_title' => $title,
        'start_time' => $startDateTime->format('H:i'),
        'end_time' => $endDateTime->format('H:i'),
        'start_timestamp' => $normalizedTimestamp,
        'description' => $programInfo['short_description'] ?? '',
        'long_description' => $programInfo['long_description'] ?? '',
        'image' => $programInfo['image'] ?? '',
        'presenters' => $programInfo['presenters'] ?? '',
        'url' => $programInfo['url'] ?? '',
        'rss_feed' => $programInfo['rss_feed'] ?? '',
        'social_twitter' => $programInfo['social_twitter'] ?? '',
        'social_instagram' => $programInfo['social_instagram'] ?? '',
        'social_mastodon' => $programInfo['social_mastodon'] ?? '',
        'social_bluesky' => $programInfo['social_bluesky'] ?? '',
        'social_facebook' => $programInfo['social_facebook'] ?? '',
        'playlist_type' => $playlistType
    ];

    // Separar bloques musicales de programas
    if ($playlistType === 'music_block') {
        $musicBlocksByDay[$dayOfWeek][] = $eventData;
        if ($isTracked) $traceLog[] = ['stage' => '3_added', 'array' => 'musicBlocksByDay', 'day' => $dayOfWeek];
    } else {
        $eventsByDay[$dayOfWeek][] = $eventData;
        if ($isTracked) $traceLog[] = ['stage' => '3_added', 'array' => 'eventsByDay', 'day' => $dayOfWeek, 'rss_feed' => $eventData['rss_feed']];
    }
}

// Ordenar bloques musicales por hora de inicio
foreach ($musicBlocksByDay as $day => &$dayBlocks) {
    usort($dayBlocks, function($a, $b) {
        return $a['start_timestamp'] - $b['start_timestamp'];
    });
}
unset($dayBlocks);

// Deduplicar bloques musicales
foreach ($musicBlocksByDay as $day => &$dayBlocks) {
    $uniqueBlocks = [];
    $seenKeys = [];

    foreach ($dayBlocks as $block) {
        $normalizedTitle = trim(mb_strtolower($block['title']));
        $uniqueKey = $normalizedTitle . '_' . $block['start_time'];

        if (!isset($seenKeys[$uniqueKey])) {
            $seenKeys[$uniqueKey] = true;
            $uniqueBlocks[] = $block;
        }
    }

    $dayBlocks = $uniqueBlocks;
}
unset($dayBlocks);

// Deduplicar eventos
foreach ($eventsByDay as $day => &$dayEvents) {
    $uniqueEvents = [];
    $seenKeys = [];

    // Trace: contar eventos antes de deduplicar
    $tracedBefore = 0;
    if (!empty($traceProgram)) {
        foreach ($dayEvents as $event) {
            if (stripos($event['title'], $traceProgram) !== false ||
                stripos($event['original_title'] ?? '', $traceProgram) !== false) {
                $tracedBefore++;
            }
        }
        if ($tracedBefore > 0) {
            $traceLog[] = ['stage' => '3b_dedup_before', 'day' => $day, 'count' => $tracedBefore, 'total_day_events' => count($dayEvents)];
        }
    }

    foreach ($dayEvents as $event) {
        $normalizedTitle = trim(mb_strtolower($event['title']));
        $uniqueKey = $normalizedTitle . '_' . $event['start_time'];

        if (!isset($seenKeys[$uniqueKey])) {
            $seenKeys[$uniqueKey] = true;
            $uniqueEvents[] = $event;
        }
    }

    // Ordenar por hora
    usort($uniqueEvents, function($a, $b) {
        return $a['start_timestamp'] - $b['start_timestamp'];
    });

    $dayEvents = $uniqueEvents;

    // Trace: contar eventos después de deduplicar
    if (!empty($traceProgram) && $tracedBefore > 0) {
        $tracedAfter = 0;
        foreach ($dayEvents as $event) {
            if (stripos($event['title'], $traceProgram) !== false ||
                stripos($event['original_title'] ?? '', $traceProgram) !== false) {
                $tracedAfter++;
            }
        }
        $traceLog[] = ['stage' => '3c_dedup_after', 'day' => $day, 'count' => $tracedAfter, 'total_day_events' => count($dayEvents)];
    }
}
unset($dayEvents); // IMPORTANTE: romper la referencia para evitar sobrescribir el array

// PRE-CARGAR todos los RSS ANTES de generar HTML (optimización de rendimiento)
$t1 = microtime(true);
$rssCache = [];
foreach ($eventsByDay as $dayEvents) {
    foreach ($dayEvents as $event) {
        $rssUrl = $event['rss_feed'] ?? '';
        if (!empty($rssUrl) && !isset($rssCache[$rssUrl])) {
            $rssCache[$rssUrl] = getLatestEpisodeFromRSS($rssUrl, 21600);
        }
    }
}
$t2 = microtime(true);
error_log(sprintf("PERFORMANCE: Pre-carga RSS: %.3fs (%d feeds únicos)", $t2 - $t1, count($rssCache)));

// Trace: mostrar log de rastreo si se solicitó
if (!empty($traceProgram)) {
    // Contar cuántos eventos quedaron para el programa rastreado
    $dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $finalCount = [];
    foreach ($eventsByDay as $day => $events) {
        foreach ($events as $event) {
            if (stripos($event['title'], $traceProgram) !== false ||
                stripos($event['original_title'] ?? '', $traceProgram) !== false) {
                $finalCount[] = [
                    'day' => $dayNames[$day],
                    'time' => $event['start_time'],
                    'title' => $event['title']
                ];
            }
        }
    }
    $traceLog[] = ['stage' => '4_final_events', 'count' => count($finalCount), 'events' => $finalCount];

    header('Content-Type: application/json');
    die(json_encode([
        'trace_program' => $traceProgram,
        'total_api_events' => count($schedule),
        'trace_log' => $traceLog
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Detectar día y hora actual
$now = new DateTime();
$currentDay = (int)$now->format('w');
$currentHour = (int)$now->format('H');
$currentMinute = (int)$now->format('i');
$currentSeconds = ($currentHour * 3600) + ($currentMinute * 60);

$daysOfWeek = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];

// Mapeo de tamaños de fuente
$fontSizes = ['small' => '14px', 'medium' => '16px', 'large' => '18px'];
$baseFontSize = $fontSizes[$widgetFontSize] ?? '16px';

$t3 = microtime(true);
error_log(sprintf("PERFORMANCE: Preparación datos completada en %.3fs (antes de HTML)", $t3 - $_START_TIME));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación - <?php echo htmlspecialchars($stationName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: <?php echo htmlspecialchars($widgetBackgroundColor); ?>;
            padding: 20px;
            font-size: <?php echo $baseFontSize; ?>;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
        }

        .header {
            background: <?php echo htmlspecialchars($widgetColor); ?>;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .tabs {
            display: flex;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }

        .tabs::-webkit-scrollbar {
            display: none; /* Chrome/Safari */
        }

        .tab-button {
            flex: 1;
            min-width: 120px;
            padding: 15px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
            position: relative;
        }

        .tab-button:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .tab-button.active {
            color: <?php echo htmlspecialchars($linkHoverColor); ?>;
            background: white;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: <?php echo htmlspecialchars($widgetColor); ?>;
        }

        .tab-content {
            display: none;
            padding: 30px;
            animation: fadeIn 0.3s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .program-card {
            background: white;
            <?php if ($widgetStyle === 'modern'): ?>
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                padding: 20px;
            <?php elseif ($widgetStyle === 'classic'): ?>
                border: 2px solid #d1d5db;
                border-radius: 4px;
                box-shadow: none;
                padding: 20px;
            <?php elseif ($widgetStyle === 'compact'): ?>
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                box-shadow: none;
                padding: 12px 16px;
            <?php elseif ($widgetStyle === 'minimal'): ?>
                border: none;
                border-radius: 0;
                box-shadow: none;
                border-bottom: 1px solid #f3f4f6;
                padding: 16px;
            <?php endif; ?>
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            transition: all 0.3s;
            position: relative;
        }

        .program-card:hover {
            <?php if ($widgetStyle === 'modern'): ?>
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            <?php elseif ($widgetStyle === 'classic'): ?>
                border-color: <?php echo htmlspecialchars($widgetColor); ?>;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            <?php elseif ($widgetStyle === 'compact'): ?>
                border-color: <?php echo htmlspecialchars($widgetColor); ?>;
                transform: translateX(4px);
            <?php elseif ($widgetStyle === 'minimal'): ?>
                background: #f9fafb;
                border-bottom-color: <?php echo htmlspecialchars($widgetColor); ?>;
            <?php endif; ?>
        }

        .program-card.live {
            background: #f5f5f5;
            border-left: 3px solid #ef4444;
        }

        /* Resaltar programas en directo (live) */
        .program-card.live-program {
            background: white;
        }

        .program-time {
            min-width: 60px;
            text-align: left;
            padding: 0;
            margin-right: 20px;
            font-size: 1.1em;
            font-weight: 600;
            color: #000000;
            align-self: flex-start;
            background: transparent;
        }

        .program-image {
            <?php if ($widgetStyle === 'compact'): ?>
                width: 80px;
                height: 80px;
            <?php else: ?>
                width: 120px;
                height: 120px;
            <?php endif; ?>
            object-fit: cover;
            <?php if ($widgetStyle === 'modern'): ?>
                border-radius: 8px;
            <?php elseif ($widgetStyle === 'classic'): ?>
                border-radius: 4px;
            <?php elseif ($widgetStyle === 'compact'): ?>
                border-radius: 6px;
            <?php elseif ($widgetStyle === 'minimal'): ?>
                border-radius: 0;
            <?php endif; ?>
            flex-shrink: 0;
        }

        .program-info {
            flex: 1;
        }

        /* Añadir margen cuando no hay imagen para alinear con las cards que sí tienen */
        .program-card.no-image .program-info {
            <?php if ($widgetStyle === 'compact'): ?>
                margin-left: 100px; /* 80px imagen + 20px gap */
            <?php else: ?>
                margin-left: 140px; /* 120px imagen + 20px gap */
            <?php endif; ?>
        }

        .program-category {
            font-size: 0.7em;
            font-weight: 700;
            color: #dc2626;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .program-title {
            <?php if ($widgetStyle === 'compact'): ?>
                font-size: 1.1em;
            <?php else: ?>
                font-size: 1.25em;
            <?php endif; ?>
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .program-schedule {
            font-size: 0.9em;
            color: #64748b;
            margin-bottom: 10px;
        }

        .live-badge-right {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #dc2626;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        a.live-badge-right:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        }

        .live-badge {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 700;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .program-description {
            color: #64748b;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .program-presenters {
            color: #94a3b8;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .program-presenters strong {
            color: #64748b;
        }

        .program-social {
            display: flex;
            gap: 12px;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            transition: all 0.3s;
            text-decoration: none;
        }

        .social-link.website {
            background: #6b7280;
            color: white;
        }

        .social-link.website:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(107, 114, 128, 0.3);
        }

        .social-link.twitter {
            background: #1DA1F2;
            color: white;
        }

        .social-link.twitter:hover {
            background: #1a8cd8;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(29, 161, 242, 0.3);
        }

        .social-link.instagram {
            background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%);
            color: white;
        }

        .social-link.instagram:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(188, 24, 136, 0.3);
        }

        .social-link.mastodon {
            background: #6364FF;
            color: white;
        }

        .social-link.mastodon:hover {
            background: #563acc;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(99, 100, 255, 0.3);
        }

        .social-link.bluesky {
            background: #1185fe;
            color: white;
        }

        .social-link.bluesky:hover {
            background: #0d6ecd;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(17, 133, 254, 0.3);
        }

        .social-link.facebook {
            background: #1877F2;
            color: white;
        }

        .social-link.facebook:hover {
            background: #145dbf;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(24, 119, 242, 0.3);
        }

        .rss-episode-link {
            text-decoration: none;
            display: block;
            margin-top: 12px;
            transition: transform 0.2s;
        }

        .rss-episode-link:hover {
            transform: translateX(4px);
        }

        .rss-episode {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid <?php echo htmlspecialchars($widgetColor); ?>;
            transition: all 0.2s;
        }

        .rss-episode-link:hover .rss-episode {
            background: #e2e8f0;
            border-left-width: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .rss-episode-title {
            font-weight: 600;
            color: #334155;
            margin-bottom: 5px;
            font-size: 0.95em;
        }

        .rss-episode-link:hover .rss-episode-title {
            color: <?php echo htmlspecialchars($linkHoverColor); ?>;
        }

        .rss-episode-date {
            color: #94a3b8;
            font-size: 0.85em;
        }

        .empty-day {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-day svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header { padding: 20px; }
            .header h1 { font-size: 1.5em; }
            .tab-button { min-width: 90px; padding: 12px 10px; font-size: 0.85em; }
            .tab-content { padding: 15px; }
            .program-card { flex-direction: column; }
            .program-image { width: 100%; height: 200px; }
            .program-time { width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; }
        }

        @media (max-width: 480px) {
            .header h1 { font-size: 1.3em; }
            .program-title { font-size: 1.1em; }
        }

        /* Cards compactas de bloques musicales */
        .blocks-container {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .blocks-title {
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
            margin-bottom: 6px;
        }

        .blocks-grid {
            display: flex;
            gap: 3px;
        }

        .block-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px 10px;
            border-left: 3px solid #8b5cf6;
            font-size: 10px;
            flex: 1;
            min-width: 0;
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
        }

        .block-card:hover {
            background: #ede9fe;
            flex: 3;
            border-left-color: #7c3aed;
        }

        .block-card-name {
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .block-card-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, margin-top 0.3s ease;
            margin-top: 0;
        }

        .block-card:hover .block-card-details {
            max-height: 40px;
            margin-top: 4px;
        }

        .block-card-time {
            font-weight: 500;
            color: #6b7280;
            font-size: 9px;
        }

        .block-card-duration {
            font-size: 9px;
            color: #8b5cf6;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .blocks-grid {
                flex-direction: column;
            }
            .block-card {
                padding: 5px 8px;
                font-size: 9px;
                width: 100%;
            }
            .block-card:hover {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($stationName); ?></h1>
            <p>Programación Semanal</p>
        </div>

        <div class="tabs">
            <?php foreach ([1,2,3,4,5,6,0] as $day): ?>
                <button class="tab-button<?php echo $day === $currentDay ? ' active' : ''; ?>"
                        data-day="<?php echo $day; ?>">
                    <?php echo $daysOfWeek[$day]; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ([1,2,3,4,5,6,0] as $day): ?>
            <div class="tab-content<?php echo $day === $currentDay ? ' active' : ''; ?>"
                 id="day-<?php echo $day; ?>">
                <?php if (empty($eventsByDay[$day])): ?>
                    <div class="empty-day">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p>No hay programación para este día</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Pre-calcular qué programa está en vivo (solo el más reciente si hay solapamiento)
                    $liveEventIndex = null;
                    $liveEventStartSec = -1;

                    foreach ($eventsByDay[$day] as $index => $event) {
                        if ($day === $currentDay) {
                            list($startH, $startM) = explode(':', $event['start_time']);
                            list($endH, $endM) = explode(':', $event['end_time']);
                            $startSec = ((int)$startH * 3600) + ((int)$startM * 60);
                            $endSec = ((int)$endH * 3600) + ((int)$endM * 60);

                            // Si está en vivo y empezó más recientemente que el anterior
                            if ($currentSeconds >= $startSec && $currentSeconds < $endSec) {
                                if ($startSec > $liveEventStartSec) {
                                    $liveEventIndex = $index;
                                    $liveEventStartSec = $startSec;
                                }
                            }
                        }
                    }
                    ?>
                    <?php foreach ($eventsByDay[$day] as $index => $event):
                        // Obtener último episodio desde la caché pre-cargada (optimización)
                        $latestEpisode = null;
                        if (!empty($event['rss_feed'])) {
                            $latestEpisode = $rssCache[$event['rss_feed']] ?? null;

                            // Nota: No ocultamos automáticamente programas con episodios antiguos
                            // El usuario puede usar "Ocultar en la parrilla" manualmente si lo desea
                            // Algunos programas se reponen manualmente o desde RANA sin publicar RSS
                        }

                        // Solo este programa está en vivo (el más reciente si hay solapamiento)
                        $isLive = ($index === $liveEventIndex);
                        $isLiveProgram = ($event['playlist_type'] === 'live');
                    ?>
                        <div class="program-card<?php echo $isLive ? ' live' : ''; ?><?php echo $isLiveProgram ? ' live-program' : ''; ?><?php echo empty($event['image']) ? ' no-image' : ''; ?>"
                             id="program-<?php echo $day; ?>-<?php echo str_replace(':', '', $event['start_time']); ?>">
                            <div class="program-time">
                                <?php echo htmlspecialchars(substr($event['start_time'], 0, 5)); ?>
                            </div>

                            <?php if (!empty($event['image'])): ?>
                                <img src="<?php echo htmlspecialchars($event['image']); ?>"
                                     alt="<?php echo htmlspecialchars($event['title']); ?>"
                                     class="program-image">
                            <?php endif; ?>

                            <div class="program-info">
                                <?php if ($isLiveProgram): ?>
                                    <div class="program-category">EN DIRECTO</div>
                                <?php elseif (!empty($event['long_description'])): ?>
                                    <div class="program-category">PODCAST</div>
                                <?php endif; ?>

                                <div class="program-title">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </div>

                                <div class="program-schedule">
                                    <?php echo htmlspecialchars(substr($event['start_time'], 0, 5)); ?> a <?php echo htmlspecialchars(substr($event['end_time'], 0, 5)); ?>
                                </div>

                                <?php if ($isLive): ?>
                                    <?php if (!empty($streamUrl)): ?>
                                        <a href="<?php echo htmlspecialchars($streamUrl); ?>"
                                           target="_blank"
                                           rel="noopener"
                                           class="live-badge-right"
                                           style="text-decoration: none; cursor: pointer;"
                                           title="Escuchar en directo">
                                            ▶️ AHORA EN DIRECTO
                                        </a>
                                    <?php else: ?>
                                        <div class="live-badge-right">▶️ AHORA EN DIRECTO</div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($event['description'])): ?>
                                    <div class="program-description">
                                        <?php echo htmlspecialchars($event['description']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($event['presenters'])): ?>
                                    <div class="program-presenters">
                                        <strong>Presenta:</strong> <?php echo htmlspecialchars($event['presenters']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($event['url']) || !empty($event['social_twitter']) || !empty($event['social_instagram']) || !empty($event['social_mastodon']) || !empty($event['social_bluesky']) || !empty($event['social_facebook'])): ?>
                                    <div class="program-social">
                                        <?php if (!empty($event['url'])): ?>
                                            <a href="<?php echo htmlspecialchars($event['url']); ?>"
                                               target="_blank" rel="noopener" class="social-link website"
                                               title="Sitio web del programa">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0ZM4.5 7.5a.5.5 0 0 1 0-1h5.793l-2.147-2.146a.5.5 0 0 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 7.5H4.5Z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['social_twitter'])):
                                            $twitter = $event['social_twitter'];
                                            if (!str_starts_with($twitter, 'http')) {
                                                $twitter = ltrim($twitter, '@');
                                                $twitter = 'https://x.com/' . $twitter;
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($twitter); ?>"
                                               target="_blank" rel="noopener" class="social-link twitter"
                                               title="Seguir en X">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.6.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['social_instagram'])):
                                            $instagram = $event['social_instagram'];
                                            if (!str_starts_with($instagram, 'http')) {
                                                $instagram = ltrim($instagram, '@');
                                                $instagram = 'https://instagram.com/' . $instagram;
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($instagram); ?>"
                                               target="_blank" rel="noopener" class="social-link instagram"
                                               title="Seguir en Instagram">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['social_mastodon'])):
                                            $mastodon = $event['social_mastodon'];
                                            // Si no es URL completa, intentar construirla desde el handle @usuario@servidor
                                            if (!str_starts_with($mastodon, 'http')) {
                                                // Si es @usuario@servidor.tld, convertir a URL
                                                if (preg_match('/@?([^@]+)@(.+)/', $mastodon, $matches)) {
                                                    $mastodon = 'https://' . $matches[2] . '/@' . $matches[1];
                                                }
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($mastodon); ?>"
                                               target="_blank" rel="noopener" class="social-link mastodon"
                                               title="Seguir en Mastodon">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['social_bluesky'])):
                                            $bluesky = $event['social_bluesky'];
                                            // Si no es URL completa y parece un handle (usuario.bsky.social), construir URL
                                            if (!str_starts_with($bluesky, 'http')) {
                                                $bluesky = 'https://bsky.app/profile/' . $bluesky;
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($bluesky); ?>"
                                               target="_blank" rel="noopener" class="social-link bluesky"
                                               title="Seguir en Bluesky">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M3.291 3.969c1.516 1.679 3.49 4.087 4.709 5.605 1.219-1.518 3.193-3.926 4.709-5.605C13.684 2.74 15.5 1.5 15.5 3.5c0 .677-.383 2.506-.572 3.213-.22.818-.804 1.596-1.745 1.931-1.214.433-3.065.353-4.183.119V11c0 2.5-1.5 5-3 5-1.5 0-3-2.5-3-5V8.763c-1.118.234-2.969.314-4.183-.119-.941-.335-1.525-1.113-1.745-1.931C2.883 6.006 2.5 4.177 2.5 3.5c0-2 1.816-.74 2.791.469z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['social_facebook'])):
                                            $facebook = $event['social_facebook'];
                                            // Si no es URL completa, construir desde nombre de página
                                            if (!str_starts_with($facebook, 'http')) {
                                                $facebook = 'https://facebook.com/' . $facebook;
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($facebook); ?>"
                                               target="_blank" rel="noopener" class="social-link facebook"
                                               title="Seguir en Facebook">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($latestEpisode): ?>
                                    <?php if (!empty($latestEpisode['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($latestEpisode['link']); ?>"
                                           target="_blank" rel="noopener" class="rss-episode-link">
                                            <div class="rss-episode">
                                                <div class="rss-episode-title">
                                                    <strong>Último episodio:</strong>
                                                    <?php echo htmlspecialchars($latestEpisode['title']); ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div class="rss-episode">
                                            <div class="rss-episode-title">
                                                <strong>Último episodio:</strong>
                                                <?php echo htmlspecialchars($latestEpisode['title']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php
                // Cards compactas de bloques musicales para este día
                $dayBlocks = $musicBlocksByDay[$day] ?? [];

                if (!empty($dayBlocks)):
                    // Ordenar por hora de inicio
                    usort($dayBlocks, function($a, $b) {
                        return $a['start_timestamp'] - $b['start_timestamp'];
                    });
                ?>
                <div class="blocks-container">
                    <div class="blocks-title">🎵 Bloques Musicales</div>
                    <div class="blocks-grid">
                        <?php foreach ($dayBlocks as $block):
                            // Calcular duración
                            $startParts = explode(':', $block['start_time']);
                            $endParts = explode(':', $block['end_time']);
                            $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
                            $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];

                            if ($endMinutes <= $startMinutes) {
                                $endMinutes += 24 * 60;
                            }

                            $durationMinutes = $endMinutes - $startMinutes;
                            $hours = floor($durationMinutes / 60);
                            $mins = $durationMinutes % 60;
                            $durationText = $hours > 0 ? $hours . 'h' : '';
                            if ($mins > 0) $durationText .= ' ' . $mins . 'm';
                        ?>
                            <div class="block-card">
                                <div class="block-card-name"><?php echo htmlspecialchars($block['title']); ?></div>
                                <div class="block-card-details">
                                    <div class="block-card-time"><?php echo substr($block['start_time'], 0, 5); ?> - <?php echo substr($block['end_time'], 0, 5); ?></div>
                                    <div class="block-card-duration"><?php echo trim($durationText); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script nonce="<?php echo $cspNonce; ?>">
        function switchTab(day) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Mostrar la pestaña seleccionada
            document.getElementById('day-' + day).classList.add('active');
            document.querySelector('[data-day="' + day + '"]').classList.add('active');
        }

        // Listeners para los botones de tabs
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(parseInt(this.dataset.day));
                });
            });
        });

        // Auto-scroll al programa en vivo cuando carga la página
        window.addEventListener('load', function() {
            const liveCard = document.querySelector('.program-card.live');
            if (liveCard) {
                setTimeout(function() {
                    liveCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 500);
            }
        });
    </script>
</body>
</html>
<?php
// Medición de rendimiento final
if (isset($_START_TIME)) {
    $duration = microtime(true) - $_START_TIME;
    error_log(sprintf("PERFORMANCE: parrilla_cards.php ejecutado en %.3fs", $duration));
}
?>
