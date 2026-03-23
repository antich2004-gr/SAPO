<?php
// includes/overrides.php — Correcciones manuales de emisiones

function _overridesPath($username) {
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

/**
 * Modifica el archivo de overrides con bloqueo exclusivo para evitar
 * race conditions en escrituras concurrentes.
 * $callback recibe el array de overrides y devuelve el array modificado.
 */
function _overridesUpdate(string $path, callable $callback): bool {
    $fh = fopen($path, 'c+');
    if (!$fh) return false;
    if (!flock($fh, LOCK_EX)) { fclose($fh); return false; }

    $raw  = stream_get_contents($fh);
    $data = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : [];
    if (!is_array($data)) $data = [];

    $data = $callback($data);

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}

function saveOverride($username, $programKey, $date, $reason, $correctedBy) {
    $path = _overridesPath($username);
    if (!$path) return false;
    return _overridesUpdate($path, function($data) use ($programKey, $date, $reason, $correctedBy) {
        $data[$programKey][$date] = [
            'reason'       => $reason,
            'corrected_at' => date('Y-m-d H:i'),
            'corrected_by' => $correctedBy,
        ];
        return $data;
    });
}

function removeOverride($username, $programKey, $date) {
    $path = _overridesPath($username);
    if (!$path) return false;
    return _overridesUpdate($path, function($data) use ($programKey, $date) {
        unset($data[$programKey][$date]);
        if (isset($data[$programKey]) && empty($data[$programKey])) {
            unset($data[$programKey]);
        }
        return $data;
    });
}
