<?php
// includes/reports.php - Gestión de informes diarios

function getReportsPath($username) {
    $config = getConfig();
    $basePath = $config['base_path'];

    if (empty($basePath)) {
        return false;
    }

    // Validar username contra path traversal
    if (strpos($username, '..') !== false || strpos($username, DIRECTORY_SEPARATOR) !== false || strpos($username, '/') !== false) {
        return false;
    }

    return $basePath . DIRECTORY_SEPARATOR . $username . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Informes';
}

function getAvailableReports($username) {
    $reportsPath = getReportsPath($username);
    
    if (!$reportsPath || !is_dir($reportsPath)) {
        return [];
    }
    
    $reports = [];
    $files = glob($reportsPath . '/Informe_diario_*.log');
    
    if ($files === false) {
        return [];
    }
    
    foreach ($files as $file) {
        if (preg_match('/Informe_diario_(\d{2})_(\d{2})_(\d{4})\.log$/', basename($file), $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            
            $reports[] = [
                'file' => $file,
                'filename' => basename($file),
                'date' => "$year-$month-$day",
                'display_date' => "$day/$month/$year",
                'timestamp' => strtotime("$year-$month-$day")
            ];
        }
    }
    
    // Ordenar por fecha descendente (más reciente primero)
    usort($reports, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $reports;
}

function getLatestReport($username) {
    $reports = getAvailableReports($username);
    return !empty($reports) ? $reports[0] : null;
}

function parseReportFile($filepath) {
    if (!file_exists($filepath)) {
        return null;
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return null;
    }
    
    $report = [
        'raw_content' => $content,
        'emisora' => '',
        'fecha' => '',
        'stats' => [
            'descargados' => 0,
            'eliminados' => 0,
            'eliminados_caducidad' => 0,
            'eliminados_reemplazo' => 0
        ],
        'podcasts_hoy' => [],
        'podcasts_anteriores' => [],
        'eliminados_hoy' => [],
        'eliminados_anteriores' => [],
        'carpetas_vacias' => [],
        'errores_podget' => [],
        'emisiones_directo' => []
    ];
    
    $lines = explode("\n", $content);
    $section = '';
    $subsection = '';
    
    foreach ($lines as $line) {
        // NO hacer trim aquí para preservar espacios de indentación
        $line = rtrim($line); // Solo eliminar espacios al final
        
        // Detectar emisora y fecha
        if (preg_match('/Emisora:\s*(.+)/', $line, $matches)) {
            $report['emisora'] = trim($matches[1]);
        }
        if (preg_match('/Fecha:\s*(.+)/', $line, $matches)) {
            $report['fecha'] = trim($matches[1]);
        }
        
        // Estadísticas
        if (preg_match('/•\s*(\d+)\s+podcasts descargados/', $line, $matches)) {
            $report['stats']['descargados'] = intval($matches[1]);
        }
        if (preg_match('/•\s*(\d+)\s+archivos eliminados\s*\((\d+)\s+por caducidad,\s*(\d+)\s+por reemplazo\)/', $line, $matches)) {
            $report['stats']['eliminados'] = intval($matches[1]);
            $report['stats']['eliminados_caducidad'] = intval($matches[2]);
            $report['stats']['eliminados_reemplazo'] = intval($matches[3]);
        }
        
        // Detectar secciones
        if (strpos($line, 'Últimos podcasts descargados:') !== false) {
            $section = 'podcasts';
            continue;
        }
        if (strpos($line, 'Últimos archivos eliminados:') !== false) {
            $section = 'eliminados';
            continue;
        }
        if (strpos($line, 'Carpetas vacías:') !== false) {
            $section = 'carpetas_vacias';
            continue;
        }
        if (strpos($line, 'Errores Podget:') !== false) {
            $section = 'errores';
            continue;
        }
        if (strpos($line, 'Emisiones en directo:') !== false) {
            $section = 'emisiones';
            continue;
        }
        
        // Detectar subsecciones
        if (trim($line) === 'Hoy') {
            $subsection = 'hoy';
            continue;
        }
        if (strpos($line, 'Días anteriores') !== false) {
            $subsection = 'anteriores';
            continue;
        }
        
        // Parsear líneas de datos
        if ($section === 'podcasts' && !empty($line) && $line[0] === ' ') {
            if (preg_match('/^\s+(.+?)\s+-\s+\[(.+?)\]\s+(.+)$/', $line, $matches)) {
                $item = [
                    'podcast' => trim($matches[1]),
                    'fecha' => trim($matches[2]),
                    'archivo' => trim($matches[3])
                ];
                
                if ($subsection === 'hoy') {
                    $report['podcasts_hoy'][] = $item;
                } else if ($subsection === 'anteriores') {
                    $report['podcasts_anteriores'][] = $item;
                }
            }
        }
        
        if ($section === 'eliminados' && !empty($line) && $line[0] === ' ') {
            if (preg_match('/^\s+(.+?)\s+-\s+\[(.+?)\]\s+(.+?)\s+←\s+por\s+(.+)$/', $line, $matches)) {
                $item = [
                    'podcast' => trim($matches[1]),
                    'fecha' => trim($matches[2]),
                    'archivo' => trim($matches[3]),
                    'motivo' => trim($matches[4])
                ];
                
                if ($subsection === 'hoy') {
                    $report['eliminados_hoy'][] = $item;
                } else if ($subsection === 'anteriores') {
                    $report['eliminados_anteriores'][] = $item;
                }
            }
        }
        
        if ($section === 'carpetas_vacias' && !empty($line) && strpos($line, '- ') === 2) {
            if (preg_match('/^\s+-\s+(.+?)\s+\(vacía desde hace (\d+) días\)/', $line, $matches)) {
                $report['carpetas_vacias'][] = [
                    'nombre' => trim($matches[1]),
                    'dias' => intval($matches[2])
                ];
            }
        }
        
        if ($section === 'errores' && !empty($line) && strpos($line, '⚠️') !== false) {
            $report['errores_podget'][] = trim($line);
        }
        
        if ($section === 'emisiones' && !empty($line) && $line[0] === ' ' && $line !== '  Ninguna emisión en directo') {
            $report['emisiones_directo'][] = trim($line);
        }
    }
    
    return $report;
}

function generatePeriodReport($username, $days = 7) {
    $reportsPath = getReportsPath($username);
    
    if (!$reportsPath || !is_dir($reportsPath)) {
        return null;
    }
    
    // Obtener todos los informes disponibles
    $allReports = getAvailableReports($username);
    
    if (empty($allReports)) {
        return null;
    }
    
    // Filtrar informes de los últimos N días
    $cutoffDate = strtotime("-{$days} days");
    $periodReports = array_filter($allReports, function($report) use ($cutoffDate) {
        return $report['timestamp'] >= $cutoffDate;
    });
    
    if (empty($periodReports)) {
        return null;
    }
    
    // Inicializar el informe consolidado
    $consolidatedReport = [
        'periodo' => $days,
        'fecha_inicio' => date('d/m/Y', min(array_column($periodReports, 'timestamp'))),
        'fecha_fin' => date('d/m/Y', max(array_column($periodReports, 'timestamp'))),
        'total_dias' => count($periodReports),
        'stats' => [
            'descargados' => 0,
            'eliminados' => 0,
            'eliminados_caducidad' => 0,
            'eliminados_reemplazo' => 0
        ],
        'podcasts_por_dia' => [],
        'eliminados_por_dia' => [],
        'errores_totales' => [],
        'carpetas_vacias' => [],
        'top_podcasts' => []
    ];
    
    $podcastsCount = [];
    
    // Procesar cada informe del período
    foreach ($periodReports as $report) {
        $reportData = parseReportFile($report['file']);
        
        if (!$reportData) continue;
        
        // Acumular estadísticas
        $consolidatedReport['stats']['descargados'] += $reportData['stats']['descargados'];
        $consolidatedReport['stats']['eliminados'] += $reportData['stats']['eliminados'];
        $consolidatedReport['stats']['eliminados_caducidad'] += $reportData['stats']['eliminados_caducidad'];
        $consolidatedReport['stats']['eliminados_reemplazo'] += $reportData['stats']['eliminados_reemplazo'];
        
        // Agrupar podcasts por día
        if (!empty($reportData['podcasts_hoy'])) {
            $consolidatedReport['podcasts_por_dia'][$report['display_date']] = [
                'fecha' => $report['display_date'],
                'count' => count($reportData['podcasts_hoy']),
                'items' => $reportData['podcasts_hoy']
            ];
            
            // Contar podcasts para el top
            foreach ($reportData['podcasts_hoy'] as $podcast) {
                $podcastName = $podcast['podcast'];
                if (!isset($podcastsCount[$podcastName])) {
                    $podcastsCount[$podcastName] = 0;
                }
                $podcastsCount[$podcastName]++;
            }
        }
        
        // Agrupar eliminados por día
        if (!empty($reportData['eliminados_hoy'])) {
            $consolidatedReport['eliminados_por_dia'][$report['display_date']] = [
                'fecha' => $report['display_date'],
                'count' => count($reportData['eliminados_hoy']),
                'items' => $reportData['eliminados_hoy']
            ];
        }
        
        // Acumular errores
        if (!empty($reportData['errores_podget'])) {
            foreach ($reportData['errores_podget'] as $error) {
                $consolidatedReport['errores_totales'][] = [
                    'fecha' => $report['display_date'],
                    'error' => $error
                ];
            }
        }
        
        // Última versión de carpetas vacías
        if (!empty($reportData['carpetas_vacias'])) {
            $consolidatedReport['carpetas_vacias'] = $reportData['carpetas_vacias'];
        }
    }
    
    // Ordenar podcasts por día (más reciente primero)
    krsort($consolidatedReport['podcasts_por_dia']);
    krsort($consolidatedReport['eliminados_por_dia']);
    
    // Generar top podcasts
    arsort($podcastsCount);
    $consolidatedReport['top_podcasts'] = array_slice($podcastsCount, 0, 10, true);
    
    // Calcular promedios
    $consolidatedReport['promedios'] = [
        'descargados_por_dia' => $consolidatedReport['total_dias'] > 0 ? 
            round($consolidatedReport['stats']['descargados'] / $consolidatedReport['total_dias'], 1) : 0,
        'eliminados_por_dia' => $consolidatedReport['total_dias'] > 0 ? 
            round($consolidatedReport['stats']['eliminados'] / $consolidatedReport['total_dias'], 1) : 0
    ];
    
    return $consolidatedReport;
}

?>
