<?php
// includes/azuracast.php - Funciones para integración con AzuraCast

/**
 * Obtener la programación de una emisora desde AzuraCast
 *
 * @param string $username Nombre de usuario de la emisora
 * @return array|false Array con los eventos de la programación o false si hay error
 */
function getAzuracastSchedule($username) {
    // Obtener configuración global
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';

    if (empty($apiUrl)) {
        error_log("AzuraCast: URL de API no configurada");
        return false;
    }

    // Obtener station_id del usuario
    $userData = getUserDB($username);
    $stationId = $userData['azuracast']['station_id'] ?? null;

    if (empty($stationId)) {
        error_log("AzuraCast: Station ID no configurado para usuario $username");
        return false;
    }

    // Construir URL de la API
    $scheduleUrl = rtrim($apiUrl, '/') . '/station/' . $stationId . '/schedule';

    // Hacer petición a la API
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,  // Timeout de 10 segundos
                'user_agent' => 'SAPO/1.0'
            ]
        ]);

        $response = @file_get_contents($scheduleUrl, false, $context);

        if ($response === false) {
            error_log("AzuraCast: Error al obtener programación desde $scheduleUrl");
            return false;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AzuraCast: Error al decodificar JSON: " . json_last_error_msg());
            return false;
        }

        return $data;

    } catch (Exception $e) {
        error_log("AzuraCast: Excepción al obtener programación: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualizar configuración de AzuraCast para un usuario
 *
 * @param string $username Nombre de usuario
 * @param int|null $stationId ID de la estación en AzuraCast
 * @param string $widgetColor Color del widget en formato hexadecimal
 * @param bool $showLogo Mostrar logo en el widget
 * @param string $logoUrl URL del logo
 * @return bool True si se guardó correctamente
 */
function updateAzuracastConfig($username, $stationId, $widgetColor = '#3b82f6', $showLogo = false, $logoUrl = '') {
    $userData = getUserDB($username);

    $userData['azuracast'] = [
        'station_id' => $stationId !== '' ? intval($stationId) : null,
        'widget_color' => $widgetColor,
        'show_logo' => (bool)$showLogo,
        'logo_url' => $logoUrl
    ];

    return saveUserDB($username, $userData);
}

/**
 * Obtener configuración de AzuraCast de un usuario
 *
 * @param string $username Nombre de usuario
 * @return array Configuración de AzuraCast
 */
function getAzuracastConfig($username) {
    $userData = getUserDB($username);
    return $userData['azuracast'] ?? [
        'station_id' => null,
        'widget_color' => '#3b82f6',
        'show_logo' => false,
        'logo_url' => ''
    ];
}

/**
 * Formatear eventos de AzuraCast para FullCalendar
 *
 * @param array $events Eventos desde la API de AzuraCast
 * @param string $color Color para los eventos
 * @return array Eventos formateados para FullCalendar
 */
function formatEventsForCalendar($events, $color = '#3b82f6') {
    if (!is_array($events)) {
        return [];
    }

    $formattedEvents = [];

    foreach ($events as $event) {
        // AzuraCast devuelve diferentes formatos dependiendo de la versión
        // Intentar adaptar a múltiples formatos

        $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
        $start = $event['start_timestamp'] ?? $event['start'] ?? null;
        $end = $event['end_timestamp'] ?? $event['end'] ?? null;

        if ($start === null) {
            continue; // Saltar eventos sin hora de inicio
        }

        // Convertir timestamp a DateTime para extraer día y hora
        $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
        $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

        // Obtener día de la semana (0=Domingo, 1=Lunes, etc.) como ENTERO
        $dayOfWeek = (int)$startDateTime->format('w');

        // Obtener solo la hora (formato HH:mm:ss)
        $startTime = $startDateTime->format('H:i:s');

        // Si no hay endTime o es igual a startTime, calcular duración desde el título
        // Muchos nombres tienen el formato "NOMBRE - DURACION" ej: "PROGRAMA - 30" (30 minutos)
        if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
            // Intentar extraer duración del título (ej: "PROGRAMA - 30" = 30 minutos)
            if (preg_match('/-\s*(\d+)\s*$/', $title, $matches)) {
                $durationMinutes = (int)$matches[1];
                $endDateTime = clone $startDateTime;
                $endDateTime->modify("+{$durationMinutes} minutes");
            } else {
                // Por defecto, 1 hora si no se puede determinar
                $endDateTime = clone $startDateTime;
                $endDateTime->modify('+1 hour');
            }
        }

        $endTime = $endDateTime->format('H:i:s');

        // Parsear descripción (formato: descripción;temática;url)
        $rawDescription = $event['description'] ?? '';
        $descriptionParts = explode(';', $rawDescription);

        $programDescription = trim($descriptionParts[0] ?? '');
        $programType = trim($descriptionParts[1] ?? '');
        $programUrl = trim($descriptionParts[2] ?? '');

        // Crear evento recurrente semanal usando daysOfWeek
        $formattedEvents[] = [
            'title' => $title,
            'daysOfWeek' => [$dayOfWeek], // Día de la semana que se repite (como entero)
            'startTime' => $startTime,     // Hora de inicio
            'endTime' => $endTime,         // Hora de fin
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'type' => $event['type'] ?? 'playlist',
                'playlist' => $event['playlist'] ?? '',
                'description' => $programDescription,
                'programType' => $programType,
                'programUrl' => $programUrl
            ]
        ];
    }

    return $formattedEvents;
}

/**
 * Verificar si la API de AzuraCast está accesible
 *
 * @return array Array con 'success' (bool) y 'message' (string)
 */
function testAzuracastConnection() {
    $config = getConfig();
    $apiUrl = $config['azuracast_api_url'] ?? '';

    if (empty($apiUrl)) {
        return [
            'success' => false,
            'message' => 'URL de API no configurada'
        ];
    }

    // Intentar hacer ping a la API
    $statusUrl = rtrim($apiUrl, '/') . '/status';

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'SAPO/1.0'
        ]
    ]);

    $response = @file_get_contents($statusUrl, false, $context);

    if ($response === false) {
        return [
            'success' => false,
            'message' => 'No se pudo conectar a la API'
        ];
    }

    return [
        'success' => true,
        'message' => 'Conexión exitosa'
    ];
}
