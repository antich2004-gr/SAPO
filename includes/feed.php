<?php
// includes/feed.php - Gestión de feeds RSS y caché

function getLastEpisodeDate($rssFeedUrl) {
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
    $xml = simplexml_load_string($xmlContent);
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
