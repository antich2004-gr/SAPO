<?php
/**
 * migrate_db.php — Migración de db.json (formato antiguo) a db/global.json (formato nuevo)
 *
 * Ejecutar una sola vez desde línea de comandos:
 *   php migrate_db.php
 *
 * BORRA ESTE ARCHIVO DESPUÉS DE EJECUTARLO.
 */

// Solo CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Este script solo puede ejecutarse desde línea de comandos.\n");
}

define('PROJECT_DIR', __DIR__);
define('OLD_DB_FILE', PROJECT_DIR . '/db.json');
define('NEW_DB_DIR',  PROJECT_DIR . '/db');
define('NEW_GLOBAL',  NEW_DB_DIR . '/global.json');
define('NEW_USERS',   NEW_DB_DIR . '/users');

echo "=== SAPO DB Migration ===\n\n";

// 1. Verificar que db.json existe
if (!file_exists(OLD_DB_FILE)) {
    die("ERROR: No se encuentra db.json en " . OLD_DB_FILE . "\n");
}

$old = json_decode(file_get_contents(OLD_DB_FILE), true);
if (!$old || !isset($old['users'])) {
    die("ERROR: db.json tiene formato inválido o está corrupto.\n");
}

echo "Encontrados " . count($old['users']) . " usuarios en db.json:\n";
foreach ($old['users'] as $u) {
    echo "  - {$u['username']}" . ($u['is_admin'] ? ' [admin]' : '') . "\n";
}
echo "\n";

// 2. Crear directorios si no existen
if (!is_dir(NEW_DB_DIR)) {
    mkdir(NEW_DB_DIR, 0755, true);
    echo "Creado directorio: db/\n";
}
if (!is_dir(NEW_USERS)) {
    mkdir(NEW_USERS, 0755, true);
    echo "Creado directorio: db/users/\n";
}

// 3. Leer global.json actual (si existe) para no perder login_attempts
$newGlobal = ['users' => [], 'config' => [], 'login_attempts' => []];
if (file_exists(NEW_GLOBAL)) {
    $existing = json_decode(file_get_contents(NEW_GLOBAL), true);
    if ($existing) {
        $newGlobal = $existing;
        echo "db/global.json existente detectado (se sobreescribirán los usuarios).\n";
    }
}

// 4. Migrar usuarios
$newGlobal['users'] = $old['users'];

// 5. Migrar config
$oldConfig = $old['config'] ?? [];
$newGlobal['config'] = array_merge([
    'base_path'             => '',
    'subscriptions_folder'  => 'Suscripciones',
    'podcasts_folder'       => 'Podcasts',
    'cache_duration'        => 86400,
    'azuracast_api_url'     => '',
    'azuracast_api_key'     => '',
    'recordings_mount_base' => ''
], $oldConfig);

// 6. Guardar db/global.json
file_put_contents(NEW_GLOBAL, json_encode($newGlobal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
chmod(NEW_GLOBAL, 0600);
echo "db/global.json guardado con " . count($newGlobal['users']) . " usuarios.\n";

// 7. Migrar categorías de usuario a db/users/{username}.json
$oldCategories = $old['user_categories'] ?? [];
foreach ($old['users'] as $user) {
    if ($user['is_admin']) continue; // admin no tiene fichero de usuario

    $username  = $user['username'];
    $userFile  = NEW_USERS . '/' . $username . '.json';

    // Leer fichero existente si hay
    $userData = ['categories' => [], 'last_feeds_update' => 0, 'azuracast' => [
        'station_id'   => null,
        'widget_color' => '#3b82f6',
        'show_logo'    => false,
        'logo_url'     => ''
    ]];
    if (file_exists($userFile)) {
        $existing = json_decode(file_get_contents($userFile), true);
        if ($existing) $userData = array_merge($userData, $existing);
    }

    // Aplicar categorías del db.json antiguo si existen
    if (isset($oldCategories[$username])) {
        $userData['categories'] = $oldCategories[$username];
    }

    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    chmod($userFile, 0640);
    echo "db/users/{$username}.json guardado.\n";
}

echo "\n=== Migración completada con éxito ===\n";
echo "Ahora puedes iniciar sesión con las credenciales anteriores.\n";
echo "IMPORTANTE: Borra este archivo (migrate_db.php) una vez verificado el acceso.\n";
