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
            <li><a href="#primeros-pasos">Primeros pasos</a></li>
            <li><a href="#gestionar-podcasts">Gestionar podcasts</a></li>
            <li><a href="#categorias">Organizar con categorías</a></li>
            <li><a href="#descargas">Ejecutar descargas</a></li>
            <li><a href="#estado-feeds">Entender el estado de los feeds</a></li>
            <li><a href="#importar-exportar">Importar/Exportar serverlist</a></li>
            <li><a href="#informes">Ver informes de descargas</a></li>
            <li><a href="#faq">Preguntas frecuentes (FAQ)</a></li>
        </ul>
    </div>

    <!-- Sección: Introducción -->
    <div id="introduccion" style="margin-bottom: 40px;">
        <h3>🐸 ¿Qué es SAPO?</h3>
        <p>SAPO (Sistema de Automatización de Podcasts) es una aplicación web que te ayuda a gestionar las suscripciones de podcasts para <strong>Radiobot</strong>.</p>

        <div style="background: #edf2f7; padding: 15px; border-radius: 8px; margin: 15px 0;">
            <strong>🎯 Objetivo principal:</strong> Facilitar la administración del archivo <code>serverlist.txt</code> y del archivo <code>caducidades.txt</code> que utiliza el script de descargas de Radiobot.
        </div>

        <p><strong>Funcionalidades principales:</strong></p>
        <ul>
            <li>✅ Agregar, editar y eliminar podcasts de forma visual</li>
            <li>✅ Organizar podcasts por categorías personalizadas</li>
            <li>✅ Ver el estado de actividad de cada feed (última actualización)</li>
            <li>✅ Configurar días de caducidad por podcast</li>
            <li>✅ Ejecutar descargas con un clic</li>
            <li>✅ Ver informes de descargas por período</li>
            <li>✅ Importar/exportar archivos serverlist.txt</li>
        </ul>
    </div>

    <!-- Sección: Primeros pasos -->
    <div id="primeros-pasos" style="margin-bottom: 40px;">
        <h3>🚀 Primeros pasos</h3>

        <h4>1. Iniciar sesión</h4>
        <p>Si eres usuario de una emisora, inicia sesión con las credenciales que te proporcionó el administrador.</p>
        <p>Si eres administrador, puedes crear usuarios desde el panel de administración.</p>

        <h4>2. Panel principal</h4>
        <p>Una vez dentro verás:</p>
        <ul>
            <li><strong>Pestañas superiores:</strong> Podcasts, Descargas, Informes</li>
            <li><strong>Listado de podcasts:</strong> Todos tus podcasts suscritos</li>
            <li><strong>Botones de acción:</strong> Agregar podcast, gestionar categorías, filtros</li>
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
                    <li><strong>URL del RSS:</strong> La dirección del feed del podcast (ejemplo: https://feeds.feedburner.com/nombre-podcast)</li>
                    <li><strong>Categoría:</strong> Selecciona una categoría existente o crea una nueva</li>
                    <li><strong>Nombre del podcast:</strong> Un nombre descriptivo (será el nombre de la carpeta de descarga)</li>
                    <li><strong>Caducidad:</strong> Días que se conservarán los episodios (por defecto: 30 días)</li>
                </ul>
            </li>
            <li>Haz clic en <strong>"Agregar Podcast"</strong></li>
        </ol>

        <div style="background: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 15px 0;">
            <strong>⚠️ Importante:</strong> El nombre del podcast se "sanitiza" automáticamente (se eliminan espacios, acentos y caracteres especiales). Ejemplo: "Mi Podcast ñ" → "Mi_Podcast_n"
        </div>

        <h4>✏️ Editar un podcast</h4>
        <ol>
            <li>Encuentra el podcast en el listado</li>
            <li>Haz clic en el botón <strong>"✏️ Editar"</strong></li>
            <li>Modifica los campos necesarios</li>
            <li>Haz clic en <strong>"Guardar Cambios"</strong></li>
        </ol>

        <h4>🗑️ Eliminar un podcast</h4>
        <ol>
            <li>Encuentra el podcast en el listado</li>
            <li>Haz clic en el botón <strong>"🗑️ Eliminar"</strong></li>
            <li>Confirma la acción</li>
        </ol>

        <div style="background: #fffaf0; border-left: 4px solid #f6ad55; padding: 15px; margin: 15px 0;">
            <strong>💡 Tip:</strong> Al eliminar un podcast, se elimina de la lista de suscripciones pero NO se borran los archivos ya descargados en el servidor.
        </div>
    </div>

    <!-- Sección: Categorías -->
    <div id="categorias" style="margin-bottom: 40px;">
        <h3>📁 Organizar con categorías</h3>
        <p>Las categorías te ayudan a organizar tus podcasts por temática. Son las carpetas donde se descargarán los episodios.</p>

        <h4>Crear una categoría</h4>
        <ol>
            <li>Haz clic en <strong>"Gestionar Categorías"</strong></li>
            <li>Escribe el nombre de la nueva categoría</li>
            <li>Haz clic en <strong>"Agregar Categoría"</strong></li>
        </ol>

        <h4>Eliminar una categoría</h4>
        <p>Solo puedes eliminar categorías que no estén siendo usadas por ningún podcast.</p>
        <ol>
            <li>Haz clic en <strong>"Gestionar Categorías"</strong></li>
            <li>Haz clic en el botón <strong>"🗑️"</strong> junto a la categoría</li>
            <li>Confirma la eliminación</li>
        </ol>

        <h4>Vistas de organización</h4>
        <p>Puedes ver tus podcasts de dos formas:</p>
        <ul>
            <li><strong>Vista alfabética:</strong> Todos los podcasts ordenados A-Z</li>
            <li><strong>Vista por categoría:</strong> Podcasts agrupados por su categoría</li>
        </ul>
        <p>Usa el botón <strong>"Agrupar por categoría"</strong> para alternar entre vistas.</p>

        <h4>Filtrar por categoría</h4>
        <p>Usa el selector <strong>"Filtrar por categoría"</strong> en la parte superior para mostrar solo los podcasts de una categoría específica.</p>
    </div>

    <!-- Sección: Descargas -->
    <div id="descargas" style="margin-bottom: 40px;">
        <h3>⬇️ Ejecutar descargas</h3>

        <h4>¿Cómo funciona?</h4>
        <p>Cuando haces clic en <strong>"Ejecutar Descargas"</strong>, SAPO ejecuta el script de descargas de Radiobot que:</p>
        <ol>
            <li>Lee tu archivo <code>serverlist.txt</code> generado por SAPO</li>
            <li>Verifica cada feed RSS en busca de nuevos episodios</li>
            <li>Descarga los episodios nuevos en las carpetas correspondientes</li>
            <li>Aplica las reglas de caducidad configuradas</li>
        </ol>

        <h4>Ejecutar descargas</h4>
        <ol>
            <li>Ve a la pestaña <strong>"Descargas"</strong></li>
            <li>Haz clic en <strong>"▶️ Ejecutar Descargas"</strong></li>
            <li>Confirma la acción</li>
            <li>Espera el mensaje de confirmación</li>
        </ol>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>✅ Nota:</strong> El proceso se ejecuta en segundo plano. Los nuevos archivos estarán disponibles en Radiobot en aproximadamente 5-10 minutos.
        </div>

        <h4>Caducidad de episodios</h4>
        <p>La caducidad indica cuántos días se conservarán los episodios antes de ser eliminados automáticamente.</p>
        <ul>
            <li><strong>30 días (predeterminado):</strong> Valor estándar para la mayoría de podcasts</li>
            <li><strong>7 días:</strong> Para podcasts diarios o noticias</li>
            <li><strong>90 días:</strong> Para contenido evergreen o podcasts poco frecuentes</li>
            <li><strong>1-365 días:</strong> Puedes configurar cualquier valor en este rango</li>
        </ul>
    </div>

    <!-- Sección: Estado de feeds -->
    <div id="estado-feeds" style="margin-bottom: 40px;">
        <h3>📊 Entender el estado de los feeds</h3>
        <p>Cada podcast muestra un indicador de estado que refleja cuándo se publicó el último episodio:</p>

        <div style="display: grid; grid-template-columns: auto 1fr; gap: 15px; margin: 20px 0;">
            <div style="background: #d4edda; padding: 10px 20px; border-radius: 6px; font-weight: bold;">🟢 Activo</div>
            <div style="padding: 10px 0;">Último episodio publicado hace <strong>≤ 30 días</strong>. El podcast se actualiza regularmente.</div>

            <div style="background: #fff3cd; padding: 10px 20px; border-radius: 6px; font-weight: bold;">🟠 Poco activo</div>
            <div style="padding: 10px 0;">Último episodio publicado hace <strong>31-90 días</strong>. El podcast puede estar en pausa o actualiza con poca frecuencia.</div>

            <div style="background: #f8d7da; padding: 10px 20px; border-radius: 6px; font-weight: bold;">🔴 Inactivo</div>
            <div style="padding: 10px 0;">Último episodio publicado hace <strong>más de 90 días</strong>. El podcast posiblemente está abandonado.</div>

            <div style="background: #e2e8f0; padding: 10px 20px; border-radius: 6px; font-weight: bold;">⚪ Desconocido</div>
            <div style="padding: 10px 0;">No se pudo obtener información del feed. Puede ser un error temporal o URL inválida.</div>
        </div>

        <h4>Actualizar estado de feeds</h4>
        <p>Haz clic en <strong>"🔄 Actualizar Feeds"</strong> para refrescar la información de todos los podcasts (última fecha de publicación). Esta acción consulta los feeds RSS actualizados.</p>

        <div style="background: #fffaf0; border-left: 4px solid #f6ad55; padding: 15px; margin: 15px 0;">
            <strong>💡 Tip:</strong> El estado se calcula en tiempo real pero se almacena en caché para mejorar el rendimiento. Usa "Actualizar Feeds" si crees que la información está desactualizada.
        </div>
    </div>

    <!-- Sección: Importar/Exportar -->
    <div id="importar-exportar" style="margin-bottom: 40px;">
        <h3>📥📤 Importar/Exportar serverlist</h3>

        <h4>📤 Exportar serverlist.txt</h4>
        <p>Descarga tu archivo <code>serverlist.txt</code> actual:</p>
        <ol>
            <li>Ve a la pestaña <strong>"Podcasts"</strong></li>
            <li>Haz clic en <strong>"📥 Exportar"</strong></li>
            <li>Se descargará un archivo: <code>serverlist_tunombre_YYYY-MM-DD.txt</code></li>
        </ol>

        <p><strong>¿Para qué sirve?</strong></p>
        <ul>
            <li>Crear respaldos de tu configuración</li>
            <li>Migrar podcasts a otro servidor</li>
            <li>Compartir tu lista con otras emisoras</li>
            <li>Editar manualmente (usuarios avanzados)</li>
        </ul>

        <h4>📥 Importar serverlist.txt</h4>
        <p>Carga un archivo <code>serverlist.txt</code> existente:</p>
        <ol>
            <li>Ve a la pestaña <strong>"Podcasts"</strong></li>
            <li>Haz clic en <strong>"Importar Serverlist"</strong></li>
            <li>Selecciona tu archivo .txt</li>
            <li>Haz clic en <strong>"Importar"</strong></li>
        </ol>

        <div style="background: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 15px 0;">
            <strong>⚠️ Importante:</strong> La importación NO sobrescribe tus podcasts actuales. Solo agrega los podcasts que no existen en tu lista (compara por URL).
        </div>

        <h4>Formato del archivo serverlist.txt</h4>
        <p>El archivo tiene el siguiente formato (cada línea es un podcast):</p>
        <pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto;">URL_RSS Carpeta_Categoria Nombre_Podcast</pre>
        <p><strong>Ejemplo:</strong></p>
        <pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto;">https://feeds.example.com/podcast1 Noticias Podcast_Noticias_Diarias
https://anchor.fm/s/12345/podcast/rss Musica Entrevistas_Musicales
http://example.org/feed.xml Ciencia Podcast_Ciencia_Facil</pre>
    </div>

    <!-- Sección: Informes -->
    <div id="informes" style="margin-bottom: 40px;">
        <h3>📈 Ver informes de descargas</h3>
        <p>La pestaña <strong>"Informes"</strong> te muestra estadísticas sobre las descargas realizadas.</p>

        <h4>Períodos disponibles</h4>
        <ul>
            <li><strong>7 días:</strong> Última semana</li>
            <li><strong>30 días:</strong> Último mes</li>
            <li><strong>90 días:</strong> Últimos 3 meses</li>
        </ul>

        <h4>Información que muestra</h4>
        <ul>
            <li>📊 Total de episodios descargados</li>
            <li>📊 Total de episodios eliminados por caducidad</li>
            <li>📊 Promedio de descargas por día</li>
            <li>📊 Promedio de eliminaciones por día</li>
            <li>📋 Detalle por podcast (nombre, episodios descargados, eliminados)</li>
        </ul>

        <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 15px 0;">
            <strong>✅ Nota:</strong> Los informes se generan automáticamente cada vez que ejecutas las descargas.
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
            <p>Se actualizará en el <code>serverlist.txt</code>, pero los archivos ya descargados en la carpeta antigua NO se moverán automáticamente. Deberás moverlos manualmente si lo deseas.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Puedo usar SAPO sin Radiobot?</h4>
            <p>SAPO está diseñado específicamente para Radiobot, pero puedes usar el <code>serverlist.txt</code> generado con otras herramientas compatibles con este formato.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Cómo sé si un feed RSS es válido?</h4>
            <p>SAPO valida automáticamente el formato de la URL. Si el feed funciona, verás el estado actualizado después de hacer clic en "Actualizar Feeds".</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Los cambios se aplican inmediatamente en Radiobot?</h4>
            <p>Los cambios en SAPO actualizan el <code>serverlist.txt</code> inmediatamente, pero Radiobot aplicará los cambios la próxima vez que ejecutes las descargas.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Puedo cambiar la caducidad de todos mis podcasts a la vez?</h4>
            <p>Actualmente no hay una función para edición masiva. Deberás editar cada podcast individualmente o editar manualmente el archivo <code>caducidades.txt</code> en el servidor.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Qué hago si olvido mi contraseña?</h4>
            <p>Contacta al administrador del sistema para que restablezca tu contraseña.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿SAPO elimina archivos de audio?</h4>
            <p>No, SAPO no elimina archivos. El script de descargas de Radiobot es el que aplica las reglas de caducidad y elimina episodios antiguos.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>¿Por qué algunos feeds muestran "Estado desconocido"?</h4>
            <p>Puede deberse a:</p>
            <ul>
                <li>El feed está temporalmente inaccesible</li>
                <li>La URL es incorrecta</li>
                <li>El servidor del podcast está caído</li>
                <li>Hay problemas de conectividad desde el servidor</li>
            </ul>
            <p>Intenta actualizar los feeds más tarde. Si persiste, verifica la URL del feed.</p>
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
#help h3 {
    color: #667eea;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 10px;
    margin-top: 30px;
}

#help h4 {
    color: #4a5568;
    margin-top: 20px;
    margin-bottom: 10px;
}

#help code {
    background: #edf2f7;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
}

#help a {
    color: #667eea;
    text-decoration: none;
}

#help a:hover {
    text-decoration: underline;
}

#help ul, #help ol {
    line-height: 1.8;
}
</style>
