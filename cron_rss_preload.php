#!/usr/bin/env php
<?php
/**
 * Script de pre-carga de RSS feeds
 * Ejecutar con cron cada hora para mantener caché caliente
 *
 * Uso: php cron_rss_preload.php
 */

// Cargar configuración
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/programs.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando pre-carga de RSS feeds\n";

$startTime = microtime(true);
$totalFeeds = 0;
$successFeeds = 0;
$failedFeeds = 0;

// Obtener todos los archivos de programas
$programsDir = DATA_DIR . '/programs';
if (!file_exists($programsDir)) {
    echo "No hay programas registrados\n";
    exit(0);
}

$programFiles = glob($programsDir . '/*.json');

if (empty($programFiles)) {
    echo "No se encontraron archivos de programas\n";
    exit(0);
}

foreach ($programFiles as $programsFile) {
    $username = basename($programsFile, '.json');

    // Saltar .gitignore y otros archivos no JSON
    if ($username === '.gitignore' || !is_file($programsFile)) {
        continue;
    }

    $programsData = json_decode(file_get_contents($programsFile), true);
    if (!$programsData || !isset($programsData['programs'])) {
        continue;
    }

    echo "Usuario: $username\n";

    // Iterar sobre cada programa
    foreach ($programsData['programs'] as $programName => $programInfo) {
        $rssUrl = $programInfo['rss_feed'] ?? '';

        if (empty($rssUrl)) {
            continue;
        }

        $totalFeeds++;
        echo "  - Cargando RSS: $programName... ";

        // Intentar cargar el episodio (esto lo guardará en caché)
        $episode = getLatestEpisodeFromRSS($rssUrl, 21600); // 6 horas de TTL

        if ($episode !== null) {
            $successFeeds++;
            echo "✓ OK\n";
        } else {
            $failedFeeds++;
            echo "✗ FAIL\n";
        }

        // Pequeña pausa para no sobrecargar
        usleep(50000); // 0.05 segundos (~20 req/s)
    }
}

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n[" . date('Y-m-d H:i:s') . "] Finalizado\n";
echo "Total feeds: $totalFeeds\n";
echo "Exitosos: $successFeeds\n";
echo "Fallidos: $failedFeeds\n";
echo "Tiempo: {$duration}s\n";

// Log en syslog
error_log("RSS Pre-load: $successFeeds/$totalFeeds feeds cargados en {$duration}s");

exit(0);
