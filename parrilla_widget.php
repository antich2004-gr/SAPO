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

// Formatear eventos para FullCalendar
$events = formatEventsForCalendar($schedule, $widgetColor);

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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #ffffff;
            padding: 0;
            margin: 0;
        }

        .widget-container {
            max-width: 100%;
            margin: 0;
            background: #ffffff;
            min-height: 100vh;
        }

        .widget-header {
            background: #ffffff;
            color: #333;
            padding: 30px 40px 20px 40px;
            border-bottom: 1px solid #e0e0e0;
        }

        .widget-header-content {
            max-width: 1400px;
            margin: 0 auto;
        }

        .widget-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            color: #222;
            letter-spacing: -0.5px;
        }

        .calendar-container {
            padding: 0;
            max-width: 1400px;
            margin: 0 auto;
        }

        #calendar {
            background: white;
        }

        /* Personalización de FullCalendar */
        .fc {
            font-family: inherit;
        }

        .fc-toolbar {
            display: none !important;
        }

        /* Días de la semana - estilo minimalista */
        .fc-col-header-cell {
            background: #f5f5f5 !important;
            padding: 16px 10px !important;
            font-weight: 600 !important;
            text-transform: capitalize !important;
            font-size: 15px !important;
            color: #333 !important;
            border: 1px solid #e0e0e0 !important;
            border-top: 3px solid <?php echo htmlspecialchars($widgetColor); ?> !important;
        }

        .fc-col-header-cell-cushion {
            color: #333 !important;
            text-decoration: none !important;
        }

        /* Celdas de horas */
        .fc-timegrid-slot {
            height: 60px !important;
            border-color: #e0e0e0 !important;
            background: #ffffff !important;
        }

        .fc-timegrid-slot-label {
            font-size: 13px !important;
            color: #666 !important;
            font-weight: 500 !important;
            padding: 0 12px !important;
        }

        /* Eventos - estilo minimalista tipo El Salto */
        .fc-event {
            border: 1px solid #ddd !important;
            border-left: 4px solid <?php echo htmlspecialchars($widgetColor); ?> !important;
            border-radius: 0 !important;
            padding: 8px 12px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            box-shadow: none !important;
            background: #fafafa !important;
        }

        .fc-event:hover {
            background: #f0f0f0 !important;
            border-left-width: 5px !important;
        }

        .fc-event-title {
            font-weight: 500 !important;
            line-height: 1.4 !important;
            color: #333 !important;
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

        /* Bordes y estructura - estilo tabla */
        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: #e0e0e0 !important;
        }

        .fc-scrollgrid {
            border: 1px solid #e0e0e0 !important;
            border-radius: 0 !important;
        }

        .fc-timegrid-divider {
            display: none !important;
        }

        /* Programa actual - destacar con color */
        .fc-event.fc-event-now {
            background: <?php echo htmlspecialchars($widgetColor); ?> !important;
            border-left-color: <?php echo htmlspecialchars($widgetColor); ?> !important;
        }

        .fc-event.fc-event-now .fc-event-title {
            color: white !important;
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
                padding: 10px;
            }

            .widget-header {
                padding: 15px 20px;
            }

            .widget-header h1 {
                font-size: 20px;
            }

            .calendar-container {
                padding: 10px;
            }

            .fc-toolbar {
                flex-direction: column !important;
                gap: 10px;
            }

            .fc-toolbar-chunk {
                width: 100%;
                display: flex;
                justify-content: center;
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
            padding: 15px;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }

        .powered-by a {
            color: <?php echo htmlspecialchars($widgetColor); ?>;
            text-decoration: none;
        }

        .powered-by a:hover {
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
                slotMinTime: '08:00:00',  // Empezar a las 8:00
                slotMaxTime: '32:00:00',  // Hasta las 8:00 del día siguiente (32h = 24h + 8h)
                allDaySlot: false,
                height: 'auto',
                expandRows: true,
                slotEventOverlap: false,
                nowIndicator: false,  // Ocultar línea NOW
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventDisplay: 'block',
                displayEventTime: false,  // No mostrar hora en el evento
                displayEventEnd: false,   // No mostrar hora final
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
