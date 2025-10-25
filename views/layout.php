<?php
// views/layout.php - Interfaz HTML principal
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAPO - Sistema de Automatizacion de Podcasts</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🐸 SAPO</h1>
        <div class="subtitle">Sistema de Automatizacion de Podcasts para Radiobot</div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlEsc($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlEsc($error); ?></div>
    <?php endif; ?>
    
    <?php 
    if (isset($_SESSION['imported_categories']) && $_SESSION['imported_categories'] > 0): 
        $count = $_SESSION['imported_categories'];
        unset($_SESSION['imported_categories']);
    ?>
        <div class="alert alert-success">
            Se han importado automaticamente <?php echo $count; ?> podcast<?php echo $count > 1 ? 's' : ''; ?> desde tu serverlist.txt existente.
        </div>
    <?php endif; ?>

    <?php if (!isLoggedIn()): ?>
        <?php require_once 'views/login.php'; ?>
    <?php elseif (isAdmin()): ?>
        <?php require_once 'views/admin.php'; ?>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'podget'): ?>
        <?php require_once 'views/podget_status.php'; ?>
    <?php else: ?>
        <?php require_once 'views/user.php'; ?>
    <?php endif; ?>
</div>

<script src="assets/app.js"></script>
</body>
</html>
