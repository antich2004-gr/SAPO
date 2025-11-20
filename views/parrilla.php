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

                    $end = $event['end_timestamp'] ?? $event['end'] ?? null;
                    $endDateTime = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;

                    if ($endDateTime) {
                        $endDateTime->setTimezone($timezone);
                    }

                    if (!$endDateTime || $endDateTime->getTimestamp() <= $startDateTime->getTimestamp()) {
                        $endDateTime = clone $startDateTime;
                        $endDateTime->modify('+1 hour');
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

                    // Calcular minutos por tipo
                    $minutesByType = ['music_block' => 0, 'program' => 0, 'live' => 0];
                    $countsByType = ['music_block' => 0, 'program' => 0, 'live' => 0];

                    foreach (['music_block', 'program', 'live'] as $type) {
                        foreach ($dayContent[$type] as $item) {
                            $duration = $item['end_minutes'] - $item['start_minutes'];
                            if ($duration < 0) $duration += 24 * 60;
                            $minutesByType[$type] += $duration;
                            $countsByType[$type]++;
                        }
                        $weekTotals[$type] += $minutesByType[$type];
                        $weekCounts[$type] += $countsByType[$type];
                    }

                    $totalMinutes = array_sum($minutesByType);
                    $totalPercentage = round(($totalMinutes / (24 * 60)) * 100, 1);

                    // Porcentajes por tipo para la barra
                    $musicPct = round(($minutesByType['music_block'] / (24 * 60)) * 100, 1);
                    $programPct = round(($minutesByType['program'] / (24 * 60)) * 100, 1);
                    $livePct = round(($minutesByType['live'] / (24 * 60)) * 100, 1);

                    // Formatear horas
                    $formatTime = function($minutes) {
                        $h = floor($minutes / 60);
                        $m = $minutes % 60;
                        return $h . 'h ' . $m . 'm';
                    };

                    $hasContent = $totalMinutes > 0;
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
                                <div class="coverage-stat total">
                                    <span class="coverage-stat-value"><?php echo $totalPercentage; ?>%</span>
                                    <span class="coverage-stat-label">total</span>
                                </div>
                            </div>
                        </div>

                        <div class="coverage-progress-bar">
                            <?php if ($musicPct > 0): ?>
                                <div class="coverage-progress-segment music" style="width: <?php echo $musicPct; ?>%"></div>
                            <?php endif; ?>
                            <?php if ($programPct > 0): ?>
                                <div class="coverage-progress-segment program" style="width: <?php echo $programPct; ?>%"></div>
                            <?php endif; ?>
                            <?php if ($livePct > 0): ?>
                                <div class="coverage-progress-segment live" style="width: <?php echo $livePct; ?>%"></div>
                            <?php endif; ?>
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
                $weekPercentage = round(($totalWeekMinutes / (7 * 24 * 60)) * 100, 1);

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
                            <div class="summary-value total"><?php echo $weekPercentage; ?>%</div>
                            <div class="summary-label">Cobertura</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.tab-active:hover, .tab-inactive:hover {
    background: #f3f4f6;
}
</style>
