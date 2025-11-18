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
            <a href="?page=help" class="btn btn-secondary" style="margin-right: 10px;"><span class="btn-icon">📖</span> Ayuda</a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
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
            <div class="form-group">
                <label>URL API de AzuraCast: <small>(para parrilla de programación)</small></label>
                <input type="text" name="azuracast_api_url" value="<?php echo htmlEsc($config['azuracast_api_url'] ?? 'https://radio.radiobot.org/api'); ?>" placeholder="https://radio.radiobot.org/api" maxlength="255">
            </div>
            <button type="submit" class="btn btn-warning"><span class="btn-icon">💾</span> Guardar Configuracion</button>
        </form>
    </div>

    <div class="section" style="background: #f0f9ff; border: 1px solid #bae6fd;">
        <h3>🧪 Probar Conexión a AzuraCast</h3>
        <p style="color: #0c4a6e; margin-bottom: 15px;">
            Verifica que SAPO puede conectarse correctamente a la API de AzuraCast configurada.
        </p>
        <a href="test_azuracast.php" target="_blank" class="btn btn-primary">
            🧪 Ejecutar Test de Conexión
        </a>
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
                <label>Contraseña:</label>
                <input type="password" name="new_password" required minlength="8">
                <small style="color: #718096;">Mínimo 8 caracteres</small>
            </div>
            <div class="form-group">
                <label>Nombre de la Emisora:</label>
                <input type="text" name="station_name" required placeholder="Radio Ejemplo FM" maxlength="100">
            </div>
            <button type="submit" class="btn btn-success"><span class="btn-icon">➕</span> Crear Usuario</button>
        </form>
    </div>
    
    <div class="user-list">
        <h3>Usuarios Registrados</h3>
        <?php
        $hasUsers = false;
        foreach ($users as $user):
            // Mostrar todos los usuarios, incluido el admin
            $hasUsers = true;
            $isAdminUser = ($user['is_admin'] ?? false);
        ?>
            <div class="user-item">
                <div class="user-info">
                    <strong><?php echo htmlEsc($user['station_name']); ?></strong>
                    <small>Usuario: <?php echo htmlEsc($user['username']); ?></small>

                    <?php if (!$isAdminUser): ?>
                    <?php
                    $azuracastConfig = getAzuracastConfig($user['username']);
                    ?>
                    <div style="margin-top: 15px; padding: 10px; background: #f7fafc; border-radius: 4px;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="update_azuracast_config">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="username" value="<?php echo htmlEsc($user['username']); ?>">

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; align-items: end;">
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-size: 12px; margin-bottom: 5px;">Station ID AzuraCast:</label>
                                    <input type="number" name="station_id" value="<?php echo htmlEsc($azuracastConfig['station_id'] ?? ''); ?>" placeholder="34" style="padding: 6px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-size: 12px; margin-bottom: 5px;">Color del widget:</label>
                                    <input type="color" name="widget_color" value="<?php echo htmlEsc($azuracastConfig['widget_color'] ?? '#3b82f6'); ?>" style="padding: 2px; height: 34px;">
                                </div>
                                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 14px;">
                                    <span class="btn-icon">💾</span> Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-warning" onclick="showPasswordModal('<?php echo htmlEsc($user['username']); ?>', '<?php echo htmlEsc($user['station_name']); ?>')">
                        <span class="btn-icon">🔑</span> Cambiar Contraseña
                    </button>
                    <?php if (!$isAdminUser): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo htmlEsc($user['id']); ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este usuario?')"><span class="btn-icon">🗑️</span> Eliminar</button>
                    </form>
                    <?php else: ?>
                    <span style="color: #718096; font-size: 14px; padding: 8px;">(Usuario administrador principal)</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$hasUsers): ?>
            <p style="color: #718096;">No hay usuarios registrados aun.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para cambiar contraseña -->
<div id="passwordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
        <h3 style="margin: 0 0 20px 0;">🔑 Cambiar Contraseña</h3>
        <p style="color: #6b7280; margin-bottom: 20px;">
            Usuario: <strong id="modalStationName"></strong> (<span id="modalUsername"></span>)
        </p>

        <form method="POST" id="passwordForm">
            <input type="hidden" name="action" value="admin_change_password">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="username" id="formUsername">

            <div class="form-group">
                <label>Nueva Contraseña:</label>
                <input type="password" name="new_password" id="newPassword" required minlength="8" placeholder="Mínimo 8 caracteres">
            </div>

            <div class="form-group">
                <label>Confirmar Contraseña:</label>
                <input type="password" name="confirm_password" id="confirmPassword" required minlength="8" placeholder="Repite la contraseña">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="hidePasswordModal()">Cancelar</button>
                <button type="submit" class="btn btn-success">
                    <span class="btn-icon">💾</span> Cambiar Contraseña
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showPasswordModal(username, stationName) {
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('modalStationName').textContent = stationName;
    document.getElementById('formUsername').value = username;
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('passwordModal').style.display = 'flex';
}

function hidePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const password = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    if (password !== confirm) {
        e.preventDefault();
        alert('❌ Las contraseñas no coinciden');
        return false;
    }

    if (password.length < 8) {
        e.preventDefault();
        alert('❌ La contraseña debe tener al menos 8 caracteres');
        return false;
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('passwordModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hidePasswordModal();
    }
});
</script>
