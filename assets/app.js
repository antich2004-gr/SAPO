// assets/app.js - Funciones JavaScript SAPO

/**
 * Cambiar pestaña
 */
function switchTab(tabName) {
    // Ocultar todas las pestañas
    const panels = document.querySelectorAll('.tab-panel');
    panels.forEach(panel => {
        panel.classList.remove('active');
    });

    // Desactivar todos los botones
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        button.classList.remove('active');
    });

    // Mostrar la pestaña seleccionada
    const selectedPanel = document.getElementById('tab-' + tabName);
    if (selectedPanel) {
        selectedPanel.classList.add('active');
    }

    // Activar el botón seleccionado
    const selectedButton = document.querySelector('[data-tab="' + tabName + '"]');
    if (selectedButton) {
        selectedButton.classList.add('active');
    }

    // Si es la pestaña de configuración, cargar datos
    if (tabName === 'config') {
        loadTimeSignalsFiles();
        loadTimeSignalsConfig();
    }
}


/**
 * Mostrar nombre del archivo seleccionado
 */
function showFileName(input) {
    const fileName = input.files[0]?.name || '';
    // Buscar el span .selected-file hermano dentro del mismo contenedor
    const span = input.closest('div, form')?.querySelector('.selected-file') || document.getElementById('fileName');
    if (span) span.textContent = fileName;
}

/**
 * Ejecutar descargas vía AJAX (sin recargar página)
 */
function executePodgetViaAjax() {
    if (!confirm('¿Iniciar descarga de nuevos episodios?')) {
        return false;
    }
    
    const statusDiv = document.getElementById('podget-status');
    if (!statusDiv) {
        alert('Error: No se encontró el elemento de estado');
        return false;
    }
    
    statusDiv.innerHTML = '<div class="alert alert-info">⏳ Ejecutando script de descargas...</div>';
    
    // Obtener el token CSRF del formulario
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    
    if (!csrfToken) {
        statusDiv.innerHTML = '<div class="alert alert-error">❌ Error: Token CSRF no encontrado</div>';
        return false;
    }
    
    // Enviar request AJAX
    const formData = new FormData();
    formData.append('action', 'run_podget');
    formData.append('csrf_token', csrfToken);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Respuesta del servidor: ' + response.status);
        }
        return response.text();
    })
    .then(data => {
        console.log('Respuesta recibida del servidor');

        // Extraer solo los mensajes de alerta de SAPO (no todo el HTML)
        const parser = new DOMParser();
        const doc = parser.parseFromString(data, 'text/html');
        const errorDiv = doc.querySelector('.alert-error');

        if (errorDiv) {
            // Hay un mensaje de error específico de SAPO
            statusDiv.innerHTML = '<div class="alert alert-error">❌ ' + errorDiv.textContent.trim() + '</div>';
        } else {
            // El script se envió correctamente — abrir modal de log
            statusDiv.innerHTML = '';
            openPodgetLogModal();
            startPodgetLogViewer();
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error);
        statusDiv.innerHTML = `
            <div class="alert alert-error">
                ❌ Error de conexión: ${error.message}
                <br><br>
                <button onclick="executePodgetViaAjax()" class="btn btn-secondary" style="margin-top: 10px;">
                    Intentar nuevamente
                </button>
            </div>
        `;
    });
    
    return false;
}

/**
 * Verificar estado del log de descargas (OPCIONAL - no se usa por defecto)
 */
function checkPodgetStatus() {
    const statusDiv = document.getElementById('podget-status') || document.getElementById('podget-status-page');
    if (!statusDiv) return;
    
    statusDiv.innerHTML = '<div class="alert alert-info">🔍 Verificando estado del log...</div>';
    
    fetch(window.location.origin + window.location.pathname + '?action=check_podget_status&_=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Estado del log:', data);
            
            if (data.exists) {
                const timeSinceUpdate = Math.floor((Date.now() / 1000) - (data.timestamp || 0));
                let statusMessage = '';
                
                if (timeSinceUpdate < 60) {
                    statusMessage = '<div class="alert alert-success">✓ Script ejecutado correctamente a las ' + data.lastUpdate + '</div>';
                } else {
                    statusMessage = '<div class="alert alert-info">ℹ️ Última ejecución: ' + data.lastUpdate + ' (hace ' + Math.floor(timeSinceUpdate / 60) + ' minutos)</div>';
                }
                
                statusDiv.innerHTML = statusMessage + 
                    '<p style="margin-top: 10px; font-size: 14px; color: #666;">Revisa el archivo de log en el servidor para ver detalles de la descarga.</p>';
            } else {
                statusDiv.innerHTML = `
                    <div class="alert alert-info">
                        ℹ️ El script se envió pero el log aún no se ha creado. 
                        <button onclick="checkPodgetStatus()" class="btn btn-secondary" style="margin-top: 10px;">
                            Verificar nuevamente
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error verificando estado:', error);
            statusDiv.innerHTML = `
                <div class="alert alert-success">
                    ✓ Las descargas se están ejecutando correctamente.
                    <br><br>
                    <small style="color: #718096;">
                        El proceso se ejecuta en segundo plano y puede tardar varios minutos.
                    </small>
                </div>
            `;
        });
}

// ─── Visor de log en tiempo real ─────────────────────────────────────────────

let _logPollTimer   = null;
let _logOffset      = 0;
let _logIdleCount   = 0;
const LOG_POLL_MS   = 500;   // cada 500 ms para ver feeds aparecer en tiempo real
const LOG_IDLE_MAX  = 600;   // parar tras 5 min sin datos nuevos (podget puede tardar)

function openPodgetLogModal() {
    const modal = document.getElementById('podget-log-modal');
    if (modal) modal.style.display = 'flex';
}

function closePodgetLogModal() {
    const modal = document.getElementById('podget-log-modal');
    if (modal) modal.style.display = 'none';
}

function startPodgetLogViewer() {
    const content = document.getElementById('podget-log-content');
    const label   = document.getElementById('podget-log-status');
    if (!content) return;

    if (_logPollTimer) clearInterval(_logPollTimer);
    _logOffset    = 0;
    _logIdleCount = 0;
    content.textContent = '';
    label.textContent   = '⏳ esperando inicio del script…';

    _logPollTimer = setInterval(_pollPodgetLog, LOG_POLL_MS);
}

function _pollPodgetLog() {
    const content = document.getElementById('podget-log-content');
    const label   = document.getElementById('podget-log-status');
    if (!content) { clearInterval(_logPollTimer); return; }

    fetch(window.location.origin + window.location.pathname + '?action=get_podget_log&offset=' + _logOffset + '&_=' + Date.now())
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data || !data.exists) {
                _logIdleCount++;
            } else if (data.size < _logOffset) {
                // El archivo fue truncado (tee inició nuevo run): resetear
                _logOffset = 0;
                content.textContent = '';
                _logIdleCount = 0;
                label.textContent = '🔄 nuevo run detectado…';
            } else if (data.chunk && data.chunk.length > 0) {
                _logOffset    = data.offset;
                _logIdleCount = 0;
                content.textContent += data.chunk;
                content.scrollTop = content.scrollHeight;
                label.textContent = '🟢 activo — ' + new Date().toLocaleTimeString();
            } else {
                _logIdleCount++;
                const secsLeft = Math.round(((LOG_IDLE_MAX - _logIdleCount) * LOG_POLL_MS) / 1000);
                label.textContent = '⏳ script en ejecución… (cierra en ' + secsLeft + 's si no hay actividad)';
            }

            if (_logIdleCount >= LOG_IDLE_MAX) {
                clearInterval(_logPollTimer);
                label.textContent = '⏹ monitoreo detenido (sin actividad 5 min)';
            }
        })
        .catch(() => { _logIdleCount++; });
}

/**
 * Función para mostrar el modal de agregar podcast
 */
function showAddPodcastModal() {
    const modal = document.getElementById('addPodcastModal');
    if (modal) {
        modal.style.display = 'block';
        // Focus en el primer input
        setTimeout(() => {
            const urlInput = document.getElementById('podcast_url');
            if (urlInput) urlInput.focus();
        }, 100);
    }
}

/**
 * Función para cerrar el modal de agregar podcast
 */
function closeAddPodcastModal() {
    const modal = document.getElementById('addPodcastModal');
    if (modal) {
        modal.style.display = 'none';
        // Limpiar el formulario
        const form = modal.querySelector('form');
        if (form) form.reset();
        // Ocultar el input de categoría personalizada
        const customInput = document.getElementById('modal_custom_category_input');
        if (customInput) customInput.style.display = 'none';
    }
}


/**
 * Mostrar el gestor de categorías
 */
function showCategoryManager(context) {
    const modal = document.getElementById('categoryModal');
    if (modal) {
        modal.style.display = 'block';
        // Focus en el input de nueva categoría
        setTimeout(() => {
            const input = document.getElementById('new_category_input');
            if (input) input.focus();
        }, 100);
    }
}

/**
 * Cerrar el gestor de categorías
 */
function closeCategoryManager() {
    const modal = document.getElementById('categoryModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Eliminar categoría
 */
function deleteCategory(categoryName) {
    if (!confirm('¿Eliminar la categoría "' + categoryName + '"?\n\nSolo se pueden eliminar categorías que no estén en uso.')) {
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const form = document.createElement('form');
    form.method = 'POST';
    [['action', 'delete_category'], ['csrf_token', csrfToken], ['category_name', categoryName]].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

/**
 * Alias para showCategoryManager
 */
function openCategoryManager() {
    showCategoryManager();
}

/**
 * Solicitar nuevo nombre para una categoría
 */
function renameCategoryPrompt(oldName) {
    const newName = prompt('Renombrar categoría "' + oldName + '" a:', oldName);
    if (newName && newName.trim() !== '' && newName !== oldName) {
        renameCategory(oldName, newName.trim());
    }
}

/**
 * Renombrar categoría
 */
function renameCategory(oldName, newName) {
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const form = document.createElement('form');
    form.method = 'POST';
    [['action', 'rename_category'], ['csrf_token', csrfToken], ['old_name', oldName], ['new_name', newName]].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

/**
 * Confirmar eliminación de categoría vacía
 */
function deleteCategoryConfirm(categoryName) {
    if (!confirm('¿Eliminar la categoría vacía "' + categoryName + '"?\n\nEsta acción eliminará la carpeta vacía del disco.')) {
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const form = document.createElement('form');
    form.method = 'POST';
    [['action', 'delete_category'], ['csrf_token', csrfToken], ['category', categoryName]].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

/**
 * Aplicar filtros combinados (categoría + actividad)
 */
function applyFilters() {
    const categorySelect = document.getElementById('filter_category');
    const activitySelect = document.getElementById('filter_activity');

    // Si no hay ningún selector, no hacer nada
    if (!categorySelect && !activitySelect) return;

    const selectedCategory = categorySelect ? categorySelect.value : '';
    const selectedActivity = activitySelect ? activitySelect.value : '';
    const normalView = document.getElementById('normal-view');
    const groupedView = document.getElementById('grouped-view');
    const allPaginationControls = document.querySelectorAll('.pagination-controls');

    // Si no hay filtros activos, restaurar vista original sin recargar
    if (selectedCategory === '' && selectedActivity === '') {
        const url = new URL(window.location);
        url.searchParams.delete('filter_activity');
        url.searchParams.delete('filter_category');
        history.replaceState({}, '', url.toString());
        if (typeof applyFiltersWithoutReload === 'function') applyFiltersWithoutReload();
        return;
    }

    // Si hay filtro de actividad, añadir a URL y recargar para cargar datos completos
    if (selectedActivity !== '') {
        const url = new URL(window.location);
        url.searchParams.set('filter_activity', selectedActivity);
        if (selectedCategory !== '') {
            url.searchParams.set('filter_category', selectedCategory);
        } else {
            url.searchParams.delete('filter_category');
        }
        window.location = url.toString();
        return;
    }

    if (typeof podcastsData !== 'undefined') {
        // Filtrar podcasts por categoría Y actividad
        const filteredPodcasts = podcastsData.filter(podcast => {
            // Filtro por categoría
            const matchesCategory = selectedCategory === '' || podcast.category === selectedCategory;

            // Filtro por actividad
            const matchesActivity = selectedActivity === '' || podcast.statusInfo.class === selectedActivity;

            return matchesCategory && matchesActivity;
        });

        // Renderizar en vista normal
        if (normalView) {
            const podcastContainer = normalView.querySelector('.row') || normalView;
            podcastContainer.innerHTML = filteredPodcasts.map(podcast => {
                const statusClass = podcast.statusInfo.class || 'unknown';
                const statusText = podcast.statusInfo.status || 'No disponible';
                const statusDate = podcast.statusInfo.date || '';
                const statusDays = podcast.statusInfo.days || 0;
                const cacheAge = podcast.feedInfo.cache_age ? Math.floor(podcast.feedInfo.cache_age / 3600) : 0;
                const isCached = podcast.feedInfo.cached && cacheAge > 0;

                let lastEpisodeHtml = '';
                if (podcast.feedInfo.timestamp !== null) {
                    lastEpisodeHtml = `${statusText} - Último episodio: ${statusDate} (hace ${statusDays} días)`;
                    if (isCached) {
                        lastEpisodeHtml += ` <span class="cache-indicator">(comprobado hace ${cacheAge}h)</span>`;
                    }
                } else {
                    lastEpisodeHtml = `⚠️ ${statusText}`;
                }

                const pausedClass = podcast.paused ? 'podcast-paused' : '';
                const pausedBadge = podcast.paused ? '<span class="badge-paused">⏸️ PAUSADO</span>' : '';

                const pauseResumeButton = podcast.paused
                    ? `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="resume_podcast">
                        <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                        <input type="hidden" name="index" value="${podcast.index}">
                        <button type="submit" class="btn btn-success"><span class="btn-icon">▶️</span> Reanudar</button>
                    </form>`
                    : `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="pause_podcast">
                        <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                        <input type="hidden" name="index" value="${podcast.index}">
                        <button type="submit" class="btn btn-secondary"><span class="btn-icon">⏸️</span> Pausar</button>
                    </form>`;

                return `
                    <div class="podcast-item podcast-item-${statusClass} ${pausedClass}" data-category="${escapeHtml(podcast.category)}">
                        <div class="podcast-info">
                            <strong>${escapeHtml(podcast.name)} ${pausedBadge}</strong>
                            <small>Categoría: ${escapeHtml(podcast.category)} | Caducidad: ${podcast.caducidad} días</small>
                            <small>${escapeHtml(podcast.url)}</small>
                            <div class="last-episode ${statusClass}">
                                ${lastEpisodeHtml}
                            </div>
                        </div>
                        <div class="podcast-actions">
                            <button type="button" class="btn btn-warning" onclick="showEditPodcastModal(${podcast.index})">
                                <span class="btn-icon">✏️</span> Editar
                            </button>
                            ${pauseResumeButton}
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_podcast">
                                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                                <input type="hidden" name="index" value="${podcast.index}">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')">
                                    <span class="btn-icon">🗑️</span> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Renderizar en vista agrupada (un solo grupo con todos los podcasts de esa categoría)
        if (groupedView) {
            groupedView.innerHTML = `
                <div class="category-group" data-category="${escapeHtml(selectedCategory)}">
                    <h3 class="category-title">${escapeHtml(selectedCategory)} (${filteredPodcasts.length})</h3>
                    <div class="row">
                        ${filteredPodcasts.map(podcast => {
                            const statusClass = podcast.statusInfo.class || 'unknown';
                            const statusText = podcast.statusInfo.status || 'No disponible';
                            const statusDate = podcast.statusInfo.date || '';
                            const statusDays = podcast.statusInfo.days || 0;
                            const cacheAge = podcast.feedInfo.cache_age ? Math.floor(podcast.feedInfo.cache_age / 3600) : 0;
                            const isCached = podcast.feedInfo.cached && cacheAge > 0;

                            let lastEpisodeHtml = '';
                            if (podcast.feedInfo.timestamp !== null) {
                                lastEpisodeHtml = `${statusText} - Último episodio: ${statusDate} (hace ${statusDays} días)`;
                                if (isCached) {
                                    lastEpisodeHtml += ` <span class="cache-indicator">(comprobado hace ${cacheAge}h)</span>`;
                                }
                            } else {
                                lastEpisodeHtml = `⚠️ ${statusText}`;
                            }

                            const pausedClass = podcast.paused ? 'podcast-paused' : '';
                            const pausedBadge = podcast.paused ? '<span class="badge-paused">⏸️ PAUSADO</span>' : '';

                            const pauseResumeButton = podcast.paused
                                ? `<form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="resume_podcast">
                                    <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                                    <input type="hidden" name="index" value="${podcast.index}">
                                    <button type="submit" class="btn btn-success"><span class="btn-icon">▶️</span> Reanudar</button>
                                </form>`
                                : `<form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="pause_podcast">
                                    <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                                    <input type="hidden" name="index" value="${podcast.index}">
                                    <button type="submit" class="btn btn-secondary"><span class="btn-icon">⏸️</span> Pausar</button>
                                </form>`;

                            return `
                                <div class="podcast-item podcast-item-${statusClass} ${pausedClass}" data-category="${escapeHtml(podcast.category)}">
                                    <div class="podcast-info">
                                        <strong>${escapeHtml(podcast.name)} ${pausedBadge}</strong>
                                        <small>Categoría: ${escapeHtml(podcast.category)} | Caducidad: ${podcast.caducidad} días</small>
                                        <small>${escapeHtml(podcast.url)}</small>
                                        <div class="last-episode ${statusClass}">
                                            ${lastEpisodeHtml}
                                        </div>
                                    </div>
                                    <div class="podcast-actions">
                                        <button type="button" class="btn btn-warning" onclick="showEditPodcastModal(${podcast.index})">
                                            <span class="btn-icon">✏️</span> Editar
                                        </button>
                                        ${pauseResumeButton}
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_podcast">
                                            <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                                            <input type="hidden" name="index" value="${podcast.index}">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')">
                                                <span class="btn-icon">🗑️</span> Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        // Ocultar TODAS las paginaciones durante el filtrado
        allPaginationControls.forEach(pagination => {
            pagination.style.display = 'none';
        });
    } else {
        // Fallback: método anterior de ocultar/mostrar elementos del DOM
        const items = document.querySelectorAll('#normal-view .podcast-item');
        const groups = document.querySelectorAll('#grouped-view .category-group');

        items.forEach(item => {
            if (item.dataset.category === selectedCategory) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });

        groups.forEach(group => {
            if (group.dataset.category === selectedCategory) {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });

        allPaginationControls.forEach(pagination => {
            pagination.style.display = 'none';
        });
    }
}

/**
 * Aplicar filtros sin recargar la página (para cuando ya se cargaron los datos)
 */
function applyFiltersWithoutReload() {
    const categorySelect = document.getElementById('filter_category');
    const activitySelect = document.getElementById('filter_activity');

    if (!categorySelect && !activitySelect) return;

    const selectedCategory = categorySelect ? categorySelect.value : '';
    const selectedActivity = activitySelect ? activitySelect.value : '';
    const normalView = document.getElementById('normal-view');
    const groupedView = document.getElementById('grouped-view');
    const allPaginationControls = document.querySelectorAll('.pagination-controls');

    if (typeof podcastsData !== 'undefined') {
        // Filtrar podcasts por categoría Y actividad
        const filteredPodcasts = podcastsData.filter(podcast => {
            const matchesCategory = selectedCategory === '' || podcast.category === selectedCategory;
            const matchesActivity = selectedActivity === '' || podcast.statusInfo.class === selectedActivity;
            return matchesCategory && matchesActivity;
        });

        // Renderizar en vista normal
        if (normalView) {
            const podcastContainer = normalView.querySelector('.row') || normalView;
            podcastContainer.innerHTML = filteredPodcasts.map(podcast => {
                const statusClass = podcast.statusInfo.class || 'unknown';
                const statusText = podcast.statusInfo.status || 'No disponible';
                const statusDate = podcast.statusInfo.date || '';
                const statusDays = podcast.statusInfo.days || 0;
                const cacheAge = podcast.feedInfo.cache_age ? Math.floor(podcast.feedInfo.cache_age / 3600) : 0;
                const isCached = podcast.feedInfo.cached && cacheAge > 0;

                let lastEpisodeHtml = '';
                if (podcast.feedInfo.timestamp !== null) {
                    lastEpisodeHtml = `${statusText} - Último episodio: ${statusDate} (hace ${statusDays} días)`;
                    if (isCached) {
                        lastEpisodeHtml += ` <span class="cache-indicator">(comprobado hace ${cacheAge}h)</span>`;
                    }
                } else {
                    lastEpisodeHtml = `⚠️ ${statusText}`;
                }

                const pausedClass = podcast.paused ? 'podcast-paused' : '';
                const pausedBadge = podcast.paused ? '<span class="badge-paused">⏸️ PAUSADO</span>' : '';

                const pauseResumeButton = podcast.paused
                    ? `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="resume_podcast">
                        <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                        <input type="hidden" name="index" value="${podcast.index}">
                        <button type="submit" class="btn btn-success"><span class="btn-icon">▶️</span> Reanudar</button>
                    </form>`
                    : `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="pause_podcast">
                        <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                        <input type="hidden" name="index" value="${podcast.index}">
                        <button type="submit" class="btn btn-secondary"><span class="btn-icon">⏸️</span> Pausar</button>
                    </form>`;

                return `
                    <div class="podcast-item podcast-item-${statusClass} ${pausedClass}" data-category="${escapeHtml(podcast.category)}">
                        <div class="podcast-info">
                            <strong>${escapeHtml(podcast.name)} ${pausedBadge}</strong>
                            <small>Categoría: ${escapeHtml(podcast.category)} | Caducidad: ${podcast.caducidad} días</small>
                            <small>${escapeHtml(podcast.url)}</small>
                            <div class="last-episode ${statusClass}">
                                ${lastEpisodeHtml}
                            </div>
                        </div>
                        <div class="podcast-actions">
                            <button type="button" class="btn btn-warning" onclick="showEditPodcastModal(${podcast.index})">
                                <span class="btn-icon">✏️</span> Editar
                            </button>
                            ${pauseResumeButton}
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_podcast">
                                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                                <input type="hidden" name="index" value="${podcast.index}">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')">
                                    <span class="btn-icon">🗑️</span> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Ocultar paginaciones durante el filtrado
        allPaginationControls.forEach(pagination => {
            pagination.style.display = 'none';
        });
    }
}

/**
 * Función de compatibilidad: redirige a applyFilters()
 */
function filterByCategory() {
    applyFilters();
}

/**
 * Toggle entre vista normal y agrupada
 */
let isGroupedView = false;

function toggleGroupView() {
    const normalView = document.getElementById('normal-view');
    const groupedView = document.getElementById('grouped-view');
    const btnText = document.getElementById('viewModeText');
    const filterSelect = document.getElementById('filter_category');
    const paginationControls = document.querySelector('.pagination-controls');
    
    if (!normalView || !groupedView) return;
    
    isGroupedView = !isGroupedView;

    // Guardar estado en localStorage
    localStorage.setItem('sapo_view_mode', isGroupedView ? 'grouped' : 'normal');
    
    if (isGroupedView) {
        normalView.style.display = 'none';
        groupedView.style.display = 'block';
        if (btnText) btnText.textContent = 'Vista alfabetica';
    } else {
        normalView.style.display = 'block';
        groupedView.style.display = 'none';
        if (btnText) btnText.textContent = 'Agrupar por categoria';
    }
    
    // Aplicar filtro en la nueva vista
    if (filterSelect) {
        filterByCategory();
    }
}

/**
 * Obtener nombre de usuario de la sesión (desde el DOM)
 */
function getUsername() {
    // Intentar obtener el nombre de usuario del DOM
    const usernameElements = document.querySelectorAll('strong');
    for (let elem of usernameElements) {
        if (elem.textContent && elem.textContent.trim().length > 0) {
            // Buscar si hay un patrón tipo "Conectado como XXX"
            const parent = elem.parentElement;
            if (parent && parent.textContent.includes('Conectado como')) {
                return elem.textContent.trim();
            }
        }
    }
    return 'usuario';
}



/**
 * Restaurar vista guardada al cargar la pagina
 */
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('sapo_view_mode');

    if (savedView === 'grouped') {
        // Restaurar vista agrupada
        const normalView = document.getElementById('normal-view');
        const groupedView = document.getElementById('grouped-view');
        const btnText = document.getElementById('viewModeText');
        const paginationControls = document.querySelector('.pagination-controls');

        if (normalView && groupedView) {
            isGroupedView = true;
            normalView.style.display = 'none';
            groupedView.style.display = 'block';
            if (btnText) btnText.textContent = 'Vista alfabetica';
        }
    }
});

/**
 * Cerrar modales al hacer clic fuera
 */
window.onclick = function(event) {
    const addPodcastModal = document.getElementById('addPodcastModal');
    const categoryModal = document.getElementById('categoryModal');

    if (event.target === addPodcastModal) {
        closeAddPodcastModal();
    }
    if (event.target === categoryModal) {
        closeCategoryManager();
    }
}


/**
 * Buscar podcasts en tiempo real
 */
function searchPodcasts() {
    const input = document.getElementById('search-podcasts');
    if (!input) return;

    const filter = input.value.toLowerCase();
    const normalView = document.getElementById('normal-view');
    const groupedView = document.getElementById('grouped-view');
    const searchResults = document.getElementById('search-results');
    const searchResultsList = document.getElementById('search-results-list');
    const searchCount = document.getElementById('search-count');
    const paginationControls = document.querySelector('.pagination-controls');

    // Si no hay filtro, restaurar la vista normal con paginación
    if (filter === '') {
        if (searchResults) searchResults.style.display = 'none';
        if (normalView) normalView.style.display = 'block';
        if (groupedView && isGroupedView) {
            groupedView.style.display = 'block';
            normalView.style.display = 'none';
        }
        if (paginationControls) paginationControls.style.display = '';
        return;
    }

    // Ocultar controles de paginación durante la búsqueda
    if (paginationControls) paginationControls.style.display = 'none';

    // Buscar en TODOS los podcasts usando podcastsData
    if (typeof podcastsData !== 'undefined' && searchResults && searchResultsList) {
        // Filtrar podcasts que coinciden con la búsqueda
        const matchingPodcasts = podcastsData.filter(podcast => {
            return podcast.name.toLowerCase().includes(filter);
        });

        // Ocultar vistas normales y mostrar resultados de búsqueda
        if (normalView) normalView.style.display = 'none';
        if (groupedView) groupedView.style.display = 'none';
        searchResults.style.display = 'block';

        // Actualizar contador
        searchCount.textContent = `Se encontraron ${matchingPodcasts.length} podcast${matchingPodcasts.length !== 1 ? 's' : ''} con "${filter}"`;

        // Renderizar resultados
        if (matchingPodcasts.length > 0) {
            searchResultsList.innerHTML = matchingPodcasts.map(podcast => {
                const statusClass = podcast.statusInfo.class || 'unknown';
                const statusText = podcast.statusInfo.status || 'No disponible';
                const statusDate = podcast.statusInfo.date || '';
                const statusDays = podcast.statusInfo.days || 0;
                const cacheAge = podcast.feedInfo.cache_age ? Math.floor(podcast.feedInfo.cache_age / 3600) : 0;
                const isCached = podcast.feedInfo.cached && cacheAge > 0;

                let lastEpisodeHtml = '';
                if (podcast.feedInfo.timestamp !== null) {
                    lastEpisodeHtml = `${statusText} - Último episodio: ${statusDate} (hace ${statusDays} días)`;
                    if (isCached) {
                        lastEpisodeHtml += ` <span class="cache-indicator">(comprobado hace ${cacheAge}h)</span>`;
                    }
                } else {
                    lastEpisodeHtml = `⚠️ ${statusText}`;
                }

                const pausedClass = podcast.paused ? 'podcast-paused' : '';
                const pausedBadge = podcast.paused ? '<span class="badge-paused">⏸️ PAUSADO</span>' : '';

                const pauseResumeButton = podcast.paused
                    ? `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="resume_podcast">
                        <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                        <input type="hidden" name="index" value="${podcast.index}">
                        <button type="submit" class="btn btn-success"><span class="btn-icon">▶️</span> Reanudar</button>
                    </form>`
                    : `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="pause_podcast">
                        <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                        <input type="hidden" name="index" value="${podcast.index}">
                        <button type="submit" class="btn btn-secondary"><span class="btn-icon">⏸️</span> Pausar</button>
                    </form>`;

                return `
                    <div class="podcast-item podcast-item-${statusClass} ${pausedClass}" data-category="${escapeHtml(podcast.category)}">
                        <div class="podcast-info">
                            <strong>${escapeHtml(podcast.name)} ${pausedBadge}</strong>
                            <small>Categoría: ${escapeHtml(podcast.category)} | Caducidad: ${podcast.caducidad} días</small>
                            <small>${escapeHtml(podcast.url)}</small>
                            <div class="last-episode ${statusClass}">
                                ${lastEpisodeHtml}
                            </div>
                        </div>
                        <div class="podcast-actions">
                            <button type="button" class="btn btn-warning" onclick="showEditPodcastModal(${podcast.index})">
                                <span class="btn-icon">✏️</span> Editar
                            </button>
                            ${pauseResumeButton}
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_podcast">
                                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                                <input type="hidden" name="index" value="${podcast.index}">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminar este podcast?')">
                                    <span class="btn-icon">🗑️</span> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            searchResultsList.innerHTML = `
                <div style="padding: 40px; text-align: center; color: #718096;">
                    <p style="font-size: 16px; margin: 0;">No se encontraron podcasts que coincidan con "${escapeHtml(filter)}"</p>
                </div>
            `;
        }
    } else {
        // Fallback al método anterior si no hay podcastsData o elementos de búsqueda
        // Buscar en vista normal
        if (normalView) {
            const items = normalView.querySelectorAll('.podcast-item');
            items.forEach(item => {
                const podcastName = item.querySelector('strong')?.textContent.toLowerCase() || '';
                if (podcastName.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Buscar en vista agrupada
        if (groupedView) {
            const groups = groupedView.querySelectorAll('.category-group');
            groups.forEach(group => {
                const items = group.querySelectorAll('.podcast-item');
                let visibleCount = 0;

                items.forEach(item => {
                    const podcastName = item.querySelector('strong')?.textContent.toLowerCase() || '';
                    if (podcastName.includes(filter)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Ocultar categoria si no hay podcasts visibles
                if (visibleCount === 0 && filter !== '') {
                    group.style.display = 'none';
                } else {
                    group.style.display = '';
                }
            });
        }
    }
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Obtener el token CSRF del DOM
 */
function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

/**
 * Cargar informe de descargas por período
 */
function loadReport(days, button) {
    // Actualizar botones activos
    const buttons = document.querySelectorAll('.period-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    if (button) {
        button.classList.add('active');
    }

    // Mostrar loading
    const container = document.getElementById('report-container');
    if (!container) return;

    container.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="color: #667eea; font-size: 18px;">⏳ Cargando informe...</div></div>';

    // Obtener CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Petición AJAX
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=load_report&days=${days}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.html) {
            container.innerHTML = data.html;
        } else {
            container.innerHTML = '<div class="alert alert-error">Error al cargar el informe</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div class="alert alert-error">Error al cargar el informe. Por favor, recarga la página.</div>';
    });
}


/**
 * Auto-ocultar mensajes de alerta después de 5 segundos
 */
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-success, .alert-error, .alert-warning, .alert-info');
    
    alerts.forEach(alert => {
        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            
            // Eliminar del DOM después de la transición
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

/**
 * Modal de progreso de actualización de feeds
 */
function showFeedsProgressModal() {
    const modal = document.getElementById('feedsProgressModal');
    if (modal) {
        modal.style.display = 'block';
        resetFeedsProgress();
    }
}

function closeFeedsProgressModal() {
    const modal = document.getElementById('feedsProgressModal');
    if (modal) {
        modal.style.display = 'none';
        
        // Si la actualización se completó, recargar la página
        if (window.feedsUpdateCompleted) {
            window.feedsUpdateCompleted = false;
            location.reload();
        }
    }
}

function resetFeedsProgress() {
    document.getElementById('feedsProgressText').textContent = 'Preparando actualización...';
    document.getElementById('feedsProgressBar').style.width = '0%';
    document.getElementById('feedsProgressPercent').textContent = '0%';
    document.getElementById('feedsCurrentPodcast').style.display = 'none';
    document.getElementById('feedsLog').style.display = 'none';
    document.getElementById('feedsLogContent').innerHTML = '';
    document.getElementById('feedsCloseButtonContainer').style.display = 'none';
}

function updateFeedsProgress(current, total, podcastName) {
    const percent = Math.round((current / total) * 100);

    // Actualizar barra de progreso
    document.getElementById('feedsProgressBar').style.width = percent + '%';
    document.getElementById('feedsProgressPercent').textContent = percent + '%';

    // Actualizar texto principal
    document.getElementById('feedsProgressText').textContent = 'Actualizando feeds... ' + current + ' de ' + total;

    // Mostrar podcast actual
    if (podcastName) {
        document.getElementById('feedsCurrentPodcast').style.display = 'block';
        document.getElementById('feedsCurrentPodcastName').textContent = podcastName;

        // Añadir al log
        const logContent = document.getElementById('feedsLogContent');
        const logEntry = document.createElement('div');
        logEntry.style.padding = '3px 0';
        logEntry.style.color = '#4a5568';
        logEntry.innerHTML = '<span style="color: #48bb78;">✓</span> ' + escapeHtml(podcastName);
        logContent.appendChild(logEntry);

        // Scroll automático al último elemento
        const feedsLog = document.getElementById('feedsLog');
        feedsLog.style.display = 'block';
        feedsLog.scrollTop = feedsLog.scrollHeight;
    }
}

function finishFeedsProgress(total) {
    document.getElementById('feedsProgressText').innerHTML = '<span style="color: #48bb78;">✓</span> ¡Actualización completada! ' + total + ' feeds actualizados';
    document.getElementById('feedsProgressBar').style.background = 'linear-gradient(90deg, #48bb78, #38a169)';
    document.getElementById('feedsCurrentPodcast').style.display = 'none';
    document.getElementById('feedsCloseButtonContainer').style.display = 'block';
    
    // Marcar que se debe recargar al cerrar
    window.feedsUpdateCompleted = true;
}

/**
 * Iniciar actualización progresiva de feeds
 */
async function refreshFeedsWithProgress() {
    try {
        showFeedsProgressModal();

        // Obtener total de podcasts
        const initResponse = await fetch('?action=refresh_feeds');
        
        if (!initResponse.ok) {
            throw new Error('Error de red: ' + initResponse.status);
        }
        
        const initData = await initResponse.json();

        if (!initData.success) {
            throw new Error(initData.error || 'Error al inicializar actualización');
        }

        const total = initData.total;
        
        // Si no hay podcasts
        if (total === 0) {
            document.getElementById('feedsProgressText').innerHTML = '<span style="color: #f59e0b;">⚠️ No hay podcasts para actualizar</span>';
            document.getElementById('feedsProgressBar').style.width = '100%';
            document.getElementById('feedsProgressBar').style.background = '#f59e0b';
            document.getElementById('feedsProgressPercent').textContent = '100%';
            document.getElementById('feedsCloseButtonContainer').style.display = 'block';
            return;
        }

        // Actualizar cada feed uno por uno
        for (let i = 0; i < total; i++) {
            const response = await fetch('?action=refresh_feeds&index=' + i);
            const data = await response.json();

            if (data.success) {
                updateFeedsProgress(i + 1, total, data.podcast);
            } else {
                console.error('Error al actualizar feed:', data.error);
                // Actualizar progreso incluso si falla, para que la barra llegue al 100%
                updateFeedsProgress(i + 1, total, data.error || 'Error');
            }
        }

        // Guardar timestamp de última actualización
        await fetch('?action=save_feeds_timestamp');

        // Finalizar
        finishFeedsProgress(total);

    } catch (error) {
        console.error('Error en actualización de feeds:', error);
        const feedsErrSpan = document.createElement('span');
        feedsErrSpan.style.color = '#ef4444';
        feedsErrSpan.textContent = '❌ Error: ' + error.message;
        document.getElementById('feedsProgressText').replaceChildren(feedsErrSpan);
        document.getElementById('feedsCloseButtonContainer').style.display = 'block';
    }
}

// Iniciar actualización al cargar la página (si es login reciente)
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si se debe abrir una pestaña concreta (e.g. paginación)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        switchTab(tabParam);
        if (tabParam === 'recordings') loadRecordings();
        if (tabParam === 'config') { loadTimeSignalsFiles(); loadTimeSignalsConfig(); }
    }

    // Verificar si se debe actualizar automáticamente
    if (urlParams.get('auto_refresh_feeds') === '1') {
        // Pequeña espera para que el usuario vea la página
        setTimeout(() => {
            refreshFeedsWithProgress();
        }, 500);

        // Limpiar el parámetro de la URL sin recargar
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Inicializar dropzone si existe
    initializeTimeSignalsDropzone();
});

// ============================================================================
// FUNCIONES PARA SEÑALES HORARIAS
// ============================================================================

/**
 * Inicializar Dropzone para subir archivos
 */
function initializeTimeSignalsDropzone() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');

    if (!dropzone || !fileInput) return;

    // Click en dropzone abre selector de archivos
    dropzone.addEventListener('click', () => {
        fileInput.click();
    });

    // Drag & Drop
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('drag-over');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        handleFileUpload(files);
    });

    // Selección de archivos
    fileInput.addEventListener('change', (e) => {
        const files = e.target.files;
        handleFileUpload(files);
    });

    // Submit del formulario
    const form = document.getElementById('time-signals-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            saveTimeSignalsConfig();
        });
    }

    // Cargar archivos y configuración inicial
    loadTimeSignalsFiles();
    loadTimeSignalsConfig();
}

/**
 * Manejar subida de archivos
 */
function handleFileUpload(files) {
    if (!files || files.length === 0) return;

    const progressDiv = document.getElementById('upload-progress');
    const progressFill = document.getElementById('progress-fill');
    const statusText = document.getElementById('upload-status');

    progressDiv.style.display = 'block';
    progressFill.style.width = '0%';
    statusText.textContent = 'Preparando archivos...';

    // Validar archivos
    const validFiles = [];
    const maxSize = 10 * 1024 * 1024; // 10MB
    const allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a'];

    for (let file of files) {
        if (file.size > maxSize) {
            alert(`El archivo ${file.name} excede el tamaño máximo de 10MB`);
            continue;
        }
        validFiles.push(file);
    }

    if (validFiles.length === 0) {
        progressDiv.style.display = 'none';
        return;
    }

    // Subir archivos uno por uno
    uploadFilesSequentially(validFiles, 0, progressFill, statusText, progressDiv);
}

/**
 * Subir archivos de forma secuencial
 */
function uploadFilesSequentially(files, index, progressFill, statusText, progressDiv) {
    if (index >= files.length) {
        progressFill.style.width = '100%';
        statusText.innerHTML = '<span style="color: #10b981;">✅ Archivo subido correctamente (reemplazó al anterior)</span>';
        setTimeout(() => {
            progressDiv.style.display = 'none';
            loadTimeSignalsFiles();
        }, 2000);
        return;
    }

    const file = files[index];
    const formData = new FormData();
    formData.append('action', 'upload_time_signal');
    formData.append('file', file);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    const progress = ((index) / files.length) * 100;
    progressFill.style.width = progress + '%';

    // Si hay múltiples archivos, avisar que solo se usará el último
    if (files.length > 1) {
        statusText.textContent = `Subiendo ${file.name} (${index + 1}/${files.length}) - Solo se guardará el último`;
    } else {
        statusText.textContent = `Subiendo ${file.name}...`;
    }

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            uploadFilesSequentially(files, index + 1, progressFill, statusText, progressDiv);
        } else {
            statusText.innerHTML = `<span style="color: #ef4444;">❌ Error: ${escapeHtml(data.message || 'Error desconocido')}</span>`;
            setTimeout(() => progressDiv.style.display = 'none', 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusText.innerHTML = '<span style="color: #ef4444;">❌ Error al subir archivo</span>';
        setTimeout(() => progressDiv.style.display = 'none', 3000);
    });
}

/**
 * Cargar lista de archivos subidos (simplificado)
 */
function loadTimeSignalsFiles() {
    const filesList = document.getElementById('files-list');
    const currentFileSpan = document.getElementById('current-signal-file');

    if (!filesList) return;

    filesList.innerHTML = '<p style="color: #718096; text-align: center;">Cargando...</p>';

    fetch('index.php?action=list_time_signals')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.files && data.files.length > 0) {
                const file = data.files[0]; // Solo hay un archivo
                filesList.innerHTML = `
                    <div class="file-item" style="background: #f7fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div class="file-item-info">
                            <span class="file-item-icon" style="font-size: 32px;">🎵</span>
                            <div>
                                <div class="file-item-name" style="font-weight: 500; color: #2d3748;">${file.name}</div>
                                <div class="file-item-size" style="color: #718096; font-size: 13px;">${file.size}</div>
                            </div>
                        </div>
                    </div>
                    <p style="color: #718096; font-size: 13px; margin-top: 10px; text-align: center;">
                        💡 Para cambiar el archivo, simplemente sube uno nuevo y reemplazará al anterior.
                    </p>
                `;

                // Mostrar archivo activo
                if (currentFileSpan) {
                    currentFileSpan.textContent = file.name;
                }
            } else {
                filesList.innerHTML = '<p style="color: #718096; text-align: center;">No hay archivos subidos. Sube uno para activar las señales horarias.</p>';
                if (currentFileSpan) {
                    currentFileSpan.textContent = 'Ninguno - Sube un archivo primero';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            filesList.innerHTML = '<p style="color: #ef4444; text-align: center;">Error al cargar archivos</p>';
        });
}

/**
 * Cargar configuración de señales horarias (simplificado)
 */
function loadTimeSignalsConfig() {
    fetch('index.php?action=get_time_signals_config')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.config) {
                const config = data.config;

                // Seleccionar frecuencia
                const frequencySelect = document.getElementById('signal-frequency');
                if (frequencySelect && config.frequency) {
                    frequencySelect.value = config.frequency;
                }

                // Actualizar nombre de archivo activo
                const currentFileSpan = document.getElementById('current-signal-file');
                if (currentFileSpan && config.signal_file) {
                    currentFileSpan.textContent = config.signal_file;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

/**
 * Guardar configuración de señales horarias
 */
function saveTimeSignalsConfig() {
    const statusDiv = document.getElementById('config-status');
    const form = document.getElementById('time-signals-form');
    const formData = new FormData(form);

    formData.append('action', 'save_time_signals_config');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    statusDiv.innerHTML = '<p style="color: #718096;">Guardando configuración...</p>';

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ Configuración guardada y aplicada correctamente</div>';
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 3000);
        } else {
            statusDiv.innerHTML = `<div class="alert alert-error">❌ Error: ${escapeHtml(data.message || 'Error desconocido')}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-error">❌ Error al guardar configuración</div>';
    });
}

/**
 * Sincronizar configuración desde Liquidsoap
 */
function syncFromLiquidsoap() {
    const statusDiv = document.getElementById('config-status');
    const formData = new FormData();

    formData.append('action', 'sync_time_signals_from_liquidsoap');

    statusDiv.innerHTML = '<p style="color: #718096;">🔍 Sincronizando desde Liquidsoap...</p>';

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ ' + escapeHtml(data.message) + '</div>';

            // Actualizar el formulario con la configuración sincronizada
            if (data.config) {
                const frequencySelect = document.getElementById('signal-frequency');
                if (frequencySelect && data.config.frequency) {
                    frequencySelect.value = data.config.frequency;
                }

                const currentFileSpan = document.getElementById('current-signal-file');
                if (currentFileSpan && data.config.signal_file) {
                    currentFileSpan.textContent = data.config.signal_file;
                }
            }

            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 4000);
        } else {
            statusDiv.innerHTML = '<div class="alert alert-error">❌ ' + escapeHtml(data.message) + '</div>';
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 5000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-error">❌ Error al sincronizar</div>';
        setTimeout(() => {
            statusDiv.innerHTML = '';
        }, 4000);
    });
}

// ============================================================================
// FUNCIONES OBSOLETAS (Ya no se usan en formulario simplificado)
// ============================================================================

/*
function updateDayInfo() {
    // Ya no se usa - formulario simplificado
}

function toggleAllDays() {
    // Ya no se usa - formulario simplificado
}
*/
