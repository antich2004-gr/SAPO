<?php
/**
 * includes/program_edit_form.php
 *
 * Formulario de edición de ficha de programa reutilizable.
 * Usado por views/parrilla_programs.php (modal normal) y por el handler
 * de embed (iframe desde seguimiento de emisión).
 *
 * Variables esperadas del contexto que lo incluye:
 *   string $editingProgram  — clave interna del programa (puede incluir ::live)
 *   array  $programInfo     — datos del programa cargados con getProgramInfo()
 *   string $username        — usuario de la sesión
 *   bool   $isEmbed         — true cuando se carga en iframe desde seguimiento
 */

$_progDisplayName = getProgramNameFromKey($editingProgram);
$_isLiveProgram   = (($programInfo['playlist_type'] ?? '') === 'live');
?>
<form method="POST">
    <input type="hidden" name="action"       value="save_program">
    <input type="hidden" name="csrf_token"   value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="program_name" value="<?php echo htmlEsc($editingProgram); ?>">
    <?php if (!empty($isEmbed)): ?>
    <input type="hidden" name="embed" value="1">
    <?php endif; ?>

    <div class="form-group">
        <label>Tipo de lista de reproducción: <small>(importante para la parrilla)</small></label>
        <?php if ($_isLiveProgram): ?>
        <select name="playlist_type" required disabled>
            <option value="live" selected>🟢 Emisión en Directo (programa manual)</option>
        </select>
        <input type="hidden" name="playlist_type" value="live">
        <small style="color:#6b7280;">
            <em>💡 Los programas en directo añadidos manualmente no pueden cambiar de tipo.</em>
        </small>
        <?php else:
            $playlistTypes = [
                'program'      => '📻 Programa (se muestra en la parrilla)',
                'music_block'  => '🎵 Bloque Musical (en timeline)',
                'jingles'      => '🔊 Jingles/Cortinillas (oculto)',
            ];
            $currentType = $programInfo['playlist_type'] ?? 'program';
        ?>
        <select name="playlist_type" required>
            <?php foreach ($playlistTypes as $value => $label):
                $selected = $currentType === $value ? 'selected' : '';
            ?>
                <option value="<?php echo htmlEsc($value); ?>" <?php echo $selected; ?>><?php echo htmlEsc($label); ?></option>
            <?php endforeach; ?>
        </select>
        <small style="color:#6b7280;">
            • <strong>Programa</strong>: Contenido producido (repeticiones, podcast)<br>
            • <strong>Bloque Musical</strong>: Música automatizada (se muestra en el timeline)<br>
            • <strong>Jingles/Cortinillas</strong>: Efectos de audio (se ocultan de la parrilla)<br>
            <em>💡 Los programas importados no pueden cambiarse a "En Directo". Para añadir programas en directo, usa el formulario de la izquierda.</em>
        </small>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="checkbox" name="hidden_from_schedule" value="1"
                   <?php echo !empty($programInfo['hidden_from_schedule']) ? 'checked' : ''; ?>
                   style="width:18px; height:18px; cursor:pointer;">
            <span>Ocultar en la parrilla</span>
        </label>
        <small style="color:#6b7280; display:block; margin-top:5px;">
            Si está marcado, este programa/bloque no aparecerá en la parrilla pública.
        </small>
    </div>

    <div class="form-group">
        <label>Título personalizado: <small>(opcional - si está vacío se usa el nombre de la playlist)</small></label>
        <input type="text" name="display_title"
               value="<?php echo htmlEsc($programInfo['display_title'] ?? ''); ?>"
               placeholder="Ej: El Despertador Matinal"
               maxlength="100">
        <small style="color:#6b7280; display:block; margin-top:5px;">
            💡 Este título aparecerá en las cards. Si lo dejas vacío, se mostrará el nombre de la playlist: <strong><?php echo htmlEsc($_progDisplayName); ?></strong>
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
    // ====== HORARIOS: leer configuración manual o fallback a AzuraCast ======
    $scheduleSlots = [];

    if (!empty($programInfo['schedule_slots'])) {
        $scheduleSlots = $programInfo['schedule_slots'];
    } elseif ($_isLiveProgram && !empty($programInfo['schedule_days'])) {
        $scheduleSlots = [[
            'days'       => array_map('intval', (array)($programInfo['schedule_days'] ?? [])),
            'start_time' => $programInfo['schedule_start_time'] ?? '',
            'duration'   => intval($programInfo['schedule_duration'] ?? 60),
        ]];
    } elseif (!$_isLiveProgram) {
        $azuraSchedule = getAzuracastSchedule($username, 600);
        if ($azuraSchedule !== false && !empty($azuraSchedule)) {
            $currentProgramNameRef = getProgramNameFromKey($editingProgram);
            $groupedRef = [];
            foreach ($azuraSchedule as $event) {
                $eventName = $event['name'] ?? $event['playlist'] ?? '';
                if ($eventName !== $currentProgramNameRef) continue;
                $start = $event['start_timestamp'] ?? $event['start'] ?? null;
                if ($start === null) continue;
                $startDt = is_numeric($start) ? new DateTime('@' . $start) : new DateTime($start);
                $startDt->setTimezone(new DateTimeZone('Europe/Madrid'));
                $end   = $event['end_timestamp'] ?? $event['end'] ?? null;
                $endDt = $end ? (is_numeric($end) ? new DateTime('@' . $end) : new DateTime($end)) : null;
                if ($endDt) $endDt->setTimezone(new DateTimeZone('Europe/Madrid'));
                $dur = ($endDt && $endDt->getTimestamp() > $startDt->getTimestamp())
                    ? (int)(($endDt->getTimestamp() - $startDt->getTimestamp()) / 60)
                    : 60;
                $day  = (int)$startDt->format('w');
                $time = $startDt->format('H:i');
                $key  = $time . '_' . $dur;
                if (!isset($groupedRef[$key])) {
                    $groupedRef[$key] = ['days' => [], 'start_time' => $time, 'duration' => $dur];
                }
                if (!in_array($day, $groupedRef[$key]['days'])) {
                    $groupedRef[$key]['days'][] = $day;
                }
            }
            $scheduleSlots = array_values($groupedRef);
        }
    }

    if (empty($scheduleSlots)) {
        $scheduleSlots = [['days' => [], 'start_time' => '', 'duration' => 60]];
    }
    ?>

    <div class="form-group">
        <label style="display:flex; justify-content:space-between; align-items:center;">
            <span>📅 Horarios de Emisión:</span>
            <button type="button" onclick="addScheduleSlot()" class="btn" style="background:#10b981; color:white; padding:6px 12px; font-size:13px;">
                ➕ Añadir Horario
            </button>
        </label>
        <small style="display:block; margin-bottom:15px; color:#6b7280;">
            <?php if ($_isLiveProgram): ?>
            Puedes añadir múltiples horarios si el programa se emite en diferentes días/horas
            <?php else: ?>
            Pre-rellenado desde Radiobot. Modifica solo si necesitas sobrescribir el horario real.
            <?php endif; ?>
        </small>

        <div id="schedule-slots-container">
            <?php foreach ($scheduleSlots as $index => $slot): ?>
            <div class="schedule-slot" data-slot-index="<?php echo $index; ?>">
                <div style="background:#f9fafb; padding:15px; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <strong style="color:#374151; font-size:14px;">Horario #<?php echo $index + 1; ?></strong>
                        <button type="button" onclick="removeScheduleSlot(this)" class="btn"
                                style="background:#dc2626; color:white; padding:4px 8px; font-size:12px; <?php echo count($scheduleSlots) <= 1 ? 'display:none;' : ''; ?>">
                            🗑️ Eliminar
                        </button>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:13px; color:#6b7280; margin-bottom:8px; display:block;">Días de emisión:</label>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                            <?php
                            $days     = ['1'=>'L','2'=>'M','3'=>'X','4'=>'J','5'=>'V','6'=>'S','0'=>'D'];
                            $slotDays = array_map('intval', (array)($slot['days'] ?? []));
                            foreach ($days as $value => $label):
                                $checked = in_array((int)$value, $slotDays) ? 'checked' : '';
                            ?>
                            <label style="display:inline-flex; align-items:center; padding:6px 10px; background:white; border:2px solid <?php echo $checked ? '#10b981' : '#d1d5db'; ?>; border-radius:6px; cursor:pointer;">
                                <input type="checkbox" name="schedule_slots[<?php echo $index; ?>][days][]"
                                       value="<?php echo $value; ?>" <?php echo $checked; ?>
                                       style="margin-right:5px; cursor:pointer;">
                                <span><?php echo $label; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label style="font-size:13px; color:#6b7280; margin-bottom:6px; display:block;">Hora inicio:</label>
                            <input type="time" name="schedule_slots[<?php echo $index; ?>][start_time]"
                                   value="<?php echo htmlEsc($slot['start_time'] ?? ''); ?>"
                                   style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
                        </div>
                        <div>
                            <label style="font-size:13px; color:#6b7280; margin-bottom:6px; display:block;">Duración:</label>
                            <select name="schedule_slots[<?php echo $index; ?>][duration]"
                                    style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
                                <?php
                                $currentDuration = intval($slot['duration'] ?? 60);
                                $durations = [15=>'15min',30=>'30min',45=>'45min',60=>'1h',90=>'1h 30m',
                                              120=>'2h',150=>'2h 30m',180=>'3h',240=>'4h',300=>'5h',360=>'6h'];
                                foreach ($durations as $mins => $label):
                                ?>
                                <option value="<?php echo $mins; ?>" <?php echo $currentDuration === $mins ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <small style="color:#6b7280; display:block; margin-top:10px;">
            <?php if ($_isLiveProgram): ?>
            💡 Marca los días y horas en que se emite el programa
            <?php else: ?>
            💡 Si no configuras horario personalizado, se usará el de Radiobot automáticamente
            <?php endif; ?>
        </small>
    </div>

    <div class="form-group">
        <label>Temática:</label>
        <select name="type">
            <option value="">-- Sin especificar --</option>
            <?php
            $types = ['Musical','Informativo','Cultural','Deportivo','Entretenimiento','Educativo','Político','Magazine','Tertulia','Otro'];
            foreach ($types as $type):
                $selected = ($programInfo['type'] ?? '') === $type ? 'selected' : '';
            ?>
            <option value="<?php echo htmlEsc($type); ?>" <?php echo $selected; ?>><?php echo htmlEsc($type); ?></option>
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
        <div style="margin-top:10px;">
            <img src="<?php echo htmlEsc($programInfo['image']); ?>" alt="Preview"
                 style="max-width:200px; max-height:200px; border-radius:8px; border:1px solid #e0e0e0;">
        </div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Presentadores: <small>(separados por comas)</small></label>
        <input type="text" name="presenters"
               value="<?php echo htmlEsc($programInfo['presenters'] ?? ''); ?>"
               placeholder="Ana García, Carlos Ruiz">
    </div>

    <?php
    $hasSocial = !empty($programInfo['social_twitter'])   || !empty($programInfo['social_instagram']) ||
                 !empty($programInfo['social_mastodon'])  || !empty($programInfo['social_bluesky'])   ||
                 !empty($programInfo['social_facebook']);
    ?>
    <div style="border:1px solid #e5e7eb; border-radius:8px; margin-bottom:16px; overflow:hidden;">
        <button type="button" onclick="toggleSocial()"
                style="width:100%; display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f9fafb; border:none; cursor:pointer; font-size:14px; font-weight:600; color:#374151;">
            <span>🌐 Redes Sociales<?php if ($hasSocial): ?> <span style="font-weight:400; color:#10b981; font-size:12px;">● configuradas</span><?php endif; ?></span>
            <span id="social-arrow" style="font-size:12px; transition:transform 0.2s;">▼</span>
        </button>
        <div id="social-fields" style="padding:0; max-height:0; overflow:hidden; transition:max-height 0.3s ease, padding 0.3s ease;">
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
                       placeholder="@programa@mastodon.social">
            </div>
            <div class="form-group">
                <label>Bluesky: <small>(handle completo o URL)</small></label>
                <input type="text" name="social_bluesky"
                       value="<?php echo htmlEsc($programInfo['social_bluesky'] ?? ''); ?>"
                       placeholder="programa.bsky.social">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Facebook: <small>(nombre de página o URL completa)</small></label>
                <input type="text" name="social_facebook"
                       value="<?php echo htmlEsc($programInfo['social_facebook'] ?? ''); ?>"
                       placeholder="alternativa90 o https://facebook.com/alternativa90">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Feed RSS del podcast: <small>(opcional)</small></label>
        <input type="url" name="rss_feed"
               value="<?php echo htmlEsc($programInfo['rss_feed'] ?? ''); ?>"
               placeholder="https://feeds.feedburner.com/mipodcast">
        <small style="color:#6b7280;">
            Si tienes un podcast RSS, pega aquí la URL del feed. Se mostrará el último episodio publicado en la parrilla.
        </small>
    </div>

    <div style="display:flex; gap:10px; align-items:center;">
        <button type="submit" class="btn btn-success">
            <span class="btn-icon">💾</span> Guardar Cambios
        </button>
        <?php if (!empty($isEmbed)): ?>
        <button type="button" class="btn btn-secondary" onclick="window.parent.postMessage({type:'programCancelled'},'*')">
            Cancelar
        </button>
        <?php else: ?>
        <a href="?page=parrilla&section=programs" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
    </div>
</form>

<script>
let slotCounter = document.querySelectorAll('.schedule-slot').length;

function addScheduleSlot() {
    const container = document.getElementById('schedule-slots-container');
    const slotIndex = slotCounter++;
    const slotHTML = `
        <div class="schedule-slot" data-slot-index="${slotIndex}">
            <div style="background:#f9fafb; padding:15px; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <strong style="color:#374151; font-size:14px;">Horario #${slotIndex+1}</strong>
                    <button type="button" onclick="removeScheduleSlot(this)" class="btn" style="background:#dc2626; color:white; padding:4px 8px; font-size:12px;">🗑️ Eliminar</button>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; color:#6b7280; margin-bottom:8px; display:block;">Días de emisión:</label>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        ${[['1','L'],['2','M'],['3','X'],['4','J'],['5','V'],['6','S'],['0','D']].map(([v,l])=>`
                        <label style="display:inline-flex; align-items:center; padding:6px 10px; background:white; border:2px solid #d1d5db; border-radius:6px; cursor:pointer;">
                            <input type="checkbox" name="schedule_slots[${slotIndex}][days][]" value="${v}" style="margin-right:5px; cursor:pointer;">
                            <span>${l}</span>
                        </label>`).join('')}
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; color:#6b7280; margin-bottom:6px; display:block;">Hora inicio:</label>
                        <input type="time" name="schedule_slots[${slotIndex}][start_time]" value="" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:13px; color:#6b7280; margin-bottom:6px; display:block;">Duración:</label>
                        <select name="schedule_slots[${slotIndex}][duration]" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px;">
                            <option value="15">15min</option><option value="30">30min</option><option value="45">45min</option>
                            <option value="60" selected>1h</option><option value="90">1h 30m</option><option value="120">2h</option>
                            <option value="150">2h 30m</option><option value="180">3h</option><option value="240">4h</option>
                            <option value="300">5h</option><option value="360">6h</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', slotHTML);
    updateSlotNumbers();
    updateDeleteButtons();
}

function removeScheduleSlot(button) {
    const slot = button.closest('.schedule-slot');
    if (slot) { slot.remove(); updateSlotNumbers(); updateDeleteButtons(); }
}

function updateSlotNumbers() {
    document.querySelectorAll('.schedule-slot').forEach((slot, index) => {
        const title = slot.querySelector('strong');
        if (title) title.textContent = `Horario #${index + 1}`;
    });
}

function updateDeleteButtons() {
    const slots = document.querySelectorAll('.schedule-slot');
    document.querySelectorAll('.schedule-slot button[onclick*="removeScheduleSlot"]').forEach(btn => {
        btn.style.display = slots.length > 1 ? 'inline-block' : 'none';
    });
}

function toggleSocial() {
    const fields = document.getElementById('social-fields');
    const arrow  = document.getElementById('social-arrow');
    if (!fields) return;
    const isOpen = fields.style.maxHeight !== '0px' && fields.style.maxHeight !== '';
    if (isOpen) {
        fields.style.maxHeight = '0'; fields.style.padding = '0'; arrow.textContent = '▼';
    } else {
        fields.style.maxHeight = '600px'; fields.style.padding = '16px'; arrow.textContent = '▲';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateDeleteButtons();
});
</script>
