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
 * Intenta sincronizar desde Liquidsoap si no hay config.json
 */
function getTimeSignalsConfig($username) {
    $configPath = getTimeSignalsConfigPath($username);

    // Si no existe config.json, intentar sincronizar desde Liquidsoap
    if (!file_exists($configPath)) {
        $synced = syncTimeSignalsFromLiquidsoap($username);
        if ($synced) {
            // Leer la configuración sincronizada
            if (file_exists($configPath)) {
                $json = file_get_contents($configPath);
                $config = json_decode($json, true);
                if (is_array($config)) {
                    return $config;
                }
            }
        }

        // Si no se pudo sincronizar, retornar configuración vacía
        return [
            'signal_file' => '',
            'frequency' => 'hourly',
            'days' => []
        ];
    }

    $json = file_get_contents($configPath);
    $config = json_decode($json, true);

    if (!is_array($config)) {
        // Si el JSON está corrupto, intentar sincronizar desde Liquidsoap
        $synced = syncTimeSignalsFromLiquidsoap($username);
        if ($synced) {
            $json = file_get_contents($configPath);
            $config = json_decode($json, true);
            if (is_array($config)) {
                return $config;
            }
        }

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
 * Generar código Liquidsoap para señales horarias
 */
function generateLiquidsoapTimeSignals($audioPath, $days, $frequency) {
    // Mapeo de días español -> liquidsoap (1=lunes, 2=martes, ..., 7=domingo)
    $dayMap = [
        'lunes' => 1,
        'martes' => 2,
        'miercoles' => 3,
        'jueves' => 4,
        'viernes' => 5,
        'sabado' => 6,
        'domingo' => 7
    ];

    // Convertir días seleccionados a números
    $activeDays = [];
    foreach ($days as $day) {
        if (isset($dayMap[$day])) {
            $activeDays[] = $dayMap[$day];
        }
    }

    if (empty($activeDays)) {
        return '';
    }

    // Generar condiciones de minutos según frecuencia
    $minuteConditions = [];
    switch ($frequency) {
        case 'hourly':
            $minuteConditions[] = '0m';
            break;
        case 'half-hourly':
            $minuteConditions[] = '0m';
            $minuteConditions[] = '30m';
            break;
        case 'quarter-hourly':
            $minuteConditions[] = '0m';
            $minuteConditions[] = '15m';
            $minuteConditions[] = '30m';
            $minuteConditions[] = '45m';
            break;
        default:
            $minuteConditions[] = '0m';
    }

    // Generar código liquidsoap
    $code = "# Señales Horarias - SAPO\n";
    $code .= "time_signal_source = single(\"$audioPath\")\n\n";

    // Combinar minutos y días - cada condición con su fuente
    $switchCases = [];
    foreach ($minuteConditions as $minute) {
        foreach ($activeDays as $dayNum) {
            // Formato: ({1w and 0m0s}, time_signal_source) = lunes a las :00
            $condition = sprintf("{%dw and %s0s}", $dayNum, $minute);
            $switchCases[] = "  ($condition, time_signal_source)";
        }
    }

    $code .= "time_signal = switch(id=\"time_signal_switch\", [\n";
    $code .= implode(",\n", $switchCases) . "\n";
    $code .= "])\n\n";

    $code .= "# Integrar señales horarias en la radio con mezcla suave\n";
    $code .= "radio = smooth_add(\n";
    $code .= "  duration=1.5,      # Duración de la transición (1.5 segundos)\n";
    $code .= "  p=0.3,             # Música baja al 30%\n";
    $code .= "  normal=radio,      # Fuente principal\n";
    $code .= "  special=time_signal   # Señales horarias\n";
    $code .= ")\n";

    return $code;
}

/**
 * Parsear configuración de señales horarias desde Liquidsoap
 * Lee el liquidsoap.liq y extrae la configuración actual
 */
function parseTimeSignalsFromLiquidsoap($username) {
    // Obtener configuración actual de Liquidsoap
    $currentConfig = getAzuracastLiquidsoapConfig($username);
    if ($currentConfig === false) {
        return null;
    }

    // Buscar custom_config
    $customConfig = '';
    foreach ($currentConfig as $field) {
        if ($field['field'] === 'custom_config') {
            $customConfig = $field['value'];
            break;
        }
    }

    if (empty($customConfig)) {
        return null;
    }

    // Buscar el marcador de señales horarias
    $marker = '# Señales Horarias - SAPO';
    if (strpos($customConfig, $marker) === false) {
        return null; // No hay señales horarias configuradas
    }

    $config = [
        'signal_file' => null,
        'frequency' => 'hourly',
        'days' => []
    ];

    // 1. Extraer archivo de audio
    if (preg_match('/time_signal_source\s*=\s*single\("([^"]+)"\)/', $customConfig, $matches)) {
        $fullPath = $matches[1];
        // Extraer solo el nombre del archivo
        $config['signal_file'] = basename($fullPath);
    }

    // 2. Extraer minutos y días de las condiciones
    // Patrón: ({1w and 0m0s}, time_signal_source)
    preg_match_all('/\{(\d+)w\s+and\s+(\d+)m0s\}/', $customConfig, $matches, PREG_SET_ORDER);

    $minutes = [];
    $dayNums = [];

    foreach ($matches as $match) {
        $dayNum = (int)$match[1];
        $minute = (int)$match[2];

        if (!in_array($dayNum, $dayNums)) {
            $dayNums[] = $dayNum;
        }
        if (!in_array($minute, $minutes)) {
            $minutes[] = $minute;
        }
    }

    // 3. Determinar frecuencia basada en los minutos
    sort($minutes);
    if (count($minutes) >= 2 && in_array(0, $minutes) && in_array(30, $minutes)) {
        $config['frequency'] = 'half-hourly';
    } elseif (count($minutes) === 1 && $minutes[0] === 0) {
        $config['frequency'] = 'hourly';
    }

    // 4. Convertir números de días a nombres
    $dayMap = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo'
    ];

    sort($dayNums);
    foreach ($dayNums as $num) {
        if (isset($dayMap[$num])) {
            $config['days'][] = $dayMap[$num];
        }
    }

    // Si no se pudo extraer información válida, retornar null
    if (empty($config['signal_file']) || empty($config['days'])) {
        return null;
    }

    return $config;
}

/**
 * Sincronizar configuración desde Liquidsoap al config.json
 * Lee el liquidsoap.liq y actualiza el config.json con la configuración real
 */
function syncTimeSignalsFromLiquidsoap($username) {
    $parsedConfig = parseTimeSignalsFromLiquidsoap($username);

    if ($parsedConfig === null) {
        return false; // No hay nada que sincronizar
    }

    // Guardar la configuración parseada
    $result = saveTimeSignalsConfig($username, $parsedConfig);

    return $result['success'];
}

/**
 * Aplicar configuración de señales horarias a AzuraCast via Liquidsoap
 */
function applyTimeSignalsToAzuraCast($username) {
    $config = getTimeSignalsConfig($username);

    if (empty($config['signal_file']) || empty($config['days'])) {
        return ['success' => false, 'message' => 'Configuración incompleta'];
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

    // PASO 2: Generar código Liquidsoap
    $liquidsoapCode = generateLiquidsoapTimeSignals($azuracastPath, $days, $frequency);

    if (empty($liquidsoapCode)) {
        return ['success' => false, 'message' => 'Error al generar código Liquidsoap'];
    }

    // PASO 3: Obtener configuración actual de Liquidsoap
    $currentConfig = getAzuracastLiquidsoapConfig($username);
    if ($currentConfig === false) {
        return ['success' => false, 'message' => 'Error al obtener configuración de Liquidsoap'];
    }

    // Buscar el campo custom_config
    $customConfig = '';
    foreach ($currentConfig as $field) {
        if ($field['field'] === 'custom_config') {
            $customConfig = $field['value'];
            break;
        }
    }

    // PASO 4: Reemplazar o añadir código de señales horarias
    $marker_start = '# Señales Horarias - SAPO';

    // Buscar y eliminar código antiguo de señales horarias
    if (strpos($customConfig, $marker_start) !== false) {
        // Eliminar desde el marcador inicial hasta el cierre de smooth_add o fallback
        // Soporta tanto el formato antiguo (fallback) como el nuevo (smooth_add)
        $pattern = '/' . preg_quote($marker_start, '/') . '.*?(?:radio = fallback\(track_sensitive=false, \[time_signal, radio\]\)|radio = smooth_add\([^)]*\))\s*\n?/s';
        $customConfig = preg_replace($pattern, '', $customConfig);
    }

    // Añadir nuevo código al final
    $customConfig = trim($customConfig);
    if (!empty($customConfig)) {
        $customConfig .= "\n\n";
    }
    $customConfig .= $liquidsoapCode;

    // PASO 5: Actualizar configuración en AzuraCast
    $updateResult = updateAzuracastLiquidsoapConfig($username, [
        'custom_config' => $customConfig
    ]);

    if (!$updateResult['success']) {
        return [
            'success' => false,
            'message' => 'Error al actualizar Liquidsoap: ' . $updateResult['message']
        ];
    }

    return [
        'success' => true,
        'message' => 'Señales horarias aplicadas correctamente al Liquidsoap'
    ];
}

/**
 * Procesar formulario de configuración (simplificado)
 */
function processTimeSignalsForm($username, $postData) {
    $frequency = $postData['frequency'] ?? 'hourly';

    // Obtener el último archivo subido automáticamente
    $files = listTimeSignals($username);

    if (empty($files)) {
        return ['success' => false, 'message' => 'Debe subir al menos un archivo de señal horaria'];
    }

    // Usar el primer archivo (o el único que haya)
    $signalFile = $files[0]['name'];

    // Validar que el archivo existe
    $audioPath = getTimeSignalsDir($username) . '/' . $signalFile;
    if (!file_exists($audioPath)) {
        return ['success' => false, 'message' => 'El archivo de audio no existe'];
    }

    // Validar frecuencia (solo hourly o half-hourly)
    $validFrequencies = ['hourly', 'half-hourly'];
    if (!in_array($frequency, $validFrequencies)) {
        $frequency = 'hourly';
    }

    // Siempre usar todos los días
    $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    $config = [
        'signal_file' => $signalFile,
        'frequency' => $frequency,
        'days' => $days
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
