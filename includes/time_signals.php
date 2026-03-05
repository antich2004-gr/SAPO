<?php
// includes/time_signals.php - Funciones para gestión de señales horarias

/**
 * Obtener directorio de señales horarias del usuario
 */
function getTimeSignalsDir($username) {
    $baseDir = __DIR__ . '/../user_data/time_signals';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    return $baseDir . '/' . $username;
}

/**
 * Listar archivos de señales horarias
 */
function listTimeSignals($username) {
    $dir = getTimeSignalsDir($username);

    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a'];

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . '/' . $item;
        if (!is_file($path)) continue;

        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) continue;

        $size = filesize($path);
        $sizeFormatted = formatFileSize($size);

        $files[] = [
            'name' => $item,
            'path' => $path,
            'size' => $sizeFormatted,
            'size_bytes' => $size,
            'extension' => $ext
        ];
    }

    // Ordenar por nombre
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $files;
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Subir archivo de señal horaria
 */
function uploadTimeSignal($username, $file) {
    $dir = getTimeSignalsDir($username);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Validar archivo
    $allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/x-m4a', 'audio/m4a'];
    $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No se recibió ningún archivo'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'El archivo excede el tamaño máximo de 10MB'];
    }

    $filename = basename($file['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Formato de archivo no permitido. Use: MP3, WAV, OGG, M4A'];
    }

    // Sanitizar nombre de archivo
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    $destination = $dir . '/' . $filename;

    // Si el archivo ya existe, agregar número
    $counter = 1;
    $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
    while (file_exists($destination)) {
        $filename = $nameWithoutExt . '_' . $counter . '.' . $ext;
        $destination = $dir . '/' . $filename;
        $counter++;
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        chmod($destination, 0644);
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Error al guardar el archivo'];
    }
}

/**
 * Eliminar archivo de señal horaria
 */
function deleteTimeSignal($username, $filename) {
    $dir = getTimeSignalsDir($username);

    // Sanitizar nombre de archivo
    $filename = basename($filename);
    $path = $dir . '/' . $filename;

    if (!file_exists($path)) {
        return ['success' => false, 'message' => 'El archivo no existe'];
    }

    if (!is_file($path)) {
        return ['success' => false, 'message' => 'Ruta inválida'];
    }

    if (unlink($path)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Error al eliminar el archivo'];
    }
}

/**
 * Obtener ruta del archivo de configuración
 */
function getTimeSignalsConfigPath($username) {
    $dir = getTimeSignalsDir($username);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/config.json';
}

/**
 * Cargar configuración de señales horarias
 */
function getTimeSignalsConfig($username) {
    $configPath = getTimeSignalsConfigPath($username);

    if (!file_exists($configPath)) {
        return [
            'signal_file' => '',
            'frequency' => 'hourly',
            'days' => []
        ];
    }

    $json = file_get_contents($configPath);
    $config = json_decode($json, true);

    if (!is_array($config)) {
        return [
            'signal_file' => '',
            'frequency' => 'hourly',
            'days' => []
        ];
    }

    return $config;
}

/**
 * Guardar configuración de señales horarias
 */
function saveTimeSignalsConfig($username, $config) {
    $configPath = getTimeSignalsConfigPath($username);

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($configPath, $json) === false) {
        return ['success' => false, 'message' => 'Error al guardar configuración'];
    }

    return ['success' => true];
}

/**
 * Subir archivo a AzuraCast
 */
function uploadFileToAzuraCast($username, $filePath, $destinationPath = '') {
    $globalConfig = getConfig();
    $apiUrl = $globalConfig['azuracast_api_url'] ?? '';
    $apiKey = $globalConfig['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'message' => 'API de AzuraCast no configurada'];
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return ['success' => false, 'message' => 'Station ID no configurado'];
    }

    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'Archivo no encontrado'];
    }

    // Preparar destino (carpeta en AzuraCast)
    $filename = basename($filePath);
    if (!empty($destinationPath)) {
        $destination = trim($destinationPath, '/') . '/' . $filename;
    } else {
        $destination = 'senales_horarias/' . $filename;
    }

    // URL de la API para subir archivos
    $uploadUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/files';

    try {
        // Leer contenido del archivo
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Error al leer el archivo'];
        }

        // Construir multipart/form-data manualmente
        $boundary = '----WebKitFormBoundary' . uniqid();
        $eol = "\r\n";

        $body = '';
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . $eol;
        $body .= 'Content-Type: application/octet-stream' . $eol . $eol;
        $body .= $fileContent . $eol;
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="path"' . $eol . $eol;
        $body .= $destination . $eol;
        $body .= '--' . $boundary . '--' . $eol;

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'X-API-Key: ' . $apiKey,
                    'Content-Type: multipart/form-data; boundary=' . $boundary,
                    'Content-Length: ' . strlen($body)
                ],
                'content' => $body,
                'timeout' => 30,
                'user_agent' => 'SAPO/1.0'
            ]
        ]);

        $response = @file_get_contents($uploadUrl, false, $context);

        if ($response === false) {
            return ['success' => false, 'message' => 'Error al subir archivo a AzuraCast'];
        }

        $data = json_decode($response, true);

        if (isset($data['success']) && $data['success']) {
            return ['success' => true, 'path' => $destination];
        } else {
            $errorMsg = $data['message'] ?? 'Error desconocido';
            return ['success' => false, 'message' => 'AzuraCast: ' . $errorMsg];
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Excepción: ' . $e->getMessage()];
    }
}

/**
 * Buscar playlist de señales horarias existente
 */
function findTimeSignalsPlaylist($username) {
    $playlists = getAzuracastPlaylists($username, 0); // Sin caché

    if ($playlists === false) {
        return null;
    }

    // Buscar playlist con nombre específico
    foreach ($playlists as $playlist) {
        $name = $playlist['name'] ?? '';
        if (stripos($name, 'Señales Horarias') !== false ||
            stripos($name, 'Time Signals') !== false ||
            stripos($name, 'SAPO - Señales') !== false) {
            return $playlist;
        }
    }

    return null;
}

/**
 * Crear o actualizar playlist de señales horarias
 */
function createOrUpdateTimeSignalsPlaylist($username, $audioPath, $days, $frequency) {
    $globalConfig = getConfig();
    $apiUrl = $globalConfig['azuracast_api_url'] ?? '';
    $apiKey = $globalConfig['azuracast_api_key'] ?? '';

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId) || empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'message' => 'Configuración incompleta'];
    }

    // Buscar playlist existente
    $existingPlaylist = findTimeSignalsPlaylist($username);

    // Mapeo de días español -> AzuraCast (0=domingo, 1=lunes, ..., 6=sábado)
    $dayMap = [
        'lunes' => 1,
        'martes' => 2,
        'miercoles' => 3,
        'jueves' => 4,
        'viernes' => 5,
        'sabado' => 6,
        'domingo' => 0
    ];

    // Convertir días seleccionados
    $activeDays = [];
    foreach ($days as $day) {
        if (isset($dayMap[$day])) {
            $activeDays[] = $dayMap[$day];
        }
    }

    if (empty($activeDays)) {
        return ['success' => false, 'message' => 'Debe seleccionar al menos un día'];
    }

    // Generar horarios según frecuencia
    $scheduleRules = [];
    $minutes = [];

    switch ($frequency) {
        case 'hourly':
            $minutes = [0]; // Solo en punto
            break;
        case 'half-hourly':
            $minutes = [0, 30]; // Cada media hora
            break;
        case 'quarter-hourly':
            $minutes = [0, 15, 30, 45]; // Cada 15 minutos
            break;
        default:
            $minutes = [0];
    }

    // Crear reglas para cada minuto y cada día
    foreach ($minutes as $minute) {
        $scheduleRules[] = [
            'start_time' => sprintf('%02d:%02d:00', 0, $minute),  // Desde las 00:XX
            'end_time' => '23:59:59',  // Hasta las 23:59
            'start_date' => null,
            'end_date' => null,
            'days' => $activeDays,
            'loop_once_per_hour' => true  // Importante: solo una vez por hora en el minuto especificado
        ];
    }

    $playlistData = [
        'name' => 'SAPO - Señales Horarias',
        'type' => 'scheduled', // Playlist programada
        'source' => 'songs',
        'order' => 'sequential',
        'include_in_automation' => true,
        'is_enabled' => true,
        'weight' => 10,
        'schedule_items' => $scheduleRules,
        'avoid_duplicates' => true
    ];

    if ($existingPlaylist) {
        // Actualizar playlist existente
        $playlistId = $existingPlaylist['id'];
        $updateUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/playlist/' . $playlistId;

        $result = makeAzuraCastRequest($updateUrl, 'PUT', $playlistData, $apiKey);

        if (!$result['success']) {
            return ['success' => false, 'message' => 'Error al actualizar playlist: ' . $result['message']];
        }

        return [
            'success' => true,
            'message' => 'Playlist de señales horarias actualizada',
            'playlist_id' => $playlistId,
            'updated' => true
        ];

    } else {
        // Crear nueva playlist
        $createUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/playlists';

        $result = makeAzuraCastRequest($createUrl, 'POST', $playlistData, $apiKey);

        if (!$result['success']) {
            return ['success' => false, 'message' => 'Error al crear playlist: ' . $result['message']];
        }

        $responseData = $result['data'] ?? [];
        $playlistId = $responseData['id'] ?? null;

        return [
            'success' => true,
            'message' => 'Playlist de señales horarias creada',
            'playlist_id' => $playlistId,
            'updated' => false
        ];
    }
}

/**
 * Hacer petición a la API de AzuraCast
 */
function makeAzuraCastRequest($url, $method = 'GET', $data = null, $apiKey = null) {
    $headers = ['User-Agent: SAPO/1.0'];

    if ($apiKey) {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 20,
            'ignore_errors' => true
        ]
    ];

    if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $jsonData = json_encode($data);
        $options['http']['header'] .= "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($jsonData);
        $options['http']['content'] = $jsonData;
    }

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['success' => false, 'message' => 'Error en la petición HTTP'];
    }

    $responseData = json_decode($response, true);

    // Verificar código de respuesta HTTP
    if (isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $statusCode = isset($matches[1]) ? (int)$matches[1] : 200;

        if ($statusCode >= 200 && $statusCode < 300) {
            return ['success' => true, 'data' => $responseData];
        } else {
            $errorMsg = $responseData['message'] ?? 'HTTP ' . $statusCode;
            return ['success' => false, 'message' => $errorMsg];
        }
    }

    return ['success' => true, 'data' => $responseData];
}

/**
 * Aplicar configuración de señales horarias a AzuraCast
 */
function applyTimeSignalsToAzuraCast($username) {
    $config = getTimeSignalsConfig($username);

    if (empty($config['signal_file']) || empty($config['days'])) {
        return ['success' => false, 'message' => 'Configuración incompleta'];
    }

    // Obtener configuración global de AzuraCast
    $globalConfig = getConfig();
    $apiUrl = $globalConfig['azuracast_api_url'] ?? '';
    $apiKey = $globalConfig['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'message' => 'API de AzuraCast no configurada'];
    }

    // Obtener station_id del usuario
    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return ['success' => false, 'message' => 'Station ID no configurado'];
    }

    $audioPath = getTimeSignalsDir($username) . '/' . $config['signal_file'];
    if (!file_exists($audioPath)) {
        return ['success' => false, 'message' => 'Archivo de audio no encontrado'];
    }

    $frequency = $config['frequency'] ?? 'hourly';
    $days = $config['days'] ?? [];

    // PASO 1: Subir archivo a AzuraCast
    $uploadResult = uploadFileToAzuraCast($username, $audioPath, 'senales_horarias');

    if (!$uploadResult['success']) {
        return [
            'success' => false,
            'message' => 'Error al subir archivo: ' . $uploadResult['message']
        ];
    }

    $azuracastPath = $uploadResult['path'];

    // PASO 2: Crear o actualizar playlist programada
    $playlistResult = createOrUpdateTimeSignalsPlaylist($username, $azuracastPath, $days, $frequency);

    if (!$playlistResult['success']) {
        return [
            'success' => false,
            'message' => $playlistResult['message']
        ];
    }

    // PASO 3: Mensaje de éxito
    $message = $playlistResult['updated']
        ? 'Configuración actualizada en AzuraCast. La playlist existente "SAPO - Señales Horarias" ha sido modificada con los nuevos horarios.'
        : 'Configuración aplicada a AzuraCast. Se creó la playlist "SAPO - Señales Horarias" con los horarios especificados.';

    return [
        'success' => true,
        'message' => $message,
        'playlist_id' => $playlistResult['playlist_id'],
        'updated' => $playlistResult['updated']
    ];
}

/**
 * Procesar formulario de configuración
 */
function processTimeSignalsForm($username, $postData) {
    $signalFile = $postData['signal_file'] ?? '';
    $days = $postData['days'] ?? [];
    $frequency = $postData['frequency'] ?? 'hourly';

    if (empty($signalFile)) {
        return ['success' => false, 'message' => 'Debe seleccionar un archivo de señal horaria'];
    }

    if (empty($days)) {
        return ['success' => false, 'message' => 'Debe seleccionar al menos un día'];
    }

    // Validar que el archivo existe
    $audioPath = getTimeSignalsDir($username) . '/' . $signalFile;
    if (!file_exists($audioPath)) {
        return ['success' => false, 'message' => 'El archivo seleccionado no existe'];
    }

    // Validar frecuencia
    $validFrequencies = ['hourly', 'half-hourly', 'quarter-hourly'];
    if (!in_array($frequency, $validFrequencies)) {
        $frequency = 'hourly';
    }

    // Validar días
    $validDays = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $days = array_filter($days, function($day) use ($validDays) {
        return in_array($day, $validDays);
    });

    if (empty($days)) {
        return ['success' => false, 'message' => 'Debe seleccionar al menos un día válido'];
    }

    $config = [
        'signal_file' => $signalFile,
        'frequency' => $frequency,
        'days' => array_values($days)
    ];

    $result = saveTimeSignalsConfig($username, $config);

    if ($result['success']) {
        // Intentar aplicar a AzuraCast
        $applyResult = applyTimeSignalsToAzuraCast($username);

        // No fallar si la aplicación falla, solo advertir
        if (!$applyResult['success']) {
            error_log("Advertencia: No se pudo aplicar a AzuraCast: " . $applyResult['message']);
        }
    }

    return $result;
}
