<?php
// includes/podcasts.php - Gestión de podcasts y serverlist

function getServerListPath($username) {
    $config = getConfig();
    $basePath = $config['base_path'];
    $subsFolder = $config['subscriptions_folder'];
    
    if (empty($basePath)) {
        return false;
    }

    // Construir la ruta
    $path = $basePath . DIRECTORY_SEPARATOR . $username . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $subsFolder . DIRECTORY_SEPARATOR . 'serverlist.txt';

    // Validar que la ruta este dentro del base_path (prevenir path traversal)
    $realBasePath = realpath($basePath);
    $realPath = realpath(dirname($path));

    // Si el directorio no existe aun, validar el dirname hasta el nivel que exista
    if ($realPath === false) {
        // No validar aun si el dir no existe, pero verificar que username no contenga ../
        if (strpos($username, '..') !== false || strpos($username, DIRECTORY_SEPARATOR) !== false) {
            return false;
        }
    } elseif ($realBasePath !== false && strpos($realPath, $realBasePath) !== 0) {
        // La ruta real esta fuera del base_path
        return false;
    }

    return $path;
}



function getCaducidadesPath($username) {
    $config = getConfig();
    $basePath = $config['base_path'];
    $subsFolder = $config['subscriptions_folder'];
    
    if (empty($basePath)) {
        return false;
    }

    // Construir la ruta
    $path = $basePath . DIRECTORY_SEPARATOR . $username . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $subsFolder . DIRECTORY_SEPARATOR . 'caducidades.txt';

    // Validar que la ruta este dentro del base_path (prevenir path traversal)
    $realBasePath = realpath($basePath);
    $realPath = realpath(dirname($path));

    // Si el directorio no existe aun, validar el dirname hasta el nivel que exista
    if ($realPath === false) {
        // No validar aun si el dir no existe, pero verificar que username no contenga ../
        if (strpos($username, '..') !== false || strpos($username, DIRECTORY_SEPARATOR) !== false) {
            return false;
        }
    } elseif ($realBasePath !== false && strpos($realPath, $realBasePath) !== 0) {
        // La ruta real esta fuera del base_path
        return false;
    }

    return $path;
}


function readCaducidades($username) {
    $path = getCaducidadesPath($username);
    if (!$path || !file_exists($path)) return [];
    
    $caducidades = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] == '#') {
            continue;
        }
        
        $parts = explode(':', $line, 2);
        if (count($parts) == 2) {
            $carpeta = trim($parts[0]);
            $dias = intval(trim($parts[1]));
            $caducidades[$carpeta] = $dias;
        }
    }
    return $caducidades;
}

function writeCaducidades($username, $caducidades) {
    $path = getCaducidadesPath($username);
    if (!$path) {
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }

    $content = "# Caducidades por podcast - Generado por SAPO\n";
    $content .= "# Formato: nombre_podcast:dias\n";
    $content .= "# Si no se especifica, se usa el valor por defecto (30 dias)\n\n";

    foreach ($caducidades as $podcastName => $dias) {
        $content .= $podcastName . ':' . $dias . "\n";
    }

    $result = file_put_contents($path, $content);
    return $result !== false;
}

function setCaducidad($username, $podcastName, $dias) {
    $caducidades = readCaducidades($username);
    $caducidades[$podcastName] = intval($dias);
    return writeCaducidades($username, $caducidades);
}

function deleteCaducidad($username, $podcastName) {
    $caducidades = readCaducidades($username);
    unset($caducidades[$podcastName]);
    return writeCaducidades($username, $caducidades);
}

function readServerList($username) {
    $path = getServerListPath($username);
    if (!$path || !file_exists($path)) return [];

    $podcasts = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // Detectar si es un podcast pausado
        $paused = false;
        if (strpos($line, '# PAUSADO:') === 0) {
            $paused = true;
            $line = trim(substr($line, 10)); // Eliminar "# PAUSADO:"
        } elseif ($line[0] == '#') {
            // Otros comentarios se ignoran
            continue;
        }

        $parts = preg_split('/\s+/', $line, 3);

        if (count($parts) == 2) {
            $podcasts[] = [
                'url' => $parts[0],
                'category' => 'Sin_categoria',
                'name' => $parts[1],
                'paused' => $paused
            ];
        } elseif (count($parts) == 3) {
            $podcasts[] = [
                'url' => $parts[0],
                'category' => $parts[1],
                'name' => $parts[2],
                'paused' => $paused
            ];
        }
    }
    return $podcasts;
}

function writeServerList($username, $podcasts) {
    $path = getServerListPath($username);
    if (!$path) {
        return false;
    }
    
    $dir = dirname($path);
    
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }
    
    $user = getCurrentUser();
    $stationName = $user ? $user['station_name'] : 'Emisora';
    
    $dateTime = date('d/m/Y H:i:s');
    $content = "# Serverlist generado por SAPO - Radiobot\n";
    $content .= "# Emisora: $stationName\n";
    $content .= "# Fecha de generacion: $dateTime\n";
    $content .= "# Formato: URL_RSS Carpeta_categoria Nombre_Podcast\n";
    $content .= "# Los podcasts pausados se comentan con '# PAUSADO:' y no se descargan\n";
    $content .= "\n";

    foreach ($podcasts as $podcast) {
        $sanitizedName = sanitizePodcastName($podcast['name']);

        // Si la categoría está vacía, usar formato de 2 campos (URL nombre)
        // Si la categoría tiene valor, usar formato de 3 campos (URL categoria nombre)
        if (empty($podcast['category'])) {
            $line = $podcast['url'] . ' ' . $sanitizedName;
        } else {
            $sanitizedCategory = sanitizePodcastName($podcast['category']);
            $line = $podcast['url'] . ' ' . $sanitizedCategory . ' ' . $sanitizedName;
        }

        // Si el podcast está pausado, comentar la línea
        if (isset($podcast['paused']) && $podcast['paused'] === true) {
            $content .= "# PAUSADO: " . $line . "\n";
        } else {
            $content .= $line . "\n";
        }
    }
    
    return file_put_contents($path, $content) !== false;
}

function addPodcast($username, $url, $category, $name, $caducidad = 30, $duracion = '') {
    $podcasts = readServerList($username);
    
    foreach ($podcasts as $podcast) {
        if ($podcast['url'] == $url) {
            return ['success' => false, 'error' => 'El podcast ya existe'];
        }
    }

    // Solo sanitizar la categoría si no está vacía
    $sanitizedCategory = empty($category) ? '' : sanitizePodcastName($category);
    $sanitizedName = sanitizePodcastName($name);
    
    $podcasts[] = [
        'url' => $url,
        'category' => $sanitizedCategory,
        'name' => $sanitizedName
    ];
    
    if (writeServerList($username, $podcasts)) {
        // Actualizar caducidades.txt solo si no es 30 (valor por defecto)
        if ($caducidad != 30) {
            setCaducidad($username, $sanitizedName, $caducidad);
        }
        
        
        // Guardar duracion si no es vacia
        if (!empty($duracion)) {
            $duraciones = readDuraciones($username);
            $duraciones[$sanitizedName] = $duracion;
            writeDuraciones($username, $duraciones);
        }

        return [
            'success' => true,
            'message' => 'Podcast agregado correctamente',
            'sanitized_category' => $sanitizedCategory,
            'sanitized_name' => $sanitizedName
        ];
    } else {
        return ['success' => false, 'error' => 'Error al guardar el podcast'];
    }
}

function editPodcast($username, $index, $url, $category, $name, $caducidad = 30, $duracion = '') {
    $podcasts = readServerList($username);

    // Ordenar alfabéticamente igual que en user.php para que los índices coincidan
    usort($podcasts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $podcasts = array_values($podcasts);

    if ($index < 0 || $index >= count($podcasts)) {
        return ['success' => false, 'error' => 'Podcast no encontrado'];
    }

    // Guardar estado original para poder revertir si es necesario
    $oldCategory = $podcasts[$index]['category'];
    $oldName = $podcasts[$index]['name'];
    $oldUrl = $podcasts[$index]['url'];

    // Releer la lista original (sin ordenar) para hacer los cambios
    $podcastsOriginal = readServerList($username);
    $oldPodcasts = $podcastsOriginal; // Backup completo

    // Verificar que la URL no exista en otro podcast
    foreach ($podcastsOriginal as $i => $podcast) {
        if ($podcast['url'] == $url && $podcast['url'] != $oldUrl) {
            return ['success' => false, 'error' => 'Esta URL ya existe en otro podcast'];
        }
    }

    // Solo sanitizar la categoría si no está vacía
    $sanitizedCategory = empty($category) ? '' : sanitizePodcastName($category);
    $sanitizedName = sanitizePodcastName($name);

    // Buscar el podcast por URL original y actualizarlo
    $found = false;
    $realIndex = -1;
    foreach ($podcastsOriginal as $i => $podcast) {
        if ($podcast['url'] === $oldUrl) {
            $podcastsOriginal[$i] = [
                'url' => $url,
                'category' => $sanitizedCategory,
                'name' => $sanitizedName
            ];
            // Preservar el estado de pausa si existía
            if (isset($podcast['paused'])) {
                $podcastsOriginal[$i]['paused'] = $podcast['paused'];
            }
            $found = true;
            $realIndex = $i;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'error' => 'Podcast no encontrado en la lista'];
    }

    if (writeServerList($username, $podcastsOriginal)) {
        // NUEVO: Si cambió la categoría, mover archivos físicos
        $moveResult = ['success' => true, 'moved' => 0];
        $categoryChanged = false;

        if ($oldCategory !== $sanitizedCategory) {
            $categoryChanged = true;

            // Usar el nombre ANTIGUO del podcast para buscar los archivos
            $moveResult = movePodcastFiles($username, $oldName, $oldCategory, $sanitizedCategory);

            // DECISIÓN 1.A: Si falla el movimiento, revertir TODO
            if (!$moveResult['success']) {
                // Revertir serverlist.txt al estado original
                writeServerList($username, $oldPodcasts);

                return [
                    'success' => false,
                    'error' => 'Error al mover archivos: ' . ($moveResult['error'] ?? 'Error desconocido') . '. Se han revertido todos los cambios.'
                ];
            }
        }

        // Si cambió el nombre del podcast, renombrar directorio
        // (ya sea en la misma categoría o después de haberlo movido a nueva categoría)
        if ($oldName !== $sanitizedName) {
            // Determinar en qué categoría está ahora el podcast
            $currentCategory = $categoryChanged ? $sanitizedCategory : $oldCategory;

            $renameResult = renamePodcastDirectory($username, $oldName, $sanitizedName, $currentCategory);

            // Si falla el renombrado, revertir TODO
            if (!$renameResult['success']) {
                // Revertir serverlist.txt al estado original
                writeServerList($username, $oldPodcasts);

                // Si se había movido de categoría, intentar revertir el movimiento también
                if ($categoryChanged) {
                    // Intentar mover de vuelta (best effort, puede fallar)
                    @movePodcastFiles($username, $oldName, $sanitizedCategory, $oldCategory);
                }

                return [
                    'success' => false,
                    'error' => 'Error al renombrar directorio: ' . ($renameResult['error'] ?? 'Error desconocido') . '. Se han revertido todos los cambios.'
                ];
            }
        }

        // Si cambió el nombre del podcast, eliminar la entrada antigua
        if ($oldName !== $sanitizedName) {
            deleteCaducidad($username, $oldName);
        }

        // Actualizar caducidades.txt solo si no es 30 (valor por defecto)
        if ($caducidad != 30) {
            setCaducidad($username, $sanitizedName, $caducidad);
        } else {
            // Si es 30, eliminar entrada (usará el default)
            deleteCaducidad($username, $sanitizedName);
        }

        // Actualizar duraciones.txt
        $duraciones = readDuraciones($username);
        if (!empty($duracion)) {
            $duraciones[$sanitizedName] = $duracion;
        } else {
            // Si esta vacio, eliminar la entrada
            unset($duraciones[$sanitizedName]);
        }
        // Si cambio el nombre, eliminar la entrada antigua
        if ($oldName !== $sanitizedName) {
            unset($duraciones[$oldName]);
        }
        writeDuraciones($username, $duraciones);

        $result = [
            'success' => true,
            'message' => 'Podcast actualizado correctamente',
            'sanitized_category' => $sanitizedCategory,
            'sanitized_name' => $sanitizedName
        ];

        // Agregar información del movimiento de archivos
        if ($categoryChanged) {
            $result['category_changed'] = true;
            $result['files_moved'] = $moveResult['moved'];
            if (isset($moveResult['message'])) {
                $result['move_message'] = $moveResult['message'];
            }
        }

        return $result;
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el podcast'];
    }
}

function deletePodcast($username, $index) {
    $podcasts = readServerList($username);

    // Ordenar alfabéticamente igual que en user.php para que los índices coincidan
    usort($podcasts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $podcasts = array_values($podcasts);

    if ($index < 0 || $index >= count($podcasts)) {
        return ['success' => false, 'error' => 'Podcast no encontrado'];
    }

    // Obtener URL y nombre del podcast a eliminar
    $targetUrl = $podcasts[$index]['url'];
    $deletedName = $podcasts[$index]['name'];

    // Releer la lista original (sin ordenar) para hacer los cambios
    $podcastsOriginal = readServerList($username);

    // Buscar y eliminar el podcast por URL
    $found = false;
    foreach ($podcastsOriginal as $key => $podcast) {
        if ($podcast['url'] === $targetUrl) {
            array_splice($podcastsOriginal, $key, 1);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'error' => 'Podcast no encontrado en la lista'];
    }

    if (writeServerList($username, $podcastsOriginal)) {
        // Eliminar entrada de caducidad del podcast
        deleteCaducidad($username, $deletedName);

        return ['success' => true, 'message' => 'Podcast eliminado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al eliminar el podcast'];
    }
}

function importPodcasts($username, $fileContent) {
    $lines = explode("\n", $fileContent);
    $podcasts = readServerList($username);
    $importedCount = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] == '#') {
            continue;
        }
        
        $parts = preg_split('/\s+/', $line, 3);
        if (count($parts) != 3) {
            continue;
        }
        
        $exists = false;
        foreach ($podcasts as $podcast) {
            if ($podcast['url'] == $parts[0]) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $podcasts[] = [
                'url' => $parts[0],
                'category' => $parts[1],
                'name' => $parts[2]
            ];
            $importedCount++;
        }
    }
    
    if ($importedCount > 0) {
        if (writeServerList($username, $podcasts)) {
            importCategoriesFromServerList($username);
            return ['success' => true, 'count' => $importedCount];
        }
    }
    
    return ['success' => false, 'count' => 0];
}

function executePodget($username) {
    $scriptPath = '/home/radioslibres/cliente_rrll/cliente_rrll.sh';
    $logFile = '/var/log/sapo/podget_' . $username . '.log';

    // SEGURIDAD: Validación estricta del username contra inyección de comandos
    if (!validateUsernameStrict($username)) {
        return [
            'success' => false,
            'message' => 'Nombre de usuario inválido o no permitido por políticas de seguridad.'
        ];
    }

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!file_exists($scriptPath)) {
        error_log("[SAPO-Security] executePodget: Script no encontrado: $scriptPath | Usuario: $username");
        return ['success' => false, 'message' => 'El script no se encontro en el servidor'];
    }

    if (!is_executable($scriptPath)) {
        error_log("[SAPO-Security] executePodget: Script sin permisos de ejecución: $scriptPath | Usuario: $username");
        return ['success' => false, 'message' => 'El script no tiene permisos de ejecucion'];
    }

    // SEGURIDAD: Logging de auditoría ANTES de ejecutar
    $userInfo = isset($_SESSION['user_id']) ? "ID: {$_SESSION['user_id']}, Session: {$_SESSION['username']}" : "No autenticado";
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');

    error_log("[SAPO-Security] EXEC PODGET | Usuario objetivo: $username | Ejecutado por: $userInfo | IP: $clientIP | Timestamp: $timestamp");

    // Ejecutar el script como usuario radioslibres usando sudo
    $command = 'sudo -u radioslibres /bin/bash ' . escapeshellarg($scriptPath) . ' --emisora ' . escapeshellarg($username);
    $command .= ' > ' . escapeshellarg($logFile) . ' 2>&1 &';

    // SEGURIDAD: Usar proc_open para mejor control (timeout implícito por background)
    // Nota: exec() con & es aceptable para procesos background, pero logging es crítico
    exec($command, $output, $returnCode);

    // Logging post-ejecución
    error_log("[SAPO-Security] EXEC PODGET completado | Usuario: $username | Return code: $returnCode | Comando: " . substr($command, 0, 200));

    return [
        'success' => true,
        'message' => 'Las descargas se estan ejecutando. Log: ' . $logFile
    ];
}


/**
 * Leer archivo duraciones.txt
 * Formato: nombre_podcast:30M
 * Retorna array asociativo ['nombre_podcast' => '30M']
 */
function readDuraciones($username) {
    $duracionesPath = getDuracionesPath($username);
    $duraciones = [];

    if (!file_exists($duracionesPath)) {
        return $duraciones;
    }

    $lines = file($duracionesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $duraciones;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $podcastName = trim($parts[0]);
            $duracion = trim($parts[1]);
            $duraciones[$podcastName] = $duracion;
        }
    }

    return $duraciones;
}

/**
 * Escribir archivo duraciones.txt
 */
function writeDuraciones($username, $duraciones) {
    $duracionesPath = getDuracionesPath($username);

    // Asegurar que el directorio existe
    $dir = dirname($duracionesPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $content = "";
    foreach ($duraciones as $podcastName => $duracion) {
        if (!empty($duracion)) {
            $content .= slugify($podcastName) . ":" . $duracion . "\n";
        }
    }

    return file_put_contents($duracionesPath, $content, LOCK_EX) !== false;
}

/**
 * Obtener ruta del archivo duraciones.txt
 */
function getDuracionesPath($username) {
    $config = getConfig();
    $basePath = rtrim($config['base_path'], '/\\');
    $subscriptionsFolder = trim($config['subscriptions_folder'], '/\\');

    $userSlug = slugify($username);
    return $basePath . DIRECTORY_SEPARATOR . $userSlug . DIRECTORY_SEPARATOR .
           'media' . DIRECTORY_SEPARATOR . $subscriptionsFolder . DIRECTORY_SEPARATOR .
           'duraciones.txt';
}

/**
 * Obtener opciones disponibles de duración
 */
function getDuracionesOptions() {
    return [
        '' => 'Sin límite',
        '30M' => '30 minutos',
        '1H' => '1 hora',
        '1H30' => '1 hora 30 minutos',
        '2H' => '2 horas',
        '2H30' => '2 horas 30 minutos',
        '3H' => '3 horas'
    ];
}

/**
 * Pausar un podcast (comentar línea en serverlist.txt)
 */
function pausePodcast($username, $index) {
    $podcasts = readServerList($username);

    // Ordenar alfabéticamente igual que en user.php para que los índices coincidan
    usort($podcasts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $podcasts = array_values($podcasts);

    if (!isset($podcasts[$index])) {
        return ['success' => false, 'message' => 'Podcast no encontrado'];
    }

    // Obtener la URL del podcast a pausar (la URL es única y no cambia)
    $targetUrl = $podcasts[$index]['url'];

    // Releer la lista original (sin ordenar) para hacer los cambios
    $podcastsOriginal = readServerList($username);

    // Buscar el podcast por URL y marcarlo como pausado
    $found = false;
    foreach ($podcastsOriginal as &$podcast) {
        if ($podcast['url'] === $targetUrl) {
            $podcast['paused'] = true;
            $found = true;
            break;
        }
    }
    unset($podcast); // Romper referencia

    if (!$found) {
        return ['success' => false, 'message' => 'Podcast no encontrado en la lista'];
    }

    // Guardar
    if (writeServerList($username, $podcastsOriginal)) {
        return [
            'success' => true,
            'message' => 'Podcast pausado correctamente. No se descargarán nuevos episodios.'
        ];
    } else {
        return ['success' => false, 'message' => 'Error al pausar el podcast'];
    }
}

/**
 * Reanudar un podcast (descomentar línea en serverlist.txt)
 */
function resumePodcast($username, $index) {
    $podcasts = readServerList($username);

    // Ordenar alfabéticamente igual que en user.php para que los índices coincidan
    usort($podcasts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $podcasts = array_values($podcasts);

    if (!isset($podcasts[$index])) {
        return ['success' => false, 'message' => 'Podcast no encontrado'];
    }

    // Obtener la URL del podcast a reanudar (la URL es única y no cambia)
    $targetUrl = $podcasts[$index]['url'];

    // Releer la lista original (sin ordenar) para hacer los cambios
    $podcastsOriginal = readServerList($username);

    // Buscar el podcast por URL y marcarlo como no pausado
    $found = false;
    foreach ($podcastsOriginal as &$podcast) {
        if ($podcast['url'] === $targetUrl) {
            $podcast['paused'] = false;
            $found = true;
            break;
        }
    }
    unset($podcast); // Romper referencia

    if (!$found) {
        return ['success' => false, 'message' => 'Podcast no encontrado en la lista'];
    }

    // Guardar
    if (writeServerList($username, $podcastsOriginal)) {
        return [
            'success' => true,
            'message' => 'Podcast reanudado correctamente. Se reanudarán las descargas.'
        ];
    } else {
        return ['success' => false, 'message' => 'Error al reanudar el podcast'];
    }
}

/**
 * Obtener ruta del archivo default_caducidad.txt
 */
function getDefaultCaducidadPath($username) {
    $config = getConfig();
    $basePath = $config['base_path'] ?? '';
    $subscriptionsFolder = $config['subscriptions_folder'] ?? 'Podcasts';

    if (empty($basePath)) {
        return false;
    }

    $userSlug = slugify($username);
    return $basePath . DIRECTORY_SEPARATOR . $userSlug . DIRECTORY_SEPARATOR .
           'media' . DIRECTORY_SEPARATOR . $subscriptionsFolder . DIRECTORY_SEPARATOR .
           'default_caducidad.txt';
}

/**
 * Obtener caducidad por defecto para un usuario
 * Retorna el valor guardado o 30 días por defecto
 */
function getDefaultCaducidad($username) {
    $path = getDefaultCaducidadPath($username);
    if (!$path || !file_exists($path)) {
        return 30; // Valor por defecto
    }

    $content = file_get_contents($path);
    $dias = intval(trim($content));

    // Validar rango
    if ($dias < 1 || $dias > 365) {
        return 30;
    }

    return $dias;
}

/**
 * Establecer caducidad por defecto para un usuario
 */
function setDefaultCaducidad($username, $dias) {
    $path = getDefaultCaducidadPath($username);
    if (!$path) {
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }

    // Validar rango
    $dias = intval($dias);
    if ($dias < 1 || $dias > 365) {
        $dias = 30;
    }

    $content = "# Caducidad por defecto - Generado por SAPO\n";
    $content .= "# Este valor se usará al agregar nuevos podcasts\n";
    $content .= $dias;

    $result = file_put_contents($path, $content);
    return $result !== false;
}

?>
