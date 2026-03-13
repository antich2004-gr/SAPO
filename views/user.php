<?php
// views/user.php - Interfaz de usuario regular
$userCategories = getUserCategories($_SESSION['username']);
$podcasts = readServerList($_SESSION['username']);
$caducidades = readCaducidades($_SESSION['username']);
$duraciones = readDuraciones($_SESSION['username']);
$margenes = readMargenes($_SESSION['username']);
$duracionesOptions = getDuracionesOptions();
$margenesOptions = getMargenesOptions();
$defaultCaducidad = getDefaultCaducidad($_SESSION['username']);

// Sincronizar caducidades si hay podcasts sin caducidad definida
if (!empty($podcasts)) {
    $podcastNames = array_column($podcasts, 'name');
    $hasMissingCaducidades = false;
    foreach ($podcastNames as $podcastName) {
        if (!isset($caducidades[$podcastName])) {
            $hasMissingCaducidades = true;
            break;
        }
    }
    if ($hasMissingCaducidades) {
        syncAllCaducidades($_SESSION['username']);
        $caducidades = readCaducidades($_SESSION['username']); // Releer caducidades actualizadas
    }
}

// SINCRONIZACIÓN INTELIGENTE: Solo importar si hay categorías nuevas en serverlist.txt
// que no estén ya registradas en SAPO
if (!empty($podcasts)) {
    // Extraer categorías del serverlist.txt (filtrar vacías y "Sin_categoria")
    $categoriesInServerList = array_filter(array_unique(array_column($podcasts, 'category')), function($cat) {
        return !empty($cat) && $cat !== 'Sin_categoria';
    });

    // Solo proceder si hay categorías en el serverlist
    if (!empty($categoriesInServerList)) {
        // Verificar si hay diferencias (categorías en serverlist que NO están en SAPO)
        $missingCategories = array_diff($categoriesInServerList, $userCategories);

        // Solo importar si realmente hay categorías nuevas
        if (!empty($missingCategories)) {
            $imported = importCategoriesFromServerList($_SESSION['username']);
            if (!empty($imported)) {
                // Recargar categorías después de la importación
                $userCategories = getUserCategories($_SESSION['username']);
                // Solo mostrar mensaje si es el primer login o si se importaron varias
                if (count($imported) > 1 || empty($userCategories)) {
                    $_SESSION['message'] = 'Se sincronizaron ' . count($imported) . ' categoría(s) desde serverlist.txt';
                }
            }
        }
    }
}

// Fallback: Auto-detectar categorías de los podcasts existentes si aún no hay categorías guardadas
if (empty($userCategories) && !empty($podcasts)) {
    $categoriesFromPodcasts = array_unique(array_column($podcasts, 'category'));
    // Filtrar "Sin_categoria" y categorías vacías
    $categoriesFromPodcasts = array_filter($categoriesFromPodcasts, function($cat) {
        return !empty($cat) && $cat !== 'Sin_categoria';
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

// ── Dashboard: conteo de podcasts por estado de RSS ──────────────────────────
// formatFeedStatus devuelve class: 'recent' | 'old' | 'inactive' | 'unknown'
$podcastCounts = ['recent' => 0, 'old' => 0, 'inactive' => 0, 'unknown' => 0];
$podcastByStatus = ['recent' => [], 'old' => [], 'inactive' => []];
$dashboardAlerts = ['warning' => []];
foreach ($podcasts as $podcast) {
    $name = displayName($podcast['name']);
    if (($podcast['type'] ?? 'rss') === 'ytdlp') {
        $podcastCounts['recent']++;
        $podcastByStatus['recent'][] = $name;
        continue;
    }
    $fi  = getCachedFeedInfo($podcast['url']);
    $si  = formatFeedStatus($fi['timestamp']);
    $cls = $si['class'] ?? 'unknown';
    if (array_key_exists($cls, $podcastCounts)) $podcastCounts[$cls]++;
    else $podcastCounts['unknown']++;
    if (isset($podcastByStatus[$cls])) $podcastByStatus[$cls][] = $name;
    if (in_array($cls, ['old', 'inactive'])) {
        $days = $si['days'] ?? 0;
        $dashboardAlerts['warning'][] = [
            'title'   => $name,
            'message' => "RSS sin actualizar ({$days} días)",
        ];
    }
}

// Detectar si estamos editando
$isEditing = isset($_GET['edit']) && is_numeric($_GET['edit']);
$editIndex = $isEditing ? intval($_GET['edit']) : null;
?>

<div class="card">
    <div class="nav-buttons">
        <div></div>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <a href="?page=help" class="btn btn-secondary" style="margin-right: 10px;"><span class="btn-icon">📖</span> Ayuda</a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary"><span class="btn-icon">🚪</span> Cerrar Sesión</button>
            </form>
        </div>
    </div>
    
    <?php
    // Preparar datos de todos los podcasts en JSON para JavaScript
    // OPTIMIZACIÓN: Solo cargar feedInfo para podcasts de la página actual (lazy loading)
    // EXCEPCIÓN: Si hay filtro de actividad en URL, cargar TODOS para que el filtro funcione
    $hasActivityFilter = isset($_GET['filter_activity']) && !empty($_GET['filter_activity']);

    $podcastsData = [];
    foreach ($podcasts as $index => $podcast) {
        // Cargar feed info si:
        // 1. Está en la página actual, O
        // 2. Hay filtro de actividad activo (necesita info de todos)
        $isInCurrentPage = ($index >= $offset && $index < $offset + $itemsPerPage);
        $shouldLoadFeedInfo = $isInCurrentPage || $hasActivityFilter;

        if ($shouldLoadFeedInfo) {
            if (($podcast['type'] ?? 'rss') === 'ytdlp') {
                $feedInfo   = ['timestamp' => null, 'cached' => false, 'cache_age' => 0];
                $statusInfo = ['class' => 'ytdlp', 'status' => 'Descarga vía yt-dlp', 'icon' => '📺', 'date' => '', 'days' => 0];
            } else {
                $feedInfo = getCachedFeedInfo($podcast['url']);
                $statusInfo = formatFeedStatus($feedInfo['timestamp']);
            }
        } else {
            // Para podcasts fuera de la página actual, usar datos vacíos
            // Se cargarán bajo demanda si el usuario navega a esa página
            $feedInfo = ['timestamp' => null, 'cached' => false, 'cache_age' => 0];
            $statusInfo = ['class' => 'unknown', 'status' => 'No cargado', 'icon' => '⏳', 'date' => '', 'days' => 0];
        }

        $podcastsData[] = [
            'index'         => $index,
            'url'           => $podcast['url'],
            'name'          => displayName($podcast['name']),
            'category'      => $podcast['category'],
            'type'          => $podcast['type'] ?? 'rss',
            'max_episodios' => $podcast['max_episodios'] ?? 1,
            'caducidad'     => $caducidades[$podcast['name']] ?? $defaultCaducidad,
            'duracion' => $duraciones[$podcast['name']] ?? '',
            'margen' => $margenes[$podcast['name']] ?? 5,
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
                <button class="tab-button active" data-tab="misapo" onclick="switchTab('misapo'); loadDashboardStats()">Mi SAPO</button>
                <button class="tab-button" data-tab="podcasts" onclick="switchTab('podcasts')">Mis Podcasts</button>
                <button class="tab-button" data-tab="config" onclick="switchTab('config')">Señales horarias</button>
                <button class="tab-button" data-tab="recordings" onclick="switchTab('recordings'); loadRecordings()">🎙️ Grabaciones</button>
                <button class="tab-button" data-tab="parrilla-link" onclick="window.location='?page=parrilla'">Asistente Parrilla</button>
            </div>
            
            <div class="tabs-content">
                <!-- PESTAÑA 0: MI SAPO (dashboard) -->
                <div id="tab-misapo" class="tab-panel active">
                    <style>
                        .podcast-status-bar-wrap { position: relative; }
                        .status-bar-popup {
                            display: none;
                            position: absolute;
                            left: calc(100% + 14px);
                            top: 50%;
                            transform: translateY(-50%);
                            background: #1a202c;
                            color: #e2e8f0;
                            font-size: 12px;
                            line-height: 1.7;
                            padding: 8px 12px;
                            border-radius: 6px;
                            white-space: nowrap;
                            z-index: 9999;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                            min-width: 180px;
                        }
                        .podcast-status-bar-wrap:hover .status-bar-popup { display: block; }
                        .podcast-status-bar-wrap:last-child:hover .status-bar-popup,
                        .podcast-status-bar-wrap:nth-last-child(2):hover .status-bar-popup {
                            top: auto; bottom: 0; transform: none;
                        }
                        .stale-programs-panel {
                            border: 2px solid #f59e0b;
                            border-radius: 8px;
                            padding: 16px;
                            margin-bottom: 12px;
                            background: #fffbeb;
                        }
                        .stale-programs-title {
                            font-weight: 600;
                            color: #92400e;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            font-size: 14px;
                            cursor: pointer;
                            user-select: none;
                        }
                        .stale-programs-title .stale-chevron {
                            margin-left: 0;
                            font-style: normal;
                            font-size: 18px;
                            line-height: 1;
                            transition: transform 0.2s;
                            background: #f59e0b;
                            color: white;
                            border-radius: 4px;
                            width: 22px;
                            height: 22px;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .stale-programs-title.collapsed .stale-chevron { transform: rotate(-90deg); }
                        .stale-programs-body { margin-top: 12px; }
                        .stale-programs-body.collapsed { display: none; }
                        .stale-programs-panel:has(.stale-programs-body.collapsed) { padding-bottom: 16px; }
                    </style>
                    <script>
                    function toggleStalePanel(titleEl) {
                        const body = titleEl.nextElementSibling;
                        const collapsed = body.classList.toggle('collapsed');
                        titleEl.classList.toggle('collapsed', collapsed);
                    }
                    </script>

                    <!-- ① ALERTAS -->
                    <?php if (!empty($dashboardAlerts['warning'])): ?>
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #2d3748; display: flex; align-items: center; gap: 8px;">
                            <span style="background:#e53e3e;color:white;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">!</span>
                            Alertas
                        </h3>
                        <div class="stale-programs-panel">
                            <div class="stale-programs-title" onclick="toggleStalePanel(this)">
                                <i class="stale-chevron">▾</i>
                                ⚠️ Programas con RSS sin actualizar (<?php echo count($dashboardAlerts['warning']); ?>)
                            </div>
                            <div class="stale-programs-body collapsed">
                                <p style="font-size: 12px; color: #92400e; margin-bottom: 12px;">
                                    Estos podcasts llevan más de 30 días sin publicar episodios nuevos en su RSS.
                                </p>
                                <?php foreach ($dashboardAlerts['warning'] as $alert): ?>
                                <div style="font-size:12px; padding:3px 0; border-bottom:1px solid #fde68a; display:flex; justify-content:space-between; gap:12px;">
                                    <span>📻 <?php echo htmlEsc($alert['title']); ?></span>
                                    <span style="color:#b45309; white-space:nowrap;"><?php echo htmlEsc($alert['message']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ② INFO -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #2d3748; display: flex; align-items: center; gap: 8px;">
                            <span style="background:#3182ce;color:white;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">i</span>
                            Info
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">

                            <!-- Podcasts suscritos -->
                            <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background: #fff; overflow: visible; position: relative;">
                                <div style="display: flex; align-items: center; gap: 20px;">
                                    <div>
                                        <div style="font-size: 13px; color: #4a5568; font-weight: 600; margin-bottom: 8px;">Podcast suscritos</div>
                                        <div style="font-size: 52px; font-weight: 800; color: #1a202c; line-height: 1;"><?php echo count($podcasts); ?></div>
                                    </div>
                                    <div style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                                        <?php
                                        $bars = [
                                            ['key' => 'recent',   'label' => '&lt;30d',    'bg' => '#38a169'],
                                            ['key' => 'old',      'label' => '30 a 60d', 'bg' => '#d97706'],
                                            ['key' => 'inactive', 'label' => '&gt;60d',    'bg' => '#e53e3e'],
                                        ];
                                        foreach ($bars as $bar):
                                            $names = $podcastByStatus[$bar['key']];
                                        ?>
                                        <div class="podcast-status-bar-wrap">
                                            <div class="podcast-status-bar"
                                                 style="display:flex;align-items:center;justify-content:space-between;background:<?php echo $bar['bg']; ?>;color:white;border-radius:6px;padding:6px 12px;cursor:default;">
                                                <span style="font-size:12px;"><?php echo $bar['label']; ?></span>
                                                <strong style="font-size:18px;"><?php echo $podcastCounts[$bar['key']]; ?></strong>
                                            </div>
                                            <?php if (!empty($names)): ?>
                                            <div class="status-bar-popup">
                                                <?php foreach ($names as $n): ?>
                                                <div><?php echo htmlEsc($n); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Grabaciones almacenadas -->
                            <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background: #fff;">
                                <div style="display: flex; align-items: center; gap: 20px;">
                                    <div>
                                        <div style="font-size: 13px; color: #4a5568; font-weight: 600; margin-bottom: 8px;">Grabaciones almacenadas</div>
                                        <div id="db-total-count" style="font-size: 52px; font-weight: 800; color: #1a202c; line-height: 1;">-</div>
                                    </div>
                                    <div style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; background: #718096; color: white; border-radius: 6px; padding: 6px 12px;">
                                            <span style="font-size: 12px;">Espacio usado</span>
                                            <strong style="font-size: 13px;" id="db-total-size">-</strong>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; background: #e53e3e; color: white; border-radius: 6px; padding: 6px 12px;">
                                            <span style="font-size: 12px;">Grabaciones antiguas</span>
                                            <strong style="font-size: 13px;" id="db-old-count">-</strong>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; background: #e53e3e; color: white; border-radius: 6px; padding: 6px 12px;">
                                            <span style="font-size: 12px;">Espacio a liberar</span>
                                            <strong style="font-size: 13px;" id="db-old-size">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ③ HERRAMIENTAS -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #2d3748; display: flex; align-items: center; gap: 8px;">
                            <span style="background:#d97706;color:white;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:15px;line-height:1;">⚙</span>
                            Herramientas
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">

                            <!-- Ejecutar descargas -->
                            <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background: #fff;">
                                <div style="font-size: 14px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Ejecutar Descargas</div>
                                <p style="color: #718096; font-size: 13px; margin: 0 0 15px 0;">
                                    Forzar ahora la descarga de nuevos episodios de todos tus podcasts suscritos
                                </p>
                                <button type="button" class="btn btn-success" onclick="executePodgetViaAjax();">
                                    <span class="btn-icon">🚀</span> Ejecutar descargas para <?php echo htmlEsc($_SESSION['station_name']); ?>
                                </button>
                                <div id="podget-status" style="margin-top: 15px;"></div>
                                <div id="podget-log-viewer" style="display:none; margin-top: 15px;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 8px;">
                                        <strong style="color:#4a5568; font-size:13px;">📋 Log en tiempo real</strong>
                                        <span id="podget-log-status" style="font-size:12px; color:#718096;"></span>
                                    </div>
                                    <pre id="podget-log-content" style="
                                        background:#1a202c; color:#68d391; font-size:12px; line-height:1.5;
                                        padding:16px; border-radius:8px; max-height:300px; overflow-y:auto;
                                        white-space:pre-wrap; word-break:break-all; margin:0;
                                    "></pre>
                                </div>
                            </div>

                            <!-- Importar / Exportar -->
                            <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background: #fff;">
                                <div style="font-size: 14px; font-weight: 600; color: #2d3748; margin-bottom: 12px;">Importar podcasts</div>
                                <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                                    <input type="hidden" name="action" value="import_serverlist">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <div class="file-input-wrapper">
                                            <label class="file-label" for="serverlist_file_dash">Seleccionar archivo...</label>
                                            <input type="file" name="serverlist_file" id="serverlist_file_dash" accept=".txt" required onchange="showFileName(this)">
                                        </div>
                                        <span class="selected-file" id="fileName_dash"></span>
                                        <button type="submit" class="btn btn-success"><span class="btn-icon">📥</span> Importar</button>
                                    </div>
                                </form>
                                <div style="font-size: 14px; font-weight: 600; color: #2d3748; margin-bottom: 10px;">Exportar podcasts</div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="export_serverlist">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <button type="submit" class="btn btn-primary"><span class="btn-icon">📤</span> Descargar mi serverlist.txt</button>
                                </form>
                            </div>

                        </div>
                    </div>

                    <!-- ④ ÚLTIMOS EPISODIOS DESCARGADOS -->
                    <div>
                        <h3 style="margin: 0 0 5px 0; font-size: 16px; color: #2d3748; display: flex; align-items: center; gap: 8px;">
                            <span style="background:#d97706;color:white;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;line-height:1;">●</span>
                            Últimos episodios descargados
                        </h3>
                        <p style="color: #4a5568; font-size: 13px; margin: 0 0 15px 0;">Lista de los episodios descargados en los últimos 7 días</p>
                        <?php
                        $allEpisodesDash = [];
                        $reportsDash = getAvailableReports($_SESSION['username']);
                        if (!empty($reportsDash)) {
                            $cutoffDate = strtotime("-7 days");
                            foreach ($reportsDash as $reportInfo) {
                                if ($reportInfo['timestamp'] >= $cutoffDate) {
                                    $reportData = parseReportFile($reportInfo['file']);
                                    if ($reportData && !empty($reportData['podcasts_hoy'])) {
                                        foreach ($reportData['podcasts_hoy'] as $ep) {
                                            $ep['report_date'] = $reportInfo['display_date'];
                                            $allEpisodesDash[] = $ep;
                                        }
                                    }
                                }
                            }
                        }
                        if (!empty($allEpisodesDash)):
                        ?>
                        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <?php foreach (array_slice($allEpisodesDash, 0, 30) as $ep):
                                $parts = explode(' ', $ep['fecha']);
                                $epDate = $parts[0] ?? '';
                                $epTime = $parts[1] ?? '';
                            ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #2d3748;">
                                <?php echo htmlEsc($epDate) . ' - ' . htmlEsc($epTime) . ' - ' . htmlEsc($ep['podcast']) . ' - ' . htmlEsc($ep['archivo']); ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($allEpisodesDash) > 30): ?>
                            <div style="text-align: center; padding: 20px; color: #718096; font-size: 14px;">
                                ... y <?php echo htmlEsc(count($allEpisodesDash) - 30); ?> episodios más
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            No hay episodios descargados en los últimos 7 días. Los informes se generan automáticamente cuando ejecutas las descargas.
                        </div>
                        <?php endif; ?>
                    </div>

                </div><!-- /tab-misapo -->

                <!-- PESTAÑA 1: MIS PODCASTS -->
                <div id="tab-podcasts" class="tab-panel">
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

                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                            <label for="filter_activity" style="margin: 0; white-space: nowrap; font-size: 14px;">Filtrar por:</label>

                            <?php if (!empty($userCategories)): ?>
                                <select id="filter_category" onchange="applyFilters()" style="min-width: 150px; max-width: 200px; padding: 8px;">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($userCategories as $cat):
                                        $countInCategory = count(array_filter($podcasts, function($p) use ($cat) {
                                            return $p['category'] === $cat;
                                        }));
                                    ?>
                                        <option value="<?php echo htmlEsc($cat); ?>"><?php echo htmlEsc(displayName($cat)); ?> (<?php echo htmlEsc($countInCategory); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <select id="filter_activity" onchange="applyFilters()" style="min-width: 150px; max-width: 220px; padding: 8px;">
                                <option value="">Todas las actividades</option>
                                <option value="recent">✅ Activo (<30 días)</option>
                                <option value="old">⚠️ Poco activo (30-60 días)</option>
                                <option value="inactive">❌ Inactivo (>60 días)</option>
                                <option value="unknown">❓ No responde</option>
                            </select>

                            <div style="display: flex; gap: 10px; margin-left: auto;">
                                <?php if (!empty($userCategories)): ?>
                                    <button type="button" class="btn btn-secondary" onclick="toggleGroupView()" id="toggleViewBtn">
                                        <span id="viewModeText">Agrupar por categoría</span>
                                    </button>

                                    <button type="button" class="btn btn-primary" onclick="openCategoryManager()" style="white-space: nowrap;">
                                        🗂️ Gestionar Categorías
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <script>
                        // Restaurar filtro desde URL al cargar la página
                        document.addEventListener('DOMContentLoaded', function() {
                            const urlParams = new URLSearchParams(window.location.search);
                            const activityFilter = urlParams.get('filter_activity');
                            const categoryFilter = urlParams.get('filter_category');

                            let shouldApplyFilter = false;

                            if (activityFilter) {
                                const activitySelect = document.getElementById('filter_activity');
                                if (activitySelect) {
                                    activitySelect.value = activityFilter;
                                    shouldApplyFilter = true;
                                }
                            }

                            if (categoryFilter) {
                                const categorySelect = document.getElementById('filter_category');
                                if (categorySelect) {
                                    categorySelect.value = categoryFilter;
                                    shouldApplyFilter = true;
                                }
                            }

                            // Si hay filtros activos, activar la pestaña Mis Podcasts
                            if (shouldApplyFilter) {
                                switchTab('podcasts');
                            }

                            // Aplicar filtro inmediatamente después de restaurar valores
                            if (shouldApplyFilter && typeof applyFiltersWithoutReload === 'function') {
                                // Usar versión que no recarga para evitar loop infinito
                                setTimeout(() => applyFiltersWithoutReload(), 100);
                            }
                        });
                        </script>
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
                                if (($podcast['type'] ?? 'rss') === 'ytdlp') {
                                    $feedInfo   = ['timestamp' => null, 'cached' => false, 'cache_age' => 0];
                                    $statusInfo = ['class' => 'ytdlp', 'status' => 'Descarga vía yt-dlp', 'icon' => '📺', 'date' => '', 'days' => 0];
                                } else {
                                    $feedInfo = getCachedFeedInfo($podcast['url']);
                                    $statusInfo = formatFeedStatus($feedInfo['timestamp']);
                                }
                            ?>
                                <div class="podcast-item podcast-item-<?php echo htmlEsc($statusInfo['class']); ?> <?php echo (isset($podcast['paused']) && $podcast['paused']) ? 'podcast-paused' : ''; ?>" data-category="<?php echo htmlEsc($podcast['category']); ?>">
                                    <div class="podcast-info">
                                        <strong>
                                            <?php echo htmlEsc(displayName($podcast['name'])); ?>
                                            <?php if (isset($podcast['paused']) && $podcast['paused']): ?>
                                                <span class="badge-paused">⏸️ PAUSADO</span>
                                            <?php endif; ?>
                                            <?php if (($podcast['type'] ?? 'rss') === 'ytdlp'): ?>
                                                <span class="badge-ytdlp">📺 yt-dlp</span>
                                            <?php endif; ?>
                                        </strong>
                                        <small>Categoría: <?php echo htmlEsc(displayName($podcast['category'])); ?> | Caducidad: <?php echo htmlEsc($podcastCaducidad); ?> días | Máx. <?php echo htmlEsc($podcast['max_episodios'] ?? 1); ?> episodios</small>
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
                                            if (($podcast['type'] ?? 'rss') === 'ytdlp') {
                                                $feedInfo   = ['timestamp' => null, 'cached' => false, 'cache_age' => 0];
                                                $statusInfo = ['class' => 'ytdlp', 'status' => 'Descarga vía yt-dlp', 'icon' => '📺', 'date' => '', 'days' => 0];
                                            } else {
                                                $feedInfo = getCachedFeedInfo($podcast['url']);
                                                $statusInfo = formatFeedStatus($feedInfo['timestamp']);
                                            }
                                        ?>
                                            <div class="podcast-item podcast-item-<?php echo htmlEsc($statusInfo['class']); ?> <?php echo (isset($podcast['paused']) && $podcast['paused']) ? 'podcast-paused' : ''; ?>">
                                                <div class="podcast-info">
                                                    <strong>
                                                        <?php echo htmlEsc(displayName($podcast['name'])); ?>
                                                        <?php if (isset($podcast['paused']) && $podcast['paused']): ?>
                                                            <span class="badge-paused">⏸️ PAUSADO</span>
                                                        <?php endif; ?>
                                                        <?php if (($podcast['type'] ?? 'rss') === 'ytdlp'): ?>
                                                            <span class="badge-ytdlp">📺 yt-dlp</span>
                                                        <?php endif; ?>
                                                    </strong>
                                                    <small>Caducidad: <?php echo htmlEsc($podcastCaducidad); ?> días | Máx. <?php echo htmlEsc($podcast['max_episodios'] ?? 1); ?> episodios</small>
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
                

                <!-- PESTAÑA 4: CONFIGURACIÓN -->
                <div id="tab-config" class="tab-panel">
                    <h4>Configuración de Señales Horarias</h4>
                    <p style="color: #718096; margin-bottom: 30px;">
                        Sube tus señales horarias y configúralas cuando deban reproducirse en tu estación.
                    </p>

                    <!-- UPLOADER -->
                    <div class="time-signals-uploader">
                        <h5>Subir Archivos de Audio</h5>
                        <form id="upload-time-signal-form">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <input type="file" id="time-signal-file" name="file" accept=".mp3,.wav,.ogg,.m4a"
                                       style="padding: 6px; border: 1px solid #cbd5e0; border-radius: 4px;">
                                <button type="submit" class="btn btn-primary" style="padding: 8px 18px;">📤 Subir</button>
                            </div>
                            <small style="color: #718096; display: block; margin-top: 6px;">Formatos: MP3, WAV, OGG, M4A · Máx. 10 MB</small>
                            <div id="upload-status" style="margin-top: 10px;"></div>
                        </form>
                    </div>

                    <!-- CONFIGURACIÓN DE HORARIOS -->
                    <div class="time-signals-config" style="margin-top: 40px;">
                        <h5>Configuración de Señales Horarias</h5>
                        <p style="color: #718096; margin-bottom: 20px; font-size: 14px;">
                            Las señales horarias sonarán automáticamente <strong>todos los días</strong> con la frecuencia que selecciones.
                        </p>

                        <form id="time-signals-form">
                            <!-- Paso 1: Archivo -->
                            <div style="padding: 15px; background: #f7fafc; border-radius: 8px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px 0; font-weight: 500; color: #2d3748;">
                                    1️⃣ Archivo de señal horaria:
                                </p>
                                <p style="margin: 0; color: #234e52; font-size: 14px;">
                                    📢 <strong>Archivo actual:</strong> <span id="current-signal-file" style="font-family: monospace; color: #38b2ac;">Ninguno</span>
                                </p>
                                <p style="margin: 8px 0 0 0; color: #718096; font-size: 13px;">
                                    Sube un archivo MP3 usando el formulario de arriba. El último archivo será el usado.
                                </p>
                            </div>

                            <!-- Paso 2: Configuración -->
                            <div style="padding: 15px; background: #f7fafc; border-radius: 8px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px 0; font-weight: 500; color: #2d3748;">
                                    2️⃣ Frecuencia de reproducción:
                                </p>
                                <select name="frequency" id="signal-frequency" required style="width: 100%; max-width: 400px; padding: 10px; font-size: 14px; border: 1px solid #cbd5e0; border-radius: 4px;">
                                    <option value="hourly">Cada hora (en punto: :00)</option>
                                    <option value="half-hourly">Cada media hora (:00 y :30)</option>
                                    <option value="quarter-hourly">Cada cuarto de hora (:00, :15, :30, :45)</option>
                                    <option value="every-5-min">🧪 Testing: Cada 5 minutos</option>
                                </select>
                                <small style="color: #718096; display: block; margin-top: 8px;">
                                    La señal se mezclará suavemente con la música todos los días de la semana.
                                </small>
                            </div>

                            <!-- Paso 3: Configuración avanzada de mezcla -->
                            <div style="padding: 15px; background: #f7fafc; border-radius: 8px; margin-bottom: 20px;">
                                <p style="margin: 0 0 15px 0; font-weight: 500; color: #2d3748;">
                                    ⚙️ Configuración avanzada de mezcla:
                                </p>

                                <!-- Duración -->
                                <div style="margin-bottom: 15px;">
                                    <label for="signal-duration" style="display: block; margin-bottom: 5px; color: #4a5568; font-size: 14px;">
                                        Duración de transición (segundos):
                                    </label>
                                    <input type="number" id="signal-duration" name="duration" min="0.2" max="10" step="0.1" value="0.2"
                                           style="width: 150px; padding: 8px; font-size: 14px; border: 1px solid #cbd5e0; border-radius: 4px;">
                                    <small style="color: #718096; display: block; margin-top: 5px;">
                                        Tiempo que tarda la señal en mezclarse con la música (0.2 - 10 segundos)
                                    </small>
                                </div>

                                <!-- Atenuación -->
                                <div>
                                    <label for="signal-attenuation" style="display: block; margin-bottom: 5px; color: #4a5568; font-size: 14px;">
                                        Atenuación de música (%):
                                    </label>
                                    <input type="number" id="signal-attenuation" name="attenuation" min="0" max="100" step="5" value="60"
                                           style="width: 150px; padding: 8px; font-size: 14px; border: 1px solid #cbd5e0; border-radius: 4px;">
                                    <small style="color: #718096; display: block; margin-top: 5px;">
                                        Porcentaje al que se reduce el volumen de la música durante la señal (recomendado: 60%)
                                    </small>
                                </div>
                            </div>

                            <!-- Paso 3: Aplicar señales horarias -->
                            <div style="padding: 15px; background: #f7fafc; border-radius: 8px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px 0; font-weight: 500; color: #2d3748;">
                                    3️⃣ Aplicar señales horarias:
                                </p>
                                <button type="button" class="btn btn-success" onclick="applyTimeSignalsDirectly()" style="padding: 10px 25px; font-size: 15px;">
                                    ✅ Aplicar Señales Horarias
                                </button>

                            </div>
                        </form>

                        <div id="config-status" style="margin-top: 20px;"></div>
                    </div>
                </div>

                <!-- PESTAÑA: GRABACIONES -->
                <div id="tab-recordings" class="tab-panel">
                    <h3 style="margin-bottom: 30px;">🎙️ Gestión de Grabaciones</h3>

                    <!-- Configuración de retención -->
                    <div style="background: #f7fafc; padding: 25px; border-radius: 12px; margin-bottom: 30px; border-left: 4px solid #3182ce;">
                        <h4 style="margin: 0 0 20px 0; color: #2d3748; font-size: 18px;">
                            ⚙️ Configuración de retención
                        </h4>
                        <p style="color: #718096; margin-bottom: 20px; font-size: 14px;">
                            Las grabaciones más antiguas que el período configurado se eliminarán automáticamente cada 24 horas.
                        </p>

                        <div style="margin-bottom: 20px;">
                            <label for="retention-days" style="display: block; margin-bottom: 10px; font-weight: 500; color: #4a5568;">
                                Días de retención:
                            </label>
                            <input type="number" id="retention-days" min="1" max="180" value="30"
                                   style="width: 100%; max-width: 300px; padding: 12px; font-size: 16px; border: 2px solid #cbd5e0; border-radius: 8px;"
                                   oninput="this.value = Math.min(180, Math.max(1, parseInt(this.value) || 1))">
                            <span style="display: block; margin-top: 6px; color: #718096; font-size: 13px;">Mínimo 1 día · Máximo 180 días</span>
                        </div>

                        <button onclick="saveRecordingsConfig()" style="background: #3182ce; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-size: 16px; font-weight: 500; cursor: pointer;">
                            💾 Guardar configuración
                        </button>
                    </div>

                    <!-- Estadísticas -->
                    <div id="recordings-stats" style="background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 2px solid #e2e8f0;">
                        <h4 style="margin: 0 0 20px 0; color: #2d3748; font-size: 18px;">
                            📊 Estadísticas
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <p style="margin: 0; color: #718096; font-size: 14px;">Total de grabaciones</p>
                                <p id="stats-total-count" style="margin: 5px 0 0 0; font-size: 28px; font-weight: bold; color: #2d3748;">-</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #718096; font-size: 14px;">Espacio usado</p>
                                <p id="stats-total-size" style="margin: 5px 0 0 0; font-size: 28px; font-weight: bold; color: #2d3748;">-</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #718096; font-size: 14px;">Grabaciones antiguas</p>
                                <p id="stats-old-count" style="margin: 5px 0 0 0; font-size: 28px; font-weight: bold; color: #e53e3e;">-</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #718096; font-size: 14px;">Espacio a liberar</p>
                                <p id="stats-old-size" style="margin: 5px 0 0 0; font-size: 28px; font-weight: bold; color: #e53e3e;">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div style="background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 2px solid #e2e8f0;">
                        <h4 style="margin: 0 0 20px 0; color: #2d3748; font-size: 18px;">
                            🗑️ Acciones
                        </h4>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button onclick="loadRecordings()" style="background: #3182ce; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-size: 16px; font-weight: 500; cursor: pointer;">
                                🔄 Actualizar lista
                            </button>
                            <button onclick="deleteOldRecordingsNow()" style="background: #e53e3e; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-size: 16px; font-weight: 500; cursor: pointer;">
                                🗑️ Eliminar grabaciones antiguas
                            </button>
                        </div>
                    </div>

                    <!-- Lista de grabaciones -->
                    <div style="background: #fff; padding: 25px; border-radius: 12px; border: 2px solid #e2e8f0;">
                        <h4 style="margin: 0 0 20px 0; color: #2d3748; font-size: 18px;">
                            📋 Grabaciones
                        </h4>
                        <div id="recordings-list" style="max-height: 600px; overflow-y: auto;">
                            <p style="color: #718096; text-align: center; padding: 40px 0;">
                                Cargando grabaciones...
                            </p>
                        </div>
                    </div>

                    <div id="recordings-status" style="margin-top: 20px;"></div>
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
                    <label>URL del podcast o plataforma:</label>
                    <input type="text" name="url" id="podcast_url" required placeholder="https://ejemplo.com/podcast/rss o https://youtube.com/channel/..." maxlength="500" oninput="detectPodcastUrlType(this.value, 'add')">
                    <small id="podcast_url_hint" style="color: #718096;">RSS, YouTube, SoundCloud, Vimeo u otras plataformas compatibles</small>
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

                <div class="form-group" id="max_episodios_group">
                    <label>Máximo de episodios a mantener en carpeta:</label>
                    <input type="number" name="max_episodios" id="podcast_max_episodios" value="1" min="1" max="50">
                    <small style="color: #718096;">Episodios recientes que se conservan en la carpeta (1-50). Por defecto 1 (solo el último).</small>
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

                <div class="form-group">
                    <label>Margen de tolerancia:</label>
                    <select name="margen" id="podcast_margen">
                        <?php foreach ($margenesOptions as $value => $label): ?>
                            <option value="<?php echo htmlEsc($value); ?>" <?php echo $value === 5 ? 'selected' : ''; ?>>
                                <?php echo htmlEsc($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #718096;">Tiempo extra permitido sobre la duración máxima antes de eliminar el episodio</small>
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
                    <label>URL del podcast o plataforma:</label>
                    <input type="text" name="url" id="edit_podcast_url" required maxlength="500" oninput="detectPodcastUrlType(this.value, 'edit')">
                    <small id="edit_podcast_url_hint" style="color: #718096;">RSS, YouTube, SoundCloud, Vimeo u otras plataformas compatibles</small>
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

                <div class="form-group" id="edit_max_episodios_group">
                    <label>Máximo de episodios a mantener en carpeta:</label>
                    <input type="number" name="max_episodios" id="edit_podcast_max_episodios" value="1" min="1" max="50">
                    <small style="color: #718096;">Episodios recientes que se conservan en la carpeta (1-50). Por defecto 1 (solo el último).</small>
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

                <div class="form-group">
                    <label>Margen de tolerancia:</label>
                    <select name="margen" id="edit_podcast_margen">
                        <?php foreach ($margenesOptions as $value => $label): ?>
                            <option value="<?php echo htmlEsc($value); ?>"><?php echo htmlEsc($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #718096;">Tiempo extra permitido sobre la duración máxima antes de eliminar el episodio</small>
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

            <!-- Botón de sincronización desde serverlist.txt -->
            <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">
                    <strong>💡 Sugerencia:</strong> Si tus categorías no aparecen, puedes importarlas automáticamente desde el archivo serverlist.txt
                </p>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="sync_categories_from_serverlist">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <span class="btn-icon">🔄</span> Sincronizar categorías desde serverlist.txt
                    </button>
                </form>
            </div>

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

.badge-ytdlp {
    background: #2563eb;
    color: #ffffff;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    display: inline-block;
    vertical-align: middle;
}

.podcast-item-ytdlp {
    border-left-color: #2563eb;
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

// Dominios compatibles con yt-dlp (debe coincidir con isYtdlpUrl() en PHP)
const YTDLP_DOMAINS = [
    'youtube.com', 'youtu.be', 'soundcloud.com', 'vimeo.com',
    'dailymotion.com', 'twitch.tv', 'rumble.com', 'odysee.com',
    'tiktok.com', 'instagram.com', 'facebook.com', 'fb.watch'
];

function isYtdlpUrl(url) {
    try {
        const parsed = new URL(url);
        let host = parsed.hostname.toLowerCase().replace(/^www\./, '');
        for (const domain of YTDLP_DOMAINS) {
            if (host === domain || host.endsWith('.' + domain)) return true;
        }
    } catch (e) {}
    return false;
}

function detectPodcastUrlType(url, mode) {
    const isYtdlp = isYtdlpUrl(url);
    const hintId     = mode === 'add' ? 'podcast_url_hint'         : 'edit_podcast_url_hint';
    const hint       = document.getElementById(hintId);

    if (hint) {
        if (isYtdlp) {
            hint.textContent = '📺 URL de plataforma detectada — se descargará con yt-dlp';
            hint.style.color = '#2563eb';
        } else {
            hint.textContent = 'RSS, YouTube, SoundCloud, Vimeo u otras plataformas compatibles';
            hint.style.color = '#718096';
        }
    }
}

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
    const margenField = document.getElementById('edit_podcast_margen');

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
    if (margenField) margenField.value = podcast.margen || 5;

    // Mostrar/ocultar campo max_episodios según tipo
    const maxEpField = document.getElementById('edit_podcast_max_episodios');
    if (maxEpField) maxEpField.value = podcast.max_episodios || 1;
    detectPodcastUrlType(podcast.url, 'edit');

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

// ============================================
// SEÑALES HORARIAS - Time Signals Functions
// ============================================

/**
 * Cargar archivo actual de señal horaria
 */
function loadTimeSignalFiles() {
    fetch('?action=list_time_signals')
        .then(response => response.json())
        .then(data => {
            const currentFileSpan = document.getElementById('current-signal-file');

            if (!data.success || !data.files || data.files.length === 0) {
                currentFileSpan.textContent = 'Ninguno';
                currentFileSpan.style.color = '#718096';
                return;
            }

            // Mostrar el archivo más reciente (el primero de la lista)
            currentFileSpan.textContent = data.files[0].name;
            currentFileSpan.style.color = '#38b2ac';
        })
        .catch(error => {
            console.error('Error al cargar archivos:', error);
            document.getElementById('current-signal-file').textContent = 'Error';
        });
}

/**
 * Subir archivo de señal horaria y aplicar automáticamente
 */
document.getElementById('upload-time-signal-form')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const fileInput = document.getElementById('time-signal-file');
    const submitButton = this.querySelector('button[type="submit"]');
    const uploadStatus = document.getElementById('upload-status');

    if (!fileInput.files || fileInput.files.length === 0) {
        uploadStatus.innerHTML = '<div class="alert alert-danger">Por favor selecciona un archivo</div>';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'upload_time_signal');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('file', fileInput.files[0]);

    submitButton.disabled = true;
    submitButton.textContent = 'Subiendo...';
    uploadStatus.innerHTML = '<p style="color: #3182ce;">⏳ Subiendo archivo...</p>';

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fileInput.value = '';
            loadTimeSignalFiles();
            document.getElementById('current-signal-file').textContent = data.filename || 'Archivo subido';

            // Aplicar automáticamente tras la subida
            uploadStatus.innerHTML = '<p style="color: #3182ce;">⏳ Aplicando señales horarias...</p>';
            doApplyTimeSignals(uploadStatus);
        } else {
            uploadStatus.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        uploadStatus.innerHTML = '<div class="alert alert-danger">Error al subir archivo</div>';
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.textContent = '📤 Subir';
    });
});

/**
 * Eliminar archivo de señal horaria
 */
function deleteTimeSignalFile(filename) {
    if (!confirm('¿Estás seguro de eliminar este archivo?\n\n' + filename)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_time_signal');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('filename', filename);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            loadTimeSignalFiles();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al eliminar archivo');
    });
}

/**
 * Cargar configuración de señales horarias desde JSON guardado
 * @param {boolean} silent - Si es true, no muestra mensajes en pantalla
 */
function loadTimeSignalsConfig(silent = false) {
    const statusDiv = document.getElementById('config-status');

    if (!silent) {
        statusDiv.innerHTML = '<p style="color: #3182ce;">Cargando configuración...</p>';
    }

    fetch('?action=get_time_signals_config')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.config) {
                const config = data.config;

                if (config.frequency) {
                    document.getElementById('signal-frequency').value = config.frequency;
                }
                if (config.signal_file) {
                    document.getElementById('current-signal-file').textContent = config.signal_file;
                }
                if (config.duration !== undefined) {
                    document.getElementById('signal-duration').value = config.duration;
                }
                if (config.attenuation !== undefined) {
                    document.getElementById('signal-attenuation').value = config.attenuation;
                }

                if (!silent) {
                    statusDiv.innerHTML = '<div class="alert alert-success">Configuración cargada correctamente</div>';
                    setTimeout(() => {
                        statusDiv.innerHTML = '';
                    }, 3000);
                }
            } else {
                if (!silent) {
                    statusDiv.innerHTML = '<div class="alert alert-info">No hay configuración previa</div>';
                    setTimeout(() => {
                        statusDiv.innerHTML = '';
                    }, 3000);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (!silent) {
                statusDiv.innerHTML = '<div class="alert alert-danger">Error al cargar configuración</div>';
            }
        });
}

/**
 * Enviar la petición de aplicar señales horarias (sin confirmación).
 * @param {HTMLElement} statusEl - Elemento donde mostrar el estado (por defecto #config-status)
 */
function doApplyTimeSignals(statusEl) {
    const statusDiv = statusEl || document.getElementById('config-status');
    const frequency = document.getElementById('signal-frequency').value;
    const duration = document.getElementById('signal-duration').value;
    const attenuation = document.getElementById('signal-attenuation').value;

    const formData = new FormData();
    formData.append('action', 'apply_time_signals_via_api');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('frequency', frequency);
    formData.append('duration', duration);
    formData.append('attenuation', attenuation);
    formData.append('offset_seconds', '0');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ ' + data.message + '</div>';
        } else {
            statusDiv.innerHTML = '<div class="alert alert-danger">❌ ' + data.message + '</div>';
        }
        setTimeout(() => { statusDiv.innerHTML = ''; }, 5000);
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger">❌ Error al aplicar señales horarias</div>';
    });
}

/**
 * Aplicar señales horarias (botón manual — pide confirmación)
 */
function applyTimeSignalsDirectly() {
    const statusDiv = document.getElementById('config-status');

    const confirmMessage =
        "⚠️ AVISO IMPORTANTE\n\n" +
        "Esta acción reiniciará la emisora para aplicar los cambios.\n\n" +
        "• Duración estimada: 3-5 segundos\n" +
        "• Habrá un corte breve en la transmisión\n\n" +
        "¿Deseas continuar?";

    if (!confirm(confirmMessage)) {
        statusDiv.innerHTML = '<div class="alert alert-info">❌ Operación cancelada</div>';
        setTimeout(() => { statusDiv.innerHTML = ''; }, 3000);
        return;
    }

    statusDiv.innerHTML = '<p style="color: #3182ce;">⏳ Aplicando señales horarias y reiniciando emisora...</p>';
    doApplyTimeSignals(statusDiv);
}

function loadInitialConfig() {
    loadTimeSignalsConfig(true);
}

// Cargar archivos y configuración al iniciar la página
document.addEventListener('DOMContentLoaded', function() {
    loadTimeSignalFiles();
    loadInitialConfig();

    // Cargar grabaciones si está en la pestaña
    if (window.location.hash === '#recordings') {
        loadRecordings();
    }
});

// ====================================================================
// FUNCIONES DE GRABACIONES
// ====================================================================

/**
 * Cargar configuración de grabaciones
 */
function loadRecordingsConfig() {
    fetch('?action=get_recordings_config')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.config) {
                document.getElementById('retention-days').value = data.config.retention_days || 30;
            }
        })
        .catch(error => console.error('Error al cargar config de grabaciones:', error));
}

/**
 * Guardar configuración de grabaciones
 */
function saveRecordingsConfig() {
    const statusDiv = document.getElementById('recordings-status');
    const retentionDays = document.getElementById('retention-days').value;

    const formData = new FormData();
    formData.append('action', 'save_recordings_config');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('retention_days', retentionDays);
    formData.append('auto_delete', 'true');

    statusDiv.innerHTML = '<p style="color: #3182ce;">Guardando configuración...</p>';

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ ' + data.message + '</div>';
            loadRecordingsStats(); // Actualizar estadísticas
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 3000);
        } else {
            statusDiv.innerHTML = '<div class="alert alert-danger">❌ ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger">❌ Error al guardar configuración</div>';
    });
}

/**
 * Cargar estadísticas de grabaciones en el dashboard (Mi SAPO)
 */
let dashboardStatsLoaded = false;
function loadDashboardStats() {
    if (dashboardStatsLoaded) return;
    fetch('?action=get_recordings_stats')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.stats) {
                const s = data.stats;
                const el = id => document.getElementById(id);
                if (el('db-total-count')) el('db-total-count').textContent = s.total_count;
                if (el('db-total-size'))  el('db-total-size').textContent  = s.total_size_formatted;
                if (el('db-old-count'))   el('db-old-count').textContent   = s.old_count;
                if (el('db-old-size'))    el('db-old-size').textContent    = s.old_size_formatted;
                dashboardStatsLoaded = true;
            }
        })
        .catch(() => {});
}

/**
 * Cargar estadísticas de grabaciones
 */
function loadRecordingsStats() {
    fetch('?action=get_recordings_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                const stats = data.stats;
                document.getElementById('stats-total-count').textContent = stats.total_count;
                document.getElementById('stats-total-size').textContent = stats.total_size_formatted;
                document.getElementById('stats-old-count').textContent = stats.old_count;
                document.getElementById('stats-old-size').textContent = stats.old_size_formatted;
            }
        })
        .catch(error => console.error('Error al cargar stats:', error));
}

/**
 * Cargar lista de grabaciones
 */
function loadRecordings() {
    const listDiv = document.getElementById('recordings-list');
    listDiv.innerHTML = '<p style="color: #3182ce; text-align: center; padding: 40px 0;">🔄 Cargando grabaciones...</p>';

    // Cargar configuración y estadísticas
    loadRecordingsConfig();
    loadRecordingsStats();

    fetch('?action=get_recordings_list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecordings(data.recordings);
            } else {
                listDiv.innerHTML = '<p style="color: #e53e3e; text-align: center; padding: 40px 0;">❌ Error al cargar grabaciones</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            listDiv.innerHTML = '<p style="color: #e53e3e; text-align: center; padding: 40px 0;">❌ Error de conexión</p>';
        });
}

/**
 * Mostrar lista de grabaciones en la interfaz
 */
function displayRecordings(recordings) {
    const listDiv = document.getElementById('recordings-list');

    if (recordings.length === 0) {
        listDiv.innerHTML = '<p style="color: #718096; text-align: center; padding: 40px 0;">📭 No hay grabaciones disponibles</p>';
        return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 15px;">';

    recordings.forEach(recording => {
        const isOld = recording.days_old >= parseInt(document.getElementById('retention-days').value);
        const borderColor = isOld ? '#e53e3e' : '#cbd5e0';
        const bgColor = isOld ? '#fff5f5' : '#fff';

        const displayName = recording.streamer
            ? `<span style="color:#805ad5;font-size:13px;">🎙️ ${escapeHtml(recording.streamer)}</span> / ${escapeHtml(recording.filename.split('/').pop())}`
            : escapeHtml(recording.filename);

        html += `
            <div style="background: ${bgColor}; padding: 20px; border-radius: 8px; border: 2px solid ${borderColor};">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 16px; color: #2d3748;">
                            📁 ${displayName}
                        </p>
                        <p style="margin: 0; color: #718096; font-size: 14px;">
                            📅 ${recording.date} • 💾 ${recording.size_formatted} • ⏱️ ${recording.days_old} día(s)
                        </p>
                        ${isOld ? '<p style="margin: 8px 0 0 0; color: #e53e3e; font-weight: 500; font-size: 14px;">⚠️ Será eliminada automáticamente</p>' : ''}
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="?action=download_recording&filename=${encodeURIComponent(recording.filename)}"
                           download
                           style="display: inline-block; background: #3182ce; color: white; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none;">
                            ⬇️ Descargar
                        </a>
                        <button onclick="deleteRecordingConfirm('${escapeHtml(recording.filename)}')"
                                style="background: #e53e3e; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer;">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    listDiv.innerHTML = html;
}

/**
 * Confirmar eliminación de una grabación
 */
function deleteRecordingConfirm(filename) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta grabación?\n\n' + filename)) {
        return;
    }

    deleteRecordingFile(filename);
}

/**
 * Eliminar una grabación específica
 */
function deleteRecordingFile(filename) {
    const statusDiv = document.getElementById('recordings-status');

    const formData = new FormData();
    formData.append('action', 'delete_recording');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('filename', filename);

    statusDiv.innerHTML = '<p style="color: #3182ce;">Eliminando grabación...</p>';

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ ' + data.message + '</div>';
            loadRecordings(); // Recargar lista
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 3000);
        } else {
            statusDiv.innerHTML = '<div class="alert alert-danger">❌ ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger">❌ Error al eliminar grabación</div>';
    });
}

/**
 * Eliminar todas las grabaciones antiguas
 */
function deleteOldRecordingsNow() {
    const retentionDays = document.getElementById('retention-days').value;
    const stats = {
        old_count: parseInt(document.getElementById('stats-old-count').textContent) || 0
    };

    if (stats.old_count === 0) {
        alert('No hay grabaciones antiguas para eliminar.');
        return;
    }

    const confirmMessage =
        `⚠️ CONFIRMACIÓN DE ELIMINACIÓN\n\n` +
        `Se eliminarán ${stats.old_count} grabación(es) con más de ${retentionDays} días.\n\n` +
        `¿Deseas continuar?`;

    if (!confirm(confirmMessage)) {
        return;
    }

    const statusDiv = document.getElementById('recordings-status');

    const formData = new FormData();
    formData.append('action', 'delete_old_recordings');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

    statusDiv.innerHTML = '<p style="color: #3182ce;">🗑️ Eliminando grabaciones antiguas...</p>';

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ ' + data.message + '</div>';
            loadRecordings(); // Recargar lista y stats
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 5000);
        } else {
            statusDiv.innerHTML = '<div class="alert alert-danger">❌ ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger">❌ Error al eliminar grabaciones</div>';
    });
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/// Cargar datos del panel activo al iniciar
document.addEventListener('DOMContentLoaded', function() {
    const activeTab = document.querySelector('.tab-panel.active');
    if (activeTab) {
        if (activeTab.id === 'tab-recordings') loadRecordings();
        if (activeTab.id === 'tab-misapo')     loadDashboardStats();
    }
});


</script>
