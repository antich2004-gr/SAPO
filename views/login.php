<?php
// views/login.php - Formulario de login
?>
<div class="card">
    <h2>Iniciar Sesión</h2>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="form-group">
            <label for="login-username">Usuario:</label>
            <input type="text" id="login-username" name="username" required maxlength="50" pattern="[a-zA-Z0-9_]+" title="Solo letras, números y guiones bajos" autocomplete="username">
            <small class="field-hint">Solo letras, números y guiones bajos (ej: mi_emisora)</small>
        </div>
        <div class="form-group">
            <label for="login-password">Contraseña:</label>
            <input type="password" id="login-password" name="password" required minlength="8" autocomplete="current-password">
            <small class="field-hint">Mínimo 8 caracteres</small>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-sm);"><span class="btn-icon">🔐</span> Entrar</button>
    </form>
</div>
