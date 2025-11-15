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

    // Cargar programas existentes
    $data = loadProgramsDB($username);
    $newCount = 0;

    // Añadir nuevos programas (sin sobrescribir existentes)
    foreach ($detectedPrograms as $programName) {
        if (!isset($data['programs'][$programName])) {
            $data['programs'][$programName] = [
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
