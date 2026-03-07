<?php
/**
 * Gestión de grabaciones de AzuraCast
 * Funciones para listar, eliminar y gestionar grabaciones automáticas
 */

/**
 * Obtener directorio de grabaciones de la emisora
 * Usa el short_name de la estación de AzuraCast
 */
function getRecordingsDir($username) {
    // Obtener station short name desde AzuraCast
    $stationShortName = getStationShortName($username);

    if ($stationShortName === null) {
        error_log("RECORDINGS: No se pudo obtener station short name para usuario: $username");
        // Fallback: intentar con username
        return "/var/azuracast/stations/{$username}/recordings";
    }

    return "/var/azuracast/stations/{$stationShortName}/recordings";
}

/**
 * Obtener el short_name de la estación desde AzuraCast API
 */
function getStationShortName($username) {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        return null;
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return null;
    }

    // Endpoint para obtener info de la estación
    $endpoint = rtrim($apiUrl, '/') . '/admin/station/' . $stationId;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "X-API-Key: {$apiKey}\r\n"
        ]
    ]);

    $response = @file_get_contents($endpoint, false, $context);

    if ($response === false) {
        error_log("RECORDINGS: Error al obtener station info desde API");
        return null;
    }

    $stationData = json_decode($response, true);
    $shortName = $stationData['short_name'] ?? $stationData['name'] ?? null;

    error_log("RECORDINGS: Station short name obtenido: $shortName (station_id: $stationId)");

    return $shortName;
}

/**
 * Obtener ruta del archivo de configuración de grabaciones
 */
function getRecordingsConfigPath($username) {
    $dir = dirname(__DIR__) . '/data/users/' . $username;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/recordings_config.json';
}

/**
 * Obtener configuración de retención de grabaciones
 */
function getRecordingsConfig($username) {
    $configPath = getRecordingsConfigPath($username);

    if (!file_exists($configPath)) {
        // Configuración por defecto: 30 días
        return [
            'retention_days' => 30,
            'auto_delete' => true
        ];
    }

    $json = file_get_contents($configPath);
    $config = json_decode($json, true);

    if (!is_array($config)) {
        return [
            'retention_days' => 30,
            'auto_delete' => true
        ];
    }

    return $config;
}

/**
 * Guardar configuración de retención de grabaciones
 */
function saveRecordingsConfig($username, $retentionDays, $autoDelete = true) {
    $configPath = getRecordingsConfigPath($username);

    // Validar días de retención (mínimo 1, máximo 365)
    $retentionDays = max(1, min(365, intval($retentionDays)));

    $config = [
        'retention_days' => $retentionDays,
        'auto_delete' => (bool)$autoDelete,
        'last_updated' => date('Y-m-d H:i:s')
    ];

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($configPath, $json) === false) {
        return ['success' => false, 'message' => 'Error al guardar configuración'];
    }

    return ['success' => true, 'message' => 'Configuración guardada correctamente'];
}

/**
 * Listar todas las grabaciones de la emisora
 * Devuelve array con información detallada de cada grabación
 */
function listRecordings($username) {
    $recordingsDir = getRecordingsDir($username);

    if (!is_dir($recordingsDir)) {
        return [];
    }

    $recordings = [];
    $files = scandir($recordingsDir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $recordingsDir . '/' . $file;

        // Solo archivos de audio
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac'])) {
            continue;
        }

        if (is_file($filePath)) {
            $fileSize = filesize($filePath);
            $fileTime = filemtime($filePath);
            $daysOld = floor((time() - $fileTime) / 86400);

            $recordings[] = [
                'filename' => $file,
                'size' => $fileSize,
                'size_formatted' => formatBytes($fileSize),
                'date' => date('Y-m-d H:i:s', $fileTime),
                'timestamp' => $fileTime,
                'days_old' => $daysOld,
                'extension' => $extension
            ];
        }
    }

    // Ordenar por fecha (más recientes primero)
    usort($recordings, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    return $recordings;
}

/**
 * Obtener estadísticas de grabaciones
 */
function getRecordingsStats($username, $retentionDays = null) {
    $recordings = listRecordings($username);

    if ($retentionDays === null) {
        $config = getRecordingsConfig($username);
        $retentionDays = $config['retention_days'];
    }

    $stats = [
        'total_count' => count($recordings),
        'total_size' => 0,
        'total_size_formatted' => '0 B',
        'old_count' => 0,
        'old_size' => 0,
        'old_size_formatted' => '0 B',
        'retention_days' => $retentionDays
    ];

    foreach ($recordings as $recording) {
        $stats['total_size'] += $recording['size'];

        if ($recording['days_old'] >= $retentionDays) {
            $stats['old_count']++;
            $stats['old_size'] += $recording['size'];
        }
    }

    $stats['total_size_formatted'] = formatBytes($stats['total_size']);
    $stats['old_size_formatted'] = formatBytes($stats['old_size']);

    return $stats;
}

/**
 * Eliminar una grabación específica
 */
function deleteRecording($username, $filename) {
    // Validar nombre de archivo (seguridad)
    if (empty($filename) || strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        return ['success' => false, 'message' => 'Nombre de archivo inválido'];
    }

    $recordingsDir = getRecordingsDir($username);
    $filePath = $recordingsDir . '/' . $filename;

    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'El archivo no existe'];
    }

    if (!is_file($filePath)) {
        return ['success' => false, 'message' => 'La ruta no es un archivo válido'];
    }

    // Verificar que sea un archivo de audio
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac'])) {
        return ['success' => false, 'message' => 'Solo se pueden eliminar archivos de audio'];
    }

    // Eliminar archivo
    if (unlink($filePath)) {
        error_log("RECORDINGS: Archivo eliminado: $filePath");
        return ['success' => true, 'message' => 'Grabación eliminada correctamente'];
    } else {
        error_log("RECORDINGS: Error al eliminar archivo: $filePath");
        return ['success' => false, 'message' => 'Error al eliminar el archivo'];
    }
}

/**
 * Eliminar grabaciones antiguas según días de retención
 */
function deleteOldRecordings($username, $retentionDays = null) {
    if ($retentionDays === null) {
        $config = getRecordingsConfig($username);
        $retentionDays = $config['retention_days'];
    }

    $recordings = listRecordings($username);
    $deleted = 0;
    $errors = 0;
    $spaceSaved = 0;

    foreach ($recordings as $recording) {
        if ($recording['days_old'] >= $retentionDays) {
            $result = deleteRecording($username, $recording['filename']);

            if ($result['success']) {
                $deleted++;
                $spaceSaved += $recording['size'];
            } else {
                $errors++;
            }
        }
    }

    $message = "Eliminadas $deleted grabación(es)";
    if ($spaceSaved > 0) {
        $message .= " • Espacio liberado: " . formatBytes($spaceSaved);
    }
    if ($errors > 0) {
        $message .= " • Errores: $errors";
    }

    error_log("RECORDINGS: Usuario $username - $message");

    return [
        'success' => true,
        'message' => $message,
        'deleted' => $deleted,
        'errors' => $errors,
        'space_saved' => $spaceSaved,
        'space_saved_formatted' => formatBytes($spaceSaved)
    ];
}

// Nota: formatBytes() ya está definida en includes/categories.php
