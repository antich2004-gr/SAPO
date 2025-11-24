<?php
// parrilla_simple.php - Versión simplificada sin CSS complejo

// Headers de seguridad (permiten embebido en otras webs)
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-ancestors *");

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
$schedule = getAzuracastSchedule($station);
if ($schedule === false) $schedule = [];

$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];

// Procesar eventos (MISMO CÓDIGO que parrilla_cards.php)
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
    
    $eventsByDay[$dayOfWeek][] = [
        'title' => $title,
        'start_time' => $startDateTime->format('H:i'),
        'end_time' => $endDateTime->format('H:i'),
        'description' => $programInfo['short_description'] ?? '',
        'image' => $programInfo['image'] ?? '',
        'playlist_type' => $playlistType
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación - <?php echo htmlspecialchars($station); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .day { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 8px; }
        .program { margin: 10px 0; padding: 10px; background: white; border-left: 3px solid #3b82f6; }
        .time { font-weight: bold; color: #3b82f6; }
    </style>
</head>
<body>
    <h1>Programación - <?php echo htmlspecialchars($station); ?></h1>
    
    <?php
    $daysOfWeek = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
    foreach ([1,2,3,4,5,6,0] as $day):
        if (empty($eventsByDay[$day])) continue;
    ?>
        <div class="day">
            <h2><?php echo $daysOfWeek[$day]; ?></h2>
            <?php foreach ($eventsByDay[$day] as $event): ?>
                <div class="program">
                    <span class="time"><?php echo htmlspecialchars($event['start_time']); ?></span>
                    - <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                    <?php if ($event['description']): ?>
                        <p><?php echo htmlspecialchars($event['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
