<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'config.php';
    require_once INCLUDES_DIR . '/database.php';
    require_once INCLUDES_DIR . '/azuracast.php';
    require_once INCLUDES_DIR . '/programs.php';
    require_once INCLUDES_DIR . '/utils.php';
    
    $station = $_GET['station'] ?? '';
    
    if (empty($station) || !validateInput($station, 'username')) {
        die('Error: Estación inválida');
    }
    
    $user = findUserByUsername($station);
    if (!$user) {
        die('Error: Estación no encontrada');
    }
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Parrilla - $station</title></head><body>";
    echo "<h1>Parrilla de $station</h1>";
    echo "<p>✅ Carga exitosa sin errores</p>";
    echo "<p>Esta es una versión simplificada. El archivo completo tiene un error en el HTML.</p>";
    echo "</body></html>";
    
} catch (Throwable $e) {
    die("Error: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
}
