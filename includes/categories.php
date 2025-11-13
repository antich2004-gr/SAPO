<?php
// includes/categories.php - Gestión de categorías de podcasts

function getUserCategories($username) {
    $userData = getUserDB($username);
    return $userData['categories'] ?? [];
}

function saveUserCategory($username, $category) {
    $userData = getUserDB($username);
    if (!isset($userData['categories'])) {
        $userData['categories'] = [];
    }

    $sanitized = sanitizePodcastName($category);
    if (!in_array($sanitized, $userData['categories'])) {
        $userData['categories'][] = $sanitized;
        sort($userData['categories']);
        saveUserDB($username, $userData);
        return $sanitized;
    }
    return false;
}

function deleteUserCategory($username, $category) {
    // Verificar si la categoría está en uso
    $podcasts = readServerList($username);
    foreach ($podcasts as $podcast) {
        if ($podcast['category'] === $category) {
            return false; // Categoría en uso, no se puede eliminar
        }
    }

    $userData = getUserDB($username);
    if (isset($userData['categories'])) {
        $userData['categories'] = array_values(
            array_filter($userData['categories'], function($cat) use ($category) {
                return $cat !== $category;
            })
        );
        saveUserDB($username, $userData);

        // También eliminar de caducidades.txt
        deleteCaducidad($username, $category);

        return true;
    }
    return false;
}

function importCategoriesFromServerList($username) {
    $podcasts = readServerList($username);
    $userData = getUserDB($username);

    if (!isset($userData['categories'])) {
        $userData['categories'] = [];
    }

    $categoriesFound = [];
    $existingCategories = $userData['categories'];

    foreach ($podcasts as $podcast) {
        $category = $podcast['category'];
        if (!in_array($category, $existingCategories)) {
            $userData['categories'][] = $category;
            $categoriesFound[] = $category;
        }
    }

    if (!empty($categoriesFound)) {
        $userData['categories'] = array_unique($userData['categories']);
        sort($userData['categories']);
        saveUserDB($username, $userData);
    }

    return $categoriesFound;
}

/**
 * Sincronizar categorías desde el disco físico
 * Escanea la carpeta media/Podcasts y registra todas las carpetas encontradas
 *
 * @param string $username Usuario propietario
 * @return array Array con 'success', 'synced' (cantidad), 'categories' (nombres)
 */
function syncCategoriesFromDisk($username) {
    $config = getConfig();
    $basePath = $config['base_path'];

    if (empty($basePath)) {
        return ['success' => false, 'error' => 'Base path no configurado'];
    }

    $userMediaPath = $basePath . DIRECTORY_SEPARATOR . $username .
                     DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Podcasts';

    if (!is_dir($userMediaPath)) {
        return ['success' => false, 'error' => 'Directorio de podcasts no existe'];
    }

    // Obtener todas las carpetas del disco
    $diskDirs = glob($userMediaPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    if ($diskDirs === false) {
        return ['success' => false, 'error' => 'Error al leer directorio'];
    }

    $diskCategories = array_map('basename', $diskDirs);

    // Obtener categorías actuales del sistema
    $userData = getUserDB($username);
    if (!isset($userData['categories'])) {
        $userData['categories'] = [];
    }

    // Encontrar categorías nuevas (en disco pero no en sistema)
    $newCategories = array_diff($diskCategories, $userData['categories']);

    if (empty($newCategories)) {
        return [
            'success' => true,
            'synced' => 0,
            'categories' => [],
            'message' => 'No hay categorías nuevas para sincronizar'
        ];
    }

    // Agregar nuevas categorías al sistema
    foreach ($newCategories as $category) {
        $userData['categories'][] = $category;
    }

    $userData['categories'] = array_unique($userData['categories']);
    sort($userData['categories']);

    if (!saveUserDB($username, $userData)) {
        return ['success' => false, 'error' => 'Error al guardar users.json'];
    }

    return [
        'success' => true,
        'synced' => count($newCategories),
        'categories' => array_values($newCategories),
        'message' => count($newCategories) . ' categoría(s) sincronizada(s) desde el disco'
    ];
}

function isCategoryInUse($username, $category) {
    $podcasts = readServerList($username);
    foreach ($podcasts as $podcast) {
        if ($podcast['category'] === $category) {
            return true;
        }
    }
    return false;
}

/**
 * Mover archivos de un podcast de una categoría a otra
 *
 * @param string $username Usuario propietario
 * @param string $podcastName Nombre del podcast (sanitizado)
 * @param string $oldCategory Categoría origen
 * @param string $newCategory Categoría destino
 * @return array ['success' => bool, 'moved' => int, 'error' => string, 'files' => array]
 */
function movePodcastFiles($username, $podcastName, $oldCategory, $newCategory) {
    // VALIDACIÓN 1: Configuración
    $config = getConfig();
    if (empty($config['base_path'])) {
        return ['success' => false, 'error' => 'Base path no configurado'];
    }

    // VALIDACIÓN 2: Seguridad - path traversal
    if (strpos($username, '..') !== false ||
        strpos($username, '/') !== false ||
        strpos($username, '\\') !== false) {
        return ['success' => false, 'error' => 'Username contiene caracteres inválidos'];
    }

    // VALIDACIÓN 3: Nombres vacíos
    if (empty($podcastName) || empty($oldCategory) || empty($newCategory)) {
        return ['success' => false, 'error' => 'Parámetros vacíos'];
    }

    // VALIDACIÓN 4: Si son la misma categoría, no hacer nada
    if ($oldCategory === $newCategory) {
        return ['success' => true, 'moved' => 0, 'message' => 'Categorías idénticas, sin cambios'];
    }

    // Construir rutas
    $basePath = $config['base_path'];
    $oldCategoryPath = $basePath . DIRECTORY_SEPARATOR . $username .
                       DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
                       'Podcasts' . DIRECTORY_SEPARATOR . $oldCategory;

    $newCategoryPath = $basePath . DIRECTORY_SEPARATOR . $username .
                       DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
                       'Podcasts' . DIRECTORY_SEPARATOR . $newCategory;

    // VALIDACIÓN 5: Carpeta origen existe
    if (!is_dir($oldCategoryPath)) {
        // No es error crítico, simplemente no hay archivos
        return ['success' => true, 'moved' => 0, 'message' => 'Carpeta origen no existe, sin archivos que mover'];
    }

    // VALIDACIÓN 6: Crear carpeta destino si no existe
    if (!is_dir($newCategoryPath)) {
        if (!@mkdir($newCategoryPath, 0755, true)) {
            return ['success' => false, 'error' => 'No se pudo crear carpeta destino'];
        }
    }

    // VALIDACIÓN 7: Permisos de escritura
    if (!is_writable($oldCategoryPath)) {
        return ['success' => false, 'error' => 'Sin permisos de escritura en carpeta origen'];
    }
    if (!is_writable($newCategoryPath)) {
        return ['success' => false, 'error' => 'Sin permisos de escritura en carpeta destino'];
    }

    // ESTRUCTURA REAL: /Categoría/NombrePodcast/archivos.mp3
    // Necesitamos mover el DIRECTORIO completo del podcast
    $podcastDirInOldCategory = $oldCategoryPath . DIRECTORY_SEPARATOR . $podcastName;
    $podcastDirInNewCategory = $newCategoryPath . DIRECTORY_SEPARATOR . $podcastName;

    // Si el directorio del podcast no existe en origen, no hay nada que mover
    if (!is_dir($podcastDirInOldCategory)) {
        return ['success' => true, 'moved' => 0, 'message' => 'El directorio del podcast no existe en la categoría origen'];
    }

    // Si el directorio ya existe en destino, error (no sobrescribir directorios)
    if (is_dir($podcastDirInNewCategory)) {
        return [
            'success' => false,
            'error' => 'Ya existe un directorio con el mismo nombre en la categoría destino'
        ];
    }

    // Contar archivos antes de mover (para estadísticas)
    $filesBeforeMove = glob($podcastDirInOldCategory . DIRECTORY_SEPARATOR . '*.{mp3,ogg,wav,m4a}', GLOB_BRACE);
    $fileCount = $filesBeforeMove ? count($filesBeforeMove) : 0;

    // Intentar mover el directorio completo
    if (@rename($podcastDirInOldCategory, $podcastDirInNewCategory)) {
        return [
            'success' => true,
            'moved' => $fileCount,
            'message' => "Directorio del podcast movido correctamente con $fileCount archivo(s)"
        ];
    } else {
        return [
            'success' => false,
            'error' => 'No se pudo mover el directorio del podcast. Verifica permisos.'
        ];
    }
}

/**
 * Obtener estadísticas de una categoría
 *
 * @param string $username Usuario propietario
 * @param string $category Nombre de la categoría
 * @return array Estadísticas de la categoría
 */
function getCategoryStats($username, $category) {
    $config = getConfig();
    $basePath = $config['base_path'];

    // Ruta física de la categoría
    $categoryPath = $basePath . DIRECTORY_SEPARATOR . $username .
                    DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
                    'Podcasts' . DIRECTORY_SEPARATOR . $category;

    // DEBUG TEMPORAL - Eliminar después de diagnosticar
    error_log("DEBUG getCategoryStats - Username: $username");
    error_log("DEBUG getCategoryStats - Category: $category");
    error_log("DEBUG getCategoryStats - BasePath: $basePath");
    error_log("DEBUG getCategoryStats - Full path: $categoryPath");
    error_log("DEBUG getCategoryStats - Directory exists: " . (is_dir($categoryPath) ? 'YES' : 'NO'));

    $stats = [
        'podcasts' => 0,
        'files' => 0,
        'size' => 0,
        'last_download' => null,
        'exists' => false,
        'status' => 'empty' // empty, inactive, active
    ];

    // Verificar si la carpeta existe
    if (!is_dir($categoryPath)) {
        error_log("DEBUG getCategoryStats - Returning empty stats (directory not found)");
        return $stats;
    }

    $stats['exists'] = true;

    // Contar archivos de audio
    // NOTA: Los archivos están en subdirectorios (un subdirectorio por podcast)
    // Estructura: /Categoría/Podcast/archivo.mp3
    // Por lo tanto, buscamos en subdirectorios: /Categoría/*/*.{mp3,ogg,wav,m4a}
    $files = glob($categoryPath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.{mp3,ogg,wav,m4a}', GLOB_BRACE);

    if ($files === false) {
        $files = [];
    }

    error_log("DEBUG getCategoryStats - Files found: " . count($files));
    if (count($files) > 0) {
        error_log("DEBUG getCategoryStats - First 3 files: " . implode(', ', array_slice(array_map('basename', $files), 0, 3)));
    }

    $stats['files'] = count($files);

    // Calcular tamaño total
    foreach ($files as $file) {
        if (is_file($file)) {
            $stats['size'] += filesize($file);
        }
    }

    // Última modificación
    if (!empty($files)) {
        $mtimes = array_map('filemtime', $files);
        $stats['last_download'] = max($mtimes);
    }

    // Contar podcasts asignados a esta categoría
    $podcasts = readServerList($username);
    foreach ($podcasts as $podcast) {
        if ($podcast['category'] === $category) {
            $stats['podcasts']++;
        }
    }

    // Determinar estado
    if ($stats['files'] == 0) {
        $stats['status'] = 'empty';
    } elseif ($stats['last_download'] !== null) {
        $daysSinceLastDownload = (time() - $stats['last_download']) / (60 * 60 * 24);
        if ($daysSinceLastDownload <= 7) {
            $stats['status'] = 'active';
        } elseif ($daysSinceLastDownload <= 30) {
            $stats['status'] = 'warning';
        } else {
            $stats['status'] = 'inactive';
        }
    }

    return $stats;
}

/**
 * Obtener todas las categorías con sus estadísticas
 *
 * @param string $username Usuario propietario
 * @return array Array de categorías con estadísticas
 */
function getAllCategoriesWithStats($username) {
    $categories = getUserCategories($username);
    $result = [];

    foreach ($categories as $category) {
        $stats = getCategoryStats($username, $category);
        $stats['name'] = $category;
        $result[] = $stats;
    }

    // Ordenar por última descarga (más reciente primero)
    usort($result, function($a, $b) {
        if ($a['last_download'] === null && $b['last_download'] === null) {
            return strcmp($a['name'], $b['name']);
        }
        if ($a['last_download'] === null) return 1;
        if ($b['last_download'] === null) return -1;
        return $b['last_download'] - $a['last_download'];
    });

    return $result;
}

/**
 * Formatear tamaño en bytes a formato legible
 *
 * @param int $bytes Tamaño en bytes
 * @return string Tamaño formateado
 */
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log(1024));

    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/**
 * Formatear tiempo relativo (ej: "hace 2 horas")
 *
 * @param int $timestamp Timestamp Unix
 * @return string Tiempo relativo
 */
function timeAgo($timestamp) {
    if ($timestamp === null) {
        return 'Nunca';
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Ahora mismo';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "Hace $mins minuto" . ($mins > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Hace $hours hora" . ($hours > 1 ? 's' : '');
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return "Hace $days día" . ($days > 1 ? 's' : '');
    } else {
        $months = floor($diff / 2592000);
        return "Hace $months mes" . ($months > 1 ? 'es' : '');
    }
}

/**
 * Obtener categorías vacías (sin archivos y sin podcasts)
 *
 * @param string $username Usuario propietario
 * @return array Array de categorías vacías
 */
function getEmptyCategories($username) {
    $categories = getUserCategories($username);
    $emptyCategories = [];

    foreach ($categories as $category) {
        $stats = getCategoryStats($username, $category);

        if ($stats['files'] == 0 && $stats['podcasts'] == 0) {
            $emptyCategories[] = [
                'name' => $category,
                'exists' => $stats['exists']
            ];
        }
    }

    return $emptyCategories;
}

/**
 * Renombrar una categoría completa
 *
 * @param string $username Usuario propietario
 * @param string $oldName Nombre actual de la categoría
 * @param string $newName Nuevo nombre para la categoría
 * @return array Resultado de la operación
 */
function renameCategory($username, $oldName, $newName) {
    $config = getConfig();
    $basePath = $config['base_path'];

    // Sanitizar nuevo nombre
    $newNameSanitized = sanitizePodcastName($newName);

    // VALIDACIÓN 1: Nuevo nombre no puede estar vacío
    if (empty($newNameSanitized)) {
        return ['success' => false, 'error' => 'El nuevo nombre no puede estar vacío'];
    }

    // VALIDACIÓN 2: Nombres deben ser diferentes
    if ($oldName === $newNameSanitized) {
        return ['success' => false, 'error' => 'El nuevo nombre es igual al antiguo'];
    }

    // VALIDACIÓN 3: Validar que el nuevo nombre no exista
    if (isCategoryInUse($username, $newNameSanitized)) {
        return ['success' => false, 'error' => 'La categoría con ese nombre ya existe'];
    }

    // Rutas
    $oldPath = $basePath . DIRECTORY_SEPARATOR . $username .
               DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
               'Podcasts' . DIRECTORY_SEPARATOR . $oldName;

    $newPath = $basePath . DIRECTORY_SEPARATOR . $username .
               DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
               'Podcasts' . DIRECTORY_SEPARATOR . $newNameSanitized;

    // VALIDACIÓN 4: Carpeta origen debe existir
    if (!is_dir($oldPath)) {
        return ['success' => false, 'error' => 'La carpeta origen no existe'];
    }

    // VALIDACIÓN 5: Carpeta destino NO debe existir
    if (is_dir($newPath)) {
        return ['success' => false, 'error' => 'Ya existe una carpeta con el nuevo nombre'];
    }

    // Guardar estado original para poder revertir
    $backupState = [
        'serverlist' => readServerList($username),
        'userData' => getUserDB($username),
        'folderRenamed' => false
    ];

    try {
        // PASO 1: Renombrar carpeta física
        if (!@rename($oldPath, $newPath)) {
            throw new Exception('Error al renombrar la carpeta física');
        }
        $backupState['folderRenamed'] = true;

        // NOTA: NO renombramos archivos dentro porque se nombran por PODCAST, no por categoría
        // Ejemplo: /Deportes/La_Grada13112025.mp3 sigue siendo La_Grada13112025.mp3 en /Noticias/

        // PASO 2: Actualizar serverlist.txt
        $podcasts = readServerList($username);
        foreach ($podcasts as &$podcast) {
            if ($podcast['category'] === $oldName) {
                $podcast['category'] = $newNameSanitized;
            }
        }
        if (!writeServerList($username, $podcasts)) {
            throw new Exception('Error al actualizar serverlist.txt');
        }

        // PASO 3: Actualizar users.json
        $userData = getUserDB($username);
        $key = array_search($oldName, $userData['categories']);
        if ($key !== false) {
            $userData['categories'][$key] = $newNameSanitized;
            sort($userData['categories']);
            if (!saveUserDB($username, $userData)) {
                throw new Exception('Error al actualizar users.json');
            }
        }

        // NOTA: NO actualizamos caducidades.txt ni duraciones.txt porque esos archivos
        // usan nombres de PODCASTS como keys, no nombres de categorías

        return [
            'success' => true,
            'new_name' => $newNameSanitized,
            'message' => "Categoría renombrada correctamente de '$oldName' a '$newNameSanitized'"
        ];

    } catch (Exception $e) {
        // REVERTIR TODO si algo falló

        // Revertir carpeta renombrada
        if ($backupState['folderRenamed']) {
            @rename($newPath, $oldPath);
        }

        // Revertir serverlist.txt
        writeServerList($username, $backupState['serverlist']);

        // Revertir users.json
        saveUserDB($username, $backupState['userData']);

        return [
            'success' => false,
            'error' => 'Error durante el renombrado: ' . $e->getMessage() . '. Se han revertido todos los cambios.'
        ];
    }
}

/**
 * Fusionar dos categorías
 *
 * @param string $username Usuario propietario
 * @param string $sourceCategory Categoría origen (se eliminará)
 * @param string $targetCategory Categoría destino (recibirá los archivos)
 * @return array Resultado de la operación
 */
function mergeCategories($username, $sourceCategory, $targetCategory) {
    // VALIDACIÓN 1: Categorías diferentes
    if ($sourceCategory === $targetCategory) {
        return ['success' => false, 'error' => 'Las categorías deben ser diferentes'];
    }

    // VALIDACIÓN 2: Ambas categorías deben existir
    $categories = getUserCategories($username);
    if (!in_array($sourceCategory, $categories)) {
        return ['success' => false, 'error' => 'La categoría origen no existe'];
    }
    if (!in_array($targetCategory, $categories)) {
        return ['success' => false, 'error' => 'La categoría destino no existe'];
    }

    $config = getConfig();
    $basePath = $config['base_path'];

    $sourcePath = $basePath . DIRECTORY_SEPARATOR . $username .
                  DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
                  'Podcasts' . DIRECTORY_SEPARATOR . $sourceCategory;

    $targetPath = $basePath . DIRECTORY_SEPARATOR . $username .
                  DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
                  'Podcasts' . DIRECTORY_SEPARATOR . $targetCategory;

    // Guardar estado original para revertir
    $backupState = [
        'serverlist' => readServerList($username),
        'userData' => getUserDB($username),
        'movedFiles' => []
    ];

    try {
        // PASO 1: Mover todos los SUBDIRECTORIOS (podcasts) de origen a destino
        // ESTRUCTURA: /Categoría/NombrePodcast/archivos.mp3
        $dirsMoved = 0;
        $totalFilesMoved = 0;

        if (is_dir($sourcePath)) {
            // Crear carpeta destino si no existe
            if (!is_dir($targetPath)) {
                if (!@mkdir($targetPath, 0755, true)) {
                    throw new Exception('No se pudo crear la carpeta destino');
                }
            }

            // Buscar subdirectorios (cada uno es un podcast)
            $podcastDirs = glob($sourcePath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            if ($podcastDirs !== false) {
                foreach ($podcastDirs as $podcastDir) {
                    $podcastName = basename($podcastDir);
                    $destDir = $targetPath . DIRECTORY_SEPARATOR . $podcastName;

                    // Si el directorio ya existe en destino, error
                    if (is_dir($destDir)) {
                        throw new Exception("El directorio '$podcastName' ya existe en la categoría destino");
                    }

                    // Contar archivos en este directorio
                    $audioFiles = glob($podcastDir . DIRECTORY_SEPARATOR . '*.{mp3,ogg,wav,m4a}', GLOB_BRACE);
                    $audioFileCount = $audioFiles ? count($audioFiles) : 0;

                    // Mover directorio completo
                    if (@rename($podcastDir, $destDir)) {
                        $dirsMoved++;
                        $totalFilesMoved += $audioFileCount;
                        $backupState['movedFiles'][] = [
                            'from' => $destDir,
                            'to' => $podcastDir
                        ];
                    } else {
                        throw new Exception("No se pudo mover el directorio del podcast: $podcastName");
                    }
                }
            }

            // Eliminar carpeta origen (ahora vacía)
            if (!@rmdir($sourcePath)) {
                // No es error crítico, puede quedar vacía
            }
        }

        // PASO 2: Actualizar serverlist.txt - reasignar podcasts
        $podcasts = readServerList($username);
        foreach ($podcasts as &$podcast) {
            if ($podcast['category'] === $sourceCategory) {
                $podcast['category'] = $targetCategory;
            }
        }
        if (!writeServerList($username, $podcasts)) {
            throw new Exception('Error al actualizar serverlist.txt');
        }

        // PASO 3: Actualizar users.json - eliminar categoría origen
        $userData = getUserDB($username);
        $userData['categories'] = array_values(
            array_filter($userData['categories'], function($cat) use ($sourceCategory) {
                return $cat !== $sourceCategory;
            })
        );
        if (!saveUserDB($username, $userData)) {
            throw new Exception('Error al actualizar users.json');
        }

        // NOTA: NO actualizamos caducidades.txt ni duraciones.txt porque esos archivos
        // usan nombres de PODCASTS como keys, no nombres de categorías

        return [
            'success' => true,
            'files_moved' => $totalFilesMoved,
            'message' => "Categoría '$sourceCategory' fusionada en '$targetCategory'. $dirsMoved directorio(s) de podcast movido(s) con $totalFilesMoved archivo(s)."
        ];

    } catch (Exception $e) {
        // REVERTIR TODO si algo falló

        // Revertir archivos movidos
        foreach ($backupState['movedFiles'] as $fileOp) {
            @rename($fileOp['from'], $fileOp['to']);
        }

        // Revertir serverlist.txt
        writeServerList($username, $backupState['serverlist']);

        // Revertir users.json
        saveUserDB($username, $backupState['userData']);

        return [
            'success' => false,
            'error' => 'Error durante la fusión: ' . $e->getMessage() . '. Se han revertido todos los cambios.'
        ];
    }
}

?>
