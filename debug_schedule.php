<?php
// debug_schedule.php - Ver datos crudos de la API de AzuraCast
// IMPORTANTE: Eliminar este archivo después de debugear

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';

header('Content-Type: application/json; charset=utf-8');

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

$result = [
    'total_eventos' => count($schedule),
    'playlists_unicas' => count($eventsByPlaylist),
    'eventos_por_playlist' => $eventsByPlaylist
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
