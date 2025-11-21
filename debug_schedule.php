<?php
// debug_schedule.php - Ver datos crudos de la API de AzuraCast
// IMPORTANTE: Eliminar este archivo después de debugear

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$station = $_GET['station'] ?? '';
if (empty($station)) {
    die(json_encode(['error' => 'Falta parámetro station'], JSON_PRETTY_PRINT));
}

// Obtener schedule SIN caché (TTL = 0)
$schedule = getAzuracastSchedule($station, 0);

if ($schedule === false) {
    die(json_encode(['error' => 'No se pudo obtener el schedule de AzuraCast'], JSON_PRETTY_PRINT));
}

// Cargar datos de programas para ver tipos
$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];

// Agrupar eventos por nombre de playlist
$eventsByPlaylist = [];
foreach ($schedule as $event) {
    $name = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
    if (!isset($eventsByPlaylist[$name])) {
        $eventsByPlaylist[$name] = [];
    }

    // Convertir timestamps a formato legible
    $start = $event['start_timestamp'] ?? $event['start'] ?? null;
    $end = $event['end_timestamp'] ?? $event['end'] ?? null;

    $startDateTime = $start ? (is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start)) : null;
    $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

    if ($startDateTime) {
        $startDateTime->setTimezone(new DateTimeZone('Europe/Madrid'));
    }
    if ($endDateTime) {
        $endDateTime->setTimezone(new DateTimeZone('Europe/Madrid'));
    }

    $dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $dayNumber = $startDateTime ? (int)$startDateTime->format('w') : -1;

    // Obtener info del programa
    $programInfo = $programsData[$name] ?? null;
    $playlistType = $programInfo['playlist_type'] ?? 'program';
    $isHidden = !empty($programInfo['hidden_from_schedule']);

    $eventsByPlaylist[$name][] = [
        'dia' => $startDateTime ? $dayNames[$dayNumber] : 'N/A',
        'dia_numero' => $dayNumber,
        'fecha' => $startDateTime ? $startDateTime->format('Y-m-d') : 'N/A',
        'hora_inicio' => $startDateTime ? $startDateTime->format('H:i') : 'N/A',
        'hora_fin' => $endDateTime ? $endDateTime->format('H:i') : 'N/A',
        'playlist_type' => $playlistType,
        'hidden' => $isHidden,
        'raw_start' => $start,
        'raw_end' => $end
    ];
}

// Ordenar por nombre
ksort($eventsByPlaylist);

// Simular procesamiento de parrilla_cards.php
$eventsByDay = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 0 => []];
$dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

foreach ($schedule as $event) {
    $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
    $start = $event['start_timestamp'] ?? $event['start'] ?? null;
    if ($start === null) continue;

    $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
    $startDateTime->setTimezone(new DateTimeZone('Europe/Madrid'));
    $dayOfWeek = (int)$startDateTime->format('w');

    $programInfo = $programsData[$title] ?? null;
    $playlistType = $programInfo['playlist_type'] ?? 'program';

    // Omitir jingles y programas ocultos (como hace parrilla_cards.php)
    if ($playlistType === 'jingles') continue;
    if (!empty($programInfo['hidden_from_schedule'])) continue;

    // Solo añadir si NO es music_block (esos van a otro array)
    if ($playlistType !== 'music_block') {
        $eventsByDay[$dayOfWeek][] = [
            'title' => $title,
            'start_time' => $startDateTime->format('H:i'),
            'playlist_type' => $playlistType
        ];
    }
}

// Deduplicar (como hace parrilla_cards.php)
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

    $dayEvents = $uniqueEvents;
}
unset($dayEvents);

// Formatear resultado
$processedByDay = [];
foreach ($eventsByDay as $day => $events) {
    $dayName = $dayNames[$day];
    $processedByDay[$dayName] = $events;
}

// ===== DEBUG ESPECÍFICO: Buscar programa específico =====
$searchTerm = $_GET['search'] ?? '';
$specificProgramEvents = [];

if (!empty($searchTerm)) {
    $searchLower = mb_strtolower($searchTerm);

    // Buscar en eventos de la API
    foreach ($schedule as $event) {
        $name = $event['name'] ?? $event['playlist'] ?? '';
        if (stripos($name, $searchTerm) !== false) {
            $start = $event['start_timestamp'] ?? $event['start'] ?? null;
            $end = $event['end_timestamp'] ?? $event['end'] ?? null;

            $startDT = $start ? (is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start)) : null;
            $endDT = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

            if ($startDT) $startDT->setTimezone(new DateTimeZone('Europe/Madrid'));
            if ($endDT) $endDT->setTimezone(new DateTimeZone('Europe/Madrid'));

            $dayNum = $startDT ? (int)$startDT->format('w') : -1;

            $programInfo = $programsData[$name] ?? null;

            $specificProgramEvents[] = [
                'api_name' => $name,
                'dia_numero' => $dayNum,
                'dia_nombre' => $startDT ? $dayNames[$dayNum] : 'N/A',
                'fecha' => $startDT ? $startDT->format('Y-m-d') : 'N/A',
                'hora_inicio' => $startDT ? $startDT->format('H:i') : 'N/A',
                'hora_fin' => $endDT ? $endDT->format('H:i') : 'N/A',
                'programa_en_db' => $programInfo !== null,
                'playlist_type' => $programInfo['playlist_type'] ?? 'program',
                'hidden' => !empty($programInfo['hidden_from_schedule']),
                'rss_feed' => $programInfo['rss_feed'] ?? '',
                'display_title' => $programInfo['display_title'] ?? ''
            ];
        }
    }

    // Ver cómo queda en eventsByDay después del procesamiento
    $processedForSearch = [];
    foreach ($eventsByDay as $day => $events) {
        foreach ($events as $event) {
            if (stripos($event['title'], $searchTerm) !== false) {
                $processedForSearch[$dayNames[$day]][] = [
                    'title' => $event['title'],
                    'start_time' => $event['start_time'],
                    'playlist_type' => $event['playlist_type']
                ];
            }
        }
    }

    // Verificar si hay algún problema con deduplicación
    $deduplicationCheck = [];
    foreach ($schedule as $event) {
        $name = $event['name'] ?? $event['playlist'] ?? '';
        if (stripos($name, $searchTerm) !== false) {
            $start = $event['start_timestamp'] ?? $event['start'] ?? null;
            if ($start === null) continue;

            $startDT = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
            $startDT->setTimezone(new DateTimeZone('Europe/Madrid'));

            $programInfo = $programsData[$name] ?? null;
            $displayTitle = !empty($programInfo['display_title']) ? $programInfo['display_title'] : $name;

            $normalizedTitle = trim(mb_strtolower($displayTitle));
            $uniqueKey = $normalizedTitle . '_' . $startDT->format('H:i');

            $deduplicationCheck[] = [
                'api_name' => $name,
                'display_title' => $displayTitle,
                'hora' => $startDT->format('H:i'),
                'dia' => $dayNames[(int)$startDT->format('w')],
                'unique_key' => $uniqueKey
            ];
        }
    }
}

$result = [
    'total_eventos_api' => count($schedule),
    'playlists_unicas' => count($eventsByPlaylist)
];

// Si hay búsqueda específica, mostrar solo eso
if (!empty($searchTerm)) {
    $result['busqueda'] = $searchTerm;
    $result['eventos_api_encontrados'] = count($specificProgramEvents);
    $result['detalle_eventos_api'] = $specificProgramEvents;
    $result['claves_deduplicacion'] = $deduplicationCheck;
    $result['eventos_procesados_encontrados'] = $processedForSearch;
} else {
    $result['eventos_por_playlist'] = $eventsByPlaylist;
    $result['eventos_procesados_por_dia'] = $processedByDay;
}

$result = [
    'total_eventos_api' => count($schedule),
    'playlists_unicas' => count($eventsByPlaylist),
    'eventos_por_playlist' => $eventsByPlaylist,
    'eventos_procesados_por_dia' => $processedByDay
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
