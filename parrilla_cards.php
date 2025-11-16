<?php
// parrilla_cards.php - Widget de parrilla estilo fichas por días

// Headers de seguridad para permitir embebido
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';

// Obtener parámetro de estación
$station = $_GET['station'] ?? '';

if (empty($station)) {
    die('Error: Debe especificar una estación (?station=nombre)');
}

// Validar que la estación existe
$user = findUserByUsername($station);
if (!$user) {
    die('Error: Estación no encontrada');
}

// Obtener configuración de AzuraCast
$azConfig = getAzuracastConfig($station);
$stationName = $user['station_name'] ?? $station;
$widgetColor = $azConfig['widget_color'] ?? '#10b981';
$widgetStyle = $azConfig['widget_style'] ?? 'modern';
$widgetFontSize = $azConfig['widget_font_size'] ?? 'medium';
$stationId = $azConfig['station_id'] ?? null;

if (!$stationId) {
    die('Error: Esta estación no tiene configurado el Station ID de AzuraCast');
}

// Obtener programación
$schedule = getAzuracastSchedule($station);
if ($schedule === false) {
    $schedule = [];
    $error = 'No se pudo obtener la programación';
}

// Cargar información adicional de programas desde SAPO
$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];

// Organizar eventos por día de la semana
$eventsByDay = [
    1 => [], // Lunes
    2 => [], // Martes
    3 => [], // Miércoles
    4 => [], // Jueves
    5 => [], // Viernes
    6 => [], // Sábado
    0 => []  // Domingo
];

$daysOfWeek = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    0 => 'Domingo'
];

// Procesar eventos de AzuraCast y organizarlos por día
foreach ($schedule as $event) {
    $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
    $start = $event['start_timestamp'] ?? $event['start'] ?? null;

    if ($start === null) {
        continue;
    }

    $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
    $dayOfWeek = (int)$startDateTime->format('w');

    // Obtener información adicional del programa
    $programInfo = $programsData[$title] ?? null;
    $playlistType = $programInfo['playlist_type'] ?? 'program';

    // Filtrar jingles y bloques musicales
    if ($playlistType === 'jingles' || $playlistType === 'music_block') {
        continue;
    }

    // Calcular duración
    $end = $event['end_timestamp'] ?? $event['end'] ?? null;
    $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

    if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
        if (preg_match('/-\s*(\d+)\s*$/', $title, $matches)) {
            $durationMinutes = (int)$matches[1];
            $endDateTime = clone $startDateTime;
            $endDateTime->modify("+{$durationMinutes} minutes");
        } else {
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+1 hour');
        }
    }

    $startTime = $startDateTime->format('H:i');
    $endTime = $endDateTime->format('H:i');

    // Crear timestamp normalizado (segundos desde medianoche para ordenamiento)
    $hour = (int)$startDateTime->format('H');
    $minute = (int)$startDateTime->format('i');
    $normalizedTimestamp = ($hour * 3600) + ($minute * 60);

    // Crear objeto de evento
    $eventData = [
        'title' => $title,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'start_timestamp' => $normalizedTimestamp,
        'playlist_type' => $playlistType,
        'description' => $programInfo['short_description'] ?? '',
        'long_description' => $programInfo['long_description'] ?? '',
        'type' => $programInfo['type'] ?? '',
        'url' => $programInfo['url'] ?? '',
        'image' => $programInfo['image'] ?? '',
        'presenters' => $programInfo['presenters'] ?? '',
        'twitter' => $programInfo['social_twitter'] ?? '',
        'instagram' => $programInfo['social_instagram'] ?? '',
        'rss_feed' => $programInfo['rss_feed'] ?? ''
    ];

    // Agregar solo programas (jingles y bloques musicales ya filtrados arriba)
    $eventsByDay[$dayOfWeek][] = $eventData;
}

// Añadir programas creados manualmente con horario definido
// Solo si NO están ya en el schedule de AzuraCast (evitar duplicados)
foreach ($programsData as $programName => $programInfo) {
    // Solo procesar programas con horario definido
    if (empty($programInfo['schedule_days']) || empty($programInfo['schedule_start_time'])) {
        continue;
    }

    $playlistType = $programInfo['playlist_type'] ?? 'program';

    // Filtrar jingles y bloques musicales
    if ($playlistType === 'jingles' || $playlistType === 'music_block') {
        continue;
    }

    // Verificar si este programa ya existe en el schedule de AzuraCast
    // Normalizar nombre para comparación más robusta (eliminar espacios extra, normalizar mayúsculas)
    $normalizedProgramName = trim(mb_strtolower($programName));
    $existsInSchedule = false;

    foreach ($schedule as $azEvent) {
        $azTitle = $azEvent['name'] ?? $azEvent['playlist'] ?? '';
        $normalizedAzTitle = trim(mb_strtolower($azTitle));

        if ($normalizedAzTitle === $normalizedProgramName) {
            $existsInSchedule = true;
            break;
        }
    }

    // Si ya está en AzuraCast, no lo añadimos manualmente
    if ($existsInSchedule) {
        continue;
    }

    $scheduleDays = $programInfo['schedule_days'];
    $startTime = $programInfo['schedule_start_time'];
    $durationMinutes = $programInfo['schedule_duration'] ?? 60;

    // Crear evento para cada día programado
    foreach ($scheduleDays as $dayOfWeek) {
        // Calcular hora de fin
        $startDateTime = DateTime::createFromFormat('H:i', $startTime);
        if (!$startDateTime) {
            continue;
        }
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$durationMinutes} minutes");

        // Crear timestamp simple basado en hora (para ordenamiento correcto)
        list($hour, $minute) = explode(':', $startTime);
        $timestamp = ((int)$hour * 3600) + ((int)$minute * 60);

        $eventData = [
            'title' => $programName,
            'start_time' => $startTime,
            'end_time' => $endDateTime->format('H:i'),
            'start_timestamp' => $timestamp,
            'playlist_type' => $playlistType,
            'description' => $programInfo['short_description'] ?? '',
            'long_description' => $programInfo['long_description'] ?? '',
            'type' => $programInfo['type'] ?? '',
            'url' => $programInfo['url'] ?? '',
            'image' => $programInfo['image'] ?? '',
            'presenters' => $programInfo['presenters'] ?? '',
            'twitter' => $programInfo['social_twitter'] ?? '',
            'instagram' => $programInfo['social_instagram'] ?? '',
            'rss_feed' => $programInfo['rss_feed'] ?? ''
        ];

        $eventsByDay[(int)$dayOfWeek][] = $eventData;
    }
}

// Deduplicar eventos por día (eliminar duplicados con mismo título, día y hora)
foreach ($eventsByDay as $day => &$dayEvents) {
    $uniqueEvents = [];
    $seenKeys = [];

    foreach ($dayEvents as $event) {
        // Crear clave única basada en título normalizado + hora de inicio
        $normalizedTitle = trim(mb_strtolower($event['title']));
        $uniqueKey = $normalizedTitle . '_' . $event['start_time'];

        // Solo añadir si no hemos visto esta combinación antes
        if (!isset($seenKeys[$uniqueKey])) {
            $uniqueEvents[] = $event;
            $seenKeys[$uniqueKey] = true;
        }
    }

    $dayEvents = $uniqueEvents;
}

// Ordenar eventos de cada día por hora de inicio
foreach ($eventsByDay as &$dayEvents) {
    usort($dayEvents, function($a, $b) {
        return $a['start_timestamp'] - $b['start_timestamp'];
    });
}

/**
 * Ajustar brillo de un color hexadecimal
 */
function adjustBrightness($hex, $steps) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
               . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
               . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

/**
 * Función para escape HTML
 */
function htmlEsc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación - <?php echo htmlEsc($stationName); ?></title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        <?php
        // Mapear tamaños de fuente
        $fontSizeMap = [
            'small' => '14px',
            'medium' => '16px',
            'large' => '18px'
        ];
        $baseFontSize = $fontSizeMap[$widgetFontSize] ?? '16px';
        ?>

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #fafafa 0%, #e5e5e5 100%);
            color: #1f2937;
            line-height: 1.6;
            font-size: <?php echo $baseFontSize; ?>;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0;
        }

        .header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-bottom: 3px solid <?php echo htmlEsc($widgetColor); ?>;
            color: white;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .header p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
        }

        .day-selector {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .day-tabs {
            display: flex;
            justify-content: center;
            gap: 0;
            max-width: 1400px;
            margin: 0 auto;
            overflow-x: auto;
        }

        .day-tab {
            padding: 16px 24px;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #6b7280;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }

        .day-tab:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        .day-tab.active {
            color: <?php echo htmlEsc($widgetColor); ?>;
            border-bottom-color: <?php echo htmlEsc($widgetColor); ?>;
            font-weight: 600;
        }

        .day-content {
            display: none;
            padding: 30px 20px;
        }

        .day-content.active {
            display: block;
        }

        .programs-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        .program-card {
            background: white;
            <?php if ($widgetStyle === 'modern'): ?>
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            <?php elseif ($widgetStyle === 'classic'): ?>
                border: 2px solid #d1d5db;
                border-radius: 4px;
                box-shadow: none;
            <?php elseif ($widgetStyle === 'compact'): ?>
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                box-shadow: none;
            <?php elseif ($widgetStyle === 'minimal'): ?>
                border: none;
                border-radius: 0;
                box-shadow: none;
                border-bottom: 1px solid #f3f4f6;
            <?php endif; ?>
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: row;
            align-items: stretch;
        }

        .program-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            border-color: <?php echo htmlEsc($widgetColor); ?>;
        }

        .program-card.music-block {
            opacity: 0.75;
            background: #f9fafb;
        }

        .program-card.music-block:hover {
            opacity: 1;
        }

        /* Emisiones en directo - estilo destacado */
        .program-card.live-broadcast {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
            position: relative;
        }

        .program-card.live-broadcast:hover {
            transform: translateX(4px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
            border-color: #d97706;
        }

        .program-card.live-broadcast .program-time {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .program-card.live-broadcast::after {
            content: "🔴 EN DIRECTO";
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc2626;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        .program-image {
            width: 140px;
            min-width: 140px;
            height: auto;
            object-fit: cover;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #9ca3af;
        }

        .program-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .program-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .program-time {
            background: <?php echo htmlEsc($widgetColor); ?>;
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .program-card.music-block .program-time {
            background: #9ca3af;
        }

        .program-body {
            <?php if ($widgetStyle === 'compact'): ?>
                padding: 12px 16px;
            <?php else: ?>
                padding: 20px;
            <?php endif; ?>
            flex: 1;
        }

        .program-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .program-type {
            display: inline-block;
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 12px;
        }

        .program-description {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .program-presenters {
            color: #6b7280;
            font-size: 13px;
            font-style: italic;
            margin-bottom: 12px;
        }

        .program-social {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #6b7280;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }

        .social-link:hover {
            color: <?php echo htmlEsc($widgetColor); ?>;
        }

        .program-url {
            margin-top: 12px;
        }

        .program-url a {
            display: inline-block;
            color: <?php echo htmlEsc($widgetColor); ?>;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .program-url a:hover {
            text-decoration: underline;
        }

        .empty-day {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty-day-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .footer {
            text-align: center;
            padding: 30px 20px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
            margin-top: 40px;
        }

        .footer a {
            color: <?php echo htmlEsc($widgetColor); ?>;
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            color: #0ea575;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 28px;
            }

            .program-card {
                flex-direction: column;
            }

            .program-image {
                width: 100%;
                height: 160px;
                min-width: auto;
            }

            .day-tab {
                padding: 12px 16px;
                font-size: 14px;
            }
        }

        /* Indicador de programa en vivo */
        .program-card.live {
            border: 2px solid #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .program-card.live .program-time {
            background: #ef4444;
            animation: pulse 2s ease-in-out infinite;
        }

        .live-badge {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-left: 8px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Estilos para último episodio RSS */
        .latest-episode {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px dashed #e5e7eb;
        }

        .latest-episode-header {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .latest-episode-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .episode-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .episode-date {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 500;
        }

        .episode-title {
            font-size: 13px;
            color: #374151;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .episode-link {
            text-decoration: none;
            transition: color 0.2s;
        }

        .episode-link:hover {
            color: <?php echo htmlEsc($widgetColor); ?>;
            text-decoration: underline;
        }

        .play-button {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: <?php echo htmlEsc($widgetColor); ?>;
            color: white;
            border-radius: 50%;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .play-button:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* ========================================
           RESPONSIVE DESIGN - TABLETS & MOBILE
           ======================================== */

        /* Tablets (768px - 1024px) */
        @media (max-width: 1024px) {
            .container {
                max-width: 100%;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 28px;
            }

            .programs-list {
                max-width: 100%;
                padding: 0 10px;
            }
        }

        /* Mobile (hasta 768px) */
        @media (max-width: 768px) {
            body {
                font-size: <?php
                    $mobileFontSize = [
                        'small' => '13px',
                        'medium' => '15px',
                        'large' => '17px'
                    ];
                    echo $mobileFontSize[$widgetFontSize] ?? '15px';
                ?>;
            }

            .header {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 24px;
                letter-spacing: 0.3px;
            }

            .header p {
                font-size: 14px;
            }

            /* Tabs horizontales con scroll */
            .day-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: <?php echo htmlEsc($widgetColor); ?> #f3f4f6;
            }

            .day-tabs::-webkit-scrollbar {
                height: 4px;
            }

            .day-tabs::-webkit-scrollbar-track {
                background: #f3f4f6;
            }

            .day-tabs::-webkit-scrollbar-thumb {
                background: <?php echo htmlEsc($widgetColor); ?>;
                border-radius: 2px;
            }

            .day-tab {
                padding: 12px 16px;
                font-size: 14px;
                flex-shrink: 0;
            }

            .day-content {
                padding: 20px 10px;
            }

            /* Cards en móvil - layout vertical */
            .program-card {
                flex-direction: column;
                border-radius: 8px;
            }

            .program-time {
                padding: 10px 15px;
                font-size: 14px;
                border-radius: 0;
            }

            .program-body {
                padding: 15px !important;
            }

            .program-title {
                font-size: 16px;
            }

            .program-description {
                font-size: 13px;
                line-height: 1.5;
            }

            .program-meta {
                font-size: 12px;
                flex-wrap: wrap;
            }

            .program-type {
                font-size: 11px;
            }

            .program-presenters {
                font-size: 13px;
            }

            /* Programas en directo - badge más pequeño */
            .program-card.live-broadcast .live-badge {
                font-size: 10px;
                padding: 3px 8px;
                position: static;
                display: inline-block;
                margin-top: 8px;
            }

            /* Footer */
            .footer {
                padding: 20px 15px;
                font-size: 13px;
            }

            .empty-day-icon {
                font-size: 48px;
            }

            .empty-day h3 {
                font-size: 18px;
            }
        }

        /* Mobile pequeño (hasta 480px) */
        @media (max-width: 480px) {
            .header h1 {
                font-size: 20px;
            }

            .header p {
                font-size: 13px;
            }

            .day-tab {
                padding: 10px 12px;
                font-size: 13px;
            }

            .programs-list {
                gap: 15px;
            }

            .program-title {
                font-size: 15px;
            }

            .program-description {
                font-size: 12px;
            }

            .program-time {
                font-size: 13px;
                padding: 8px 12px;
            }

            .social-links {
                gap: 8px;
            }

            .social-link {
                font-size: 18px;
            }

            /* RSS Episode en móvil */
            .episode-title {
                font-size: 12px;
            }

            .episode-date {
                font-size: 10px;
            }

            .play-button {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .latest-episode-content {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlEsc($stationName); ?></h1>
            <p>Programación Semanal</p>
        </div>

        <div class="day-selector">
            <div class="day-tabs">
                <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
                    <button class="day-tab <?php echo $dayNum === 1 ? 'active' : ''; ?>"
                            onclick="showDay(<?php echo $dayNum; ?>)"
                            data-day="<?php echo $dayNum; ?>">
                        <?php echo htmlEsc($dayName); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
            <div class="day-content <?php echo $dayNum === 1 ? 'active' : ''; ?>" data-day="<?php echo $dayNum; ?>">
                <?php if (empty($eventsByDay[$dayNum])): ?>
                    <div class="empty-day">
                        <div class="empty-day-icon">📭</div>
                        <p>No hay programación disponible para este día</p>
                    </div>
                <?php else: ?>
                    <div class="programs-list">
                        <?php foreach ($eventsByDay[$dayNum] as $event): ?>
                            <?php
                            // Si el programa tiene RSS feed, verificar si el último episodio es reciente
                            $shouldSkip = false;
                            $cachedLatestEpisode = null;

                            if (!empty($event['rss_feed'])) {
                                $cachedLatestEpisode = getLatestEpisodeFromRSS($event['rss_feed']);
                                if ($cachedLatestEpisode && !empty($cachedLatestEpisode['pub_date'])) {
                                    $episodeTimestamp = strtotime($cachedLatestEpisode['pub_date']);
                                    $daysSinceLastEpisode = (time() - $episodeTimestamp) / (60 * 60 * 24);

                                    // Si hace más de 30 días, ocultar el programa
                                    if ($daysSinceLastEpisode > 30) {
                                        $shouldSkip = true;
                                    }
                                }
                            }

                            if ($shouldSkip) {
                                continue;
                            }

                            // Determinar si el programa está en vivo ahora
                            $now = time();
                            $isLive = false;

                            // Calcular timestamp para hoy
                            $todayDayOfWeek = (int)date('w');
                            if ($dayNum === $todayDayOfWeek) {
                                $todayStart = strtotime('today ' . $event['start_time']);
                                $todayEnd = strtotime('today ' . $event['end_time']);

                                if ($todayEnd < $todayStart) {
                                    $todayEnd = strtotime('tomorrow ' . $event['end_time']);
                                }

                                $isLive = ($now >= $todayStart && $now < $todayEnd);
                            }
                            ?>
                            <div class="program-card <?php echo $event['playlist_type'] === 'live' ? 'live-broadcast' : ''; ?> <?php echo $isLive ? 'live' : ''; ?>">
                                <div class="program-image">
                                    <?php if (!empty($event['image'])): ?>
                                        <img src="<?php echo htmlEsc($event['image']); ?>"
                                             alt="<?php echo htmlEsc($event['title']); ?>"
                                             onerror="this.parentElement.innerHTML='📻';">
                                    <?php else: ?>
                                        <?php
                                        if ($event['playlist_type'] === 'live') {
                                            echo '🔴';
                                        } elseif ($event['playlist_type'] === 'music_block') {
                                            echo '🎵';
                                        } else {
                                            echo '📻';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>

                                <div class="program-content">
                                    <div class="program-time">
                                        🕐 <?php echo htmlEsc($event['start_time']); ?>
                                        <?php if ($isLive): ?>
                                            <span class="live-badge">🔴 EN VIVO</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="program-body">
                                        <h3 class="program-title"><?php echo htmlEsc($event['title']); ?></h3>

                                        <?php if (!empty($event['type'])): ?>
                                            <span class="program-type"><?php echo htmlEsc($event['type']); ?></span>
                                        <?php endif; ?>

                                        <?php if (!empty($event['description'])): ?>
                                            <p class="program-description">
                                                <?php echo htmlEsc($event['description']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($event['presenters'])): ?>
                                            <p class="program-presenters">
                                                👤 <?php echo htmlEsc($event['presenters']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($event['twitter']) || !empty($event['instagram'])): ?>
                                            <div class="program-social">
                                                <?php if (!empty($event['twitter'])): ?>
                                                    <a href="https://twitter.com/<?php echo htmlEsc(ltrim($event['twitter'], '@')); ?>"
                                                       target="_blank"
                                                       class="social-link">
                                                        🐦 @<?php echo htmlEsc(ltrim($event['twitter'], '@')); ?>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (!empty($event['instagram'])): ?>
                                                    <a href="https://instagram.com/<?php echo htmlEsc(ltrim($event['instagram'], '@')); ?>"
                                                       target="_blank"
                                                       class="social-link">
                                                        📷 @<?php echo htmlEsc(ltrim($event['instagram'], '@')); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($event['url'])): ?>
                                            <div class="program-url">
                                                <a href="<?php echo htmlEsc($event['url']); ?>"
                                                   target="_blank">
                                                    🔗 Más información
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        // Mostrar último episodio si hay RSS feed (usar caché del check anterior)
                                        if (!empty($event['rss_feed']) && $cachedLatestEpisode):
                                            $latestEpisode = $cachedLatestEpisode;
                                        ?>
                                            <div class="latest-episode">
                                                <div class="latest-episode-header">
                                                    📻 Último programa
                                                </div>
                                                <div class="latest-episode-content">
                                                    <div class="episode-info">
                                                        <span class="episode-date"><?php echo htmlEsc($latestEpisode['formatted_date']); ?></span>
                                                        <?php if (!empty($latestEpisode['link'])): ?>
                                                            <a href="<?php echo htmlEsc($latestEpisode['link']); ?>"
                                                               target="_blank"
                                                               class="episode-title episode-link"
                                                               title="Ver episodio">
                                                                <?php echo htmlEsc($latestEpisode['title']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="episode-title"><?php echo htmlEsc($latestEpisode['title']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($latestEpisode['audio_url'])): ?>
                                                        <a href="<?php echo htmlEsc($latestEpisode['audio_url']); ?>"
                                                           target="_blank"
                                                           class="play-button"
                                                           title="Reproducir audio">
                                                            ▶️
                                                        </a>
                                                    <?php elseif (!empty($latestEpisode['link'])): ?>
                                                        <a href="<?php echo htmlEsc($latestEpisode['link']); ?>"
                                                           target="_blank"
                                                           class="play-button"
                                                           title="Ver episodio">
                                                            🔗
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="footer">
            Generado con <a href="https://github.com/antich2004-gr/SAPO" target="_blank">SAPO</a>
        </div>
    </div>

    <script>
        function showDay(dayNum) {
            // Ocultar todos los contenidos
            document.querySelectorAll('.day-content').forEach(content => {
                content.classList.remove('active');
            });

            // Desactivar todas las pestañas
            document.querySelectorAll('.day-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Activar el contenido seleccionado
            document.querySelector(`.day-content[data-day="${dayNum}"]`).classList.add('active');

            // Activar la pestaña seleccionada
            document.querySelector(`.day-tab[data-day="${dayNum}"]`).classList.add('active');

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Auto-seleccionar el día actual al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().getDay(); // 0=Domingo, 1=Lunes, etc.
            showDay(today);

            // Scroll al programa en vivo después de un pequeño delay
            setTimeout(function() {
                const liveProgram = document.querySelector('.program-card.live');
                if (liveProgram) {
                    liveProgram.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }, 300);
        });
    </script>
</body>
</html>
