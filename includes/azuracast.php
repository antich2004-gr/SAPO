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
 * Obtiene todas las playlists de AzuraCast y mapea qué carpetas tienen asignadas
 *
 * @param string $username
 * @return array ['success' => bool, 'playlists' => array, 'message' => string]
 */
function getAzuracastPlaylists($username) {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    error_log("AzuraCast: Obteniendo playlists para usuario: $username");

    if (empty($apiUrl)) {
        return ['success' => false, 'message' => 'URL de API no configurada', 'playlists' => []];
    }

    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'API Key no configurada', 'playlists' => []];
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return ['success' => false, 'message' => 'Station ID no configurado', 'playlists' => []];
    }

    // Endpoint para obtener todas las playlists
    $playlistsUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/playlists';

    error_log("AzuraCast: URL de playlists: $playlistsUrl");

    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'X-API-Key: ' . $apiKey,
                    'Accept: application/json'
                ],
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($playlistsUrl, false, $context);

        $httpCode = 0;
        if (isset($http_response_header) && count($http_response_header) > 0) {
            preg_match('/\d{3}/', $http_response_header[0], $matches);
            $httpCode = isset($matches[0]) ? (int)$matches[0] : 0;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $playlists = json_decode($response, true);

            if (!is_array($playlists)) {
                error_log("AzuraCast: Respuesta no es un array válido");
                return ['success' => false, 'message' => 'Respuesta inválida', 'playlists' => []];
            }

            error_log("AzuraCast: " . count($playlists) . " playlists obtenidas");

            return [
                'success' => true,
                'message' => 'Playlists obtenidas correctamente',
                'playlists' => $playlists
            ];
        } else {
            error_log("AzuraCast: Error al obtener playlists. HTTP $httpCode");
            error_log("AzuraCast: Response: $response");

            return [
                'success' => false,
                'message' => 'Error al obtener playlists (HTTP ' . $httpCode . ')',
                'playlists' => []
            ];
        }
    } catch (Exception $e) {
        error_log("AzuraCast: Excepción al obtener playlists: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error de conexión: ' . $e->getMessage(),
            'playlists' => []
        ];
    }
}

/**
 * Obtiene todos los archivos de la estación
 *
 * @param string $username Usuario
 * @param int $playlistId ID de la playlist para filtrar (opcional)
 * @return array Resultado con archivos
 */
function getStationFiles($username, $playlistId = null) {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';
    $apiKey = $config['azuracast_api_key'] ?? '';

    if (empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'files' => []];
    }

    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        return ['success' => false, 'files' => []];
    }

    // Endpoint para obtener archivos de la estación
    $filesUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/files';
    if ($playlistId !== null) {
        $filesUrl .= '?playlist=' . $playlistId;
    }

    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'X-API-Key: ' . $apiKey,
                    'Accept: application/json'
                ],
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($filesUrl, false, $context);

        $httpCode = 0;
        if (isset($http_response_header) && count($http_response_header) > 0) {
            preg_match('/\d{3}/', $http_response_header[0], $matches);
            $httpCode = isset($matches[0]) ? (int)$matches[0] : 0;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'files' => is_array($data) ? $data : []
            ];
        }
    } catch (Exception $e) {
        error_log("AzuraCast: Error obteniendo archivos: " . $e->getMessage());
    }

    return ['success' => false, 'files' => []];
}

/**
 * Encuentra la playlist de AzuraCast vinculada a una carpeta de podcast
 *
 * @param string $username
 * @param string $podcastPath Ruta completa del podcast
 * @return array|null Información de la playlist vinculada o null si no hay
 */
function findLinkedPlaylist($username, $podcastPath) {
    $result = getAzuracastPlaylists($username);

    if (!$result['success']) {
        return null;
    }

    $config = getConfig();
    $basePath = $config['base_path'] ?? '';

    // Convertir ruta absoluta a relativa
    $relativePath = str_replace($basePath, '', $podcastPath);
    $relativePath = trim($relativePath, '/\\');
    // Normalizar separadores a /
    $relativePath = str_replace('\\', '/', $relativePath);

    // Extraer el nombre del podcast de la ruta
    // Formato esperado: usuario/media/Podcasts/Categoria/NombrePodcast
    $pathParts = explode('/', $relativePath);
    $podcastName = end($pathParts);

    error_log("AzuraCast: Buscando playlist para podcast: $podcastName (ruta: $relativePath)");

    foreach ($result['playlists'] as $playlist) {
        // Saltar playlists vacías
        if (empty($playlist['num_songs'])) {
            continue;
        }

        $playlistName = $playlist['name'];
        $playlistShortName = $playlist['short_name'] ?? '';

        error_log("AzuraCast: Revisando playlist: $playlistName (short_name: $playlistShortName)");

        // Estrategia 1: Comparar nombre exacto (case-insensitive)
        if (strcasecmp($playlistName, $podcastName) === 0) {
            error_log("AzuraCast: ✓ Playlist encontrada por nombre exacto: $playlistName");
            return $playlist;
        }

        // Estrategia 2: Comparar short_name con nombre del podcast
        if (!empty($playlistShortName) && strcasecmp($playlistShortName, $podcastName) === 0) {
            error_log("AzuraCast: ✓ Playlist encontrada por short_name: $playlistName");
            return $playlist;
        }

        // Estrategia 3: Obtener archivos de la playlist y verificar rutas
        $filesResult = getStationFiles($username, $playlist['id']);

        if (!$filesResult['success'] || empty($filesResult['files'])) {
            continue;
        }

        error_log("AzuraCast: Playlist tiene " . count($filesResult['files']) . " archivos");

        // Revisar si algún archivo de la playlist está en la carpeta del podcast
        foreach ($filesResult['files'] as $file) {
            $filePath = $file['path'] ?? '';
            if (empty($filePath)) {
                continue;
            }

            // Normalizar separadores
            $filePath = str_replace('\\', '/', $filePath);

            // Verificar si el archivo está dentro de la carpeta del podcast
            if (strpos($filePath, $relativePath) === 0) {
                error_log("AzuraCast: ✓ Playlist encontrada por archivo: $playlistName (archivo: $filePath)");
                return $playlist;
            }
        }
    }

    error_log("AzuraCast: ✗ No se encontró playlist vinculada para: $podcastName");
    return null;
}
