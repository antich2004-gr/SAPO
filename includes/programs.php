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
 * Obtener el último episodio de un feed RSS
 *
 * @param string $rssUrl URL del feed RSS
 * @return array|null Datos del último episodio o null si hay error
 */
function getLatestEpisodeFromRSS($rssUrl) {
    if (empty($rssUrl)) {
        return null;
    }

    // Intentar cargar el RSS con timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; SAPO-RSS-Parser/1.0)'
        ]
    ]);

    $rssContent = @file_get_contents($rssUrl, false, $context);

    if ($rssContent === false) {
        error_log("Error al obtener RSS: $rssUrl");
        return null;
    }

    // Parsear XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rssContent);

    if ($xml === false) {
        error_log("Error al parsear XML del RSS: $rssUrl");
        return null;
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
    $link = (string)($item->link ?? '');
    $pubDate = (string)($item->pubDate ?? $item->published ?? '');

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

    // Formatear fecha
    $formattedDate = '';
    if ($pubDate) {
        $timestamp = strtotime($pubDate);
        if ($timestamp) {
            $formattedDate = date('d/m/Y', $timestamp);
        }
    }

    return [
        'title' => $title,
        'description' => strip_tags($description),
        'link' => $link,
        'audio_url' => $audioUrl,
        'pub_date' => $pubDate,
        'formatted_date' => $formattedDate
    ];
}
