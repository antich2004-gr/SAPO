<?php
// includes/utils.php - Funciones de utilidad y sanitizaciÃ³n

function validateInput($input, $type = 'text', $maxLength = 255) {
    $input = trim($input);
    
    if (strlen($input) > $maxLength) {
        return false;
    }
    
    switch ($type) {
        case 'username':
            return preg_match('/^[a-z0-9_]{3,50}$/i', $input);
        case 'url':
            // Validar formato básico
            if (filter_var($input, FILTER_VALIDATE_URL) === false) {
                return false;
            }
            // Validar esquema permitido (solo http/https)
            $parsed = parse_url($input);
            $allowedSchemes = ['http', 'https'];
            return isset($parsed['scheme']) && in_array(strtolower($parsed['scheme']), $allowedSchemes);
        case 'path':
            return preg_match('/^[a-zA-Z0-9\/_\-.: \\\\]+$/', $input);
        default:
            return true;
    }
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '_');
    $text = preg_replace('~-+~', '_', $text);
    $text = strtolower($text);
    return $text;
}

function sanitizePodcastName($text) {
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = str_replace(' ', '_', $text);
    $text = preg_replace('/[^a-zA-Z0-9_-]/', '', $text);
    $text = preg_replace('/_+/', '_', $text);
    $text = trim($text, '_');
    
    if (empty($text)) {
        $text = 'podcast_' . time();
    }
    
    return $text;
}

function htmlEsc($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function displayName($text) {
    // Convertir guiones bajos a espacios para mostrar
    return str_replace('_', ' ', $text);
}

/**
 * Validación estricta de username para operaciones críticas de seguridad
 * Usada en: ejecución de comandos, acceso a archivos del sistema, etc.
 *
 * @param string $username Username a validar
 * @return bool True si es válido, false si no
 */
function validateUsernameStrict($username) {
    // Solo alfanuméricos y guión bajo, entre 3 y 20 caracteres
    // Más restrictivo que validateInput() para operaciones críticas
    if (!preg_match('/^[a-z0-9_]{3,20}$/i', $username)) {
        // Logging de seguridad para intentos sospechosos
        error_log("[SAPO-Security] validateUsernameStrict FAILED: " . var_export($username, true) . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }

    // Bloquear usernames peligrosos (path traversal, comandos del sistema)
    // Nota: 'admin' no está en la blacklist porque es un usuario válido del sistema
    $blacklist = ['..', './', '\\', '/', 'root', 'sudo', 'bin', 'etc', 'var', 'tmp'];
    $usernameLower = strtolower($username);
    foreach ($blacklist as $blocked) {
        if (strpos($usernameLower, $blocked) !== false) {
            error_log("[SAPO-Security] validateUsernameStrict BLOCKED (blacklist): $username | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }
    }

    return true;
}

?>
