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
            return filter_var($input, FILTER_VALIDATE_URL) !== false;
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

?>
