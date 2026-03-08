<?php
/**
 * api_schedule.php - API JSON para obtener datos de la parrilla
 * Devuelve la programación en formato JSON para widgets y aplicaciones externas
 * Portado desde GRILLO y adaptado a la arquitectura de SAPO
 */

// Headers JSON y CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Permitir CORS para incrustar en otros dominios
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurar zona horaria
date_default_timezone_set('Europe/Madrid');

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';
require_once INCLUDES_DIR . '/utils.php';
require_once INCLUDES_DIR . '/cache.php';

// Obtener parámetros
$station = $_GET['station'] ?? '';

// Validar entrada
if (empty($station) || !validateInput($station, 'username')) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Parámetro station inválido o faltante',
        'usage' => 'api_schedule.php?station=nombre_emisora'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar que el usuario existe
$user = findUserByUsername($station);
if (!$user) {
    http_response_code(404);
    echo json_encode([
        'error' => 'Emisora no encontrada',
        'station' => $station
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ====== SISTEMA DE CACHÉ ======
$cacheKey = "api_schedule_{$station}";
$cachedResponse = cacheGet($cacheKey, 300); // 5 minutos TTL

if ($cachedResponse !== null) {
    // Cache HIT
    header('X-Cache: HIT');
    header('Cache-Control: public, max-age=300');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
    echo json_encode($cachedResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Cache MISS - generar respuesta
header('X-Cache: MISS');

// Obtener configuración de la emisora
$azConfig = getAzuracastConfig($station);
$stationName = $user['station_name'] ?? $station;

// Obtener programación de AzuraCast
$schedule = getAzuracastSchedule($station, 600); // 10 min cache
if ($schedule === false) $schedule = [];

// Cargar información de programas
$programsDB = loadProgramsDB($station);
$programsData = $programsDB['programs'] ?? [];

// Días de la semana
$daysOfWeek = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    0 => 'Domingo'
];

// Inicializar estructura de programación por día
$scheduleByDay = [];
foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
    $scheduleByDay[$day] = [
        'name' => $daysOfWeek[$day],
        'programs' => []
    ];
}

// ====== AÑADIR PROGRAMAS MANUALES (LIVE) ======
foreach ($programsData as $programKey => $programInfo) {
    if (($programInfo['playlist_type'] ?? '') === 'live') {
        // Saltar programas ocultos
        if (!empty($programInfo['hidden_from_schedule'])) continue;

        // ====== SOPORTE PARA HORARIOS MÚLTIPLES (schedule_slots) ======
        // Obtener slots de horarios con retrocompatibilidad
        $slots = [];

        // PRIORIDAD 1: Leer schedule_slots (formato nuevo)
        if (!empty($programInfo['schedule_slots'])) {
            $slots = $programInfo['schedule_slots'];
        }
        // PRIORIDAD 2: Migrar desde formato antiguo
        elseif (!empty($programInfo['schedule_days']) && !empty($programInfo['schedule_start_time'])) {
            $slots = [[
                'days' => $programInfo['schedule_days'],
                'start_time' => $programInfo['schedule_start_time'],
                'duration' => (int)($programInfo['schedule_duration'] ?? 60)
            ]];
        }

        // Procesar cada bloque de horario
        foreach ($slots as $slot) {
            $scheduleDays = $slot['days'] ?? [];
            $startTime = $slot['start_time'] ?? '';
            $duration = (int)($slot['duration'] ?? 60);

            if (empty($scheduleDays) || empty($startTime)) continue;

            // Obtener último episodio RSS (caché 6h, una vez por slot)
            $rssUrl = $programInfo['rss_feed'] ?? '';
            $latestEpisode = null;
            if (!empty($rssUrl)) {
                $episode = getLatestEpisodeFromRSS($rssUrl, 21600);
                if ($episode) {
                    $latestEpisode = [
                        'title' => $episode['title'] ?? '',
                        'link' => $episode['link'] ?? ''
                    ];
                }
            }

            foreach ($scheduleDays as $day) {
                $startDateTime = DateTime::createFromFormat('H:i', $startTime);
                if (!$startDateTime) continue;

                $endDateTime = clone $startDateTime;
                $endDateTime->modify("+{$duration} minutes");

                $startMinutes = (int)$startDateTime->format('H') * 60 + (int)$startDateTime->format('i');
                $endMinutes = (int)$endDateTime->format('H') * 60 + (int)$endDateTime->format('i');

                // Obtener nombre para mostrar
                $programName = $programInfo['original_name'] ?? getProgramNameFromKey($programKey);
                $displayTitle = !empty($programInfo['display_title']) ? $programInfo['display_title'] : $programName;

                $programData = [
                    'title' => $displayTitle,
                    'description' => $programInfo['short_description'] ?? $programInfo['description'] ?? '',
                    'image' => $programInfo['image'] ?? $programInfo['image_url'] ?? '',
                    'type' => 'live',
                    'url' => $programInfo['url'] ?? '',
                    'social' => [
                        'twitter' => $programInfo['social_twitter'] ?? '',
                        'instagram' => $programInfo['social_instagram'] ?? '',
                        'facebook' => $programInfo['social_facebook'] ?? '',
                        'mastodon' => $programInfo['social_mastodon'] ?? '',
                        'bluesky' => $programInfo['social_bluesky'] ?? ''
                    ],
                    'rss_feed' => $rssUrl,
                    'latest_episode' => $latestEpisode
                ];

                // Manejar programas que cruzan medianoche
                if ($endMinutes > 1440 || $endMinutes < $startMinutes) {
                    // Parte 1: día actual hasta 23:59
                    $scheduleByDay[$day]['programs'][] = array_merge($programData, [
                        'start_time' => $startDateTime->format('H:i'),
                        'end_time' => '23:59'
                    ]);

                    // Parte 2: día siguiente desde 00:00
                    $nextDay = ($day + 1) % 7;
                    $scheduleByDay[$nextDay]['programs'][] = array_merge($programData, [
                        'start_time' => '00:00',
                        'end_time' => $endDateTime->format('H:i')
                    ]);
                } else {
                    // Programa normal
                    $scheduleByDay[$day]['programs'][] = array_merge($programData, [
                        'start_time' => $startDateTime->format('H:i'),
                        'end_time' => $endDateTime->format('H:i')
                    ]);
                }
            }
        }
    }
}

// ====== AÑADIR EVENTOS DE AZURACAST ======
foreach ($schedule as $event) {
    $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
    $playlist = $event['playlist'] ?? $title;
    $start = $event['start_timestamp'] ?? $event['start'] ?? null;

    if ($start === null) continue;

    // Parsear fecha/hora
    $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
    $timezone = new DateTimeZone('Europe/Madrid');
    $startDateTime->setTimezone($timezone);

    $dayOfWeek = (int)$startDateTime->format('w');

    // Obtener info del programa
    $programInfo = $programsData[$playlist] ?? null;
    $playlistType = $programInfo['playlist_type'] ?? 'program';

    // Saltar jingles y programas ocultos
    if ($playlistType === 'jingles') continue;
    if (!empty($programInfo['hidden_from_schedule'])) continue;

    // Calcular hora de fin
    $customDuration = isset($programInfo['schedule_duration']) ? (int)$programInfo['schedule_duration'] : 0;

    if ($customDuration > 0) {
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$customDuration} minutes");
    } else {
        $end = $event['end_timestamp'] ?? $event['end'] ?? null;
        $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

        if ($endDateTime) {
            $endDateTime->setTimezone($timezone);
        }

        // Fallback: 1 hora
        if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+1 hour');
        }
    }

    // Título para mostrar
    $displayTitle = !empty($programInfo['display_title']) ? $programInfo['display_title'] : $title;

    // Obtener último episodio RSS (caché 6h)
    $rssUrl = $programInfo['rss_feed'] ?? '';
    $latestEpisode = null;
    if (!empty($rssUrl)) {
        $episode = getLatestEpisodeFromRSS($rssUrl, 21600);
        if ($episode) {
            $latestEpisode = [
                'title' => $episode['title'] ?? '',
                'link' => $episode['link'] ?? ''
            ];
        }
    }

    $eventData = [
        'title' => $displayTitle,
        'description' => $programInfo['description'] ?? '',
        'image' => $programInfo['image_url'] ?? '',
        'type' => $playlistType === 'music_block' ? 'music' : 'program',
        'url' => $programInfo['url'] ?? '',
        'social' => [
            'twitter' => $programInfo['social_twitter'] ?? '',
            'instagram' => $programInfo['social_instagram'] ?? '',
            'facebook' => $programInfo['social_facebook'] ?? '',
            'mastodon' => $programInfo['social_mastodon'] ?? '',
            'bluesky' => $programInfo['social_bluesky'] ?? ''
        ],
        'rss_feed' => $rssUrl,
        'latest_episode' => $latestEpisode
    ];

    // Manejar eventos que cruzan medianoche
    if ($endDateTime->format('d') != $startDateTime->format('d')) {
        // Parte 1: día actual hasta 23:59
        $scheduleByDay[$dayOfWeek]['programs'][] = array_merge($eventData, [
            'start_time' => $startDateTime->format('H:i'),
            'end_time' => '23:59'
        ]);

        // Parte 2: día siguiente desde 00:00
        $nextDay = ($dayOfWeek + 1) % 7;
        $scheduleByDay[$nextDay]['programs'][] = array_merge($eventData, [
            'start_time' => '00:00',
            'end_time' => $endDateTime->format('H:i')
        ]);
    } else {
        // Evento normal
        $scheduleByDay[$dayOfWeek]['programs'][] = array_merge($eventData, [
            'start_time' => $startDateTime->format('H:i'),
            'end_time' => $endDateTime->format('H:i')
        ]);
    }
}

// ====== DEDUPLICAR Y ORDENAR ======
foreach ($scheduleByDay as $day => &$dayData) {
    $programs = $dayData['programs'];

    // Eliminar duplicados (mismo título y hora de inicio)
    $seen = [];
    $unique = [];
    foreach ($programs as $prog) {
        $key = $prog['title'] . '_' . $prog['start_time'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $prog;
        }
    }

    // Ordenar por hora de inicio
    usort($unique, function($a, $b) {
        return strcmp($a['start_time'], $b['start_time']);
    });

    $dayData['programs'] = $unique;
}
unset($dayData);

// ====== PREPARAR RESPUESTA ======
$response = [
    'success' => true,
    'station' => [
        'username' => $station,
        'name' => $stationName,
        'stream_url' => $azConfig['stream_url'] ?? ''
    ],
    'config' => [
        'color' => $azConfig['widget_color'] ?? '#10b981',
        'background_color' => $azConfig['widget_background_color'] ?? '#ffffff',
        'style' => $azConfig['widget_style'] ?? 'modern',
        'font_size' => $azConfig['widget_font_size'] ?? 'medium'
    ],
    'schedule' => array_values($scheduleByDay),
    'generated_at' => date('c'),
    'powered_by' => 'SAPO'
];

// Guardar en caché
cacheSet($cacheKey, $response);

// Headers de caché HTTP (5 minutos)
header('Cache-Control: public, max-age=300');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

// Enviar respuesta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
