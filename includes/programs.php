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

    $result = file_put_contents($filePath, $json) !== false;

    // Borrar caché del schedule para que se actualice con los nuevos datos
    if ($result) {
        clearScheduleCache($username);
    }

    return $result;
}

/**
 * Sincronizar programas desde AzuraCast
 * Detecta nuevos programas y los añade sin sobrescribir existentes
 * También detecta playlists deshabilitadas si hay API Key configurada
 *
 * @param string $username Nombre de usuario
 * @return array Resultado con 'success', 'new_count', 'total_count', 'message'
 */
function syncProgramsFromAzuracast($username) {
    // Obtener schedule de AzuraCast (sin caché para datos frescos)
    $schedule = getAzuracastSchedule($username, 0);

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

    // Obtener estado de playlists (requiere API Key)
    // Crear mapa de playlist_name => is_enabled
    $playlistStatus = [];
    $playlists = getAzuracastPlaylists($username);
    if ($playlists !== false && is_array($playlists)) {
        foreach ($playlists as $playlist) {
            $playlistName = $playlist['name'] ?? null;
            if ($playlistName) {
                $playlistStatus[$playlistName] = $playlist['is_enabled'] ?? true;
            }
        }
        error_log("syncProgramsFromAzuracast: Estado de playlists obtenido para " . count($playlistStatus) . " playlists");
    } else {
        error_log("syncProgramsFromAzuracast: No se pudo obtener estado de playlists (¿API Key configurada?)");
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
    $orphanedCount = 0;
    $reactivatedCount = 0;

    // Añadir nuevos programas (sin sobrescribir existentes)
    foreach ($detectedPrograms as $programName) {
        if (!isset($data['programs'][$programName])) {
            $data['programs'][$programName] = [
                'display_title' => '',
                'playlist_type' => 'program',
                'short_description' => '',
                'long_description' => '',
                'type' => '',
                'url' => '',
                'image' => '',
                'presenters' => '',
                'social_twitter' => '',
                'social_instagram' => '',
                'orphaned' => false
                // NO incluir created_at - solo programas manuales lo tienen
            ];
            $newCount++;
        } else {
            // Si el programa existía y estaba huérfano, preparar para verificación
            // La reactivación real se hace en el segundo bucle para considerar el estado de la playlist
        }
    }

    // Detectar programas huérfanos
    // Si tenemos API Key: usar lista de playlists (más preciso)
    // Si no: usar schedule (menos preciso, playlists sin horario parecerán huérfanas)
    $disabledCount = 0;
    foreach ($data['programs'] as $programName => $programInfo) {
        // Solo marcar como huérfano si NO es un programa manual (live)
        $isManual = ($programInfo['playlist_type'] ?? 'program') === 'live';

        if (!$isManual) {
            $shouldBeOrphaned = false;
            $reason = '';

            // Si tenemos acceso a la API de playlists, usar eso para determinar estado
            if (!empty($playlistStatus)) {
                // Caso 1: La playlist no existe en AzuraCast
                if (!isset($playlistStatus[$programName])) {
                    $shouldBeOrphaned = true;
                    $reason = 'no_en_azuracast';
                }
                // Caso 2: La playlist existe pero está deshabilitada
                elseif (!$playlistStatus[$programName]) {
                    $shouldBeOrphaned = true;
                    $reason = 'playlist_deshabilitada';
                    $disabledCount++;
                }
                // Caso 3: La playlist existe y está habilitada -> NO huérfano
            } else {
                // Sin API Key: usar schedule (menos preciso)
                if (!in_array($programName, $detectedPrograms)) {
                    $shouldBeOrphaned = true;
                    $reason = 'no_en_schedule';
                }
            }

            // Debug: mostrar estado actual
            $currentOrphaned = $data['programs'][$programName]['orphaned'] ?? false;

            if ($shouldBeOrphaned && !$currentOrphaned) {
                // Marcar como huérfano
                $data['programs'][$programName]['orphaned'] = true;
                $data['programs'][$programName]['orphan_reason'] = $reason;
                $orphanedCount++;
                error_log("syncProgramsFromAzuracast: Marcando '$programName' como huérfano (razón: $reason)");
            } elseif ($shouldBeOrphaned && $currentOrphaned) {
                // Ya es huérfano, pero actualizar el motivo si cambió
                $currentReason = $data['programs'][$programName]['orphan_reason'] ?? '';
                if ($currentReason !== $reason) {
                    $data['programs'][$programName]['orphan_reason'] = $reason;
                    error_log("syncProgramsFromAzuracast: Actualizando razón de '$programName': '$currentReason' -> '$reason'");
                }
            } elseif (!$shouldBeOrphaned && $currentOrphaned) {
                // Reactivar si ya no debería ser huérfano
                $data['programs'][$programName]['orphaned'] = false;
                unset($data['programs'][$programName]['orphan_reason']);
                $reactivatedCount++;
                error_log("syncProgramsFromAzuracast: Reactivando '$programName' (estaba huérfano, ahora activo)");
            }

            // Debug adicional
            $existsInPlaylists = isset($playlistStatus[$programName]) ? 'SI' : 'NO';
            $playlistEnabled = isset($playlistStatus[$programName]) ? ($playlistStatus[$programName] ? 'SI' : 'NO') : 'N/A';
            error_log("syncProgramsFromAzuracast: '$programName' - Existe en playlists: $existsInPlaylists, Habilitada: $playlistEnabled, Huérfano actual: " . ($currentOrphaned ? 'SI' : 'NO') . ", Debería ser huérfano: " . ($shouldBeOrphaned ? 'SI' : 'NO'));
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

    // Construir mensaje informativo
    $messages = [];
    if ($newCount > 0) {
        $messages[] = "$newCount nuevos";
    }
    if ($orphanedCount > 0) {
        if ($disabledCount > 0) {
            $messages[] = "$orphanedCount marcados como no encontrados ($disabledCount deshabilitadas)";
        } else {
            $messages[] = "$orphanedCount marcados como no encontrados";
        }
    }
    if ($reactivatedCount > 0) {
        $messages[] = "$reactivatedCount reactivados";
    }

    $message = empty($messages)
        ? "Sincronización completada. No hay cambios."
        : "Sincronización completada: " . implode(', ', $messages);

    // Añadir nota si no hay API Key
    if (empty($playlistStatus)) {
        $message .= " (Sin API Key: no se detectan playlists deshabilitadas)";
    }

    return [
        'success' => true,
        'message' => $message,
        'new_count' => $newCount,
        'orphaned_count' => $orphanedCount,
        'disabled_count' => $disabledCount,
        'reactivated_count' => $reactivatedCount,
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
        error_log("DEBUG RSS: URL vacía");
        return null;
    }

    error_log("DEBUG RSS: Procesando $rssUrl");

    // Verificar caché primero (ANTES de validaciones para evitar DNS lookups lentos)
    $cachedData = getRSSFromCache($rssUrl, $cacheTTL);
    // Si hay caché (incluso null), retornar inmediatamente sin validaciones
    if ($cachedData !== false) {
        if ($cachedData === null) {
            error_log("DEBUG RSS: Retornando NULL desde caché para $rssUrl");
        } else {
            error_log("DEBUG RSS: Retornando datos desde caché para $rssUrl - Título: " . ($cachedData['title'] ?? 'sin título'));
        }
        return $cachedData;
    }

    error_log("DEBUG RSS: No hay caché, consultando feed...");

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
        error_log("DEBUG RSS: ERROR - No se pudo descargar el feed: $rssUrl");
        // Cachear el fallo por 1 hora para no reintentar constantemente
        saveRSSToCache($rssUrl, null);
        return null;
    }

    error_log("DEBUG RSS: Feed descargado correctamente, tamaño: " . strlen($rssContent) . " bytes");

    // Parsear XML
    // SEGURIDAD: Deshabilitar entidades externas para prevenir XXE
    libxml_use_internal_errors(true);

    // LIBXML_NONET: Deshabilita carga de recursos externos (previene XXE)
    // LIBXML_NOCDATA: Procesa CDATA como texto
    // IMPORTANTE: NO usar LIBXML_NOENT, LIBXML_DTDLOAD, LIBXML_DTDATTR (vulnerables a XXE)
    $xml = simplexml_load_string($rssContent, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

    $errors = libxml_get_errors();
    libxml_clear_errors();

    if ($xml === false) {
        // Log detallado de errores XXE o XML malformado
        if (!empty($errors)) {
            $errorMessages = array_map(function($error) {
                return trim($error->message);
            }, $errors);
            error_log("DEBUG RSS: ERROR - Fallo al parsear XML: " . implode('; ', $errorMessages));
            error_log("[SAPO-Security] XML parsing failed for $rssUrl - Errors: " . implode('; ', $errorMessages));

            // Detectar posibles intentos XXE
            $rssLower = strtolower($rssContent);
            if (strpos($rssLower, '<!entity') !== false || strpos($rssLower, 'system') !== false) {
                error_log("[SAPO-Security] POSSIBLE XXE ATTEMPT BLOCKED - Feed contains suspicious entities: $rssUrl");
            }
        } else {
            error_log("DEBUG RSS: ERROR - Error desconocido al parsear XML: $rssUrl");
            error_log("[SAPO-Security] Error al parsear XML del RSS: $rssUrl");
        }

        // Cachear el fallo de parsing
        saveRSSToCache($rssUrl, null);
        return null;
    }

    error_log("DEBUG RSS: XML parseado correctamente");

    // Intentar obtener el primer item (último episodio)
    $item = null;

    // Formato RSS 2.0
    if (isset($xml->channel->item[0])) {
        $item = $xml->channel->item[0];
        error_log("DEBUG RSS: Encontrado item en formato RSS 2.0");
    }
    // Formato Atom
    elseif (isset($xml->entry[0])) {
        $item = $xml->entry[0];
        error_log("DEBUG RSS: Encontrado item en formato Atom");
    }

    if (!$item) {
        error_log("DEBUG RSS: ERROR - No se encontró ningún item/entry en el feed");
        return null;
    }

    // Extraer datos del episodio
    $title = (string)($item->title ?? '');
    $description = (string)($item->description ?? $item->summary ?? '');

    error_log("DEBUG RSS: Título del episodio: " . $title);

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

    error_log("DEBUG RSS: Fecha de publicación (raw): " . $pubDate);

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

    // Verificar antigüedad del episodio
    $isTooOld = false;
    if ($timestamp) {
        $daysSincePublished = (time() - $timestamp) / (60 * 60 * 24);
        error_log("DEBUG RSS: Días desde publicación: " . round($daysSincePublished, 1));

        if ($daysSincePublished > 30) {
            error_log("DEBUG RSS: ADVERTENCIA - Episodio demasiado antiguo (>" . round($daysSincePublished) . " días)");
            $isTooOld = true;
        }
    } else {
        error_log("DEBUG RSS: ADVERTENCIA - No se pudo parsear la fecha de publicación");
    }

    $result = [
        'title' => $title,
        'description' => strip_tags($description),
        'link' => $link,
        'audio_url' => $audioUrl,
        'pub_date' => $pubDate,
        'formatted_date' => $formattedDate,
        'too_old' => $isTooOld,  // Flag para indicar si el episodio tiene >30 días
        'days_since_published' => isset($daysSincePublished) ? round($daysSincePublished) : null
    ];

    if ($isTooOld) {
        error_log("DEBUG RSS: ⚠️ EPISODIO ANTIGUO - " . $title . " (>" . round($daysSincePublished) . " días)");
    } else {
        error_log("DEBUG RSS: ✅ SUCCESS - Episodio procesado correctamente: " . $title);
    }
    error_log("DEBUG RSS: Link: " . $link);
    error_log("DEBUG RSS: Audio URL: " . $audioUrl);

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
