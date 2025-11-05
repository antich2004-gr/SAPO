<?php
/**
 * Script de migración: db.json único -> estructura de archivos separados
 *
 * IMPORTANTE: Ejecutar solo UNA VEZ
 * Uso: php migrate_to_split_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "=== MIGRACIÓN DE BASE DE DATOS ===\n\n";

// Verificar que existe db.json
if (!file_exists(DB_FILE)) {
    die("ERROR: No se encontró db.json\n");
}

// Leer db.json actual
echo "1. Leyendo db.json actual...\n";
$oldDB = json_decode(file_get_contents(DB_FILE), true);
if (!$oldDB) {
    die("ERROR: No se pudo leer db.json\n");
}

echo "   - Usuarios encontrados: " . count($oldDB['users']) . "\n";
echo "   - Categorías de usuarios: " . count($oldDB['user_categories'] ?? []) . "\n";
echo "   - Entradas en cache: " . count($oldDB['feed_cache'] ?? []) . "\n\n";

// Crear estructura de directorios
echo "2. Creando estructura de directorios...\n";
$dbDir = dirname(DB_FILE) . '/db';
$usersDir = $dbDir . '/users';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
    echo "   - Creado: $dbDir\n";
}

if (!is_dir($usersDir)) {
    mkdir($usersDir, 0755, true);
    echo "   - Creado: $usersDir\n";
}

echo "\n";

// Crear global.json (usuarios, config, login_attempts)
echo "3. Creando db/global.json...\n";
$globalData = [
    'users' => $oldDB['users'],
    'config' => $oldDB['config'] ?? [
        'base_path' => '',
        'subscriptions_folder' => 'Suscripciones',
        'cache_duration' => 43200
    ],
    'login_attempts' => $oldDB['login_attempts'] ?? []
];

$globalFile = $dbDir . '/global.json';
file_put_contents($globalFile, json_encode($globalData, JSON_PRETTY_PRINT));
chmod($globalFile, 0666);
echo "   - Creado: $globalFile\n";
echo "   - Usuarios migrados: " . count($globalData['users']) . "\n\n";

// Crear archivos por usuario
echo "4. Creando archivos de usuario individuales...\n";
$userCategories = $oldDB['user_categories'] ?? [];

foreach ($userCategories as $username => $categories) {
    $userData = [
        'categories' => $categories
    ];

    $userFile = $usersDir . '/' . $username . '.json';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT));
    chmod($userFile, 0666);

    echo "   - Creado: db/users/$username.json (" . count($categories) . " categorías)\n";
}

// Verificar usuarios sin categorías y crear archivos vacíos
foreach ($oldDB['users'] as $user) {
    $username = $user['username'];
    $userFile = $usersDir . '/' . $username . '.json';

    if (!file_exists($userFile) && !$user['is_admin']) {
        $userData = ['categories' => []];
        file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT));
        chmod($userFile, 0666);
        echo "   - Creado: db/users/$username.json (vacío)\n";
    }
}

echo "\n";

// Crear feed_cache.json
echo "5. Creando db/feed_cache.json...\n";
$feedCache = $oldDB['feed_cache'] ?? [];
$cacheFile = $dbDir . '/feed_cache.json';
file_put_contents($cacheFile, json_encode($feedCache, JSON_PRETTY_PRINT));
chmod($cacheFile, 0666);
echo "   - Creado: $cacheFile\n";
echo "   - Entradas migradas: " . count($feedCache) . "\n\n";

// Crear backup del db.json original
echo "6. Creando backup del db.json original...\n";
$backupFile = DB_FILE . '.backup-' . date('Y-m-d-His');
copy(DB_FILE, $backupFile);
echo "   - Backup creado: $backupFile\n\n";

// Resumen
echo "=== MIGRACIÓN COMPLETADA ===\n\n";
echo "Estructura creada:\n";
echo "  db/\n";
echo "  ├── global.json (" . count($globalData['users']) . " usuarios)\n";
echo "  ├── feed_cache.json (" . count($feedCache) . " entradas)\n";
echo "  └── users/\n";

$userFiles = glob($usersDir . '/*.json');
foreach ($userFiles as $file) {
    $name = basename($file, '.json');
    $data = json_decode(file_get_contents($file), true);
    $catCount = count($data['categories'] ?? []);
    echo "      ├── $name.json ($catCount categorías)\n";
}

echo "\n";
echo "IMPORTANTE:\n";
echo "1. El archivo db.json original se ha respaldado como:\n";
echo "   $backupFile\n";
echo "2. Puedes eliminar db.json manualmente si todo funciona correctamente\n";
echo "3. El sistema ahora usa la nueva estructura de archivos separados\n";
echo "4. Cada emisora tiene su propio archivo, evitando conflictos de concurrencia\n\n";

echo "¡Migración finalizada con éxito!\n";
?>
