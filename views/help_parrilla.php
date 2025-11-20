<?php
// views/help_parrilla.php - Página de ayuda específica para la Parrilla de programación
?>

<div class="card">
    <div class="nav-buttons" style="margin-bottom: 30px;">
        <h2>📅 Ayuda - Parrilla de programación</h2>
        <div style="text-align: right;">
            <?php if (isLoggedIn()): ?>
                <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <?php endif; ?>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>?page=help" class="btn btn-secondary" style="margin-right: 10px;">
                <span class="btn-icon">📖</span> Ayuda General
            </a>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                <span class="btn-icon">⬅️</span> Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Índice de contenidos -->
    <div id="indice" style="background: #f7fafc; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 30px;">
        <h3 style="margin-top: 0;">📑 Contenido</h3>
        <ul style="line-height: 1.8;">
            <li><a href="#que-es">¿Qué es la parrilla de programación?</a></li>
            <li><a href="#configuracion">Configuración de la parrilla</a></li>
            <li><a href="#estilos">Estilos visuales disponibles</a></li>
            <li><a href="#integracion">Integración con AzuraCast</a></li>
            <li><a href="#programas-directo">Programas en directo manuales</a></li>
            <li><a href="#feed-rss">Feed RSS en programas</a></li>
            <li><a href="#urls">URLs de la parrilla</a></li>
            <li><a href="#indicador-directo">Indicador "AHORA EN DIRECTO"</a></li>
            <li><a href="#personalizacion">Personalización de programas</a></li>
            <li><a href="#responsive">Diseño responsive</a></li>
        </ul>
    </div>

    <!-- Sección: Qué es -->
    <div id="que-es" style="margin-bottom: 40px;">
        <h3>🎯 ¿Qué es la parrilla de programación?</h3>
        <p>SAPO incluye un widget de parrilla de programación que muestra tu programación semanal de manera visual y profesional, integrándose perfectamente con AzuraCast.</p>

        <p>Es una vista organizada por días de la semana que muestra:</p>
        <ul>
            <li>📻 Programas automatizados desde AzuraCast</li>
            <li>🎙️ Programas en directo manuales</li>
            <li>🎧 Últimos episodios de podcasts con RSS</li>
            <li>🔴 Indicador de programa en emisión actual (con enlace al stream)</li>
            <li>📱 Iconos de redes sociales (Twitter/Instagram)</li>
        </ul>
    </div>

    <!-- Sección: Configuración -->
    <div id="configuracion" style="margin-bottom: 40px;">
        <h3>⚙️ Configuración de la parrilla</h3>
        <p>Para configurar la visualización de tu parrilla:</p>
        <ol>
            <li>Accede a la sección <strong>"Parrilla → Configuración"</strong> en el panel</li>
            <li>Configura los siguientes parámetros:
                <ul>
                    <li><strong>Station ID:</strong> ID de tu estación en AzuraCast (requerido)</li>
                    <li><strong>URL de la Página Pública del Stream:</strong> URL de tu emisora en AzuraCast para escucha en directo (opcional)</li>
                    <li><strong>Color del widget:</strong> Color principal de la parrilla (hexadecimal)</li>
                    <li><strong>Estilo visual:</strong> Modern, Classic, Compact o Minimal</li>
                    <li><strong>Tamaño de fuente:</strong> Small, Medium o Large</li>
                </ul>
            </li>
            <li>Guarda los cambios</li>
        </ol>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>🎯 URL del Stream:</strong> Si configuras la URL de tu página pública del stream (ej: <code>https://tu-servidor.com/public/tu_emisora</code>), el badge "🔴 AHORA EN DIRECTO" se convertirá en un enlace clickeable que llevará a tus oyentes directamente a escuchar la emisora.
        </div>
    </div>

    <!-- Sección: Estilos -->
    <div id="estilos" style="margin-bottom: 40px;">
        <h3>🎨 Estilos visuales disponibles</h3>
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 15px; margin: 20px 0;">
            <div style="background: #e6f2ff; padding: 10px 20px; border-radius: 6px; font-weight: bold;">Modern</div>
            <div style="padding: 10px 0;">Bordes redondeados, sombras suaves.</div>

            <div style="background: #f0f0f0; padding: 10px 20px; border-radius: 6px; font-weight: bold;">Classic</div>
            <div style="padding: 10px 0;">Bordes rectos, aspecto tradicional y profesional.</div>

            <div style="background: #fff3e0; padding: 10px 20px; border-radius: 6px; font-weight: bold;">Compact</div>
            <div style="padding: 10px 0;">Espaciado reducido, ideal para mostrar más programas.</div>

            <div style="background: #f5f5f5; padding: 10px 20px; border-radius: 6px; font-weight: bold;">Minimal</div>
            <div style="padding: 10px 0;">Sin bordes, máxima limpieza visual.</div>
        </div>
    </div>

    <!-- Sección: Integración -->
    <div id="integracion" style="margin-bottom: 40px;">
        <h3>📺 Integración con AzuraCast</h3>
        <p>La parrilla se sincroniza automáticamente con AzuraCast para mostrar:</p>
        <ul>
            <li>✅ Horarios de emisión de cada programa</li>
            <li>✅ Nombres de playlists configuradas</li>
            <li>✅ Detección automática del programa en emisión</li>
            <li>✅ Zona horaria correcta (Europe/Madrid - CET/CEST)</li>
        </ul>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>🔄 Sincronización automática:</strong> La parrilla se actualiza cada 10 minutos desde AzuraCast. Los cambios en tu programación se reflejan automáticamente.
        </div>
    </div>

    <!-- Sección: Programas en directo -->
    <div id="programas-directo" style="margin-bottom: 40px;">
        <h3>🎙️ Programas en directo manuales</h3>
        <p>Además de los programas automatizados de AzuraCast, puedes añadir programas en directo que se mostrarán independientemente:</p>
        <ol>
            <li>Edita un programa en SAPO</li>
            <li>Selecciona <strong>"Tipo de playlist: En directo"</strong></li>
            <li>Configura el horario:
                <ul>
                    <li><strong>Días de emisión:</strong> Selecciona los días de la semana</li>
                    <li><strong>Hora de inicio:</strong> Hora en formato HH:MM</li>
                    <li><strong>Duración:</strong> Duración en minutos</li>
                </ul>
            </li>
            <li>Añade información adicional:
                <ul>
                    <li><strong>Título personalizado:</strong> Nombre a mostrar en la parrilla</li>
                    <li><strong>Descripción corta:</strong> Subtítulo o descripción breve</li>
                    <li><strong>Descripción larga:</strong> Información detallada del programa</li>
                    <li><strong>Imagen:</strong> URL de la imagen del programa</li>
                    <li><strong>Presentadores:</strong> Nombres de los conductores</li>
                    <li><strong>Redes sociales:</strong> Usuarios de Twitter e Instagram</li>
                </ul>
            </li>
        </ol>

        <div style="background: #e6f7ff; border-left: 4px solid #1890ff; padding: 15px; margin: 15px 0;">
            <strong>✨ Ventaja:</strong> Los programas en directo se muestran con una etiqueta especial "EN DIRECTO" y un diseño distintivo en la parrilla.
        </div>
    </div>

    <!-- Sección: Feed RSS -->
    <div id="feed-rss" style="margin-bottom: 40px;">
        <h3>🎧 Feed RSS en programas</h3>
        <p>Si configuras un feed RSS en un programa, la parrilla mostrará:</p>
        <ul>
            <li>📻 Enlace al último episodio publicado</li>
            <li>📌 Título del último episodio</li>
            <li>🔗 Link clickable al episodio</li>
        </ul>

        <div style="background: #fff7e6; border-left: 4px solid #ffa940; padding: 15px; margin: 15px 0;">
            <strong>⏱️ Caché de RSS:</strong> Los feeds RSS se cachean durante 6 horas para optimizar el rendimiento. Puedes pre-cargar los feeds ejecutando el cron de RSS.
        </div>
    </div>

    <!-- Sección: URLs -->
    <div id="urls" style="margin-bottom: 40px;">
        <h3>📱 URLs de la parrilla</h3>
        <p>Existen dos versiones de la parrilla:</p>

        <h4>1. Versión completa</h4>
        <pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto;">https://tu-servidor.com/sapo/parrilla_cards.php?station=TU_USUARIO</pre>
        <p>Incluye header con nombre de la emisora y diseño completo.</p>

        <h4>2. Versión embebible (iframe)</h4>
        <pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto;">https://tu-servidor.com/sapo/parrilla_cards_embed.php?station=TU_USUARIO</pre>
        <p>Sin header, ideal para incluir en otras webs mediante iframe:</p>
        <pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto;">&lt;iframe src="https://tu-servidor.com/sapo/parrilla_cards_embed.php?station=TU_USUARIO"
        width="100%" height="800" frameborder="0"&gt;&lt;/iframe&gt;</pre>
    </div>

    <!-- Sección: Indicador directo -->
    <div id="indicador-directo" style="margin-bottom: 40px;">
        <h3>🔴 Indicador "AHORA EN DIRECTO"</h3>
        <p>La parrilla detecta automáticamente qué programa está en emisión:</p>
        <ul>
            <li>🕐 Compara la hora actual con los horarios configurados</li>
            <li>🎯 Muestra badge rojo "🔴 AHORA EN DIRECTO" en el programa activo</li>
            <li>🔗 Si tienes configurada la URL del stream, el badge es clickeable y lleva a la página de escucha</li>
            <li>📜 Auto-scroll al programa en vivo al cargar la página</li>
            <li>⚡ Si hay solapamiento, muestra solo el programa que empezó más recientemente</li>
        </ul>

        <div style="background: #ffe6e6; border-left: 4px solid #dc2626; padding: 15px; margin: 15px 0;">
            <strong>🎧 Enlace al Stream:</strong> Para que el badge "🔴 AHORA EN DIRECTO" sea clickeable, configura la <strong>URL de la Página Pública del Stream</strong> en <em>Parrilla → Configuración</em>. Ejemplo: <code>https://tu-servidor.com/public/tu_emisora</code>
        </div>
    </div>

    <!-- Sección: Personalización -->
    <div id="personalizacion" style="margin-bottom: 40px;">
        <h3>🎨 Personalización de programas</h3>
        <p>Cada programa puede tener información personalizada que se muestra en la parrilla:</p>
        <ul>
            <li><strong>Título personalizado:</strong> Diferente al nombre de la playlist en AzuraCast</li>
            <li><strong>Imagen:</strong> Logo o portada del programa</li>
            <li><strong>Descripción:</strong> Texto explicativo del contenido</li>
            <li><strong>Presentadores:</strong> Nombres de los conductores</li>
            <li><strong>Redes sociales:</strong> Links a Twitter e Instagram</li>
        </ul>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>💡 Tip:</strong> Configura toda la información de tus programas en SAPO para que la parrilla se vea completa y profesional, incluso si los nombres en AzuraCast son técnicos o codificados.
        </div>
    </div>

    <!-- Sección: Responsive -->
    <div id="responsive" style="margin-bottom: 40px;">
        <h3>📱 Diseño responsive</h3>
        <p>La parrilla se adapta automáticamente a todos los dispositivos:</p>
        <ul>
            <li>💻 Desktop: Vista completa con todas las columnas</li>
            <li>📱 Tablet: Layout adaptado para pantalla media</li>
            <li>📲 Móvil: Diseño vertical optimizado para táctil</li>
        </ul>
    </div>

    <!-- Pie de página -->
    <div style="border-top: 2px solid #e2e8f0; padding-top: 20px; margin-top: 40px; text-align: center; color: #718096;">
        <p><strong>🐸 SAPO</strong> - Sistema de Automatización de Podcasts para AzuraCast</p>
        <p style="font-size: 14px;">¿Necesitas más ayuda? Contacta al administrador del sistema.</p>
    </div>
</div>

<style>
/* Estilos específicos para la página de ayuda */
.card h3 {
    color: #667eea;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 10px;
    margin-top: 30px;
}

.card h4 {
    color: #4a5568;
    margin-top: 20px;
    margin-bottom: 10px;
}

.card code {
    background: #edf2f7;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
}

.card a {
    color: #667eea;
    text-decoration: none;
}

.card a:hover {
    text-decoration: underline;
}

.card ul, .card ol {
    line-height: 1.8;
}

/* Botón flotante para volver al índice */
#btn-volver-indice {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 50px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    transition: all 0.3s;
    z-index: 1000;
    display: none;
}

#btn-volver-indice:hover {
    background: #5568d3;
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
    transform: translateY(-2px);
}

#btn-volver-indice.visible {
    display: block;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    #btn-volver-indice {
        bottom: 20px;
        right: 20px;
        padding: 10px 20px;
        font-size: 13px;
    }
}
</style>

<!-- Botón flotante para volver al índice -->
<a href="#indice" id="btn-volver-indice" title="Volver al índice">
    ⬆️ Volver al índice
</a>

<script>
// Mostrar/ocultar botón según scroll
window.addEventListener('scroll', function() {
    const btn = document.getElementById('btn-volver-indice');
    const indice = document.getElementById('indice');

    if (!btn || !indice) return;

    const indiceBottom = indice.offsetTop + indice.offsetHeight;
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;

    // Mostrar botón solo si hemos pasado el índice
    if (scrollPosition > indiceBottom) {
        btn.classList.add('visible');
    } else {
        btn.classList.remove('visible');
    }
});

// Scroll suave al hacer clic
document.getElementById('btn-volver-indice')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('indice')?.scrollIntoView({ behavior: 'smooth' });
});
</script>
