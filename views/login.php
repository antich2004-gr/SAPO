<?php
// views/login.php - Formulario de login
?>
<div class="card">
    <h2>Iniciar Sesion</h2>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>Usuario:</label>
            <input type="text" name="username" required maxlength="50" pattern="[a-zA-Z0-9_]+" title="Solo letras, numeros y guiones bajos">
        </div>
        <div class="form-group">
            <label>Contrasena:</label>
            <input type="password" name="password" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
</div>
