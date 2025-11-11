<?php
// help.php - Guía de uso de SAPO
session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayuda - SAPO Sistema de Automatización de Podcasts</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .accordion {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .accordion-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .accordion-header:hover {
            opacity: 0.9;
        }
        .accordion-content {
            padding: 20px;
            display: none;
            background: white;
            color: #2d3748;
            line-height: 1.8;
        }
        .accordion.active .accordion-content {
            display: block;
        }
        .accordion.active .accordion-icon {
            transform: rotate(180deg);
        }
        .accordion-icon {
            transition: transform 0.3s;
        }
        .step-box {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
        .tip-box {
            background: #fef5e7;
            border-left: 4px solid #f39c12;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fee;
            border-left: 4px solid #e53e3e;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🐸 SAPO</h1>
        <div class="subtitle">Sistema de Automatización de Podcasts para Radiobot</div>
    </div>

    <!-- Menú de navegación -->
    <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 30px; text-align: center; border: 1px solid #e2e8f0;">
        <a href="index.php" style="margin: 0 15px; color: #3182ce; text-decoration: none; font-weight: 500;">🏠 Inicio</a>
        <a href="help.php" style="margin: 0 15px; color: #667eea; text-decoration: none; font-weight: 600;">❓ Ayuda</a>
        <a href="about.php" style="margin: 0 15px; color: #3182ce; text-decoration: none; font-weight: 500;">📖 Acerca de</a>
    </div>

    <div class="card">
        <h2 style="color: #2d3748; margin-bottom: 30px;">❓ Guía de Uso de SAPO</h2>

        <!-- Índice rápido -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="margin: 0 0 15px 0;">📋 Índice de Contenidos</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 14px;">
                <a href="#inicio-rapido" style="color: white; opacity: 0.9;">→ Inicio Rápido</a>
                <a href="#agregar-podcasts" style="color: white; opacity: 0.9;">→ Agregar Podcasts</a>
                <a href="#categorias" style="color: white; opacity: 0.9;">→ Gestionar Categorías</a>
                <a href="#editar" style="color: white; opacity: 0.9;">→ Editar/Eliminar</a>
                <a href="#descargas" style="color: white; opacity: 0.9;">→ Ejecutar Descargas</a>
                <a href="#importar" style="color: white; opacity: 0.9;">→ Importar/Exportar</a>
                <a href="#problemas" style="color: white; opacity: 0.9;">→ Solución de Problemas</a>
                <a href="#faq" style="color: white; opacity: 0.9;">→ Preguntas Frecuentes</a>
            </div>
        </div>

        <!-- INICIO RÁPIDO -->
        <div id="inicio-rapido" class="section">
            <h3 style="color: #667eea;">🚀 Inicio Rápido (5 minutos)</h3>

            <div class="step-box">
                <span class="step-number">1</span>
                <strong>Inicia sesión</strong> con tus credenciales proporcionadas por el administrador
            </div>

            <div class="step-box">
                <span class="step-number">2</span>
                <strong>Crea una categoría</strong> para organizar tus podcasts (ej: "Noticias", "Deportes", "Música")
            </div>

            <div class="step-box">
                <span class="step-number">3</span>
                <strong>Agrega tu primer podcast</strong> pegando la URL del feed RSS
            </div>

            <div class="step-box">
                <span class="step-number">4</span>
                <strong>Ejecuta las descargas</strong> desde la pestaña "Descargas" para obtener los episodios
            </div>

            <div class="success-box">
                <strong>✅ ¡Listo!</strong> SAPO se encargará de gestionar automáticamente tu archivo serverlist.txt
            </div>
        </div>

        <!-- AGREGAR PODCASTS -->
        <div id="agregar-podcasts" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">➕ Cómo Agregar un Nuevo Podcast</h3>

            <p style="color: #4a5568; line-height: 1.8;">
                Agregar podcasts es muy sencillo. Solo necesitas la URL del feed RSS del podcast que quieres descargar.
            </p>

            <div class="step-box">
                <strong>Paso 1: Encuentra la URL del RSS</strong><br>
                La mayoría de podcasts publican su URL RSS en su página web. Busca enlaces como "RSS Feed", "Subscribe", o "Suscribirse".
            </div>

            <div class="tip-box">
                <strong>💡 Ejemplos de URLs RSS válidas:</strong><br>
                • <code>https://feeds.feedburner.com/nombre-podcast</code><br>
                • <code>https://anchor.fm/s/12345678/podcast/rss</code><br>
                • <code>https://www.ivoox.com/podcast-ejemplo_fg_f1234567_filtro_1.xml</code><br>
                • <code>https://archive.org/download/nombre-podcast/rss</code>
            </div>

            <div class="step-box">
                <strong>Paso 2: Haz clic en "Agregar Nuevo Podcast"</strong><br>
                Encontrarás este botón verde en la pestaña "Mis Podcasts".
            </div>

            <div class="step-box">
                <strong>Paso 3: Rellena el formulario</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>URL del RSS:</strong> Pega la URL completa del feed</li>
                    <li><strong>Categoría:</strong> Selecciona en qué categoría quieres organizarlo</li>
                    <li><strong>Nombre del Podcast:</strong> Dale un nombre descriptivo (puedes usar espacios)</li>
                    <li><strong>Días de caducidad:</strong> Cuántos días mantener episodios antiguos (por defecto: 30)</li>
                </ul>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante:</strong> Asegúrate de que la URL del RSS sea válida y accesible. SAPO mostrará un error si no puede acceder al feed.
            </div>
        </div>

        <!-- GESTIONAR CATEGORÍAS -->
        <div id="categorias" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">📁 Gestionar Categorías</h3>

            <p style="color: #4a5568; line-height: 1.8;">
                Las categorías te ayudan a organizar tus podcasts. Son especialmente útiles cuando gestionas muchos feeds.
            </p>

            <div class="step-box">
                <strong>Crear una nueva categoría:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Haz clic en el botón "Gestionar" junto al selector de categorías</li>
                    <li>Escribe el nombre de la nueva categoría (ej: "Deportes", "Tecnología")</li>
                    <li>Haz clic en "Añadir"</li>
                </ol>
            </div>

            <div class="step-box">
                <strong>Eliminar una categoría:</strong><br>
                Solo puedes eliminar categorías que no estén en uso. Si intentas eliminar una categoría con podcasts asignados, primero deberás reasignar esos podcasts a otra categoría.
            </div>

            <div class="tip-box">
                <strong>💡 Sugerencias de categorías:</strong><br>
                • Noticias • Deportes • Música • Tecnología • Cultura • Entretenimiento • Educación • Historia • Ciencia
            </div>

            <div class="step-box">
                <strong>Filtrar y agrupar por categorías:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Usa el selector "Filtrar por" para ver solo podcasts de una categoría</li>
                    <li>Haz clic en "Agrupar por categoría" para organizar la vista por categorías</li>
                </ul>
            </div>
        </div>

        <!-- EDITAR Y ELIMINAR -->
        <div id="editar" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">✏️ Editar y Eliminar Podcasts</h3>

            <div class="step-box">
                <strong>Para editar un podcast:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Haz clic en el botón "✏️ Editar" junto al podcast que quieres modificar</li>
                    <li>Modifica los campos que necesites (URL, categoría, nombre, caducidad)</li>
                    <li>Haz clic en "💾 Guardar Cambios"</li>
                </ol>
            </div>

            <div class="step-box">
                <strong>Para eliminar un podcast:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Haz clic en el botón "🗑️ Eliminar" junto al podcast</li>
                    <li>Confirma la eliminación en el diálogo</li>
                </ol>
            </div>

            <div class="warning-box">
                <strong>⚠️ Atención:</strong> Eliminar un podcast de SAPO solo lo quita de tu serverlist.txt. Los archivos ya descargados en el servidor permanecerán hasta que los elimines manualmente o expiren por caducidad.
            </div>
        </div>

        <!-- EJECUTAR DESCARGAS -->
        <div id="descargas" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">🚀 Ejecutar Descargas con Podget</h3>

            <p style="color: #4a5568; line-height: 1.8;">
                La pestaña "Descargas" te permite ejecutar Podget para descargar los nuevos episodios de todos tus podcasts suscritos.
            </p>

            <div class="step-box">
                <strong>Ejecutar descargas:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Ve a la pestaña "Descargas"</li>
                    <li>Haz clic en el botón "🚀 Ejecutar descargas"</li>
                    <li>Espera a que el proceso termine (puede tardar varios minutos)</li>
                    <li>Verás un resumen de los episodios descargados</li>
                </ol>
            </div>

            <div class="tip-box">
                <strong>💡 ¿Cuándo ejecutar descargas?</strong><br>
                • Después de agregar nuevos podcasts<br>
                • Una o dos veces al día para mantener tu biblioteca actualizada<br>
                • Antes de programar episodios en Radiobot
            </div>

            <div class="step-box">
                <strong>Ver episodios descargados:</strong><br>
                En la sección "Últimos Episodios Descargados" verás un listado de los episodios descargados en los últimos 7 días, con fecha, hora, podcast y nombre del archivo.
            </div>

            <div class="success-box">
                <strong>✅ Automatización:</strong> Puedes configurar un cron job en el servidor para ejecutar Podget automáticamente cada día sin necesidad de usar la interfaz web.
            </div>
        </div>

        <!-- IMPORTAR/EXPORTAR -->
        <div id="importar" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">📤 Importar y Exportar Listas</h3>

            <div class="step-box">
                <strong>Importar desde serverlist.txt existente:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Ve a la pestaña "Importar/Exportar"</li>
                    <li>Haz clic en "Seleccionar archivo" y elige tu archivo serverlist.txt</li>
                    <li>Haz clic en "📥 Importar"</li>
                </ol>
            </div>

            <div class="tip-box">
                <strong>💡 Formato del archivo:</strong><br>
                Cada línea debe tener el formato:<br>
                <code>URL categoria nombre_podcast caducidad</code><br><br>
                Ejemplo:<br>
                <code>https://ejemplo.com/feed.rss Noticias El_Diario_Hoy 30</code>
            </div>

            <div class="step-box">
                <strong>Exportar tu lista actual:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Ve a la pestaña "Importar/Exportar"</li>
                    <li>Haz clic en "📤 Descargar mi serverlist.txt"</li>
                    <li>El archivo se descargará en tu ordenador</li>
                </ol>
            </div>

            <div class="success-box">
                <strong>✅ Backup recomendado:</strong> Exporta tu lista periódicamente como respaldo. Así podrás restaurarla fácilmente en caso de necesidad.
            </div>
        </div>

        <!-- ESTADO DE FEEDS -->
        <div class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">📊 Entender el Estado de los Feeds</h3>

            <p style="color: #4a5568; line-height: 1.8;">
                SAPO monitorea automáticamente la actividad de cada podcast y te muestra su estado:
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
                <div style="background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; border-radius: 4px;">
                    <strong style="color: #22543d;">🟢 Activo (≤30 días)</strong><br>
                    <span style="color: #718096; font-size: 14px;">El podcast ha publicado episodios recientemente. Todo está bien.</span>
                </div>
                <div style="background: #fef5e7; border-left: 4px solid #f39c12; padding: 15px; border-radius: 4px;">
                    <strong style="color: #856404;">🟠 Poco activo (31-90 días)</strong><br>
                    <span style="color: #718096; font-size: 14px;">El último episodio tiene más de un mes. El podcast puede estar en pausa.</span>
                </div>
                <div style="background: #fee; border-left: 4px solid #e53e3e; padding: 15px; border-radius: 4px;">
                    <strong style="color: #742a2a;">🔴 Inactivo (>90 días)</strong><br>
                    <span style="color: #718096; font-size: 14px;">No hay episodios recientes. El podcast puede estar cancelado.</span>
                </div>
            </div>

            <div class="tip-box" style="margin-top: 20px;">
                <strong>💡 Actualizar estado:</strong> Haz clic en "🔄 Actualizar estado de feeds" para verificar la última actividad de todos tus podcasts.
            </div>
        </div>

        <!-- SOLUCIÓN DE PROBLEMAS -->
        <div id="problemas" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">🔧 Solución de Problemas Comunes</h3>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>❌ Error: No se puede acceder a la URL del RSS</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <strong>Posibles causas:</strong>
                    <ul>
                        <li>La URL es incorrecta o ha cambiado</li>
                        <li>El servidor del podcast está caído temporalmente</li>
                        <li>El feed requiere autenticación</li>
                    </ul>
                    <strong>Soluciones:</strong>
                    <ul>
                        <li>Verifica que la URL sea correcta copiándola y pegándola en tu navegador</li>
                        <li>Busca en el sitio web del podcast si la URL del RSS ha cambiado</li>
                        <li>Intenta de nuevo más tarde si el servidor está temporalmente caído</li>
                    </ul>
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>⚠️ Los episodios no se descargan</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <strong>Verifica lo siguiente:</strong>
                    <ul>
                        <li>¿Has ejecutado las descargas desde la pestaña "Descargas"?</li>
                        <li>¿El feed está marcado como "Activo"?</li>
                        <li>¿Hay espacio suficiente en el servidor?</li>
                        <li>¿Podget está correctamente instalado y configurado?</li>
                    </ul>
                    <strong>Solución:</strong>
                    <ul>
                        <li>Ejecuta las descargas manualmente desde la interfaz</li>
                        <li>Verifica los logs de Podget en el servidor</li>
                        <li>Contacta al administrador si el problema persiste</li>
                    </ul>
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>🔄 Los cambios no se reflejan en Radiobot</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <strong>Importante:</strong> SAPO gestiona el archivo serverlist.txt, pero Podget es quien descarga los episodios.
                    <br><br>
                    <strong>Pasos a seguir:</strong>
                    <ol>
                        <li>Verifica que SAPO haya actualizado correctamente el serverlist.txt</li>
                        <li>Ejecuta las descargas con Podget</li>
                        <li>Espera a que los episodios se descarguen completamente</li>
                        <li>Los archivos aparecerán automáticamente en Radiobot</li>
                    </ol>
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>📁 No puedo crear categorías</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <strong>Solución:</strong>
                    <ul>
                        <li>Asegúrate de que el nombre de la categoría no esté vacío</li>
                        <li>Evita usar caracteres especiales en el nombre</li>
                        <li>Verifica que no exista ya una categoría con ese nombre</li>
                        <li>Si el problema persiste, limpia la caché de tu navegador</li>
                    </ul>
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>⏰ ¿Cómo funciona la caducidad?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <strong>Explicación:</strong>
                    <p>La caducidad define cuántos días mantener un episodio en el servidor después de descargarlo.</p>
                    <ul>
                        <li><strong>30 días:</strong> Recomendado para podcasts diarios o muy frecuentes</li>
                        <li><strong>60-90 días:</strong> Para podcasts semanales</li>
                        <li><strong>180+ días:</strong> Para contenido que quieres mantener mucho tiempo</li>
                    </ul>
                    <p><strong>Importante:</strong> Podget elimina automáticamente los archivos según esta configuración para ahorrar espacio en disco.</p>
                </div>
            </div>
        </div>

        <!-- PREGUNTAS FRECUENTES -->
        <div id="faq" class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">❔ Preguntas Frecuentes (FAQ)</h3>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Puedo gestionar múltiples emisoras con una sola cuenta?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    No. Cada emisora debe tener su propia cuenta de usuario. El administrador puede crear múltiples cuentas desde el panel de administración.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿SAPO descarga los episodios automáticamente?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    No directamente. SAPO gestiona tu archivo serverlist.txt. Las descargas las realiza <strong>Podget</strong>, que puedes ejecutar manualmente desde la interfaz o configurar con un cron job para que se ejecute automáticamente.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Puedo usar SAPO sin Radiobot?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    Sí. SAPO es una herramienta para gestionar archivos serverlist.txt de Podget. Puedes usarlo con cualquier sistema de automatización de radio o simplemente para organizar tus suscripciones de podcasts.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Dónde se almacenan los episodios descargados?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    Los episodios se descargan en el servidor según la configuración de Podget, normalmente en:<br>
                    <code>[Ruta Base]/[tu_usuario]/media/[categoria]/</code>
                    <br><br>
                    El administrador puede indicarte la ruta exacta.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Puedo agregar feeds de Archive.org, Ivoox, Spotify, etc.?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    Sí, siempre que tengan un feed RSS válido. La mayoría de plataformas de podcasting ofrecen URLs RSS públicas. Spotify no proporciona RSS directamente, pero muchos podcasts están también en otras plataformas que sí lo hacen.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Cuántos podcasts puedo agregar?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    No hay límite técnico en SAPO. El límite lo impone el espacio disponible en el servidor y la configuración de caducidad. Consulta con tu administrador sobre las políticas de uso.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Puedo compartir mi cuenta con otros usuarios?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    No es recomendable. Cada cuenta está vinculada a una emisora específica y tiene su propio serverlist.txt. Compartir cuentas puede causar conflictos y pérdida de datos.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Cómo cambio mi contraseña?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    Actualmente SAPO no tiene una función de cambio de contraseña desde la interfaz. Contacta al administrador del sistema para solicitar un cambio de contraseña.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿Qué pasa si elimino un podcast por error?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    Puedes volver a agregarlo sin problema. Los episodios ya descargados en el servidor permanecerán intactos. Solo se eliminará la entrada del serverlist.txt, no los archivos físicos.
                </div>
            </div>

            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span>¿SAPO funciona en móviles?</span>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    Sí. La interfaz de SAPO es responsive y se adapta a pantallas de móviles y tablets. Puedes gestionar tus podcasts desde cualquier dispositivo con navegador web.
                </div>
            </div>
        </div>

        <!-- Recursos adicionales -->
        <div class="section" style="margin-top: 40px;">
            <h3 style="color: #667eea;">📚 Recursos Adicionales</h3>
            <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 15px;">
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                        <a href="about.php" style="color: #3182ce; text-decoration: none;">
                            📖 Acerca de SAPO - Información del proyecto
                        </a>
                    </li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                        <a href="https://github.com/avillalba/Podget" target="_blank" style="color: #3182ce; text-decoration: none;">
                            📦 Documentación de Podget
                        </a>
                    </li>
                    <li style="padding: 10px 0;">
                        <a href="https://www.radiobot.org/" target="_blank" style="color: #3182ce; text-decoration: none;">
                            📻 Sitio web de Radiobot
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Volver al inicio -->
        <div style="text-align: center; margin-top: 40px; padding-top: 30px; border-top: 2px solid #e2e8f0;">
            <a href="index.php" class="btn btn-primary" style="text-decoration: none; display: inline-block;">
                🏠 Volver al Inicio
            </a>
        </div>
    </div>
</div>

<script>
function toggleAccordion(header) {
    const accordion = header.parentElement;
    const isActive = accordion.classList.contains('active');

    // Cerrar todos los acordeones
    document.querySelectorAll('.accordion').forEach(acc => {
        acc.classList.remove('active');
    });

    // Abrir el clickeado si no estaba activo
    if (!isActive) {
        accordion.classList.add('active');
    }
}
</script>

</body>
</html>
