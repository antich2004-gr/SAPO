#!/usr/bin/env php
<?php
/**
 * Script de limpieza automática de grabaciones
 * Se ejecuta cada 24 horas vía cron
 *
 * Uso: php /path/to/SAPO/cron/cleanup_recordings.php
 */

// Configurar zona horaria
date_default_timezone_set('Europe/Madrid');

// Cargar archivos necesarios
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/recordings.php';

// Log de inicio
$logFile = $baseDir . '/logs/recordings_cleanup.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("========================================");
logMessage("Iniciando limpieza automática de grabaciones");
logMessage("========================================");

// Obtener todos los usuarios
$users = getAllUsers();

if (empty($users)) {
    logMessage("No hay usuarios en el sistema");
    exit(0);
}

$totalDeleted = 0;
$totalSpaceSaved = 0;
$usersProcessed = 0;
$usersWithErrors = 0;

foreach ($users as $user) {
    $username = $user['username'];

    // Saltar usuarios admin
    if (isset($user['is_admin']) && $user['is_admin']) {
        continue;
    }

    logMessage("Procesando usuario: $username");

    try {
        // Obtener configuración del usuario
        $config = getRecordingsConfig($username);

        if (!isset($config['auto_delete']) || !$config['auto_delete']) {
            logMessage("  - Auto-delete desactivado, saltando");
            continue;
        }

        $retentionDays = $config['retention_days'] ?? 30;
        logMessage("  - Días de retención: $retentionDays");

        // Eliminar grabaciones antiguas
        $result = deleteOldRecordings($username, $retentionDays);

        if ($result['success']) {
            $deleted = $result['deleted'];
            $spaceSaved = $result['space_saved'];

            logMessage("  - Grabaciones eliminadas: $deleted");
            logMessage("  - Espacio liberado: " . $result['space_saved_formatted']);

            $totalDeleted += $deleted;
            $totalSpaceSaved += $spaceSaved;
            $usersProcessed++;
        } else {
            logMessage("  - ERROR: " . $result['message']);
            $usersWithErrors++;
        }

    } catch (Exception $e) {
        logMessage("  - EXCEPCIÓN: " . $e->getMessage());
        $usersWithErrors++;
    }
}

logMessage("========================================");
logMessage("Resumen de limpieza:");
logMessage("  - Usuarios procesados: $usersProcessed");
logMessage("  - Total de grabaciones eliminadas: $totalDeleted");
logMessage("  - Total de espacio liberado: " . formatBytes($totalSpaceSaved));
logMessage("  - Usuarios con errores: $usersWithErrors");
logMessage("========================================");
logMessage("Limpieza completada");
logMessage("");

exit(0);
