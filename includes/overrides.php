<?php
// includes/overrides.php — Correcciones manuales de emisiones

function _overridesPath($username) {
    // Validación estricta: solo se usa para construir la ruta del archivo
    if (!preg_match('/^[a-z0-9_]{3,50}$/i', $username)) return null;
    $dir = DATA_DIR . '/overrides';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/' . $username . '.json';
}

function loadOverrides($username) {
    $path = _overridesPath($username);
    if (!$path || !file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveOverride($username, $programKey, $date, $reason, $correctedBy) {
    $path = _overridesPath($username);
    if (!$path) return false;
    $data = loadOverrides($username);
    $data[$programKey][$date] = [
        'reason'       => $reason,
        'corrected_at' => date('Y-m-d H:i'),
        'corrected_by' => $correctedBy,
    ];
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function removeOverride($username, $programKey, $date) {
    $path = _overridesPath($username);
    if (!$path) return false;
    $data = loadOverrides($username);
    unset($data[$programKey][$date]);
    if (isset($data[$programKey]) && empty($data[$programKey])) unset($data[$programKey]);
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
