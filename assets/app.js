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
}


/**
 * Mostrar nombre del archivo seleccionado
 */
function showFileName(input) {
    const fileName = input.files[0]?.name || '';
    const fileNameSpan = document.getElementById('fileName');
    if (fileNameSpan) {
        fileNameSpan.textContent = fileName ? fileName : '';
    }
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
            // El script se envió correctamente
            statusDiv.innerHTML = `
                <div class="alert alert-success">
                    ✅ Descargas iniciadas correctamente.
                    <br><br>
                    <small style="color: #718096;">
                        La descarga se ejecuta de fondo. Los nuevos archivos estarán disponibles en Radiobot en 5-10 min. aproximadamente.
                    </small>
                </div>
            `;
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
    
    fetch(window.location.href + '?action=check_podget_status&_=' + Date.now())
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
    
    const form = document.createElement('form');
    form.method = 'POST';
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="category_name" value="${categoryName}">
    `;
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
    const form = document.createElement('form');
    form.method = 'POST';
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    form.innerHTML = `
        <input type="hidden" name="action" value="rename_category">
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="old_name" value="${oldName}">
        <input type="hidden" name="new_name" value="${newName}">
    `;
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

    const form = document.createElement('form');
    form.method = 'POST';
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="category" value="${categoryName}">
    `;
    document.body.appendChild(form);
    form.submit();
}

/**
 * Filtrar podcasts por categoría
 */
function filterByCategory() {
    const select = document.getElementById('filter_category');
    if (!select) return;

    const selectedCategory = select.value;
    const normalView = document.getElementById('normal-view');
    const groupedView = document.getElementById('grouped-view');
    // Obtener TODAS las paginaciones (vista normal y agrupada)
    const allPaginationControls = document.querySelectorAll('.pagination-controls');

    if (selectedCategory === '') {
        // Restaurar vista original: recargar la página para volver al estado inicial
        window.location.reload();
    } else if (typeof podcastsData !== 'undefined') {
        // Filtrar TODOS los podcasts por categoría usando podcastsData
        const filteredPodcasts = podcastsData.filter(podcast => podcast.category === selectedCategory);

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
 * (excepto el recordatorio de Radiobot que requiere cierre manual)
 */
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-success, .alert-error, .alert-warning, .alert-info');

    alerts.forEach(alert => {
        // No auto-ocultar el recordatorio de Radiobot (requiere cierre manual)
        if (alert.classList.contains('alert-radiobot-reminder')) {
            return;
        }

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
 * Cerrar recordatorio de Radiobot manualmente
 */
function closeRadiobotReminder(button) {
    const alert = button.closest('.alert-radiobot-reminder');
    if (alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';

        setTimeout(() => {
            alert.remove();
        }, 500);
    }
}

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
        document.getElementById('feedsProgressText').innerHTML = '<span style="color: #ef4444;">❌ Error: ' + error.message + '</span>';
        document.getElementById('feedsCloseButtonContainer').style.display = 'block';
    }
}

// Iniciar actualización al cargar la página (si es login reciente)
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si se debe actualizar automáticamente
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto_refresh_feeds') === '1') {
        // Pequeña espera para que el usuario vea la página
        setTimeout(() => {
            refreshFeedsWithProgress();
        }, 500);

        // Limpiar el parámetro de la URL sin recargar
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
