<?php
/**
 * Script de diagnóstico de rendimiento
 * Mide el tiempo de cada operación principal
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/programs.php';
require_once __DIR__ . '/includes/azuracast.php';

$station = $_GET['station'] ?? 'salto';

echo "=== Diagnóstico de Rendimiento ===\n";
echo "Station: $station\n\n";

$totalStart = microtime(true);

// 1. Cargar config de Radiobot
$t1 = microtime(true);
$azConfig = getAzuracastConfig($station);
$t2 = microtime(true);
echo sprintf("1. getAzuracastConfig: %.3fs\n", $t2 - $t1);

// 2. Cargar schedule de Radiobot
$t1 = microtime(true);
$schedule = getAzuracastSchedule($station);
$t2 = microtime(true);
echo sprintf("2. getAzuracastSchedule: %.3fs\n", $t2 - $t1);

if ($schedule === false) {
    echo "   ERROR: No se pudo obtener schedule\n";
    exit(1);
}
echo "   Eventos obtenidos: " . count($schedule) . "\n";

// 3. Cargar programas DB
$t1 = microtime(true);
$programsDB = loadProgramsDB($station);
$t2 = microtime(true);
echo sprintf("3. loadProgramsDB: %.3fs\n", $t2 - $t1);

// 4. Formatear eventos
$t1 = microtime(true);
$events = formatEventsForCalendar($schedule, '#3b82f6', $station);
$t2 = microtime(true);
echo sprintf("4. formatEventsForCalendar: %.3fs\n", $t2 - $t1);
echo "   Eventos formateados: " . count($events) . "\n";

// 5. Simular carga de RSS (solo contar cuántos hay)
$rssCount = 0;
foreach ($events as $event) {
    if (!empty($event['extendedProps']['rss_feed'] ?? '')) {
        $rssCount++;
    }
}

// Extraer info de programas desde programsDB
$programs = $programsDB['programs'] ?? [];
$rssFeeds = [];
foreach ($programs as $name => $info) {
    if (!empty($info['rss_feed'])) {
        $rssFeeds[$name] = $info['rss_feed'];
    }
}

echo "\n5. Feeds RSS configurados: " . count($rssFeeds) . "\n";

$t1 = microtime(true);
$rssResults = [];
foreach ($rssFeeds as $name => $url) {
    $rt1 = microtime(true);
    $episode = getLatestEpisodeFromRSS($url, 21600);
    $rt2 = microtime(true);
    $rssResults[$name] = [
        'time' => $rt2 - $rt1,
        'success' => $episode !== null
    ];
}
$t2 = microtime(true);

echo sprintf("   Tiempo total RSS: %.3fs\n", $t2 - $t1);
foreach ($rssResults as $name => $result) {
    $status = $result['success'] ? '✓' : '✗';
    echo sprintf("   - %s %s: %.3fs\n", $status, substr($name, 0, 40), $result['time']);
}

$totalEnd = microtime(true);
echo sprintf("\n=== TIEMPO TOTAL: %.3fs ===\n", $totalEnd - $totalStart);
