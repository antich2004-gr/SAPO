#!/usr/bin/env php
<?php
/**
 * record_emissions.php — Registro en tiempo real de emisiones perdidas
 *
 * Ejecutar cada 10 minutos vía cron para capturar el diagnóstico
 * mientras el estado es fresco (playlist vacía, log de Liquidsoap, etc.)
 *
 * Uso: php /path/to/SAPO/cron/record_emissions.php [username]
 *   Si se pasa un username, solo procesa ese usuario.
 *   Sin argumento, procesa todos los usuarios no-admin.
 */

date_default_timezone_set('Europe/Madrid');

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';
require_once INCLUDES_DIR . '/programs.php';
require_once INCLUDES_DIR . '/reports.php';
require_once INCLUDES_DIR . '/emission_recorder.php';

// ─────────────────────────────────────────────────────────────────────────────
// Logging
// ─────────────────────────────────────────────────────────────────────────────

$logFile = $baseDir . '/logs/emission_recorder.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function erLog(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────

$targetUser = $argv[1] ?? null;

$users = getAllUsers();
if (empty($users)) {
    erLog('No hay usuarios en el sistema.');
    exit(0);
}

$total = 0;
foreach ($users as $user) {
    if (!empty($user['is_admin'])) continue;

    $username = $user['username'];
    if ($targetUser !== null && $username !== $targetUser) continue;

    try {
        $count = erRecordUser($username);
        if ($count > 0) {
            erLog("[$username] $count nueva(s) entrada(s) registrada(s).");
        }
        $total += $count;
    } catch (Throwable $e) {
        erLog("[$username] ERROR: " . $e->getMessage());
    }
}

if ($total === 0 && $targetUser === null) {
    // Normal when nothing new to record — no noise in log
} else {
    erLog("Total nuevas entradas: $total");
}

exit(0);
