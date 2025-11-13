<?php
// views/help.php - Página de ayuda y documentación
?>

<div class="card">
    <div class="nav-buttons" style="margin-bottom: 30px;">
        <h2>📖 Ayuda - Cómo usar SAPO</h2>
        <div style="text-align: right;">
            <?php if (isLoggedIn()): ?>
                <p style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px;">Conectado como <strong><?php echo htmlEsc($_SESSION['station_name']); ?></strong></p>
            <?php endif; ?>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                <span class="btn-icon">⬅️</span> Volver
            </a>
        </div>
    </div>

    <!-- Índice de contenidos -->
    <div style="background: #f7fafc; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 30px;">
        <h3 style="margin-top: 0;">📑 Contenido</h3>
        <ul style="line-height: 1.8;">
            <li><a href="#introduccion">¿Qué es SAPO?</a></li>
            <li><a href="#como-funciona">¿Cómo funciona con Radiobot?</a></li>
            <li><a href="#primeros-pasos">Primeros pasos</a></li>
            <li><a href="#gestionar-podcasts">Gestionar podcasts</a></li>
            <li><a href="#categorias">Organizar con categorías</a></li>
            <li><a href="#descargas">Ejecutar descargas</a></li>
            <li><a href="#estado-feeds">Entender el estado de los feeds</a></li>
            <li><a href="#informes">Ver informes de descargas</a></li>
            <li><a href="#faq">Preguntas frecuentes (FAQ)</a></li>
        </ul>
    </div>

    <!-- Sección: Introducción -->
    <div id="introduccion" style="margin-bottom: 40px;">
        <h3>🐸 ¿Qué es SAPO?</h3>
        <p>SAPO (Sistema de Automatización de Podcasts) es una aplicación web que facilita la gestión de suscripciones de podcasts para <strong>Radiobot</strong>.</p>

        <div style="background: #edf2f7; padding: 15px; border-radius: 8px; margin: 15px 0;">
            <strong>🎯 Objetivo:</strong> Simplificar la administración de tus podcasts mediante una interfaz web intuitiva, sin necesidad de editar archivos de configuración manualmente.
        </div>

        <p><strong>Funcionalidades principales:</strong></p>
        <ul>
            <li>✅ Agregar, editar y eliminar podcasts con unos pocos clics</li>
            <li>✅ Organizar podcasts por categorías personalizadas</li>
            <li>✅ Ver el estado de actividad de cada feed RSS</li>
            <li>✅ Configurar días de caducidad individuales por podcast</li>
            <li>✅ Ejecutar descargas de episodios nuevos</li>
            <li>✅ Ver informes detallados de descargas</li>
        </ul>
    </div>

    <!-- Sección: Cómo funciona -->
    <div id="como-funciona" style="margin-bottom: 40px;">
        <h3>⚙️ ¿Cómo funciona con Radiobot?</h3>

        <h4>Flujo de trabajo</h4>
        <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <ol style="line-height: 2;">
                <li>📝 <strong>Configuras tus podcasts en SAPO</strong> - Defines qué podcasts quieres, en qué categorías y por cuántos días</li>
                <li>🔄 <strong>SAPO sincroniza con Radiobot</strong> - Tu configuración se comunica automáticamente con el sistema de descargas</li>
                <li>⬇️ <strong>Radiobot descarga episodios</strong> - El sistema consulta los feeds RSS y descarga nuevos episodios</li>
                <li>📁 <strong>Organización automática</strong> - Los episodios se guardan en las carpetas de cada categoría</li>
                <li>🗑️ <strong>Limpieza automática</strong> - Los episodios antiguos se eliminan según la caducidad configurada</li>
            </ol>
        </div>

        <h4>Estructura de archivos en Radiobot</h4>
        <p>SAPO organiza tus descargas en la siguiente estructura:</p>
        <pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto;">
/tu-emisora/
└── media/
    └── Suscripciones/
        ├── Categoria1/
        │   ├── Podcast_A/
        │   │   ├── episodio1.mp3
        │   │   └── episodio2.mp3
        │   └── Podcast_B/
        │       └── episodio1.mp3
        └── Categoria2/
            └── Podcast_C/
                └── episodio1.mp3</pre>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>✨ Ventaja:</strong> No necesitas preocuparte por la estructura de archivos. SAPO y Radiobot se encargan automáticamente de mantener todo organizado.
        </div>
    </div>

    <!-- Sección: Primeros pasos -->
    <div id="primeros-pasos" style="margin-bottom: 40px;">
        <h3>🚀 Primeros pasos</h3>

        <h4>1. Iniciar sesión</h4>
        <p>Si eres usuario de una emisora, inicia sesión con las credenciales que te proporcionó el administrador.</p>
        <p>Si eres administrador, puedes crear usuarios desde el panel de administración.</p>

        <h4>2. Panel principal</h4>
        <p>Una vez dentro verás tres pestañas:</p>
        <ul>
            <li><strong>📻 Podcasts:</strong> Lista y gestión de tus podcasts suscritos</li>
            <li><strong>⬇️ Descargas:</strong> Ejecutar el proceso de descarga de nuevos episodios</li>
            <li><strong>📊 Informes:</strong> Estadísticas de descargas realizadas</li>
        </ul>
    </div>

    <!-- Sección: Gestionar podcasts -->
    <div id="gestionar-podcasts" style="margin-bottom: 40px;">
        <h3>🎙️ Gestionar podcasts</h3>

        <h4>➕ Agregar un podcast</h4>
        <ol>
            <li>Haz clic en <strong>"+ Agregar Podcast"</strong></li>
            <li>Completa el formulario:
                <ul>
                    <li><strong>URL del RSS:</strong> Dirección del feed (ejemplo: https://feeds.feedburner.com/mi-podcast)</li>
                    <li><strong>Categoría:</strong> Selecciona una existente o crea una nueva</li>
                    <li><strong>Nombre del podcast:</strong> Nombre descriptivo (será el nombre de la carpeta)</li>
                    <li><strong>Caducidad:</strong> Días a conservar episodios (1-365 días, por defecto: 30)</li>
                </ul>
            </li>
            <li>Haz clic en <strong>"Agregar Podcast"</strong></li>
        </ol>

        <div style="background: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 15px 0;">
            <strong>⚠️ Importante:</strong> El nombre del podcast se normaliza automáticamente. Los espacios se convierten en guiones bajos y se eliminan caracteres especiales.<br>
            Ejemplo: "Mi Podcast Ñoño" → "Mi_Podcast_Nono"
        </div>

        <h4>✏️ Editar un podcast</h4>
        <ol>
            <li>Encuentra el podcast en el listado</li>
            <li>Haz clic en <strong>"✏️ Editar"</strong></li>
            <li>Modifica los campos necesarios</li>
            <li>Haz clic en <strong>"Guardar Cambios"</strong></li>
        </ol>

        <h4>🗑️ Eliminar un podcast</h4>
        <ol>
            <li>Encuentra el podcast en el listado</li>
            <li>Haz clic en <strong>"🗑️ Eliminar"</strong></li>
            <li>Confirma la acción</li>
        </ol>

        <div style="background: #fffaf0; border-left: 4px solid #f6ad55; padding: 15px; margin: 15px 0;">
            <strong>💡 Nota:</strong> Al eliminar un podcast de SAPO, los episodios ya descargados permanecen en el servidor hasta la próxima limpieza automática.
        </div>
    </div>

    <!-- Sección: Categorías -->
    <div id="categorias" style="margin-bottom: 40px;">
        <h3>📁 Organizar con categorías</h3>
        <p>Las categorías son carpetas que agrupan podcasts por temática (Noticias, Música, Deportes, etc.).</p>

        <h4>Crear una categoría</h4>
        <ol>
            <li>Haz clic en <strong>"Gestionar Categorías"</strong></li>
            <li>Escribe el nombre de la nueva categoría</li>
            <li>Haz clic en <strong>"Agregar Categoría"</strong></li>
        </ol>

        <h4>Eliminar una categoría</h4>
        <p>Solo puedes eliminar categorías vacías (sin podcasts asignados).</p>

        <h4>Vistas de organización</h4>
        <ul>
            <li><strong>Vista alfabética:</strong> Todos los podcasts ordenados A-Z</li>
            <li><strong>Vista agrupada:</strong> Podcasts organizados por categoría</li>
        </ul>
        <p>Alterna entre vistas con el botón <strong>"Agrupar por categoría"</strong>.</p>

        <h4>Filtrar por categoría</h4>
        <p>Usa el selector en la parte superior para mostrar solo podcasts de una categoría específica.</p>
    </div>

    <!-- Sección: Descargas -->
    <div id="descargas" style="margin-bottom: 40px;">
        <h3>⬇️ Ejecutar descargas</h3>

        <h4>¿Cómo funciona el proceso?</h4>
        <p>Cuando ejecutas las descargas desde SAPO:</p>
        <ol>
            <li>🔍 <strong>Radiobot consulta los feeds RSS</strong> de todos tus podcasts</li>
            <li>🆕 <strong>Identifica episodios nuevos</strong> que aún no has descargado</li>
            <li>⬇️ <strong>Descarga los archivos de audio</strong> en las carpetas correspondientes</li>
            <li>📂 <strong>Organiza por categoría y podcast</strong> automáticamente</li>
            <li>🗑️ <strong>Elimina episodios antiguos</strong> según la caducidad configurada</li>
        </ol>

        <h4>Ejecutar descargas</h4>
        <ol>
            <li>Ve a la pestaña <strong>"Descargas"</strong></li>
            <li>Haz clic en <strong>"▶️ Ejecutar Descargas"</strong></li>
            <li>Confirma la acción</li>
            <li>El proceso se ejecuta en segundo plano</li>
        </ol>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>⏱️ Tiempo estimado:</strong> Los nuevos episodios estarán disponibles en Radiobot en aproximadamente 5-10 minutos, dependiendo del número de podcasts y el tamaño de los archivos.
        </div>

        <h4>Configuración de caducidad</h4>
        <p>La caducidad determina cuántos días se conservan los episodios:</p>
        <ul>
            <li><strong>7 días:</strong> Ideal para noticias diarias o contenido muy frecuente</li>
            <li><strong>30 días (predeterminado):</strong> Recomendado para la mayoría de podcasts</li>
            <li><strong>90+ días:</strong> Para podcasts poco frecuentes o contenido atemporal</li>
        </ul>

        <div style="background: #fffaf0; border-left: 4px solid #f6ad55; padding: 15px; margin: 15px 0;">
            <strong>💡 Consejo:</strong> Ajusta la caducidad según la frecuencia de publicación del podcast y el espacio disponible en tu servidor.
        </div>
    </div>

    <!-- Sección: Estado de feeds -->
    <div id="estado-feeds" style="margin-bottom: 40px;">
        <h3>📊 Entender el estado de los feeds</h3>
        <p>Cada podcast muestra un indicador de actividad basado en la fecha del último episodio publicado:</p>

        <div style="display: grid; grid-template-columns: auto 1fr; gap: 15px; margin: 20px 0;">
            <div style="background: #d4edda; padding: 10px 20px; border-radius: 6px; font-weight: bold;">🟢 Activo</div>
            <div style="padding: 10px 0;">Último episodio hace <strong>≤ 30 días</strong>. El podcast se actualiza regularmente.</div>

            <div style="background: #fff3cd; padding: 10px 20px; border-radius: 6px; font-weight: bold;">🟠 Poco activo</div>
            <div style="padding: 10px 0;">Último episodio hace <strong>31-90 días</strong>. Actualizaciones poco frecuentes.</div>

            <div style="background: #f8d7da; padding: 10px 20px; border-radius: 6px; font-weight: bold;">🔴 Inactivo</div>
            <div style="padding: 10px 0;">Último episodio hace <strong>más de 90 días</strong>. Posiblemente abandonado.</div>

            <div style="background: #e2e8f0; padding: 10px 20px; border-radius: 6px; font-weight: bold;">⚪ Desconocido</div>
            <div style="padding: 10px 0;">No se pudo obtener información. Puede ser un error temporal.</div>
        </div>

        <h4>Actualizar estado de feeds</h4>
        <p>Haz clic en <strong>"🔄 Actualizar Feeds"</strong> para refrescar la información de todos los podcasts. Esto consulta los feeds RSS actualizados y puede tardar unos segundos.</p>

        <div style="background: #e6fffa; border-left: 4px solid #38b2ac; padding: 15px; margin: 15px 0;">
            <strong>ℹ️ Información:</strong> El estado se almacena en caché para mejorar el rendimiento. Se actualiza automáticamente cada 12 horas o cuando haces clic en "Actualizar Feeds".
        </div>
    </div>

    <!-- Sección: Informes -->
    <div id="informes" style="margin-bottom: 40px;">
        <h3>📈 Ver informes de descargas</h3>
        <p>La pestaña <strong>"Informes"</strong> muestra estadísticas detalladas sobre las descargas realizadas.</p>

        <h4>Períodos disponibles</h4>
        <ul>
            <li><strong>7 días:</strong> Actividad de la última semana</li>
            <li><strong>30 días:</strong> Resumen mensual</li>
            <li><strong>90 días:</strong> Tendencias trimestrales</li>
        </ul>

        <h4>Información mostrada</h4>
        <ul>
            <li>📊 Total de episodios descargados en el período</li>
            <li>📊 Total de episodios eliminados por caducidad</li>
            <li>📊 Promedio de descargas por día</li>
            <li>📊 Promedio de eliminaciones por día</li>
            <li>📋 Detalle por podcast (nombre, descargas, eliminaciones)</li>
        </ul>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>📝 Nota:</strong> Los informes se generan automáticamente cada vez que ejecutas las descargas desde SAPO.
        </div>
    </div>

    <!-- Sección: FAQ -->
    <div id="faq" style="margin-bottom: 40px;">
        <h3>❓ Preguntas frecuentes (FAQ)</h3>

        <div style="margin-bottom: 20px;">
            <h4>¿Puedo tener podcasts sin categoría?</h4>
            <p>No, todos los podcasts deben tener una categoría asignada. Si no especificas una, se asignará automáticamente a "Sin_categoria".</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Qué pasa si cambio el nombre de un podcast?</h4>
            <p>El nombre se actualizará en SAPO, pero los archivos ya descargados permanecerán en la carpeta antigua. Radiobot empezará a descargar en la nueva carpeta con el nombre actualizado.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Cómo sé si un feed RSS es válido?</h4>
            <p>SAPO valida automáticamente el formato de la URL. Si el feed funciona, verás el estado actualizado después de hacer clic en "Actualizar Feeds" o después de la primera descarga.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Los cambios se aplican inmediatamente en Radiobot?</h4>
            <p>Sí, los cambios en SAPO se sincronizan inmediatamente. Radiobot aplicará los cambios en la siguiente ejecución de descargas.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Puedo cambiar la caducidad de múltiples podcasts a la vez?</h4>
            <p>Actualmente no hay función de edición masiva. Deberás editar cada podcast individualmente para cambiar su caducidad.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Qué hago si olvido mi contraseña?</h4>
            <p>Contacta al administrador del sistema para que restablezca tu contraseña.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿SAPO elimina archivos de audio?</h4>
            <p>No, SAPO solo gestiona la configuración. Radiobot es quien descarga y elimina archivos según las reglas de caducidad configuradas en SAPO.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Por qué algunos feeds muestran "Estado desconocido"?</h4>
            <p>Puede deberse a que el feed está temporalmente inaccesible, la URL es incorrecta, o hay problemas de conectividad. Intenta actualizar los feeds más tarde.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Cuánto espacio en disco necesito?</h4>
            <p>Depende del número de podcasts, frecuencia de publicación y configuración de caducidad. Un podcast diario con caducidad de 30 días puede ocupar entre 1-5 GB. Ajusta la caducidad según tu espacio disponible.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Puedo pausar un podcast temporalmente?</h4>
            <p>Actualmente no hay función de pausa. Si quieres dejar de recibir episodios temporalmente, elimina el podcast y agrégalo nuevamente cuando quieras reactivarlo.</p>
        </div>
    </div>

    <!-- Pie de página -->
    <div style="border-top: 2px solid #e2e8f0; padding-top: 20px; margin-top: 40px; text-align: center; color: #718096;">
        <p><strong>🐸 SAPO</strong> - Sistema de Automatización de Podcasts para Radiobot</p>
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
</style>
