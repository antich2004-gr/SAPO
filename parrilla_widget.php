<?php
// parrilla_widget.php - Widget público de parrilla semanal
// Este archivo puede ser embebido en iframes

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';
require_once INCLUDES_DIR . '/azuracast.php';

// Obtener parámetro de estación
$station = $_GET['station'] ?? '';

if (empty($station)) {
    die('Error: Debe especificar una estación (?station=nombre)');
}

// Validar que la estación existe
$user = findUserByUsername($station);
if (!$user) {
    die('Error: Estación no encontrada');
}

// Obtener configuración de AzuraCast
$azConfig = getAzuracastConfig($station);
$stationName = $user['station_name'] ?? $station;
$widgetColor = $azConfig['widget_color'] ?? '#3b82f6';
$stationId = $azConfig['station_id'] ?? null;

if (!$stationId) {
    die('Error: Esta estación no tiene configurado el Station ID de AzuraCast');
}

// Obtener programación
$schedule = getAzuracastSchedule($station);
if ($schedule === false) {
    $schedule = [];
    $error = 'No se pudo obtener la programación';
}

// Formatear eventos para FullCalendar (con información adicional de SAPO)
$events = formatEventsForCalendar($schedule, $widgetColor, $station);

// Debug: registrar en error log
error_log("Parrilla Widget - Station: $station, Events: " . count($events));

/**
 * Ajustar brillo de un color hexadecimal
 */
function adjustBrightness($hex, $steps) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
               . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
               . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parrilla - <?php echo htmlspecialchars($stationName); ?></title>

    <!-- FullCalendar JS - versión local -->
    <script src='assets/fullcalendar.min.js'></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #fafafa;
            padding: 0;
            margin: 0;
            color: #1f2937;
        }

        .widget-container {
            max-width: 100%;
            margin: 0;
            background: #fafafa;
            min-height: 100vh;
        }

        .widget-header {
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            color: #1f2937;
            padding: 32px 40px 24px 40px;
            border-bottom: 2px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .widget-header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .widget-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: #111827;
            letter-spacing: -0.8px;
            line-height: 1.2;
        }

        .widget-header h1::before {
            content: "📻";
            margin-right: 12px;
            font-size: 28px;
        }

        .calendar-container {
            padding: 24px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        #calendar {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Personalización de FullCalendar */
        .fc {
            font-family: inherit;
        }

        .fc-toolbar {
            display: none !important;
        }

        /* Días de la semana - estilo mejorado */
        .fc-col-header-cell {
            background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%) !important;
            padding: 18px 10px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 13px !important;
            letter-spacing: 0.5px !important;
            color: #374151 !important;
            border: 1px solid #e5e7eb !important;
            border-top: 4px solid <?php echo htmlspecialchars($widgetColor); ?> !important;
        }

        .fc-col-header-cell-cushion {
            color: #374151 !important;
            text-decoration: none !important;
        }

        /* Celdas de horas */
        .fc-timegrid-slot {
            height: 50px !important;
            border-color: #e5e7eb !important;
            background: #ffffff !important;
            transition: background-color 0.2s ease;
        }

        .fc-timegrid-slot:hover {
            background: #fafafa !important;
        }

        .fc-timegrid-slot-label {
            font-size: 11px !important;
            color: #6b7280 !important;
            font-weight: 600 !important;
            padding: 0 10px !important;
            vertical-align: top !important;
            padding-top: 4px !important;
        }

        /* Eventos - estilo mejorado con sombras sutiles */
        .fc-event {
            border: none !important;
            border-left: 4px solid <?php echo htmlspecialchars($widgetColor); ?> !important;
            border-radius: 4px !important;
            padding: 6px 10px !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            background: #ffffff !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) !important;
            transition: all 0.2s ease !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .fc-event:hover {
            background: #f9fafb !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            transform: translateY(-1px);
            border-left-width: 5px !important;
            z-index: 100 !important;
        }

        .fc-event-title {
            font-weight: 600 !important;
            line-height: 1.3 !important;
            color: #1f2937 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: block !important;
        }

        .fc-event-time {
            font-size: 11px !important;
            color: #6b7280 !important;
            font-weight: 500 !important;
            display: block !important;
            margin-bottom: 2px !important;
        }

        /* Bloques musicales - estilo atenuado */
        .fc-event.music-block {
            background: #f3f4f6 !important;
            border-left-color: #9ca3af !important;
            opacity: 0.75;
        }

        .fc-event.music-block .fc-event-title {
            color: #6b7280 !important;
            font-weight: 500 !important;
            font-style: italic;
        }

        .fc-event.music-block:hover {
            opacity: 1;
            background: #e5e7eb !important;
        }

        .fc-event.music-block::after {
            content: "🎵";
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            opacity: 0.5;
        }

        .fc-timegrid-event {
            border-left: 4px solid rgba(255,255,255,0.5) !important;
        }

        /* Línea del NOW */
        .fc-timegrid-now-indicator-line {
            border-color: #ef4444 !important;
            border-width: 2px !important;
        }

        .fc-timegrid-now-indicator-arrow {
            border-color: #ef4444 !important;
        }

        /* Bordes y estructura - estilo mejorado */
        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: #e5e7eb !important;
        }

        .fc-scrollgrid {
            border: none !important;
            border-radius: 0 !important;
        }

        .fc-timegrid-divider {
            display: none !important;
        }

        /* Programa actual - destacar con color y animación */
        .fc-event.fc-event-now {
            background: <?php echo htmlspecialchars($widgetColor); ?> !important;
            border-left-color: <?php echo htmlspecialchars($widgetColor); ?> !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15), 0 0 0 3px <?php echo htmlspecialchars($widgetColor); ?>33 !important;
            animation: pulse-glow 2s ease-in-out infinite;
            position: relative;
        }

        .fc-event.fc-event-now::before {
            content: "🔴 EN VIVO";
            position: absolute;
            top: -8px;
            right: 8px;
            background: #ef4444;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
            animation: pulse-badge 2s ease-in-out infinite;
        }

        .fc-event.fc-event-now .fc-event-title {
            color: white !important;
            font-weight: 700 !important;
        }

        .fc-event.fc-event-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 3px <?php echo htmlspecialchars($widgetColor); ?>44 !important;
        }

        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15), 0 0 0 3px <?php echo htmlspecialchars($widgetColor); ?>33;
            }
            50% {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15), 0 0 0 5px <?php echo htmlspecialchars($widgetColor); ?>55;
            }
        }

        @keyframes pulse-badge {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.85;
            }
        }

        /* Ajustes para look tipo tabla */
        .fc-timegrid-axis {
            background: #fafafa !important;
        }

        .fc-timegrid-slot-lane {
            background: white !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                background: #ffffff;
            }

            .widget-container {
                background: #ffffff;
            }

            .widget-header {
                padding: 20px 16px;
            }

            .widget-header h1 {
                font-size: 22px;
            }

            .widget-header h1::before {
                font-size: 20px;
                margin-right: 8px;
            }

            .calendar-container {
                padding: 16px 8px;
            }

            #calendar {
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            }

            .fc-col-header-cell {
                padding: 12px 6px !important;
                font-size: 11px !important;
            }

            .fc-timegrid-slot {
                height: 50px !important;
            }

            .fc-timegrid-slot-label {
                font-size: 11px !important;
                padding: 0 8px !important;
            }

            .fc-event {
                padding: 8px 10px !important;
                font-size: 12px !important;
            }

            .fc-event.fc-event-now::before {
                font-size: 9px;
                padding: 2px 6px;
                top: -6px;
                right: 4px;
            }

            .powered-by {
                padding: 16px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .widget-header h1 {
                font-size: 18px;
            }

            .fc-col-header-cell {
                padding: 10px 4px !important;
                font-size: 10px !important;
            }
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin: 20px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .powered-by {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: #9ca3af;
            background: linear-gradient(180deg, #fafafa 0%, #f3f4f6 100%);
            border-top: 1px solid #e5e7eb;
            margin-top: 24px;
        }

        .powered-by a {
            color: <?php echo htmlspecialchars($widgetColor); ?>;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .powered-by a:hover {
            color: <?php echo adjustBrightness($widgetColor, -20); ?>;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="widget-container">
        <div class="widget-header">
            <div class="widget-header-content">
                <h1><?php echo htmlspecialchars($stationName); ?></h1>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="calendar-container">
            <div id="calendar"></div>

            <?php if (isset($_GET['debug'])): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                    <h3>🐛 Modo Debug</h3>
                    <p><strong>Station:</strong> <?php echo htmlspecialchars($station); ?></p>
                    <p><strong>Station ID:</strong> <?php echo htmlspecialchars($stationId ?? 'N/A'); ?></p>
                    <p><strong>Color:</strong> <span style="background: <?php echo htmlspecialchars($widgetColor); ?>; padding: 2px 10px; color: white; border-radius: 3px;"><?php echo htmlspecialchars($widgetColor); ?></span></p>
                    <p><strong>Total eventos:</strong> <?php echo count($events); ?></p>
                    <details>
                        <summary>Ver datos crudos de programación</summary>
                        <pre style="background: #1f2937; color: #e5e7eb; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px;"><?php echo json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                    </details>
                    <details>
                        <summary>Ver eventos formateados</summary>
                        <pre style="background: #1f2937; color: #e5e7eb; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px;"><?php echo json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                    </details>
                </div>
            <?php endif; ?>
        </div>

        <div class="powered-by">
            Generado con <a href="https://github.com/antich2004-gr/SAPO" target="_blank">SAPO</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando calendario...');

            var calendarEl = document.getElementById('calendar');
            console.log('Elemento calendario:', calendarEl);

            var events = <?php echo json_encode($events); ?>;
            console.log('Eventos cargados:', events);
            console.log('Total eventos:', events.length);

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                locale: 'es',
                timeZone: 'local',
                firstDay: 1, // Empezar semana en Lunes
                headerToolbar: {
                    left: '',
                    center: 'title',
                    right: ''
                },
                titleFormat: function() {
                    return 'Programación Semanal';
                },
                dayHeaderFormat: { weekday: 'long' },
                slotMinTime: '00:00:00',  // Mostrar día completo
                slotMaxTime: '24:00:00',
                slotDuration: '00:30:00', // Slots de 30 minutos
                slotLabelInterval: '01:00:00', // Etiquetas cada 1 hora
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                    omitZeroMinute: false
                },
                allDaySlot: false,
                height: 'auto',
                expandRows: true,
                slotEventOverlap: false,
                nowIndicator: true,  // Mostrar línea actual
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventDisplay: 'block',
                displayEventTime: true,  // Mostrar hora en el evento
                displayEventEnd: false,
                eventMinHeight: 30, // Altura mínima para eventos cortos
                events: events,
                eventClick: function(info) {
                    // Mostrar información del evento
                    var props = info.event.extendedProps;
                    var details = 'Programa: ' + info.event.title + '\n';
                    details += 'Inicio: ' + info.event.start.toLocaleString('es-ES') + '\n';
                    if (info.event.end) {
                        details += 'Fin: ' + info.event.end.toLocaleString('es-ES') + '\n';
                    }
                    if (props.description) {
                        details += '\nDescripción: ' + props.description + '\n';
                    }
                    if (props.programType) {
                        details += 'Temática: ' + props.programType + '\n';
                    }
                    if (props.programUrl) {
                        details += '\nMás información: ' + props.programUrl;
                    }
                    alert(details);
                },
                eventDidMount: function(info) {
                    // Añadir tooltip
                    var tooltip = info.event.title;
                    if (info.event.extendedProps.playlist) {
                        tooltip += ' (' + info.event.extendedProps.playlist + ')';
                    }
                    info.el.setAttribute('title', tooltip);

                    // Marcar evento actual (EN VIVO)
                    var now = new Date();
                    var eventStart = info.event.start;
                    var eventEnd = info.event.end;

                    if (eventStart && eventEnd && now >= eventStart && now < eventEnd) {
                        info.el.classList.add('fc-event-now');
                    }
                }
            });

            try {
                calendar.render();
                console.log('Calendario renderizado correctamente');
            } catch (error) {
                console.error('Error al renderizar calendario:', error);
                document.getElementById('calendar').innerHTML = '<div class="error-message">Error al cargar el calendario: ' + error.message + '</div>';
            }
        });
    </script>
</body>
</html>
