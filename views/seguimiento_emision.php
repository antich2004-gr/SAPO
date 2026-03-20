<?php
// views/seguimiento_emision.php - Seguimiento de emisiones del mes (solo admin)
?>

<div class="card">
    <div class="nav-buttons">
        <h2 style="margin:0;">📊 Seguimiento Emisión</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <a href="?" class="btn btn-secondary" style="margin-right: 10px;"><span class="btn-icon">⚙️</span> Panel Admin</a>
            <a href="?page=help" class="btn btn-secondary" style="margin-right: 10px;"><span class="btn-icon">📖</span> Ayuda</a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
            </form>
        </div>
    </div>

    <div class="section" style="margin-bottom: 30px; text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; margin-bottom: 20px;">🚧</div>
        <h3 style="color: #4a5568; margin-bottom: 10px;">En construcción</h3>
        <p style="color: #718096;">Esta sección estará disponible próximamente.</p>
    </div>
</div>
