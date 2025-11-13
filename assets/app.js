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
 * Filtrar podcasts por categoría
 */
function filterByCategory() {
    const select = document.getElementById('filter_category');
    if (!select) return;
    
    const selectedCategory = select.value;
    const items = document.querySelectorAll('#normal-view .podcast-item');
    const groups = document.querySelectorAll('#grouped-view .category-group');
    
    if (selectedCategory === '') {
        // Mostrar todos
        items.forEach(item => item.style.display = '');
        groups.forEach(group => group.style.display = '');
    } else {
        // Filtrar por categoría
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
    
    if (!normalView || !groupedView) return;
    
    isGroupedView = !isGroupedView;
    
    if (isGroupedView) {
        normalView.style.display = 'none';
        groupedView.style.display = 'block';
        if (btnText) btnText.textContent = 'Vista alfabética';
    } else {
        normalView.style.display = 'block';
        groupedView.style.display = 'none';
        if (btnText) btnText.textContent = 'Agrupar por categoría';
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

