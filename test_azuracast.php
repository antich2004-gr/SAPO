<?php
// test_azuracast.php - Script de testing para conexión con AzuraCast
// ELIMINAR ESTE ARCHIVO EN PRODUCCIÓN

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test AzuraCast - SAPO</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f7fafc;
        }
        .test-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        pre {
            background: #1f2937;
            color: #e5e7eb;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        h1 { color: #1f2937; }
        h3 { color: #4b5563; margin-top: 0; }
    </style>
</head>
<body>
    <h1>🧪 Test de Conexión AzuraCast</h1>

    <!-- Test 1: Configuración Global -->
    <div class="test-card">
        <h3>1. Configuración Global</h3>
        <?php
        $config = getConfig();
        $apiUrl = $config['azuracast_api_url'] ?? '';

        if (empty($apiUrl)) {
            echo '<p class="error">❌ URL de API no configurada</p>';
        } else {
            echo '<p class="success">✅ URL de API configurada: ' . htmlspecialchars($apiUrl) . '</p>';
        }
        ?>
    </div>

    <!-- Test 2: Conexión a API -->
    <div class="test-card">
        <h3>2. Test de Conexión a API</h3>
        <?php
        $connectionTest = testAzuracastConnection();

        if ($connectionTest['success']) {
            echo '<p class="success">✅ ' . htmlspecialchars($connectionTest['message']) . '</p>';
        } else {
            echo '<p class="error">❌ ' . htmlspecialchars($connectionTest['message']) . '</p>';
        }
        ?>
    </div>

    <!-- Test 3: Usuarios con Station ID -->
    <div class="test-card">
        <h3>3. Usuarios con Station ID configurado</h3>
        <?php
        $db = getGlobalDB();
        $users = $db['users'] ?? [];
        $hasConfiguredUsers = false;

        foreach ($users as $user) {
            if ($user['is_admin'] ?? false) continue;

            $username = $user['username'];
            $stationName = $user['station_name'];
            $azConfig = getAzuracastConfig($username);
            $stationId = $azConfig['station_id'] ?? null;

            if ($stationId) {
                $hasConfiguredUsers = true;
                echo '<p class="success">✅ ' . htmlspecialchars($stationName) . ' (' . htmlspecialchars($username) . ') - Station ID: ' . htmlspecialchars($stationId) . '</p>';
            } else {
                echo '<p class="error">❌ ' . htmlspecialchars($stationName) . ' (' . htmlspecialchars($username) . ') - Sin Station ID</p>';
            }
        }

        if (!$hasConfiguredUsers) {
            echo '<p style="color: #6b7280;">⚠️ Ningún usuario tiene Station ID configurado todavía</p>';
        }
        ?>
    </div>

    <!-- Test 4: Obtener Programación -->
    <div class="test-card">
        <h3>4. Test de Obtención de Programación</h3>
        <?php
        $db = getGlobalDB();
        $users = $db['users'] ?? [];
        $testedAny = false;

        foreach ($users as $user) {
            if ($user['is_admin'] ?? false) continue;

            $username = $user['username'];
            $stationName = $user['station_name'];
            $azConfig = getAzuracastConfig($username);
            $stationId = $azConfig['station_id'] ?? null;

            if (!$stationId) continue;

            $testedAny = true;
            echo '<h4>Emisora: ' . htmlspecialchars($stationName) . '</h4>';

            $schedule = getAzuracastSchedule($username);

            if ($schedule === false) {
                echo '<p class="error">❌ Error al obtener programación</p>';
            } elseif (empty($schedule)) {
                echo '<p style="color: #f59e0b;">⚠️ Programación vacía (sin eventos programados)</p>';
            } else {
                echo '<p class="success">✅ Programación obtenida: ' . count($schedule) . ' eventos</p>';
                echo '<details><summary>Ver datos (JSON)</summary>';
                echo '<pre>' . json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                echo '</details>';

                // Formatear para FullCalendar
                $formattedEvents = formatEventsForCalendar($schedule, $azConfig['widget_color'] ?? '#3b82f6');
                echo '<details><summary>Ver eventos formateados para FullCalendar</summary>';
                echo '<pre>' . json_encode($formattedEvents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                echo '</details>';
            }
        }

        if (!$testedAny) {
            echo '<p style="color: #6b7280;">⚠️ No hay usuarios con Station ID para probar</p>';
        }
        ?>
    </div>

    <div class="test-card">
        <h3>📝 Instrucciones</h3>
        <ol>
            <li>Si todos los tests pasan ✅, la configuración es correcta</li>
            <li>Configura el Station ID para cada emisora en el panel de administración</li>
            <li>Una vez confirmado que funciona, <strong>elimina este archivo (test_azuracast.php)</strong> por seguridad</li>
        </ol>
    </div>
</body>
</html>
