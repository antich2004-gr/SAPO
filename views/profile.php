<?php
// views/profile.php - Perfil de la emisora
$currentUser = findUserById($_SESSION['user_id']);
$currentEmail = $currentUser['email'] ?? '';
?>

<div class="card">
    <h2 style="margin-bottom: var(--spacing-lg);">👤 Mi Perfil</h2>

    <!-- ── DATOS DE LA EMISORA ──────────────────────────────────────────── -->
    <div style="margin-bottom: var(--spacing-lg);">
        <h3>📻 Datos de la emisora</h3>
        <p style="color: #6b7280; margin-bottom: var(--spacing-md);">
            Información pública de tu emisora en SAPO.
        </p>

        <form method="POST" style="max-width: 480px;">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-group">
                <label for="profile-username">Nombre de usuario <small>(no editable)</small></label>
                <input type="text" id="profile-username" value="<?php echo htmlEsc($_SESSION['username']); ?>" disabled>
            </div>

            <div class="form-group">
                <label for="profile-station">Nombre de la emisora</label>
                <input type="text"
                       id="profile-station"
                       name="station_name"
                       value="<?php echo htmlEsc($_SESSION['station_name']); ?>"
                       required
                       maxlength="100"
                       placeholder="Radio Ejemplo FM">
            </div>

            <div class="form-group">
                <label for="profile-email">Email de contacto <small>(opcional)</small></label>
                <input type="email"
                       id="profile-email"
                       name="email"
                       value="<?php echo htmlEsc($currentEmail); ?>"
                       maxlength="255"
                       placeholder="contacto@miemisora.es">
                <small class="field-hint">Se usa para notificaciones del administrador.</small>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="btn-icon">💾</span> Guardar cambios
            </button>
        </form>
    </div>

    <hr style="border: none; border-top: 1px solid var(--border-color); margin: var(--spacing-lg) 0;">

    <!-- ── CAMBIAR CONTRASEÑA ────────────────────────────────────────────── -->
    <div>
        <h3>🔑 Cambiar contraseña</h3>
        <p style="color: #6b7280; margin-bottom: var(--spacing-md);">
            La nueva contraseña debe tener al menos 8 caracteres.
        </p>

        <form method="POST" id="change-password-form" style="max-width: 480px;">
            <input type="hidden" name="action" value="change_own_password">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-group">
                <label for="current-password">Contraseña actual</label>
                <input type="password"
                       id="current-password"
                       name="current_password"
                       required
                       autocomplete="current-password"
                       placeholder="Tu contraseña actual">
            </div>

            <div class="form-group">
                <label for="new-password">Nueva contraseña</label>
                <input type="password"
                       id="new-password"
                       name="new_password"
                       required
                       minlength="8"
                       autocomplete="new-password"
                       placeholder="Mínimo 8 caracteres">
                <small class="field-hint">Mínimo 8 caracteres.</small>
            </div>

            <div class="form-group">
                <label for="confirm-password">Confirmar nueva contraseña</label>
                <input type="password"
                       id="confirm-password"
                       name="confirm_password"
                       required
                       minlength="8"
                       autocomplete="new-password"
                       placeholder="Repite la nueva contraseña">
            </div>

            <button type="submit" class="btn btn-warning">
                <span class="btn-icon">🔐</span> Cambiar contraseña
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('change-password-form').addEventListener('submit', function (e) {
    const newPass     = document.getElementById('new-password').value;
    const confirmPass = document.getElementById('confirm-password').value;
    if (newPass !== confirmPass) {
        e.preventDefault();
        showToast('Las contraseñas no coinciden', 'error');
    }
});
</script>
