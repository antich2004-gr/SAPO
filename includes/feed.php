<?php
// includes/feed.php - Gestión de feeds RSS y caché

/**
 * Validar URL contra SSRF (Server-Side Request Forgery)
 * Bloquea IPs privadas, localhost y esquemas inseguros
 */
function validateRssFeedUrl($url) {
    // Validar formato básico
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    // Solo permitir HTTP y HTTPS
    $parsed = parse_url($url);
    if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
        return false;
    }

    // Obtener el host
    $host = $parsed['host'] ?? '';
    if (empty($host)) {
        return false;
    }

    // Resolver el hostname a IP
    $ip = gethostbyname($host);
    if ($ip === $host) {
        // No se pudo resolver, permitir solo si es un hostname válido
        // (algunos feeds usan CDNs que pueden no resolver desde el servidor)
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            return false;
        }
    } else {
        // Validar que la IP no sea privada/localhost
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }

    // Validar que el hostname no sea localhost
    $localhostPatterns = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '169.254.'];
    foreach ($localhostPatterns as $pattern) {
        if (stripos($host, $pattern) !== false) {
            return false;
        }
    }

    return true;
}


function getLastEpisodeDate($rssFeedUrl) {
    // Validar URL contra SSRF
    if (!validateRssFeedUrl($rssFeedUrl)) {
        error_log("SSRF attempt blocked: " . $rssFeedUrl);
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'SAPO-Radiobot/1.0'
        ]
    ]);
    
    $xmlContent = @file_get_contents($rssFeedUrl, false, $context);
    
    if ($xmlContent === false) {
        return null;
    }
    
    libxml_use_internal_errors(true);

    // Prevenir ataques XXE (XML External Entity)
    $previousValue = libxml_disable_entity_loader(true);
    $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_NOCDATA);
    libxml_disable_entity_loader($previousValue);

    libxml_clear_errors();
    
    if ($xml === false) {
        return null;
    }
    
    $lastDate = null;
    
    if (isset($xml->channel->item[0])) {
        $firstItem = $xml->channel->item[0];
        if (isset($firstItem->pubDate)) {
            $lastDate = (string)$firstItem->pubDate;
        }
    }
    
    if (!$lastDate && isset($xml->entry[0])) {
        $firstEntry = $xml->entry[0];
        if (isset($firstEntry->published)) {
            $lastDate = (string)$firstEntry->published;
        } elseif (isset($firstEntry->updated)) {
            $lastDate = (string)$firstEntry->updated;
        }
    }
    
    if ($lastDate) {
        $timestamp = strtotime($lastDate);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }
    
    return null;
}

function getCachedFeedInfo($url, $forceUpdate = false) {
    $config = getConfig();
    $cacheDuration = $config['cache_duration'] ?? 43200;
    $now = time();
    $cacheKey = md5($url);
    
    if (!$forceUpdate) {
        $cached = getCacheEntry($cacheKey);
        if ($cached) {
            $age = $now - $cached['cached_at'];
            if ($age < $cacheDuration) {
                return [
                    'timestamp' => $cached['last_episode'],
                    'cached' => true,
                    'cache_age' => $age
                ];
            }
        }
    }
    
    $lastEpisode = getLastEpisodeDate($url);
    
    setCacheEntry($cacheKey, [
        'url' => $url,
        'last_episode' => $lastEpisode,
        'cached_at' => $now
    ]);
    
    return [
        'timestamp' => $lastEpisode,
        'cached' => false,
        'cache_age' => 0
    ];
}

function refreshAllFeeds($username) {
    $podcasts = readServerList($username);
    $updated = 0;

    foreach ($podcasts as $podcast) {
        // Actualizar caché de fecha del último episodio
        getCachedFeedInfo($podcast['url'], true);
        $updated++;
    }

    return $updated;
}

function formatFeedStatus($timestamp) {
    if ($timestamp === null) {
        return [
            'class' => 'unknown',
            'status' => 'No se pudo obtener informacion',
            'icon' => '?'
        ];
    }

    $daysSince = floor((time() - $timestamp) / (60 * 60 * 24));
    $dateFormatted = date('d/m/Y', $timestamp);

    if ($daysSince <= 30) {
        $class = 'recent';
        $status = 'Activo';
        $icon = 'OK';
    } elseif ($daysSince <= 90) {
        $class = 'old';
        $status = 'Poco activo';
        $icon = 'WARNING';
    } else {
        $class = 'inactive';
        $status = 'Inactivo';
        $icon = 'X';
    }

    return [
        'class' => $class,
        'status' => $status,
        'icon' => $icon,
        'date' => $dateFormatted,
        'days' => $daysSince
    ];
}

?>
