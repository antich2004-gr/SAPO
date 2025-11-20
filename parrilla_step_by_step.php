<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PASO 1: Cargar includes<br>";
require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';
require_once INCLUDES_DIR . '/utils.php';
echo "✅ Includes cargados<br><br>";

echo "PASO 2: Validar estación<br>";
$station = 'sonora';
$user = findUserByUsername($station);
echo "✅ Usuario encontrado<br><br>";

echo "PASO 3: Obtener configuración<br>";
$azConfig = getAzuracastConfig($station);
$widgetColor = $azConfig['widget_color'] ?? '#10b981';
echo "✅ Config obtenida, color: $widgetColor<br><br>";

echo "PASO 4: Obtener schedule de Radiobot<br>";
$schedule = getAzuracastSchedule($station);
if ($schedule === false) {
    $schedule = [];
}
echo "✅ Schedule obtenido: " . count($schedule) . " eventos<br><br>";

echo "PASO 5: Cargar programas DB<br>";
$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];
echo "✅ Programs DB cargado: " . count($programsData) . " programas<br><br>";

echo "PASO 6: Organizar eventos por día<br>";
$eventsByDay = [
    1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 0 => []
];

$processedCount = 0;
foreach ($schedule as $event) {
    try {
        $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
        $start = $event['start_timestamp'] ?? $event['start'] ?? null;
        
        if ($start === null) continue;
        
        $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
        $dayOfWeek = (int)$startDateTime->format('w');
        
        $programInfo = $programsData[$title] ?? null;
        $playlistType = $programInfo['playlist_type'] ?? 'program';
        
        if ($playlistType === 'jingles' || $playlistType === 'music_block') {
            continue;
        }
        
        $eventsByDay[$dayOfWeek][] = [
            'title' => $title,
            'start_time' => $startDateTime->format('H:i'),
            'playlist_type' => $playlistType
        ];
        $processedCount++;
        
    } catch (Throwable $e) {
        echo "❌ Error procesando evento: " . $e->getMessage() . "<br>";
        die();
    }
}
echo "✅ Eventos procesados: $processedCount<br><br>";

echo "PASO 7: Intentar generar HTML básico<br>";
echo "<hr>";
echo "<h1>Programación de $station</h1>";

foreach ([1,2,3,4,5,6,0] as $day) {
    $dayNames = [0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'];
    echo "<h2>{$dayNames[$day]}</h2>";
    echo "<ul>";
    foreach ($eventsByDay[$day] as $event) {
        echo "<li>{$event['start_time']} - {$event['title']}</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<h2>✅ TODOS LOS PASOS COMPLETADOS SIN ERRORES</h2>";
echo "<p>Si esto funciona pero parrilla_cards.php no, el problema está en el CSS/HTML complejo del archivo original.</p>";
