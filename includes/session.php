
<?php
// includes/session.php - Manejo de sesiones

function initSession() {
    session_start();

    // Verificar timeout de inactividad
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
    $_SESSION['last_activity'] = time();

    // Regenerar ID de sesión periódicamente
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 3600) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getGlobalDB();
    foreach ($db['users'] as $user) {
        if ($user['id'] == $_SESSION['user_id']) {
            return $user;
        }
    }
    return null;
}

function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['station_name'] = $user['station_name'];
    $_SESSION['is_admin'] = $user['is_admin'] ?? false;
    session_regenerate_id(true);
}

function logoutUser() {
    session_destroy();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }
}

?>
