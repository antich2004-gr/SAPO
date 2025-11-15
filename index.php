<?php
// index.php - Punto de entrada principal SAPO

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
    // AJAX: Guardar timestamp de última actualización de feeds
    if (isset($_GET['action']) && $_GET['action'] == 'save_feeds_timestamp' && isLoggedIn() && !isAdmin()) {
        header('Content-Type: application/json');

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
        
        $username = $_SESSION['username'];
        $podcasts = readServerList($username);
        
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

        if (empty($basePath)) {
            $error = 'La ruta base es obligatoria';
        } elseif (!is_dir($basePath)) {
            $error = 'La ruta base no existe o no es accesible';
        } else {
            if (saveConfig($basePath, $subsFolder, $azuracastApiUrl)) {
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
        if (deleteUser($userId)) {
            $message = 'Usuario eliminado correctamente';
        } else {
            $error = 'No se puede eliminar el usuario administrador principal';
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
                if (updateUserPassword($user['id'], $hashedPassword)) {
                    $message = "Contraseña actualizada correctamente para el usuario $username";
                } else {
                    $error = 'Error al actualizar la contraseña';
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
                    'playlist_type' => trim($_POST['playlist_type'] ?? 'live'),
                    'short_description' => trim($_POST['short_description'] ?? ''),
                    'long_description' => trim($_POST['long_description'] ?? ''),
                    'type' => trim($_POST['type'] ?? ''),
                    'url' => trim($_POST['url'] ?? ''),
                    'image' => trim($_POST['image'] ?? ''),
                    'presenters' => trim($_POST['presenters'] ?? ''),
                    'social_twitter' => trim($_POST['social_twitter'] ?? ''),
                    'social_instagram' => trim($_POST['social_instagram'] ?? ''),
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

    // SAVE PROGRAM INFO
    if ($action == 'save_program' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];
        $programName = $_POST['program_name'] ?? '';

        if (empty($programName)) {
            $error = 'Nombre de programa no especificado';
        } else {
            // Procesar días de emisión
            $scheduleDays = $_POST['schedule_days'] ?? [];
            if (!is_array($scheduleDays)) {
                $scheduleDays = [];
            }

            $programInfo = [
                'playlist_type' => trim($_POST['playlist_type'] ?? 'program'),
                'short_description' => trim($_POST['short_description'] ?? ''),
                'long_description' => trim($_POST['long_description'] ?? ''),
                'type' => trim($_POST['type'] ?? ''),
                'url' => trim($_POST['url'] ?? ''),
                'image' => trim($_POST['image'] ?? ''),
                'presenters' => trim($_POST['presenters'] ?? ''),
                'social_twitter' => trim($_POST['social_twitter'] ?? ''),
                'social_instagram' => trim($_POST['social_instagram'] ?? ''),
                'schedule_days' => $scheduleDays,
                'schedule_start_time' => trim($_POST['schedule_start_time'] ?? ''),
                'schedule_duration' => (int)($_POST['schedule_duration'] ?? 60)
            ];

            if (saveProgramInfo($username, $programName, $programInfo)) {
                $message = "Información del programa guardada correctamente";
            } else {
                $error = 'Error al guardar la información del programa';
            }
        }
    }

    // UPDATE AZURACAST CONFIG (usuario regular desde pestaña Parrilla)
    if ($action == 'update_azuracast_config_user' && isLoggedIn() && !isAdmin()) {
        $username = $_SESSION['username'];
        $stationId = $_POST['station_id'] ?? '';
        $widgetColor = $_POST['widget_color'] ?? $_POST['widget_color_text'] ?? '#3b82f6';

        if (updateAzuracastConfig($username, $stationId, $widgetColor)) {
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
                // Validar que sea un archivo de texto
                $fileName = $_FILES['serverlist_file']['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExt !== 'txt') {
                    $error = 'Solo se permiten archivos .txt';
                } else {
                    // Todo validado, proceder a leer
                    $fileContent = file_get_contents($_FILES['serverlist_file']['tmp_name']);
                    $result = importPodcasts($_SESSION['username'], $fileContent);

                    if ($result['success']) {
                        $message = "Se importaron {$result['count']} podcasts correctamente";
                    } else {
                        $message = 'No se encontraron podcasts nuevos para importar';
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
            $error = 'Los administradores no pueden eliminar categorías. Inicia sesión con un usuario de emisora.';
        } else {
            $categoryName = $_POST['category_name'] ?? '';
            if (deleteUserCategory($_SESSION['username'], $categoryName)) {
                $message = 'Categoría eliminada correctamente';
            } else {
                $error = 'Error al eliminar la categoría. Puede estar en uso por un podcast.';
            }
        }
    }
    
    // ADD PODCAST
    if ($action == 'add_podcast' && isLoggedIn() && !isAdmin()) {
        $url = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $customCategory = trim($_POST['custom_category'] ?? '');
        $name = trim($_POST['name'] ?? '');
        
        $caducidad = intval($_POST['caducidad'] ?? 30);
        $duracion = trim($_POST['duracion'] ?? '');

        // Validar caducidad
        if ($caducidad < 1 || $caducidad > 365) {
            $caducidad = 30; // Valor por defecto si está fuera de rango
        }

        $finalCategory = !empty($customCategory) ? $customCategory : $category;
        
        if (empty($url) || empty($finalCategory) || empty($name)) {
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
        $caducidad = intval($_POST['caducidad'] ?? 30);
        $duracion = trim($_POST['duracion'] ?? '');

        // Validar caducidad
        if ($caducidad < 1 || $caducidad > 365) {
            $caducidad = 30; // Valor por defecto si está fuera de rango
        }

        
        $finalCategory = !empty($customCategory) ? $customCategory : $category;
        
        if ($index < 0) {
            $error = 'Podcast no valido';
        } elseif (empty($url) || empty($finalCategory) || empty($name)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!validateInput($url, 'url')) {
            $error = 'URL de RSS invalida';
        } elseif (strlen($name) > 100) {
            $error = 'El nombre del podcast es demasiado largo';
        } else {
            $result = editPodcast($_SESSION['username'], $index, $url, $finalCategory, $name, $caducidad, $duracion);
            if ($result['success']) {
                // Si se cambió la categoría, mostrar recordatorio de Radiobot
                if (!empty($result['category_changed'])) {
                    $_SESSION['show_radiobot_reminder'] = true;
                    $_SESSION['radiobot_action'] = 'move_podcast';
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
            $_SESSION['show_radiobot_reminder'] = true;
            $_SESSION['radiobot_action'] = 'rename';
            $_SESSION['radiobot_old_name'] = $oldName;
            $_SESSION['radiobot_new_name'] = $result['new_name'];
        } else {
            $_SESSION['error'] = $result['error'];
        }
    }
    header('Location: index.php');
    exit;
}

// DELETE CATEGORY (POST) - para categorías vacías
if ($action == 'delete_category' && isLoggedIn() && !isAdmin()) {
    $categoryName = $_POST['category'] ?? '';

    if (empty($categoryName)) {
        $_SESSION['error'] = 'Categoría inválida';
    } else {
        // Verificar que esté vacía
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
    header('Location: index.php');
    exit;
}

// GET CATEGORY FILES (AJAX)
if (isset($_GET['action']) && $_GET['action'] == 'get_category_files' && isLoggedIn() && !isAdmin()) {
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
