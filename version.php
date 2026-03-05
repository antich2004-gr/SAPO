<?php
// version.php - Endpoint para verificar la versión instalada de SAPO

require_once 'config.php';

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Determinar formato de respuesta
$format = $_GET['format'] ?? 'html';

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'version' => SAPO_VERSION,
        'version_date' => SAPO_VERSION_DATE,
        'git_commit' => exec('git rev-parse --short HEAD 2>/dev/null') ?: 'unknown'
    ], JSON_PRETTY_PRINT);
} else {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Versión de SAPO</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .version-card {
                background: white;
                border-radius: 8px;
                padding: 30px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                margin-top: 0;
                color: #333;
            }
            .version-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            .version-number {
                font-size: 2em;
                font-weight: bold;
                color: #007bff;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                margin: 10px 0;
                padding: 8px 0;
                border-bottom: 1px solid #dee2e6;
            }
            .info-label {
                font-weight: 600;
                color: #666;
            }
            .info-value {
                color: #333;
            }
            .footer {
                margin-top: 20px;
                font-size: 0.9em;
                color: #666;
                text-align: center;
            }
            code {
                background: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <div class="version-card">
            <h1>🎙️ SAPO - Sistema de Automatización de Parrilla Online</h1>

            <div class="version-info">
                <div class="info-row">
                    <span class="info-label">Versión:</span>
                    <span class="version-number"><?php echo htmlspecialchars(SAPO_VERSION); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha de versión:</span>
                    <span class="info-value"><?php echo htmlspecialchars(SAPO_VERSION_DATE); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Commit Git:</span>
                    <span class="info-value"><code><?php
                        $commit = exec('git rev-parse --short HEAD 2>/dev/null');
                        echo $commit ? htmlspecialchars($commit) : 'unknown';
                    ?></code></span>
                </div>
            </div>

            <div class="footer">
                <p>Para obtener la información en formato JSON: <code>version.php?format=json</code></p>
                <p>Para actualizar a la última versión, ejecuta: <code>git pull origin main</code></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
