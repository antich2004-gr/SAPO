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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parrilla - <?php echo htmlspecialchars($stationName); ?></title>

    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f7fafc;
            padding: 20px;
        }

        .widget-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .widget-header {
            background: <?php echo htmlspecialchars($widgetColor); ?>;
            color: white;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .widget-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .widget-header .subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .calendar-container {
            padding: 20px;
        }

        #calendar {
            background: white;
        }

        /* Personalización de FullCalendar */
        .fc {
            font-family: inherit;
        }

        .fc-toolbar-title {
            font-size: 1.5em !important;
            font-weight: 600 !important;
            color: #1f2937;
        }

        .fc-button {
            background-color: <?php echo htmlspecialchars($widgetColor); ?> !important;
            border-color: <?php echo htmlspecialchars($widgetColor); ?> !important;
            text-transform: capitalize !important;
        }

        .fc-button:hover {
            opacity: 0.9;
        }

        .fc-button:disabled {
            opacity: 0.6;
        }

        .fc-event {
            border: none !important;
            border-radius: 4px !important;
            padding: 2px 4px !important;
            font-size: 12px !important;
        }

        .fc-event-title {
            font-weight: 500;
        }

        .fc-daygrid-event {
            margin: 1px 2px !important;
        }

        .fc-timegrid-event {
            border-left: 3px solid rgba(255,255,255,0.3) !important;
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
            <div>
                <h1>📅 <?php echo htmlspecialchars($stationName); ?></h1>
                <div class="subtitle">Parrilla de Programación Semanal</div>
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

    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/locales/es.global.min.js"></script>

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
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: 'Hoy',
                    week: 'Semana',
                    day: 'Día',
                    list: 'Lista'
                },
                slotMinTime: '00:00:00',
                slotMaxTime: '24:00:00',
                allDaySlot: false,
                height: 'auto',
                expandRows: true,
                slotEventOverlap: false,
                nowIndicator: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                events: events,
                eventClick: function(info) {
                    // Mostrar información del evento
                    var props = info.event.extendedProps;
                    var details = 'Programa: ' + info.event.title + '\n';
                    details += 'Inicio: ' + info.event.start.toLocaleString('es-ES') + '\n';
                    if (info.event.end) {
                        details += 'Fin: ' + info.event.end.toLocaleString('es-ES') + '\n';
                    }
                    if (props.playlist) {
                        details += 'Playlist: ' + props.playlist + '\n';
                    }
                    if (props.description) {
                        details += 'Descripción: ' + props.description;
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
