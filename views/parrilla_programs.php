<?php
// views/parrilla_programs.php - Gestión de información de programas (subsección)

$programsData = getAllProgramsWithStats($username);
$editingProgram = $_GET['edit'] ?? null;
$creatingProgram = $_GET['create'] ?? null;
$showSavedMessage = isset($_GET['saved']) && $_GET['saved'] == '1';
?>

<div class="section">
    <?php if ($showSavedMessage): ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>✅ Guardado correctamente</strong> - La información del programa se ha actualizado.
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">Gestión de Programas</h3>
        <div style="display: flex; gap: 10px;">
            <a href="?page=parrilla&section=programs&create=1" class="btn btn-success">
                <span class="btn-icon">➕</span> Añadir Programa en Directo
            </a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="sync_programs">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-icon">🔄</span> Sincronizar con Radiobot
                </button>
            </form>
        </div>
    </div>

    <?php if ($programsData['last_sync']): ?>
        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="margin: 0; color: #4a5568; font-size: 14px;">
                <strong>Última sincronización:</strong> <?php echo htmlEsc($programsData['last_sync']); ?>
            </p>
            <p style="margin: 5px 0 0 0; color: #4a5568; font-size: 14px;">
                <strong>Total de programas:</strong> <?php echo $programsData['total']; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($creatingProgram !== null): ?>
        <!-- Formulario de creación de programa en directo -->
        <div class="section" style="background: #fffbeb; border: 2px solid #f59e0b;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">🔴 Añadir Programa en Directo</h3>
                <a href="?page=parrilla&section=programs" class="btn btn-secondary">❌ Cancelar</a>
            </div>

            <div style="background: #fef3c7; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
                <p style="margin: 0; color: #92400e; font-size: 14px;">
                    <strong>ℹ️ Información:</strong> Los programas en directo se destacan con estilo especial (fondo amarillo/dorado) en la parrilla de programación.
                </p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="create_program">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="playlist_type" value="live">

                <div class="form-group">
                    <label>Nombre del programa: <small>(requerido)</small></label>
                    <input type="text" name="program_name" required
                           placeholder="La Mañana en Directo"
                           maxlength="200">
                    <small style="color: #6b7280;">
                        Este nombre se mostrará en la parrilla de programación
                    </small>
                </div>

                <div class="form-group">
                    <label>Título personalizado: <small>(opcional - si está vacío se usa el nombre de la playlist)</small></label>
                    <input type="text" name="display_title"
                           placeholder="Ej: El Despertador Matinal"
                           maxlength="100">
                    <small style="color: #6b7280; display: block; margin-top: 5px;">
                        💡 Este título aparecerá en las cards. Si lo dejas vacío, se mostrará el nombre de la playlist de Radiobot.
                    </small>
                </div>

                <div class="form-group">
                    <label>Descripción corta: <small>(para cards y previews)</small></label>
                    <input type="text" name="short_description"
                           placeholder="Programa de música alternativa de los 90"
                           maxlength="300">
                </div>

                <div class="form-group">
                    <label style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📅 Horarios de Emisión:</span>
                        <button type="button" onclick="addScheduleSlot()" class="btn" style="background: #10b981; color: white; padding: 6px 12px; font-size: 13px;">
                            <span>➕</span> Añadir Horario
                        </button>
                    </label>
                    <small style="display: block; margin-bottom: 15px; color: #6b7280;">
                        Puedes añadir múltiples horarios si el programa se emite en diferentes días/horas
                    </small>

                    <div id="schedule-slots-container">
                        <!-- Por defecto, crear un slot vacío -->
                        <div class="schedule-slot" data-slot-index="0">
                            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <strong style="color: #374151; font-size: 14px;">Horario #1</strong>
                                    <button type="button" onclick="removeScheduleSlot(this)" class="btn" style="background: #dc2626; color: white; padding: 4px 8px; font-size: 12px; display: none;">
                                        🗑️ Eliminar
                                    </button>
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <label style="font-size: 13px; color: #6b7280; margin-bottom: 8px; display: block;">Días de emisión:</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php
                                        $days = [
                                            '1' => 'L', '2' => 'M', '3' => 'X',
                                            '4' => 'J', '5' => 'V', '6' => 'S', '0' => 'D'
                                        ];
                                        foreach ($days as $value => $label):
                                        ?>
                                            <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                                                <input type="checkbox" name="schedule_slots[0][days][]" value="<?php echo $value; ?>" style="margin-right: 5px; cursor: pointer;">
                                                <span><?php echo $label; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <label style="font-size: 13px; color: #6b7280; margin-bottom: 6px; display: block;">Hora inicio:</label>
                                        <input type="time" name="schedule_slots[0][start_time]" value=""
                                               style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                                    </div>
                                    <div>
                                        <label style="font-size: 13px; color: #6b7280; margin-bottom: 6px; display: block;">Duración:</label>
                                        <select name="schedule_slots[0][duration]"
                                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                                            <option value="15">15min</option>
                                            <option value="30">30min</option>
                                            <option value="45">45min</option>
                                            <option value="60" selected>1h</option>
                                            <option value="90">1h 30m</option>
                                            <option value="120">2h</option>
                                            <option value="150">2h 30m</option>
                                            <option value="180">3h</option>
                                            <option value="240">4h</option>
                                            <option value="300">5h</option>
                                            <option value="360">6h</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <small style="color: #6b7280; display: block; margin-top: 10px;">
                        💡 Si no seleccionas ningún día, el programa solo aparecerá cuando Radiobot lo programe
                    </small>
                </div>

                <div class="form-group">
                    <label>Temática:</label>
                    <select name="type">
                        <option value="">-- Sin especificar --</option>
                        <?php
                        $types = ['Musical', 'Informativo', 'Cultural', 'Deportivo', 'Entretenimiento', 'Educativo', 'Político', 'Magazine', 'Tertulia', 'Otro'];
                        foreach ($types as $type):
                        ?>
                            <option value="<?php echo htmlEsc($type); ?>">
                                <?php echo htmlEsc($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>URL del programa: <small>(web con más información)</small></label>
                    <input type="url" name="url"
                           placeholder="https://turadio.com/programas/alternativa">
                </div>

                <div class="form-group">
                    <label>URL de imagen: <small>(logo o portada del programa)</small></label>
                    <input type="url" name="image"
                           placeholder="https://turadio.com/img/programas/alternativa.jpg">
                </div>

                <div class="form-group">
                    <label>Presentadores: <small>(separados por comas)</small></label>
                    <input type="text" name="presenters"
                           placeholder="Ana García, Carlos Ruiz">
                </div>

                <div class="form-group">
                    <label>X (Twitter): <small>(handle sin @ o URL completa)</small></label>
                    <input type="text" name="social_twitter"
                           placeholder="alternativa90 o https://x.com/alternativa90">
                </div>

                <div class="form-group">
                    <label>Instagram: <small>(handle sin @ o URL completa)</small></label>
                    <input type="text" name="social_instagram"
                           placeholder="alternativa90 o https://instagram.com/alternativa90">
                </div>

                <div class="form-group">
                    <label>Mastodon: <small>(usuario completo @usuario@servidor o URL)</small></label>
                    <input type="text" name="social_mastodon"
                           placeholder="@programa@mastodon.social o https://mastodon.social/@programa">
                </div>

                <div class="form-group">
                    <label>Bluesky: <small>(handle completo o URL)</small></label>
                    <input type="text" name="social_bluesky"
                           placeholder="programa.bsky.social o https://bsky.app/profile/programa.bsky.social">
                </div>

                <div class="form-group">
                    <label>Facebook: <small>(nombre de página o URL completa)</small></label>
                    <input type="text" name="social_facebook"
                           placeholder="alternativa90 o https://facebook.com/alternativa90">
                </div>

                <div class="form-group">
                    <label>Feed RSS del podcast: <small>(opcional)</small></label>
                    <input type="url" name="rss_feed"
                           placeholder="https://feeds.feedburner.com/mipodcast">
                    <small style="color: #6b7280;">
                        Si tienes un podcast RSS, pega aquí la URL del feed. Se mostrará el último episodio publicado en la parrilla.
                    </small>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-success">
                        <span class="btn-icon">💾</span> Añadir Programa en Directo
                    </button>
                    <a href="?page=parrilla&section=programs" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($editingProgram !== null): ?>
        <!-- Formulario de edición -->
        <?php
        $programInfo = null;
        foreach ($programsData['programs'] as $prog) {
            if ($prog['name'] === $editingProgram) {
                $programInfo = $prog['info'];
                break;
            }
        }

        if ($programInfo !== null):
        ?>
            <div class="section" style="background: #f0f9ff; border: 2px solid #3b82f6;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Editar: <?php echo htmlEsc($editingProgram); ?></h3>
                    <a href="?page=parrilla&section=programs" class="btn btn-secondary">❌ Cancelar</a>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_program">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="program_name" value="<?php echo htmlEsc($editingProgram); ?>">

                    <div class="form-group">
                        <label>Tipo de lista de reproducción: <small>(importante para la parrilla)</small></label>
                        <?php
                        $currentType = $programInfo['playlist_type'] ?? 'program';
                        $isLiveProgram = ($currentType === 'live');

                        // Si es tipo 'live' es un programa manual y no se puede cambiar
                        if ($isLiveProgram):
                        ?>
                        <select name="playlist_type" required disabled>
                            <option value="live" selected>🟢 Emisión en Directo (programa manual)</option>
                        </select>
                        <input type="hidden" name="playlist_type" value="live">
                        <small style="color: #6b7280;">
                            <em>💡 Los programas en directo añadidos manualmente no pueden cambiar de tipo.</em>
                        </small>
                        <?php else:
                            // Programas importados pueden cambiar entre program, music_block, jingles
                            $playlistTypes = [
                                'program' => '📻 Programa (se muestra en la parrilla)',
                                'music_block' => '🎵 Bloque Musical (en timeline)',
                                'jingles' => '🔊 Jingles/Cortinillas (oculto)'
                            ];
                        ?>
                        <select name="playlist_type" required>
                            <?php foreach ($playlistTypes as $value => $label):
                                $selected = $currentType === $value ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlEsc($value); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlEsc($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">
                            • <strong>Programa</strong>: Contenido producido (repeticiones, podcast)<br>
                            • <strong>Bloque Musical</strong>: Música automatizada (se muestra en el timeline)<br>
                            • <strong>Jingles/Cortinillas</strong>: Efectos de audio (se ocultan de la parrilla)<br>
                            <em>💡 Los programas importados no pueden cambiarse a "En Directo". Para añadir programas en directo, usa el formulario de la izquierda.</em>
                        </small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="hidden_from_schedule" value="1"
                                   <?php echo !empty($programInfo['hidden_from_schedule']) ? 'checked' : ''; ?>
                                   style="width: 18px; height: 18px; cursor: pointer;">
                            <span>Ocultar en la parrilla</span>
                        </label>
                        <small style="color: #6b7280; display: block; margin-top: 5px;">
                            Si está marcado, este programa/bloque no aparecerá en la parrilla pública.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Título personalizado: <small>(opcional - si está vacío se usa el nombre de la playlist)</small></label>
                        <input type="text" name="display_title"
                               value="<?php echo htmlEsc($programInfo['display_title'] ?? ''); ?>"
                               placeholder="Ej: El Despertador Matinal"
                               maxlength="100">
                        <small style="color: #6b7280; display: block; margin-top: 5px;">
                            💡 Este título aparecerá en las cards. Si lo dejas vacío, se mostrará el nombre de la playlist: <strong><?php echo htmlEsc($programName); ?></strong>
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Descripción corta: <small>(para cards y previews)</small></label>
                        <input type="text" name="short_description"
                               value="<?php echo htmlEsc($programInfo['short_description'] ?? ''); ?>"
                               placeholder="Programa de música alternativa de los 90"
                               maxlength="300">
                    </div>

                    <?php
                    // Mostrar campos de horario solo si es programa en directo
                    $isLiveProgram = ($programInfo['playlist_type'] ?? 'program') === 'live';
                    if ($isLiveProgram):
                        // ====== MIGRACIÓN AUTOMÁTICA: Formato antiguo → Nuevo ======
                        $scheduleSlots = [];

                        // PRIORIDAD 1: Leer schedule_slots (formato nuevo)
                        if (!empty($programInfo['schedule_slots'])) {
                            $scheduleSlots = $programInfo['schedule_slots'];
                        }
                        // PRIORIDAD 2: Migrar desde formato antiguo
                        elseif (!empty($programInfo['schedule_days'])) {
                            $scheduleSlots = [[
                                'days' => $programInfo['schedule_days'],
                                'start_time' => $programInfo['schedule_start_time'] ?? '',
                                'duration' => intval($programInfo['schedule_duration'] ?? 60)
                            ]];
                        }

                        // Si no hay slots, crear uno vacío
                        if (empty($scheduleSlots)) {
                            $scheduleSlots = [[
                                'days' => [],
                                'start_time' => '',
                                'duration' => 60
                            ]];
                        }
                    ?>
                        <div class="form-group">
                            <label style="display: flex; justify-content: space-between; align-items: center;">
                                <span>📅 Horarios de Emisión:</span>
                                <button type="button" onclick="addScheduleSlot()" class="btn" style="background: #10b981; color: white; padding: 6px 12px; font-size: 13px;">
                                    <span>➕</span> Añadir Horario
                                </button>
                            </label>
                            <small style="display: block; margin-bottom: 15px; color: #6b7280;">
                                Puedes añadir múltiples horarios si el programa se emite en diferentes días/horas
                            </small>

                            <div id="schedule-slots-container">
                                <?php foreach ($scheduleSlots as $index => $slot): ?>
                                <div class="schedule-slot" data-slot-index="<?php echo $index; ?>">
                                    <div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 15px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                            <strong style="color: #374151; font-size: 14px;">Horario #<?php echo $index + 1; ?></strong>
                                            <button type="button" onclick="removeScheduleSlot(this)" class="btn" style="background: #dc2626; color: white; padding: 4px 8px; font-size: 12px; <?php echo count($scheduleSlots) <= 1 ? 'display: none;' : ''; ?>">
                                                🗑️ Eliminar
                                            </button>
                                        </div>

                                        <div style="margin-bottom: 12px;">
                                            <label style="font-size: 13px; color: #6b7280; margin-bottom: 8px; display: block;">Días de emisión:</label>
                                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                                <?php
                                                $days = [
                                                    '1' => 'L', '2' => 'M', '3' => 'X',
                                                    '4' => 'J', '5' => 'V', '6' => 'S', '0' => 'D'
                                                ];
                                                $slotDays = $slot['days'] ?? [];
                                                foreach ($days as $value => $label):
                                                    $checked = in_array((int)$value, (array)$slotDays) ? 'checked' : '';
                                                ?>
                                                    <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid <?php echo $checked ? '#10b981' : '#d1d5db'; ?>; border-radius: 6px; cursor: pointer;">
                                                        <input type="checkbox" name="schedule_slots[<?php echo $index; ?>][days][]" value="<?php echo $value; ?>" <?php echo $checked; ?> style="margin-right: 5px; cursor: pointer;">
                                                        <span><?php echo $label; ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                            <div>
                                                <label style="font-size: 13px; color: #6b7280; margin-bottom: 6px; display: block;">Hora inicio:</label>
                                                <input type="time" name="schedule_slots[<?php echo $index; ?>][start_time]"
                                                       value="<?php echo htmlEsc($slot['start_time'] ?? ''); ?>"
                                                       style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                                            </div>
                                            <div>
                                                <label style="font-size: 13px; color: #6b7280; margin-bottom: 6px; display: block;">Duración:</label>
                                                <select name="schedule_slots[<?php echo $index; ?>][duration]"
                                                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                                                    <?php
                                                    $currentDuration = intval($slot['duration'] ?? 60);
                                                    $durations = [
                                                        15 => '15min', 30 => '30min', 45 => '45min', 60 => '1h', 90 => '1h 30m',
                                                        120 => '2h', 150 => '2h 30m', 180 => '3h', 240 => '4h', 300 => '5h', 360 => '6h'
                                                    ];
                                                    foreach ($durations as $mins => $label):
                                                    ?>
                                                        <option value="<?php echo $mins; ?>" <?php echo $currentDuration === $mins ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <small style="color: #6b7280; display: block; margin-top: 10px;">
                                💡 Marca los días y horas en que se emite el programa en directo
                            </small>
                        </div>

                        <script>
                        // Inicializar contador desde el último índice
                        let slotCounter = <?php echo count($scheduleSlots); ?>;
                        </script>

                    <?php endif; ?>

                    <?php
                    // Ocultar campo de duración para bloques musicales (usan duración de Radiobot)
                    $isMusicBlock = ($programInfo['playlist_type'] ?? 'program') === 'music_block';
                    if (!$isMusicBlock):
                    ?>
                    <div class="form-group">
                        <label>Duración en parrilla:</label>
                        <?php $currentDuration = intval($programInfo['schedule_duration'] ?? 60); ?>
                        <select name="schedule_duration">
                            <option value="15" <?php echo $currentDuration === 15 ? 'selected' : ''; ?>>15 minutos</option>
                            <option value="30" <?php echo $currentDuration === 30 ? 'selected' : ''; ?>>30 minutos</option>
                            <option value="45" <?php echo $currentDuration === 45 ? 'selected' : ''; ?>>45 minutos</option>
                            <option value="60" <?php echo $currentDuration === 60 ? 'selected' : ''; ?>>1 hora</option>
                            <option value="90" <?php echo $currentDuration === 90 ? 'selected' : ''; ?>>1h 30m</option>
                            <option value="120" <?php echo $currentDuration === 120 ? 'selected' : ''; ?>>2 horas</option>
                            <option value="180" <?php echo $currentDuration === 180 ? 'selected' : ''; ?>>3 horas</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Temática:</label>
                        <select name="type">
                            <option value="">-- Sin especificar --</option>
                            <?php
                            $types = ['Musical', 'Informativo', 'Cultural', 'Deportivo', 'Entretenimiento', 'Educativo', 'Político', 'Magazine', 'Tertulia', 'Otro'];
                            foreach ($types as $type):
                                $selected = ($programInfo['type'] ?? '') === $type ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlEsc($type); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlEsc($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>URL del programa: <small>(web con más información)</small></label>
                        <input type="url" name="url"
                               value="<?php echo htmlEsc($programInfo['url'] ?? ''); ?>"
                               placeholder="https://turadio.com/programas/alternativa">
                    </div>

                    <div class="form-group">
                        <label>URL de imagen: <small>(logo o portada del programa)</small></label>
                        <input type="url" name="image"
                               value="<?php echo htmlEsc($programInfo['image'] ?? ''); ?>"
                               placeholder="https://turadio.com/img/programas/alternativa.jpg">
                        <?php if (!empty($programInfo['image'])): ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo htmlEsc($programInfo['image']); ?>"
                                     alt="Preview"
                                     style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #e0e0e0;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Presentadores: <small>(separados por comas)</small></label>
                        <input type="text" name="presenters"
                               value="<?php echo htmlEsc($programInfo['presenters'] ?? ''); ?>"
                               placeholder="Ana García, Carlos Ruiz">
                    </div>

                    <div class="form-group">
                        <label>X (Twitter): <small>(handle sin @ o URL completa)</small></label>
                        <input type="text" name="social_twitter"
                               value="<?php echo htmlEsc($programInfo['social_twitter'] ?? ''); ?>"
                               placeholder="alternativa90 o https://x.com/alternativa90">
                    </div>

                    <div class="form-group">
                        <label>Instagram: <small>(handle sin @ o URL completa)</small></label>
                        <input type="text" name="social_instagram"
                               value="<?php echo htmlEsc($programInfo['social_instagram'] ?? ''); ?>"
                               placeholder="alternativa90 o https://instagram.com/alternativa90">
                    </div>

                    <div class="form-group">
                        <label>Mastodon: <small>(usuario completo @usuario@servidor o URL)</small></label>
                        <input type="text" name="social_mastodon"
                               value="<?php echo htmlEsc($programInfo['social_mastodon'] ?? ''); ?>"
                               placeholder="@programa@mastodon.social o https://mastodon.social/@programa">
                    </div>

                    <div class="form-group">
                        <label>Bluesky: <small>(handle completo o URL)</small></label>
                        <input type="text" name="social_bluesky"
                               value="<?php echo htmlEsc($programInfo['social_bluesky'] ?? ''); ?>"
                               placeholder="programa.bsky.social o https://bsky.app/profile/programa.bsky.social">
                    </div>

                    <div class="form-group">
                        <label>Facebook: <small>(nombre de página o URL completa)</small></label>
                        <input type="text" name="social_facebook"
                               value="<?php echo htmlEsc($programInfo['social_facebook'] ?? ''); ?>"
                               placeholder="alternativa90 o https://facebook.com/alternativa90">
                    </div>

                    <div class="form-group">
                        <label>Feed RSS del podcast: <small>(opcional)</small></label>
                        <input type="url" name="rss_feed"
                               value="<?php echo htmlEsc($programInfo['rss_feed'] ?? ''); ?>"
                               placeholder="https://feeds.feedburner.com/mipodcast">
                        <small style="color: #6b7280;">
                            Si tienes un podcast RSS, pega aquí la URL del feed. Se mostrará el último episodio publicado en la parrilla.
                        </small>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-success">
                            <span class="btn-icon">💾</span> Guardar Cambios
                        </button>
                        <a href="?page=parrilla&section=programs" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-error">Programa no encontrado</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Lista de programas -->
    <div class="section">
        <h3>Programas</h3>

        <?php if (empty($programsData['programs'])): ?>
            <div class="alert alert-info">
                No hay programas. Haz click en "🔄 Sincronizar con Radiobot" para detectar tus programas o añade programas en directo manualmente.
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($programsData['programs'] as $program): ?>
                    <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <strong style="font-size: 16px;"><?php echo htmlEsc($program['display_name'] ?? $program['name']); ?></strong>
                                <?php
                                $playlistType = $program['info']['playlist_type'] ?? 'program';

                                if ($playlistType === 'live'):
                                ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        🟢 EN DIRECTO
                                    </span>
                                <?php elseif ($playlistType === 'program'): ?>
                                    <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        📻 PROGRAMA
                                    </span>
                                <?php elseif ($playlistType === 'music_block'): ?>
                                    <span style="background: #e5e7eb; color: #4b5563; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        🎵 BLOQUE MUSICAL
                                    </span>
                                <?php elseif ($playlistType === 'jingles'): ?>
                                    <span style="background: #e5e7eb; color: #4b5563; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        🔊 JINGLES
                                    </span>
                                <?php endif; ?>
                                <?php
                                $isOrphaned = !empty($program['info']['orphaned']);
                                $orphanReason = $program['info']['orphan_reason'] ?? '';
                                if ($isOrphaned):
                                    if ($orphanReason === 'playlist_deshabilitada'):
                                ?>
                                    <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;" title="La playlist existe pero está deshabilitada en Radiobot">
                                        ⏸️ DESHABILITADA EN AZURACAST
                                    </span>
                                <?php elseif ($orphanReason === 'no_en_azuracast'): ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;" title="Esta playlist no existe en Radiobot">
                                        ❌ NO EN AZURACAST
                                    </span>
                                <?php else: ?>
                                    <span style="background: #e5e7eb; color: #4b5563; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;" title="Este programa no aparece en la programación actual (sin API Key o sin horario)">
                                        ⚠️ SIN HORARIO
                                    </span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div style="font-size: 13px; color: #4a5568; margin-top: 5px;">
                                <?php if (!empty($program['info']['type'])): ?>
                                    <span style="background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 5px;">
                                        <?php echo htmlEsc($program['info']['type']); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($program['info']['short_description'])): ?>
                                    <span><?php echo htmlEsc(substr($program['info']['short_description'], 0, 100)); ?><?php echo strlen($program['info']['short_description']) > 100 ? '...' : ''; ?></span>
                                <?php elseif (!empty($program['info']['type'])): ?>
                                    <span style="color: #9ca3af;">Sin descripción</span>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">Haz clic en "Editar" para añadir información</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <a href="?page=parrilla&section=programs&edit=<?php echo urlencode($program['name']); ?>" class="btn btn-primary">
                                <span class="btn-icon">✏️</span> Editar
                            </a>
                            <?php
                            // Mostrar botón de eliminar para:
                            // 1. Programas creados manualmente (tipo 'live')
                            // 2. Programas que no existen en Radiobot (orphan_reason = 'no_en_azuracast')
                            $isManualProgram = isset($program['info']['created_at']) && ($program['info']['playlist_type'] ?? '') === 'live';
                            $isNotInAzuracast = !empty($program['info']['orphaned']) && ($program['info']['orphan_reason'] ?? '') === 'no_en_azuracast';

                            if ($isManualProgram):
                            ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este programa en directo?');">
                                    <input type="hidden" name="action" value="delete_program">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="program_name" value="<?php echo htmlEsc($program['name']); ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <span class="btn-icon">🗑️</span> Eliminar
                                    </button>
                                </form>
                            <?php elseif ($isNotInAzuracast): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este programa? Ya no existe en Radiobot.');">
                                    <input type="hidden" name="action" value="delete_program">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="program_name" value="<?php echo htmlEsc($program['name']); ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <span class="btn-icon">🗑️</span> Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ====== JAVASCRIPT PARA HORARIOS MÚLTIPLES ======
let slotCounter = 1; // Ya tenemos el slot 0 por defecto

/**
 * Añadir nuevo bloque de horario
 */
function addScheduleSlot() {
    const container = document.getElementById('schedule-slots-container');
    const slotIndex = slotCounter++;

    const slotHTML = `
        <div class="schedule-slot" data-slot-index="${slotIndex}">
            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <strong style="color: #374151; font-size: 14px;">Horario #${slotIndex + 1}</strong>
                    <button type="button" onclick="removeScheduleSlot(this)" class="btn" style="background: #dc2626; color: white; padding: 4px 8px; font-size: 12px;">
                        🗑️ Eliminar
                    </button>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="font-size: 13px; color: #6b7280; margin-bottom: 8px; display: block;">Días de emisión:</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="1" style="margin-right: 5px; cursor: pointer;">
                            <span>L</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="2" style="margin-right: 5px; cursor: pointer;">
                            <span>M</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="3" style="margin-right: 5px; cursor: pointer;">
                            <span>X</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="4" style="margin-right: 5px; cursor: pointer;">
                            <span>J</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="5" style="margin-right: 5px; cursor: pointer;">
                            <span>V</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="6" style="margin-right: 5px; cursor: pointer;">
                            <span>S</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; padding: 6px 10px; background: white; border: 2px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="0" style="margin-right: 5px; cursor: pointer;">
                            <span>D</span>
                        </label>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 13px; color: #6b7280; margin-bottom: 6px; display: block;">Hora inicio:</label>
                        <input type="time" name="schedule_slots[${slotIndex}][start_time]" value=""
                               style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; color: #6b7280; margin-bottom: 6px; display: block;">Duración:</label>
                        <select name="schedule_slots[${slotIndex}][duration]"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                            <option value="15">15min</option>
                            <option value="30">30min</option>
                            <option value="45">45min</option>
                            <option value="60" selected>1h</option>
                            <option value="90">1h 30m</option>
                            <option value="120">2h</option>
                            <option value="150">2h 30m</option>
                            <option value="180">3h</option>
                            <option value="240">4h</option>
                            <option value="300">5h</option>
                            <option value="360">6h</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', slotHTML);
    updateSlotNumbers();
    updateDeleteButtons();
}

/**
 * Eliminar bloque de horario
 */
function removeScheduleSlot(button) {
    const slot = button.closest('.schedule-slot');
    if (slot) {
        slot.remove();
        updateSlotNumbers();
        updateDeleteButtons();
    }
}

/**
 * Actualizar numeración de slots
 */
function updateSlotNumbers() {
    const slots = document.querySelectorAll('.schedule-slot');
    slots.forEach((slot, index) => {
        const title = slot.querySelector('strong');
        if (title) {
            title.textContent = `Horario #${index + 1}`;
        }
    });
}

/**
 * Mostrar/ocultar botones de eliminar
 * Solo mostrar si hay más de un slot
 */
function updateDeleteButtons() {
    const slots = document.querySelectorAll('.schedule-slot');
    const deleteButtons = document.querySelectorAll('.schedule-slot button[onclick*="removeScheduleSlot"]');

    deleteButtons.forEach(btn => {
        if (slots.length > 1) {
            btn.style.display = 'inline-block';
        } else {
            btn.style.display = 'none';
        }
    });
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    updateDeleteButtons();
});
</script>
