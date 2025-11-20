<?php
// includes/azuracast.php - Funciones para integración con AzuraCast

/**
 * Obtener la programación de una emisora desde AzuraCast (con caché)
 *
 * @param string $username Nombre de usuario de la emisora
 * @param int $cacheTTL Tiempo de vida de la caché en segundos (por defecto 600 = 10 minutos)
 * @return array|false Array con los eventos de la programación o false si hay error
 */
function getAzuracastSchedule($username, $cacheTTL = 600) {
    // Verificar si hay caché válida
    $cachedData = getScheduleFromCache($username, $cacheTTL);
    if ($cachedData !== null) {
        return $cachedData;
    }

    // Obtener configuración global
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';

    if (empty($apiUrl)) {
        error_log("AzuraCast: URL de API no configurada");
        return false;
    }

    // Obtener station_id del usuario
    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        error_log("AzuraCast: Station ID no configurado para usuario $username");
        return false;
    }

    // Construir URL de la API
    // Obtener programación de toda la semana (7 días desde hoy)
    $now = time();
    $startDate = date('Y-m-d\T00:00:00P', $now); // Hoy a las 00:00
    $endDate = date('Y-m-d\T23:59:59P', strtotime('+7 days', $now)); // +7 días

    $scheduleUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/schedule';
    $scheduleUrl .= '?start=' . urlencode($startDate) . '&end=' . urlencode($endDate);

    // Hacer petición a la API
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,  // Timeout de 15 segundos (puede ser más datos)
                'user_agent' => 'SAPO/1.0'
            ]
        ]);

        $response = @file_get_contents($scheduleUrl, false, $context);

        if ($response === false) {
            error_log("AzuraCast: Error al obtener programación desde $scheduleUrl");
            return false;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AzuraCast: Error al decodificar JSON: " . json_last_error_msg());
            return false;
        }

        // Log para debug
        error_log("AzuraCast: Obtenidos " . count($data) . " eventos para station $stationId");

        // Guardar en caché
        saveScheduleToCache($username, $data);

        return $data;

    } catch (Exception $e) {
        error_log("AzuraCast: Excepción al obtener programación: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener todas las playlists de AzuraCast (incluyendo desactivadas)
 * Requiere API Key para autenticación
 *
 * @param string $username Nombre de usuario
 * @return array|false Array de playlists o false si hay error
 */
function getAzuracastPlaylists($username) {
    // Obtener configuración global
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl)) {
        error_log("AzuraCast Playlists: URL de API no configurada");
        return false;
    }

    if (empty($apiKey)) {
        error_log("AzuraCast Playlists: API Key no configurada - no se puede consultar estado de playlists");
        return false;
    }

    // Obtener station_id del usuario
    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        error_log("AzuraCast Playlists: Station ID no configurado para usuario $username");
        return false;
    }

    // Construir URL de la API de playlists
    $playlistsUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/playlists';

    try {
        // Crear contexto con autenticación API Key
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'SAPO/1.0',
                'header' => 'X-API-Key: ' . $apiKey
            ]
        ]);

        $response = @file_get_contents($playlistsUrl, false, $context);

        if ($response === false) {
            error_log("AzuraCast Playlists: Error al obtener playlists desde $playlistsUrl (¿API Key válida?)");
            return false;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AzuraCast Playlists: Error JSON: " . json_last_error_msg());
            return false;
        }

        // Debug: mostrar estructura de la primera playlist
        if (!empty($data) && isset($data[0])) {
            error_log("AzuraCast Playlists: Total playlists: " . count($data));
            $enabledCount = count(array_filter($data, fn($p) => ($p['is_enabled'] ?? true)));
            $disabledCount = count($data) - $enabledCount;
            error_log("AzuraCast Playlists: Habilitadas: $enabledCount, Deshabilitadas: $disabledCount");
        } else {
            error_log("AzuraCast Playlists: Respuesta vacía o no es array");
        }

        return $data;

    } catch (Exception $e) {
        error_log("AzuraCast Playlists: Excepción: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener schedule desde caché si existe y es válida
 *
 * @param string $username Nombre de usuario
 * @param int $ttl Tiempo de vida en segundos
 * @return array|null Datos del schedule o null si no hay caché válida
 */
function getScheduleFromCache($username, $ttl = 600) {
    $cacheDir = DATA_DIR . '/cache/schedule';
    if (!file_exists($cacheDir)) {
        return null;
    }

    $cacheFile = $cacheDir . '/' . md5($username) . '.json';

    if (!file_exists($cacheFile)) {
        return null;
    }

    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge > $ttl) {
        // Caché expirada
        @unlink($cacheFile);
        return null;
    }

    $cacheContent = @file_get_contents($cacheFile);
    if ($cacheContent === false) {
        return null;
    }

    $data = json_decode($cacheContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    error_log("AzuraCast: Schedule cargado desde caché para $username (edad: {$cacheAge}s)");
    return $data;
}

/**
 * Guardar schedule en caché
 *
 * @param string $username Nombre de usuario
 * @param array $data Datos del schedule
 * @return bool True si se guardó correctamente
 */
function saveScheduleToCache($username, $data) {
    $cacheDir = DATA_DIR . '/cache/schedule';

    if (!file_exists($cacheDir)) {
        if (!file_exists(DATA_DIR . '/cache')) {
            if (!file_exists(DATA_DIR)) {
                mkdir(DATA_DIR, 0755, true);
            }
            mkdir(DATA_DIR . '/cache', 0755, true);
        }
        mkdir($cacheDir, 0755, true);
    }

    $cacheFile = $cacheDir . '/' . md5($username) . '.json';
    $jsonContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return @file_put_contents($cacheFile, $jsonContent, LOCK_EX) !== false;
}

/**
 * Actualizar configuración de AzuraCast para un usuario
 *
 * @param string $username Nombre de usuario
 * @param int|null $stationId ID de la estación en AzuraCast
 * @param string $widgetColor Color del widget en formato hexadecimal
 * @param bool $showLogo Mostrar logo en el widget
 * @param string $logoUrl URL del logo
 * @return bool True si se guardó correctamente
 */
function updateAzuracastConfig($username, $stationId, $widgetColor = '#3b82f6', $showLogo = false, $logoUrl = '', $widgetStyle = 'modern', $widgetFontSize = 'medium', $streamUrl = '') {
    $userData = getUserDB($username);

    $userData['azuracast'] = [
        'station_id' => $stationId !== '' ? intval($stationId) : null,
        'widget_color' => $widgetColor,
        'show_logo' => (bool)$showLogo,
        'logo_url' => $logoUrl,
        'widget_style' => $widgetStyle,
        'widget_font_size' => $widgetFontSize,
        'stream_url' => $streamUrl
    ];

    return saveUserDB($username, $userData);
}

/**
 * Obtener configuración de AzuraCast de un usuario
 *
 * @param string $username Nombre de usuario
 * @return array Configuración de AzuraCast
 */
function getAzuracastConfig($username) {
    $userData = getUserDB($username);
    return $userData['azuracast'] ?? [
        'station_id' => null,
        'widget_color' => '#3b82f6',
        'show_logo' => false,
        'logo_url' => '',
        'widget_style' => 'modern',
        'widget_font_size' => 'medium',
        'stream_url' => ''
    ];
}

/**
 * Formatear eventos de AzuraCast para FullCalendar
 *
 * @param array $events Eventos desde la API de AzuraCast
 * @param string $color Color para los eventos
 * @param string $username Nombre de usuario (para obtener info adicional de SAPO)
 * @return array Eventos formateados para FullCalendar
 */
function formatEventsForCalendar($events, $color = '#3b82f6', $username = null) {
    if (!is_array($events)) {
        return [];
    }

    // Cargar información adicional de programas desde SAPO si se proporciona username
    $programsData = [];
    if ($username !== null && function_exists('loadProgramsDB')) {
        $programsDB = loadProgramsDB($username);
        $programsData = $programsDB['programs'] ?? [];
    }

    $formattedEvents = [];

    foreach ($events as $event) {
        // AzuraCast devuelve diferentes formatos dependiendo de la versión
        // Intentar adaptar a múltiples formatos

        $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
        $start = $event['start_timestamp'] ?? $event['start'] ?? null;
        $end = $event['end_timestamp'] ?? $event['end'] ?? null;

        if ($start === null) {
            continue; // Saltar eventos sin hora de inicio
        }

        // Convertir timestamp a DateTime para extraer día y hora
        $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
        $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

        // Establecer zona horaria local (CET/CEST)
        $timezone = new DateTimeZone('Europe/Madrid');
        $startDateTime->setTimezone($timezone);
        if ($endDateTime) {
            $endDateTime->setTimezone($timezone);
        }

        // Obtener día de la semana (0=Domingo, 1=Lunes, etc.) como ENTERO
        $dayOfWeek = (int)$startDateTime->format('w');

        // Obtener solo la hora (formato HH:mm:ss)
        $startTime = $startDateTime->format('H:i:s');

        // Si no hay endTime o es igual a startTime, calcular duración desde el título
        // Muchos nombres tienen el formato "NOMBRE - DURACION" ej: "PROGRAMA - 30" (30 minutos)
        if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
            // Intentar extraer duración del título (ej: "PROGRAMA - 30" = 30 minutos)
            if (preg_match('/-\s*(\d+)\s*$/', $title, $matches)) {
                $durationMinutes = (int)$matches[1];
                $endDateTime = clone $startDateTime;
                $endDateTime->modify("+{$durationMinutes} minutes");
            } else {
                // Por defecto, 1 hora si no se puede determinar
                $endDateTime = clone $startDateTime;
                $endDateTime->modify('+1 hour');
            }
        }

        $endTime = $endDateTime->format('H:i:s');

        // Intentar obtener información adicional de SAPO primero
        $programInfo = $programsData[$title] ?? null;

        // Obtener tipo de lista (program, music_block, jingles)
        $playlistType = 'program'; // Por defecto
        if ($programInfo) {
            $playlistType = $programInfo['playlist_type'] ?? 'program';

            // FILTRAR: No mostrar jingles/cortinillas en la parrilla
            if ($playlistType === 'jingles') {
                continue; // Saltar este evento
            }

            // Usar información de SAPO (prioritaria)
            $programDescription = $programInfo['short_description'] ?? '';
            $programLongDescription = $programInfo['long_description'] ?? '';
            $programType = $programInfo['type'] ?? '';
            $programUrl = $programInfo['url'] ?? '';
            $programImage = $programInfo['image'] ?? '';
            $programPresenters = $programInfo['presenters'] ?? '';
            $programTwitter = $programInfo['social_twitter'] ?? '';
            $programInstagram = $programInfo['social_instagram'] ?? '';
        } else {
            // Fallback: parsear descripción de AzuraCast (formato: descripción;temática;url)
            $rawDescription = $event['description'] ?? '';
            $descriptionParts = explode(';', $rawDescription);

            $programDescription = trim($descriptionParts[0] ?? '');
            $programLongDescription = '';
            $programType = trim($descriptionParts[1] ?? '');
            $programUrl = trim($descriptionParts[2] ?? '');
            $programImage = '';
            $programPresenters = '';
            $programTwitter = '';
            $programInstagram = '';
        }

        // Determinar estilo según el tipo de lista
        $backgroundColor = $color;
        $borderColor = $color;
        $className = '';

        if ($playlistType === 'music_block') {
            // Bloques musicales: color más suave, atenuado
            $backgroundColor = '#e5e7eb'; // Gris claro
            $borderColor = '#9ca3af';     // Gris medio
            $className = 'music-block';
        }

        // Crear evento recurrente semanal usando daysOfWeek
        $formattedEvents[] = [
            'title' => $title,
            'daysOfWeek' => [$dayOfWeek], // Día de la semana que se repite (como entero)
            'startTime' => $startTime,     // Hora de inicio
            'endTime' => $endTime,         // Hora de fin
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
            'className' => $className,
            'extendedProps' => [
                'type' => $event['type'] ?? 'playlist',
                'playlist' => $event['playlist'] ?? '',
                'playlistType' => $playlistType,
                'description' => $programDescription,
                'longDescription' => $programLongDescription,
                'programType' => $programType,
                'programUrl' => $programUrl,
                'programImage' => $programImage,
                'presenters' => $programPresenters,
                'twitter' => $programTwitter,
                'instagram' => $programInstagram
            ]
        ];
    }

    return $formattedEvents;
}

/**
 * Verificar si la API de AzuraCast está accesible
 *
 * @return array Array con 'success' (bool) y 'message' (string)
 */
function testAzuracastConnection() {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';

    if (empty($apiUrl)) {
        return [
            'success' => false,
            'message' => 'URL de API no configurada'
        ];
    }

    // Intentar hacer ping a la API
    $statusUrl = rtrim($apiUrl, '/') . '/status';

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'SAPO/1.0'
        ]
    ]);

    $response = @file_get_contents($statusUrl, false, $context);

    if ($response === false) {
        return [
            'success' => false,
            'message' => 'No se pudo conectar a la API'
        ];
    }

    return [
        'success' => true,
        'message' => 'Conexión exitosa'
    ];
}

/**
 * Obtener configuración avanzada de Liquidsoap de una emisora
 * Requiere API Key para autenticación
 *
 * @param string $username Nombre de usuario
 * @return array|false Array con la configuración de Liquidsoap o false si hay error
 */
function getAzuracastLiquidsoapConfig($username) {
    // Obtener configuración global
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl)) {
        error_log("AzuraCast Liquidsoap: URL de API no configurada");
        return false;
    }

    if (empty($apiKey)) {
        error_log("AzuraCast Liquidsoap: API Key no configurada");
        return false;
    }

    // Obtener station_id del usuario
    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        error_log("AzuraCast Liquidsoap: Station ID no configurado para usuario $username");
        return false;
    }

    // Obtener perfil de la estación
    $stationUrl = rtrim($apiUrl, '/') . '/station/' . $stationId;

    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'SAPO/1.0',
                'header' => 'X-API-Key: ' . $apiKey
            ]
        ]);

        $response = @file_get_contents($stationUrl, false, $context);

        if ($response === false) {
            error_log("AzuraCast Liquidsoap: Error al obtener estación desde $stationUrl");
            return false;
        }

        $stationData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AzuraCast Liquidsoap: Error JSON: " . json_last_error_msg());
            return false;
        }

        // Extraer backend_config que contiene la configuración de Liquidsoap
        $backendConfig = $stationData['backend_config'] ?? [];

        // Debug: ver qué campos hay en backend_config
        error_log("AzuraCast Liquidsoap: Campos en backend_config: " . implode(', ', array_keys($backendConfig)));
        error_log("AzuraCast Liquidsoap: custom_config = " . ($backendConfig['custom_config'] ?? 'NO EXISTE'));

        // Convertir a formato compatible con la UI
        $liquidConfig = [
            [
                'field' => 'custom_config',
                'label' => 'Custom Configuration (Top of File)',
                'value' => $backendConfig['custom_config'] ?? ''
            ],
            [
                'field' => 'dj_on_air_hook',
                'label' => 'Custom Code on DJ Connect',
                'value' => $backendConfig['dj_on_air_hook'] ?? ''
            ],
            [
                'field' => 'dj_off_air_hook',
                'label' => 'Custom Code on DJ Disconnect',
                'value' => $backendConfig['dj_off_air_hook'] ?? ''
            ]
        ];

        error_log("AzuraCast Liquidsoap: Configuración obtenida correctamente para station $stationId");
        return $liquidConfig;

    } catch (Exception $e) {
        error_log("AzuraCast Liquidsoap: Excepción: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualizar configuración avanzada de Liquidsoap de una emisora
 * Requiere API Key para autenticación
 *
 * @param string $username Nombre de usuario
 * @param array $configData Configuración de Liquidsoap a guardar (clave => valor)
 * @return array Array con 'success' (bool) y 'message' (string)
 */
function updateAzuracastLiquidsoapConfig($username, $configData) {
    // Obtener configuración global
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl)) {
        return ['success' => false, 'message' => 'URL de API no configurada'];
    }

    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'API Key no configurada'];
    }

    // Obtener station_id del usuario
    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return ['success' => false, 'message' => 'Station ID no configurado'];
    }

    $stationUrl = rtrim($apiUrl, '/') . '/station/' . $stationId;

    try {
        // Primero obtener la configuración actual de la estación
        $getContext = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'SAPO/1.0',
                'header' => 'X-API-Key: ' . $apiKey
            ]
        ]);

        $getResponse = @file_get_contents($stationUrl, false, $getContext);

        if ($getResponse === false) {
            return ['success' => false, 'message' => 'Error al obtener configuración actual'];
        }

        $stationData = json_decode($getResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Error al procesar configuración actual'];
        }

        // Actualizar backend_config con los nuevos valores
        $backendConfig = $stationData['backend_config'] ?? [];
        foreach ($configData as $key => $value) {
            $backendConfig[$key] = $value;
        }

        // Preparar datos para PUT (solo enviamos backend_config)
        $updateData = [
            'backend_config' => $backendConfig
        ];

        $jsonData = json_encode($updateData, JSON_UNESCAPED_UNICODE);

        // URL para admin endpoint (necesario para actualizar)
        $adminStationUrl = rtrim($apiUrl, '/') . '/admin/station/' . $stationId;

        // Usar cURL para el PUT request (más fiable que file_get_contents)
        $ch = curl_init($adminStationUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $apiKey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ]);

        $putResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($putResponse === false || !empty($curlError)) {
            error_log("AzuraCast Liquidsoap: cURL error - " . $curlError);
            return ['success' => false, 'message' => 'Error de conexión: ' . $curlError];
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($putResponse, true);
            $errorMsg = $errorData['message'] ?? $errorData['error'] ?? "HTTP $httpCode";
            error_log("AzuraCast Liquidsoap: HTTP $httpCode - $errorMsg");
            return ['success' => false, 'message' => "Error HTTP $httpCode: $errorMsg"];
        }

        $data = json_decode($putResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Error al procesar respuesta de la API'];
        }

        error_log("AzuraCast Liquidsoap: Configuración actualizada correctamente para station $stationId");
        return ['success' => true, 'message' => 'Configuración guardada correctamente', 'data' => $data];

    } catch (Exception $e) {
        error_log("AzuraCast Liquidsoap: Excepción al actualizar: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
