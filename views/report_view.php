<?php
// views/report_view.php - Vista del informe consolidado
// Variable $report debe estar definida antes de incluir este archivo
?>

<div class="report-container">
    <!-- Estadísticas Generales -->
    <div class="stats-grid">
        <div class="stat-card stat-success">
            <div class="stat-number"><?php echo htmlEsc($report['stats']['descargados']); ?></div>
            <div class="stat-label">Descargados</div>
            <div class="stat-average">
                ~<?php echo htmlEsc($report['promedios']['descargados_por_dia']); ?> por día
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-number"><?php echo htmlEsc($report['stats']['eliminados']); ?></div>
            <div class="stat-label">Eliminados</div>
            <div class="stat-average">
                ~<?php echo htmlEsc($report['promedios']['eliminados_por_dia']); ?> por día
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-number"><?php echo htmlEsc($report['total_dias']); ?></div>
            <div class="stat-label">Días con actividad</div>
            <div class="stat-average">
                <?php echo htmlEsc($report['fecha_inicio']); ?> - <?php echo htmlEsc($report['fecha_fin']); ?>
            </div>
        </div>

        <div class="stat-card stat-neutral">
            <div class="stat-number"><?php echo htmlEsc(count($report['top_podcasts'])); ?></div>
            <div class="stat-label">Podcasts activos</div>
            <div class="stat-average">
                con descargas
            </div>
        </div>
    </div>

    <!-- Últimos Episodios Descargados -->
    <?php
    // Combinar todos los episodios de todos los días
    $allEpisodes = [];
    if (!empty($report['podcasts_por_dia'])) {
        foreach ($report['podcasts_por_dia'] as $dayData) {
            foreach ($dayData['items'] as $item) {
                $allEpisodes[] = $item;
            }
        }
    }

    if (!empty($allEpisodes)):
    ?>
    <div class="report-section">
        <h4>🎙️ Últimos Episodios Descargados</h4>
        <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
            Mostrando los <?php echo htmlEsc(min(count($allEpisodes), 20)); ?> episodios más recientes
        </p>
        <div class="episodes-list">
            <?php foreach (array_slice($allEpisodes, 0, 20) as $episode): ?>
                <div class="episode-item">
                    <div class="episode-icon">🎙️</div>
                    <div class="episode-info">
                        <div class="episode-name">
                            <?php echo htmlEsc($episode['archivo']); ?>
                        </div>
                        <div class="episode-meta">
                            <?php echo htmlEsc($episode['podcast']); ?>
                            • <?php echo htmlEsc($episode['fecha']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Podcasts -->
    <?php if (!empty($report['top_podcasts'])): ?>
    <div class="report-section">
        <h4>🏆 Top 10 Podcasts Más Descargados</h4>
        <div class="top-podcasts-list">
            <?php
            $position = 1;
            foreach ($report['top_podcasts'] as $podcastName => $count):
            ?>
                <div class="top-podcast-item">
                    <div class="top-position">#<?php echo htmlEsc($position); ?></div>
                    <div class="top-podcast-name"><?php echo htmlEsc($podcastName); ?></div>
                    <div class="top-podcast-count"><?php echo htmlEsc($count); ?> episodios</div>
                </div>
            <?php
                $position++;
            endforeach;
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actividad Diaria -->
    <?php if (!empty($report['podcasts_por_dia'])): ?>
    <div class="report-section">
        <h4>📅 Actividad Diaria de Descargas</h4>
        <div class="daily-activity">
            <?php foreach ($report['podcasts_por_dia'] as $dayData): ?>
                <div class="day-activity-item">
                    <div class="day-header">
                        <span class="day-date"><?php echo htmlEsc($dayData['fecha']); ?></span>
                        <span class="day-count"><?php echo htmlEsc($dayData['count']); ?> descargas</span>
                    </div>
                    <div class="day-podcasts">
                        <?php foreach (array_slice($dayData['items'], 0, 5) as $item): ?>
                            <div class="day-podcast-mini">
                                <strong>• <?php echo htmlEsc($item['podcast']); ?></strong>
                                <br>
                                <span style="font-size: 12px; color: #718096; padding-left: 12px;">
                                    📄 <?php echo htmlEsc($item['archivo']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($dayData['items']) > 5): ?>
                            <div class="day-podcast-mini" style="color: #a0aec0; font-style: italic;">
                                ... y <?php echo htmlEsc(count($dayData['items']) - 5); ?> más
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Archivos Eliminados -->
    <?php if (!empty($report['eliminados_por_dia'])): ?>
    <div class="report-section">
        <h4>🗑️ Archivos Eliminados por Día</h4>
        <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
            Total: <?php echo htmlEsc($report['stats']['eliminados']); ?> archivos
            (<?php echo $report['stats']['eliminados_caducidad']; ?> por caducidad,
            <?php echo $report['stats']['eliminados_reemplazo']; ?> por reemplazo)
        </p>
        <div class="daily-activity">
            <?php foreach ($report['eliminados_por_dia'] as $dayData): ?>
                <div class="day-activity-item" style="border-left-color: #fc8181;">
                    <div class="day-header">
                        <span class="day-date"><?php echo htmlEsc($dayData['fecha']); ?></span>
                        <span class="day-count" style="background: #fc8181;"><?php echo htmlEsc($dayData['count']); ?> eliminados</span>
                    </div>
                    <div class="day-podcasts">
                        <?php foreach (array_slice($dayData['items'], 0, 5) as $item): ?>
                            <div class="day-podcast-mini">
                                • <?php echo htmlEsc($item['podcast']); ?>
                                <span style="color: #e53e3e; font-size: 12px;">(<?php echo htmlEsc($item['motivo']); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($dayData['items']) > 5): ?>
                            <div class="day-podcast-mini" style="color: #a0aec0; font-style: italic;">
                                ... y <?php echo htmlEsc(count($dayData['items']) - 5); ?> más
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Errores -->
    <?php if (!empty($report['errores_totales'])): ?>
    <div class="report-section">
        <h4>⚠️ Errores Detectados</h4>
        <div class="errors-list">
            <?php foreach ($report['errores_totales'] as $errorData): ?>
                <div class="error-item">
                    <strong>[<?php echo htmlEsc($errorData['fecha']); ?>]</strong>
                    <?php echo htmlEsc($errorData['error']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Carpetas Vacías -->
    <?php if (!empty($report['carpetas_vacias'])): ?>
    <div class="report-section">
        <h4>📁 Carpetas Vacías</h4>
        <div class="empty-folders-list">
            <?php foreach ($report['carpetas_vacias'] as $folder): ?>
                <div class="empty-folder-item">
                    <span class="folder-name"><?php echo htmlEsc($folder['nombre']); ?></span>
                    <span class="folder-days">Vacía desde hace <?php echo htmlEsc($folder['dias']); ?> días</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
