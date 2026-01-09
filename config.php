<?php
// config.php - Configuración central de SAPO

// Versión de SAPO
define('SAPO_VERSION', '1.24');
define('SAPO_VERSION_DATE', '2025-11-17');

// Seguridad
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Constantes
define('DB_FILE', 'db.json');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos
define('SESSION_TIMEOUT', 1800); // 30 minutos

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
// Activar cookie_secure automáticamente si se detecta HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https');
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', 0); // Cookie de sesión (se borra al cerrar navegador)

// Directorio del proyecto
define('PROJECT_DIR', dirname(__FILE__));
define('INCLUDES_DIR', PROJECT_DIR . '/includes');
define('DATA_DIR', PROJECT_DIR . '/data');

// Tipos de rol
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');
define('ROLE_GUEST', 'guest');

// Mensajes de error
define('ERROR_INVALID_TOKEN', 'Token de seguridad inválido. Por favor, recarga la página.');
define('ERROR_RATE_LIMIT', 'Demasiadas peticiones. Por favor, espera un momento.');
define('ERROR_AUTH_FAILED', 'Usuario o contraseña incorrectos.');
define('ERROR_LOCKED_ACCOUNT', 'Cuenta bloqueada temporalmente por demasiados intentos fallidos.');

?>
