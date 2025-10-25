<?php
// views/user.php - Interfaz de usuario regular
if (!isset($_SESSION['categories_auto_imported'])) {
    importCategoriesFromServerList($_SESSION['username']);
    $_SESSION['categories_auto_imported'] = true;
}

$userCategories = getUserCategories($_SESSION['username']);
$podcasts = readServerList($_SESSION['username']);
$caducidades = readCaducidades($_SESSION['username']);

// Agregar índice original a cada podcast
$podcastsWithIndex = array();
foreach ($podcasts as $originalIndex => $podcast) {
    $podcast['original_index'] = $originalIndex;
    $podcastsWithIndex[] = $podcast;
}

// Ordenar podcasts alfabéticamente por nombre
usort($podcastsWithIndex, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

$podcasts = $podcastsWithIndex;

// Detectar si estamos editando
$isEditing = isset($_GET['edit']) && is_numeric($_GET['edit']);
$editIndex = $isEditing ? intval($_GET['edit']) : null;
?>

<div class="card">
    <div class="nav-buttons">
        <h2>Mis Podcasts</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary">Cerrar Sesion</button>
            </form>
        </div>
    </div>
    
    <?php if ($isEditing && $editIndex !== null): ?>
        <!-- FORMULARIO DE EDICIÓN -->
        <?php 
        $podcastsOriginal = readServerList($_SESSION['username']);
        if ($editIndex >= 0 && $editIndex < count($podcastsOriginal)) {
            $podcast = $podcastsOriginal[$editIndex];
            $podcastCaducidad = $caducidades[$podcast['name']] ?? 30;
        ?>
        
        <div style="margin-top: 30px;">
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary" style="margin-bottom: 20px;">Volver al listado</a>
            
            <h3 style="margin-bottom: 20px;">Editar Podcast</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="edit_podcast">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="index" value="<?php echo $editIndex; ?>">
                
                <div class="form-group">
                    <label>URL del RSS:</label>
                    <input type="text" name="url" value="<?php echo htmlEsc($podcast['url']); ?>" required maxlength="500">
                </div>
                
                <div class="form-group">
                    <label>Categoria:</label>
                    <?php if (!empty($userCategories)): ?>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <select name="category" id="edit_category_select" onchange="toggleEditInlineCategory()" style="flex: 1;">
                                <option value="">-- Selecciona una categoria --</option>
                                <?php foreach ($userCategories as $cat): 
                                    $inUse = isCategoryInUse($_SESSION['username'], $cat);
                                ?>
                                    <option value="<?php echo htmlEsc($cat); ?>" <?php echo $podcast['category'] === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlEsc($cat); ?><?php echo !$inUse ? ' (sin usar)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom">✏️ Escribir nueva categoria...</option>
                            </select>
                            
                            <button type="button" class="btn btn-secondary" onclick="showCategoryManager('edit')" style="white-space: nowrap;">Gestionar</button>
                        </div>
                        <div id="edit_custom_category_input" style="display: none; margin-top: 10px;">
                            <input type="text" name="custom_category" placeholder="Escribe una nueva categoria" maxlength="50">
                        </div>
                    <?php else: ?>
                        <input type="text" name="custom_category" value="<?php echo htmlEsc($podcast['category']); ?>" required placeholder="Escribe el nombre de la categoria" maxlength="50">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Nombre del Podcast:</label>
                    <input type="text" name="name" value="<?php echo htmlEsc($podcast['name']); ?>" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label>Días de caducidad:</label>
                    <input type="number" name="caducidad" value="<?php echo $podcastCaducidad; ?>" min="1" max="365" required>
                    <small style="color: #718096;">Los archivos se eliminarán después de X días sin descargas nuevas (por defecto: 30 días)</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar Cambios</button>
                    <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">Cancelar</a>
                </div>
            </form>
        </div>
        
        <script>
            function toggleEditInlineCategory() {
                const select = document.getElementById('edit_category_select');
                const customInput = document.getElementById('edit_custom_category_input');
                if (select && customInput) {
                    if (select.value === 'custom') {
                        customInput.style.display = 'block';
                    } else {
                        customInput.style.display = 'none';
                    }
                }
            }
        </script>
        
        <?php 
        } else {
        ?>
            <div class="alert alert-error">Podcast no encontrado</div>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Volver al listado</a>
        <?php 
        }
        ?>
        
    <?php else: ?>
        <!-- LISTADO CON PESTAÑAS -->
        
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" data-tab="podcasts" onclick="switchTab('podcasts')">Mis Podcasts</button>
                <button class="tab-button" data-tab="importar" onclick="switchTab('importar')">Importar/Exportar</button>
                <button class="tab-button" data-tab="descargas" onclick="switchTab('descargas')">Descargas</button>
            </div>
            
            <div class="tabs-content">
                <!-- PESTAÑA 1: MIS PODCASTS -->
                <div id="tab-podcasts" class="tab-panel active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 10px;">
                        <h3 style="margin: 0;">Podcasts Suscritos</h3>
                        <button type="button" class="btn btn-success" onclick="showAddPodcastModal()">
                            ➕ Agregar Nuevo Podcast
                        </button>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="action" value="refresh_feeds">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-warning">Actualizar estado</button>
                        </form>
                        
                        <?php if (!empty($userCategories)): ?>
                            <div style="display: flex; gap: 10px; align-items: center; flex: 1;">
                                <label for="filter_category" style="margin: 0; white-space: nowrap;">Filtrar por:</label>
                                <select id="filter_category" onchange="filterByCategory()" style="max-width: 200px;">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($userCategories as $cat): 
                                        $countInCategory = count(array_filter($podcasts, function($p) use ($cat) {
                                            return $p['category'] === $cat;
                                        }));
                                    ?>
                                        <option value="<?php echo htmlEsc($cat); ?>"><?php echo htmlEsc($cat); ?> (<?php echo $countInCategory; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="button" class="btn btn-secondary" onclick="toggleGroupView()" id="toggleViewBtn">
                                    <span id="viewModeText">Agrupar por categoría</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($podcasts)): ?>
                        <p style="color: #718096; margin-top: 20px;">No hay podcasts suscritos aún.</p>
                    <?php else: ?>
                        <!-- Vista Normal (Alfabética) -->
                        <div id="normal-view" class="podcast-list">
                            <?php foreach ($podcasts as $index => $podcast): 
                                $podcastCaducidad = $caducidades[$podcast['name']] ?? 30;
                            ?>
                                <div class="podcast-item" data-category="<?php echo htmlEsc($podcast['category']); ?>">
                                    <div class="podcast-info">
                                        <strong><?php echo htmlEsc($podcast['name']); ?></strong>
                                        <small>Categoria: <?php echo htmlEsc($podcast['category']); ?> | Caducidad: <?php echo $podcastCaducidad; ?> días</small>
                                        <small><?php echo htmlEsc($podcast['url']); ?></small>
                                        
                                        <?php
                                        $feedInfo = getCachedFeedInfo($podcast['url']);
                                        $statusInfo = formatFeedStatus($feedInfo['timestamp']);
                                        ?>
                                        
                                        <div class="last-episode <?php echo $statusInfo['class']; ?>">
                                            <?php echo $statusInfo['status']; ?> - Último episodio: <?php echo $statusInfo['date']; ?> (hace <?php echo $statusInfo['days']; ?> días)
                                            <?php if ($feedInfo['cached']): 
                                                $cacheHours = floor($feedInfo['cache_age'] / 3600);
                                            ?>
                                                <span class="cache-indicator">(datos de hace <?php echo $cacheHours; ?>h)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="podcast-actions">
                                        <a href="?edit=<?php echo htmlEsc($podcast['original_index']); ?>" class="btn btn-warning">Editar</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_podcast">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="index" value="<?php echo htmlEsc($podcast['original_index']); ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Vista Agrupada por Categorías -->
                        <div id="grouped-view" style="display: none;">
                            <?php 
                            // Agrupar podcasts por categoría
                            $podcastsByCategory = [];
                            foreach ($podcasts as $podcast) {
                                $cat = $podcast['category'];
                                if (!isset($podcastsByCategory[$cat])) {
                                    $podcastsByCategory[$cat] = [];
                                }
                                $podcastsByCategory[$cat][] = $podcast;
                            }
                            
                            // Ordenar categorías alfabéticamente
                            ksort($podcastsByCategory);
                            
                            foreach ($podcastsByCategory as $category => $categoryPodcasts):
                                // Ordenar podcasts dentro de la categoría alfabéticamente
                                usort($categoryPodcasts, function($a, $b) {
                                    return strcasecmp($a['name'], $b['name']);
                                });
                            ?>
                                <div class="category-group" data-category="<?php echo htmlEsc($category); ?>">
                                    <div class="category-header">
                                        <h4><?php echo htmlEsc($category); ?></h4>
                                        <span class="category-count"><?php echo count($categoryPodcasts); ?> podcast<?php echo count($categoryPodcasts) > 1 ? 's' : ''; ?></span>
                                    </div>
                                    
                                    <div class="podcast-list">
                                        <?php foreach ($categoryPodcasts as $podcast): 
                                            $podcastCaducidad = $caducidades[$podcast['name']] ?? 30;
                                        ?>
                                            <div class="podcast-item">
                                                <div class="podcast-info">
                                                    <strong><?php echo htmlEsc($podcast['name']); ?></strong>
                                                    <small>Caducidad: <?php echo $podcastCaducidad; ?> días</small>
                                                    <small><?php echo htmlEsc($podcast['url']); ?></small>
                                                    
                                                    <?php
                                                    $feedInfo = getCachedFeedInfo($podcast['url']);
                                                    $statusInfo = formatFeedStatus($feedInfo['timestamp']);
                                                    ?>
                                                    
                                                    <div class="last-episode <?php echo $statusInfo['class']; ?>">
                                                        <?php echo $statusInfo['status']; ?> - Último episodio: <?php echo $statusInfo['date']; ?> (hace <?php echo $statusInfo['days']; ?> días)
                                                        <?php if ($feedInfo['cached']): 
                                                            $cacheHours = floor($feedInfo['cache_age'] / 3600);
                                                        ?>
                                                            <span class="cache-indicator">(datos de hace <?php echo $cacheHours; ?>h)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="podcast-actions">
                                                    <a href="?edit=<?php echo htmlEsc($podcast['original_index']); ?>" class="btn btn-warning">Editar</a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_podcast">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="index" value="<?php echo htmlEsc($podcast['original_index']); ?>">
                                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')">Eliminar</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- PESTAÑA 2: IMPORTAR/EXPORTAR -->
                <div id="tab-importar" class="tab-panel">
                    <p style="color: #718096; margin-bottom: 15px;">Importa podcasts desde un archivo serverlist.txt o exporta tu lista actual.</p>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px;">Importar podcasts</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_serverlist">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="file-input-wrapper">
                            <label class="file-label" for="serverlist_file">
                                Seleccionar archivo...
                            </label>
                            <input type="file" name="serverlist_file" id="serverlist_file" accept=".txt" required onchange="showFileName(this)">
                        </div>
                        <span class="selected-file" id="fileName"></span>
                        <button type="submit" class="btn btn-success">Importar</button>
                    </form>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px;">Exportar podcasts</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="export_serverlist">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn btn-primary">Descargar mi serverlist.txt</button>
                    </form>
                </div>
                
                <!-- PESTAÑA 3: DESCARGAS -->
                <div id="tab-descargas" class="tab-panel">
                    <h4>Ejecutar Descargas</h4>
                    <p style="color: #718096; margin-bottom: 20px;">Descarga los nuevos episodios de todos tus podcasts suscritos en el servidor.</p>
                    
                    <button type="button" class="btn btn-info" style="font-size: 16px; padding: 15px 30px;" onclick="executePodgetViaAjax();">
                        Ejecutar descargas para <?php echo htmlEsc($_SESSION['station_name']); ?>
                    </button>
                    
                    <div id="podget-status" style="margin-top: 20px;"></div>
                    
                    <p style="color: #718096; margin-top: 20px; font-size: 14px;">
                    </p>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
</div>

<!-- MODAL DE AGREGAR PODCAST -->
<div id="addPodcastModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeAddPodcastModal()">&times;</span>
        <h3>Agregar Nuevo Podcast</h3>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" value="add_podcast">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label>URL del RSS:</label>
                <input type="text" name="url" id="podcast_url" required placeholder="https://ejemplo.com/podcast/rss" maxlength="500">
            </div>
            
            <div class="form-group">
                <label>Categoría:</label>
                <?php if (!empty($userCategories)): ?>
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <select name="category" id="modal_category_select" onchange="toggleModalCustomCategory()" style="flex: 1;">
                            <option value="">-- Selecciona una categoría --</option>
                            <?php foreach ($userCategories as $cat): ?>
                                <option value="<?php echo htmlEsc($cat); ?>"><?php echo htmlEsc($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="custom">✏️ Escribir nueva categoría...</option>
                        </select>
                        <button type="button" class="btn btn-secondary" onclick="showCategoryManager('modal')" style="white-space: nowrap;">Gestionar</button>
                    </div>
                    <div id="modal_custom_category_input" style="display: none; margin-top: 10px;">
                        <input type="text" name="custom_category" placeholder="Escribe una nueva categoría" maxlength="50">
                    </div>
                <?php else: ?>
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <input type="text" name="custom_category" required placeholder="Escribe el nombre de la categoría" maxlength="50" style="flex: 1;">
                        <button type="button" class="btn btn-secondary" onclick="showCategoryManager('modal')" style="white-space: nowrap;">Gestionar</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Nombre del Podcast:</label>
                <input type="text" name="name" id="podcast_name" required placeholder="Mi_Podcast" maxlength="100">
                <small style="color: #718096;">Usa guiones bajos en lugar de espacios</small>
            </div>
            
            <div class="form-group">
                <label>Días de caducidad:</label>
                <input type="number" name="caducidad" id="podcast_caducidad" value="30" min="1" max="365" required>
                <small style="color: #718096;">Los archivos se eliminarán después de X días sin descargas nuevas (por defecto: 30 días)</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Agregar Podcast</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddPodcastModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE GESTIÓN DE CATEGORÍAS -->
<div id="categoryModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close" onclick="closeCategoryManager()">&times;</span>
        <h3>Gestión de Categorías</h3>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" value="add_category">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label>Nueva Categoría:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="category_name" id="new_category_input" required placeholder="Ej: Deportes, Noticias..." maxlength="50" style="flex: 1;">
                    <button type="submit" class="btn btn-success">Añadir</button>
                </div>
            </div>
        </form>
        
        <div style="margin-top: 30px;">
            <h4>Categorías Existentes:</h4>
            <?php if (!empty($userCategories)): ?>
                <div class="category-list" style="margin-top: 15px;">
                    <?php foreach ($userCategories as $cat): 
                        $inUse = isCategoryInUse($_SESSION['username'], $cat);
                    ?>
                        <div class="category-item">
                            <span><?php echo htmlEsc($cat); ?></span>
                            <?php if ($inUse): ?>
                                <span class="badge-in-use">En uso</span>
                            <?php else: ?>
                                <button type="button" class="btn-delete-small" onclick="deleteCategory('<?php echo htmlEsc($cat); ?>')" title="Eliminar">×</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #718096; margin-top: 15px;">No hay categorías creadas aún.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.category-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: #f7fafc;
    border-radius: 6px;
}

.category-item span {
    font-size: 14px;
    color: #2d3748;
}

.badge-in-use {
    background: #bee3f8;
    color: #2c5282;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.btn-delete-small {
    background: #f56565;
    color: white;
    border: none;
    border-radius: 4px;
    width: 24px;
    height: 24px;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    transition: all 0.3s;
}

.btn-delete-small:hover {
    background: #e53e3e;
    transform: scale(1.1);
}

/* Vista agrupada por categorías */
.category-group {
    margin-bottom: 30px;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    margin-bottom: 15px;
}

.category-header h4 {
    margin: 0;
    color: white;
    font-size: 18px;
    font-weight: 600;
}

.category-count {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}

.category-group .podcast-list {
    margin-left: 20px;
    border-left: 3px solid #e2e8f0;
    padding-left: 15px;
}

</style>
