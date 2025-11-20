<?php
// parrilla_cards.php - Widget de parrilla estilo fichas por días

// Headers de seguridad para permitir embebido
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy - Protección contra XSS
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; " .  // unsafe-inline necesario para <script> embebido
    "style-src 'self' 'unsafe-inline'; " .   // unsafe-inline necesario para <style> embebido
    "img-src 'self' data: https:; " .        // Permitir imágenes externas HTTPS
    "font-src 'self'; " .
    "connect-src 'self'; " .
    "frame-ancestors 'self' *; " .           // Permitir embebido en cualquier origen
    "base-uri 'self'; " .
    "form-action 'self'"
);

// HSTS - Forzar HTTPS si está disponible
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';
require_once INCLUDES_DIR . '/utils.php';
require_once INCLUDES_DIR . '/security_logger.php';

// Obtener parámetro de estación
$station = $_GET['station'] ?? '';

if (empty($station)) {
    die('Error: Debe especificar una estación (?station=nombre)');
}

// SEGURIDAD: Validar formato de username para prevenir path traversal
if (!validateInput($station, 'username')) {
    logPathTraversal($station, ['source' => 'parrilla_cards.php']);
    die('Error: Nombre de estación inválido');
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
