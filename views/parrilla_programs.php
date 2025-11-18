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
                           maxlength="200">
                </div>

                <div class="form-group">
                    <label>Descripción larga: <small>(para página de detalle)</small></label>
                    <textarea name="long_description" rows="4"
                              placeholder="Descripción detallada del programa, presentadores, temáticas, etc."></textarea>
                </div>

                <div class="form-group">
                    <label>Días de emisión: <small>(marcar los días en que se emite)</small></label>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 10px; background: #f9fafb; border-radius: 6px;">
                        <?php
                        $days = [
                            '1' => 'Lunes',
                            '2' => 'Martes',
                            '3' => 'Miércoles',
                            '4' => 'Jueves',
                            '5' => 'Viernes',
                            '6' => 'Sábado',
                            '0' => 'Domingo'
                        ];
                        foreach ($days as $value => $label):
                        ?>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                <input type="checkbox" name="schedule_days[]" value="<?php echo $value; ?>" style="cursor: pointer;">
                                <?php echo htmlEsc($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: #6b7280;">
                        Si no seleccionas ningún día, el programa solo aparecerá cuando Radiobot lo programe
                    </small>
                </div>

                <div class="form-group">
                    <label>Hora de inicio: <small>(formato 24h)</small></label>
                    <input type="time" name="schedule_start_time"
                           placeholder="20:00">
                    <small style="color: #6b7280;">
                        Hora a la que comienza la emisión (ej: 20:00)
                    </small>
                </div>

                <div class="form-group">
                    <label>Duración (minutos):</label>
                    <input type="number" name="schedule_duration"
                           placeholder="60"
                           min="1"
                           max="1440">
                    <small style="color: #6b7280;">
                        Duración del programa en minutos (ej: 60 para 1 hora, 120 para 2 horas)
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
                    <label>Twitter: <small>(sin @)</small></label>
                    <input type="text" name="social_twitter"
                           placeholder="alternativa90">
                </div>

                <div class="form-group">
                    <label>Instagram: <small>(sin @)</small></label>
                    <input type="text" name="social_instagram"
                           placeholder="alternativa90">
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
                        <select name="playlist_type" required>
                            <?php
                            $playlistTypes = [
                                'program' => '📻 Programa (se muestra en la parrilla)',
                                'live' => '🔴 Emisión en Directo (destacado especial)',
                                'music_block' => '🎵 Bloque Musical (oculto)',
                                'jingles' => '🔊 Jingles/Cortinillas (oculto)'
                            ];
                            $currentType = $programInfo['playlist_type'] ?? 'program';
                            foreach ($playlistTypes as $value => $label):
                                $selected = $currentType === $value ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlEsc($value); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlEsc($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">
                            • <strong>Programa</strong>: Contenido producido (repeticiones, podcast)<br>
                            • <strong>Emisión en Directo</strong>: Programas en vivo, destacados con estilo especial<br>
                            • <strong>Bloque Musical</strong>: Música automatizada (se oculta de la parrilla)<br>
                            • <strong>Jingles/Cortinillas</strong>: Efectos de audio (se ocultan de la parrilla)
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
                               maxlength="200">
                    </div>

                    <div class="form-group">
                        <label>Descripción larga: <small>(para página de detalle)</small></label>
                        <textarea name="long_description" rows="4"
                                  placeholder="Descripción detallada del programa, presentadores, temáticas, etc."><?php echo htmlEsc($programInfo['long_description'] ?? ''); ?></textarea>
                    </div>

                    <?php
                    // Mostrar campos de horario solo si es programa en directo
                    $isLiveProgram = ($programInfo['playlist_type'] ?? 'program') === 'live';
                    if ($isLiveProgram):
                    ?>
                        <div class="form-group">
                            <label>Días de emisión: <small>(marcar los días en que se emite)</small></label>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 10px; background: #f9fafb; border-radius: 6px;">
                                <?php
                                $days = [
                                    '1' => 'Lunes',
                                    '2' => 'Martes',
                                    '3' => 'Miércoles',
                                    '4' => 'Jueves',
                                    '5' => 'Viernes',
                                    '6' => 'Sábado',
                                    '0' => 'Domingo'
                                ];
                                $currentScheduleDays = $programInfo['schedule_days'] ?? [];
                                foreach ($days as $value => $label):
                                    $checked = in_array($value, $currentScheduleDays) ? 'checked' : '';
                                ?>
                                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                        <input type="checkbox" name="schedule_days[]" value="<?php echo $value; ?>" <?php echo $checked; ?> style="cursor: pointer;">
                                        <?php echo htmlEsc($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small style="color: #6b7280;">
                                Marca los días en que se emite el programa en directo
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Hora de inicio: <small>(formato 24h)</small></label>
                            <input type="time" name="schedule_start_time"
                                   value="<?php echo htmlEsc($programInfo['schedule_start_time'] ?? ''); ?>"
                                   placeholder="20:00">
                            <small style="color: #6b7280;">
                                Hora a la que comienza la emisión (ej: 20:00)
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Duración (minutos):</label>
                            <input type="number" name="schedule_duration"
                                   value="<?php echo htmlEsc($programInfo['schedule_duration'] ?? '60'); ?>"
                                   placeholder="60"
                                   min="1"
                                   max="1440">
                            <small style="color: #6b7280;">
                                Duración del programa en minutos (ej: 60 para 1 hora, 120 para 2 horas)
                            </small>
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
                        <label>Twitter: <small>(sin @)</small></label>
                        <input type="text" name="social_twitter"
                               value="<?php echo htmlEsc($programInfo['social_twitter'] ?? ''); ?>"
                               placeholder="alternativa90">
                    </div>

                    <div class="form-group">
                        <label>Instagram: <small>(sin @)</small></label>
                        <input type="text" name="social_instagram"
                               value="<?php echo htmlEsc($programInfo['social_instagram'] ?? ''); ?>"
                               placeholder="alternativa90">
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
        <h3>Programas Detectados</h3>

        <?php if (empty($programsData['programs'])): ?>
            <div class="alert alert-info">
                No hay programas detectados. Haz click en "🔄 Sincronizar con Radiobot" para detectar tus programas.
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($programsData['programs'] as $program): ?>
                    <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <strong style="font-size: 16px;"><?php echo htmlEsc($program['name']); ?></strong>
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
                            // Mostrar botón de eliminar solo para programas creados manualmente (tipo 'live')
                            $isManualProgram = isset($program['info']['created_at']) && ($program['info']['playlist_type'] ?? '') === 'live';
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
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
