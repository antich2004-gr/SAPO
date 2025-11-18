<?php
// views/user.php - Interfaz de usuario regular
$userCategories = getUserCategories($_SESSION['username']);
$podcasts = readServerList($_SESSION['username']);
$caducidades = readCaducidades($_SESSION['username']);
$duraciones = readDuraciones($_SESSION['username']);
$duracionesOptions = getDuracionesOptions();
$defaultCaducidad = getDefaultCaducidad($_SESSION['username']);

// Auto-detectar categorías de los podcasts existentes si no hay categorías guardadas
if (empty($userCategories) && !empty($podcasts)) {
    $categoriesFromPodcasts = array_unique(array_column($podcasts, 'category'));
    // Filtrar "Sin_categoria" si hay otras categorías
    $categoriesFromPodcasts = array_filter($categoriesFromPodcasts, function($cat) {
        return $cat !== 'Sin_categoria';
    });
    if (!empty($categoriesFromPodcasts)) {
        $userCategories = array_values($categoriesFromPodcasts);
    }
}

// Ordenar podcasts alfabéticamente por nombre
usort($podcasts, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

// Re-indexar el array para asegurar índices consecutivos desde 0
$podcasts = array_values($podcasts);

// Paginación
$itemsPerPage = 25;
$currentPage = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$totalPodcasts = count($podcasts);
$totalPages = ceil($totalPodcasts / $itemsPerPage);
$currentPage = min($currentPage, max(1, $totalPages)); // Asegurar que la página existe
$offset = ($currentPage - 1) * $itemsPerPage;
$podcastsPaginated = array_slice($podcasts, $offset, $itemsPerPage);

// Detectar si estamos editando
$isEditing = isset($_GET['edit']) && is_numeric($_GET['edit']);
$editIndex = $isEditing ? intval($_GET['edit']) : null;
?>

<div class="card">
    <div class="nav-buttons">
        <h2>Mis Podcasts</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <a href="?page=parrilla" class="btn btn-primary" style="margin-right: 10px;"><span class="btn-icon">📺</span> Parrilla</a>
            <a href="?page=help" class="btn btn-secondary" style="margin-right: 10px;"><span class="btn-icon">📖</span> Ayuda</a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
            </form>
        </div>
    </div>
    
    <?php
    // Preparar datos de todos los podcasts en JSON para JavaScript
    // IMPORTANTE: Reutilizar $podcasts ordenado para mantener consistencia con los índices del HTML
    // Esto elimina la doble lectura del archivo y asegura que los índices coincidan
    $podcastsData = [];
    foreach ($podcasts as $index => $podcast) {
        $feedInfo = getCachedFeedInfo($podcast['url']);
        $statusInfo = formatFeedStatus($feedInfo['timestamp']);

        $podcastsData[] = [
            'index' => $index,
            'url' => $podcast['url'],
            'name' => displayName($podcast['name']),
            'category' => $podcast['category'],
            'caducidad' => $caducidades[$podcast['name']] ?? $defaultCaducidad,
            'duracion' => $duraciones[$podcast['name']] ?? '',
            'paused' => isset($podcast['paused']) ? $podcast['paused'] : false,
            'feedInfo' => [
                'timestamp' => $feedInfo['timestamp'],
                'cached' => $feedInfo['cached'],
                'cache_age' => $feedInfo['cache_age']
            ],
            'statusInfo' => [
                'class' => $statusInfo['class'],
                'status' => $statusInfo['status'],
                'icon' => $statusInfo['icon'],
                'date' => $statusInfo['date'] ?? '',
                'days' => $statusInfo['days'] ?? 0
            ]
        ];
    }
    ?>
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
                            <span class="btn-icon">➕</span> Agregar Nuevo Podcast
                        </button>
                    </div>
                    

                    <!-- Campo de búsqueda -->
                    <div style="margin-bottom: 20px;">
                        <input type="text" id="search-podcasts" placeholder="🔍 Buscar por nombre de podcast..."
                               style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;"
                               onkeyup="searchPodcasts()">
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-warning" onclick="refreshFeedsWithProgress()" style="margin-left: 0;">
                            🔄 Actualizar estado de feeds
                        </button>

                        <form method="POST" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                            <input type="hidden" name="action" value="set_default_caducidad">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <label style="margin: 0; white-space: nowrap; font-size: 14px;">Caducidad por defecto:</label>
                            <input type="number" name="default_caducidad" value="<?php echo htmlEsc($defaultCaducidad); ?>"
                                   min="1" max="365" required style="width: 70px; padding: 8px;">
                            <span style="font-size: 14px;">días</span>
                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">💾 Guardar</button>
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
                                        <option value="<?php echo htmlEsc($cat); ?>"><?php echo htmlEsc(displayName($cat)); ?> (<?php echo htmlEsc($countInCategory); ?>)</option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="button" class="btn btn-secondary" onclick="toggleGroupView()" id="toggleViewBtn">
                                    <span id="viewModeText">Agrupar por categoría</span>
                                </button>

                                <button type="button" class="btn btn-primary" onclick="openCategoryManager()" style="white-space: nowrap;">
                                    🗂️ Gestionar Categorías
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($podcasts)): ?>
                        <p style="color: #718096; margin-top: 20px;">No hay podcasts suscritos aún.</p>
                    <?php else: ?>
                        <!-- Contenedor de resultados de búsqueda -->
                        <div id="search-results" style="display: none;">
                            <div class="search-info" style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #667eea;">
                                <span id="search-count" style="font-weight: 600; color: #667eea;"></span>
                            </div>
                            <div id="search-results-list" class="podcast-list"></div>
                        </div>

                        <!-- Vista Normal (Alfabética) -->
                        <div id="normal-view" class="podcast-list">
                            <?php foreach ($podcastsPaginated as $index => $podcast):
                                // Calcular índice global considerando paginación
                                $globalIndex = $offset + $index;
                                $podcastCaducidad = $caducidades[$podcast['name']] ?? $defaultCaducidad;
            $podcastDuracion = $duraciones[$podcast['name']] ?? '';
                                $feedInfo = getCachedFeedInfo($podcast['url']);
                                $statusInfo = formatFeedStatus($feedInfo['timestamp']);
                            ?>
                                <div class="podcast-item podcast-item-<?php echo htmlEsc($statusInfo['class']); ?> <?php echo (isset($podcast['paused']) && $podcast['paused']) ? 'podcast-paused' : ''; ?>" data-category="<?php echo htmlEsc($podcast['category']); ?>">
                                    <div class="podcast-info">
                                        <strong>
                                            <?php echo htmlEsc(displayName($podcast['name'])); ?>
                                            <?php if (isset($podcast['paused']) && $podcast['paused']): ?>
                                                <span class="badge-paused">⏸️ PAUSADO</span>
                                            <?php endif; ?>
                                        </strong>
                                        <small>Categoría: <?php echo htmlEsc(displayName($podcast['category'])); ?> | Caducidad: <?php echo htmlEsc($podcastCaducidad); ?> días</small>
                                        <small><?php echo htmlEsc($podcast['url']); ?></small>

                                        <div class="last-episode <?php echo $statusInfo['class']; ?>">
                                            <?php if ($feedInfo['timestamp'] !== null): ?>
                                                <?php echo htmlEsc($statusInfo['status']); ?> - Último episodio: <?php echo htmlEsc($statusInfo['date']); ?> (hace <?php echo htmlEsc($statusInfo['days']); ?> días)
                                            <?php else: ?>
                                                ⚠️ <?php echo htmlEsc($statusInfo['status']); ?>
                                            <?php endif; ?>
                                            <?php if ($feedInfo['cached'] && $feedInfo['cache_age'] > 0):
                                                $cacheHours = floor($feedInfo['cache_age'] / 3600);
                                            ?>
                                                <span class="cache-indicator">(comprobado hace <?php echo htmlEsc($cacheHours); ?>h)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="podcast-actions">
                                        <button type="button" class="btn btn-warning" onclick="showEditPodcastModal(<?php echo htmlEsc($globalIndex); ?>)"><span class="btn-icon">✏️</span> Editar</button>
                                        <?php if (isset($podcast['paused']) && $podcast['paused']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="resume_podcast">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="index" value="<?php echo htmlEsc($globalIndex); ?>">
                                                <button type="submit" class="btn btn-success"><span class="btn-icon">▶️</span> Reanudar</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="pause_podcast">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="index" value="<?php echo htmlEsc($globalIndex); ?>">
                                                <button type="submit" class="btn btn-secondary"><span class="btn-icon">⏸️</span> Pausar</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_podcast">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="index" value="<?php echo htmlEsc($globalIndex); ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')"><span class="btn-icon">🗑️</span> Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>


                        <!-- Controles de paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination-controls">
                                <div class="pagination-info">
                                    Mostrando <?php echo htmlEsc(min($offset + 1, $totalPodcasts)); ?>-<?php echo htmlEsc(min($offset + $itemsPerPage, $totalPodcasts)); ?> de <?php echo htmlEsc($totalPodcasts); ?> podcasts
                                </div>
                                <div class="pagination-buttons">
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?p=<?php echo htmlEsc($currentPage - 1); ?>" class="btn btn-secondary pagination-btn">← Anterior</a>
                                    <?php endif; ?>

                                    <?php
                                    // Mostrar números de página (máximo 5)
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $currentPage + 2);

                                    if ($startPage > 1): ?>
                                        <a href="?p=1" class="btn btn-secondary pagination-btn">1</a>
                                        <?php if ($startPage > 2): ?>
                                            <span style="padding: 0 5px; color: #718096;">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <a href="?p=<?php echo htmlEsc($i); ?>"
                                           class="btn btn-secondary pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                            <?php echo htmlEsc($i); ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?>
                                            <span style="padding: 0 5px; color: #718096;">...</span>
                                        <?php endif; ?>
                                        <a href="?p=<?php echo htmlEsc($totalPages); ?>" class="btn btn-secondary pagination-btn"><?php echo htmlEsc($totalPages); ?></a>
                                    <?php endif; ?>

                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?p=<?php echo htmlEsc($currentPage + 1); ?>" class="btn btn-secondary pagination-btn">Siguiente →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        </div>

                        <!-- Vista Agrupada por Categorías -->
                        <div id="grouped-view" style="display: none;">
                            <?php
                            // Agrupar podcasts por categoría
                            $podcastsByCategory = [];
                            foreach ($podcasts as $globalIndex => $podcast) {
                                // Agregar índice global para usar en onclick
                                $podcast['global_index'] = $globalIndex;
                                $cat = $podcast['category'];
                                if (!isset($podcastsByCategory[$cat])) {
                                    $podcastsByCategory[$cat] = [];
                                }
                                $podcastsByCategory[$cat][] = $podcast;
                            }
                            
                            // Ordenar categorías alfabéticamente
                            ksort($podcastsByCategory);

                            // Paginacion de categorias (3 categorias por pagina)
                            $categoriesPerPage = 3;
                            $categoryNames = array_keys($podcastsByCategory);
                            $totalCategories = count($categoryNames);
                            $totalCategoryPages = ceil($totalCategories / $categoriesPerPage);
                            $currentCategoryPage = min($currentPage, max(1, $totalCategoryPages));
                            $categoryOffset = ($currentCategoryPage - 1) * $categoriesPerPage;
                            $categoryNamesToShow = array_slice($categoryNames, $categoryOffset, $categoriesPerPage);

                            
                            foreach ($categoryNamesToShow as $category):
                                $categoryPodcasts = $podcastsByCategory[$category];
                                // Ordenar podcasts dentro de la categoría alfabéticamente
                                usort($categoryPodcasts, function($a, $b) {
                                    return strcasecmp($a['name'], $b['name']);
                                });
                            ?>
                                <div class="category-group" data-category="<?php echo htmlEsc($category); ?>">
                                    <div class="category-header">
                                        <h4><?php echo htmlEsc(displayName($category)); ?></h4>
                                        <span class="category-count"><?php echo htmlEsc(count($categoryPodcasts)); ?> podcast<?php echo count($categoryPodcasts) > 1 ? 's' : ''; ?></span>
                                    </div>
                                    
                                    <div class="podcast-list">
                                        <?php foreach ($categoryPodcasts as $podcast):
                                            $podcastCaducidad = $caducidades[$podcast['name']] ?? $defaultCaducidad;
            $podcastDuracion = $duraciones[$podcast['name']] ?? '';
                                            $feedInfo = getCachedFeedInfo($podcast['url']);
                                            $statusInfo = formatFeedStatus($feedInfo['timestamp']);
                                        ?>
                                            <div class="podcast-item podcast-item-<?php echo htmlEsc($statusInfo['class']); ?> <?php echo (isset($podcast['paused']) && $podcast['paused']) ? 'podcast-paused' : ''; ?>">
                                                <div class="podcast-info">
                                                    <strong>
                                                        <?php echo htmlEsc(displayName($podcast['name'])); ?>
                                                        <?php if (isset($podcast['paused']) && $podcast['paused']): ?>
                                                            <span class="badge-paused">⏸️ PAUSADO</span>
                                                        <?php endif; ?>
                                                    </strong>
                                                    <small>Caducidad: <?php echo htmlEsc($podcastCaducidad); ?> días</small>
                                                    <small><?php echo htmlEsc($podcast['url']); ?></small>

                                                    <div class="last-episode <?php echo $statusInfo['class']; ?>">
                                                        <?php if ($feedInfo['timestamp'] !== null): ?>
                                                            <?php echo htmlEsc($statusInfo['status']); ?> - Último episodio: <?php echo htmlEsc($statusInfo['date']); ?> (hace <?php echo htmlEsc($statusInfo['days']); ?> días)
                                                        <?php else: ?>
                                                            ⚠️ <?php echo htmlEsc($statusInfo['status']); ?>
                                                        <?php endif; ?>
                                                        <?php if ($feedInfo['cached'] && $feedInfo['cache_age'] > 0):
                                                            $cacheHours = floor($feedInfo['cache_age'] / 3600);
                                                        ?>
                                                            <span class="cache-indicator">(comprobado hace <?php echo htmlEsc($cacheHours); ?>h)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="podcast-actions">
                                                    <button type="button" class="btn btn-warning" onclick="showEditPodcastModal(<?php echo htmlEsc($podcast['global_index']); ?>)"><span class="btn-icon">✏️</span> Editar</button>
                                                    <?php if (isset($podcast['paused']) && $podcast['paused']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="resume_podcast">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="index" value="<?php echo htmlEsc($podcast['global_index']); ?>">
                                                            <button type="submit" class="btn btn-success"><span class="btn-icon">▶️</span> Reanudar</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="pause_podcast">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="index" value="<?php echo htmlEsc($podcast['global_index']); ?>">
                                                            <button type="submit" class="btn btn-secondary"><span class="btn-icon">⏸️</span> Pausar</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_podcast">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="index" value="<?php echo htmlEsc($podcast['global_index']); ?>">
                                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')"><span class="btn-icon">🗑️</span> Eliminar</button>
                                                        </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Controles de paginacion para categorias -->
                            <?php if ($totalCategoryPages > 1): ?>
                                <div class="pagination-controls" style="margin-top: 30px;">
                                    <div class="pagination-info">
                                        Mostrando <?php
                                            $firstCat = $categoryOffset + 1;
                                            $lastCat = min($categoryOffset + $categoriesPerPage, $totalCategories);
                                            echo htmlEsc($firstCat) . '-' . htmlEsc($lastCat) . ' de ' . htmlEsc($totalCategories);
                                        ?> categorías
                                    </div>
                                    <div class="pagination-buttons">
                                        <?php if ($currentCategoryPage > 1): ?>
                                            <a href="?p=<?php echo htmlEsc($currentCategoryPage - 1); ?>" class="btn btn-secondary pagination-btn">← Anterior</a>
                                        <?php endif; ?>

                                        <?php
                                        // Mostrar números de página (máximo 5)
                                        $startPage = max(1, $currentCategoryPage - 2);
                                        $endPage = min($totalCategoryPages, $currentCategoryPage + 2);

                                        if ($startPage > 1): ?>
                                            <a href="?p=1" class="btn btn-secondary pagination-btn">1</a>
                                            <?php if ($startPage > 2): ?>
                                                <span style="padding: 0 5px; color: #718096;">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <a href="?p=<?php echo htmlEsc($i); ?>"
                                               class="btn btn-secondary pagination-btn <?php echo $i === $currentCategoryPage ? 'active' : ''; ?>">
                                                <?php echo htmlEsc($i); ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($endPage < $totalCategoryPages): ?>
                                            <?php if ($endPage < $totalCategoryPages - 1): ?>
                                                <span style="padding: 0 5px; color: #718096;">...</span>
                                            <?php endif; ?>
                                            <a href="?p=<?php echo htmlEsc($totalCategoryPages); ?>" class="btn btn-secondary pagination-btn"><?php echo htmlEsc($totalCategoryPages); ?></a>
                                        <?php endif; ?>

                                        <?php if ($currentCategoryPage < $totalCategoryPages): ?>
                                            <a href="?p=<?php echo htmlEsc($currentCategoryPage + 1); ?>" class="btn btn-secondary pagination-btn">Siguiente →</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                        <button type="submit" class="btn btn-success"><span class="btn-icon">📥</span> Importar</button>
                    </form>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px;">Exportar podcasts</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="export_serverlist">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn btn-primary"><span class="btn-icon">📤</span> Descargar mi serverlist.txt</button>
                    </form>
                </div>
                
                <!-- PESTAÑA 3: DESCARGAS -->
                <div id="tab-descargas" class="tab-panel">
                    <h4>Ejecutar Descargas</h4>
                    <p style="color: #718096; margin-bottom: 20px;">Descarga los nuevos episodios de todos tus podcasts suscritos en el servidor.</p>
                    
                    <button type="button" class="btn btn-info" style="font-size: 16px; padding: 15px 30px;" onclick="executePodgetViaAjax();">
                        <span class="btn-icon">🚀</span> Ejecutar descargas para <?php echo htmlEsc($_SESSION['station_name']); ?>
                    </button>
                    
                    <div id="podget-status" style="margin-top: 20px;"></div>

                    <!-- ÚLTIMOS EPISODIOS DESCARGADOS -->
                    <div style="margin-top: 40px; border-top: 2px solid #e2e8f0; padding-top: 30px;">
                        <h4>🎙️ Últimos Episodios Descargados (esta semana)</h4>
                        <p style="color: #718096; margin-bottom: 20px;">Listado de los episodios descargados en los últimos 7 días</p>

                        <?php
                        // Cargar informes de los últimos 7 días
                        $allEpisodes = [];
                        $reports = getAvailableReports($_SESSION['username']);

                        if (!empty($reports)) {
                            $cutoffDate = strtotime("-7 days");
                            foreach ($reports as $reportInfo) {
                                if ($reportInfo['timestamp'] >= $cutoffDate) {
                                    $reportData = parseReportFile($reportInfo['file']);
                                    if ($reportData && !empty($reportData['podcasts_hoy'])) {
                                        foreach ($reportData['podcasts_hoy'] as $episode) {
                                            $episode['report_date'] = $reportInfo['display_date'];
                                            $allEpisodes[] = $episode;
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($allEpisodes)):
                        ?>
                            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <?php foreach (array_slice($allEpisodes, 0, 30) as $episode): ?>
                                    <?php
                                    // Dividir fecha en fecha y hora: "09-11-2025 11:01:48"
                                    $parts = explode(' ', $episode['fecha']);
                                    $date = isset($parts[0]) ? $parts[0] : '';
                                    $time = isset($parts[1]) ? $parts[1] : '';
                                    ?>
                                    <div style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #2d3748;">
                                        <?php
                                        echo htmlEsc($date) . ' - ' . htmlEsc($time) . ' - ' .
                                             htmlEsc($episode['podcast']) . ' - ' . htmlEsc($episode['archivo']);
                                        ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($allEpisodes) > 30): ?>
                                    <div style="text-align: center; padding: 20px; color: #718096; font-size: 14px;">
                                        ... y <?php echo htmlEsc(count($allEpisodes) - 30); ?> episodios más
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No hay episodios descargados en los últimos 7 días. Los informes se generan automáticamente cuando ejecutas las descargas.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
</div>

<!-- MODAL DE AGREGAR PODCAST -->
<div id="addPodcastModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3>
            <span class="close" onclick="closeAddPodcastModal()">&times;</span>
            Agregar Nuevo Podcast
        </h3>

        <div>
            <form method="POST">
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
                            <select name="category" id="modal_category_select" style="flex: 1;">
                                <option value="">-- Sin categoría (carpeta principal) --</option>
                                <?php foreach ($userCategories as $cat): ?>
                                    <option value="<?php echo htmlEsc($cat); ?>"><?php echo htmlEsc(displayName($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="showCategoryManager('modal')" style="white-space: nowrap;">Gestionar</button>
                        </div>
                        <small style="color: #718096;">Categoría opcional. Usa el botón "Gestionar" para añadir nuevas categorías</small>
                    <?php else: ?>
                        <p style="color: #718096; margin-bottom: 10px;">Sin categorías. El podcast se guardará en la carpeta principal.</p>
                        <button type="button" class="btn btn-secondary" onclick="showCategoryManager('modal')">Gestionar Categorías</button>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Nombre del Podcast:</label>
                    <input type="text" name="name" id="podcast_name" required placeholder="Mi Podcast" maxlength="100">
                    <small style="color: #718096;">Puedes usar espacios normales</small>
                </div>

                <div class="form-group">
                    <label>Días de caducidad:</label>
                    <input type="number" name="caducidad" id="podcast_caducidad" value="<?php echo htmlEsc($defaultCaducidad); ?>" min="1" max="365" required>
                    <small style="color: #718096;">Los archivos se eliminarán después de X días sin descargas nuevas (por defecto: <?php echo htmlEsc($defaultCaducidad); ?> días)</small>
                </div>
                <div class="form-group">
                    <label>Duración máxima de episodios:</label>
                    <select name="duracion" id="podcast_duracion">
                        <?php foreach ($duracionesOptions as $value => $label): ?>
                            <option value="<?php echo htmlEsc($value); ?>" <?php echo $value === '' ? 'selected' : ''; ?>>
                                <?php echo htmlEsc($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #718096;">Los episodios que excedan esta duración serán eliminados durante la limpieza diaria</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success"><span class="btn-icon">➕</span> Agregar Podcast</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddPodcastModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE EDITAR PODCAST -->
<div id="editPodcastModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3>
            <span class="close" onclick="closeEditPodcastModal()">&times;</span>
            Editar Podcast
        </h3>

        <div>
            <form method="POST" id="editPodcastForm">
                <input type="hidden" name="action" value="edit_podcast">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="index" id="edit_podcast_index">

                <div class="form-group">
                    <label>URL del RSS:</label>
                    <input type="text" name="url" id="edit_podcast_url" required maxlength="500">
                </div>

                <div class="form-group">
                    <label>Categoría:</label>
                    <?php if (!empty($userCategories)): ?>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <select name="category" id="edit_podcast_category" style="flex: 1;">
                                <option value="">-- Sin categoría (carpeta principal) --</option>
                                <?php foreach ($userCategories as $cat): ?>
                                    <option value="<?php echo htmlEsc($cat); ?>"><?php echo htmlEsc(displayName($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="showCategoryManager('edit')" style="white-space: nowrap;">Gestionar</button>
                        </div>
                        <small style="color: #718096;">Categoría opcional. Usa el botón "Gestionar" para añadir nuevas categorías</small>
                    <?php else: ?>
                        <p style="color: #718096; margin-bottom: 10px;">Sin categorías. El podcast se guardará en la carpeta principal.</p>
                        <button type="button" class="btn btn-secondary" onclick="showCategoryManager('edit')">Gestionar Categorías</button>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Nombre del Podcast:</label>
                    <input type="text" name="name" id="edit_podcast_name" required maxlength="100">
                    <small style="color: #718096;">Puedes usar espacios normales</small>
                </div>

                <div class="form-group">
                    <label>Días de caducidad:</label>
                    <input type="number" name="caducidad" id="edit_podcast_caducidad" min="1" max="365" required>
                    <small style="color: #718096;">Los archivos se eliminarán después de X días sin descargas nuevas (por defecto: 30 días)</small>
                </div>

                <div class="form-group">
                    <label>Duración máxima de episodios:</label>
                    <select name="duracion" id="edit_podcast_duracion">
                        <?php foreach ($duracionesOptions as $value => $label): ?>
                            <option value="<?php echo htmlEsc($value); ?>"><?php echo htmlEsc($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #718096;">Los episodios que excedan esta duración serán eliminados durante la limpieza diaria</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><span class="btn-icon">💾</span> Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditPodcastModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE GESTIÓN DE CATEGORÍAS -->
<div id="categoryModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3>
            <span class="close" onclick="closeCategoryManager()">&times;</span>
            Gestión de Categorías
        </h3>

        <div>
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label>Nueva Categoría:</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="category_name" id="new_category_input" required placeholder="Ej: Deportes, Noticias..." maxlength="50" style="flex: 1;">
                        <button type="submit" class="btn btn-success"><span class="btn-icon">✅</span> Añadir</button>
                    </div>
                </div>
            </form>

            <div style="margin-top: 30px;">
                <h4>Categorías Existentes:</h4>
                <?php if (!empty($userCategories)): ?>
                    <div class="category-list" style="margin-top: 15px;">
                        <?php foreach ($userCategories as $cat):
                            $stats = getCategoryStats($_SESSION['username'], $cat);
                            $isEmpty = $stats['files'] == 0 && $stats['podcasts'] == 0;
                        ?>
                            <div class="category-item-extended">
                                <div class="category-info">
                                    <div class="category-name"><?php echo htmlEsc(displayName($cat)); ?></div>
                                    <div class="category-stats">
                                        <?php echo $stats['podcasts']; ?> podcast<?php echo $stats['podcasts'] != 1 ? 's' : ''; ?> ·
                                        <?php echo $stats['files']; ?> archivo<?php echo $stats['files'] != 1 ? 's' : ''; ?>
                                        <?php if ($isEmpty): ?>
                                            <span class="badge-empty">Vacía</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="category-actions">
                                    <button type="button" class="btn-action" onclick="renameCategoryPrompt('<?php echo htmlEsc($cat); ?>')" title="Renombrar">
                                        ✏️
                                    </button>
                                    <?php if ($isEmpty): ?>
                                        <button type="button" class="btn-delete-small" onclick="deleteCategoryConfirm('<?php echo htmlEsc($cat); ?>')" title="Eliminar">
                                            🗑️
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-action" disabled title="No se puede eliminar: contiene archivos">
                                            🔒
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #718096; margin-top: 15px;">No hay categorías creadas aún.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.category-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.category-item-extended {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f7fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.category-info {
    flex: 1;
}

.category-name {
    font-size: 15px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 4px;
}

.category-stats {
    font-size: 13px;
    color: #718096;
    display: flex;
    align-items: center;
    gap: 8px;
}

.badge-empty {
    background: #feebc8;
    color: #c05621;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.badge-paused {
    background: #dc2626;
    color: #ffffff;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    display: inline-block;
    vertical-align: middle;
}

.podcast-paused {
    opacity: 0.7;
    background: #f7fafc !important;
}

.podcast-paused:hover {
    opacity: 0.85;
}

.category-actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    background: #667eea;
    color: white;
    border: none;
    border-radius: 4px;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover:not(:disabled) {
    background: #5a67d8;
    transform: scale(1.05);
}

.btn-action:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-delete-small {
    background: #f56565;
    color: white;
    border: none;
    border-radius: 4px;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-delete-small:hover {
    background: #e53e3e;
    transform: scale(1.05);
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
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    border-radius: 8px;
    margin-bottom: 15px;
    border-bottom: 2px solid #10b981;
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

<!-- Modal de progreso de actualización de feeds -->
<div id="feedsProgressModal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0;">🔄 Actualizando Feeds</h3>
        </div>
        <div class="modal-body">
            <p id="feedsProgressText" style="margin-bottom: 15px; color: #4a5568;">Preparando actualización...</p>
            
            <!-- Barra de progreso -->
            <div style="background: #e2e8f0; border-radius: 8px; overflow: hidden; height: 30px; position: relative;">
                <div id="feedsProgressBar" style="background: linear-gradient(90deg, #3b82f6, #2563eb); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                    <span id="feedsProgressPercent">0%</span>
                </div>
            </div>
            
            <!-- Podcast actual -->
            <div id="feedsCurrentPodcast" style="margin-top: 15px; padding: 10px; background: #f7fafc; border-radius: 4px; border-left: 3px solid #3b82f6; font-size: 14px; color: #2d3748; display: none;">
                <strong>Procesando:</strong> <span id="feedsCurrentPodcastName"></span>
            </div>
            
            <!-- Log de actualizaciones -->
            <div id="feedsLog" style="margin-top: 15px; max-height: 150px; overflow-y: auto; font-size: 13px; color: #718096; display: none;">
                <div style="border-top: 1px solid #e2e8f0; padding-top: 10px;">
                    <strong style="color: #2d3748;">Actualizados:</strong>
                    <div id="feedsLogContent" style="margin-top: 5px;"></div>
                </div>
            </div>
            
            <!-- Botón de cerrar (solo visible al terminar) -->
            <div id="feedsCloseButtonContainer" style="margin-top: 20px; text-align: right; display: none;">
                <button onclick="closeFeedsProgressModal()" class="btn btn-primary">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
#feedsProgressModal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(3px);
}

#feedsProgressModal .modal-content {
    background-color: #ffffff;
    margin: 10% auto;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalFadeIn 0.3s ease;
}

#feedsProgressModal .modal-header {
    padding: 20px 25px;
    border-bottom: 2px solid #e2e8f0;
}

#feedsProgressModal .modal-body {
    padding: 25px;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#feedsLog::-webkit-scrollbar {
    width: 6px;
}

#feedsLog::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#feedsLog::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 10px;
}

#feedsLog::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}
</style>

<script>
// Datos de todos los podcasts para JavaScript
const podcastsData = <?php echo json_encode($podcastsData); ?>;

// Funciones para el modal de editar podcast
function showEditPodcastModal(index) {
    // Convertir a número por si viene como string
    const numIndex = parseInt(index);
    console.log('Buscando podcast con índice:', numIndex);
    console.log('Podcasts disponibles:', podcastsData);

    // Buscar el podcast por índice en podcastsData
    const podcast = podcastsData.find(p => p.index === numIndex);
    if (!podcast) {
        alert('Podcast no encontrado (índice: ' + numIndex + ')');
        console.error('Índice buscado:', numIndex);
        console.error('Índices disponibles:', podcastsData.map(p => p.index));
        return;
    }

    console.log('Podcast encontrado:', podcast);

    // Llenar el formulario con los datos del podcast (verificar que existan primero)
    const indexField = document.getElementById('edit_podcast_index');
    const urlField = document.getElementById('edit_podcast_url');
    const nameField = document.getElementById('edit_podcast_name');
    const categoryField = document.getElementById('edit_podcast_category');
    const caducidadField = document.getElementById('edit_podcast_caducidad');
    const duracionField = document.getElementById('edit_podcast_duracion');

    if (indexField) indexField.value = podcast.index;
    if (urlField) urlField.value = podcast.url;
    if (nameField) nameField.value = podcast.name;
    if (categoryField) {
        categoryField.value = podcast.category;
    } else {
        console.warn('Campo de categoría no disponible. El usuario necesita crear categorías primero.');
    }
    if (caducidadField) caducidadField.value = podcast.caducidad;
    if (duracionField) duracionField.value = podcast.duracion;

    // Mostrar el modal
    document.getElementById('editPodcastModal').style.display = 'block';
}

function closeEditPodcastModal() {
    document.getElementById('editPodcastModal').style.display = 'none';
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
    const editModal = document.getElementById('editPodcastModal');
    if (event.target === editModal) {
        closeEditPodcastModal();
    }
}

// Reabrir el modal de categorías si se acabó de añadir o eliminar una categoría
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si hay mensajes relacionados con categorías
    const alerts = document.querySelectorAll('.alert');
    let shouldOpenCategoryModal = false;

    alerts.forEach(alert => {
        const text = alert.textContent.toLowerCase();
        if (text.includes('categoría agregada') ||
            text.includes('categoría eliminada') ||
            text.includes('categoria agregada') ||
            text.includes('categoria eliminada')) {
            shouldOpenCategoryModal = true;
        }
    });

    if (shouldOpenCategoryModal) {
        // Abrir el modal después de un pequeño delay para que se vea el mensaje
        setTimeout(() => {
            showCategoryManager();
        }, 100);
    }
});


</script>
