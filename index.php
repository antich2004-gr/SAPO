<?php
// index.php - Punto de entrada principal SAPO

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once 'config.php';
require_once INCLUDES_DIR . '/session.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/auth.php';
require_once INCLUDES_DIR . '/utils.php';
require_once INCLUDES_DIR . '/categories.php';
require_once INCLUDES_DIR . '/podcasts.php';
require_once INCLUDES_DIR . '/feed.php';
require_once INCLUDES_DIR . '/reports.php';

initSession();

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

                header('Location: ' . basename($_SERVER['PHP_SELF']));
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
        
        if (empty($basePath)) {
            $error = 'La ruta base es obligatoria';
        } elseif (!is_dir($basePath)) {
            $error = 'La ruta base no existe o no es accesible';
        } else {
            if (saveConfig($basePath, $subsFolder)) {
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
