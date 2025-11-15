<?php
// includes/database.php - Operaciones de base de datos

// Nuevas constantes para archivos separados
define('DB_DIR', dirname(DB_FILE) . '/db');
define('GLOBAL_DB_FILE', DB_DIR . '/global.json');
define('FEED_CACHE_FILE', DB_DIR . '/feed_cache.json');
define('USERS_DIR', DB_DIR . '/users');

// ==========================================
// SISTEMA NUEVO: ARCHIVOS SEPARADOS
// ==========================================

/**
 * Inicializar estructura de directorios
 */
function initDBStructure() {
    if (!is_dir(DB_DIR)) {
        mkdir(DB_DIR, 0755, true);
    }
    if (!is_dir(USERS_DIR)) {
        mkdir(USERS_DIR, 0755, true);
    }
}

/**
 * Obtener base de datos global (usuarios, config, login_attempts)
 */
function getGlobalDB() {
    initDBStructure();

    if (!file_exists(GLOBAL_DB_FILE)) {
        $initialData = [
            'users' => [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'password' => password_hash('admin123', PASSWORD_BCRYPT),
                    'station_name' => 'Administrador',
                    'is_admin' => true
                ]
            ],
            'config' => [
                'base_path' => '',
                'subscriptions_folder' => 'Suscripciones',
                'cache_duration' => 43200
            ],
            'login_attempts' => []
        ];
        file_put_contents(GLOBAL_DB_FILE, json_encode($initialData, JSON_PRETTY_PRINT));
        chmod(GLOBAL_DB_FILE, 0640);
    }

    return json_decode(file_get_contents(GLOBAL_DB_FILE), true);
}

/**
 * Guardar base de datos global
 */
function saveGlobalDB($data) {
    initDBStructure();

    // Usar flock para prevenir race conditions
    $fp = fopen(GLOBAL_DB_FILE, 'c');
    if (!$fp) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } else {
        fclose($fp);
        return false;
    }
}

/**
 * Obtener datos de usuario específico (categorías)
 */
function getUserDB($username) {
    initDBStructure();

    $userFile = USERS_DIR . '/' . $username . '.json';

    if (!file_exists($userFile)) {
        $initialData = [
            'categories' => [],
            'last_feeds_update' => 0  // Timestamp de última actualización de feeds
        ];
        file_put_contents($userFile, json_encode($initialData, JSON_PRETTY_PRINT));
        chmod($userFile, 0640);
    }

    return json_decode(file_get_contents($userFile), true);
}

/**
 * Guardar datos de usuario específico
 */
function saveUserDB($username, $data) {
    initDBStructure();

    $userFile = USERS_DIR . '/' . $username . '.json';

    // Usar flock para prevenir race conditions
    $fp = fopen($userFile, 'c');
    if (!$fp) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } else {
        fclose($fp);
        return false;
    }
}

/**
 * Obtener cache de feeds
 */
function getFeedCacheDB() {
    initDBStructure();

    if (!file_exists(FEED_CACHE_FILE)) {
        file_put_contents(FEED_CACHE_FILE, json_encode([], JSON_PRETTY_PRINT));
        chmod(FEED_CACHE_FILE, 0640);
    }

    return json_decode(file_get_contents(FEED_CACHE_FILE), true);
}

/**
 * Guardar cache de feeds
 */
function saveFeedCacheDB($data) {
    initDBStructure();

    // Usar flock para prevenir race conditions
    $fp = fopen(FEED_CACHE_FILE, 'c');
    if (!$fp) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } else {
        fclose($fp);
        return false;
    }
}

// ==========================================
// SISTEMA ANTIGUO (para compatibilidad temporal)
// ==========================================

function getDB() {
    if (!file_exists(DB_FILE)) {
        $initialData = [
            'users' => [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'password' => password_hash('admin123', PASSWORD_BCRYPT),
                    'station_name' => 'Administrador',
                    'is_admin' => true
                ]
            ],
            'podcasts' => [],
            'user_categories' => [],
            'login_attempts' => [],
            'feed_cache' => [],
            'config' => [
                'base_path' => '',
                'subscriptions_folder' => 'Suscripciones',
                'cache_duration' => 43200
            ]
        ];
        file_put_contents(DB_FILE, json_encode($initialData, JSON_PRETTY_PRINT));
        chmod(DB_FILE, 0600);
    }
    return json_decode(file_get_contents(DB_FILE), true);
}

function saveDB($data) {
    return file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

function getConfig() {
    $db = getGlobalDB();
    return $db['config'] ?? [
        'base_path' => '',
        'subscriptions_folder' => 'Suscripciones',
        'cache_duration' => 43200
    ];
}

function saveConfig($basePath, $subscriptionsFolder) {
    $db = getGlobalDB();
    $db['config'] = [
        'base_path' => rtrim($basePath, '/\\'),
        'subscriptions_folder' => trim($subscriptionsFolder, '/\\'),
        'cache_duration' => $db['config']['cache_duration'] ?? 43200
    ];
    return saveGlobalDB($db);
}

function findUserByUsername($username) {
    $db = getGlobalDB();
    foreach ($db['users'] as $user) {
        if ($user['username'] == $username) {
            return $user;
        }
    }
    return null;
}

function findUserById($id) {
    $db = getGlobalDB();
    foreach ($db['users'] as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

function createUser($username, $password, $station_name) {
    $db = getGlobalDB();

    $newId = max(array_column($db['users'], 'id')) + 1;
    $db['users'][] = [
        'id' => $newId,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'station_name' => $station_name,
        'is_admin' => false
    ];

    saveGlobalDB($db);

    // Crear archivo de usuario vacío
    $userData = ['categories' => []];
    saveUserDB($username, $userData);

    return $newId;
}

function deleteUser($userId) {
    if ($userId == 1) {
        return false;
    }

    $db = getGlobalDB();

    // Obtener username antes de eliminar
    $username = null;
    foreach ($db['users'] as $user) {
        if ($user['id'] == $userId) {
            $username = $user['username'];
            break;
        }
    }

    $db['users'] = array_filter($db['users'], function($user) use ($userId) {
        return $user['id'] != $userId;
    });
    $db['users'] = array_values($db['users']);

    $result = saveGlobalDB($db);

    // Eliminar archivo de usuario
    if ($username && $result) {
        $userFile = USERS_DIR . '/' . $username . '.json';
        if (file_exists($userFile)) {
            unlink($userFile);
        }
    }

    return $result;
}

function updateUser($userId, $username, $password, $station_name) {
    if ($userId == 1) {
        return false; // No permitir editar el admin principal
    }

    $db = getGlobalDB();

    $oldUsername = null;
    foreach ($db['users'] as &$user) {
        if ($user['id'] == $userId) {
            // Verificar si el nuevo username ya existe (en otro usuario)
            foreach ($db['users'] as $otherUser) {
                if ($otherUser['id'] != $userId && $otherUser['username'] == $username) {
                    return false; // Username ya existe
                }
            }

            $oldUsername = $user['username'];
            $user['username'] = $username;
            if (!empty($password)) {
                $user['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $user['station_name'] = $station_name;

            $result = saveGlobalDB($db);

            // Si cambió el username, renombrar archivo de usuario
            if ($result && $oldUsername !== $username) {
                $oldFile = USERS_DIR . '/' . $oldUsername . '.json';
                $newFile = USERS_DIR . '/' . $username . '.json';
                if (file_exists($oldFile)) {
                    rename($oldFile, $newFile);
                }
            }

            return $result;
        }
    }

    return false;
}

function getAllUsers() {
    $db = getGlobalDB();
    return $db['users'];
}

function getCacheEntry($key) {
    $cache = getFeedCacheDB();
    return $cache[$key] ?? null;
}

function setCacheEntry($key, $data) {
    $cache = getFeedCacheDB();
    $cache[$key] = $data;
    return saveFeedCacheDB($cache);
}

?>
