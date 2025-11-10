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

function getLastEpisodesFromFeed($rssFeedUrl, $limit = 5) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'SAPO-Radiobot/1.0'
        ]
    ]);

    $xmlContent = @file_get_contents($rssFeedUrl, false, $context);

    if ($xmlContent === false) {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    libxml_clear_errors();

    if ($xml === false) {
        return [];
    }

    $episodes = [];

    // RSS 2.0 format
    if (isset($xml->channel->item)) {
        $items = array_slice((array)$xml->channel->item, 0, $limit);

        foreach ($items as $item) {
            $pubDate = isset($item->pubDate) ? strtotime((string)$item->pubDate) : null;
            $title = isset($item->title) ? (string)$item->title : 'Sin título';
            $enclosure = isset($item->enclosure['url']) ? (string)$item->enclosure['url'] : '';

            // Extraer nombre de archivo del enclosure URL
            $fileName = $enclosure ? basename(parse_url($enclosure, PHP_URL_PATH)) : $title;

            if ($pubDate) {
                $episodes[] = [
                    'title' => $title,
                    'file' => $fileName,
                    'pubDate' => $pubDate,
                    'dateFormatted' => date('d-m-Y H:i:s', $pubDate)
                ];
            }
        }
    }
    // Atom format
    elseif (isset($xml->entry)) {
        $entries = array_slice((array)$xml->entry, 0, $limit);

        foreach ($entries as $entry) {
            $pubDate = null;
            if (isset($entry->published)) {
                $pubDate = strtotime((string)$entry->published);
            } elseif (isset($entry->updated)) {
                $pubDate = strtotime((string)$entry->updated);
            }

            $title = isset($entry->title) ? (string)$entry->title : 'Sin título';

            if ($pubDate) {
                $episodes[] = [
                    'title' => $title,
                    'file' => $title,
                    'pubDate' => $pubDate,
                    'dateFormatted' => date('d-m-Y H:i:s', $pubDate)
                ];
            }
        }
    }

    return $episodes;
}

?>
