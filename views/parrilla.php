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
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
    $widgetUrl = rtrim($baseUrl, '/') . '/parrilla_widget.php?station=' . urlencode($username);
}
?>

<div class="card">
    <div class="nav-buttons">
        <h2>📺 Parrilla de Programación</h2>
        <div style="text-align: right;">
            <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <a href="?page=dashboard" class="btn btn-secondary">
                <span class="btn-icon">◀️</span> Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Navegación por pestañas -->
    <div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
        <div style="display: flex; gap: 0; flex-wrap: wrap;">
            <a href="?page=parrilla&section=preview"
               class="<?php echo $section === 'preview' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'preview' ? $widgetColor : 'transparent'; ?>; color: <?php echo $section === 'preview' ? $widgetColor : '#6b7280'; ?>; font-weight: <?php echo $section === 'preview' ? '600' : '400'; ?>; transition: all 0.2s;">
                📅 Vista Parrilla
            </a>
            <a href="?page=parrilla&section=cards"
               class="<?php echo $section === 'cards' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'cards' ? $widgetColor : 'transparent'; ?>; color: <?php echo $section === 'cards' ? $widgetColor : '#6b7280'; ?>; font-weight: <?php echo $section === 'cards' ? '600' : '400'; ?>; transition: all 0.2s;">
                🎴 Vista Fichas
            </a>
            <a href="?page=parrilla&section=programs"
               class="<?php echo $section === 'programs' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'programs' ? $widgetColor : 'transparent'; ?>; color: <?php echo $section === 'programs' ? $widgetColor : '#6b7280'; ?>; font-weight: <?php echo $section === 'programs' ? '600' : '400'; ?>; transition: all 0.2s;">
                📝 Gestión de Programas
            </a>
            <a href="?page=parrilla&section=config"
               class="<?php echo $section === 'config' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'config' ? $widgetColor : 'transparent'; ?>; color: <?php echo $section === 'config' ? $widgetColor : '#6b7280'; ?>; font-weight: <?php echo $section === 'config' ? '600' : '400'; ?>; transition: all 0.2s;">
                ⚙️ Configuración
            </a>
            <a href="?page=parrilla&section=embed"
               class="<?php echo $section === 'embed' ? 'tab-active' : 'tab-inactive'; ?>"
               style="padding: 12px 24px; text-decoration: none; border-bottom: 3px solid <?php echo $section === 'embed' ? $widgetColor : 'transparent'; ?>; color: <?php echo $section === 'embed' ? $widgetColor : '#6b7280'; ?>; font-weight: <?php echo $section === 'embed' ? '600' : '400'; ?>; transition: all 0.2s;">
                🔗 Código de Embebido
            </a>
        </div>
    </div>

    <!-- Contenido de las secciones -->
    <?php if ($section === 'preview'): ?>
        <!-- VISTA PREVIA DEL WIDGET -->
        <div class="section">
            <h3>Vista Previa de tu Parrilla</h3>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #3b82f6; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Así es como se ve tu parrilla de programación cuando se embebe en tu sitio web:
                </p>

                <div style="border: 2px solid #e0e0e0; border-radius: 8px; overflow: hidden; background: #f9fafb;">
                    <iframe src="<?php echo htmlspecialchars($widgetUrl); ?>"
                            style="width: 100%; height: 800px; border: none; display: block;"
                            title="Vista previa de la parrilla">
                    </iframe>
                </div>

                <div style="margin-top: 15px; text-align: center;">
                    <a href="<?php echo htmlspecialchars($widgetUrl); ?>"
                       target="_blank"
                       class="btn btn-secondary">
                        🔗 Abrir en nueva pestaña
                    </a>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($section === 'cards'): ?>
        <!-- VISTA DE FICHAS -->
        <div class="section">
            <h3>Vista de Fichas por Días</h3>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #3b82f6; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Vista de programación con fichas organizadas por días de la semana, similar a Cadena SER:
                </p>

                <?php
                // Generar URL del widget de fichas
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
                $cardsUrl = rtrim($baseUrl, '/') . '/parrilla_cards.php?station=' . urlencode($username);
                ?>

                <div style="border: 2px solid #e0e0e0; border-radius: 8px; overflow: hidden; background: #f9fafb;">
                    <iframe src="<?php echo htmlspecialchars($cardsUrl); ?>"
                            style="width: 100%; height: 800px; border: none; display: block;"
                            title="Vista de fichas de programación">
                    </iframe>
                </div>

                <div style="margin-top: 15px; text-align: center;">
                    <a href="<?php echo htmlspecialchars($cardsUrl); ?>"
                       target="_blank"
                       class="btn btn-secondary">
                        🔗 Abrir en nueva pestaña
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
                    <label>Station ID de AzuraCast: <small>(requerido)</small></label>
                    <input type="number"
                           name="station_id"
                           value="<?php echo htmlEsc($stationId ?? ''); ?>"
                           placeholder="34"
                           required>
                    <small style="color: #6b7280;">
                        Puedes encontrar el Station ID en la URL de tu estación en AzuraCast.<br>
                        Ejemplo: si tu URL es <code>radio.radiobot.org/station/34</code>, tu Station ID es <strong>34</strong>
                    </small>
                </div>

                <div class="form-group">
                    <label>Color del widget:</label>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="color"
                               name="widget_color"
                               value="<?php echo htmlEsc($widgetColor); ?>"
                               style="width: 80px; height: 40px; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer;">
                        <input type="text"
                               name="widget_color_text"
                               value="<?php echo htmlEsc($widgetColor); ?>"
                               pattern="^#[0-9A-Fa-f]{6}$"
                               placeholder="#3b82f6"
                               style="width: 120px; font-family: monospace;"
                               onchange="document.querySelector('input[name=widget_color]').value = this.value">
                        <small style="color: #6b7280;">Color para destacar el programa actual</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <span class="btn-icon">💾</span> Guardar Configuración
                </button>
            </form>
        </div>

        <?php if ($hasStationId): ?>
        <div class="section" style="background: #f0f9ff; border: 1px solid #bae6fd;">
            <h3>🧪 Probar Conexión</h3>
            <p style="color: #0c4a6e; margin-bottom: 15px;">
                Verifica que SAPO puede conectarse correctamente a tu estación en AzuraCast.
            </p>
            <a href="test_azuracast.php" target="_blank" class="btn btn-primary">
                🧪 Ejecutar Test de Conexión
            </a>
        </div>
        <?php endif; ?>

    <?php elseif ($section === 'embed'): ?>
        <!-- CÓDIGO DE EMBEBIDO -->
        <div class="section">
            <h3>Código para Embedar en tu Web</h3>

            <?php if (!$hasStationId): ?>
                <div class="alert alert-warning">
                    ⚠️ Primero debes configurar el <strong>Station ID de AzuraCast</strong> en la pestaña
                    <a href="?page=parrilla&section=config" style="color: #3b82f6; text-decoration: underline;">Configuración</a>
                </div>
            <?php else: ?>
                <?php
                // Generar URL del widget de fichas
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
                $cardsUrl = rtrim($baseUrl, '/') . '/parrilla_cards.php?station=' . urlencode($username);
                ?>

                <p style="color: #6b7280; margin-bottom: 20px;">
                    Tienes dos opciones de visualización para embedar en tu sitio web:
                </p>

                <!-- Vista Parrilla (FullCalendar) -->
                <h4 style="color: #1f2937; margin-bottom: 15px;">📅 Vista Parrilla (Calendario Semanal)</h4>
                <div style="background: #1f2937; color: #e5e7eb; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; overflow-x: auto; position: relative; margin-bottom: 30px;">
                    <button onclick="copyEmbedCodeGrid()"
                            style="position: absolute; top: 10px; right: 10px; background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        📋 Copiar
                    </button>
                    <pre id="embed-code-grid" style="margin: 0; color: #e5e7eb; white-space: pre-wrap; word-wrap: break-word;">&lt;!-- Parrilla de Programación (Vista Parrilla) - <?php echo htmlEsc($_SESSION['station_name']); ?> --&gt;
&lt;iframe src="<?php echo htmlspecialchars($widgetUrl); ?>"
        width="100%"
        height="800"
        frameborder="0"
        style="border: none; border-radius: 8px;"
        title="Parrilla de Programación"&gt;
&lt;/iframe&gt;</pre>
                </div>

                <!-- Vista Fichas (Cards) -->
                <h4 style="color: #1f2937; margin-bottom: 15px; margin-top: 30px;">🎴 Vista Fichas (Estilo Cadena SER)</h4>
                <div style="background: #1f2937; color: #e5e7eb; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; overflow-x: auto; position: relative; margin-bottom: 30px;">
                    <button onclick="copyEmbedCodeCards()"
                            style="position: absolute; top: 10px; right: 10px; background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        📋 Copiar
                    </button>
                    <pre id="embed-code-cards" style="margin: 0; color: #e5e7eb; white-space: pre-wrap; word-wrap: break-word;">&lt;!-- Parrilla de Programación (Vista Fichas) - <?php echo htmlEsc($_SESSION['station_name']); ?> --&gt;
&lt;iframe src="<?php echo htmlspecialchars($cardsUrl); ?>"
        width="100%"
        height="800"
        frameborder="0"
        style="border: none; border-radius: 8px;"
        title="Programación por Días"&gt;
&lt;/iframe&gt;</pre>
                </div>

                <script>
                function copyEmbedCodeGrid() {
                    const code = document.getElementById('embed-code-grid').textContent;
                    navigator.clipboard.writeText(code).then(function() {
                        alert('✅ Código de Vista Parrilla copiado al portapapeles');
                    }, function() {
                        alert('❌ Error al copiar el código');
                    });
                }

                function copyEmbedCodeCards() {
                    const code = document.getElementById('embed-code-cards').textContent;
                    navigator.clipboard.writeText(code).then(function() {
                        alert('✅ Código de Vista Fichas copiado al portapapeles');
                    }, function() {
                        alert('❌ Error al copiar el código');
                    });
                }
                </script>

                <div style="margin-top: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #166534;">✅ Personalización</h4>
                    <p style="margin: 0; color: #166534; font-size: 14px;">
                        Puedes ajustar el <code>height</code> (altura) del iframe según el espacio disponible en tu web.<br>
                        <strong>Vista Parrilla:</strong> Recomendado 600-1000 píxeles.<br>
                        <strong>Vista Fichas:</strong> Recomendado 800-1200 píxeles.
                    </p>
                </div>

                <div style="margin-top: 15px; background: #fffbeb; border: 1px solid #fde68a; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #92400e;">💡 Consejo</h4>
                    <p style="margin: 0; color: #92400e; font-size: 14px;">
                        Ambas vistas se actualizan automáticamente con los cambios que hagas en AzuraCast y en la gestión de programas de SAPO.<br>
                        <strong>Vista Parrilla:</strong> Ideal para ver toda la semana de un vistazo.<br>
                        <strong>Vista Fichas:</strong> Ideal para detalles de cada programa con imágenes y descripciones.
                    </p>
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
