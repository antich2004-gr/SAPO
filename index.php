<?php
// index.php - Punto de entrada principal SAPO

// Configurar zona horaria a CET/CEST (Europe/Madrid)
date_default_timezone_set('Europe/Madrid');

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; frame-src 'self';"  );
// HSTS - Solo si se usa HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

require_once 'config.php';
require_once INCLUDES_DIR . '/session.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/auth.php';
require_once INCLUDES_DIR . '/utils.php';
require_once INCLUDES_DIR . '/categories.php';
require_once INCLUDES_DIR . '/programs.php';
require_once INCLUDES_DIR . '/podcasts.php';
require_once INCLUDES_DIR . '/feed.php';
require_once INCLUDES_DIR . '/reports.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/liquidsoap_log.php';
require_once INCLUDES_DIR . '/time_signals.php';
require_once INCLUDES_DIR . '/recordings.php';
require_once INCLUDES_DIR . '/overrides.php';

initSession();

    // AJAX: Guardar configuración de Liquidsoap
    if (isset($_POST['action']) && $_POST['action'] == 'save_liquidsoap_config' && isLoggedIn()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $username = $_SESSION['username'];
        $section = $_POST['section'] ?? '';
        $code = $_POST['code'] ?? '';

        if (empty($section) || empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        // Validar sección permitida
        $allowedSections = ['custom_config', 'dj_on_air_hook', 'dj_off_air_hook'];
        if (!in_array($section, $allowedSections)) {
            echo json_encode(['success' => false, 'message' => 'Sección no válida']);
            exit;
        }

        // Obtener configuración actual
        $currentConfig = getAzuracastLiquidsoapConfig($username);
        if ($currentConfig === false) {
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener la configuración actual de AzuraCast']);
            exit;
        }

        // Preparar datos para actualizar (formato clave => valor)
        $configToUpdate = [];
        foreach ($currentConfig as $item) {
            $field = $item['field'] ?? '';
            $value = $item['value'] ?? '';

            // Si es la sección donde queremos añadir, concatenar el código
            if ($field === $section) {
                // Añadir salto de línea si ya hay contenido
                if (!empty(trim($value))) {
                    $value .= "\n\n";
                }
                $value .= $code;
            }

            $configToUpdate[$field] = $value;
        }

        // Guardar configuración actualizada
        $result = updateAzuracastLiquidsoapConfig($username, $configToUpdate);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente en AzuraCast']);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
        exit;
    }

    // AJAX: Guardar timestamp de última actualización de feeds
    if (isset($_GET['action']) && $_GET['action'] == 'save_feeds_timestamp' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // SEGURIDAD: Rate limiting para prevenir spam
        if (!checkRateLimit('save_feeds_timestamp', 10, 60)) {
            http_response_code(429); // Too Many Requests
            echo json_encode(['success' => false, 'error' => ERROR_RATE_LIMIT]);
            exit;
        }

        $username = $_SESSION['username'];
        $userData = getUserDB($username);
        $userData['last_feeds_update'] = time();
        saveUserDB($username, $userData);

        echo json_encode(['success' => true]);
        exit;
    }

    // AJAX: Actualizar feeds progresivamente
    if (isset($_GET['action']) && $_GET['action'] == 'refresh_feeds' && isLoggedIn()) {
        header('Content-Type: application/json');

        // SEGURIDAD: Rate limiting más restrictivo para operaciones costosas
        // Límite: 150 peticiones cada 5 minutos (300 segundos)
        // Necesario para emisoras con 50-100+ suscripciones
        if (!checkRateLimit('refresh_feeds', 150, 300)) {
            http_response_code(429); // Too Many Requests
            echo json_encode(['success' => false, 'error' => 'Límite excedido. Espera 5 minutos antes de refrescar feeds nuevamente.']);
            exit;
        }

        $username = $_SESSION['username'];
        $podcasts = readServerList($username);

        // Ordenar alfabéticamente igual que en user.php para consistencia
        usort($podcasts, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        $podcasts = array_values($podcasts);

        // Si se especifica un índice, actualizar solo ese podcast
        if (isset($_GET['index']) && is_numeric($_GET['index'])) {
            $index = intval($_GET['index']);
            
            if ($index >= 0 && $index < count($podcasts)) {
                $podcast = $podcasts[$index];
                
                // Actualizar feed
                $feedInfo = getCachedFeedInfo($podcast['url'], true);
                
                echo json_encode([
                    'success' => true,
                    'index' => $index,
                    'total' => count($podcasts),
                    'podcast' => $podcast['name'],
                    'timestamp' => $feedInfo['timestamp']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Índice inválido']);
            }
        } else {
            // Devolver información inicial (total de podcasts)
            echo json_encode([
                'success' => true,
                'total' => count($podcasts),
                'podcasts' => array_map(function($p) { return $p['name']; }, $podcasts)
            ]);
        }
        exit;
    }

    // AJAX: Subir archivo de señal horaria
    if (isset($_POST['action']) && $_POST['action'] == 'upload_time_signal' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo']);
            exit;
        }

        $result = uploadTimeSignal($_SESSION['username'], $_FILES['file']);
        echo json_encode($result);
        exit;
    }

    // AJAX: Listar archivos de señales horarias
    if (isset($_GET['action']) && $_GET['action'] == 'list_time_signals' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $files = listTimeSignals($_SESSION['username']);
        echo json_encode(['success' => true, 'files' => $files]);
        exit;
    }

    // AJAX: Eliminar archivo de señal horaria
    if (isset($_POST['action']) && $_POST['action'] == 'delete_time_signal' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'message' => 'Nombre de archivo no especificado']);
            exit;
        }

        $result = deleteTimeSignal($_SESSION['username'], $filename);
        echo json_encode($result);
        exit;
    }

    // AJAX: Obtener configuración de señales horarias
    if (isset($_GET['action']) && $_GET['action'] == 'get_time_signals_config' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $config = getTimeSignalsConfig($_SESSION['username']);
        echo json_encode(['success' => true, 'config' => $config]);
        exit;
    }

    // AJAX: Guardar configuración de señales horarias
    if (isset($_POST['action']) && $_POST['action'] == 'save_time_signals_config' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $result = processTimeSignalsForm($_SESSION['username'], $_POST);
        echo json_encode($result);
        exit;
    }

    // AJAX: Aplicar señales horarias (alias de save_time_signals_config)
    if (isset($_POST['action']) && $_POST['action'] == 'apply_time_signals' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $result = processTimeSignalsForm($_SESSION['username'], $_POST);
        echo json_encode($result);
        exit;
    }

    // AJAX: Sincronizar configuración desde Liquidsoap
    if (isset($_POST['action']) && $_POST['action'] == 'sync_time_signals_from_liquidsoap' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $synced = syncTimeSignalsFromLiquidsoap($_SESSION['username']);

        if ($synced) {
            $config = getTimeSignalsConfig($_SESSION['username']);
            echo json_encode([
                'success' => true,
                'message' => 'Configuración sincronizada correctamente',
                'config' => $config
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró configuración de señales horarias en Liquidsoap'
            ]);
        }
        exit;
    }

    // AJAX: Generar código de señales horarias (para mostrar en interfaz)
    if (isset($_POST['action']) && $_POST['action'] == 'generate_time_signals_code' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $frequency = $_POST['frequency'] ?? 'hourly';
        $duration = floatval($_POST['duration'] ?? 1.5);
        $attenuationPercent = intval($_POST['attenuation'] ?? 30);
        $attenuation = $attenuationPercent / 100; // Convertir porcentaje a decimal
        $username = $_SESSION['username'];

        // Validar rangos
        $duration = max(0.2, min(10, $duration));
        $attenuation = max(0, min(1, $attenuation));

        // Obtener el último archivo subido
        $files = listTimeSignals($username);

        if (empty($files)) {
            echo json_encode(['success' => false, 'message' => 'Debe subir un archivo de señal horaria primero']);
            exit;
        }

        $signalFile = $files[0]['name'];
        $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

        // Generar código con valores personalizados
        $liquidsoapPath = "/var/azuracast/stations/{$username}/media/senales_horarias/{$signalFile}";
        $musicVolume = 1.0 - $attenuation; // Convertir atenuación a volumen
        $offsetSeconds = -50; // Compensar delay de 60s (adelantar 50s)
        $liquidsoapCode = generateTimeSignalsSmooth($liquidsoapPath, $days, $frequency, $musicVolume, $duration, $offsetSeconds);

        if (empty($liquidsoapCode)) {
            echo json_encode(['success' => false, 'message' => 'Error al generar código']);
            exit;
        }

        // Guardar configuración con valores personalizados
        $config = [
            'signal_file' => $signalFile,
            'frequency' => $frequency,
            'days' => $days,
            'duration' => $duration,
            'attenuation' => $attenuationPercent // Guardar como porcentaje
        ];
        saveTimeSignalsConfig($username, $config);

        echo json_encode([
            'success' => true,
            'code' => $liquidsoapCode,
            'signal_file' => $signalFile
        ]);
        exit;
    }

    // AJAX: Aplicar señales horarias via API de AzuraCast
    if (isset($_POST['action']) && $_POST['action'] == 'apply_time_signals_via_api' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        // Recibir parámetros del formulario
        $frequency = $_POST['frequency'] ?? 'hourly';
        $duration = floatval($_POST['duration'] ?? 1.5);
        $attenuationPercent = intval($_POST['attenuation'] ?? 30);
        $username = $_SESSION['username'];

        // Validar rangos
        $duration = max(0.2, min(10, $duration));
        $attenuationPercent = max(0, min(100, $attenuationPercent));

        // Obtener el último archivo subido
        $files = listTimeSignals($username);

        if (empty($files)) {
            echo json_encode(['success' => false, 'message' => 'Debe subir un archivo de señal horaria primero']);
            exit;
        }

        $signalFile = $files[0]['name'];
        $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

        // Guardar configuración actualizada con valores personalizados
        $config = [
            'signal_file' => $signalFile,
            'frequency' => $frequency,
            'days' => $days,
            'duration' => $duration,
            'attenuation' => $attenuationPercent
        ];
        saveTimeSignalsConfig($username, $config);

        // Aplicar via API
        $result = applyTimeSignalsViaAPI($username);
        echo json_encode($result);
        exit;
    }

    // ====================================================================
    // AJAX HANDLERS - GRABACIONES
    // ====================================================================

    // AJAX: Diagnóstico de ruta de grabaciones (temporal)
    if (isset($_GET['action']) && $_GET['action'] == 'debug_recordings_path' && isLoggedIn()) {
        header('Content-Type: application/json');

        // Admin puede consultar cualquier usuario; usuario normal solo el suyo
        if (isAdmin()) {
            $targetUser = $_GET['user'] ?? $_SESSION['username'];
        } else {
            $targetUser = $_SESSION['username'];
        }

        $stationInfo = getStationInfo($targetUser);
        $resolvedPath = getRecordingsDir($targetUser);
        $dirExists = is_dir($resolvedPath);

        echo json_encode([
            'user' => $targetUser,
            'resolved_path' => $resolvedPath,
            'dir_exists' => $dirExists,
            'station_api_ok' => $stationInfo !== null,
            'recordings_storage_location' => $stationInfo['recordings_storage_location'] ?? 'NO PRESENTE EN API',
            'radio_base_dir' => $stationInfo['radio_base_dir'] ?? 'NO PRESENTE EN API',
            'short_name' => $stationInfo['short_name'] ?? 'NO PRESENTE EN API',
            'station_info_keys' => $stationInfo ? array_keys($stationInfo) : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // AJAX: Obtener lista de grabaciones
    if (isset($_GET['action']) && $_GET['action'] == 'get_recordings_list' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $recordings = listRecordings($_SESSION['username']);
        echo json_encode(['success' => true, 'recordings' => $recordings]);
        exit;
    }

    // Descarga de grabación
    if (isset($_GET['action']) && $_GET['action'] == 'download_recording' && isLoggedIn() && !isAdmin()) {
        $filename = $_GET['filename'] ?? '';

        if (empty($filename) || strpos($filename, '..') !== false) {
            http_response_code(400);
            exit('Archivo inválido');
        }

        $recordingsDir = getRecordingsDir($_SESSION['username']);
        $filePath = realpath($recordingsDir . '/' . $filename);
        $realRecordingsDir = realpath($recordingsDir);

        if ($filePath === false || $realRecordingsDir === false || strpos($filePath, $realRecordingsDir . DIRECTORY_SEPARATOR) !== 0) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        if (!is_file($filePath)) {
            http_response_code(404);
            exit('Archivo no encontrado');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac'])) {
            http_response_code(403);
            exit('Tipo de archivo no permitido');
        }

        $basename = basename($filePath);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $basename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }

    // AJAX: Obtener estadísticas de grabaciones
    if (isset($_GET['action']) && $_GET['action'] == 'get_recordings_stats' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $stats = getRecordingsStats($_SESSION['username']);
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    // AJAX: Obtener configuración de grabaciones
    if (isset($_GET['action']) && $_GET['action'] == 'get_recordings_config' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $config = getRecordingsConfig($_SESSION['username']);
        echo json_encode(['success' => true, 'config' => $config]);
        exit;
    }

    // AJAX: Guardar configuración de grabaciones
    if (isset($_POST['action']) && $_POST['action'] == 'save_recordings_config' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $retentionDays = intval($_POST['retention_days'] ?? 30);
        $autoDelete = isset($_POST['auto_delete']) ? (bool)$_POST['auto_delete'] : true;

        $result = saveRecordingsConfig($_SESSION['username'], $retentionDays, $autoDelete);
        echo json_encode($result);
        exit;
    }

    // AJAX: Eliminar una grabación específica
    if (isset($_POST['action']) && $_POST['action'] == 'delete_recording' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $filename = $_POST['filename'] ?? '';
        $result = deleteRecording($_SESSION['username'], $filename);
        echo json_encode($result);
        exit;
    }

    // AJAX: Eliminar grabaciones antiguas
    if (isset($_POST['action']) && $_POST['action'] == 'delete_old_recordings' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $result = deleteOldRecordings($_SESSION['username']);
        echo json_encode($result);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'clear_public_cache' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => ERROR_INVALID_TOKEN]);
            exit;
        }

        $deleted = cachePurgeStation($_SESSION['username']);
        echo json_encode(['success' => true, 'message' => "Caché vaciada ({$deleted} archivos eliminados)"]);
        exit;
    }


// ── AJAX: corrección manual de emisiones ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'override_emision'
    && isLoggedIn()) {
    header('Content-Type: application/json');

    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }

    // Determinar emisora objetivo
    if (!isAdmin() || isImpersonating()) {
        $targetUser = $_SESSION['username'];
    } else {
        $targetUser     = $_POST['station'] ?? '';
        if (!validateUsernameStrict($targetUser)) {
            echo json_encode(['ok' => false, 'error' => 'Emisora inválida']);
            exit;
        }
        $allUsers       = getAllUsers();
        $validUsernames = array_column(
            array_filter($allUsers, fn($u) => !($u['is_admin'] ?? false)),
            'username'
        );
        if (!in_array($targetUser, $validUsernames, true)) {
            echo json_encode(['ok' => false, 'error' => 'Emisora no encontrada']);
            exit;
        }
    }

    $progKey = $_POST['prog_key'] ?? '';
    $date    = $_POST['date']     ?? '';
    $remove  = ($_POST['remove'] ?? '') === '1';
    $reason  = substr(trim($_POST['reason'] ?? ''), 0, 200);

    // Validar fecha (no futura)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
        echo json_encode(['ok' => false, 'error' => 'Fecha inválida']);
        exit;
    }
    if (empty($progKey) || mb_strlen($progKey) > 300) {
        echo json_encode(['ok' => false, 'error' => 'Programa inválido']);
        exit;
    }

    $ok = $remove
        ? removeOverride($targetUser, $progKey, $date)
        : saveOverride($targetUser, $progKey, $date, $reason, $_SESSION['username']);

    echo json_encode(['ok' => $ok]);
    exit;
}

$message = '';
$error = '';
$action = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validar CSRF
    if ($action !== '' && $action !== 'login' && $action !== 'logout') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            $error = ERROR_INVALID_TOKEN;
            $action = '';
        }
    }
    
    // Rate limiting
    if ($action !== '' && $action !== 'login' && $action !== 'logout') {
        if (!checkRateLimit($action, 20, 60)) {
            $error = ERROR_RATE_LIMIT;
            $action = '';
        }
    }
    
    // LOGIN
    if ($action == 'login') {
        // Validar CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            $error = ERROR_INVALID_TOKEN;
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!validateInput($username, 'username')) {
                $error = 'Nombre de usuario inválido';
            } else {
                $result = authenticateUser($username, $password);
                if ($result['success']) {
                    loginUser($result['user']);

                    // Comprobar espacio en disco al iniciar sesión y guardar en sesión
                    try {
                        $_SESSION['storage_alert'] = getStationStorageAlert($username);
                    } catch (Throwable $e) {
                        $_SESSION['storage_alert'] = null;
                    }

                    // Verificar si han pasado más de 24 horas desde la última actualización de feeds
                    $redirect_url = basename($_SERVER['PHP_SELF']);

                    // Solo verificar para usuarios no-admin
                    if (!isAdmin()) {
                        $userData = getUserDB($username);
                        $lastUpdate = $userData['last_feeds_update'] ?? 0;

                        if ($lastUpdate > 0) {
                            $hours_passed = (time() - $lastUpdate) / 3600;
                            if ($hours_passed >= 24) {
                                $redirect_url .= '?auto_refresh_feeds=1';
                            }
                        } else {
                            // Primera vez: forzar actualización
                            $redirect_url .= '?auto_refresh_feeds=1';
                        }
                    }

                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
    
    // LOGOUT
    if ($action == 'logout') {
        logoutUser();
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }

    // IMPERSONAR USUARIO (solo admin)
    if ($action == 'impersonate_user' && isAdmin()) {
        $token = $_POST['csrf_token'] ?? '';
        if (validateCSRFToken($token)) {
            $userId = intval($_POST['user_id'] ?? 0);
            $user = findUserById($userId);
            if ($user && !($user['is_admin'] ?? false)) {
                startImpersonating($user);
                header('Location: ' . basename($_SERVER['PHP_SELF']));
                exit;
            }
        }
    }

    // DEJAR DE IMPERSONAR
    if ($action == 'stop_impersonating' && isImpersonating()) {
        $token = $_POST['csrf_token'] ?? '';
        if (validateCSRFToken($token)) {
            stopImpersonating();
            header('Location: ' . basename($_SERVER['PHP_SELF']));
            exit;
        }
    }

    // SAVE CONFIG (admin)
    if ($action == 'save_config' && isAdmin()) {
        $basePath = trim($_POST['base_path'] ?? '');
        $subsFolder = trim($_POST['subscriptions_folder'] ?? 'Suscripciones');
        $podcastsFolder = trim($_POST['podcasts_folder'] ?? 'Podcasts');
        $azuracastApiUrl = trim($_POST['azuracast_api_url'] ?? '');
        $azuracastApiKey = trim($_POST['azuracast_api_key'] ?? '');
        $recordingsMountBase = trim($_POST['recordings_mount_base'] ?? '');

        if (empty($basePath)) {
            $error = 'La ruta base es obligatoria';
        } elseif (!is_dir($basePath)) {
            $error = 'La ruta base no existe o no es accesible';
        } else {
            if (saveConfig($basePath, $subsFolder, $podcastsFolder, $azuracastApiUrl, $azuracastApiKey, $recordingsMountBase)) {
                $message = 'Configuracion guardada correctamente';
            } else {
                $error = 'Error al guardar la configuracion';
            }
        }
    }
    
    // CREATE USER (admin)
    if ($action == 'create_user' && isAdmin()) {
        $username = slugify($_POST['new_username'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $station_name = $_POST['station_name'] ?? '';
        
        if (empty($username) || empty($password) || empty($station_name)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif (!validateInput($username, 'username')) {
            $error = 'Nombre de usuario inválido';
        } else {
            $config = getConfig();
            if (empty($config['base_path'])) {
                $error = 'Primero debes configurar la ruta base';
            } elseif (findUserByUsername($username)) {
                $error = 'El usuario ya existe';
            } else {
                createUser($username, $password, $station_name);
                
                $path = getServerListPath($username);
                if ($path) {
                    $dir = dirname($path);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    if (!file_exists($path)) {
                        file_put_contents($path, '');
                    }
                }
                
                $message = 'Usuario creado correctamente';
            }
        }
    }
    
    // DELETE USER (admin)
    if ($action == 'delete_user' && isAdmin()) {
        $userId = intval($_POST['user_id'] ?? 0);
        $result = deleteUser($userId);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['error'];
        }
    }

    // ADMIN: CHANGE USER PASSWORD
    if ($action == 'admin_change_password' && isAdmin()) {
        $username = $_POST['username'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($newPassword)) {
            $error = 'Datos incompletos';
        } elseif (strlen($newPassword) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas no coinciden';
        } else {
            $user = findUserByUsername($username);
            if (!$user) {
                $error = 'Usuario no encontrado';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $result = updateUserPassword($user['id'], $hashedPassword);
                if ($result['success']) {
                    $message = "Contraseña actualizada correctamente para el usuario $username";
                } else {
                    $error = $result['error'];
                }
            }
        }
    }

    // UPDATE AZURACAST CONFIG (admin)
    if ($action == 'update_azuracast_config' && isAdmin()) {
        $username = $_POST['username'] ?? '';
        $stationId = $_POST['station_id'] ?? '';
        $widgetColor = $_POST['widget_color'] ?? '#3b82f6';
        $recordingsFolder = trim($_POST['recordings_folder'] ?? '');

        if (empty($username)) {
            $error = 'Usuario no especificado';
        } else {
            if (updateAzuracastConfig($username, $stationId, $widgetColor, $recordingsFolder)) {
                $message = "Configuración de AzuraCast actualizada para $username";
            } else {
                $error = 'Error al actualizar la configuración';
            }
        }
    }

    // SYNC PROGRAMS FROM AZURACAST
    if ($action == 'sync_programs' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];
        $result = syncProgramsFromAzuracast($username);

        if ($result['success']) {
            $message = $result['message'] . " (Total: {$result['total_count']})";
        } else {
            $error = $result['message'];
        }
    }

    // CREATE PROGRAM
    if ($action == 'create_program' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];
        $programName = trim($_POST['program_name'] ?? '');

        if (empty($programName)) {
            $error = 'El nombre del programa es requerido';
        } else {
            $playlistType = trim($_POST['playlist_type'] ?? 'live');

            // Generar clave interna según el tipo (los programas live usan sufijo ::live)
            $programKey = getProgramKey($programName, $playlistType);

            // Verificar que no exista ya un programa con el mismo nombre Y tipo
            $existingProgram = getProgramInfo($username, $programKey);
            if ($existingProgram !== null) {
                $typeName = $playlistType === 'live' ? 'en directo' : 'enlatado';
                $error = "Ya existe un programa $typeName con ese nombre";
            } else {
                // ====== PROCESAR HORARIOS MÚLTIPLES (schedule_slots) ======
                // Soporte para formato nuevo (múltiples horarios) con retrocompatibilidad total
                $scheduleSlots = [];

                if (isset($_POST['schedule_slots']) && is_array($_POST['schedule_slots'])) {
                    // Formato NUEVO: schedule_slots
                    foreach ($_POST['schedule_slots'] as $slot) {
                        // Validar que el slot tenga días y hora
                        if (!empty($slot['days']) && !empty($slot['start_time'])) {
                            $scheduleSlots[] = [
                                'days' => array_map('intval', (array)$slot['days']),
                                'start_time' => trim($slot['start_time']),
                                'duration' => intval($slot['duration'] ?? 60)
                            ];
                        }
                    }
                } elseif (isset($_POST['schedule_days'])) {
                    // Formato ANTIGUO: schedule_days (retrocompatibilidad)
                    // Migrar automáticamente al formato nuevo
                    $scheduleDays = $_POST['schedule_days'] ?? [];
                    if (!empty($scheduleDays)) {
                        $scheduleSlots[] = [
                            'days' => array_map('intval', (array)$scheduleDays),
                            'start_time' => trim($_POST['schedule_start_time'] ?? ''),
                            'duration' => intval($_POST['schedule_duration'] ?? 60)
                        ];
                    }
                }

                // RETROCOMPATIBILIDAD: Guardar TAMBIÉN en formato antiguo (primer slot)
                // Esto garantiza que código antiguo siga funcionando
                $firstSlot = $scheduleSlots[0] ?? null;
                $scheduleDays = $firstSlot ? $firstSlot['days'] : [];
                $scheduleStartTime = $firstSlot ? $firstSlot['start_time'] : '';
                $scheduleDuration = $firstSlot ? $firstSlot['duration'] : 60;

                $programInfo = [
                    'original_name' => $programName,  // Guardar nombre original
                    'display_title' => trim($_POST['display_title'] ?? ''),
                    'playlist_type' => $playlistType,
                    'short_description' => trim($_POST['short_description'] ?? ''),
                    'long_description' => trim($_POST['long_description'] ?? ''),
                    'type' => trim($_POST['type'] ?? ''),
                    'url' => trim($_POST['url'] ?? ''),
                    'image' => trim($_POST['image'] ?? ''),
                    'presenters' => trim($_POST['presenters'] ?? ''),
                    'social_twitter' => trim($_POST['social_twitter'] ?? ''),
                    'social_instagram' => trim($_POST['social_instagram'] ?? ''),
                    'social_mastodon' => trim($_POST['social_mastodon'] ?? ''),
                    'social_bluesky' => trim($_POST['social_bluesky'] ?? ''),
                    'social_facebook' => trim($_POST['social_facebook'] ?? ''),
                    'rss_feed' => trim($_POST['rss_feed'] ?? ''),
                    // FORMATO NUEVO: múltiples horarios
                    'schedule_slots' => $scheduleSlots,
                    // FORMATO ANTIGUO: mantener por retrocompatibilidad
                    'schedule_days' => $scheduleDays,
                    'schedule_start_time' => $scheduleStartTime,
                    'schedule_duration' => $scheduleDuration,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                // Usar la clave interna para guardar
                if (saveProgramInfo($username, $programKey, $programInfo)) {
                    $message = "Programa \"$programName\" creado correctamente";
                    // Redirigir para que no se quede en modo creación
                    header('Location: ?page=parrilla&section=programs');
                    exit;
                } else {
                    $error = 'Error al crear el programa';
                }
            }
        }
    }

    // DELETE PROGRAM
    if ($action == 'delete_program' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];
        $programName = $_POST['program_name'] ?? '';

        if (empty($programName)) {
            $error = 'Nombre de programa no especificado';
        } else {
            // Verificar que el programa existe y es eliminable
            $programInfo = getProgramInfo($username, $programName);
            if ($programInfo === null) {
                $error = 'El programa no existe';
            } else {
                // Permitir eliminar si:
                // 1. Es un programa en directo creado manualmente (tipo 'live')
                // 2. Es un programa que ya no existe en AzuraCast (orphan_reason = 'no_en_azuracast')
                $isManualProgram = ($programInfo['playlist_type'] ?? '') === 'live';
                $isNotInAzuracast = !empty($programInfo['orphaned']) && ($programInfo['orphan_reason'] ?? '') === 'no_en_azuracast';

                if (!$isManualProgram && !$isNotInAzuracast) {
                    $error = 'Solo se pueden eliminar programas en directo o programas que ya no existen en AzuraCast';
                } else
                if (deleteProgram($username, $programName)) {
                    $message = "Programa \"$programName\" eliminado correctamente";
                    // Redirigir para limpiar la vista
                    header('Location: ?page=parrilla&section=programs');
                    exit;
                } else {
                    $error = 'Error al eliminar el programa';
                }
            }
        }
    }

    // SAVE PROGRAM INFO
    if ($action == 'save_program' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];
        $programName = $_POST['program_name'] ?? '';

        if (empty($programName)) {
            $error = 'Nombre de programa no especificado';
        } else {
            $playlistType = trim($_POST['playlist_type'] ?? 'program');

            // ====== PROCESAR HORARIOS MÚLTIPLES (schedule_slots) ======
            // Procesar horarios para TODOS los tipos de programas (como en Grillo)
            $scheduleSlots = [];

            if (isset($_POST['schedule_slots']) && is_array($_POST['schedule_slots'])) {
                // Formato NUEVO: schedule_slots
                foreach ($_POST['schedule_slots'] as $slot) {
                    if (!empty($slot['days']) && !empty($slot['start_time'])) {
                        $scheduleSlots[] = [
                            'days' => array_map('intval', (array)$slot['days']),
                            'start_time' => trim($slot['start_time']),
                            'duration' => intval($slot['duration'] ?? 60)
                        ];
                    }
                }
            } elseif (isset($_POST['schedule_days'])) {
                // Formato ANTIGUO: schedule_days (retrocompatibilidad)
                $scheduleDays = $_POST['schedule_days'] ?? [];
                if (!empty($scheduleDays)) {
                    $scheduleSlots[] = [
                        'days' => array_map('intval', (array)$scheduleDays),
                        'start_time' => trim($_POST['schedule_start_time'] ?? ''),
                        'duration' => intval($_POST['schedule_duration'] ?? 60)
                    ];
                }
            }

            // RETROCOMPATIBILIDAD: Guardar TAMBIÉN en formato antiguo (primer slot)
            $firstSlot = $scheduleSlots[0] ?? null;
            $scheduleDays = $firstSlot ? $firstSlot['days'] : [];
            $scheduleStartTime = $firstSlot ? $firstSlot['start_time'] : '';
            $scheduleDuration = $firstSlot ? $firstSlot['duration'] : 60;

            $nowHidden   = isset($_POST['hidden_from_schedule']);
            $existingInfo = getProgramInfo($username, $programName) ?? [];
            $wasHidden    = !empty($existingInfo['hidden_from_schedule']);

            $programInfo = [
                'display_title' => trim($_POST['display_title'] ?? ''),
                'playlist_type' => $playlistType,
                'short_description' => trim($_POST['short_description'] ?? ''),
                'long_description' => trim($_POST['long_description'] ?? ''),
                'type' => trim($_POST['type'] ?? ''),
                'url' => trim($_POST['url'] ?? ''),
                'image' => trim($_POST['image'] ?? ''),
                'presenters' => trim($_POST['presenters'] ?? ''),
                'social_twitter' => trim($_POST['social_twitter'] ?? ''),
                'social_instagram' => trim($_POST['social_instagram'] ?? ''),
                'social_mastodon' => trim($_POST['social_mastodon'] ?? ''),
                'social_bluesky' => trim($_POST['social_bluesky'] ?? ''),
                'social_facebook' => trim($_POST['social_facebook'] ?? ''),
                'rss_feed' => trim($_POST['rss_feed'] ?? ''),
                'hidden_from_schedule' => $nowHidden,
                // FORMATO NUEVO: múltiples horarios
                'schedule_slots' => $scheduleSlots,
                // FORMATO ANTIGUO: mantener por retrocompatibilidad
                'schedule_days' => $scheduleDays,
                'schedule_start_time' => $scheduleStartTime,
                'schedule_duration' => $scheduleDuration
            ];

            // Gestionar last_active_date según cambio de visibilidad
            if ($nowHidden && !$wasHidden) {
                // Se está ocultando ahora → registrar fecha de baja
                $programInfo['last_active_date'] = date('Y-m-d');
            } elseif (!$nowHidden && $wasHidden) {
                // Se está mostrando de nuevo → limpiar fecha de baja
                $programInfo['last_active_date'] = null;
            }

            if (saveProgramInfo($username, $programName, $programInfo)) {
                if (!empty($_POST['embed'])) {
                    // Modo embed (iframe desde seguimiento): comunicar al padre y mostrar confirmación
                    header_remove('X-Frame-Options');
                    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body style="margin:0;padding:28px 24px;font-family:sans-serif;">';
                    echo '<p style="color:#166534;font-size:15px;font-weight:600;">✓ Cambios guardados correctamente</p>';
                    echo '<script>window.parent.postMessage({type:"programSaved",program:' . json_encode($programName) . '},"*");</script>';
                    echo '</body></html>';
                    exit;
                }
                $message = "Información del programa guardada correctamente";
                // Redirigir para cerrar el formulario de edición
                header('Location: ?page=parrilla&section=programs&saved=1');
                exit;
            } else {
                $error = 'Error al guardar la información del programa';
            }
        }
    }

    // UPDATE AZURACAST CONFIG (usuario regular desde pestaña Parrilla)
    if ($action == 'update_azuracast_config_user' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];

        // Obtener valores que solo el admin puede cambiar
        $currentConfig = getAzuracastConfig($username);
        $stationId = $currentConfig['station_id'] ?? '';
        $recordingsFolder = $currentConfig['recordings_folder'] ?? '';

        // Los usuarios solo pueden actualizar personalización y stream URL
        $widgetColor = $_POST['widget_color'] ?? $_POST['widget_color_text'] ?? '#3b82f6';
        $widgetBackgroundColor = $_POST['widget_background_color'] ?? $_POST['widget_background_color_text'] ?? '#ffffff';
        $widgetStyle = $_POST['widget_style'] ?? 'modern';
        $widgetFontSize = $_POST['widget_font_size'] ?? 'medium';
        $streamUrl = $_POST['stream_url'] ?? '';

        if (updateAzuracastConfig($username, $stationId, $widgetColor, $recordingsFolder, false, '', $widgetStyle, $widgetFontSize, $streamUrl, $widgetBackgroundColor)) {
            $message = "Configuración de AzuraCast actualizada correctamente";
        } else {
            $error = 'Error al actualizar la configuración';
        }
    }

    // IMPORT SERVERLIST
    if ($action == 'import_serverlist' && isLoggedIn() && !isAdmin()) {
        if (isset($_FILES['serverlist_file']) && $_FILES['serverlist_file']['error'] == 0) {
            // Validar tamaño del archivo (máximo 1 MB)
            $maxSize = 1 * 1024 * 1024; // 1 MB
            $fileSize = $_FILES['serverlist_file']['size'];

            if ($fileSize > $maxSize) {
                $error = 'El archivo es demasiado grande. Tamaño máximo: 1 MB.';
            } elseif ($fileSize == 0) {
                $error = 'El archivo está vacío';
            } else {
                // SEGURIDAD: Validar extensión Y MIME type real
                $fileName = $_FILES['serverlist_file']['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExt !== 'txt') {
                    $error = 'Solo se permiten archivos .txt';
                } else {
                    // Validar MIME type real del archivo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['serverlist_file']['tmp_name']);
                    finfo_close($finfo);

                    // Tipos MIME permitidos para archivos de texto
                    $allowedMimeTypes = [
                        'text/plain',
                        'application/octet-stream',  // Algunos sistemas reportan esto para .txt
                        'text/x-Algol68'  // Algunos servidores reportan esto
                    ];

                    if (!in_array($mimeType, $allowedMimeTypes)) {
                        error_log("[SAPO-Security] Upload blocked - Invalid MIME type: $mimeType for file: $fileName from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                        $error = "Tipo de archivo no permitido. Detectado: $mimeType. Solo se permiten archivos de texto plano.";
                    } else {
                        // Validar contenido (debe ser texto válido UTF-8 o ASCII)
                        $fileContent = file_get_contents($_FILES['serverlist_file']['tmp_name']);

                        if (!mb_check_encoding($fileContent, 'UTF-8') && !mb_check_encoding($fileContent, 'ASCII')) {
                            error_log("[SAPO-Security] Upload blocked - Binary content detected in file: $fileName from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                            $error = 'El archivo contiene datos binarios no permitidos. Solo se permiten archivos de texto plano.';
                        } else {
                            // Todo validado, proceder a importar
                            $result = importPodcasts($_SESSION['username'], $fileContent);

                            if ($result['success']) {
                                $message = "Se importaron {$result['count']} podcasts correctamente";
                            } else {
                                $message = 'No se encontraron podcasts nuevos para importar';
                            }
                        }
                    }
                }
            }
        } else {
            $error = 'Error al cargar el archivo';
        }
    }
    
    // EXPORT SERVERLIST
    if ($action == 'export_serverlist' && isLoggedIn() && !isAdmin()) {
        $path = getServerListPath($_SESSION['username']);
        if ($path && file_exists($path)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="serverlist_' . $_SESSION['username'] . '_' . date('Y-m-d') . '.txt"');
            readfile($path);
            exit;
        } else {
            $error = 'No se encontro el archivo serverlist.txt';
        }
    }
    
    // ADD CATEGORY
    if ($action == 'add_category' && isLoggedIn()) {
        if (isAdmin()) {
            $error = 'Los administradores no pueden crear categorías. Inicia sesión con un usuario de emisora.';
        } else {
            $categoryName = trim($_POST['category_name'] ?? '');
            if (empty($categoryName)) {
                $error = 'El nombre de la categoría es obligatorio';
            } else {
                $result = saveUserCategory($_SESSION['username'], $categoryName);
                if ($result) {
                    $_SESSION['message'] = 'Categoría agregada: ' . $result;
                    header('Location: index.php');
                    exit;
                } else {
                    $_SESSION['error'] = 'La categoría ya existe';
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }

    // SYNC CATEGORIES FROM SERVERLIST
    if ($action == 'sync_categories_from_serverlist' && isLoggedIn() && !isAdmin()) {
        $imported = importCategoriesFromServerList($_SESSION['username']);
        if (!empty($imported)) {
            $_SESSION['message'] = 'Se importaron ' . count($imported) . ' categoría(s) desde serverlist.txt: ' . implode(', ', array_map('displayName', $imported));
        } else {
            $_SESSION['message'] = 'No se encontraron categorías nuevas para importar desde serverlist.txt';
        }
        header('Location: index.php');
        exit;
    }

    // DELETE CATEGORY
    if ($action == 'delete_category' && isLoggedIn()) {
        if (isAdmin()) {
            $_SESSION['error'] = 'Los administradores no pueden eliminar categorías. Inicia sesión con un usuario de emisora.';
        } else {
            // Aceptar ambos nombres de campo (category_name o category)
            $categoryName = $_POST['category_name'] ?? $_POST['category'] ?? '';

            if (empty($categoryName)) {
                $_SESSION['error'] = 'Categoría inválida';
            } else {
                // Verificar que esté vacía antes de eliminar
                $stats = getCategoryStats($_SESSION['username'], $categoryName);
                if ($stats['files'] > 0 || $stats['podcasts'] > 0) {
                    $_SESSION['error'] = 'No se puede eliminar una categoría con archivos o podcasts asignados';
                } else {
                    if (deleteUserCategory($_SESSION['username'], $categoryName)) {
                        $_SESSION['message'] = 'Categoría eliminada correctamente';
                    } else {
                        $_SESSION['error'] = 'Error al eliminar la categoría';
                    }
                }
            }
        }
        header('Location: index.php');
        exit;
    }

    // SET DEFAULT CADUCIDAD
    if ($action == 'set_default_caducidad' && isLoggedIn() && !isAdmin()) {
        $defaultCaducidad = intval($_POST['default_caducidad'] ?? 30);

        // Validar rango
        if ($defaultCaducidad < 1 || $defaultCaducidad > 365) {
            $error = 'La caducidad debe estar entre 1 y 365 días';
        } else {
            // Guardar el valor ANTERIOR antes de cambiarlo (para detectar personalizaciones)
            $oldDefaultCaducidad = getDefaultCaducidad($_SESSION['username']);

            if (setDefaultCaducidad($_SESSION['username'], $defaultCaducidad)) {
                // Sincronizar caducidades.txt pasando el valor ANTERIOR como referencia
                $syncResult = syncAllCaducidades($_SESSION['username'], $oldDefaultCaducidad);

                // Guardar mensaje en sesión para mostrarlo después del redirect
                $_SESSION['message'] = 'Caducidad por defecto actualizada a ' . $defaultCaducidad . ' días' .
                                       ($syncResult ? ' y sincronizada con todos los podcasts' : '');

                // Redirect para que se recargue la página con los valores actualizados
                header('Location: ' . basename($_SERVER['PHP_SELF']));
                exit;
            } else {
                $error = 'Error al actualizar la caducidad por defecto';
            }
        }
    }

    // ADD PODCAST
    if ($action == 'add_podcast' && isLoggedIn() && !isAdmin()) {
        $url = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $customCategory = trim($_POST['custom_category'] ?? '');
        $name = trim($_POST['name'] ?? '');

        $defaultCaducidad = getDefaultCaducidad($_SESSION['username']);
        $caducidad = intval($_POST['caducidad'] ?? $defaultCaducidad);
        $duracion = trim($_POST['duracion'] ?? '');
        $margen = intval($_POST['margen'] ?? 5);
        if (!in_array($margen, [5, 10, 15])) $margen = 5;
        $max_episodios = intval($_POST['max_episodios'] ?? 1);
        if ($max_episodios < 1 || $max_episodios > 50) $max_episodios = 1;

        // Validar caducidad
        if ($caducidad < 1 || $caducidad > 365) {
            $caducidad = $defaultCaducidad; // Valor por defecto del usuario si está fuera de rango
        }

        $finalCategory = !empty($customCategory) ? $customCategory : $category;

        if (empty($url) || empty($name)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!validateInput($url, 'url')) {
            $error = 'URL inválida';
        } elseif (strlen($name) > 100) {
            $error = 'El nombre del podcast es demasiado largo';
        } else {
            $result = addPodcast($_SESSION['username'], $url, $finalCategory, $name, $caducidad, $duracion, $margen, $max_episodios);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
        }
    }
    
    // EDIT PODCAST
    if ($action == 'edit_podcast' && isLoggedIn() && !isAdmin()) {
        $index = intval($_POST['index'] ?? -1);
        $url = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $customCategory = trim($_POST['custom_category'] ?? '');
        $name = trim($_POST['name'] ?? '');

        $defaultCaducidad = getDefaultCaducidad($_SESSION['username']);
        $caducidad = intval($_POST['caducidad'] ?? $defaultCaducidad);
        $duracion = trim($_POST['duracion'] ?? '');
        $margen = intval($_POST['margen'] ?? 5);
        if (!in_array($margen, [5, 10, 15])) $margen = 5;
        $max_episodios = intval($_POST['max_episodios'] ?? 1);
        if ($max_episodios < 1 || $max_episodios > 50) $max_episodios = 1;

        // Validar caducidad
        if ($caducidad < 1 || $caducidad > 365) {
            $caducidad = $defaultCaducidad; // Valor por defecto del usuario si está fuera de rango
        }

        $finalCategory = !empty($customCategory) ? $customCategory : $category;

        if ($index < 0) {
            $error = 'Podcast no valido';
        } elseif (empty($url) || empty($name)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!validateInput($url, 'url')) {
            $error = 'URL inválida';
        } elseif (strlen($name) > 100) {
            $error = 'El nombre del podcast es demasiado largo';
        } else {
            $result = editPodcast($_SESSION['username'], $index, $url, $finalCategory, $name, $caducidad, $duracion, $margen, $max_episodios);
            if ($result['success']) {
                // Si se cambió la categoría, mostrar recordatorio de AzuraCast
                if (!empty($result['category_changed'])) {
                    $_SESSION['show_azuracast_reminder'] = true;
                    $_SESSION['azuracast_action'] = 'move_podcast';
                }
                header('Location: ' . basename($_SERVER['PHP_SELF']));
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
    
    // DELETE PODCAST
    if ($action == 'delete_podcast' && isLoggedIn() && !isAdmin()) {
        $index = intval($_POST['index'] ?? -1);
        $result = deletePodcast($_SESSION['username'], $index);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['error'];
        }
    }

    // PAUSE PODCAST
    if ($action == 'pause_podcast' && isLoggedIn() && !isAdmin()) {
        $index = intval($_POST['index'] ?? -1);
        $result = pausePodcast($_SESSION['username'], $index);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }

    // RESUME PODCAST
    if ($action == 'resume_podcast' && isLoggedIn() && !isAdmin()) {
        $index = intval($_POST['index'] ?? -1);
        $result = resumePodcast($_SESSION['username'], $index);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }


    // RUN PODGET
    if ($action == 'run_podget' && isLoggedIn() && !isAdmin()) {
        $result = executePodget($_SESSION['username']);
        if ($result['success']) {
            $message = 'Las descargas se estan ejecutando en segundo plano';
            $_SESSION['last_podget_execution'] = time();
        } else {
            $error = $result['message'];
        }
    }
    
    // AJAX: Verificar estado del log
    if (isset($_GET['action']) && $_GET['action'] == 'check_podget_status' && isLoggedIn() && !isAdmin()) {
        // SEGURIDAD: Rate limiting (polling frecuente permitido pero limitado)
        if (!checkRateLimit('check_podget_status', 60, 60)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => ERROR_RATE_LIMIT]);
            exit;
        }

        $username = $_SESSION['username'];
        $logFile = PROJECT_DIR . '/logs/podget_' . $username . '.log';

        $status = [
            'exists' => false,
            'lastUpdate' => null,
            'message' => 'No se encontro archivo de log'
        ];

        if (file_exists($logFile)) {
            $lastModified = filemtime($logFile);
            $status['exists'] = true;
            $status['lastUpdate'] = date('H:i:s', $lastModified);
            $status['timestamp'] = $lastModified;
            $status['message'] = 'Script ejecutado a las ' . $status['lastUpdate'];
        }

        header('Content-Type: application/json');
        echo json_encode($status);
        exit;
    }

    // LOAD REPORT (AJAX)
    if ($action == 'load_report' && isLoggedIn() && !isAdmin()) {
        $days = intval($_POST['days'] ?? 7);
        if ($days < 1 || $days > 365) $days = 7;

        $report = generatePeriodReport($_SESSION['username'], $days);

        ob_start();
        if ($report) {
            include 'views/report_view.php';
        } else {
            echo '<div class="alert alert-info">No hay informes disponibles para este período. Los informes se generan automáticamente cuando ejecutas las descargas.</div>';
        }
        $html = ob_get_clean();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    }

    // REFRESH FEEDS
    if ($action == 'refresh_feeds' && isLoggedIn() && !isAdmin()) {
        $updated = refreshAllFeeds($_SESSION['username']);
        $_SESSION['feeds_updated'] = true;

        // Guardar timestamp de última actualización en la base de datos
        $username = $_SESSION['username'];
        $userData = getUserDB($username);
        $userData['last_feeds_update'] = time();
        saveUserDB($username, $userData);

        $message = "Se actualizaron $updated feeds correctamente";
    }

}

// AJAX: Leer contenido del log (GET, fuera del bloque POST)
if (isset($_GET['action']) && $_GET['action'] == 'get_podget_log' && isLoggedIn() && !isAdmin()) {
    if (!checkRateLimit('get_podget_log', 120, 60)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => ERROR_RATE_LIMIT]);
        exit;
    }

    $username = $_SESSION['username'];
    // Mismo archivo que PHP trunca y el exec redirige: captura todo el stdout del script
    $logFile = PROJECT_DIR . '/logs/podget_' . $username . '.log';
    $offset = max(0, intval($_GET['offset'] ?? 0));

    header('Content-Type: application/json');

    if (!file_exists($logFile)) {
        echo json_encode(['exists' => false, 'chunk' => '', 'offset' => 0, 'size' => 0]);
        exit;
    }

    clearstatcache(true, $logFile);
    $size = filesize($logFile);
    $chunk = '';
    if ($size > $offset) {
        $fh = fopen($logFile, 'rb');
        fseek($fh, $offset);
        $chunk = fread($fh, min($size - $offset, 65536)); // max 64 KB por poll
        fclose($fh);
    }

    echo json_encode([
        'exists' => true,
        'chunk'  => $chunk,
        'offset' => $offset + strlen($chunk),
        'size'   => $size,
    ]);
    exit;
}

// ========== ACCIONES POST PARA GESTOR DE CATEGORÍAS (con protección CSRF) ==========

// Todas estas acciones ahora usan POST con CSRF token para prevenir ataques CSRF
// Los tokens se validan automáticamente en el bloque POST principal (líneas 28-34)

// RENAME CATEGORY (POST)
if ($action == 'rename_category' && isLoggedIn() && !isAdmin()) {
    $oldName = $_POST['old_name'] ?? '';
    $newName = $_POST['new_name'] ?? '';

    if (empty($oldName) || empty($newName)) {
        $_SESSION['error'] = 'Nombres de categoría inválidos';
    } else {
        $result = renameCategory($_SESSION['username'], $oldName, $newName);
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['show_azuracast_reminder'] = true;
            $_SESSION['azuracast_action'] = 'rename';
            $_SESSION['azuracast_old_name'] = $oldName;
            $_SESSION['azuracast_new_name'] = $result['new_name'];
        } else {
            $_SESSION['error'] = $result['error'];
        }
    }
    header('Location: index.php');
    exit;
}

// GET CATEGORY FILES (AJAX)
if (isset($_GET['action']) && $_GET['action'] == 'get_category_files' && isLoggedIn() && !isAdmin()) {
    // SEGURIDAD: Rate limiting (operación costosa - lectura de disco)
    // Límite: 10 peticiones por minuto
    if (!checkRateLimit('get_category_files', 10, 60)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => ERROR_RATE_LIMIT]);
        exit;
    }

    $categoryName = $_GET['category'] ?? '';

    if (empty($categoryName)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Categoría inválida']);
        exit;
    }

    $config = getConfig();
    $basePath = $config['base_path'];
    $categoryPath = $basePath . DIRECTORY_SEPARATOR . $_SESSION['username'] .
                    DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR .
                    'Podcast' . DIRECTORY_SEPARATOR . $categoryName;

    if (!is_dir($categoryPath)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Carpeta no encontrada']);
        exit;
    }

    $files = glob($categoryPath . DIRECTORY_SEPARATOR . '*.{mp3,ogg,wav,m4a}', GLOB_BRACE);

    if ($files === false) {
        $files = [];
    }

    $fileList = [];
    foreach ($files as $file) {
        if (is_file($file)) {
            $fileList[] = [
                'name' => basename($file),
                'size' => formatBytes(filesize($file)),
                'date' => date('d/m/Y H:i', filemtime($file))
            ];
        }
    }

    // Ordenar por fecha, más reciente primero
    usort($fileList, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'files' => $fileList]);
    exit;
}

// ========== FIN ACCIONES GESTOR DE CATEGORÍAS ==========

// ── AJAX: actualizar duración de un slot de programa desde seguimiento ────────
if (isset($_POST['action']) && $_POST['action'] === 'update_slot_duration' && isLoggedIn() && !isAdmin()) {
    header('Content-Type: application/json');

    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        echo json_encode(['success' => false, 'error' => ERROR_INVALID_TOKEN]);
        exit;
    }

    $username    = $_SESSION['username'];
    $programName = trim($_POST['program_name'] ?? '');
    $day         = (int)($_POST['day'] ?? -1);
    $startTime   = trim($_POST['start_time'] ?? '');
    $duration    = (int)($_POST['duration'] ?? 0);

    if ($programName === '' || $duration < 1 || $duration > 720) {
        echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }

    $info = getProgramInfo($username, $programName);
    if ($info === null) {
        // Programa existe en AzuraCast pero aún no está en la BD de SAPO → crear entrada mínima
        $info = [];
    }

    // Construir array de slots (migrando formato legacy si es necesario)
    $slots = $info['schedule_slots'] ?? [];
    if (empty($slots) && !empty($info['schedule_days'])) {
        $slots = [[
            'days'       => (array)($info['schedule_days'] ?? []),
            'start_time' => $info['schedule_start_time'] ?? '',
            'duration'   => (int)($info['schedule_duration'] ?? 60),
        ]];
    }

    // Buscar slot coincidente: mismo start_time Y mismo día de semana
    $found = false;
    foreach ($slots as &$slot) {
        $slotDays = array_map('intval', (array)($slot['days'] ?? []));
        if ($startTime !== '' && ($slot['start_time'] ?? '') === $startTime && in_array($day, $slotDays, true)) {
            $slot['duration'] = $duration;
            $found = true;
            break;
        }
    }
    unset($slot);

    // Fallback: coincidencia solo por start_time
    if (!$found && $startTime !== '') {
        foreach ($slots as &$slot) {
            if (($slot['start_time'] ?? '') === $startTime) {
                $slot['duration'] = $duration;
                $found = true;
                break;
            }
        }
        unset($slot);
    }

    // Fallback: si hay un único slot, actualizar; si hay varios, añadir uno nuevo
    if (!$found) {
        if (count($slots) === 1) {
            $slots[0]['duration'] = $duration;
        } else {
            $slots[] = ['days' => $day >= 0 ? [$day] : [], 'start_time' => $startTime, 'duration' => $duration];
        }
    }

    // Actualizar también campos legacy (primer slot)
    $first = $slots[0] ?? null;
    $saveData = [
        'schedule_slots'      => $slots,
        'schedule_days'       => $first ? ($first['days'] ?? []) : ($info['schedule_days'] ?? []),
        'schedule_start_time' => $first ? ($first['start_time'] ?? '') : ($info['schedule_start_time'] ?? ''),
        'schedule_duration'   => $first ? (int)($first['duration'] ?? 60) : $duration,
    ];

    if (saveProgramInfo($username, $programName, $saveData)) {
        echo json_encode(['success' => true, 'duration' => $duration]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar']);
    }
    exit;
}

// ── Embed: edición de ficha de programa en iframe (desde seguimiento) ─────────
// Se intercepta antes de cargar el layout para devolver una página mínima.
if (isset($_GET['page']) && $_GET['page'] === 'program_edit_embed') {
    if (!isLoggedIn() || isAdmin()) {
        http_response_code(403); die('No autorizado');
    }
    $username = $_SESSION['username'];
    $progKey  = trim($_GET['program'] ?? '');
    if ($progKey === '') {
        http_response_code(400); die('Programa no especificado');
    }
    $programInfo    = getProgramInfo($username, $progKey);
    $editingProgram = $progKey;
    $isEmbed        = true;
    // Quitar X-Frame-Options para que el iframe desde la misma app funcione
    header_remove('X-Frame-Options');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar: <?php echo htmlEsc(getProgramNameFromKey($progKey)); ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { margin: 0; padding: 20px 24px 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fff; }
        h2   { margin: 0 0 20px; font-size: 17px; color: #1e40af; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
    </style>
</head>
<body>
    <?php if ($programInfo === null): ?>
    <div class="alert alert-error">Programa no encontrado: <?php echo htmlEsc($progKey); ?></div>
    <?php else: ?>
    <h2>✏️ Editar: <?php echo htmlEsc(getProgramNameFromKey($progKey)); ?><?php if (str_ends_with($progKey, '::live')): ?> <span style="font-size:12px; background:#dcfce7; color:#166534; padding:2px 7px; border-radius:4px; font-weight:600;">DIRECTO</span><?php endif; ?></h2>
    <?php include INCLUDES_DIR . '/program_edit_form.php'; ?>
    <?php endif; ?>
</body>
</html>
    <?php
    exit;
}

// Cargar vista
require_once 'views/layout.php';
?>
