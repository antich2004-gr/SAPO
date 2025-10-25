<?php
// includes/database.php - Operaciones de base de datos

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
    $db = getDB();
    return $db['config'] ?? [
        'base_path' => '',
        'subscriptions_folder' => 'Suscripciones',
        'cache_duration' => 43200
    ];
}

function saveConfig($basePath, $subscriptionsFolder) {
    $db = getDB();
    $db['config'] = [
        'base_path' => rtrim($basePath, '/\\'),
        'subscriptions_folder' => trim($subscriptionsFolder, '/\\'),
        'cache_duration' => $db['config']['cache_duration'] ?? 43200
    ];
    return saveDB($db);
}

function findUserByUsername($username) {
    $db = getDB();
    foreach ($db['users'] as $user) {
        if ($user['username'] == $username) {
            return $user;
        }
    }
    return null;
}

function findUserById($id) {
    $db = getDB();
    foreach ($db['users'] as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

function createUser($username, $password, $station_name) {
    $db = getDB();
    
    $newId = max(array_column($db['users'], 'id')) + 1;
    $db['users'][] = [
        'id' => $newId,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'station_name' => $station_name,
        'is_admin' => false
    ];
    
    saveDB($db);
    return $newId;
}

function deleteUser($userId) {
    if ($userId == 1) {
        return false;
    }
    
    $db = getDB();
    $db['users'] = array_filter($db['users'], function($user) use ($userId) {
        return $user['id'] != $userId;
    });
    $db['users'] = array_values($db['users']);
    
    return saveDB($db);
}

function updateUser($userId, $username, $password, $station_name) {
    if ($userId == 1) {
        return false; // No permitir editar el admin principal
    }
    
    $db = getDB();
    
    foreach ($db['users'] as &$user) {
        if ($user['id'] == $userId) {
            // Verificar si el nuevo username ya existe (en otro usuario)
            foreach ($db['users'] as $otherUser) {
                if ($otherUser['id'] != $userId && $otherUser['username'] == $username) {
                    return false; // Username ya existe
                }
            }
            
            $user['username'] = $username;
            if (!empty($password)) {
                $user['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $user['station_name'] = $station_name;
            
            return saveDB($db);
        }
    }
    
    return false;
}

function getAllUsers() {
    $db = getDB();
    return $db['users'];
}

function getCacheEntry($key) {
    $db = getDB();
    return $db['feed_cache'][$key] ?? null;
}

function setCacheEntry($key, $data) {
    $db = getDB();
    if (!isset($db['feed_cache'])) {
        $db['feed_cache'] = [];
    }
    $db['feed_cache'][$key] = $data;
    return saveDB($db);
}

?>
