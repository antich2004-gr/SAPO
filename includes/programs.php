<?php
// includes/programs.php - Funciones para gestión de información de programas

/**
 * Obtener ruta del archivo de programas de un usuario
 */
function getProgramsFilePath($username) {
    $dataDir = DATA_DIR . '/programs';
    if (!file_exists($dataDir)) {
        // Intentar crear el directorio padre primero si no existe
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        mkdir($dataDir, 0755, true);
    }
    return $dataDir . '/' . $username . '.json';
}

/**
 * Cargar información de programas de un usuario
 *
 * @param string $username Nombre de usuario
 * @return array Datos de programas
 */
function loadProgramsDB($username) {
    $filePath = getProgramsFilePath($username);

    if (!file_exists($filePath)) {
        return [
            'programs' => [],
            'last_sync' => null
        ];
    }

    $data = file_get_contents($filePath);
    $decoded = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al leer programas de $username: " . json_last_error_msg());
        return [
            'programs' => [],
            'last_sync' => null
        ];
    }

    return $decoded;
}

/**
 * Guardar información de programas de un usuario
 *
 * @param string $username Nombre de usuario
 * @param array $data Datos a guardar
 * @return bool True si se guardó correctamente
 */
function saveProgramsDB($username, $data) {
    $filePath = getProgramsFilePath($username);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        error_log("Error al codificar JSON de programas: " . json_last_error_msg());
        return false;
    }

    return file_put_contents($filePath, $json) !== false;
}

/**
 * Sincronizar programas desde AzuraCast
 * Detecta nuevos programas y los añade sin sobrescribir existentes
 *
 * @param string $username Nombre de usuario
 * @return array Resultado con 'success', 'new_count', 'total_count', 'message'
 */
function syncProgramsFromAzuracast($username) {
    // Obtener schedule de AzuraCast
    $schedule = getAzuracastSchedule($username);

    if ($schedule === false) {
        return [
            'success' => false,
            'message' => 'No se pudo obtener la programación de AzuraCast',
            'new_count' => 0,
            'total_count' => 0
        ];
    }

    if (empty($schedule)) {
        return [
            'success' => false,
            'message' => 'La programación de AzuraCast está vacía',
            'new_count' => 0,
            'total_count' => 0
        ];
    }

    // Extraer nombres únicos de programas
    $detectedPrograms = [];
    foreach ($schedule as $event) {
        $name = $event['name'] ?? $event['playlist'] ?? null;
        if ($name && !in_array($name, $detectedPrograms)) {
            $detectedPrograms[] = $name;
        }
    }

    // Log para debug
    error_log("syncProgramsFromAzuracast: Total eventos recibidos: " . count($schedule));
    error_log("syncProgramsFromAzuracast: Programas únicos detectados: " . count($detectedPrograms));
    error_log("syncProgramsFromAzuracast: Nombres detectados: " . implode(', ', $detectedPrograms));

    // Cargar programas existentes
    $data = loadProgramsDB($username);
    $newCount = 0;

    // Añadir nuevos programas (sin sobrescribir existentes)
    foreach ($detectedPrograms as $programName) {
        if (!isset($data['programs'][$programName])) {
            $data['programs'][$programName] = [
                'display_title' => '', // Título personalizado (si está vacío, usa el nombre de la playlist)
                'playlist_type' => 'program', // program, music_block, jingles
                'short_description' => '',
                'long_description' => '',
                'type' => '',
                'url' => '',
                'image' => '',
                'presenters' => '',
                'social_twitter' => '',
                'social_instagram' => '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            $newCount++;
        }
    }

    // Actualizar fecha de sincronización
    $data['last_sync'] = date('Y-m-d H:i:s');

    // Guardar
    if (!saveProgramsDB($username, $data)) {
        return [
            'success' => false,
            'message' => 'Error al guardar los programas',
            'new_count' => 0,
            'total_count' => 0
        ];
    }

    return [
        'success' => true,
        'message' => $newCount > 0
            ? "Se detectaron $newCount programas nuevos"
            : "No hay programas nuevos",
        'new_count' => $newCount,
        'total_count' => count($data['programs'])
    ];
}

/**
 * Obtener información de un programa específico
 *
 * @param string $username Nombre de usuario
 * @param string $programName Nombre del programa
 * @return array|null Información del programa o null si no existe
 */
function getProgramInfo($username, $programName) {
    $data = loadProgramsDB($username);
    return $data['programs'][$programName] ?? null;
}

/**
 * Guardar información de un programa
 *
 * @param string $username Nombre de usuario
 * @param string $programName Nombre del programa
 * @param array $info Información a guardar
 * @return bool True si se guardó correctamente
 */
function saveProgramInfo($username, $programName, $info) {
    $data = loadProgramsDB($username);

    // Asegurar que existe el programa
    if (!isset($data['programs'][$programName])) {
        $data['programs'][$programName] = [];
    }

    // Actualizar información
    $data['programs'][$programName] = array_merge(
        $data['programs'][$programName],
        $info,
        ['updated_at' => date('Y-m-d H:i:s')]
    );

    return saveProgramsDB($username, $data);
}

/**
 * Obtener todos los programas
 *
 * @param string $username Nombre de usuario
 * @return array Array con programas
 */
function getAllProgramsWithStats($username) {
    $data = loadProgramsDB($username);
    $programs = [];

    foreach ($data['programs'] as $name => $info) {
        $programs[] = [
            'name' => $name,
            'info' => $info
        ];
    }

    // Ordenar por nombre
    usort($programs, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return [
        'programs' => $programs,
        'last_sync' => $data['last_sync'] ?? null,
        'total' => count($programs)
    ];
}

/**
 * Eliminar un programa
 *
 * @param string $username Nombre de usuario
 * @param string $programName Nombre del programa a eliminar
 * @return bool True si se eliminó correctamente
 */
function deleteProgram($username, $programName) {
    $data = loadProgramsDB($username);

    // Verificar que el programa existe
    if (!isset($data['programs'][$programName])) {
        return false;
    }

    // Eliminar el programa
    unset($data['programs'][$programName]);

    return saveProgramsDB($username, $data);
}

/**
 * Obtener el último episodio de un feed RSS (con caché)
 *
 * @param string $rssUrl URL del feed RSS
 * @param int $cacheTTL Tiempo de vida de la caché en segundos (por defecto 1 hora)
 * @return array|null Datos del último episodio o null si hay error
 */
function getLatestEpisodeFromRSS($rssUrl, $cacheTTL = 3600) {
    if (empty($rssUrl)) {
        return null;
    }

    // Verificar caché primero (ANTES de validaciones para evitar DNS lookups lentos)
    $cachedData = getRSSFromCache($rssUrl, $cacheTTL);
    // Si hay caché (incluso null), retornar inmediatamente sin validaciones
    if ($cachedData !== false) {
        return $cachedData;
    }

    // SEGURIDAD: Validar que es una URL válida
    if (!filter_var($rssUrl, FILTER_VALIDATE_URL)) {
        error_log("URL RSS inválida: $rssUrl");
        if (function_exists('logInvalidInput')) {
            logInvalidInput('rss_url', $rssUrl, ['validation' => 'FILTER_VALIDATE_URL']);
        }
        return null;
    }

    // SEGURIDAD: Prevenir SSRF - solo permitir HTTP/HTTPS públicos
    $parsedUrl = parse_url($rssUrl);
    if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
        error_log("Esquema no permitido en RSS: $rssUrl");
        if (function_exists('logSSRFAttempt')) {
            logSSRFAttempt($rssUrl, 'Esquema no permitido', ['scheme' => $parsedUrl['scheme'] ?? 'none']);
        }
        return null;
    }

    // SEGURIDAD: Bloquear IPs privadas y localhost para prevenir SSRF
    $host = $parsedUrl['host'] ?? '';
    $ip = gethostbyname($host);

    // Bloquear rangos privados: 127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        error_log("IP privada/localhost bloqueada en RSS: $rssUrl ($ip)");
        if (function_exists('logSSRFAttempt')) {
            logSSRFAttempt($rssUrl, 'IP privada/localhost', ['host' => $host, 'ip' => $ip]);
        }
        return null;
    }

    // Intentar cargar el RSS con timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; SAPO-RSS-Parser/1.0)',
            'follow_location' => 0  // SEGURIDAD: No seguir redirects automáticamente
        ]
    ]);

    $rssContent = @file_get_contents($rssUrl, false, $context);

    if ($rssContent === false) {
        error_log("Error al obtener RSS: $rssUrl");
        // Cachear el fallo por 1 hora para no reintentar constantemente
        saveRSSToCache($rssUrl, null);
        return null;
    }

    // Parsear XML
    // SEGURIDAD: Deshabilitar entidades externas para prevenir XXE
    libxml_use_internal_errors(true);

    // En PHP 8.0+, libxml_disable_entity_loader está deprecated porque está habilitado por defecto
    // Solo llamar en versiones antiguas de PHP
    if (PHP_VERSION_ID < 80000) {
        libxml_disable_entity_loader(true);
    }

    $xml = simplexml_load_string($rssContent, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_DTDATTR);

    if ($xml === false) {
        error_log("Error al parsear XML del RSS: $rssUrl");
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader(false);
        }
        // Cachear el fallo de parsing
        saveRSSToCache($rssUrl, null);
        return null;
    }

    // Re-habilitar para no afectar otros procesos (solo en PHP < 8.0)
    if (PHP_VERSION_ID < 80000) {
        libxml_disable_entity_loader(false);
    }

    // Intentar obtener el primer item (último episodio)
    $item = null;

    // Formato RSS 2.0
    if (isset($xml->channel->item[0])) {
        $item = $xml->channel->item[0];
    }
    // Formato Atom
    elseif (isset($xml->entry[0])) {
        $item = $xml->entry[0];
    }

    if (!$item) {
        return null;
    }

    // Extraer datos del episodio
    $title = (string)($item->title ?? '');
    $description = (string)($item->description ?? $item->summary ?? '');

    // Extraer link (compatible con RSS 2.0 y Atom)
    $link = '';
    if (isset($item->link)) {
        // Atom: <link href="..." /> - verificar atributo href primero
        if (isset($item->link['href'])) {
            $link = (string)$item->link['href'];
        }
        // RSS 2.0: <link>http://...</link> - elemento de texto
        elseif ((string)$item->link !== '') {
            $link = (string)$item->link;
        }
        // Múltiples links Atom: buscar el adecuado
        else {
            foreach ($item->link as $linkItem) {
                if (isset($linkItem['href'])) {
                    // Preferir link con rel="alternate" o sin rel
                    if (!isset($linkItem['rel']) || $linkItem['rel'] == 'alternate') {
                        $link = (string)$linkItem['href'];
                        break;
                    }
                }
            }
            // Si no encontramos alternate, usar el primer link con href
            if (empty($link)) {
                foreach ($item->link as $linkItem) {
                    if (isset($linkItem['href'])) {
                        $link = (string)$linkItem['href'];
                        break;
                    }
                }
            }
        }
    }

    // Extraer fecha de publicación (RSS 2.0: pubDate, Atom: published o updated)
    $pubDate = (string)($item->pubDate ?? $item->published ?? $item->updated ?? '');

    // Buscar URL del audio (enclosure)
    $audioUrl = '';
    if (isset($item->enclosure['url'])) {
        $audioUrl = (string)$item->enclosure['url'];
    } elseif (isset($item->link)) {
        foreach ($item->link as $linkItem) {
            if (isset($linkItem['rel']) && $linkItem['rel'] == 'enclosure') {
                $audioUrl = (string)$linkItem['href'];
                break;
            }
        }
    }

    // Formatear fecha y validar antigüedad
    $formattedDate = '';
    $timestamp = null;
    if ($pubDate) {
        $timestamp = strtotime($pubDate);
        if ($timestamp) {
            $formattedDate = date('d/m/Y', $timestamp);
        }
    }

    // No mostrar episodios con más de 30 días
    if ($timestamp) {
        $daysSincePublished = (time() - $timestamp) / (60 * 60 * 24);
        if ($daysSincePublished > 30) {
            // Episodio demasiado antiguo, cachear null para no volver a consultar
            saveRSSToCache($rssUrl, null);
            return null;
        }
    }

    $result = [
        'title' => $title,
        'description' => strip_tags($description),
        'link' => $link,
        'audio_url' => $audioUrl,
        'pub_date' => $pubDate,
        'formatted_date' => $formattedDate
    ];

    // Guardar en caché
    saveRSSToCache($rssUrl, $result);

    return $result;
}

/**
 * Obtener RSS de la caché
 *
 * @param string $rssUrl URL del feed RSS
 * @param int $ttl Tiempo de vida en segundos
 * @return array|null Datos cacheados o null si no existe o expiró
 */
function getRSSFromCache($rssUrl, $ttl) {
    $t1 = microtime(true);
    $cacheDir = DATA_DIR . '/rss_cache';
    if (!file_exists($cacheDir)) {
        return false;  // false = sin caché, null = caché con valor null
    }

    // Crear nombre de archivo único basado en URL
    $cacheFile = $cacheDir . '/' . md5($rssUrl) . '.json';

    if (!file_exists($cacheFile)) {
        return false;  // false = sin caché
    }

    // Verificar si expiró
    $fileAge = time() - filemtime($cacheFile);
    if ($fileAge > $ttl) {
        @unlink($cacheFile);  // Eliminar caché expirada
        return false;  // false = sin caché
    }

    // Leer caché
    $data = file_get_contents($cacheFile);
    $decoded = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        @unlink($cacheFile);  // Eliminar caché corrupta
        return false;  // false = sin caché
    }

    $t2 = microtime(true);
    if (($t2 - $t1) > 0.1) {
        error_log(sprintf("WARNING: getRSSFromCache tardó %.3fs para %s", $t2 - $t1, basename($cacheFile)));
    }

    return $decoded;  // Puede ser null (RSS fallido cacheado) o array (RSS exitoso)
}

/**
 * Guardar RSS en la caché
 *
 * @param string $rssUrl URL del feed RSS
 * @param array $data Datos a cachear
 * @return bool True si se guardó correctamente
 */
function saveRSSToCache($rssUrl, $data) {
    $cacheDir = DATA_DIR . '/rss_cache';

    // Crear directorio de caché si no existe
    if (!file_exists($cacheDir)) {
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        mkdir($cacheDir, 0755, true);
    }

    // Crear nombre de archivo único basado en URL
    $cacheFile = $cacheDir . '/' . md5($rssUrl) . '.json';

    // Guardar JSON
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    // Escribir archivo
    $result = @file_put_contents($cacheFile, $json, LOCK_EX);

    // Limpiar cachés antiguas ocasionalmente (1% de las veces)
    if (rand(1, 100) === 1) {
        cleanOldRSSCache($cacheDir);
    }

    return $result !== false;
}

/**
 * Limpiar cachés de RSS antiguas (más de 7 días sin acceso)
 *
 * @param string $cacheDir Directorio de caché
 */
function cleanOldRSSCache($cacheDir) {
    $files = glob($cacheDir . '/*.json');
    if (!$files) {
        return;
    }

    $cutoffTime = time() - (7 * 24 * 60 * 60); // 7 días

    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            @unlink($file);
        }
    }
}
