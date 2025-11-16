<?php
// includes/security_logger.php - Sistema centralizado de logging de seguridad

/**
 * Registrar evento de seguridad
 *
 * @param string $event_type Tipo de evento (path_traversal, xxe, ssrf, csrf_fail, etc)
 * @param string $severity Severidad (critical, high, medium, low, info)
 * @param string $message Mensaje descriptivo
 * @param array $context Contexto adicional (IP, usuario, URL, etc)
 */
function logSecurityEvent($event_type, $severity, $message, $context = []) {
    $logDir = DATA_DIR . '/security_logs';

    // Crear directorio de logs si no existe
    if (!file_exists($logDir)) {
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        mkdir($logDir, 0750, true);  // Permisos más restrictivos para logs
    }

    // Preparar datos del evento
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'severity' => strtoupper($severity),
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'username' => $_SESSION['username'] ?? 'anonymous',
        'context' => $context
    ];

    // Log en formato JSON por día
    $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
    $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    // Escribir log
    @file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);

    // También log en error_log de PHP si es crítico o alto
    if (in_array($severity, ['critical', 'high'])) {
        error_log("[SECURITY] [{$severity}] {$event_type}: {$message}");
    }

    // Limpiar logs antiguos (más de 90 días)
    cleanOldSecurityLogs($logDir);
}

/**
 * Limpiar logs de seguridad antiguos (>90 días)
 *
 * @param string $logDir Directorio de logs
 */
function cleanOldSecurityLogs($logDir) {
    // Solo ejecutar limpieza aleatoriamente (1% de las veces) para no sobrecargar
    if (rand(1, 100) !== 1) {
        return;
    }

    $files = glob($logDir . '/security_*.log');
    if (!$files) {
        return;
    }

    $cutoffTime = time() - (90 * 24 * 60 * 60); // 90 días

    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            @unlink($file);
        }
    }
}

/**
 * Obtener resumen de eventos de seguridad
 *
 * @param int $days Días a revisar (por defecto 7)
 * @return array Resumen de eventos
 */
function getSecurityEventsSummary($days = 7) {
    $logDir = DATA_DIR . '/security_logs';

    if (!file_exists($logDir)) {
        return [
            'total' => 0,
            'by_severity' => [],
            'by_type' => [],
            'recent_events' => []
        ];
    }

    $events = [];
    $startDate = strtotime("-{$days} days");

    // Leer logs de los últimos X días
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $logFile = $logDir . '/security_' . $date . '.log';

        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $event = json_decode($line, true);
                if ($event) {
                    $events[] = $event;
                }
            }
        }
    }

    // Agrupar por severidad y tipo
    $bySeverity = [];
    $byType = [];

    foreach ($events as $event) {
        $severity = $event['severity'] ?? 'UNKNOWN';
        $type = $event['event_type'] ?? 'unknown';

        $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + 1;
        $byType[$type] = ($byType[$type] ?? 0) + 1;
    }

    // Ordenar eventos recientes
    usort($events, function($a, $b) {
        return strcmp($b['timestamp'], $a['timestamp']);
    });

    return [
        'total' => count($events),
        'by_severity' => $bySeverity,
        'by_type' => $byType,
        'recent_events' => array_slice($events, 0, 50)
    ];
}

/**
 * Atajos para diferentes tipos de eventos
 */
function logPathTraversal($attempted_path, $context = []) {
    logSecurityEvent('path_traversal', 'critical',
        "Intento de path traversal detectado: {$attempted_path}",
        array_merge(['attempted_path' => $attempted_path], $context)
    );
}

function logXXEAttempt($rss_url, $context = []) {
    logSecurityEvent('xxe_attempt', 'high',
        "Posible intento XXE desde RSS: {$rss_url}",
        array_merge(['rss_url' => $rss_url], $context)
    );
}

function logSSRFAttempt($blocked_url, $reason, $context = []) {
    logSecurityEvent('ssrf_attempt', 'high',
        "Intento SSRF bloqueado: {$blocked_url} - Razón: {$reason}",
        array_merge(['blocked_url' => $blocked_url, 'reason' => $reason], $context)
    );
}

function logCSRFFail($context = []) {
    logSecurityEvent('csrf_fail', 'medium',
        "Token CSRF inválido o ausente",
        $context
    );
}

function logRateLimitExceeded($action, $context = []) {
    logSecurityEvent('rate_limit', 'medium',
        "Límite de tasa excedido para acción: {$action}",
        array_merge(['action' => $action], $context)
    );
}

function logInvalidInput($input_type, $input_value, $context = []) {
    logSecurityEvent('invalid_input', 'low',
        "Entrada inválida detectada ({$input_type}): {$input_value}",
        array_merge(['input_type' => $input_type, 'input_value' => $input_value], $context)
    );
}
