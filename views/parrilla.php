<?php
// views/parrilla.php - Gestión completa de la parrilla de programación
$username = $_SESSION['username'];
$azConfig = getAzuracastConfig($username);
$stationId = $azConfig['station_id'] ?? null;
$widgetColor = $azConfig['widget_color'] ?? '#3b82f6';

// Determinar subsección activa
$section = $_GET['section'] ?? 'preview';

// Generar URL del widget
$widgetUrl = '';
$hasStationId = !empty($stationId) && $stationId !== '';
if ($hasStationId) {
    // Forzar HTTPS para que el iframe funcione en sitios HTTPS
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = 'https://' . $host . dirname($_SERVER['PHP_SELF']);
    $widgetUrl = rtrim($baseUrl, '/') . '/parrilla_widget.php?station=' . urlencode($username);
}
?>

<div class="card">
    <div class="nav-buttons">
        <h2>📺 Parrilla de Programación</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <a href="?page=help_parrilla" class="btn btn-secondary" style="margin-right: 10px;">
                <span class="btn-icon">❓</span> Ayuda
            </a>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                <span class="btn-icon">◀️</span> Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Navegación por pestañas -->
    <div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
        <div style="display: flex; gap: 0; flex-wrap: wrap;">
            <a href="?page=parrilla&section=preview"
               class="<?php echo $section === 'preview' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'preview' ? '#10b981' : 'transparent'; ?>; color: <?php echo $section === 'preview' ? '#10b981' : '#6b7280'; ?>; font-weight: <?php echo $section === 'preview' ? '600' : '400'; ?>; transition: all 0.2s;">
                👁️ Vista Previa
            </a>
            <a href="?page=parrilla&section=programs"
               class="<?php echo $section === 'programs' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'programs' ? '#10b981' : 'transparent'; ?>; color: <?php echo $section === 'programs' ? '#10b981' : '#6b7280'; ?>; font-weight: <?php echo $section === 'programs' ? '600' : '400'; ?>; transition: all 0.2s;">
                📝 Gestión de Programas
            </a>
            <a href="?page=parrilla&section=config"
               class="<?php echo $section === 'config' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'config' ? '#10b981' : 'transparent'; ?>; color: <?php echo $section === 'config' ? '#10b981' : '#6b7280'; ?>; font-weight: <?php echo $section === 'config' ? '600' : '400'; ?>; transition: all 0.2s;">
                ⚙️ Configuración
            </a>
            <a href="?page=parrilla&section=embed"
               class="<?php echo $section === 'embed' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'embed' ? '#10b981' : 'transparent'; ?>; color: <?php echo $section === 'embed' ? '#10b981' : '#6b7280'; ?>; font-weight: <?php echo $section === 'embed' ? '600' : '400'; ?>; transition: all 0.2s;">
                🔗 Código de Embebido
            </a>
            <a href="?page=parrilla&section=coverage"
               class="<?php echo $section === 'coverage' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'coverage' ? '#10b981' : 'transparent'; ?>; color: <?php echo $section === 'coverage' ? '#10b981' : '#6b7280'; ?>; font-weight: <?php echo $section === 'coverage' ? '600' : '400'; ?>; transition: all 0.2s;">
                📊 Cobertura Semanal
            </a>
        </div>
    </div>

    <!-- Contenido de las secciones -->
    <?php if ($section === 'preview'): ?>
        <!-- VISTA PREVIA -->
        <div class="section">
            <h3>Vista Previa de tu Parrilla</h3>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #10b981; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <?php
                // Generar URL del widget - Forzar HTTPS para que funcione en sitios HTTPS
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = 'https://' . $host . dirname($_SERVER['PHP_SELF']);
                $widgetUrl = rtrim($baseUrl, '/') . '/parrilla_cards.php?station=' . urlencode($username);
                ?>

                <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px;">
                    <div style="font-size: 64px; margin-bottom: 20px;">📺</div>
                    <h3 style="color: #1f2937; margin-bottom: 12px; font-size: 24px;">Vista Previa de tu Parrilla</h3>
                    <p style="color: #6b7280; margin-bottom: 30px; font-size: 16px;">
                        Haz clic en el botón para ver tu parrilla de programación
                    </p>
                    <a href="<?php echo htmlspecialchars($widgetUrl); ?>"
                       target="_blank"
                       class="btn btn-primary"
                       style="display: inline-flex; align-items: center; gap: 8px; font-size: 16px; padding: 12px 24px;">
                        🔗 Abrir Parrilla en Nueva Pestaña
                    </a>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($section === 'programs'): ?>
        <!-- GESTIÓN DE PROGRAMAS -->
        <?php require_once 'views/parrilla_programs.php'; ?>

    <?php elseif ($section === 'config'): ?>
        <!-- CONFIGURACIÓN -->
        <div class="section">
            <h3>Configuración de AzuraCast</h3>

            <form method="POST">
                <input type="hidden" name="action" value="update_azuracast_config_user">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label>URL de la Página Pública del Stream:</label>
                    <input type="url"
                           name="stream_url"
                           value="<?php echo htmlEsc($azConfig['stream_url'] ?? ''); ?>"
                           placeholder="https://tu-servidor.com/public/tu_emisora">
                    <small style="color: #6b7280;">
                        URL de la página pública de tu emisora en AzuraCast. El badge "🔴 AHORA EN DIRECTO" enlazará a esta página.<br>
                        Ejemplo: <code>https://tu-servidor.com/public/tu_emisora</code>
                    </small>
                </div>

                <h4 style="margin-top: 30px; margin-bottom: 15px; color: #374151;">🎨 Personalización del Widget</h4>

                <div class="form-group">
                    <label>Color principal de la parrilla:</label>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="color"
                               name="widget_color"
                               value="<?php echo htmlEsc($widgetColor); ?>"
                               style="width: 80px; height: 40px; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer;"
                               onchange="document.querySelector('input[name=widget_color_text]').value = this.value">
                        <input type="text"
                               name="widget_color_text"
                               value="<?php echo htmlEsc($widgetColor); ?>"
                               pattern="^#[0-9A-Fa-f]{6}$"
                               placeholder="#10b981"
                               style="width: 120px; font-family: monospace;"
                               onchange="document.querySelector('input[name=widget_color]').value = this.value">
                        <small style="color: #6b7280;">Color para headers, bordes y acentos</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Estilo de las cards:</label>
                    <select name="widget_style">
                        <?php
                        $currentStyle = $azConfig['widget_style'] ?? 'modern';
                        $styles = [
                            'modern' => '🔲 Moderno (bordes suaves, sombras)',
                            'classic' => '📋 Clásico (bordes simples)',
                            'compact' => '📦 Compacto (menos espaciado)',
                            'minimal' => '⬜ Minimalista (sin bordes)'
                        ];
                        foreach ($styles as $value => $label):
                            $selected = $currentStyle === $value ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlEsc($value); ?>" <?php echo $selected; ?>>
                                <?php echo htmlEsc($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6b7280;">
                        Cambia el aspecto visual de las cards de programas
                    </small>
                </div>

                <div class="form-group">
                    <label>Tamaño de fuente:</label>
                    <select name="widget_font_size">
                        <?php
                        $currentFontSize = $azConfig['widget_font_size'] ?? 'medium';
                        $fontSizes = [
                            'small' => 'Pequeño',
                            'medium' => 'Mediano (recomendado)',
                            'large' => 'Grande'
                        ];
                        foreach ($fontSizes as $value => $label):
                            $selected = $currentFontSize === $value ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlEsc($value); ?>" <?php echo $selected; ?>>
                                <?php echo htmlEsc($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">
                    <span class="btn-icon">💾</span> Guardar Configuración
                </button>
            </form>
        </div>

    <?php elseif ($section === 'embed'): ?>
        <!-- CÓDIGO DE EMBEBIDO -->
        <div class="section">
            <h3>Código para Embedar en tu Web</h3>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #10b981; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <?php
                // Generar URL del widget - Forzar HTTPS para que funcione en sitios HTTPS
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = 'https://' . $host . dirname($_SERVER['PHP_SELF']);
                $widgetUrl = rtrim($baseUrl, '/') . '/parrilla_cards.php?station=' . urlencode($username);
                ?>

                <p style="color: #6b7280; margin-bottom: 20px;">
                    Copia este código HTML e insértalo en tu sitio web donde quieras mostrar la parrilla:
                </p>

                <div style="background: #1f2937; color: #e5e7eb; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; overflow-x: auto; position: relative;">
                    <button onclick="copyEmbedCode()"
                            style="position: absolute; top: 10px; right: 10px; background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        📋 Copiar
                    </button>
                    <pre id="embed-code" style="margin: 0; color: #e5e7eb; white-space: pre-wrap; word-wrap: break-word;">&lt;!-- Parrilla de Programación - <?php echo htmlEsc($_SESSION['station_name']); ?> --&gt;
&lt;iframe src="<?php echo htmlspecialchars($widgetUrl); ?>"
        width="100%"
        height="900"
        frameborder="0"
        style="border: none; border-radius: 8px;"
        title="Parrilla de Programación"&gt;
&lt;/iframe&gt;</pre>
                </div>

                <script>
                function copyEmbedCode() {
                    const code = document.getElementById('embed-code').textContent;
                    navigator.clipboard.writeText(code).then(function() {
                        alert('✅ Código copiado al portapapeles');
                    }, function() {
                        alert('❌ Error al copiar el código');
                    });
                }
                </script>

                <div style="margin-top: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #166534;">✅ Personalización</h4>
                    <p style="margin: 0; color: #166534; font-size: 14px;">
                        Puedes ajustar el <code>height</code> (altura) del iframe según el espacio disponible en tu web.<br>
                        Recomendado: entre 800 y 1200 píxeles.
                    </p>
                </div>

                <div style="margin-top: 15px; background: #fffbeb; border: 1px solid #fde68a; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #92400e;">💡 Consejo</h4>
                    <p style="margin: 0; color: #92400e; font-size: 14px;">
                        La parrilla se actualiza automáticamente con los cambios que hagas en AzuraCast y en la gestión de programas de SAPO.
                    </p>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($section === 'coverage'): ?>
        <!-- COBERTURA SEMANAL -->
        <div class="section">
            <h3>📊 Resumen de Cobertura Semanal</h3>
            <p style="color: #6b7280; margin-bottom: 20px;">
                Estadísticas de todos los tipos de contenido: programas, bloques musicales y emisiones en directo.
            </p>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #10b981; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <?php
                // Cargar schedule y programas
                $schedule = getAzuracastSchedule($username);
                if ($schedule === false) $schedule = [];

                $programsDB = loadProgramsDB($username);
                $programsData = $programsDB['programs'] ?? [];

                // Organizar contenido por día y tipo
                $contentByDay = [
                    1 => ['music_block' => [], 'program' => [], 'live' => []],
                    2 => ['music_block' => [], 'program' => [], 'live' => []],
                    3 => ['music_block' => [], 'program' => [], 'live' => []],
                    4 => ['music_block' => [], 'program' => [], 'live' => []],
                    5 => ['music_block' => [], 'program' => [], 'live' => []],
                    6 => ['music_block' => [], 'program' => [], 'live' => []],
                    0 => ['music_block' => [], 'program' => [], 'live' => []]
                ];

                // Primero: Añadir programas en directo (live) manuales
                foreach ($programsData as $programName => $programInfo) {
                    if (($programInfo['playlist_type'] ?? '') === 'live') {
                        if (!empty($programInfo['hidden_from_schedule'])) continue;

                        $scheduleDays = $programInfo['schedule_days'] ?? [];
                        $startTime = $programInfo['schedule_start_time'] ?? '';
                        $duration = (int)($programInfo['schedule_duration'] ?? 60);

                        if (!empty($scheduleDays) && !empty($startTime)) {
                            foreach ($scheduleDays as $day) {
                                $startDateTime = DateTime::createFromFormat('H:i', $startTime);
                                $endDateTime = clone $startDateTime;
                                $endDateTime->modify("+{$duration} minutes");

                                $contentByDay[$day]['live'][] = [
                                    'title' => $programInfo['display_title'] ?: $programName,
                                    'start_time' => $startDateTime->format('H:i'),
                                    'end_time' => $endDateTime->format('H:i'),
                                    'start_minutes' => (int)$startDateTime->format('H') * 60 + (int)$startDateTime->format('i'),
                                    'end_minutes' => (int)$endDateTime->format('H') * 60 + (int)$endDateTime->format('i')
                                ];
                            }
                        }
                    }
                }

                // Segundo: Añadir eventos de AzuraCast
                foreach ($schedule as $event) {
                    $title = $event['name'] ?? $event['playlist'] ?? 'Sin nombre';
                    $start = $event['start_timestamp'] ?? $event['start'] ?? null;
                    if ($start === null) continue;

                    $startDateTime = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
                    $timezone = new DateTimeZone('Europe/Madrid');
                    $startDateTime->setTimezone($timezone);

                    $dayOfWeek = (int)$startDateTime->format('w');

                    $programInfo = $programsData[$title] ?? null;
                    $playlistType = $programInfo['playlist_type'] ?? 'program';

                    // Omitir jingles y ocultos
                    if ($playlistType === 'jingles') continue;
                    if (!empty($programInfo['hidden_from_schedule'])) continue;

                    // Usar duración configurada en SAPO si existe, sino la de AzuraCast
                    $customDuration = isset($programInfo['schedule_duration']) ? (int)$programInfo['schedule_duration'] : 0;

                    if ($customDuration > 0) {
                        // Usar duración configurada en SAPO
                        $endDateTime = clone $startDateTime;
                        $endDateTime->modify("+{$customDuration} minutes");
                    } else {
                        // Usar duración de AzuraCast
                        $end = $event['end_timestamp'] ?? $event['end'] ?? null;
                        $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

                        if ($endDateTime) {
                            $endDateTime->setTimezone($timezone);
                        }

                        if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
                            $endDateTime = clone $startDateTime;
                            $endDateTime->modify('+1 hour');
                        }
                    }

                    $eventData = [
                        'title' => !empty($programInfo['display_title']) ? $programInfo['display_title'] : $title,
                        'start_time' => $startDateTime->format('H:i'),
                        'end_time' => $endDateTime->format('H:i'),
                        'start_minutes' => (int)$startDateTime->format('H') * 60 + (int)$startDateTime->format('i'),
                        'end_minutes' => (int)$endDateTime->format('H') * 60 + (int)$endDateTime->format('i')
                    ];

                    if ($playlistType === 'music_block') {
                        $contentByDay[$dayOfWeek]['music_block'][] = $eventData;
                    } else {
                        $contentByDay[$dayOfWeek]['program'][] = $eventData;
                    }
                }

                // Deduplicar eventos (mismo título y misma hora de inicio)
                foreach ($contentByDay as $day => &$types) {
                    foreach ($types as $type => &$items) {
                        $uniqueItems = [];
                        $seenKeys = [];

                        foreach ($items as $item) {
                            $normalizedTitle = trim(mb_strtolower($item['title']));
                            $uniqueKey = $normalizedTitle . '_' . $item['start_time'];

                            if (!isset($seenKeys[$uniqueKey])) {
                                $seenKeys[$uniqueKey] = true;
                                $uniqueItems[] = $item;
                            }
                        }

                        $items = $uniqueItems;
                    }
                }
                unset($types, $items);

                // Ordenar contenido por hora de inicio
                foreach ($contentByDay as $day => &$types) {
                    foreach ($types as $type => &$items) {
                        usort($items, function($a, $b) {
                            return $a['start_minutes'] - $b['start_minutes'];
                        });
                    }
                }
                unset($types, $items);

                $daysOfWeek = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
                ?>

                <style>
                    .coverage-day {
                        background: #f9fafb;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        padding: 16px;
                        margin-bottom: 15px;
                    }
                    .coverage-day-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 12px;
                        flex-wrap: wrap;
                        gap: 10px;
                    }
                    .coverage-day-name {
                        font-weight: 600;
                        font-size: 16px;
                        color: #1f2937;
                    }
                    .coverage-stats {
                        display: flex;
                        gap: 12px;
                        font-size: 12px;
                        flex-wrap: wrap;
                    }
                    .coverage-stat {
                        display: flex;
                        align-items: center;
                        gap: 4px;
                        padding: 4px 8px;
                        border-radius: 4px;
                        background: white;
                        border: 1px solid #e5e7eb;
                    }
                    .coverage-stat-value {
                        font-weight: 600;
                    }
                    .coverage-stat-label {
                        color: #6b7280;
                    }
                    .coverage-stat.music .coverage-stat-value { color: #8b5cf6; }
                    .coverage-stat.program .coverage-stat-value { color: #3b82f6; }
                    .coverage-stat.live .coverage-stat-value { color: #ef4444; }
                    .coverage-stat.total .coverage-stat-value { color: #10b981; }
                    .coverage-stat.error .coverage-stat-value { color: #dc2626; font-weight: 700; }
                    .coverage-stat.error { background: #fee2e2; border-color: #fecaca; }
                    .coverage-stat.ok .coverage-stat-value { color: #10b981; }
                    .coverage-timeline-container {
                        margin-bottom: 12px;
                    }
                    .coverage-timeline {
                        height: 24px;
                        background: #f3f4f6;
                        border-radius: 4px;
                        position: relative;
                        overflow: hidden;
                        border: 1px solid #e5e7eb;
                    }
                    .coverage-timeline-segment {
                        position: absolute;
                        top: 0;
                        height: 100%;
                        min-width: 1px;
                    }
                    .coverage-timeline-segment.music {
                        background: #8b5cf6;
                    }
                    .coverage-timeline-segment.program {
                        background: #3b82f6;
                    }
                    .coverage-timeline-segment.live {
                        background: #ef4444;
                    }
                    .coverage-timeline-segment:hover {
                        opacity: 0.8;
                    }
                    .coverage-timeline-segment.overlap {
                        background: repeating-linear-gradient(
                            45deg,
                            #dc2626,
                            #dc2626 3px,
                            #fca5a5 3px,
                            #fca5a5 6px
                        );
                        z-index: 10 !important;
                    }
                    .coverage-hour-ticks {
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        display: flex;
                        pointer-events: none;
                    }
                    .coverage-hour-tick {
                        flex: 1;
                        border-right: 1px solid rgba(0, 0, 0, 0.1);
                    }
                    .coverage-hour-tick:last-child {
                        border-right: none;
                    }
                    .coverage-hour-markers {
                        display: flex;
                        justify-content: space-between;
                        font-size: 8px;
                        color: #9ca3af;
                        margin-top: 2px;
                        padding: 0;
                    }
                    .coverage-hour-marker {
                        text-align: center;
                        flex: 1;
                    }
                    .coverage-hour-marker:first-child {
                        text-align: left;
                        flex: 0.5;
                    }
                    .coverage-hour-marker:last-child {
                        text-align: right;
                        flex: 0.5;
                    }
                    /* Alerts de solapamiento y huecos */
                    .coverage-alerts {
                        margin-top: 10px;
                    }
                    .coverage-alert {
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 11px;
                        margin-bottom: 6px;
                    }
                    .coverage-alert.warning {
                        background: #fef3c7;
                        border: 1px solid #f59e0b;
                        color: #92400e;
                    }
                    .coverage-alert.error {
                        background: #fee2e2;
                        border: 1px solid #ef4444;
                        color: #991b1b;
                    }
                    .coverage-alert.info {
                        background: #dbeafe;
                        border: 1px solid #3b82f6;
                        color: #1e40af;
                    }
                    .coverage-alert-title {
                        font-weight: 600;
                        margin-bottom: 4px;
                    }
                    .coverage-alert-details {
                        font-size: 10px;
                        opacity: 0.9;
                    }
                    /* Legacy support */
                    .coverage-progress-bar {
                        height: 12px;
                        background: #e5e7eb;
                        border-radius: 6px;
                        overflow: hidden;
                        margin-bottom: 12px;
                        display: flex;
                    }
                    .coverage-progress-segment {
                        height: 100%;
                        transition: width 0.3s ease;
                    }
                    .coverage-progress-segment.music {
                        background: #8b5cf6;
                    }
                    .coverage-progress-segment.program {
                        background: #3b82f6;
                    }
                    .coverage-progress-segment.live {
                        background: #ef4444;
                    }
                    .coverage-content-list {
                        font-size: 12px;
                        margin-top: 8px;
                    }
                    .coverage-content-title {
                        font-weight: 600;
                        color: #6b7280;
                        margin-bottom: 6px;
                        margin-top: 10px;
                    }
                    .coverage-content-title:first-child {
                        margin-top: 0;
                    }
                    .coverage-item {
                        display: inline-block;
                        padding: 3px 8px;
                        border-radius: 4px;
                        margin: 2px 4px 2px 0;
                        font-size: 11px;
                    }
                    .coverage-item.music {
                        background: #ede9fe;
                        color: #7c3aed;
                    }
                    .coverage-item.program {
                        background: #dbeafe;
                        color: #1d4ed8;
                    }
                    .coverage-item.live {
                        background: #fee2e2;
                        color: #dc2626;
                    }
                    .no-content-message {
                        color: #9ca3af;
                        font-style: italic;
                        font-size: 13px;
                    }
                    .summary-totals {
                        background: #f0fdf4;
                        border: 1px solid #bbf7d0;
                        border-radius: 8px;
                        padding: 16px;
                        margin-top: 20px;
                    }
                    .summary-totals h4 {
                        margin: 0 0 15px 0;
                        color: #166534;
                    }
                    .summary-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                        gap: 15px;
                    }
                    .summary-item {
                        text-align: center;
                        padding: 10px;
                        background: white;
                        border-radius: 8px;
                        border: 1px solid #bbf7d0;
                    }
                    .summary-value {
                        font-size: 20px;
                        font-weight: 700;
                    }
                    .summary-value.music { color: #8b5cf6; }
                    .summary-value.program { color: #3b82f6; }
                    .summary-value.live { color: #ef4444; }
                    .summary-value.total { color: #166534; }
                    .summary-label {
                        font-size: 11px;
                        color: #166534;
                        margin-top: 4px;
                    }
                    .coverage-legend {
                        display: flex;
                        gap: 15px;
                        margin-bottom: 15px;
                        font-size: 12px;
                        flex-wrap: wrap;
                    }
                    .legend-item {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                    }
                    .legend-color {
                        width: 12px;
                        height: 12px;
                        border-radius: 3px;
                    }
                    .legend-color.music { background: #8b5cf6; }
                    .legend-color.program { background: #3b82f6; }
                    .legend-color.live { background: #ef4444; }
                </style>

                <!-- Leyenda -->
                <div class="coverage-legend">
                    <div class="legend-item">
                        <div class="legend-color music"></div>
                        <span>Bloques Musicales</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color program"></div>
                        <span>Programas</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color live"></div>
                        <span>En Directo</span>
                    </div>
                </div>

                <?php
                // Totales semanales por tipo
                $weekTotals = ['music_block' => 0, 'program' => 0, 'live' => 0];
                $weekCounts = ['music_block' => 0, 'program' => 0, 'live' => 0];

                foreach ([1, 2, 3, 4, 5, 6, 0] as $day):
                    $dayContent = $contentByDay[$day];

                    // Calcular minutos reales por tipo
                    $realMinutes = ['music_block' => 0, 'program' => 0, 'live' => 0];
                    $countsByType = ['music_block' => 0, 'program' => 0, 'live' => 0];

                    foreach (['music_block', 'program', 'live'] as $type) {
                        foreach ($dayContent[$type] as $item) {
                            $duration = $item['end_minutes'] - $item['start_minutes'];
                            if ($duration < 0) $duration += 24 * 60;
                            $realMinutes[$type] += $duration;
                            $countsByType[$type]++;
                        }
                        $weekCounts[$type] += $countsByType[$type];
                    }

                    // Calcular cobertura efectiva:
                    // - Programas y directo tienen prioridad (tiempo real)
                    // - Bloques musicales = tiempo disponible restante (máximo)
                    $dayTotalMinutes = 24 * 60;
                    $programLiveMinutes = $realMinutes['program'] + $realMinutes['live'];

                    // Tiempo disponible para música = 24h - programas - directo
                    $availableForMusic = max(0, $dayTotalMinutes - $programLiveMinutes);

                    // Los bloques musicales efectivos son el mínimo entre:
                    // - El tiempo real programado de música
                    // - El tiempo disponible (para no exceder 100%)
                    $effectiveMusicMinutes = min($realMinutes['music_block'], $availableForMusic);

                    // Para mostrar en stats usamos los valores efectivos
                    $minutesByType = [
                        'music_block' => $effectiveMusicMinutes,
                        'program' => $realMinutes['program'],
                        'live' => $realMinutes['live']
                    ];

                    // Acumular totales semanales
                    $weekTotals['music_block'] += $effectiveMusicMinutes;
                    $weekTotals['program'] += $realMinutes['program'];
                    $weekTotals['live'] += $realMinutes['live'];

                    $totalMinutes = array_sum($minutesByType);
                    $totalPercentage = round(($totalMinutes / $dayTotalMinutes) * 100, 1);

                    // Porcentajes por tipo para la barra
                    $musicPct = round(($minutesByType['music_block'] / $dayTotalMinutes) * 100, 1);
                    $programPct = round(($minutesByType['program'] / $dayTotalMinutes) * 100, 1);
                    $livePct = round(($minutesByType['live'] / $dayTotalMinutes) * 100, 1);

                    // Formatear horas
                    $formatTime = function($minutes) {
                        $h = floor($minutes / 60);
                        $m = $minutes % 60;
                        return $h . 'h ' . $m . 'm';
                    };

                    $hasContent = $totalMinutes > 0;

                    // Detectar solapamientos solo entre programas y emisiones en directo
                    // Los bloques musicales NO generan solapes (son de menor prioridad, actúan como relleno)
                    $priorityEvents = [];
                    foreach (['program', 'live'] as $type) {
                        foreach ($dayContent[$type] as $item) {
                            $priorityEvents[] = [
                                'title' => $item['title'],
                                'start' => $item['start_minutes'],
                                'end' => $item['end_minutes'],
                                'start_time' => $item['start_time'],
                                'end_time' => $item['end_time'],
                                'type' => $type
                            ];
                        }
                    }

                    // Buscar solapamientos y calcular tiempo total solapado
                    $overlapSegments = [];
                    $totalOverlapMinutes = 0;
                    $eventCount = count($priorityEvents);
                    for ($i = 0; $i < $eventCount; $i++) {
                        for ($j = $i + 1; $j < $eventCount; $j++) {
                            $a = $priorityEvents[$i];
                            $b = $priorityEvents[$j];

                            // Manejar eventos que cruzan medianoche
                            $aEnd = $a['end'] <= $a['start'] ? $a['end'] + 1440 : $a['end'];
                            $bEnd = $b['end'] <= $b['start'] ? $b['end'] + 1440 : $b['end'];
                            $aStart = $a['start'];
                            $bStart = $b['start'];

                            // Detectar solapamiento
                            if ($aStart < $bEnd && $bStart < $aEnd) {
                                $overlapStart = max($aStart, $bStart);
                                $overlapEnd = min($aEnd, $bEnd);
                                $overlapMinutes = $overlapEnd - $overlapStart;
                                $totalOverlapMinutes += $overlapMinutes;

                                // Guardar segmento para visualización
                                $overlapSegments[] = [
                                    'start' => $overlapStart > 1440 ? $overlapStart - 1440 : $overlapStart,
                                    'end' => $overlapEnd > 1440 ? $overlapEnd - 1440 : $overlapEnd
                                ];
                            }
                        }
                    }

                    // Todos los eventos para cálculo de huecos (incluye bloques musicales)
                    $allEvents = [];
                    foreach (['program', 'live', 'music_block'] as $type) {
                        foreach ($dayContent[$type] as $item) {
                            $allEvents[] = [
                                'title' => $item['title'],
                                'start' => $item['start_minutes'],
                                'end' => $item['end_minutes'],
                                'start_time' => $item['start_time'],
                                'end_time' => $item['end_time'],
                                'type' => $type
                            ];
                        }
                    }

                    // Calcular tiempo total sin asignar
                    $totalGapMinutes = 0;
                    if (!empty($allEvents)) {
                        // Ordenar eventos por inicio
                        usort($allEvents, function($a, $b) {
                            return $a['start'] - $b['start'];
                        });

                        // Sumar huecos
                        $lastEnd = 0;
                        foreach ($allEvents as $event) {
                            $eventEnd = $event['end'] <= $event['start'] ? $event['end'] + 1440 : $event['end'];
                            if ($event['start'] > $lastEnd) {
                                $totalGapMinutes += $event['start'] - $lastEnd;
                            }
                            $lastEnd = max($lastEnd, $eventEnd > 1440 ? $eventEnd - 1440 : $eventEnd);
                        }
                        // Hueco hasta medianoche
                        if ($lastEnd < 1440) {
                            $totalGapMinutes += 1440 - $lastEnd;
                        }
                    } else {
                        $totalGapMinutes = 1440; // Todo el día sin asignar
                    }
                ?>
                    <div class="coverage-day">
                        <div class="coverage-day-header">
                            <div class="coverage-day-name"><?php echo $daysOfWeek[$day]; ?></div>
                            <div class="coverage-stats">
                                <?php if ($minutesByType['music_block'] > 0): ?>
                                <div class="coverage-stat music">
                                    <span class="coverage-stat-value"><?php echo $formatTime($minutesByType['music_block']); ?></span>
                                    <span class="coverage-stat-label">🎵</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($minutesByType['program'] > 0): ?>
                                <div class="coverage-stat program">
                                    <span class="coverage-stat-value"><?php echo $formatTime($minutesByType['program']); ?></span>
                                    <span class="coverage-stat-label">📻</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($minutesByType['live'] > 0): ?>
                                <div class="coverage-stat live">
                                    <span class="coverage-stat-value"><?php echo $formatTime($minutesByType['live']); ?></span>
                                    <span class="coverage-stat-label">🔴</span>
                                </div>
                                <?php endif; ?>
                                <div class="coverage-stat <?php echo $totalGapMinutes > 0 ? 'error' : 'ok'; ?>">
                                    <span class="coverage-stat-value"><?php echo $formatTime($totalGapMinutes); ?></span>
                                    <span class="coverage-stat-label">huecos</span>
                                </div>
                                <div class="coverage-stat <?php echo $totalOverlapMinutes > 0 ? 'error' : 'ok'; ?>">
                                    <span class="coverage-stat-value"><?php echo $formatTime($totalOverlapMinutes); ?></span>
                                    <span class="coverage-stat-label">solapes</span>
                                </div>
                            </div>
                        </div>

                        <div class="coverage-timeline-container">
                            <div class="coverage-timeline">
                                <?php
                                // Generar segmentos para cada tipo de contenido
                                // Prioridad: live > program > music (para que se vean encima)
                                $allSegments = [];

                                // Procesar cada tipo
                                foreach (['music_block', 'program', 'live'] as $type) {
                                    $cssClass = $type === 'music_block' ? 'music' : $type;
                                    foreach ($dayContent[$type] as $item) {
                                        $startMin = $item['start_minutes'];
                                        $endMin = $item['end_minutes'];

                                        // Manejar eventos que cruzan medianoche
                                        if ($endMin <= $startMin) {
                                            // Primer segmento: desde inicio hasta medianoche
                                            $allSegments[] = [
                                                'start' => $startMin,
                                                'end' => 1440,
                                                'type' => $cssClass,
                                                'title' => $item['title'],
                                                'time' => $item['start_time'] . ' - 00:00'
                                            ];
                                            // Segundo segmento: desde medianoche hasta fin
                                            if ($endMin > 0) {
                                                $allSegments[] = [
                                                    'start' => 0,
                                                    'end' => $endMin,
                                                    'type' => $cssClass,
                                                    'title' => $item['title'],
                                                    'time' => '00:00 - ' . $item['end_time']
                                                ];
                                            }
                                        } else {
                                            $allSegments[] = [
                                                'start' => $startMin,
                                                'end' => $endMin,
                                                'type' => $cssClass,
                                                'title' => $item['title'],
                                                'time' => $item['start_time'] . ' - ' . $item['end_time']
                                            ];
                                        }
                                    }
                                }

                                // Renderizar segmentos
                                foreach ($allSegments as $seg) {
                                    $leftPct = ($seg['start'] / 1440) * 100;
                                    $widthPct = (($seg['end'] - $seg['start']) / 1440) * 100;
                                    $zIndex = $seg['type'] === 'live' ? 3 : ($seg['type'] === 'program' ? 2 : 1);
                                    ?>
                                    <div class="coverage-timeline-segment <?php echo $seg['type']; ?>"
                                         style="left: <?php echo $leftPct; ?>%; width: <?php echo $widthPct; ?>%; z-index: <?php echo $zIndex; ?>;"
                                         title="<?php echo htmlspecialchars($seg['title'] . ' (' . $seg['time'] . ')'); ?>">
                                    </div>
                                <?php } ?>
                                <!-- Segmentos de solapamiento -->
                                <?php foreach ($overlapSegments as $overlap):
                                    $leftPct = ($overlap['start'] / 1440) * 100;
                                    $widthPct = (($overlap['end'] - $overlap['start']) / 1440) * 100;
                                ?>
                                    <div class="coverage-timeline-segment overlap"
                                         style="left: <?php echo $leftPct; ?>%; width: <?php echo $widthPct; ?>%;"
                                         title="⚠️ Solapamiento">
                                    </div>
                                <?php endforeach; ?>
                                <!-- Rayitas de hora -->
                                <div class="coverage-hour-ticks">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <div class="coverage-hour-tick"></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="coverage-hour-markers">
                                <?php for ($h = 0; $h <= 24; $h++): ?>
                                    <span class="coverage-hour-marker"><?php echo $h; ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <?php if (!$hasContent): ?>
                            <p class="no-content-message">No hay contenido programado para este día.</p>
                        <?php else: ?>
                            <div class="coverage-content-list">
                                <?php if (!empty($dayContent['program'])): ?>
                                    <div class="coverage-content-title">📻 Programas (<?php echo count($dayContent['program']); ?>):</div>
                                    <?php foreach ($dayContent['program'] as $item): ?>
                                        <span class="coverage-item program">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                            (<?php echo $item['start_time']; ?> - <?php echo $item['end_time']; ?>)
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($dayContent['live'])): ?>
                                    <div class="coverage-content-title">🔴 En Directo (<?php echo count($dayContent['live']); ?>):</div>
                                    <?php foreach ($dayContent['live'] as $item): ?>
                                        <span class="coverage-item live">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                            (<?php echo $item['start_time']; ?> - <?php echo $item['end_time']; ?>)
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($dayContent['music_block'])): ?>
                                    <div class="coverage-content-title">🎵 Bloques Musicales (<?php echo count($dayContent['music_block']); ?>):</div>
                                    <?php foreach ($dayContent['music_block'] as $item): ?>
                                        <span class="coverage-item music">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                            (<?php echo $item['start_time']; ?> - <?php echo $item['end_time']; ?>)
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Resumen semanal -->
                <?php
                $totalWeekMinutes = array_sum($weekTotals);
                $totalWeekAvailable = 7 * 24 * 60; // 10080 minutos
                $uncoveredMinutes = max(0, $totalWeekAvailable - $totalWeekMinutes);

                // Formatear tiempos
                $formatWeekTime = function($minutes) {
                    $h = floor($minutes / 60);
                    $m = $minutes % 60;
                    return $h . 'h ' . $m . 'm';
                };
                ?>
                <div class="summary-totals">
                    <h4>📈 Resumen Semanal</h4>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-value music"><?php echo $formatWeekTime($weekTotals['music_block']); ?></div>
                            <div class="summary-label">🎵 Bloques Musicales (<?php echo $weekCounts['music_block']; ?>)</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value program"><?php echo $formatWeekTime($weekTotals['program']); ?></div>
                            <div class="summary-label">📻 Programas (<?php echo $weekCounts['program']; ?>)</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value live"><?php echo $formatWeekTime($weekTotals['live']); ?></div>
                            <div class="summary-label">🔴 En Directo (<?php echo $weekCounts['live']; ?>)</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value total"><?php echo $formatWeekTime($totalWeekMinutes); ?></div>
                            <div class="summary-label">Total Semanal</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value <?php echo $uncoveredMinutes > 0 ? 'live' : 'total'; ?>"><?php echo $formatWeekTime($uncoveredMinutes); ?></div>
                            <div class="summary-label">Por cubrir</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($section === 'liquidsoap'): ?>
        <!-- GENERADOR LIQUIDSOAP -->
        <div class="section">
            <h3>🎛️ Generador de Código Liquidsoap</h3>
            <p style="color: #6b7280; margin-bottom: 20px;">
                Crea rotaciones personalizadas de playlists y genera el código para pegar en AzuraCast.
            </p>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #10b981; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <?php
                // Obtener configuración global
                $globalConfig = getConfig();

                // Obtener playlists de AzuraCast
                $playlists = getAzuracastPlaylists($username);
                if ($playlists === false) $playlists = [];

                // Obtener configuración actual de Liquidsoap
                $liquidsoapConfig = getAzuracastLiquidsoapConfig($username);
                $hasLiquidsoapConfig = is_array($liquidsoapConfig) && !empty($liquidsoapConfig);

                // Obtener nombre corto de la estación para el código
                $stationShortName = '';
                $azConfig = getAzuracastConfig($username);
                $stationId = $azConfig['station_id'] ?? null;
                if ($stationId && !empty($globalConfig['azuracast_api_url'])) {
                    $stationUrl = rtrim($globalConfig['azuracast_api_url'], '/') . '/station/' . $stationId;
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 5,
                            'user_agent' => 'SAPO/1.0',
                            'header' => 'X-API-Key: ' . ($globalConfig['azuracast_api_key'] ?? '')
                        ]
                    ]);
                    $stationResponse = @file_get_contents($stationUrl, false, $context);
                    if ($stationResponse !== false) {
                        $stationData = json_decode($stationResponse, true);
                        $stationShortName = $stationData['short_name'] ?? $stationData['name'] ?? '';
                    }
                }
                ?>

                <style>
                    .liquidsoap-form {
                        background: #f9fafb;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        padding: 20px;
                        margin-bottom: 20px;
                    }
                    .rotation-item {
                        background: white;
                        border: 1px solid #e5e7eb;
                        border-radius: 6px;
                        padding: 15px;
                        margin-bottom: 10px;
                        display: grid;
                        grid-template-columns: 1fr 100px 40px;
                        gap: 10px;
                        align-items: center;
                    }
                    .rotation-item select, .rotation-item input {
                        padding: 8px 12px;
                        border: 1px solid #d1d5db;
                        border-radius: 4px;
                        font-size: 14px;
                    }
                    .rotation-item input[type="number"] {
                        text-align: center;
                    }
                    .btn-remove {
                        background: #fee2e2;
                        color: #dc2626;
                        border: none;
                        border-radius: 4px;
                        padding: 8px;
                        cursor: pointer;
                        font-size: 16px;
                    }
                    .btn-remove:hover {
                        background: #fecaca;
                    }
                    .btn-add {
                        background: #dbeafe;
                        color: #1d4ed8;
                        border: none;
                        border-radius: 4px;
                        padding: 10px 15px;
                        cursor: pointer;
                        font-size: 14px;
                        margin-top: 10px;
                    }
                    .btn-add:hover {
                        background: #bfdbfe;
                    }
                    .schedule-config {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                        gap: 15px;
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #e5e7eb;
                    }
                    .schedule-config label {
                        display: block;
                        font-size: 12px;
                        color: #6b7280;
                        margin-bottom: 5px;
                    }
                    .schedule-config input, .schedule-config select {
                        width: 100%;
                        padding: 8px 12px;
                        border: 1px solid #d1d5db;
                        border-radius: 4px;
                        font-size: 14px;
                    }
                    .code-output {
                        background: #1f2937;
                        color: #e5e7eb;
                        padding: 20px;
                        border-radius: 8px;
                        font-family: 'Courier New', monospace;
                        font-size: 13px;
                        overflow-x: auto;
                        white-space: pre-wrap;
                        position: relative;
                        margin-top: 20px;
                    }
                    .code-output .copy-btn {
                        position: absolute;
                        top: 10px;
                        right: 10px;
                        background: #10b981;
                        color: white;
                        border: none;
                        padding: 6px 12px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 12px;
                    }
                    .code-output .copy-btn:hover {
                        background: #059669;
                    }
                    .rotation-header {
                        display: grid;
                        grid-template-columns: 1fr 100px 40px;
                        gap: 10px;
                        padding: 0 15px;
                        margin-bottom: 5px;
                        font-size: 12px;
                        color: #6b7280;
                        font-weight: 600;
                    }
                    .info-box {
                        background: #eff6ff;
                        border: 1px solid #bfdbfe;
                        border-radius: 8px;
                        padding: 15px;
                        margin-bottom: 20px;
                        font-size: 13px;
                        color: #1e40af;
                    }
                    .current-config {
                        background: #f0fdf4;
                        border: 1px solid #bbf7d0;
                        border-radius: 8px;
                        padding: 15px;
                        margin-bottom: 20px;
                    }
                    .current-config h4 {
                        margin: 0 0 15px 0;
                        color: #166534;
                        font-size: 14px;
                    }
                    .config-section {
                        background: white;
                        border: 1px solid #dcfce7;
                        border-radius: 6px;
                        margin-bottom: 10px;
                    }
                    .config-section:last-child {
                        margin-bottom: 0;
                    }
                    .config-section-header {
                        padding: 10px 15px;
                        background: #f0fdf4;
                        border-bottom: 1px solid #dcfce7;
                        font-weight: 600;
                        font-size: 13px;
                        color: #15803d;
                        cursor: pointer;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .config-section-header:hover {
                        background: #dcfce7;
                    }
                    .config-section-content {
                        padding: 10px 15px;
                        font-family: 'Courier New', monospace;
                        font-size: 12px;
                        white-space: pre-wrap;
                        max-height: 200px;
                        overflow-y: auto;
                        display: none;
                        background: #f9fafb;
                        color: #374151;
                    }
                    .config-section-content.expanded {
                        display: block;
                    }
                    .config-empty {
                        color: #9ca3af;
                        font-style: italic;
                        font-family: inherit;
                    }
                    .toggle-icon {
                        transition: transform 0.2s;
                    }
                    .toggle-icon.expanded {
                        transform: rotate(180deg);
                    }
                    .no-config {
                        color: #6b7280;
                        font-size: 13px;
                        padding: 10px;
                        text-align: center;
                    }
                </style>

                <div class="info-box">
                    💡 <strong>Cómo funciona:</strong> Añade playlists en el orden que quieres que se reproduzcan.
                    Indica cuántos audios de cada una. El código generado lo pegas en
                    <strong>Settings > Edit Liquidsoap Configuration</strong> en AzuraCast.
                </div>

                <!-- Configuración actual de Liquidsoap -->
                <div class="current-config">
                    <h4>📋 Configuración Actual de Liquidsoap</h4>
                    <?php if ($hasLiquidsoapConfig): ?>
                        <?php foreach ($liquidsoapConfig as $configItem): ?>
                            <?php
                            $field = $configItem['field'] ?? '';
                            $label = $configItem['label'] ?? $field;
                            $value = $configItem['value'] ?? '';
                            $hasValue = !empty(trim($value));
                            ?>
                            <div class="config-section">
                                <div class="config-section-header" onclick="toggleConfigSection(this)">
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                    <span class="toggle-icon">▼</span>
                                </div>
                                <div class="config-section-content">
                                    <?php if ($hasValue): ?>
                                        <?php echo htmlspecialchars($value); ?>
                                    <?php else: ?>
                                        <span class="config-empty">(Sin configuración personalizada)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <script>
                            function toggleConfigSection(header) {
                                const content = header.nextElementSibling;
                                const icon = header.querySelector('.toggle-icon');
                                content.classList.toggle('expanded');
                                icon.classList.toggle('expanded');
                            }
                        </script>
                    <?php else: ?>
                        <div class="no-config">
                            No se pudo obtener la configuración de Liquidsoap.
                            <?php if (empty($globalConfig['azuracast_api_key'] ?? '')): ?>
                                <br><small>Verifica que la API Key esté configurada en Administración.</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="liquidsoap-form">
                    <h4 style="margin: 0 0 15px 0; color: #374151;">Rotación de Playlists</h4>

                    <div class="rotation-header">
                        <span>Playlist</span>
                        <span>Nº Audios</span>
                        <span></span>
                    </div>

                    <div id="rotation-items">
                        <div class="rotation-item">
                            <select name="playlist[]" class="playlist-select">
                                <option value="">-- Seleccionar playlist --</option>
                                <?php foreach ($playlists as $playlist): ?>
                                    <option value="<?php echo htmlspecialchars($playlist['short_name'] ?? $playlist['name']); ?>">
                                        <?php echo htmlspecialchars($playlist['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="tracks[]" value="1" min="1" max="10">
                            <button type="button" class="btn-remove" onclick="removeRotationItem(this)">×</button>
                        </div>
                    </div>

                    <button type="button" class="btn-add" onclick="addRotationItem()">+ Añadir Playlist</button>

                    <div class="schedule-config">
                        <div>
                            <label>Título/Nombre</label>
                            <input type="text" id="rotation-title" placeholder="Ej: Programa Mañanas" maxlength="50">
                        </div>
                        <div>
                            <label>Hora inicio</label>
                            <input type="time" id="start-time" value="08:00">
                        </div>
                        <div>
                            <label>Hora fin</label>
                            <input type="time" id="end-time" value="10:00">
                        </div>
                        <div>
                            <label>Tipo de rotación</label>
                            <select id="rotation-type">
                                <option value="sequence">Secuencial (en orden)</option>
                                <option value="rotate">Rotación (alterna)</option>
                                <option value="random">Aleatorio</option>
                            </select>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" style="margin-top: 20px;" onclick="generateCode()">
                        🎵 Generar Código
                    </button>
                </div>

                <div id="code-container" style="display: none;">
                    <h4 style="margin: 0 0 10px 0; color: #374151;">Código Generado</h4>
                    <div class="code-output">
                        <button class="copy-btn" onclick="copyCode()">📋 Copiar</button>
                        <pre id="generated-code"></pre>
                    </div>

                    <div style="margin-top: 15px; background: #fef3c7; border: 1px solid #fde68a; padding: 15px; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #92400e;">⚠️ Importante - Código de Plantilla</h4>
                        <p style="margin: 0 0 10px 0; color: #92400e; font-size: 13px;">
                            Este código es una <strong>plantilla de referencia</strong>. Pasos a seguir:
                        </p>
                        <ol style="margin: 0; padding-left: 20px; color: #92400e; font-size: 13px;">
                            <li>Copia el código generado</li>
                            <li>Ve a AzuraCast → Settings → Edit Liquidsoap Configuration</li>
                            <li>Pega en la sección "Custom Configuration"</li>
                            <li>Guarda y reinicia el backend</li>
                        </ol>
                        <p style="margin: 10px 0 0 0; color: #92400e; font-size: 12px;">
                            <strong>Nota:</strong> Las rutas usan el nombre de tu estación automáticamente.
                        </p>
                    </div>
                </div>

                <script>
                    const stationShortName = '<?php echo htmlspecialchars($stationShortName); ?>';
                    const playlistOptions = `
                        <option value="">-- Seleccionar playlist --</option>
                        <?php foreach ($playlists as $playlist): ?>
                            <option value="<?php echo htmlspecialchars($playlist['short_name'] ?? $playlist['name']); ?>">
                                <?php echo htmlspecialchars($playlist['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    `;

                    function addRotationItem() {
                        const container = document.getElementById('rotation-items');
                        const item = document.createElement('div');
                        item.className = 'rotation-item';
                        item.innerHTML = `
                            <select name="playlist[]" class="playlist-select">
                                ${playlistOptions}
                            </select>
                            <input type="number" name="tracks[]" value="1" min="1" max="10">
                            <button type="button" class="btn-remove" onclick="removeRotationItem(this)">×</button>
                        `;
                        container.appendChild(item);
                    }

                    function removeRotationItem(btn) {
                        const items = document.querySelectorAll('.rotation-item');
                        if (items.length > 1) {
                            btn.parentElement.remove();
                        }
                    }

                    function generateCode() {
                        const items = document.querySelectorAll('.rotation-item');
                        const title = document.getElementById('rotation-title').value.trim() || 'Rotación sin nombre';
                        const startTime = document.getElementById('start-time').value;
                        const endTime = document.getElementById('end-time').value;
                        const rotationType = document.getElementById('rotation-type').value;

                        const playlists = [];
                        items.forEach(item => {
                            const select = item.querySelector('select');
                            const tracks = item.querySelector('input[type="number"]').value;
                            if (select.value) {
                                playlists.push({
                                    name: select.value,
                                    displayName: select.options[select.selectedIndex].text,
                                    tracks: parseInt(tracks)
                                });
                            }
                        });

                        if (playlists.length === 0) {
                            alert('Selecciona al menos una playlist');
                            return;
                        }

                        // Generar código Liquidsoap
                        let code = '# ========================================\n';
                        code += '# ' + title + '\n';
                        code += '# Generado por SAPO - ' + new Date().toLocaleDateString('es-ES') + '\n';
                        code += '# Horario: ' + startTime + ' - ' + endTime + '\n';
                        code += '# ========================================\n\n';

                        // Definir playlists
                        const stationPath = stationShortName || 'TU_ESTACION';
                        playlists.forEach((p, i) => {
                            const safeName = p.name.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
                            code += `# ${p.displayName}\n`;
                            code += `playlist_${safeName} = playlist(mode="randomize", "/var/azuracast/stations/${stationPath}/media/${p.name}")\n\n`;
                        });

                        // Generar rotación
                        code += '# Configuración de rotación\n';

                        if (rotationType === 'sequence') {
                            code += 'programa_rotacion = sequence([\n';
                            playlists.forEach((p, i) => {
                                const safeName = p.name.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
                                for (let j = 0; j < p.tracks; j++) {
                                    code += `  once(playlist_${safeName})`;
                                    if (i < playlists.length - 1 || j < p.tracks - 1) code += ',';
                                    code += '\n';
                                }
                            });
                            code += '])\n\n';
                        } else if (rotationType === 'rotate') {
                            const weights = playlists.map(p => p.tracks).join(',');
                            code += `programa_rotacion = rotate(weights=[${weights}], [\n`;
                            playlists.forEach((p, i) => {
                                const safeName = p.name.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
                                code += `  playlist_${safeName}`;
                                if (i < playlists.length - 1) code += ',';
                                code += '\n';
                            });
                            code += '])\n\n';
                        } else {
                            const weights = playlists.map(p => p.tracks).join(',');
                            code += `programa_rotacion = random(weights=[${weights}], [\n`;
                            playlists.forEach((p, i) => {
                                const safeName = p.name.replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
                                code += `  playlist_${safeName}`;
                                if (i < playlists.length - 1) code += ',';
                                code += '\n';
                            });
                            code += '])\n\n';
                        }

                        // Horario
                        const startH = startTime.split(':')[0];
                        const endH = endTime.split(':')[0];
                        code += '# Programación horaria\n';
                        code += `# Añade esto a tu switch principal:\n`;
                        code += `({ ${startH}h-${endH}h }, programa_rotacion),\n`;

                        document.getElementById('generated-code').textContent = code;
                        document.getElementById('code-container').style.display = 'block';
                    }

                    function copyCode() {
                        const code = document.getElementById('generated-code').textContent;
                        navigator.clipboard.writeText(code).then(() => {
                            alert('✅ Código copiado al portapapeles');
                        }).catch(() => {
                            alert('❌ Error al copiar');
                        });
                    }
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.tab-active:hover, .tab-inactive:hover {
    background: #f3f4f6;
}
</style>
