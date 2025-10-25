<?php
// views/edit_podcast_form.php - Formulario de edición inline
$editIndex = $_GET['edit'] ?? null;
$podcasts = readServerList($_SESSION['username']);

if ($editIndex === null || $editIndex < 0 || $editIndex >= count($podcasts)) {
    return;
}

$podcast = $podcasts[$editIndex];
$userCategories = getUserCategories($_SESSION['username']);
?>

<div class="card">
    <div class="nav-buttons">
        <h2>Editar Podcast</h2>
        <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Volver al listado</a>
    </div>
    
    <form method="POST" style="margin-top: 20px;">
        <input type="hidden" name="action" value="edit_podcast">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="index" value="<?php echo $editIndex; ?>">
        
        <div class="form-group">
            <label>URL del RSS:</label>
            <input type="text" name="url" value="<?php echo htmlEsc($podcast['url']); ?>" required maxlength="500" autocomplete="off">
            <small style="color: #718096;">La URL RSS del podcast</small>
        </div>
        
        <div class="form-group">
            <label>Categoria:</label>
            <?php if (!empty($userCategories)): ?>
                <select name="category" id="edit_category_select" onchange="toggleEditInlineCategory()">
                    <option value="">-- Selecciona una categoria --</option>
                    <?php foreach ($userCategories as $cat): ?>
                        <option value="<?php echo htmlEsc($cat); ?>" <?php echo $podcast['category'] === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlEsc($cat); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="custom">Escribir nueva categoria...</option>
                </select>
                <div id="edit_custom_category_input" style="display: none; margin-top: 10px;">
                    <input type="text" name="custom_category" placeholder="Escribe una nueva categoria" maxlength="50" autocomplete="off">
                </div>
                <script>
                    // Mostrar u ocultar campo personalizado
                    function toggleEditInlineCategory() {
                        const select = document.getElementById('edit_category_select');
                        const customInput = document.getElementById('edit_custom_category_input');
                        if (select.value === 'custom') {
                            customInput.style.display = 'block';
                        } else {
                            customInput.style.display = 'none';
                        }
                    }
                </script>
            <?php else: ?>
                <input type="text" name="custom_category" value="<?php echo htmlEsc($podcast['category']); ?>" required placeholder="Escribe el nombre de la categoria" maxlength="50" autocomplete="off">
                <small style="color: #718096;">Crea algunas categorias para poder seleccionarlas.</small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label>Nombre del Podcast:</label>
            <input type="text" name="name" value="<?php echo htmlEsc($podcast['name']); ?>" required maxlength="100" autocomplete="off">
            <small style="color: #718096;">El nombre que aparecera en el listado</small>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar Cambios</button>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center;">Cancelar</a>
        </div>
    </form>
</div>
