<?php
// views/layout.php - Interfaz HTML principal
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPO - Sistema de Automatización de Podcasts</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🐸 SAPO</h1>
        <div class="subtitle">Sistema de Automatización de Podcasts</div>
    </div>

    <?php if (isImpersonating()): ?>
    <div class="no-print impersonate-bar">
        <span>👤 Viendo como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong> (<?php echo htmlEsc($_SESSION['username']); ?>)</span>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="stop_impersonating">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <button type="submit" class="btn-impersonate-back">← Volver a Admin</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (isLoggedIn()):
        $currentPage = $_GET['page'] ?? '';
        $currentTab  = $_GET['tab']  ?? '';
        $isHomePage  = ($currentPage === '' || !in_array($currentPage, ['parrilla','seguimiento_emision','help','help_parrilla']));
    ?>
    <nav class="main-nav no-print">
        <?php if (isAdmin() && !isImpersonating()): ?>
            <a href="?" class="nav-link <?php echo ($currentPage === '') ? 'active' : ''; ?>">⚙️ Admin</a>
            <a href="?page=seguimiento_emision" class="nav-link <?php echo ($currentPage === 'seguimiento_emision') ? 'active' : ''; ?>">📊 Seguimiento</a>
            <div class="nav-spacer"></div>
            <a href="?page=help" class="nav-link <?php echo ($currentPage === 'help') ? 'active' : ''; ?>">❓ Ayuda</a>
        <?php else: ?>
            <a href="?" class="nav-link <?php echo ($isHomePage && $currentTab === '') ? 'active' : ''; ?>">🏠 Mi SAPO</a>
            <a href="?page=seguimiento_emision" class="nav-link <?php echo ($currentPage === 'seguimiento_emision') ? 'active' : ''; ?>">📊 Seguimiento</a>
            <a href="?tab=podcasts" class="nav-link <?php echo ($isHomePage && $currentTab === 'podcasts') ? 'active' : ''; ?>">🎙️ Mis Podcasts</a>
            <a href="?page=parrilla" class="nav-link <?php echo ($currentPage === 'parrilla') ? 'active' : ''; ?>">📋 Parrilla</a>
            <div class="nav-dropdown">
                <button class="nav-link nav-dropdown-trigger <?php echo ($isHomePage && in_array($currentTab, ['config','recordings'])) ? 'active' : ''; ?>">🔧 Herramientas</button>
                <div class="nav-dropdown-menu">
                    <a href="?tab=config" class="nav-dropdown-item <?php echo ($currentTab === 'config') ? 'active' : ''; ?>">⏰ Señales horarias</a>
                    <a href="?tab=recordings" class="nav-dropdown-item <?php echo ($currentTab === 'recordings') ? 'active' : ''; ?>">🔴 Grabaciones</a>
                </div>
            </div>
            <div class="nav-spacer"></div>
            <a href="?page=help" class="nav-link <?php echo (in_array($currentPage, ['help','help_parrilla'])) ? 'active' : ''; ?>">❓ Ayuda</a>
        <?php endif; ?>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <button type="submit" class="nav-link nav-link-logout">🚪 Salir</button>
        </form>
    </nav>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlEsc($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo htmlEsc($error); ?></div>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['imported_categories']) && $_SESSION['imported_categories'] > 0):
        $count = $_SESSION['imported_categories'];
        unset($_SESSION['imported_categories']);
    ?>
        <div class="alert alert-success">
            Se han importado automáticamente <?php echo htmlEsc($count); ?> podcast<?php echo $count > 1 ? 's' : ''; ?> desde tu serverlist.txt existente.
        </div>
    <?php endif; ?>

    <?php
    // Mostrar mensajes de sesión
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-success">' . htmlEsc($_SESSION['message']) . '</div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-error">' . htmlEsc($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }

    // Mostrar recordatorio de Radiobot si es necesario
    if (isset($_SESSION['show_azuracast_reminder']) && $_SESSION['show_azuracast_reminder']) {
        $azuracastAction = $_SESSION['azuracast_action'] ?? '';
        $config = getConfig();
        $azuracastUrl = $config['azuracast_url'] ?? '';

        echo '<div class="alert alert-warning">';
        echo '<strong>⚠️ RECORDATORIO IMPORTANTE:</strong><br>';
        echo 'No olvides actualizar las playlists en Radiobot para que apunten a las nuevas rutas.';
        echo '</div>';

        unset($_SESSION['show_azuracast_reminder']);
        unset($_SESSION['azuracast_action']);
        unset($_SESSION['azuracast_old_name']);
        unset($_SESSION['azuracast_new_name']);
        unset($_SESSION['azuracast_source']);
        unset($_SESSION['azuracast_target']);
    }
    ?>

    <?php if (isset($_GET['page']) && $_GET['page'] == 'help'): ?>
        <?php require_once 'views/help.php'; ?>
    <?php elseif (isset($_GET['page']) && $_GET['page'] == 'help_parrilla'): ?>
        <?php require_once 'views/help_parrilla.php'; ?>
    <?php elseif (isset($_GET['page']) && $_GET['page'] == 'parrilla' && isLoggedIn() && !isAdmin()): ?>
        <?php require_once 'views/parrilla.php'; ?>
    <?php elseif (isset($_GET['page']) && $_GET['page'] == 'seguimiento_emision' && isLoggedIn()): ?>
        <?php require_once 'views/seguimiento_emision.php'; ?>
    <?php elseif (!isLoggedIn()): ?>
        <?php require_once 'views/login.php'; ?>
    <?php elseif (isAdmin()): ?>
        <?php require_once 'views/admin.php'; ?>
    <?php else: ?>
        <?php require_once 'views/user.php'; ?>
    <?php endif; ?>

    <footer style="margin-top: 40px; padding: 20px 0; border-top: 1px solid #e2e8f0; text-align: center; color: #718096; font-size: 14px;">
        <p style="margin: 0;">🐸 <strong>SAPO</strong> - Sistema de Automatización de Podcasts</p>
        <p style="margin: 5px 0 0 0;">Versión <?php echo SAPO_VERSION; ?></p>
    </footer>
</div>

<div id="toast-container"></div>
<script src="assets/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/app.js'); ?>"></script>
</body>
</html>
