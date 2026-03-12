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
    <div style="background: #1e40af; color: white; padding: 10px 20px; margin-bottom: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
        <span>👤 Viendo como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong> (<?php echo htmlEsc($_SESSION['username']); ?>)</span>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="stop_impersonating">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <button type="submit" style="background: white; color: #1e40af; border: none; padding: 5px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">← Volver a Admin</button>
        </form>
    </div>
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

        echo '<div class="alert alert-warning" style="border-left: 4px solid #ffc107;">';
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

<script src="assets/app.js"></script>
</body>
</html>
