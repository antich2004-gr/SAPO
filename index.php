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
            $error = 'Nombre de usuario invalido';
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
            $error = 'La contrasena debe tener al menos 8 caracteres';
        } elseif (!validateInput($username, 'username')) {
            $error = 'Nombre de usuario invalido';
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
            $fileContent = file_get_contents($_FILES['serverlist_file']['tmp_name']);
            $result = importPodcasts($_SESSION['username'], $fileContent);
            
            if ($result['success']) {
                $message = "Se importaron {$result['count']} podcasts correctamente";
            } else {
                $message = 'No se encontraron podcasts nuevos para importar';
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
            $error = 'Los administradores no pueden crear categorias. Inicia sesion con un usuario de emisora.';
        } else {
            $categoryName = trim($_POST['category_name'] ?? '');
            if (empty($categoryName)) {
                $error = 'El nombre de la categoria es obligatorio';
            } else {
                $result = saveUserCategory($_SESSION['username'], $categoryName);
                if ($result) {
                    $message = 'Categoria agregada: ' . $result;
                } else {
                    $error = 'La categoria ya existe';
                }
            }
        }
    }
    
    // DELETE CATEGORY
    if ($action == 'delete_category' && isLoggedIn()) {
        if (isAdmin()) {
            $error = 'Los administradores no pueden eliminar categorias. Inicia sesion con un usuario de emisora.';
        } else {
            $categoryName = $_POST['category_name'] ?? '';
            if (deleteUserCategory($_SESSION['username'], $categoryName)) {
                $message = 'Categoria eliminada correctamente';
            } else {
                $error = 'Error al eliminar la categoria. Puede estar en uso por un podcast.';
            }
        }
    }
    
    // ADD PODCAST
    if ($action == 'add_podcast' && isLoggedIn() && !isAdmin()) {
        $url = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $customCategory = trim($_POST['custom_category'] ?? '');
        $name = trim($_POST['name'] ?? '');
        
        $finalCategory = !empty($customCategory) ? $customCategory : $category;
        
        if (empty($url) || empty($finalCategory) || empty($name)) {
            $error = 'Todos los campos son obligatorios';
        } elseif (!validateInput($url, 'url')) {
            $error = 'URL de RSS invalida';
        } elseif (strlen($name) > 100) {
            $error = 'El nombre del podcast es demasiado largo';
        } else {
            $result = addPodcast($_SESSION['username'], $url, $finalCategory, $name);
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
            $result = editPodcast($_SESSION['username'], $index, $url, $finalCategory, $name);
            if ($result['success']) {
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
    
    // REFRESH FEEDS
    if ($action == 'refresh_feeds' && isLoggedIn() && !isAdmin()) {
        $updated = refreshAllFeeds($_SESSION['username']);
        $_SESSION['feeds_updated'] = true;
        $message = "Se actualizaron $updated feeds correctamente";
    }
}

// Cargar vista
require_once 'views/layout.php';
?>
