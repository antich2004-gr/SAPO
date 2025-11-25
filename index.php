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

                    // Verificar si han pasado más de 8 horas desde la última actualización de feeds
                    $redirect_url = basename($_SERVER['PHP_SELF']);

                    // Solo verificar para usuarios no-admin
                    if (!isAdmin()) {
                        $userData = getUserDB($username);
                        $lastUpdate = $userData['last_feeds_update'] ?? 0;

                        if ($lastUpdate > 0) {
                            $hours_passed = (time() - $lastUpdate) / 3600;
                            if ($hours_passed >= 8) {
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

    // SAVE CONFIG (admin)
    if ($action == 'save_config' && isAdmin()) {
        $basePath = trim($_POST['base_path'] ?? '');
        $subsFolder = trim($_POST['subscriptions_folder'] ?? 'Suscripciones');
        $azuracastApiUrl = trim($_POST['azuracast_api_url'] ?? '');
        $azuracastApiKey = trim($_POST['azuracast_api_key'] ?? '');

        if (empty($basePath)) {
            $error = 'La ruta base es obligatoria';
        } elseif (!is_dir($basePath)) {
            $error = 'La ruta base no existe o no es accesible';
        } else {
            if (saveConfig($basePath, $subsFolder, $azuracastApiUrl, $azuracastApiKey)) {
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

        if (empty($username)) {
            $error = 'Usuario no especificado';
        } else {
            if (updateAzuracastConfig($username, $stationId, $widgetColor)) {
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
            // Verificar que el programa no exista ya
            $existingProgram = getProgramInfo($username, $programName);
            if ($existingProgram !== null) {
                $error = 'Ya existe un programa con ese nombre';
            } else {
                // Procesar días de emisión
                $scheduleDays = $_POST['schedule_days'] ?? [];
                if (!is_array($scheduleDays)) {
                    $scheduleDays = [];
                }

                $programInfo = [
                    'display_title' => trim($_POST['display_title'] ?? ''),
                    'playlist_type' => trim($_POST['playlist_type'] ?? 'live'),
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
                    'rss_feed' => trim($_POST['rss_feed'] ?? ''),
                    'schedule_days' => $scheduleDays,
                    'schedule_start_time' => trim($_POST['schedule_start_time'] ?? ''),
                    'schedule_duration' => (int)($_POST['schedule_duration'] ?? 60),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                if (saveProgramInfo($username, $programName, $programInfo)) {
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
                'rss_feed' => trim($_POST['rss_feed'] ?? ''),
                'hidden_from_schedule' => isset($_POST['hidden_from_schedule']) ? true : false,
                'schedule_duration' => (int)($_POST['schedule_duration'] ?? 60)
            ];

            // Solo guardar campos de horario de días/hora si es programa en directo
            if ($playlistType === 'live') {
                $scheduleDays = $_POST['schedule_days'] ?? [];
                if (!is_array($scheduleDays)) {
                    $scheduleDays = [];
                }

                $programInfo['schedule_days'] = $scheduleDays;
                $programInfo['schedule_start_time'] = trim($_POST['schedule_start_time'] ?? '');
            }

            if (saveProgramInfo($username, $programName, $programInfo)) {
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

        // Obtener el Station ID actual (solo el admin puede cambiarlo)
        $currentConfig = getAzuracastConfig($username);
        $stationId = $currentConfig['station_id'] ?? '';

        // Los usuarios solo pueden actualizar personalización y stream URL
        $widgetColor = $_POST['widget_color'] ?? $_POST['widget_color_text'] ?? '#3b82f6';
        $widgetStyle = $_POST['widget_style'] ?? 'modern';
        $widgetFontSize = $_POST['widget_font_size'] ?? 'medium';
        $streamUrl = $_POST['stream_url'] ?? '';

        if (updateAzuracastConfig($username, $stationId, $widgetColor, false, '', $widgetStyle, $widgetFontSize, $streamUrl)) {
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
                    $message = 'Categoría agregada: ' . $result;
                } else {
                    $error = 'La categoría ya existe';
                }
            }
        }
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

        // Validar caducidad
        if ($caducidad < 1 || $caducidad > 365) {
            $caducidad = $defaultCaducidad; // Valor por defecto del usuario si está fuera de rango
        }

        $finalCategory = !empty($customCategory) ? $customCategory : $category;

        if (empty($url) || empty($name)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!validateInput($url, 'url')) {
            $error = 'URL de RSS invalida';
        } elseif (strlen($name) > 100) {
            $error = 'El nombre del podcast es demasiado largo';
        } else {
            $result = addPodcast($_SESSION['username'], $url, $finalCategory, $name, $caducidad, $duracion);
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
            $error = 'URL de RSS invalida';
        } elseif (strlen($name) > 100) {
            $error = 'El nombre del podcast es demasiado largo';
        } else {
            $result = editPodcast($_SESSION['username'], $index, $url, $finalCategory, $name, $caducidad, $duracion);
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
        $logFile = '/var/log/sapo/podget_' . $username . '.log';

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

// Cargar vista
require_once 'views/layout.php';
?>
