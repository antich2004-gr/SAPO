<?php
// views/audio_quality_view.php - Resultados del análisis de calidad de audio
// Variables esperadas: $scanResult (array), $username (string), $stationName (string)

$files         = $scanResult['files']          ?? [];
$totalScanned  = $scanResult['total_scanned']  ?? 0;
$totalIssues   = $scanResult['total_issues']   ?? 0;
$scanDate      = $scanResult['scan_date']      ?? date('d/m/Y H:i');

$errorFiles   = array_filter($files, fn($f) => $f['severity'] === 'error');
$warningFiles = array_filter($files, fn($f) => $f['severity'] === 'warning');

// Resumen de tipos de problemas
$issueTypeCounts = [];
$severityIcons = [
    'error'   => '&#10060;',
    'warning' => '&#9888;&#65039;',
];

$issueTypeLabels = [
    'integrity_error'      => 'Archivo corrupto',
    'missing_metadata'     => 'Metadatos incompletos',
    'low_bitrate'          => 'Bitrate bajo',
    'high_bitrate'         => 'Bitrate alto',
    'duration_too_short'   => 'Duración demasiado corta',
    'duration_too_long'    => 'Duración muy larga',
    'codec_mismatch'       => 'Codec no coincide',
    'unusual_sample_rate'  => 'Sample rate inusual',
    'leading_silence'      => 'Silencio al inicio',
    'trailing_silence'     => 'Silencio al final',
    'internal_silence'     => 'Silencio interno',
    'loudness_out_of_range'=> 'Nivel de volumen (LUFS)',
    'clipping'             => 'Saturación (clipping)',
];

foreach ($files as $f) {
    foreach ($f['issues'] as $issue) {
        $type = $issue['type'];
        if (!isset($issueTypeCounts[$type])) {
            $issueTypeCounts[$type] = ['error' => 0, 'warning' => 0];
        }
        $issueTypeCounts[$type][$issue['severity']]++;
    }
}
arsort($issueTypeCounts);
?>

<div class="report-container">

    <!-- Cabecera -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h4 style="margin: 0 0 5px 0;">Análisis de Calidad de Audio</h4>
            <p style="color: #718096; font-size: 13px; margin: 0;">
                Emisora: <strong><?php echo htmlEsc($stationName); ?></strong>
                &nbsp;·&nbsp; Escaneado: <?php echo htmlEsc($scanDate); ?>
            </p>
        </div>
    </div>

    <!-- Estadísticas generales -->
    <div class="stats-grid">
        <div class="stat-card stat-info">
            <div class="stat-number"><?php echo $totalScanned; ?></div>
            <div class="stat-label">Archivos analizados</div>
        </div>
        <div class="stat-card <?php echo count($errorFiles) > 0 ? 'stat-danger' : 'stat-success'; ?>">
            <div class="stat-number"><?php echo count($errorFiles); ?></div>
            <div class="stat-label">Con errores</div>
        </div>
        <div class="stat-card <?php echo count($warningFiles) > 0 ? 'stat-warning' : 'stat-success'; ?>">
            <div class="stat-number"><?php echo count($warningFiles); ?></div>
            <div class="stat-label">Con avisos</div>
        </div>
        <div class="stat-card stat-neutral">
            <div class="stat-number"><?php echo $totalScanned - count($files); ?></div>
            <div class="stat-label">Sin problemas</div>
        </div>
    </div>

    <?php if (empty($files)): ?>
    <div class="alert alert-success" style="margin-top: 20px;">
        &#10003; No se encontraron problemas de calidad en los <?php echo $totalScanned; ?> archivos analizados.
    </div>
    <?php else: ?>

    <!-- Resumen por tipo de problema -->
    <?php if (!empty($issueTypeCounts)): ?>
    <div class="report-section">
        <h4>Resumen por tipo de problema</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($issueTypeCounts as $type => $counts): ?>
            <div style="background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 14px; font-size: 13px;">
                <strong><?php echo htmlEsc($issueTypeLabels[$type] ?? $type); ?></strong>
                <?php if ($counts['error'] > 0): ?>
                    <span style="color: #e53e3e; margin-left: 6px;">&#10060; <?php echo $counts['error']; ?></span>
                <?php endif; ?>
                <?php if ($counts['warning'] > 0): ?>
                    <span style="color: #d69e2e; margin-left: 6px;">&#9888; <?php echo $counts['warning']; ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Archivos con errores -->
    <?php if (!empty($errorFiles)): ?>
    <div class="report-section">
        <h4>&#10060; Archivos con errores (<?php echo count($errorFiles); ?>)</h4>
        <div class="episodes-list">
            <?php foreach ($errorFiles as $fileData): ?>
            <div class="episode-item" style="border-left: 3px solid #fc8181; background: #fff5f5; flex-direction: column; align-items: flex-start; padding: 12px 15px;">
                <div style="font-family: monospace; font-size: 12px; color: #2d3748; word-break: break-all; margin-bottom: 8px;">
                    &#128266; <?php echo htmlEsc($fileData['path']); ?>
                    <span style="color: #a0aec0; margin-left: 8px;">(<?php echo self_format_bytes($fileData['size']); ?>)</span>
                </div>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px;">
                    <?php foreach ($fileData['issues'] as $issue): ?>
                    <?php if ($issue['severity'] === 'error'): ?>
                    <li style="color: #c53030; margin-bottom: 3px;">
                        <?php echo htmlEsc($issue['detail']); ?>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($fileData['issues'] as $issue): ?>
                    <?php if ($issue['severity'] === 'warning'): ?>
                    <li style="color: #b7791f; margin-bottom: 3px;">
                        &#9888; <?php echo htmlEsc($issue['detail']); ?>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Archivos con avisos -->
    <?php if (!empty($warningFiles)): ?>
    <div class="report-section">
        <h4>&#9888;&#65039; Archivos con avisos (<?php echo count($warningFiles); ?>)</h4>
        <div class="episodes-list">
            <?php foreach ($warningFiles as $fileData): ?>
            <div class="episode-item" style="border-left: 3px solid #f6ad55; background: #fffaf0; flex-direction: column; align-items: flex-start; padding: 12px 15px;">
                <div style="font-family: monospace; font-size: 12px; color: #2d3748; word-break: break-all; margin-bottom: 8px;">
                    &#128266; <?php echo htmlEsc($fileData['path']); ?>
                    <span style="color: #a0aec0; margin-left: 8px;">(<?php echo self_format_bytes($fileData['size']); ?>)</span>
                </div>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px;">
                    <?php foreach ($fileData['issues'] as $issue): ?>
                    <li style="color: #b7791f; margin-bottom: 3px;">
                        <?php echo htmlEsc($issue['detail']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // fin $files vacío ?>

</div>

<?php
// Helper local de formato de bytes (evita dependencia de función global)
function self_format_bytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
