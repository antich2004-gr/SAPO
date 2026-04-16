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


// ─────────────────────────────────────────────────────────────────────────────
// SOPORTE YT-DLP: YouTube, SoundCloud, Vimeo y otras plataformas de vídeo/audio
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Detecta si una URL debe descargarse con yt-dlp en lugar de podget/RSS.
 */
function isYtdlpUrl($url) {
    $ytdlpDomains = [
        'youtube.com', 'youtu.be',
        'soundcloud.com',
        'vimeo.com',
        'dailymotion.com',
        'twitch.tv',
        'rumble.com',
        'odysee.com',
        'tiktok.com',
        'instagram.com',
        'facebook.com',
        'fb.watch',
    ];
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = preg_replace('/^www\./', '', $host);
    foreach ($ytdlpDomains as $domain) {
        if ($host === $domain || substr($host, -(strlen($domain) + 1)) === '.' . $domain) {
            return true;
        }
    }
    return false;
}

/**
 * Ruta de ytdlp_feeds.txt (mismo directorio que serverlist.txt).
 */
function getYtdlpFeedsPath($username) {
    $config = getConfig();
    $basePath = $config['base_path'];
    $subsFolder = $config['subscriptions_folder'];

    if (empty($basePath)) {
        return false;
    }

    $path = $basePath . DIRECTORY_SEPARATOR . $username . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $subsFolder . DIRECTORY_SEPARATOR . 'ytdlp_feeds.txt';

    $realBasePath = realpath($basePath);
    $realPath = realpath(dirname($path));

    if ($realPath === false) {
        if (strpos($username, '..') !== false || strpos($username, DIRECTORY_SEPARATOR) !== false) {
            return false;
        }
    } elseif ($realBasePath !== false && strpos($realPath, $realBasePath) !== 0) {
        return false;
    }

    return $path;
}

/**
 * Lee ytdlp_feeds.txt.
 * Formato por línea: URL CATEGORIA NOMBRE MAX_EPISODIOS
 *   - CATEGORIA = '-' si no tiene categoría
 *   - MAX_EPISODIOS = número entero (defecto 5)
 *   - Líneas pausadas: '# PAUSADO: URL CATEGORIA NOMBRE MAX_EPISODIOS'
 * Retorna el mismo esquema que readServerList() con 'type'=>'ytdlp' y 'max_episodios'.
 */
function readYtdlpFeeds($username) {
    $path = getYtdlpFeedsPath($username);
    if (!$path || !file_exists($path)) return [];

    $feeds = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $paused = false;
        if (strpos($line, '# PAUSADO:') === 0) {
            $paused = true;
            $line = trim(substr($line, 10));
        } elseif ($line[0] === '#') {
            continue; // otros comentarios
        }

        // Formato: URL CATEGORIA NOMBRE MAX_EPISODIOS (4 campos, CATEGORIA='-' si vacía)
        $parts = preg_split('/\s+/', $line, 4);
        if (count($parts) < 3) continue;

        $url      = $parts[0];
        $category = ($parts[1] === '-') ? '' : $parts[1];
        $name     = $parts[2];
        $maxEp    = isset($parts[3]) ? max(1, (int)$parts[3]) : 1;

        $feeds[] = [
            'url'           => $url,
            'category'      => $category,
            'name'          => $name,
            'paused'        => $paused,
            'type'          => 'ytdlp',
            'max_episodios' => $maxEp,
        ];
    }
    return $feeds;
}

/**
 * Escribe ytdlp_feeds.txt.
 */
function writeYtdlpFeeds($username, $feeds) {
    $path = getYtdlpFeedsPath($username);
    if (!$path) return false;

    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) return false;
    }

    $user = getCurrentUser();
    $stationName = $user ? $user['station_name'] : 'Emisora';
    $dateTime = date('d/m/Y H:i:s');

    $content  = "# ytdlp_feeds.txt - Suscripciones de plataformas de vídeo/audio\n";
    $content .= "# Emisora: $stationName\n";
    $content .= "# Fecha de generacion: $dateTime\n";
    $content .= "# Formato: URL CATEGORIA NOMBRE MAX_EPISODIOS\n";
    $content .= "# Si no tiene categoría, CATEGORIA='-'\n";
    $content .= "# Los podcasts pausados se comentan con '# PAUSADO:' y no se descargan\n";
    $content .= "\n";

    foreach ($feeds as $feed) {
        $sanitizedName     = sanitizePodcastName($feed['name']);
        $sanitizedCategory = empty($feed['category']) ? '-' : sanitizePodcastName($feed['category']);
        $maxEp             = isset($feed['max_episodios']) ? max(1, (int)$feed['max_episodios']) : 1;

        $line = $feed['url'] . ' ' . $sanitizedCategory . ' ' . $sanitizedName . ' ' . $maxEp;

        if (isset($feed['paused']) && $feed['paused'] === true) {
            $content .= "# PAUSADO: " . $line . "\n";
        } else {
            $content .= $line . "\n";
        }
    }

    return file_put_contents($path, $content) !== false;
}

// ─────────────────────────────────────────────────────────────────────────────

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

// ─────────────────────────────────────────────────────────────────────────────
// MÁX. EPISODIOS RSS — max_episodios_rss.txt
// ─────────────────────────────────────────────────────────────────────────────

function getMaxEpisodiosRssPath($username) {
    $config = getConfig();
    $basePath = $config['base_path'];
    $subsFolder = $config['subscriptions_folder'];

    if (empty($basePath)) {
        return false;
    }

    $path = $basePath . DIRECTORY_SEPARATOR . $username . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $subsFolder . DIRECTORY_SEPARATOR . 'max_episodios_rss.txt';

    $realBasePath = realpath($basePath);
    $realPath = realpath(dirname($path));

    if ($realPath === false) {
        if (strpos($username, '..') !== false || strpos($username, DIRECTORY_SEPARATOR) !== false) {
            return false;
        }
    } elseif ($realBasePath !== false && strpos($realPath, $realBasePath) !== 0) {
        return false;
    }

    return $path;
}

function readMaxEpisodiosRss($username) {
    $path = getMaxEpisodiosRssPath($username);
    if (!$path || !file_exists($path)) return [];

    $data = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] == '#') continue;
        $parts = explode(':', $line, 2);
        if (count($parts) == 2) {
            $nombre = trim($parts[0]);
            $n = intval(trim($parts[1]));
            $data[$nombre] = max(1, $n);
        }
    }
    return $data;
}

function writeMaxEpisodiosRss($username, $data) {
    $path = getMaxEpisodiosRssPath($username);
    if (!$path) return false;

    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) return false;
    }

    $content = "# Máx. episodios RSS por podcast - Generado por SAPO\n";
    $content .= "# Formato: nombre_podcast:numero\n\n";

    foreach ($data as $podcastName => $n) {
        $content .= $podcastName . ':' . max(1, intval($n)) . "\n";
    }

    return file_put_contents($path, $content) !== false;
}

function setMaxEpisodiosRss($username, $podcastName, $n) {
    $data = readMaxEpisodiosRss($username);
    $data[$podcastName] = max(1, intval($n));
    return writeMaxEpisodiosRss($username, $data);
}

function deleteMaxEpisodiosRss($username, $podcastName) {
    $data = readMaxEpisodiosRss($username);
    unset($data[$podcastName]);
    return writeMaxEpisodiosRss($username, $data);
}

/**
 * Sincronizar caducidades.txt con todos los podcasts
 * Asegura que todos los podcasts tienen una caducidad definida
 * Actualiza los podcasts NO personalizados con el valor por defecto
 *
 * @param string $username Usuario
 * @param int|null $oldDefaultCaducidad Valor por defecto ANTERIOR (para detectar personalizaciones)
 */
function syncAllCaducidades($username, $oldDefaultCaducidad = null) {
    $podcasts = readServerList($username);
    $caducidades = readCaducidades($username);
    $defaultCaducidad = getDefaultCaducidad($username);

    // Sincronizar caducidades según si son personalizadas o no
    // IMPORTANTE: Solo se respetan los podcasts EXPLÍCITAMENTE marcados como personalizados
    // (marcados cuando el usuario edita manualmente un podcast con valor != default)
    foreach ($podcasts as $podcast) {
        $podcastName = $podcast['name'];

        // Si el podcast tiene caducidad personalizada, NO tocar su valor
        if (hasCaducidadCustom($username, $podcastName)) {
            // Mantener el valor actual (si existe)
            if (!isset($caducidades[$podcastName])) {
                // Caso extraño: está marcado como personalizado pero no tiene valor
                // Usar valor por defecto y desmarcar
                $caducidades[$podcastName] = $defaultCaducidad;
                unmarkCaducidadAsCustom($username, $podcastName);
            }
        } else {
            // No es personalizado: siempre usar el valor por defecto
            $caducidades[$podcastName] = $defaultCaducidad;
        }
    }

    // Limpiar caducidades de podcasts que ya no existen
    $existingPodcastNames = array_column($podcasts, 'name');
    foreach ($caducidades as $podcastName => $dias) {
        if (!in_array($podcastName, $existingPodcastNames)) {
            unset($caducidades[$podcastName]);
        }
    }

    // Limpiar también la lista de personalizados
    cleanupCustomCaducidades($username);

    return writeCaducidades($username, $caducidades);
}

function readServerList($username) {
    $path = getServerListPath($username);
    $podcasts = [];

    if ($path && file_exists($path)) {
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
                    'url'      => $parts[0],
                    'category' => '',
                    'name'     => $parts[1],
                    'paused'   => $paused,
                    'type'     => 'rss',
                ];
            } elseif (count($parts) == 3) {
                $podcasts[] = [
                    'url'      => $parts[0],
                    'category' => $parts[1],
                    'name'     => $parts[2],
                    'paused'   => $paused,
                    'type'     => 'rss',
                ];
            }
        }
    }

    // Añadir max_episodios a cada entrada RSS
    $maxEpisodiosMap = readMaxEpisodiosRss($username);
    foreach ($podcasts as &$podcast) {
        $podcast['max_episodios'] = $maxEpisodiosMap[$podcast['name']] ?? 1;
    }
    unset($podcast);

    // Mezclar con ytdlp_feeds.txt
    return array_merge($podcasts, readYtdlpFeeds($username));
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
    $content = "# Serverlist generado por SAPO\n";
    $content .= "# Emisora: $stationName\n";
    $content .= "# Fecha de generacion: $dateTime\n";
    $content .= "# Formato: URL_RSS Carpeta_categoria Nombre_Podcast\n";
    $content .= "# Los podcasts pausados se comentan con '# PAUSADO:' y no se descargan\n";
    $content .= "\n";

    foreach ($podcasts as $podcast) {
        // Solo escribir entradas RSS (las ytdlp van a ytdlp_feeds.txt)
        if (isset($podcast['type']) && $podcast['type'] === 'ytdlp') {
            continue;
        }

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

function addPodcast($username, $url, $category, $name, $caducidad = 30, $duracion = '', $margen = 5, $max_episodios = 1) {
    // Comprobar duplicados en AMBOS ficheros (RSS y ytdlp)
    $allPodcasts = readServerList($username);
    foreach ($allPodcasts as $podcast) {
        if ($podcast['url'] == $url) {
            return ['success' => false, 'error' => 'El podcast ya existe'];
        }
    }

    $sanitizedCategory = empty($category) ? '' : sanitizePodcastName($category);
    $sanitizedName = sanitizePodcastName($name);

    if (isYtdlpUrl($url)) {
        // Guardar en ytdlp_feeds.txt
        $ytdlpFeeds = readYtdlpFeeds($username);
        $ytdlpFeeds[] = [
            'url'           => $url,
            'category'      => $sanitizedCategory,
            'name'          => $sanitizedName,
            'paused'        => false,
            'type'          => 'ytdlp',
            'max_episodios' => max(1, (int)$max_episodios),
        ];
        $writeOk = writeYtdlpFeeds($username, $ytdlpFeeds);
    } else {
        // Guardar en serverlist.txt (RSS)
        // allPodcasts ya tiene la lista mezclada; writeServerList filtra ytdlp automáticamente
        $allPodcasts[] = [
            'url'      => $url,
            'category' => $sanitizedCategory,
            'name'     => $sanitizedName,
            'type'     => 'rss',
        ];
        $writeOk = writeServerList($username, $allPodcasts);
    }

    if ($writeOk) {
        // Actualizar caducidades.txt SIEMPRE
        setCaducidad($username, $sanitizedName, $caducidad);

        // Marcar como personalizada si es diferente al valor por defecto
        $defaultCaducidad = getDefaultCaducidad($username);
        if ($caducidad != $defaultCaducidad) {
            markCaducidadAsCustom($username, $sanitizedName);
        }

        // Guardar duración si no es vacía
        if (!empty($duracion)) {
            $duraciones = readDuraciones($username);
            $margenes = readMargenes($username);
            $duraciones[strtolower($sanitizedName)] = $duracion;
            $margenes[strtolower($sanitizedName)] = (int)$margen;
            writeDuraciones($username, $duraciones, $margenes);
        }

        // Guardar max_episodios para RSS
        if (!isYtdlpUrl($url)) {
            setMaxEpisodiosRss($username, $sanitizedName, $max_episodios);
        }

        return [
            'success'            => true,
            'message'            => 'Podcast agregado correctamente',
            'sanitized_category' => $sanitizedCategory,
            'sanitized_name'     => $sanitizedName,
        ];
    } else {
        return ['success' => false, 'error' => 'Error al guardar el podcast'];
    }
}

function editPodcast($username, $index, $url, $category, $name, $caducidad = 30, $duracion = '', $margen = 5, $max_episodios = 1) {
    // Lista mezclada ordenada para resolver el índice
    $allPodcasts = readServerList($username);
    usort($allPodcasts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $allPodcasts = array_values($allPodcasts);

    if ($index < 0 || $index >= count($allPodcasts)) {
        return ['success' => false, 'error' => 'Podcast no encontrado'];
    }

    $oldCategory = $allPodcasts[$index]['category'];
    $oldName     = $allPodcasts[$index]['name'];
    $oldUrl      = $allPodcasts[$index]['url'];
    $oldType     = $allPodcasts[$index]['type'] ?? 'rss';

    // Lista mezclada original (sin ordenar) para backup y búsqueda
    $allOriginal = readServerList($username);

    // Verificar que la URL no exista en otro podcast
    foreach ($allOriginal as $podcast) {
        if ($podcast['url'] == $url && $podcast['url'] != $oldUrl) {
            return ['success' => false, 'error' => 'Esta URL ya existe en otro podcast'];
        }
    }

    $sanitizedCategory = empty($category) ? '' : sanitizePodcastName($category);
    $sanitizedName     = sanitizePodcastName($name);
    $newType           = isYtdlpUrl($url) ? 'ytdlp' : 'rss';
    $typeChanged       = ($oldType !== $newType);
    $categoryChanged   = ($oldCategory !== $sanitizedCategory);

    // ── Operaciones de fichero (mover/renombrar directorio) ──────────────────
    $moveResult = ['success' => true, 'moved' => 0];

    if ($categoryChanged) {
        $moveResult = movePodcastFiles($username, $oldName, $oldCategory, $sanitizedCategory);
        if (!$moveResult['success']) {
            return [
                'success' => false,
                'error'   => 'Error al mover archivos: ' . ($moveResult['error'] ?? 'Error desconocido') . '. No se han realizado cambios.',
            ];
        }
    }

    if ($oldName !== $sanitizedName) {
        $currentCategory = $categoryChanged ? $sanitizedCategory : $oldCategory;
        $renameResult = renamePodcastDirectory($username, $oldName, $sanitizedName, $currentCategory);
        if (!$renameResult['success']) {
            if ($categoryChanged) {
                @movePodcastFiles($username, $oldName, $sanitizedCategory, $oldCategory);
            }
            return [
                'success' => false,
                'error'   => 'Error al renombrar directorio: ' . ($renameResult['error'] ?? 'Error desconocido') . '. Se han revertido los cambios de categoría.',
            ];
        }
    }

    // ── Actualizar ficheros de configuración ─────────────────────────────────
    $writeOk = false;

    if ($typeChanged) {
        // El podcast cambia de tipo: eliminar del fichero original, añadir al nuevo
        if ($oldType === 'rss') {
            // Eliminar de serverlist.txt
            $rssFeeds = array_values(array_filter($allOriginal, function($p) use ($oldUrl) {
                return ($p['type'] ?? 'rss') === 'rss' && $p['url'] !== $oldUrl;
            }));
            writeServerList($username, $rssFeeds);

            // Añadir a ytdlp_feeds.txt
            $ytdlpFeeds   = readYtdlpFeeds($username);
            $ytdlpFeeds[] = [
                'url'           => $url,
                'category'      => $sanitizedCategory,
                'name'          => $sanitizedName,
                'paused'        => $allPodcasts[$index]['paused'] ?? false,
                'type'          => 'ytdlp',
                'max_episodios' => max(1, (int)$max_episodios),
            ];
            $writeOk = writeYtdlpFeeds($username, $ytdlpFeeds);
        } else {
            // Eliminar de ytdlp_feeds.txt
            $ytdlpFeeds = array_values(array_filter(readYtdlpFeeds($username), function($p) use ($oldUrl) {
                return $p['url'] !== $oldUrl;
            }));
            writeYtdlpFeeds($username, $ytdlpFeeds);

            // Añadir a serverlist.txt
            $rssFeeds   = array_values(array_filter($allOriginal, function($p) {
                return ($p['type'] ?? 'rss') === 'rss';
            }));
            $rssFeeds[] = [
                'url'      => $url,
                'category' => $sanitizedCategory,
                'name'     => $sanitizedName,
                'paused'   => $allPodcasts[$index]['paused'] ?? false,
                'type'     => 'rss',
            ];
            $writeOk = writeServerList($username, $rssFeeds);
        }
    } elseif ($newType === 'ytdlp') {
        // Actualizar en ytdlp_feeds.txt
        $ytdlpFeeds = readYtdlpFeeds($username);
        $found = false;
        foreach ($ytdlpFeeds as &$feed) {
            if ($feed['url'] === $oldUrl) {
                $feed['url']           = $url;
                $feed['category']      = $sanitizedCategory;
                $feed['name']          = $sanitizedName;
                $feed['max_episodios'] = max(1, (int)$max_episodios);
                $found = true;
                break;
            }
        }
        unset($feed);
        if (!$found) {
            return ['success' => false, 'error' => 'Podcast no encontrado en ytdlp_feeds.txt'];
        }
        $writeOk = writeYtdlpFeeds($username, $ytdlpFeeds);
    } else {
        // Actualizar en serverlist.txt
        $rssFeeds = array_values(array_filter($allOriginal, function($p) {
            return ($p['type'] ?? 'rss') === 'rss';
        }));
        $found = false;
        foreach ($rssFeeds as &$feed) {
            if ($feed['url'] === $oldUrl) {
                $feed['url']      = $url;
                $feed['category'] = $sanitizedCategory;
                $feed['name']     = $sanitizedName;
                $found = true;
                break;
            }
        }
        unset($feed);
        if (!$found) {
            return ['success' => false, 'error' => 'Podcast no encontrado en serverlist.txt'];
        }
        $writeOk = writeServerList($username, $rssFeeds);
    }

    if (!$writeOk) {
        return ['success' => false, 'error' => 'Error al actualizar el podcast'];
    }

    // ── Caducidades y duraciones ──────────────────────────────────────────────
    if ($oldName !== $sanitizedName) {
        deleteCaducidad($username, $oldName);
        unmarkCaducidadAsCustom($username, $oldName);
    }

    setCaducidad($username, $sanitizedName, $caducidad);

    $defaultCaducidad = getDefaultCaducidad($username);
    if ($caducidad != $defaultCaducidad) {
        markCaducidadAsCustom($username, $sanitizedName);
    } else {
        unmarkCaducidadAsCustom($username, $sanitizedName);
    }

    $duraciones  = readDuraciones($username);
    $margenesArr = readMargenes($username);
    if (!empty($duracion)) {
        $duraciones[strtolower($sanitizedName)]  = $duracion;
        $margenesArr[strtolower($sanitizedName)] = (int)$margen;
    } else {
        unset($duraciones[strtolower($sanitizedName)]);
        unset($margenesArr[strtolower($sanitizedName)]);
    }
    if ($oldName !== $sanitizedName) {
        unset($duraciones[strtolower($oldName)]);
        unset($margenesArr[strtolower($oldName)]);
    }
    writeDuraciones($username, $duraciones, $margenesArr);

    // ── Máx. episodios RSS ────────────────────────────────────────────────────
    if ($newType === 'rss') {
        if ($oldName !== $sanitizedName) {
            deleteMaxEpisodiosRss($username, $oldName);
        }
        setMaxEpisodiosRss($username, $sanitizedName, $max_episodios);
    } elseif ($typeChanged && $oldType === 'rss') {
        // Cambió de RSS a ytdlp → limpiar entrada RSS
        deleteMaxEpisodiosRss($username, $oldName);
    }

    $result = [
        'success'            => true,
        'message'            => 'Podcast actualizado correctamente',
        'sanitized_category' => $sanitizedCategory,
        'sanitized_name'     => $sanitizedName,
    ];

    if ($categoryChanged) {
        $result['category_changed'] = true;
        $result['files_moved']      = $moveResult['moved'];
        if (isset($moveResult['message'])) {
            $result['move_message'] = $moveResult['message'];
        }
    }

    return $result;
}

function deletePodcast($username, $index) {
    $allPodcasts = readServerList($username);

    usort($allPodcasts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $allPodcasts = array_values($allPodcasts);

    if ($index < 0 || $index >= count($allPodcasts)) {
        return ['success' => false, 'error' => 'Podcast no encontrado'];
    }

    $targetUrl   = $allPodcasts[$index]['url'];
    $deletedName = $allPodcasts[$index]['name'];
    $targetType  = $allPodcasts[$index]['type'] ?? 'rss';

    if ($targetType === 'ytdlp') {
        $ytdlpFeeds = readYtdlpFeeds($username);
        $found = false;
        foreach ($ytdlpFeeds as $key => $feed) {
            if ($feed['url'] === $targetUrl) {
                array_splice($ytdlpFeeds, $key, 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ['success' => false, 'error' => 'Podcast no encontrado en la lista'];
        }
        if (writeYtdlpFeeds($username, $ytdlpFeeds)) {
            deleteCaducidad($username, $deletedName);
            return ['success' => true, 'message' => 'Podcast eliminado correctamente'];
        }
        return ['success' => false, 'error' => 'Error al eliminar el podcast'];
    }

    // RSS: releer sin ordenar y filtrar solo RSS
    $allOriginal = readServerList($username);
    $rssFeeds = array_values(array_filter($allOriginal, function($p) {
        return ($p['type'] ?? 'rss') === 'rss';
    }));

    $found = false;
    foreach ($rssFeeds as $key => $podcast) {
        if ($podcast['url'] === $targetUrl) {
            array_splice($rssFeeds, $key, 1);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'error' => 'Podcast no encontrado en la lista'];
    }

    if (writeServerList($username, $rssFeeds)) {
        deleteCaducidad($username, $deletedName);
        deleteMaxEpisodiosRss($username, $deletedName);
        return ['success' => true, 'message' => 'Podcast eliminado correctamente'];
    }
    return ['success' => false, 'error' => 'Error al eliminar el podcast'];
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
    $scriptPath = PROJECT_DIR . '/cliente_rrll/cliente_rrll.sh';
    $logFile = PROJECT_DIR . '/logs/podget_' . $username . '.log';

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

    if (!is_writable($logDir)) {
        error_log("[SAPO-Security] executePodget: Directorio de logs no escribible: $logDir | Usuario: $username");
        return ['success' => false, 'message' => 'El directorio de logs no tiene permisos de escritura'];
    }

    // SEGURIDAD: Logging de auditoría ANTES de ejecutar
    $userInfo = isset($_SESSION['user_id']) ? "ID: {$_SESSION['user_id']}, Session: {$_SESSION['username']}" : "No autenticado";
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');

    error_log("[SAPO-Security] EXEC PODGET | Usuario objetivo: $username | Ejecutado por: $userInfo | IP: $clientIP | Timestamp: $timestamp");

    // Lanzar en background real con nohup para que el proceso sobreviva al timeout de PHP.
    // proc_close() bloquea hasta que el hijo termina; con nohup+& el shell retorna
    // inmediatamente y el script sigue corriendo de forma independiente.
    // Seguridad: escapeshellarg() en todos los valores dinámicos.
    // PHP-FPM arranca con entorno mínimo (sin HOME, PATH incompleto); se fijan
    // explícitamente para que el script y sus herramientas funcionen igual que
    // en una sesión de terminal. 'cd /tmp' evita el error de find al restaurar
    // el cwd de PHP-FPM (/home/fide u otro directorio inaccesible).
    // Limpiar el log al inicio de cada run para que el visor siempre empiece
    // desde 0 y muestre solo el output del run actual (sin mezclar con el anterior).
    $written = file_put_contents($logFile, date('[Y-m-d H:i:s]') . " Descarga programada para $username. Esperando que el runner del host la inicie...\n");
    if ($written === false) {
        $webUser = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? posix_geteuid()) : 'desconocido';
        error_log("[SAPO] No se pudo escribir el log $logFile (usuario web: $webUser, permisos dir: " . decoct(fileperms($logDir) & 0777) . ")");
        return ['success' => false, 'message' => "Sin permisos de escritura en logs. Revisa permisos de $logFile"];
    }

    // ARQUITECTURA HOST-RUNNER:
    // PHP corre dentro del contenedor Docker de AzuraCast (usuario 'azuracast'),
    // pero cliente_rrll.sh necesita correr en el HOST donde están podget y demás
    // herramientas. Solución: PHP escribe un archivo trigger en el directorio de
    // logs (compartido entre contenedor y host via volumen). Un cron en el HOST
    // ejecuta sapo_host_runner.sh cada minuto, detecta los triggers y lanza el
    // script directamente en el host.
    //
    // Cron recomendado en el host:
    //   * * * * * root /var/www/html/sapo_host_runner.sh
    $triggerFile = $logDir . '/.sapo_trigger_' . $username;
    $triggerContent = json_encode([
        'emisora'      => $username,
        'logfile'      => $logFile,
        'requested_at' => date('Y-m-d H:i:s'),
        'requested_by' => $_SESSION['username'] ?? 'unknown',
    ], JSON_UNESCAPED_UNICODE);

    $triggerWritten = file_put_contents($triggerFile, $triggerContent . "\n");
    if ($triggerWritten === false) {
        error_log("[SAPO-Security] EXEC PODGET: no se pudo escribir trigger $triggerFile | Usuario: $username");
        return ['success' => false, 'message' => 'No se pudo crear el archivo de trigger. Revisa permisos en ' . $logDir];
    }

    error_log("[SAPO-Security] EXEC PODGET trigger escrito | Usuario: $username | Trigger: $triggerFile");

    return [
        'success' => true,
        'message' => 'Descarga programada. El runner del host la iniciará en breve. Log: ' . $logFile,
    ];
}


/**
 * Leer archivo duraciones.txt
 * Formato: nombre_podcast:30M  o  nombre_podcast:30M:10
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

        $parts = explode(':', $line, 3);
        if (count($parts) >= 2) {
            $podcastName = strtolower(trim($parts[0]));
            $duracion = trim($parts[1]);
            $duraciones[$podcastName] = $duracion;
        }
    }

    return $duraciones;
}

/**
 * Leer márgenes desde duraciones.txt (3er campo opcional)
 * Retorna array asociativo ['nombre_podcast' => 10] (minutos, defecto 5)
 */
function readMargenes($username) {
    $duracionesPath = getDuracionesPath($username);
    $margenes = [];

    if (!file_exists($duracionesPath)) {
        return $margenes;
    }

    $lines = file($duracionesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $margenes;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode(':', $line, 3);
        if (count($parts) === 3) {
            $podcastName = strtolower(trim($parts[0]));
            $margen = (int)trim($parts[2]);
            // Solo aceptar valores válidos del selector (5, 10, 15)
            // Valores legacy como 1 se ignoran y se tratan como el default (5)
            if (in_array($margen, [5, 10, 15])) {
                $margenes[$podcastName] = $margen;
            }
        }
    }

    return $margenes;
}

/**
 * Escribir archivo duraciones.txt
 * Incluye el margen como 3er campo cuando es distinto del defecto (5 min)
 */
function writeDuraciones($username, $duraciones, $margenes = []) {
    $duracionesPath = getDuracionesPath($username);

    // Asegurar que el directorio existe
    $dir = dirname($duracionesPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $content = "";
    foreach ($duraciones as $podcastName => $duracion) {
        if (!empty($duracion)) {
            $rawMargen = isset($margenes[$podcastName]) ? (int)$margenes[$podcastName] : 5;
            // Normalizar: si el valor no es válido (ej. legacy 1), usar default 5
            $margen = in_array($rawMargen, [5, 10, 15]) ? $rawMargen : 5;
            $line = slugify($podcastName) . ":" . $duracion;
            if ($margen !== 5) {
                $line .= ":" . $margen;
            }
            $content .= $line . "\n";
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
 * Obtener opciones disponibles de margen de duración
 */
function getMargenesOptions() {
    return [
        5  => '5 minutos (defecto)',
        10 => '10 minutos',
        15 => '15 minutos',
    ];
}

/**
 * Pausar un podcast (comentar línea en serverlist.txt)
 */
function pausePodcast($username, $index) {
    $allPodcasts = readServerList($username);
    usort($allPodcasts, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
    $allPodcasts = array_values($allPodcasts);

    if (!isset($allPodcasts[$index])) {
        return ['success' => false, 'message' => 'Podcast no encontrado'];
    }

    $targetUrl  = $allPodcasts[$index]['url'];
    $targetType = $allPodcasts[$index]['type'] ?? 'rss';

    if ($targetType === 'ytdlp') {
        $ytdlpFeeds = readYtdlpFeeds($username);
        $found = false;
        foreach ($ytdlpFeeds as &$feed) {
            if ($feed['url'] === $targetUrl) {
                $feed['paused'] = true;
                $found = true;
                break;
            }
        }
        unset($feed);
        if (!$found) return ['success' => false, 'message' => 'Podcast no encontrado en la lista'];
        if (writeYtdlpFeeds($username, $ytdlpFeeds)) {
            return ['success' => true, 'message' => 'Podcast pausado correctamente. No se descargarán nuevos episodios.'];
        }
        return ['success' => false, 'message' => 'Error al pausar el podcast'];
    }

    // RSS
    $allOriginal = readServerList($username);
    $rssFeeds = array_values(array_filter($allOriginal, function($p) {
        return ($p['type'] ?? 'rss') === 'rss';
    }));
    $found = false;
    foreach ($rssFeeds as &$podcast) {
        if ($podcast['url'] === $targetUrl) {
            $podcast['paused'] = true;
            $found = true;
            break;
        }
    }
    unset($podcast);
    if (!$found) return ['success' => false, 'message' => 'Podcast no encontrado en la lista'];
    if (writeServerList($username, $rssFeeds)) {
        return ['success' => true, 'message' => 'Podcast pausado correctamente. No se descargarán nuevos episodios.'];
    }
    return ['success' => false, 'message' => 'Error al pausar el podcast'];
}

/**
 * Reanudar un podcast (descomentar línea en serverlist.txt)
 */
function resumePodcast($username, $index) {
    $allPodcasts = readServerList($username);
    usort($allPodcasts, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
    $allPodcasts = array_values($allPodcasts);

    if (!isset($allPodcasts[$index])) {
        return ['success' => false, 'message' => 'Podcast no encontrado'];
    }

    $targetUrl  = $allPodcasts[$index]['url'];
    $targetType = $allPodcasts[$index]['type'] ?? 'rss';

    if ($targetType === 'ytdlp') {
        $ytdlpFeeds = readYtdlpFeeds($username);
        $found = false;
        foreach ($ytdlpFeeds as &$feed) {
            if ($feed['url'] === $targetUrl) {
                $feed['paused'] = false;
                $found = true;
                break;
            }
        }
        unset($feed);
        if (!$found) return ['success' => false, 'message' => 'Podcast no encontrado en la lista'];
        if (writeYtdlpFeeds($username, $ytdlpFeeds)) {
            return ['success' => true, 'message' => 'Podcast reanudado correctamente. Se reanudarán las descargas.'];
        }
        return ['success' => false, 'message' => 'Error al reanudar el podcast'];
    }

    // RSS
    $allOriginal = readServerList($username);
    $rssFeeds = array_values(array_filter($allOriginal, function($p) {
        return ($p['type'] ?? 'rss') === 'rss';
    }));
    $found = false;
    foreach ($rssFeeds as &$podcast) {
        if ($podcast['url'] === $targetUrl) {
            $podcast['paused'] = false;
            $found = true;
            break;
        }
    }
    unset($podcast);
    if (!$found) return ['success' => false, 'message' => 'Podcast no encontrado en la lista'];
    if (writeServerList($username, $rssFeeds)) {
        return ['success' => true, 'message' => 'Podcast reanudado correctamente. Se reanudarán las descargas.'];
    }
    return ['success' => false, 'message' => 'Error al reanudar el podcast'];
}

/**
 * Obtener caducidad por defecto para un usuario
 * Retorna el valor guardado en el JSON del usuario o 30 días por defecto
 */
function getDefaultCaducidad($username) {
    $userData = getUserDB($username);

    // Si existe el campo default_caducidad en el JSON
    if (isset($userData['default_caducidad'])) {
        $dias = intval($userData['default_caducidad']);

        // Validar rango
        if ($dias >= 1 && $dias <= 365) {
            return $dias;
        }
    }

    return 30; // Valor por defecto
}

/**
 * Establecer caducidad por defecto para un usuario
 * Guarda el valor en el JSON del usuario
 */
function setDefaultCaducidad($username, $dias) {
    $userData = getUserDB($username);

    // Validar rango
    $dias = intval($dias);
    if ($dias < 1 || $dias > 365) {
        $dias = 30;
    }

    // Guardar en el JSON
    $userData['default_caducidad'] = $dias;

    return saveUserDB($username, $userData);
}

/**
 * Marcar un podcast como que tiene caducidad personalizada
 */
function markCaducidadAsCustom($username, $podcastName) {
    $userData = getUserDB($username);

    if (!isset($userData['custom_caducidades'])) {
        $userData['custom_caducidades'] = [];
    }

    if (!in_array($podcastName, $userData['custom_caducidades'])) {
        $userData['custom_caducidades'][] = $podcastName;
        saveUserDB($username, $userData);
    }
}

/**
 * Desmarcar un podcast como personalizado (vuelve a usar el valor por defecto)
 */
function unmarkCaducidadAsCustom($username, $podcastName) {
    $userData = getUserDB($username);

    if (!isset($userData['custom_caducidades'])) {
        return;
    }

    $key = array_search($podcastName, $userData['custom_caducidades']);
    if ($key !== false) {
        unset($userData['custom_caducidades'][$key]);
        $userData['custom_caducidades'] = array_values($userData['custom_caducidades']); // Reindexar
        saveUserDB($username, $userData);
    }
}

/**
 * Verificar si un podcast tiene caducidad personalizada
 */
function hasCaducidadCustom($username, $podcastName) {
    $userData = getUserDB($username);

    if (!isset($userData['custom_caducidades'])) {
        return false;
    }

    return in_array($podcastName, $userData['custom_caducidades']);
}

/**
 * Limpiar caducidades personalizadas de podcasts que ya no existen
 */
function cleanupCustomCaducidades($username) {
    $userData = getUserDB($username);
    $podcasts = readServerList($username);
    $podcastNames = array_column($podcasts, 'name');

    if (!isset($userData['custom_caducidades'])) {
        return;
    }

    $cleaned = false;
    foreach ($userData['custom_caducidades'] as $key => $podcastName) {
        if (!in_array($podcastName, $podcastNames)) {
            unset($userData['custom_caducidades'][$key]);
            $cleaned = true;
        }
    }

    if ($cleaned) {
        $userData['custom_caducidades'] = array_values($userData['custom_caducidades']);
        saveUserDB($username, $userData);
    }
}

?>
