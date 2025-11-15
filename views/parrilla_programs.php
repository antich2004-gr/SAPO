<?php
// views/parrilla_programs.php - Gestión de información de programas (subsección)
$programsData = getAllProgramsWithStats($username);
$editingProgram = $_GET['edit'] ?? null;
?>

<div class="section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">Gestión de Programas</h3>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="sync_programs">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <button type="submit" class="btn btn-primary">
                <span class="btn-icon">🔄</span> Sincronizar con AzuraCast
            </button>
        </form>
    </div>

    <?php if ($programsData['last_sync']): ?>
        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="margin: 0; color: #4a5568; font-size: 14px;">
                <strong>Última sincronización:</strong> <?php echo htmlEsc($programsData['last_sync']); ?>
            </p>
            <p style="margin: 5px 0 0 0; color: #4a5568; font-size: 14px;">
                <strong>Programas:</strong>
                <?php echo $programsData['total']; ?> total
                (<?php echo $programsData['complete']; ?> ✅ completos,
                <?php echo $programsData['partial']; ?> ⚠️ parciales,
                <?php echo $programsData['empty']; ?> ❌ vacíos)
            </p>
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
                No hay programas detectados. Haz click en "🔄 Sincronizar con AzuraCast" para detectar tus programas.
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($programsData['programs'] as $program): ?>
                    <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <strong style="font-size: 16px;"><?php echo htmlEsc($program['name']); ?></strong>
                                <span style="font-size: 13px; color: #6b7280;"><?php echo $program['status_label']; ?></span>
                            </div>

                            <?php if ($program['status'] !== 'empty'): ?>
                                <div style="font-size: 13px; color: #4a5568; margin-top: 5px;">
                                    <?php if (!empty($program['info']['type'])): ?>
                                        <span style="background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 5px;">
                                            <?php echo htmlEsc($program['info']['type']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($program['info']['short_description'])): ?>
                                        <span><?php echo htmlEsc(substr($program['info']['short_description'], 0, 100)); ?><?php echo strlen($program['info']['short_description']) > 100 ? '...' : ''; ?></span>
                                    <?php endif; ?>
                                </div>

                                <div style="font-size: 12px; color: #9ca3af; margin-top: 8px;">
                                    Completitud: <?php echo round($program['completeness']); ?>%
                                    <div style="width: 200px; height: 6px; background: #e5e7eb; border-radius: 3px; display: inline-block; margin-left: 10px;">
                                        <div style="width: <?php echo $program['completeness']; ?>%; height: 100%; background: <?php
                                            echo $program['completeness'] === 100 ? '#10b981' : ($program['completeness'] > 0 ? '#f59e0b' : '#ef4444');
                                        ?>; border-radius: 3px;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <a href="?page=parrilla&section=programs&edit=<?php echo urlencode($program['name']); ?>" class="btn btn-primary">
                                <span class="btn-icon">✏️</span> Editar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
