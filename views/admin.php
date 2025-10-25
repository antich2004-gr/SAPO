<?php
// views/admin.php - Panel de administracion
$config = getConfig();
$users = getAllUsers();
?>

<div class="card">
    <div class="nav-buttons">
        <h2>Panel de Administracion</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary">Cerrar Sesion</button>
            </form>
        </div>
    </div>
    
    <div class="section">
        <h3>Configuracion de Rutas</h3>
        <?php if (!empty($config['base_path'])): ?>
            <div class="config-info">
                <strong>Ruta Base:</strong> <?php echo htmlEsc($config['base_path']); ?><br>
                <strong>Carpeta Suscripciones:</strong> <?php echo htmlEsc($config['subscriptions_folder']); ?><br>
                <strong>Formato:</strong> [Ruta Base]/[usuario]/media/<?php echo htmlEsc($config['subscriptions_folder']); ?>/serverlist.txt
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Primero debes configurar la ruta base donde se almacenaran los archivos serverlist.txt
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label>Ruta Base: <small>(Ejemplo: C:\emisoras o /mnt/emisoras)</small></label>
                <input type="text" name="base_path" value="<?php echo htmlEsc($config['base_path']); ?>" required placeholder="C:\emisoras" maxlength="255">
            </div>
            <div class="form-group">
                <label>Carpeta de Suscripciones: <small>(donde estara serverlist.txt)</small></label>
                <input type="text" name="subscriptions_folder" value="<?php echo htmlEsc($config['subscriptions_folder']); ?>" required placeholder="Suscripciones" maxlength="100">
            </div>
            <button type="submit" class="btn btn-warning">Guardar Configuracion</button>
        </form>
    </div>
    
    <div class="section">
        <h3>Crear Nuevo Usuario</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label>Nombre de Usuario (slug):</label>
                <input type="text" name="new_username" required placeholder="radio_ejemplo" pattern="[a-z0-9_]+" title="Solo minusculas, numeros y guiones bajos" maxlength="50">
                <small style="color: #718096;">Se convertira automaticamente en formato slug</small>
            </div>
            <div class="form-group">
                <label>Contrasena:</label>
                <input type="password" name="new_password" required minlength="8">
                <small style="color: #718096;">Minimo 8 caracteres</small>
            </div>
            <div class="form-group">
                <label>Nombre de la Emisora:</label>
                <input type="text" name="station_name" required placeholder="Radio Ejemplo FM" maxlength="100">
            </div>
            <button type="submit" class="btn btn-success">Crear Usuario</button>
        </form>
    </div>
    
    <div class="user-list">
        <h3>Usuarios Registrados</h3>
        <?php
        $hasUsers = false;
        foreach ($users as $user):
            if ($user['is_admin'] ?? false) continue;
            $hasUsers = true;
        ?>
            <div class="user-item">
                <div class="user-info">
                    <strong><?php echo htmlEsc($user['station_name']); ?></strong>
                    <small>Usuario: <?php echo htmlEsc($user['username']); ?></small>
                    <?php 
                    $userPath = getServerListPath($user['username']);
                    if ($userPath): 
                    ?>
                        <small>Ruta: <?php echo htmlEsc($userPath); ?></small>
                    <?php endif; ?>
                </div>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este usuario?')">Eliminar</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (!$hasUsers): ?>
            <p style="color: #718096;">No hay usuarios registrados aun.</p>
        <?php endif; ?>
    </div>
</div>
