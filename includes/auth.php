<?php
// includes/auth.php - Autenticación y seguridad

/**
 * Limpiar intentos de login antiguos para prevenir crecimiento indefinido
 * Se ejecuta periódicamente para mantener la base de datos limpia
 */
function cleanOldLoginAttempts() {
    $db = getGlobalDB();
    if (!isset($db['login_attempts']) || empty($db['login_attempts'])) {
        return;
    }

    $now = time();
    $cleaned = false;

    foreach ($db['login_attempts'] as $username => $data) {
        // Eliminar intentos más antiguos que 2x el tiempo de lockout
        if ($now - $data['last_attempt'] > (LOCKOUT_TIME * 2)) {
            unset($db['login_attempts'][$username]);
            $cleaned = true;
        }
    }

    if ($cleaned) {
        saveGlobalDB($db);
    }
}


function checkLoginAttempts($username) {
    $db = getGlobalDB();
    if (!isset($db['login_attempts'])) {
        $db['login_attempts'] = [];
    }

    $now = time();
    $attempts = $db['login_attempts'][$username] ?? ['count' => 0, 'last_attempt' => 0];

    if ($now - $attempts['last_attempt'] > LOCKOUT_TIME) {
        $attempts = ['count' => 0, 'last_attempt' => 0];
    }

    return $attempts;
}

function recordLoginAttempt($username, $success = false) {
    $db = getGlobalDB();
    if (!isset($db['login_attempts'])) {
        $db['login_attempts'] = [];
    }

    if ($success) {
        unset($db['login_attempts'][$username]);
    } else {
        $attempts = checkLoginAttempts($username);
        $db['login_attempts'][$username] = [
            'count' => $attempts['count'] + 1,
            'last_attempt' => time()
        ];
    }

    saveGlobalDB($db);
}

function authenticateUser($username, $password) {
    // Limpiar intentos antiguos periódicamente (10% de probabilidad)
    if (rand(1, 10) === 1) {
        cleanOldLoginAttempts();
    }

    $attempts = checkLoginAttempts($username);
    
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        $minutesLeft = ceil((LOCKOUT_TIME - (time() - $attempts['last_attempt'])) / 60);
        return [
            'success' => false,
            'error' => "Demasiados intentos fallidos. Intenta de nuevo en $minutesLeft minutos.",
            'locked' => true
        ];
    }
    
    $user = findUserByUsername($username);
    
    if ($user && password_verify($password, $user['password'])) {
        recordLoginAttempt($username, true);
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    recordLoginAttempt($username, false);
    $remainingAttempts = MAX_LOGIN_ATTEMPTS - $attempts['count'] - 1;
    
    return [
        'success' => false,
        'error' => "Usuario o contraseña incorrectos. Intentos restantes: $remainingAttempts",
        'locked' => false
    ];
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function checkRateLimit($action, $limit = 10, $window = 60) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    $key = $action . '_' . ($_SESSION['user_id'] ?? 'guest');
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'window_start' => $now];
    }
    
    $data = $_SESSION['rate_limit'][$key];
    
    if ($now - $data['window_start'] > $window) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'window_start' => $now];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

?>
