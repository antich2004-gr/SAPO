<?php
// views/categories.php - Vista del Gestor de Categorías
// Requiere: autenticación y includes

if (!isset($username)) {
    die('Acceso no autorizado');
}

// Obtener todas las categorías con estadísticas
$categoriesWithStats = getAllCategoriesWithStats($username);

// Calcular estadísticas globales
$totalCategories = count($categoriesWithStats);
$totalFiles = 0;
$totalSize = 0;
$activeCount = 0;
$warningCount = 0;
$inactiveCount = 0;
$emptyCount = 0;

foreach ($categoriesWithStats as $cat) {
    $totalFiles += $cat['files'];
    $totalSize += $cat['size'];

    switch ($cat['status']) {
        case 'active':
            $activeCount++;
            break;
        case 'warning':
            $warningCount++;
            break;
        case 'inactive':
            $inactiveCount++;
            break;
        case 'empty':
            $emptyCount++;
            break;
    }
}

// Obtener configuración para enlaces
$config = getConfig();
$radiobotUrl = $config['radiobot_url'] ?? 'https://radiobot.radioslibres.info';
?>

<style>
        .categories-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .stat-label {
            font-size: 0.85em;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
        }

        .status-badges {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            background: rgba(255, 255, 255, 0.3);
        }

        .actions-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .categories-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .category-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s;
        }

        .category-item:hover {
            background: #f8f9fa;
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .category-name {
            font-size: 1.3em;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333 !important;
            background: transparent !important;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-active { background: #28a745; }
        .status-warning { background: #ffc107; }
        .status-inactive { background: #dc3545; }
        .status-empty { background: #6c757d; }

        .category-info {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .category-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        /* Modales */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .warning-box.critical {
            background: #f8d7da;
            border-color: #dc3545;
        }

        .warning-title {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .checkbox-group {
            margin: 15px 0;
        }

        .checkbox-group label {
            display: flex;
            align-items: start;
            gap: 10px;
            cursor: pointer;
            padding: 10px;
            border-radius: 5px;
            transition: background 0.2s;
        }

        .checkbox-group label:hover {
            background: #f0f0f0;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .changes-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .changes-list li {
            margin: 5px 0;
        }

        .help-link {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }

        .help-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .category-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .category-info {
                flex-direction: column;
                gap: 5px;
            }

            .stats-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="categories-container">
        <!-- Encabezado -->
        <div class="page-header">
            <h1>🗂️ Gestor de Categorías</h1>
            <p>Administra las carpetas de podcasts y su contenido</p>

            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-label">Total de Categorías</div>
                    <div class="stat-value"><?php echo $totalCategories; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total de Archivos</div>
                    <div class="stat-value"><?php echo number_format($totalFiles); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Espacio Total</div>
                    <div class="stat-value"><?php echo formatBytes($totalSize); ?></div>
                </div>
            </div>

            <div class="status-badges">
                <span class="status-badge">🟢 Activas: <?php echo $activeCount; ?></span>
                <span class="status-badge">🟠 Advertencia: <?php echo $warningCount; ?></span>
                <span class="status-badge">🔴 Inactivas: <?php echo $inactiveCount; ?></span>
                <span class="status-badge">⚪ Vacías: <?php echo $emptyCount; ?></span>
            </div>
        </div>

        <!-- Barra de acciones -->
        <div class="actions-bar">
            <a href="index.php" class="btn btn-secondary">← Volver a Podcasts</a>
            <button class="btn btn-primary" onclick="showNewCategoryModal()">+ Nueva Categoría</button>
            <?php if ($emptyCount > 0): ?>
                <button class="btn btn-danger" onclick="showCleanEmptyModal()">🧹 Limpiar Vacías (<?php echo $emptyCount; ?>)</button>
            <?php endif; ?>
        </div>

        <!-- Lista de categorías -->
        <div class="categories-table">
            <?php if (empty($categoriesWithStats)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📂</div>
                    <h3>No hay categorías</h3>
                    <p>Crea tu primera categoría para organizar tus podcasts</p>
                    <button class="btn btn-primary" onclick="showNewCategoryModal()">+ Crear Categoría</button>
                </div>
            <?php else: ?>
                <?php foreach ($categoriesWithStats as $cat): ?>
                    <div class="category-item">
                        <div class="category-header">
                            <div class="category-name">
                                <span class="status-indicator status-<?php echo $cat['status']; ?>"></span>
                                📁 <?php echo htmlspecialchars($cat['name']); ?>
                            </div>
                            <span class="time-ago"><?php echo timeAgo($cat['last_download']); ?></span>
                        </div>

                        <div class="category-info">
                            <span><strong><?php echo $cat['podcasts']; ?></strong> podcast<?php echo $cat['podcasts'] != 1 ? 's' : ''; ?></span>
                            <span><strong><?php echo $cat['files']; ?></strong> archivo<?php echo $cat['files'] != 1 ? 's' : ''; ?></span>
                            <span><strong><?php echo formatBytes($cat['size']); ?></strong></span>
                        </div>

                        <div class="category-actions">
                            <?php if ($cat['files'] > 0): ?>
                                <button class="btn btn-sm btn-secondary" onclick="viewCategoryFiles('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">👁️ Ver Archivos</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-primary" onclick="showRenameModal('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">✏️ Renombrar</button>
                            <?php if ($totalCategories > 1): ?>
                                <button class="btn btn-sm btn-secondary" onclick="showMergeModal('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">🔀 Fusionar</button>
                            <?php endif; ?>
                            <?php if ($cat['podcasts'] == 0 && $cat['files'] == 0): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteCategory('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">🗑️ Eliminar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Ayuda -->
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <h3>ℹ️ Información Importante</h3>
            <p><strong>Conexión con Radiobot/AzuraCast:</strong> Las categorías son carpetas físicas en tu servidor. Si cambias el nombre de una categoría o fusionas categorías, <strong>debes actualizar manualmente las playlists en Radiobot</strong> para que apunten a las nuevas rutas.</p>
            <p><a href="<?php echo htmlspecialchars($radiobotUrl); ?>" target="_blank" class="help-link">🔗 Abrir Radiobot</a></p>
        </div>
    </div>

    <!-- Modal: Renombrar Categoría -->
    <div id="modal-rename" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Renombrar Categoría</div>

            <div class="form-group">
                <label>Nombre actual:</label>
                <input type="text" id="rename-old-name" readonly style="background: #f0f0f0;">
            </div>

            <div class="form-group">
                <label>Nuevo nombre:</label>
                <input type="text" id="rename-new-name" placeholder="Ingresa el nuevo nombre">
            </div>

            <div class="warning-box critical">
                <div class="warning-title">🔴 CRÍTICO - ACCIÓN REQUERIDA EN RADIOBOT</div>
                <p>La carpeta cambiará de nombre en el servidor.</p>

                <p><strong>DEBES actualizar TODAS las playlists en Radiobot que usen esta carpeta:</strong></p>
                <ol>
                    <li>Accede a Radiobot/AzuraCast</li>
                    <li>Busca playlists que usen esta categoría</li>
                    <li>Edita cada playlist</li>
                    <li>Cambia la ruta de carpeta al nuevo nombre</li>
                    <li>Guarda los cambios en cada playlist</li>
                </ol>

                <p><strong>⚠️ Si no actualizas Radiobot:</strong> Las playlists quedarán VACÍAS y no se reproducirán los podcasts.</p>
            </div>

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" id="rename-check-1">
                    <span>He anotado las playlists que debo actualizar</span>
                </label>
                <label>
                    <input type="checkbox" id="rename-check-2">
                    <span>Entiendo que debo cambiar las rutas en Radiobot</span>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('modal-rename')">❌ Cancelar</button>
                <button class="btn btn-success" onclick="confirmRename()">✅ Confirmar Renombrado</button>
            </div>
        </div>
    </div>

    <!-- Modal: Fusionar Categorías -->
    <div id="modal-merge" class="modal">
        <div class="modal-content">
            <div class="modal-header">🔀 Fusionar Categorías</div>

            <div class="form-group">
                <label>Categoría origen (se eliminará):</label>
                <input type="text" id="merge-source" readonly style="background: #f0f0f0;">
            </div>

            <div class="form-group">
                <label>Categoría destino (recibirá los archivos):</label>
                <select id="merge-target">
                    <option value="">Selecciona una categoría...</option>
                    <?php foreach ($categoriesWithStats as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['files']; ?> archivos)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="warning-box critical">
                <div class="warning-title">🔴 CRÍTICO - ACCIONES REQUERIDAS EN RADIOBOT</div>

                <p><strong>OPCIÓN A - Mantener playlist separada:</strong></p>
                <ol>
                    <li>Ve a la playlist de origen en Radiobot</li>
                    <li>Cambia la carpeta origen a la categoría destino</li>
                    <li>Añade un filtro para reproducir solo los archivos deseados</li>
                </ol>

                <p><strong>OPCIÓN B - Fusionar en Radiobot también:</strong></p>
                <ol>
                    <li>Elimina la playlist de origen en Radiobot</li>
                    <li>La playlist destino reproducirá todos los archivos</li>
                </ol>

                <p><strong>⚠️ Si no haces NADA:</strong> La playlist de origen quedará VACÍA y aparecerán errores.</p>
            </div>

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" id="merge-check-1">
                    <span>Entiendo que debo actualizar las playlists</span>
                </label>
                <label>
                    <input type="checkbox" id="merge-check-2">
                    <span>Sé qué opción (A o B) voy a usar en Radiobot</span>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('modal-merge')">❌ Cancelar</button>
                <button class="btn btn-danger" onclick="confirmMerge()">✅ Confirmar Fusión</button>
            </div>
        </div>
    </div>

    <!-- Modal: Ver Archivos -->
    <div id="modal-files" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">📁 Archivos de Categoría</div>
            <div id="files-content"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('modal-files')">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        // CSRF Token para protección contra ataques CSRF
        const csrfToken = '<?php echo generateCSRFToken(); ?>';

        /**
         * Helper para enviar acciones POST con protección CSRF
         * @param {string} action - La acción a ejecutar
         * @param {object} params - Parámetros adicionales
         */
        function submitPostAction(action, params) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';

            // Agregar CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            // Agregar acción
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            form.appendChild(actionInput);

            // Agregar parámetros
            for (const key in params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        }

        function showRenameModal(categoryName) {
            document.getElementById('rename-old-name').value = categoryName;
            document.getElementById('rename-new-name').value = '';
            document.getElementById('rename-check-1').checked = false;
            document.getElementById('rename-check-2').checked = false;
            showModal('modal-rename');
        }

        function showMergeModal(categoryName) {
            document.getElementById('merge-source').value = categoryName;
            document.getElementById('merge-target').value = '';
            document.getElementById('merge-check-1').checked = false;
            document.getElementById('merge-check-2').checked = false;

            // Deshabilitar la opción de la categoría origen en el select
            const select = document.getElementById('merge-target');
            for (let option of select.options) {
                option.disabled = (option.value === categoryName);
            }

            showModal('modal-merge');
        }

        function viewCategoryFiles(categoryName) {
            document.getElementById('files-content').innerHTML = '<p>Cargando archivos...</p>';
            showModal('modal-files');

            fetch('index.php?action=get_category_files&category=' + encodeURIComponent(categoryName))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<h3>' + categoryName + '</h3>';
                        html += '<p><strong>' + data.files.length + '</strong> archivos</p>';
                        html += '<div style="max-height: 400px; overflow-y: auto;">';
                        html += '<table style="width: 100%; border-collapse: collapse;">';
                        data.files.forEach(file => {
                            html += '<tr style="border-bottom: 1px solid #e0e0e0;">';
                            html += '<td style="padding: 10px;">' + file.name + '</td>';
                            html += '<td style="padding: 10px; text-align: right; color: #666;">' + file.size + '</td>';
                            html += '</tr>';
                        });
                        html += '</table></div>';
                        document.getElementById('files-content').innerHTML = html;
                    } else {
                        document.getElementById('files-content').innerHTML = '<p>Error: ' + data.error + '</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('files-content').innerHTML = '<p>Error al cargar archivos</p>';
                });
        }

        function confirmRename() {
            const check1 = document.getElementById('rename-check-1').checked;
            const check2 = document.getElementById('rename-check-2').checked;

            if (!check1 || !check2) {
                alert('⚠️ Debes marcar ambas casillas para confirmar que entiendes los cambios necesarios en Radiobot.');
                return;
            }

            const oldName = document.getElementById('rename-old-name').value;
            const newName = document.getElementById('rename-new-name').value.trim();

            if (!newName) {
                alert('Debes ingresar un nuevo nombre');
                return;
            }

            if (confirm('¿Estás seguro de renombrar "' + oldName + '" a "' + newName + '"?')) {
                // Enviar POST con CSRF token (previene ataques CSRF)
                submitPostAction('rename_category', {
                    'old_name': oldName,
                    'new_name': newName
                });
            }
        }

        function confirmMerge() {
            const check1 = document.getElementById('merge-check-1').checked;
            const check2 = document.getElementById('merge-check-2').checked;

            if (!check1 || !check2) {
                alert('⚠️ Debes marcar ambas casillas para confirmar que entiendes los cambios necesarios en Radiobot.');
                return;
            }

            const source = document.getElementById('merge-source').value;
            const target = document.getElementById('merge-target').value;

            if (!target) {
                alert('Debes seleccionar una categoría destino');
                return;
            }

            if (confirm('¿Estás seguro de fusionar "' + source + '" en "' + target + '"? Esta acción NO se puede deshacer.')) {
                // Enviar POST con CSRF token (previene ataques CSRF)
                submitPostAction('merge_categories', {
                    'source': source,
                    'target': target
                });
            }
        }

        function deleteCategory(categoryName) {
            if (confirm('¿Estás seguro de eliminar la categoría "' + categoryName + '"?')) {
                // Enviar POST con CSRF token (previene ataques CSRF)
                submitPostAction('delete_category', {
                    'category': categoryName
                });
            }
        }

        function showModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Cerrar modal al hacer clic fuera
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
