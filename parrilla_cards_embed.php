<?php
// parrilla_cards_embed.php - Versión embebible sin header para incluir en otras webs

// Iniciar output buffering con compresión gzip
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

// Generar nonce para CSP
$cspNonce = base64_encode(random_bytes(16));

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: ALLOW-FROM *");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$cspNonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors *; base-uri 'self'; form-action 'self'");

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
$widgetStyle = $azConfig['widget_style'] ?? 'modern';
$widgetFontSize = $azConfig['widget_font_size'] ?? 'medium';

$schedule = getAzuracastSchedule($station);
if ($schedule === false) $schedule = [];

$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];

// Organizar eventos por día de la semana
$eventsByDay = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 0 => []];

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
                    'rss_feed' => $programInfo['rss_feed'] ?? '',
                    'social_twitter' => $programInfo['social_twitter'] ?? '',
                    'social_instagram' => $programInfo['social_instagram'] ?? '',
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

    if ($playlistType === 'jingles' || $playlistType === 'music_block') continue;

    $end = $event['end_timestamp'] ?? $event['end'] ?? null;
    $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

    if ($endDateTime) {
        $endDateTime->setTimezone($timezone);
    }

    if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+1 hour');
    }

    $hour = (int)$startDateTime->format('H');
    $minute = (int)$startDateTime->format('i');
    $normalizedTimestamp = ($hour * 3600) + ($minute * 60);

    // Usar título personalizado si existe, sino el nombre de la playlist
    $displayTitle = !empty($programInfo['display_title']) ? $programInfo['display_title'] : $title;

    $eventsByDay[$dayOfWeek][] = [
        'title' => $displayTitle,
        'original_title' => $title, // Guardar el título original para referencia
        'start_time' => $startDateTime->format('H:i'),
        'end_time' => $endDateTime->format('H:i'),
        'start_timestamp' => $normalizedTimestamp,
        'description' => $programInfo['short_description'] ?? '',
        'long_description' => $programInfo['long_description'] ?? '',
        'image' => $programInfo['image'] ?? '',
        'presenters' => $programInfo['presenters'] ?? '',
        'rss_feed' => $programInfo['rss_feed'] ?? '',
        'social_twitter' => $programInfo['social_twitter'] ?? '',
        'social_instagram' => $programInfo['social_instagram'] ?? '',
        'playlist_type' => $playlistType
    ];
}

// Deduplicar eventos
foreach ($eventsByDay as $day => &$dayEvents) {
    $uniqueEvents = [];
    $seenKeys = [];

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
            font-size: <?php echo $baseFontSize; ?>;
            background: transparent;
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
            color: <?php echo htmlspecialchars($widgetColor); ?>;
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
            background: white;
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

        .program-time .time-start {
            font-size: 1.4em;
            display: block;
        }

        .program-time .time-end {
            font-size: 0.85em;
            opacity: 0.9;
            display: block;
            margin-top: 5px;
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
            color: <?php echo htmlspecialchars($widgetColor); ?>;
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
            .tab-button { min-width: 90px; padding: 12px 10px; font-size: 0.85em; }
            .tab-content { padding: 15px; }
            .program-card { flex-direction: column; }
            .program-image { width: 100%; height: 200px; }
            .program-time { width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; }
        }

        @media (max-width: 480px) {
            .program-title { font-size: 1.1em; }
        }
    </style>
</head>
<body>
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
                    // Obtener último episodio RSS si existe (caché de 6 horas)
                    $latestEpisode = null;
                    if (!empty($event['rss_feed'])) {
                        $latestEpisode = getLatestEpisodeFromRSS($event['rss_feed'], 21600);

                        // Si tiene RSS configurado pero no hay episodios recientes:
                        // - Programas tipo 'program' (podcast): no mostrar
                        // - Programas tipo 'live' (en directo): sí mostrar (el RSS es opcional)
                        if ($latestEpisode === null && $event['playlist_type'] === 'program') {
                            continue;
                        }
                    }

                    // Solo este programa está en vivo (el más reciente si hay solapamiento)
                    $isLive = ($index === $liveEventIndex);
                    $isLiveProgram = ($event['playlist_type'] === 'live');
                ?>
                    <div class="program-card<?php echo $isLive ? ' live' : ''; ?><?php echo $isLiveProgram ? ' live-program' : ''; ?>"
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
                                <div class="live-badge-right">🔴 AHORA EN DIRECTO</div>
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

                            <?php if (!empty($event['social_twitter']) || !empty($event['social_instagram'])): ?>
                                <div class="program-social">
                                    <?php if (!empty($event['social_twitter'])):
                                        // Construir URL de Twitter/X a partir del handle
                                        $twitter = $event['social_twitter'];
                                        if (!str_starts_with($twitter, 'http')) {
                                            $twitter = ltrim($twitter, '@');
                                            $twitter = 'https://x.com/' . $twitter;
                                        }
                                    ?>
                                        <a href="<?php echo htmlspecialchars($twitter); ?>"
                                           target="_blank" rel="noopener" class="social-link twitter"
                                           title="Seguir en Twitter/X">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.6.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($event['social_instagram'])):
                                        // Construir URL de Instagram a partir del handle
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
        </div>
    <?php endforeach; ?>

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
