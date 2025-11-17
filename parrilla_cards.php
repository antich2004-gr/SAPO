<?php
// parrilla_cards.php - Widget de parrilla estilo fichas por días (optimizado)

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'self' *; base-uri 'self'; form-action 'self'");

// Temporalmente desactivado para permitir HTTP
// if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
//     header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
// }

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

foreach ($schedule as $event) {
    $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
    $start = $event['start_timestamp'] ?? $event['start'] ?? null;
    if ($start === null) continue;

    $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
    $dayOfWeek = (int)$startDateTime->format('w');

    $programInfo = $programsData[$title] ?? null;
    $playlistType = $programInfo['playlist_type'] ?? 'program';

    if ($playlistType === 'jingles' || $playlistType === 'music_block') continue;

    $end = $event['end_timestamp'] ?? $event['end'] ?? null;
    $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

    if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+1 hour');
    }

    $hour = (int)$startDateTime->format('H');
    $minute = (int)$startDateTime->format('i');
    $normalizedTimestamp = ($hour * 3600) + ($minute * 60);

    $eventsByDay[$dayOfWeek][] = [
        'title' => $title,
        'start_time' => $startDateTime->format('H:i'),
        'end_time' => $endDateTime->format('H:i'),
        'start_timestamp' => $normalizedTimestamp,
        'description' => $programInfo['short_description'] ?? '',
        'long_description' => $programInfo['long_description'] ?? '',
        'image' => $programInfo['image'] ?? '',
        'presenters' => $programInfo['presenters'] ?? '',
        'rss_feed' => $programInfo['rss_feed'] ?? '',
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            font-size: <?php echo $baseFontSize; ?>;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
            border: 2px solid #ef4444;
            background: #fef2f2;
        }

        .program-time {
            min-width: 80px;
            text-align: center;
            padding: 10px;
            background: <?php echo htmlspecialchars($widgetColor); ?>;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            align-self: flex-start;
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

        .program-title {
            <?php if ($widgetStyle === 'compact'): ?>
                font-size: 1.1em;
            <?php else: ?>
                font-size: 1.3em;
            <?php endif; ?>
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
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
                        data-day="<?php echo $day; ?>"
                        onclick="switchTab(<?php echo $day; ?>)">
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
                    <?php foreach ($eventsByDay[$day] as $event):
                        // Detectar si está en vivo
                        $isLive = false;
                        if ($day === $currentDay) {
                            list($startH, $startM) = explode(':', $event['start_time']);
                            list($endH, $endM) = explode(':', $event['end_time']);
                            $startSec = ((int)$startH * 3600) + ((int)$startM * 60);
                            $endSec = ((int)$endH * 3600) + ((int)$endM * 60);
                            $isLive = ($currentSeconds >= $startSec && $currentSeconds < $endSec);
                        }

                        // Obtener último episodio RSS si existe
                        $latestEpisode = null;
                        if (!empty($event['rss_feed'])) {
                            $latestEpisode = getLatestEpisodeFromRSS($event['rss_feed']);
                        }
                    ?>
                        <div class="program-card<?php echo $isLive ? ' live' : ''; ?>"
                             id="program-<?php echo $day; ?>-<?php echo str_replace(':', '', $event['start_time']); ?>">
                            <div class="program-time">
                                <span class="time-start"><?php echo htmlspecialchars($event['start_time']); ?></span>
                                <span class="time-end"><?php echo htmlspecialchars($event['end_time']); ?></span>
                            </div>

                            <?php if (!empty($event['image'])): ?>
                                <img src="<?php echo htmlspecialchars($event['image']); ?>"
                                     alt="<?php echo htmlspecialchars($event['title']); ?>"
                                     class="program-image">
                            <?php endif; ?>

                            <div class="program-info">
                                <div class="program-title">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                    <?php if ($isLive): ?>
                                        <span class="live-badge">🔴 EN EMISIÓN</span>
                                    <?php endif; ?>
                                </div>

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

                                <?php if ($latestEpisode): ?>
                                    <?php if (!empty($latestEpisode['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($latestEpisode['link']); ?>"
                                           target="_blank" rel="noopener" class="rss-episode-link">
                                            <div class="rss-episode">
                                                <div class="rss-episode-title">
                                                    <strong>Último episodio:</strong>
                                                    <?php echo htmlspecialchars($latestEpisode['title']); ?>
                                                </div>
                                                <?php if (!empty($latestEpisode['formatted_date'])): ?>
                                                    <div class="rss-episode-date">
                                                        <?php echo htmlspecialchars($latestEpisode['formatted_date']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div class="rss-episode">
                                            <div class="rss-episode-title">
                                                <strong>Último episodio:</strong>
                                                <?php echo htmlspecialchars($latestEpisode['title']); ?>
                                            </div>
                                            <?php if (!empty($latestEpisode['formatted_date'])): ?>
                                                <div class="rss-episode-date">
                                                    <?php echo htmlspecialchars($latestEpisode['formatted_date']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
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
