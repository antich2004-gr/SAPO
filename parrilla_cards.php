<?php
// parrilla_cards.php - Widget de parrilla estilo fichas por días
// Similar a https://cadenaser.com/cadena-ser/programacion/

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
$widgetColor = $azConfig['widget_color'] ?? '#3b82f6';
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

// Procesar eventos y organizarlos por día
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

    // Filtrar jingles
    if ($playlistType === 'jingles') {
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

    // Crear objeto de evento
    $eventData = [
        'title' => $title,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'start_timestamp' => $startDateTime->getTimestamp(),
        'playlist_type' => $playlistType,
        'description' => $programInfo['short_description'] ?? '',
        'long_description' => $programInfo['long_description'] ?? '',
        'type' => $programInfo['type'] ?? '',
        'url' => $programInfo['url'] ?? '',
        'image' => $programInfo['image'] ?? '',
        'presenters' => $programInfo['presenters'] ?? '',
        'twitter' => $programInfo['social_twitter'] ?? '',
        'instagram' => $programInfo['social_instagram'] ?? ''
    ];

    $eventsByDay[$dayOfWeek][] = $eventData;
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

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #ffffff;
            color: #1f2937;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0;
        }

        .header {
            background: linear-gradient(135deg, <?php echo htmlEsc($widgetColor); ?> 0%, <?php echo adjustBrightness($widgetColor, -30); ?> 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.95;
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
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
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
            padding: 20px;
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
                            <div class="program-card <?php echo $event['playlist_type'] === 'music_block' ? 'music-block' : ''; ?> <?php echo $isLive ? 'live' : ''; ?>">
                                <div class="program-image">
                                    <?php if (!empty($event['image'])): ?>
                                        <img src="<?php echo htmlEsc($event['image']); ?>"
                                             alt="<?php echo htmlEsc($event['title']); ?>"
                                             onerror="this.parentElement.innerHTML='📻';">
                                    <?php else: ?>
                                        <?php echo $event['playlist_type'] === 'music_block' ? '🎵' : '📻'; ?>
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
        });
    </script>
</body>
</html>
