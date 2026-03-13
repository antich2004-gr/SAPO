<?php
// views/admin.php - Panel de administracion
$config = getConfig();
$users = getAllUsers();
?>

<div class="card">
    <div class="nav-buttons">
        <h2 style="margin:0;">⚙️ Panel de Administración</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <a href="?page=help" class="btn btn-secondary" style="margin-right: 10px;"><span class="btn-icon">📖</span> Ayuda</a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
            </form>
        </div>
    </div>

    <!-- ── CONFIGURACIÓN DEL SISTEMA ─────────────────────────────────────── -->
    <div class="section" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px;">
            <span style="background:#667eea;color:white;border-radius:8px;padding:4px 10px;font-size:14px;">⚙️</span>
            Configuración del sistema
        </h3>

        <?php if (!empty($config['base_path'])): ?>
        <div style="background:#f0fff4;border:1px solid #9ae6b4;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#276749;">
            <strong>✅ Ruta activa:</strong>
            <code style="background:#c6f6d5;padding:2px 6px;border-radius:4px;margin-left:4px;"><?php echo htmlEsc($config['base_path']); ?>/[usuario]/media/<?php echo htmlEsc($config['subscriptions_folder']); ?>/serverlist.txt</code>
        </div>
        <?php else: ?>
        <div class="alert alert-info" style="margin-bottom:20px;">
            ⚠️ Configura la ruta base para que las emisoras puedan gestionar sus suscripciones.
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <!-- Bloque: API de Radiobot -->
            <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h4 style="margin:0 0 14px 0;font-size:14px;color:#4a5568;text-transform:uppercase;letter-spacing:.05em;">🔗 API de Radiobot</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label>URL de la API</label>
                        <input type="text" name="azuracast_api_url" value="<?php echo htmlEsc($config['azuracast_api_url'] ?? ''); ?>" placeholder="https://tu-servidor.com/api" maxlength="255">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>API Key <small style="font-weight:400;color:#718096;">(Admin → API Keys)</small></label>
                        <input type="password" name="azuracast_api_key" value="<?php echo htmlEsc($config['azuracast_api_key'] ?? ''); ?>" placeholder="••••••••••••" maxlength="255" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <!-- Bloque: Rutas del servidor -->
            <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h4 style="margin:0 0 14px 0;font-size:14px;color:#4a5568;text-transform:uppercase;letter-spacing:.05em;">📁 Rutas del servidor</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label>Ruta base de emisoras <small style="font-weight:400;color:#718096;">(ej: /mnt/emisoras)</small></label>
                        <input type="text" name="base_path" value="<?php echo htmlEsc($config['base_path']); ?>" required placeholder="/mnt/emisoras" maxlength="255">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Ruta base de grabaciones <small style="font-weight:400;color:#718096;">(si difiere de la base)</small></label>
                        <input type="text" name="recordings_mount_base" value="<?php echo htmlEsc($config['recordings_mount_base'] ?? ''); ?>" placeholder="/mnt/emisoras" maxlength="255">
                        <small style="color:#718096;margin-top:4px;display:block;">Si se deja vacío se usa la ruta base de emisoras o la de Radiobot.</small>
                    </div>
                </div>
            </div>

            <!-- Bloque avanzado plegable -->
            <details style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;margin-bottom:20px;">
                <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#92400e;user-select:none;">
                    🔧 Configuración avanzada de carpetas
                </summary>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
                    <div class="form-group" style="margin:0;">
                        <label>Carpeta de suscripciones <small style="font-weight:400;color:#718096;">(donde estará serverlist.txt)</small></label>
                        <input type="text" name="subscriptions_folder" value="<?php echo htmlEsc($config['subscriptions_folder']); ?>" required placeholder="Suscripciones" maxlength="100">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Carpeta de podcasts <small style="font-weight:400;color:#718096;">(donde se descargan)</small></label>
                        <input type="text" name="podcasts_folder" value="<?php echo htmlEsc($config['podcasts_folder'] ?? 'Podcasts'); ?>" required placeholder="Podcasts" pattern="[a-zA-Z0-9_-]+" maxlength="100">
                    </div>
                </div>
            </details>

            <button type="submit" class="btn btn-warning"><span class="btn-icon">💾</span> Guardar configuración</button>
        </form>
    </div>

    <!-- ── CREAR USUARIO ─────────────────────────────────────────────────── -->
    <div class="section" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px;">
            <span style="background:#48bb78;color:white;border-radius:8px;padding:4px 10px;font-size:14px;">➕</span>
            Crear nueva emisora
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:14px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label>Nombre de usuario <small style="font-weight:400;color:#718096;">(slug)</small></label>
                    <input type="text" name="new_username" required placeholder="radio_ejemplo" pattern="[a-z0-9_]+" title="Solo minúsculas, números y guiones bajos" maxlength="50">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Contraseña <small style="font-weight:400;color:#718096;">(mín. 8 caracteres)</small></label>
                    <input type="password" name="new_password" required minlength="8" placeholder="••••••••">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Nombre de la emisora</label>
                    <input type="text" name="station_name" required placeholder="Radio Ejemplo FM" maxlength="100">
                </div>
                <button type="submit" class="btn btn-success" style="white-space:nowrap;"><span class="btn-icon">➕</span> Crear</button>
            </div>
        </form>
    </div>

    <!-- ── LISTA DE EMISORAS ─────────────────────────────────────────────── -->
    <div class="section">
        <h3 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px;">
            <span style="background:#4299e1;color:white;border-radius:8px;padding:4px 10px;font-size:14px;">📻</span>
            Emisoras registradas
            <span style="background:#e2e8f0;color:#4a5568;border-radius:99px;padding:2px 10px;font-size:13px;font-weight:600;"><?php echo count($users); ?></span>
        </h3>

        <?php
        $hasUsers = false;
        foreach ($users as $user):
            $hasUsers = true;
            $isAdminUser = ($user['is_admin'] ?? false);
        ?>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin-bottom:12px;display:flex;flex-direction:column;gap:12px;">

            <!-- Cabecera de la emisora -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="font-size:16px;font-weight:700;color:#2d3748;">
                        <?php if ($isAdminUser): ?>
                            🛡️ <?php echo htmlEsc($user['station_name']); ?>
                        <?php else: ?>
                            📻 <?php echo htmlEsc($user['station_name']); ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:#718096;margin-top:2px;">
                        @<?php echo htmlEsc($user['username']); ?>
                        <?php if ($isAdminUser): ?>
                            <span style="background:#e9d8fd;color:#6b46c1;border-radius:99px;padding:1px 8px;margin-left:6px;font-size:11px;font-weight:600;">Administrador</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-warning" onclick="showPasswordModal('<?php echo htmlEsc($user['username']); ?>', '<?php echo htmlEsc($user['station_name']); ?>')" style="font-size:13px;padding:6px 12px;">
                        🔑 Contraseña
                    </button>
                    <?php if (!$isAdminUser): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="impersonate_user">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo htmlEsc($user['id']); ?>">
                        <button type="submit" class="btn btn-primary" style="font-size:13px;padding:6px 12px;">👤 Entrar como</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo htmlEsc($user['id']); ?>">
                        <button type="submit" class="btn btn-danger" style="font-size:13px;padding:6px 12px;" onclick="return confirm('¿Eliminar esta emisora?')">🗑️ Eliminar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Config de Radiobot (solo emisoras no-admin) -->
            <?php if (!$isAdminUser):
                $azuracastConfig = getAzuracastConfig($user['username']);
            ?>
            <div style="background:#f7fafc;border-radius:8px;padding:12px 16px;">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="update_azuracast_config">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="username" value="<?php echo htmlEsc($user['username']); ?>">
                    <div style="display:grid;grid-template-columns:180px 120px auto;gap:10px;align-items:end;">
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:12px;margin-bottom:4px;">Station ID Radiobot</label>
                            <input type="number" name="station_id" value="<?php echo htmlEsc($azuracastConfig['station_id'] ?? ''); ?>" placeholder="ej: 34" style="padding:6px;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:12px;margin-bottom:4px;">Color widget</label>
                            <input type="color" name="widget_color" value="<?php echo htmlEsc($azuracastConfig['widget_color'] ?? '#3b82f6'); ?>" style="padding:2px;height:34px;width:100%;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding:6px 14px;font-size:13px;">💾 Guardar</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
        <?php if (!$hasUsers): ?>
            <p style="color:#718096;text-align:center;padding:30px 0;">No hay emisoras registradas aún.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal cambio de contraseña -->
<div id="passwordModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:12px;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <h3 style="margin:0 0 8px 0;">🔑 Cambiar contraseña</h3>
        <p style="color:#6b7280;margin:0 0 20px 0;font-size:14px;">
            <strong id="modalStationName"></strong> · <span id="modalUsername" style="color:#9ca3af;"></span>
        </p>
        <form method="POST" id="passwordForm">
            <input type="hidden" name="action" value="admin_change_password">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="username" id="formUsername">
            <div class="form-group">
                <label>Nueva contraseña</label>
                <input type="password" name="new_password" id="newPassword" required minlength="8" placeholder="Mínimo 8 caracteres">
            </div>
            <div class="form-group">
                <label>Confirmar contraseña</label>
                <input type="password" name="confirm_password" id="confirmPassword" required minlength="8" placeholder="Repite la contraseña">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="hidePasswordModal()">Cancelar</button>
                <button type="submit" class="btn btn-success"><span class="btn-icon">💾</span> Guardar</button>
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
    const confirm  = document.getElementById('confirmPassword').value;
    if (password !== confirm) { e.preventDefault(); alert('❌ Las contraseñas no coinciden'); return false; }
    if (password.length < 8)  { e.preventDefault(); alert('❌ Mínimo 8 caracteres'); return false; }
});
document.getElementById('passwordModal').addEventListener('click', function(e) {
    if (e.target === this) hidePasswordModal();
});
</script>
