<?php
/**
 * Gestión de grabaciones de AzuraCast
 * Funciones para listar, eliminar y gestionar grabaciones automáticas
 */

/**
 * Obtener directorio de grabaciones de la emisora
 * Usa el campo recordings_storage_location.path devuelto por la API de AzuraCast
 */
function getRecordingsDir($username) {
    // Comprobar primero si hay un slug manual configurado en SAPO
    $azConfig = getAzuracastConfig($username);
    $manualSlug = $azConfig['azuracast_short_name'] ?? '';

    $stationInfo = getStationInfo($username);

    if ($stationInfo === null) {
        error_log("RECORDINGS: No se pudo obtener info de la estación para usuario: $username");
        // Fallback: usar slug manual si está configurado, si no el username
        $slug = !empty($manualSlug) ? $manualSlug : $username;
        return "/var/azuracast/stations/{$slug}/recordings";
    }

    // recordings_storage_location puede ser un objeto con 'path' (versiones nuevas)
    // o simplemente el ID entero (versiones anteriores de AzuraCast)
    $recStorageLoc = $stationInfo['recordings_storage_location'] ?? null;
    if (is_array($recStorageLoc) && !empty($recStorageLoc['path'])) {
        error_log("RECORDINGS: Ruta de grabaciones obtenida desde recordings_storage_location.path: " . $recStorageLoc['path']);
        return rtrim($recStorageLoc['path'], '/');
    }

    // Fallback principal: radio_base_dir devuelve la ruta real del servidor,
    // incluyendo mayúsculas/minúsculas correctas del nombre de la emisora
    $radioBaseDir = $stationInfo['radio_base_dir'] ?? null;
    if (!empty($radioBaseDir)) {
        $path = rtrim($radioBaseDir, '/') . '/recordings';
        error_log("RECORDINGS: Ruta de grabaciones construida desde radio_base_dir: $path");
        return $path;
    }

    // Fallback: usar short_name de la API
    $shortName = $stationInfo['short_name'] ?? $stationInfo['name'] ?? null;
    if (!empty($shortName)) {
        $path = "/var/azuracast/stations/{$shortName}/recordings";
        error_log("RECORDINGS: Ruta de grabaciones construida desde short_name: $path");
        return $path;
    }

    // Último fallback: slug manual configurado en SAPO, o username
    $slug = !empty($manualSlug) ? $manualSlug : $username;
    error_log("RECORDINGS: Último fallback, usando slug: $slug");
    return "/var/azuracast/stations/{$slug}/recordings";
}

/**
 * Obtener información completa de la estación desde AzuraCast API
 * Devuelve el objeto estación incluyendo recordings_storage_location, radio_base_dir, short_name, etc.
 */
function getStationInfo($username) {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        error_log("RECORDINGS: API URL o API Key no configurados");
        return null;
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        error_log("RECORDINGS: No hay station_id configurado para usuario: $username");
        return null;
    }

    // Endpoint de admin devuelve datos completos incluyendo recordings_storage_location.path
    $endpoint = rtrim($apiUrl, '/') . '/admin/station/' . $stationId;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "X-API-Key: {$apiKey}\r\n"
        ]
    ]);

    $response = @file_get_contents($endpoint, false, $context);

    if ($response === false) {
        error_log("RECORDINGS: Error al obtener station info desde API (station_id: $stationId)");
        return null;
    }

    $stationData = json_decode($response, true);

    if (!is_array($stationData)) {
        error_log("RECORDINGS: Respuesta inválida de la API para station_id: $stationId");
        return null;
    }

    error_log("RECORDINGS: Station info obtenida para station_id: $stationId, short_name: " . ($stationData['short_name'] ?? 'N/A'));

    return $stationData;
}

/**
 * Obtener el short_name de la estación desde AzuraCast API
 * @deprecated Usar getStationInfo() directamente para acceder a todos los campos
 */
function getStationShortName($username) {
    $stationData = getStationInfo($username);
    return $stationData['short_name'] ?? $stationData['name'] ?? null;
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
    $retentionDays = max(1, min(180, intval($retentionDays)));

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
 * Listar todas las grabaciones de la emisora.
 * AzuraCast organiza las grabaciones en subcarpetas por streamer:
 *   recordings/nombre_streamer/archivo.mp3
 * El campo 'filename' devuelve la ruta relativa: "nombre_streamer/archivo.mp3"
 */
function listRecordings($username) {
    $recordingsDir = getRecordingsDir($username);
    error_log("RECORDINGS: Escaneando directorio: $recordingsDir");

    if (!is_dir($recordingsDir)) {
        error_log("RECORDINGS: ERROR - El directorio NO EXISTE: $recordingsDir");
        return [];
    }

    $audioExtensions = ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac'];
    $recordings = [];

    foreach (scandir($recordingsDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $entryPath = $recordingsDir . '/' . $entry;

        if (is_dir($entryPath)) {
            // Subcarpeta de streamer: escanear su contenido
            $streamer = $entry;
            foreach (scandir($entryPath) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($extension, $audioExtensions)) {
                    continue;
                }
                $filePath = $entryPath . '/' . $file;
                if (!is_file($filePath)) {
                    continue;
                }
                $fileTime = filemtime($filePath);
                $recordings[] = [
                    'filename' => $streamer . '/' . $file,  // ruta relativa para borrado
                    'streamer' => $streamer,
                    'size' => filesize($filePath),
                    'size_formatted' => formatBytes(filesize($filePath)),
                    'date' => date('Y-m-d H:i:s', $fileTime),
                    'timestamp' => $fileTime,
                    'days_old' => (int)floor((time() - $fileTime) / 86400),
                    'extension' => $extension
                ];
            }
        } else {
            // Archivos en la raíz (sin subcarpeta de streamer)
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $audioExtensions) || !is_file($entryPath)) {
                continue;
            }
            $fileTime = filemtime($entryPath);
            $recordings[] = [
                'filename' => $entry,
                'streamer' => '',
                'size' => filesize($entryPath),
                'size_formatted' => formatBytes(filesize($entryPath)),
                'date' => date('Y-m-d H:i:s', $fileTime),
                'timestamp' => $fileTime,
                'days_old' => (int)floor((time() - $fileTime) / 86400),
                'extension' => $extension
            ];
        }
    }

    usort($recordings, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

    error_log("RECORDINGS: Total grabaciones encontradas: " . count($recordings));
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
 * Eliminar una grabación específica.
 * $filename puede ser "archivo.mp3" o "streamer/archivo.mp3" (subcarpeta de streamer).
 */
function deleteRecording($username, $filename) {
    if (empty($filename) || strpos($filename, '..') !== false) {
        return ['success' => false, 'message' => 'Nombre de archivo inválido'];
    }

    $recordingsDir = getRecordingsDir($username);

    // Resolver ruta real y verificar que esté dentro del directorio de grabaciones
    $filePath = realpath($recordingsDir . '/' . $filename);
    $realRecordingsDir = realpath($recordingsDir);

    if ($filePath === false || $realRecordingsDir === false) {
        return ['success' => false, 'message' => 'El archivo no existe'];
    }

    // Prevenir path traversal: la ruta resuelta debe empezar por el directorio de grabaciones
    if (strpos($filePath, $realRecordingsDir . DIRECTORY_SEPARATOR) !== 0) {
        error_log("RECORDINGS: Intento de path traversal bloqueado: $filename");
        return ['success' => false, 'message' => 'Ruta de archivo no permitida'];
    }

    if (!is_file($filePath)) {
        return ['success' => false, 'message' => 'La ruta no es un archivo válido'];
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac'])) {
        return ['success' => false, 'message' => 'Solo se pueden eliminar archivos de audio'];
    }

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
