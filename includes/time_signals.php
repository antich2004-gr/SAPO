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

    error_log("WRITE DEBUG - Usuario: $username");
    error_log("WRITE DEBUG - Ruta de archivo: $filePath");
    error_log("WRITE DEBUG - Directorio: $dirPath");
    error_log("WRITE DEBUG - Longitud de contenido: " . strlen($content) . " bytes");

    if (!is_dir($dirPath)) {
        error_log("WRITE DEBUG - ERROR: Directorio no existe: $dirPath");
        return false;
    }

    error_log("WRITE DEBUG - Directorio existe: SÍ");
    error_log("WRITE DEBUG - Directorio es escribible: " . (is_writable($dirPath) ? 'SÍ' : 'NO'));
    error_log("WRITE DEBUG - Archivo existe: " . (file_exists($filePath) ? 'SÍ' : 'NO'));
    if (file_exists($filePath)) {
        error_log("WRITE DEBUG - Archivo es escribible: " . (is_writable($filePath) ? 'SÍ' : 'NO'));
    }

    if (!is_writable($dirPath)) {
        error_log("WRITE DEBUG - ERROR: Directorio no escribible: $dirPath");
        return false;
    }

    error_log("WRITE DEBUG - Intentando escribir contenido...");
    error_log("WRITE DEBUG - Primeros 500 caracteres a escribir: " . substr($content, -500));

    $result = file_put_contents($filePath, $content);

    if ($result === false) {
        error_log("WRITE DEBUG - ERROR: file_put_contents falló");
        error_log("WRITE DEBUG - Error PHP: " . error_get_last()['message'] ?? 'desconocido');
        return false;
    }

    error_log("WRITE DEBUG - ¡Escritura exitosa! Bytes escritos: $result");
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

    // ELIMINAR TODOS LOS ARCHIVOS EXISTENTES (solo permitir uno)
    $existingFiles = listTimeSignals($username);
    foreach ($existingFiles as $existingFile) {
        $existingPath = $dir . '/' . $existingFile['name'];
        if (file_exists($existingPath)) {
            @unlink($existingPath);
            error_log("Archivo anterior eliminado: " . $existingFile['name']);
        }
    }

    $destination = $dir . '/' . $filename;

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
        case 'every-5-min':
            // Cada 5 minutos: 0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55
            for ($i = 0; $i < 60; $i += 5) {
                $minuteConditions[] = $i . 'm';
            }
            break;
        default:
            $minuteConditions[] = '0m';
    }

    // Generar código liquidsoap con sintaxis de Liquidsoap 2.x
    $code = "# Señales Horarias - SAPO\n";
    $code .= "# Crear fuente de señal con logging\n";
    $code .= "señal_base = single(\"$audioPath\")\n";
    $code .= "señal_horaria = map_metadata(fun (m) -> begin\n";
    $code .= "  log(\"SEÑAL HORARIA DISPARADA: #{time()}\")\n";
    $code .= "  m\n";
    $code .= "end, señal_base)\n\n";

    // Generar predicados de tiempo con ventanas precisas
    if ($allDays) {
        // Definir predicados para cada momento
        $predicates = [];
        foreach ($minuteConditions as $idx => $minute) {
            $predName = "time_pred_" . str_replace('m', '', $minute);
            // Usar sintaxis de bloque de tiempo: { 0m0s }, { 30m0s }, etc.
            $timeSpec = "{ " . $minute . "0s }";
            $code .= "# Predicado para minuto $minute (disparo exacto en segundo 0)\n";
            $code .= "$predName = $timeSpec\n";
            $predicates[] = "($predName, señal_horaria)";
        }
        $code .= "\n";
        $code .= "horarias = switch(id=\"time_signal_switch\", track_sensitive=false, [\n";
        $code .= "  " . implode(",\n  ", $predicates) . "\n";
        $code .= "])\n\n";
    } else {
        // Formato completo con días específicos
        $predicates = [];
        $predIdx = 0;
        foreach ($minuteConditions as $minute) {
            foreach ($activeDays as $dayNum) {
                $predName = "time_pred_" . $predIdx;
                $timeSpec = sprintf("{ %dw and %s0s }", $dayNum, $minute);
                $code .= "# Predicado para día $dayNum, minuto $minute (disparo exacto en segundo 0)\n";
                $code .= "$predName = $timeSpec\n";
                $predicates[] = "($predName, señal_horaria)";
                $predIdx++;
            }
        }
        $code .= "\n";
        $code .= "horarias = switch(id=\"time_signal_switch\", track_sensitive=false, [\n";
        $code .= "  " . implode(",\n  ", $predicates) . "\n";
        $code .= "])\n\n";
    }

    // Convertir atenuación a porcentaje para el comentario
    $attenuationPercent = (int)($attenuation * 100);
    $musicVolume = 1.0 - $attenuation; // Volumen de música durante señal

    $code .= "# Fallback con prioridad absoluta: cuando hay señal horaria, se reproduce de inmediato\n";
    $code .= "# Atenuar música durante señal\n";
    $code .= "radio_atenuado = amplify($musicVolume, radio)\n";
    $code .= "horarias_con_fondo = add(normalize=false, [horarias, radio_atenuado])\n\n";

    $code .= "# Prioridad: si hay señal horaria, se reproduce inmediatamente sobre música atenuada\n";
    $code .= "# track_sensitive=false hace el cambio inmediato sin esperar fin de pista\n";
    $code .= "radio = fallback(\n";
    $code .= "  track_sensitive=false,\n";
    $code .= "  [horarias_con_fondo, radio]\n";
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

    // DEBUG: Log del contenido parcial para verificar qué estamos leyendo
    error_log("PARSE DEBUG - Usuario: $username");
    error_log("PARSE DEBUG - Primeros 500 caracteres: " . substr($liquidsoapContent, 0, 500));

    $config = [
        'signal_file' => null,
        'frequency' => 'hourly',
        'days' => []
    ];

    // 1. Detectar archivo de audio (soporta múltiples nombres de variables)
    // Buscar: señal_horaria = single("...") o señal_horaria = single(/...)
    // Soporta RUTAS CON Y SIN COMILLAS
    $audioPattern = '/(?:señal_horaria|time_signal_source|signal)\s*=\s*single\(["\']?([^"\')\s]+)["\']?\)/';
    error_log("PARSE DEBUG - Patrón de búsqueda de audio: $audioPattern");

    if (preg_match($audioPattern, $liquidsoapContent, $matches)) {
        error_log("PARSE DEBUG - ¡Coincidencia encontrada! Matches: " . json_encode($matches));
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
        error_log("PARSE DEBUG - signal_file detectado: " . $config['signal_file']);
    } else {
        error_log("PARSE DEBUG - NO se encontró coincidencia de archivo de audio");
        // Intentar detectar CUALQUIER single() en el contenido (con y sin comillas)
        if (preg_match_all('/single\(["\']?([^"\')\s]+)["\']?\)/', $liquidsoapContent, $allMatches)) {
            error_log("PARSE DEBUG - Se encontraron estos single(): " . json_encode($allMatches[1]));
        }
    }

    // Si no se encuentra archivo, no hay señales horarias
    if (empty($config['signal_file'])) {
        error_log("PARSE DEBUG - Config incompleto, signal_file vacío. Retornando null.");
        return null;
    }

    // 2. Detectar formato de condiciones
    $minutes = [];
    $dayNums = [];
    $allDays = false;

    // Formato A1: predicate.once(time.predicate("0m0s-0m5s")) - nuevo formato con ventanas
    error_log("PARSE DEBUG - Buscando predicate.once con ventanas...");
    if (preg_match_all('/predicate\.once\(time\.predicate\("(\d+)m\d+s-\d+m\d+s"\)\)/', $liquidsoapContent, $matches)) {
        error_log("PARSE DEBUG - ¡Encontrado predicate.once con ventanas! Minutos: " . json_encode($matches[1]));
        $allDays = true;
        foreach ($matches[1] as $minute) {
            $minutes[] = (int)$minute;
        }
    }
    // Formato A2: predicate.once({ 0m }) - formato antiguo
    else if (preg_match_all('/predicate\.once\(\{\s*(\d+)m\s*\}\)/', $liquidsoapContent, $matches)) {
        error_log("PARSE DEBUG - ¡Encontrado predicate.once antiguo! Minutos: " . json_encode($matches[1]));
        $allDays = true;
        foreach ($matches[1] as $minute) {
            $minutes[] = (int)$minute;
        }
    }
    // Formato B1: predicate.once(time.predicate("1w and (0m0s-0m5s)")) - días específicos con ventanas
    else if (preg_match_all('/predicate\.once\(time\.predicate\("(\d+)w\s+and\s+\((\d+)m\d+s-\d+m\d+s\)"\)\)/', $liquidsoapContent, $matches, PREG_SET_ORDER)) {
        error_log("PARSE DEBUG - Encontrado formato día+minuto con ventanas: " . json_encode($matches));
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
    // Formato B2: {1w and 0m0s} - días específicos antiguo
    else if (preg_match_all('/\{(\d+)w\s+and\s+(\d+)m0s\}/', $liquidsoapContent, $matches, PREG_SET_ORDER)) {
        error_log("PARSE DEBUG - Encontrado formato día+minuto específico antiguo: " . json_encode($matches));
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
    } else {
        error_log("PARSE DEBUG - NO se encontró ninguna condición de tiempo");
    }

    // 3. Determinar frecuencia basada en los minutos
    error_log("PARSE DEBUG - Minutos detectados: " . json_encode($minutes));
    error_log("PARSE DEBUG - Días detectados: " . json_encode($dayNums));
    error_log("PARSE DEBUG - Todos los días: " . ($allDays ? 'SÍ' : 'NO'));
    sort($minutes);
    if (count($minutes) >= 12) {
        // Cada 5 minutos (12 veces por hora)
        $config['frequency'] = 'every-5-min';
    } else if (count($minutes) >= 4) {
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
        error_log("PARSE DEBUG - Config incompleto, sin días configurados. Retornando null.");
        return null;
    }

    error_log("PARSE DEBUG - ¡Configuración parseada exitosamente!: " . json_encode($config));
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
    error_log("APPLY DEBUG - Iniciando aplicación para usuario: $username");

    $config = getTimeSignalsConfig($username);
    error_log("APPLY DEBUG - Configuración obtenida: " . json_encode($config));

    if (empty($config['signal_file']) || empty($config['days'])) {
        error_log("APPLY DEBUG - ERROR: Configuración incompleta");
        return ['success' => false, 'message' => 'Configuración incompleta'];
    }

    $audioPath = getTimeSignalsDir($username) . '/' . $config['signal_file'];
    error_log("APPLY DEBUG - Verificando archivo de audio: $audioPath");

    if (!file_exists($audioPath)) {
        error_log("APPLY DEBUG - ERROR: Archivo de audio no encontrado");
        return ['success' => false, 'message' => 'Archivo de audio no encontrado'];
    }

    error_log("APPLY DEBUG - Archivo de audio existe: SÍ");
    $frequency = $config['frequency'] ?? 'hourly';
    $days = $config['days'] ?? [];
    $duration = floatval($config['duration'] ?? 1.5);
    $attenuationPercent = intval($config['attenuation'] ?? 30);
    $attenuation = $attenuationPercent / 100; // Convertir porcentaje a decimal

    // PASO 1: Generar path para Liquidsoap
    // El archivo ya está en /var/azuracast/stations/{username}/media/senales_horarias/{filename}
    $liquidsoapPath = "/var/azuracast/stations/{$username}/media/senales_horarias/{$config['signal_file']}";
    error_log("APPLY DEBUG - Path Liquidsoap: $liquidsoapPath");
    error_log("APPLY DEBUG - Frecuencia: $frequency");
    error_log("APPLY DEBUG - Días: " . json_encode($days));
    error_log("APPLY DEBUG - Duración: $duration");
    error_log("APPLY DEBUG - Atenuación: $attenuationPercent%");

    // PASO 2: Generar código Liquidsoap con valores personalizados
    error_log("APPLY DEBUG - Generando código Liquidsoap...");
    $musicVolume = 1.0 - $attenuation; # Convertir atenuación a volumen
    $offsetSeconds = -40; # Compensar delay de 50s (adelantar 40s)
    $liquidsoapCode = generateTimeSignalsSmooth($liquidsoapPath, $days, $frequency, $musicVolume, $duration, $offsetSeconds);

    if (empty($liquidsoapCode)) {
        error_log("APPLY DEBUG - ERROR: Código Liquidsoap vacío");
        return ['success' => false, 'message' => 'Error al generar código Liquidsoap'];
    }

    error_log("APPLY DEBUG - Código Liquidsoap generado (" . strlen($liquidsoapCode) . " bytes)");
    error_log("APPLY DEBUG - Código generado (primeros 500 chars): " . substr($liquidsoapCode, 0, 500));

    // PASO 3: Leer archivo liquidsoap.liq actual
    error_log("APPLY DEBUG - Leyendo archivo liquidsoap.liq...");
    $liquidsoapContent = readLiquidsoapFile($username);
    if ($liquidsoapContent === false) {
        error_log("APPLY DEBUG - ERROR: No se pudo leer liquidsoap.liq");
        return ['success' => false, 'message' => 'Error al leer archivo liquidsoap.liq'];
    }

    error_log("APPLY DEBUG - Archivo leído correctamente (" . strlen($liquidsoapContent) . " bytes)");

    // PASO 4: Reemplazar o añadir código de señales horarias
    $marker_start = '# Señales Horarias - SAPO';

    // Buscar y eliminar código antiguo de señales horarias
    error_log("APPLY DEBUG - Buscando código antiguo de señales horarias...");
    if (strpos($liquidsoapContent, $marker_start) !== false) {
        error_log("APPLY DEBUG - Encontrado código antiguo, eliminando...");
        // Eliminar desde el marcador inicial hasta el cierre de smooth_add, add o fallback
        // Soporta múltiples formatos: fallback, smooth_add, add
        $pattern = '/' . preg_quote($marker_start, '/') . '.*?(?:radio = (?:fallback\(track_sensitive=false, \[time_signal, radio\]\)|smooth_add\([^)]*\)|add\([^)]*\)))\s*\n?/s';
        $liquidsoapContent = preg_replace($pattern, '', $liquidsoapContent);
        error_log("APPLY DEBUG - Código antiguo eliminado");
    } else {
        error_log("APPLY DEBUG - No se encontró código antiguo");
    }

    // Añadir nuevo código al final
    error_log("APPLY DEBUG - Añadiendo nuevo código al final...");
    $liquidsoapContent = trim($liquidsoapContent);
    if (!empty($liquidsoapContent)) {
        $liquidsoapContent .= "\n\n";
    }
    $liquidsoapContent .= $liquidsoapCode;
    error_log("APPLY DEBUG - Contenido final (" . strlen($liquidsoapContent) . " bytes)");
    error_log("APPLY DEBUG - Últimos 500 caracteres: " . substr($liquidsoapContent, -500));

    // PASO 5: Escribir archivo liquidsoap.liq actualizado
    error_log("APPLY DEBUG - Escribiendo archivo liquidsoap.liq...");
    $writeResult = writeLiquidsoapFile($username, $liquidsoapContent);

    if (!$writeResult) {
        error_log("APPLY DEBUG - ERROR: writeLiquidsoapFile() retornó false");
        return [
            'success' => false,
            'message' => 'Error al escribir archivo liquidsoap.liq'
        ];
    }

    error_log("APPLY DEBUG - ¡Éxito! Señales horarias aplicadas correctamente");
    return [
        'success' => true,
        'message' => 'Señales horarias aplicadas correctamente al Liquidsoap'
    ];
}

/**
 * Procesar formulario de configuración (simplificado)
 */
function processTimeSignalsForm($username, $postData) {
    error_log("PROCESS FORM DEBUG - Usuario: $username");
    error_log("PROCESS FORM DEBUG - POST data: " . json_encode($postData));

    $frequency = $postData['frequency'] ?? 'hourly';
    error_log("PROCESS FORM DEBUG - Frecuencia: $frequency");

    // Obtener el último archivo subido automáticamente
    $files = listTimeSignals($username);
    error_log("PROCESS FORM DEBUG - Archivos encontrados: " . count($files));

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

    // Validar frecuencia
    $validFrequencies = ['hourly', 'half-hourly', 'quarter-hourly', 'every-5-min'];
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

    error_log("PROCESS FORM DEBUG - Config a guardar: " . json_encode($config));

    $result = saveTimeSignalsConfig($username, $config);
    error_log("PROCESS FORM DEBUG - saveTimeSignalsConfig resultado: " . json_encode($result));

    if ($result['success']) {
        // Intentar aplicar a AzuraCast
        error_log("PROCESS FORM DEBUG - Llamando a applyTimeSignalsToAzuraCast()...");
        $applyResult = applyTimeSignalsToAzuraCast($username);
        error_log("PROCESS FORM DEBUG - applyTimeSignalsToAzuraCast resultado: " . json_encode($applyResult));

        // IMPORTANTE: Si falla la aplicación, informar al usuario
        if (!$applyResult['success']) {
            error_log("PROCESS FORM DEBUG - ERROR en aplicación: " . $applyResult['message']);
            // Retornar el error de aplicación al usuario
            return [
                'success' => false,
                'message' => 'Configuración guardada pero error al aplicar: ' . $applyResult['message']
            ];
        }

        error_log("PROCESS FORM DEBUG - Todo completado exitosamente");
        return [
            'success' => true,
            'message' => 'Señales horarias configuradas y aplicadas correctamente'
        ];
    }

    error_log("PROCESS FORM DEBUG - ERROR al guardar configuración");
    return $result;
}

/**
 * Obtener configuración actual de la estación desde AzuraCast API
 */
function getAzuraCastStationConfig($username) {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'message' => 'API de AzuraCast no configurada'];
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return ['success' => false, 'message' => 'Station ID no configurado'];
    }

    $endpoint = rtrim($apiUrl, '/') . '/admin/station/' . $stationId;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'user_agent' => 'SAPO/1.0',
            'header' => 'X-API-Key: ' . $apiKey,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($endpoint, false, $context);

    if ($response === false) {
        return ['success' => false, 'message' => 'Error al conectar con AzuraCast API'];
    }

    $data = json_decode($response, true);

    if (!$data) {
        return ['success' => false, 'message' => 'Respuesta inválida de la API'];
    }

    return ['success' => true, 'data' => $data];
}

/**
 * Actualizar custom_config de la estación via API de AzuraCast
 */
function updateAzuraCastCustomConfig($username, $customConfig) {
    error_log("API UPDATE - Iniciando actualización para usuario: $username");

    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        error_log("API UPDATE - ERROR: API no configurada");
        return ['success' => false, 'message' => 'API de AzuraCast no configurada en SAPO'];
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        error_log("API UPDATE - ERROR: Station ID no configurado");
        return ['success' => false, 'message' => 'Station ID no configurado'];
    }

    error_log("API UPDATE - Station ID: $stationId");

    // Primero obtener la configuración actual
    $currentConfig = getAzuraCastStationConfig($username);

    if (!$currentConfig['success']) {
        return $currentConfig;
    }

    error_log("API UPDATE - Configuración actual obtenida");

    // Actualizar solo el campo custom_config dentro de backend_config
    $stationData = $currentConfig['data'];

    if (!isset($stationData['backend_config'])) {
        $stationData['backend_config'] = [];
    }

    $stationData['backend_config']['custom_config'] = $customConfig;

    error_log("API UPDATE - Custom config a enviar (" . strlen($customConfig) . " bytes)");

    // Enviar actualización
    $endpoint = rtrim($apiUrl, '/') . '/admin/station/' . $stationId;

    $payload = json_encode($stationData);

    $context = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'timeout' => 15,
            'user_agent' => 'SAPO/1.0',
            'header' => [
                'X-API-Key: ' . $apiKey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ],
            'content' => $payload,
            'ignore_errors' => true
        ]
    ]);

    error_log("API UPDATE - Enviando PUT a: $endpoint");

    $response = @file_get_contents($endpoint, false, $context);

    // Obtener código de respuesta HTTP
    $httpCode = 0;
    if (isset($http_response_header)) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $httpCode = isset($matches[1]) ? (int)$matches[1] : 0;
    }

    error_log("API UPDATE - Código HTTP: $httpCode");

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log("API UPDATE - ERROR: " . ($response ?: 'Sin respuesta'));
        return [
            'success' => false,
            'message' => 'Error al actualizar AzuraCast (HTTP ' . $httpCode . ')'
        ];
    }

    error_log("API UPDATE - ¡Actualización exitosa!");

    // Reiniciar Liquidsoap para aplicar cambios
    $restartEndpoint = rtrim($apiUrl, '/') . '/station/' . $stationId . '/restart';

    $restartContext = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 10,
            'user_agent' => 'SAPO/1.0',
            'header' => 'X-API-Key: ' . $apiKey,
            'ignore_errors' => true
        ]
    ]);

    error_log("API UPDATE - Reiniciando Liquidsoap...");
    @file_get_contents($restartEndpoint, false, $restartContext);

    return [
        'success' => true,
        'message' => 'Configuración aplicada correctamente. Liquidsoap se está reiniciando.'
    ];
}

/**
 * Aplicar señales horarias usando la API de AzuraCast (nuevo método)
 */
function applyTimeSignalsViaAPI($username) {
    error_log("API APPLY - Iniciando aplicación para usuario: $username");

    $config = getTimeSignalsConfig($username);
    error_log("API APPLY - Configuración: " . json_encode($config));

    if (empty($config['signal_file']) || empty($config['days'])) {
        error_log("API APPLY - ERROR: Configuración incompleta");
        return ['success' => false, 'message' => 'Configuración incompleta'];
    }

    $audioPath = getTimeSignalsDir($username) . '/' . $config['signal_file'];
    if (!file_exists($audioPath)) {
        error_log("API APPLY - ERROR: Archivo de audio no encontrado: $audioPath");
        return ['success' => false, 'message' => 'Archivo de audio no encontrado'];
    }

    $frequency = $config['frequency'] ?? 'hourly';
    $days = $config['days'] ?? [];
    $duration = floatval($config['duration'] ?? 1.5);
    $attenuationPercent = intval($config['attenuation'] ?? 30);
    $attenuation = $attenuationPercent / 100; // Convertir porcentaje a decimal

    // Generar path para Liquidsoap
    $liquidsoapPath = "/var/azuracast/stations/{$username}/media/senales_horarias/{$config['signal_file']}";
    error_log("API APPLY - Path Liquidsoap: $liquidsoapPath");

    // Generar código Liquidsoap con valores personalizados
    $musicVolume = 1.0 - $attenuation; # Convertir atenuación a volumen
    $offsetSeconds = -40; # Compensar delay de 50s (adelantar 40s)
    $liquidsoapCode = generateTimeSignalsSmooth($liquidsoapPath, $days, $frequency, $musicVolume, $duration, $offsetSeconds);

    if (empty($liquidsoapCode)) {
        error_log("API APPLY - ERROR: No se pudo generar código");
        return ['success' => false, 'message' => 'Error al generar código Liquidsoap'];
    }

    error_log("API APPLY - Código generado (" . strlen($liquidsoapCode) . " bytes)");

    // Aplicar via API
    $result = updateAzuraCastCustomConfig($username, $liquidsoapCode);

    return $result;
}

/**
 * Generar código Liquidsoap para señales horarias usando smooth_add
 * Formato moderno y simple de Liquidsoap 2.x
 *
 * @param string $audioPath Ruta completa al archivo de audio
 * @param array $days Array de días (mantiene compatibilidad)
 * @param string $frequency Frecuencia de las señales
 * @param float $musicVolume Volumen de música durante señal (0.0-1.0)
 * @param float $transitionDuration Duración de la transición en segundos
 * @param int $offsetSeconds Offset en segundos (negativo = adelantar, positivo = retrasar)
 * @return string Código Liquidsoap generado
 */
function generateTimeSignalsSmooth($audioPath, $days, $frequency, $musicVolume = 0.5, $transitionDuration = 0.8, $offsetSeconds = 0) {
    // Convertir frecuencia en minutos
    $minutes = [];
    switch ($frequency) {
        case 'hourly':
            $minutes = [0];
            break;
        case 'half-hourly':
            $minutes = [0, 30];
            break;
        case 'quarter-hourly':
            $minutes = [0, 15, 30, 45];
            break;
        case 'every-5-min':
            for ($i = 0; $i < 60; $i += 5) {
                $minutes[] = $i;
            }
            break;
        default:
            $minutes = [0];
    }

    // Liquidsoap 2.x requiere floats explícitos (1.0 no 1)
    $durationFloat = number_format((float)$transitionDuration, 1, '.', '');
    $volumeFloat = number_format((float)$musicVolume, 1, '.', '');
    $musicPercent = (int)($musicVolume * 100);

    $code = "# Señales Horarias - SAPO (smooth_add)\n";
    if ($offsetSeconds != 0) {
        $offsetDesc = $offsetSeconds < 0 ? abs($offsetSeconds) . 's adelantado' : $offsetSeconds . 's retrasado';
        $code .= "# Offset: {$offsetDesc}\n";
    }
    $code .= "# Buffer agregado para estabilizar latencia\n";
    $code .= "señal_horaria = buffer(single(\"$audioPath\"))\n";
    $code .= "horarias = switch(id=\"time_signal_switch\", [\n";

    // Generar entradas del switch con offset aplicado
    $entries = [];
    foreach ($minutes as $minute) {
        // Convertir a segundos totales
        $totalSeconds = ($minute * 60) + $offsetSeconds;

        // Ajustar si es negativo (retroceder al minuto anterior)
        if ($totalSeconds < 0) {
            $totalSeconds = 3600 + $totalSeconds; // Retroceder en la hora
        }

        // Normalizar dentro de 0-3599 (0h00m00s - 0h59m59s)
        $totalSeconds = $totalSeconds % 3600;

        // Convertir de vuelta a minutos y segundos
        $adjustedMinute = intval($totalSeconds / 60);
        $adjustedSecond = $totalSeconds % 60;

        if ($adjustedSecond > 0) {
            $entries[] = "  (predicate.once({ {$adjustedMinute}m{$adjustedSecond}s }), señal_horaria)";
        } else {
            $entries[] = "  (predicate.once({ {$adjustedMinute}m }), señal_horaria)";
        }
    }
    $code .= implode(",\n", $entries) . "\n";

    $code .= "])\n";
    $code .= "# smooth_add mezcla suavemente sin cortar\n";
    $code .= "radio = smooth_add(\n";
    $code .= "  duration={$durationFloat},      # Duración de la transición ({$durationFloat} segundos)\n";
    $code .= "  p={$volumeFloat},             # Música baja al {$musicPercent}%\n";
    $code .= "  normal=radio,      # Fuente principal\n";
    $code .= "  special=horarias   # Señales horarias\n";
    $code .= ")\n";

    return $code;
}
