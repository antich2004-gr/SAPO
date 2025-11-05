<?php
// views/login.php - Formulario de login
?>
<div class="card">
    <h2>Iniciar Sesion</h2>
    <form method="POST" id="loginForm">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>Usuario:</label>
            <input type="text" name="username" id="username" required maxlength="50" pattern="[a-zA-Z0-9_]+" title="Solo letras, numeros y guiones bajos">
        </div>
        <div class="form-group">
            <label>Contrasena:</label>
            <input type="password" name="password" id="password" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
</div>

<!-- Modal de carga de feeds -->
<div id="feedLoadingModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <h3>Cargando datos...</h3>
        <div style="margin: 30px 0;">
            <div class="spinner"></div>
        </div>
        <p id="feedLoadingMessage" style="color: #718096; margin-top: 20px;">
            Comprobando estado de los feeds RSS
        </p>
        <div id="feedProgress" style="margin-top: 20px; font-size: 18px; font-weight: 600; color: #667eea;">
            <span id="feedProgressText">0 / 0</span>
        </div>
    </div>
</div>

<style>
.spinner {
    border: 4px solid #f3f4f6;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            // Mostrar modal de carga
            const modal = document.getElementById('feedLoadingModal');
            if (modal) {
                modal.style.display = 'block';
            }

            // Enviar login por POST normal para establecer la sesión
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Si el login es exitoso, la página se recargará automáticamente
                // y mostrará el panel de usuario con el progreso de feeds
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                if (modal) {
                    modal.style.display = 'none';
                }
                alert('Error al iniciar sesión');
            });
        });
    }
});
</script>
