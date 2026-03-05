<?php
// includes/time_signals.php - Funciones para gestión de señales horarias

/**
 * Obtener directorio de señales horarias del usuario
 * Guardamos directamente en el directorio media de la emisora
 */
function getTimeSignalsDir($username) {
    $dir = "/var/azuracast/stations/{$username}/media/senales_horarias";

    // Crear directorio si no existe
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * Obtener ruta del archivo liquidsoap.liq de la emisora
 */
function getLiquidsoapFilePath($username) {
    return "/var/azuracast/stations/{$username}/config/liquidsoap.liq";
}

/**
 * Leer contenido del archivo liquidsoap.liq directamente
 */
function readLiquidsoapFile($username) {
    $filePath = getLiquidsoapFilePath($username);

    if (!file_exists($filePath)) {
        error_log("Liquidsoap file not found: $filePath");
        return false;
    }

    if (!is_readable($filePath)) {
        error_log("Liquidsoap file not readable: $filePath");
        return false;
    }

    $content = file_get_contents($filePath);

    if ($content === false) {
        error_log("Failed to read liquidsoap file: $filePath");
        return false;
    }

    return $content;
}

/**
 * Escribir contenido al archivo liquidsoap.liq directamente
 */
function writeLiquidsoapFile($username, $content) {
    $filePath = getLiquidsoapFilePath($username);
    $dirPath = dirname($filePath);

    if (!is_dir($dirPath)) {
        error_log("Liquidsoap config directory not found: $dirPath");
        return false;
    }

    if (!is_writable($dirPath)) {
        error_log("Liquidsoap config directory not writable: $dirPath");
        return false;
    }

    $result = file_put_contents($filePath, $content);

    if ($result === false) {
        error_log("Failed to write liquidsoap file: $filePath");
        return false;
    }

    return true;
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
        $modified = date('Y-m-d H:i:s', filemtime($path));

        $files[] = [
            'name' => $item,
            'path' => $path,
            'size' => $sizeFormatted,
            'size_bytes' => $size,
            'modified' => $modified,
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

    // Verificar que el directorio sea escribible
    if (!is_writable($dir)) {
        error_log("Directorio no escribible: $dir");
        return ['success' => false, 'message' => 'Directorio no tiene permisos de escritura'];
    }

    // Verificar que el archivo temporal exista
    if (!file_exists($file['tmp_name'])) {
        error_log("Archivo temporal no existe: " . $file['tmp_name']);
        return ['success' => false, 'message' => 'Archivo temporal no encontrado'];
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        chmod($destination, 0644);
        return [
            'success' => true,
            'filename' => $filename,
            'message' => 'Archivo subido correctamente'
        ];
    } else {
        $error = error_get_last();
        error_log("Error al mover archivo: " . json_encode($error));
        error_log("Destino: $destination");
        error_log("Permisos del directorio: " . substr(sprintf('%o', fileperms($dir)), -4));
        return ['success' => false, 'message' => 'Error al guardar el archivo en: ' . basename($destination)];
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
function generateLiquidsoapTimeSignals($audioPath, $days, $frequency, $duration = 1.5, $attenuation = 0.3) {
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

    // Verificar si es todos los días (formato simplificado)
    $allDays = count($activeDays) === 7;

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
    $code .= "señal_horaria = single(\"$audioPath\")\n";

    // Usar formato simplificado con predicate.once si es todos los días
    if ($allDays) {
        $code .= "horarias = switch(id=\"time_signal_switch\", [\n";
        foreach ($minuteConditions as $minute) {
            $code .= "  (predicate.once({ $minute }), señal_horaria)";
            if ($minute !== end($minuteConditions)) {
                $code .= ",";
            }
            $code .= "\n";
        }
        $code .= "])\n\n";
    } else {
        // Formato completo con días específicos
        $switchCases = [];
        foreach ($minuteConditions as $minute) {
            foreach ($activeDays as $dayNum) {
                $condition = sprintf("{%dw and %s0s}", $dayNum, $minute);
                $switchCases[] = "  ($condition, señal_horaria)";
            }
        }
        $code .= "horarias = switch(id=\"time_signal_switch\", [\n";
        $code .= implode(",\n", $switchCases) . "\n";
        $code .= "])\n\n";
    }

    // Convertir atenuación a porcentaje para el comentario
    $attenuationPercent = (int)($attenuation * 100);

    $code .= "# smooth_add mezcla suavemente sin cortar\n";
    $code .= "radio = smooth_add(\n";
    $code .= "  duration=$duration,      # Duración de la transición ($duration segundos)\n";
    $code .= "  p=$attenuation,             # Música baja al $attenuationPercent%\n";
    $code .= "  normal=radio,      # Fuente principal\n";
    $code .= "  special=horarias   # Señales horarias\n";
    $code .= ")\n";

    return $code;
}

/**
 * Parsear configuración de señales horarias desde Liquidsoap
 * Lee el liquidsoap.liq directamente del sistema de archivos
 * Soporta tanto formato SAPO como configuraciones manuales
 */
function parseTimeSignalsFromLiquidsoap($username) {
    // Leer archivo liquidsoap.liq directamente
    $liquidsoapContent = readLiquidsoapFile($username);

    if ($liquidsoapContent === false) {
        error_log("No se pudo leer liquidsoap.liq para usuario: $username");
        return null;
    }

    $config = [
        'signal_file' => null,
        'frequency' => 'hourly',
        'days' => []
    ];

    // 1. Detectar archivo de audio (soporta múltiples nombres de variables)
    // Buscar: señal_horaria = single("...") o time_signal_source = single("...")
    if (preg_match('/(?:señal_horaria|time_signal_source|signal)\s*=\s*single\(["\']([^"\']+)["\']\)/', $liquidsoapContent, $matches)) {
        $fullPath = $matches[1];
        // Extraer solo el nombre del archivo
        $filename = basename($fullPath);

        // Si el archivo está en senales_horarias.mp3 (sin carpeta), conservarlo así
        if (strpos($fullPath, '/senales_horarias/') !== false) {
            // Archivo en carpeta senales_horarias/
            $config['signal_file'] = $filename;
        } else if ($filename === 'senales_horarias.mp3') {
            // Archivo directo senales_horarias.mp3
            $config['signal_file'] = $filename;
        } else {
            $config['signal_file'] = $filename;
        }
    }

    // Si no se encuentra archivo, no hay señales horarias
    if (empty($config['signal_file'])) {
        return null;
    }

    // 2. Detectar formato de condiciones
    $minutes = [];
    $dayNums = [];
    $allDays = false;

    // Formato A: predicate.once({ 0m }) - todos los días
    if (preg_match_all('/predicate\.once\(\{\s*(\d+)m\s*\}\)/', $liquidsoapContent, $matches)) {
        $allDays = true;
        foreach ($matches[1] as $minute) {
            $minutes[] = (int)$minute;
        }
    }
    // Formato B: {1w and 0m0s} - días específicos
    else if (preg_match_all('/\{(\d+)w\s+and\s+(\d+)m0s\}/', $liquidsoapContent, $matches, PREG_SET_ORDER)) {
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
    }

    // 3. Determinar frecuencia basada en los minutos
    sort($minutes);
    if (count($minutes) >= 4) {
        $config['frequency'] = 'quarter-hourly';
    } else if (count($minutes) >= 2 && in_array(0, $minutes) && in_array(30, $minutes)) {
        $config['frequency'] = 'half-hourly';
    } else if (count($minutes) === 1 && $minutes[0] === 0) {
        $config['frequency'] = 'hourly';
    }

    // 4. Determinar días
    $dayMap = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo'
    ];

    if ($allDays) {
        // Si usa predicate.once, son todos los días
        $config['days'] = array_values($dayMap);
    } else {
        // Convertir números de días a nombres
        sort($dayNums);
        foreach ($dayNums as $num) {
            if (isset($dayMap[$num])) {
                $config['days'][] = $dayMap[$num];
            }
        }
    }

    // Si no hay días configurados, retornar null
    if (empty($config['days'])) {
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

    // PASO 1: Generar path para Liquidsoap
    // El archivo ya está en /var/azuracast/stations/{username}/media/senales_horarias/{filename}
    $liquidsoapPath = "/var/azuracast/stations/{$username}/media/senales_horarias/{$config['signal_file']}";

    // PASO 2: Generar código Liquidsoap
    $liquidsoapCode = generateLiquidsoapTimeSignals($liquidsoapPath, $days, $frequency);

    if (empty($liquidsoapCode)) {
        return ['success' => false, 'message' => 'Error al generar código Liquidsoap'];
    }

    // PASO 3: Leer archivo liquidsoap.liq actual
    $liquidsoapContent = readLiquidsoapFile($username);
    if ($liquidsoapContent === false) {
        return ['success' => false, 'message' => 'Error al leer archivo liquidsoap.liq'];
    }

    // PASO 4: Reemplazar o añadir código de señales horarias
    $marker_start = '# Señales Horarias - SAPO';

    // Buscar y eliminar código antiguo de señales horarias
    if (strpos($liquidsoapContent, $marker_start) !== false) {
        // Eliminar desde el marcador inicial hasta el cierre de smooth_add o fallback
        // Soporta tanto el formato antiguo (fallback) como el nuevo (smooth_add)
        $pattern = '/' . preg_quote($marker_start, '/') . '.*?(?:radio = fallback\(track_sensitive=false, \[time_signal, radio\]\)|radio = smooth_add\([^)]*\))\s*\n?/s';
        $liquidsoapContent = preg_replace($pattern, '', $liquidsoapContent);
    }

    // Añadir nuevo código al final
    $liquidsoapContent = trim($liquidsoapContent);
    if (!empty($liquidsoapContent)) {
        $liquidsoapContent .= "\n\n";
    }
    $liquidsoapContent .= $liquidsoapCode;

    // PASO 5: Escribir archivo liquidsoap.liq actualizado
    $writeResult = writeLiquidsoapFile($username, $liquidsoapContent);

    if (!$writeResult) {
        return [
            'success' => false,
            'message' => 'Error al escribir archivo liquidsoap.liq'
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
