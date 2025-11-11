<?php
// about.php - Acerca de SAPO
session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acerca de SAPO - Sistema de Automatización de Podcasts</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css">
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
        <a href="help.php" style="margin: 0 15px; color: #3182ce; text-decoration: none; font-weight: 500;">❓ Ayuda</a>
        <a href="about.php" style="margin: 0 15px; color: #667eea; text-decoration: none; font-weight: 600;">📖 Acerca de</a>
    </div>

    <div class="card">
        <h2 style="color: #2d3748; margin-bottom: 30px;">📖 Acerca de SAPO</h2>

        <!-- ¿Qué es SAPO? -->
        <div class="section">
            <h3 style="color: #667eea;">¿Qué es SAPO?</h3>
            <p style="color: #4a5568; line-height: 1.8; font-size: 15px;">
                <strong>SAPO</strong> (Sistema de Automatización de Podcasts) es una aplicación web
                diseñada para gestionar suscripciones de podcasts en múltiples emisoras de radio que
                utilizan <strong>Radiobot</strong> y <strong>Podget</strong>.
            </p>
            <p style="color: #4a5568; line-height: 1.8; font-size: 15px;">
                Permite a cada emisora mantener su propio archivo <code>serverlist.txt</code> de forma
                centralizada, facilitando la gestión de suscripciones a podcasts sin necesidad de acceder
                directamente a los servidores.
            </p>
        </div>

        <!-- Características principales -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">✨ Características Principales</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;">
                    <h4 style="margin: 0 0 10px 0; color: #2d3748;">🎙️ Gestión Multi-emisora</h4>
                    <p style="color: #718096; font-size: 14px; margin: 0;">
                        Cada emisora tiene su propio espacio con su lista de podcasts personalizada.
                    </p>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #48bb78;">
                    <h4 style="margin: 0 0 10px 0; color: #2d3748;">📁 Categorías Personalizadas</h4>
                    <p style="color: #718096; font-size: 14px; margin: 0;">
                        Organiza tus podcasts en categorías personalizadas para facilitar su gestión.
                    </p>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #ed8936;">
                    <h4 style="margin: 0 0 10px 0; color: #2d3748;">🔄 Importar/Exportar</h4>
                    <p style="color: #718096; font-size: 14px; margin: 0;">
                        Importa tu serverlist.txt existente o exporta tu lista para backup.
                    </p>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #3182ce;">
                    <h4 style="margin: 0 0 10px 0; color: #2d3748;">⏰ Caducidad Configurable</h4>
                    <p style="color: #718096; font-size: 14px; margin: 0;">
                        Define cuántos días mantener episodios sin descargas nuevas antes de eliminarlos.
                    </p>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #e53e3e;">
                    <h4 style="margin: 0 0 10px 0; color: #2d3748;">📊 Estado de Feeds</h4>
                    <p style="color: #718096; font-size: 14px; margin: 0;">
                        Monitorea la actividad de cada podcast: activo (≤30d), poco activo (31-90d), o inactivo (>90d).
                    </p>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #9f7aea;">
                    <h4 style="margin: 0 0 10px 0; color: #2d3748;">🚀 Ejecución de Descargas</h4>
                    <p style="color: #718096; font-size: 14px; margin: 0;">
                        Ejecuta Podget directamente desde la interfaz web para descargar nuevos episodios.
                    </p>
                </div>
            </div>
        </div>

        <!-- Tecnologías utilizadas -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">🛠️ Tecnologías Utilizadas</h3>
            <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 15px;">
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #2d3748;">
                        <strong>Backend:</strong> PHP 8+ con SQLite3
                    </li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #2d3748;">
                        <strong>Frontend:</strong> HTML5, CSS3, JavaScript vanilla
                    </li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; color: #2d3748;">
                        <strong>Integración:</strong> Radiobot + Podget
                    </li>
                    <li style="padding: 10px 0; color: #2d3748;">
                        <strong>Seguridad:</strong> Protección CSRF, validación de entrada, sesiones seguras
                    </li>
                </ul>
            </div>
        </div>

        <!-- Casos de uso -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">💼 Casos de Uso</h3>
            <div style="margin-top: 15px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="margin: 0 0 10px 0;">Emisoras de Radio Pequeñas</h4>
                    <p style="margin: 0; opacity: 0.9;">
                        Gestiona fácilmente los podcasts que quieres descargar y automatizar sin conocimientos técnicos.
                    </p>
                </div>
                <div style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="margin: 0 0 10px 0;">Grupos de Emisoras</h4>
                    <p style="margin: 0; opacity: 0.9;">
                        Centraliza la gestión de múltiples emisoras en una sola plataforma con cuentas separadas.
                    </p>
                </div>
                <div style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); color: white; padding: 20px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">Administradores de Sistema</h4>
                    <p style="margin: 0; opacity: 0.9;">
                        Permite a los usuarios gestionar sus propias suscripciones sin necesidad de acceso SSH.
                    </p>
                </div>
            </div>
        </div>

        <!-- Integración con Podget -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">🔗 Integración con Podget</h3>
            <p style="color: #4a5568; line-height: 1.8; font-size: 15px;">
                SAPO genera automáticamente archivos <code>serverlist.txt</code> compatibles con
                <strong>Podget</strong>, el popular gestor de descargas de podcasts para Linux.
            </p>
            <div style="background: #fef5e7; border-left: 4px solid #f39c12; padding: 15px; border-radius: 4px; margin-top: 15px;">
                <p style="margin: 0; color: #856404;">
                    <strong>Formato del serverlist.txt:</strong><br>
                    <code style="background: white; padding: 2px 6px; border-radius: 3px; display: inline-block; margin-top: 5px;">
                        https://ejemplo.com/podcast/rss categoria nombre_podcast caducidad
                    </code>
                </p>
            </div>
        </div>

        <!-- Estadísticas del sistema -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">📈 Estadísticas del Sistema</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php
                $db = getDB();

                // Contar usuarios (excluyendo admin)
                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
                $totalUsers = $stmt->fetchColumn();

                // Contar podcasts totales
                $totalPodcasts = 0;
                $stmtUsers = $db->query("SELECT username FROM users WHERE is_admin = 0");
                while ($user = $stmtUsers->fetch()) {
                    $podcasts = readServerList($user['username']);
                    $totalPodcasts += count($podcasts);
                }
                ?>
                <div style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; border-radius: 8px;">
                    <div style="font-size: 36px; font-weight: bold; margin-bottom: 5px;"><?php echo $totalUsers; ?></div>
                    <div style="font-size: 14px; opacity: 0.9;">Emisoras Registradas</div>
                </div>
                <div style="text-align: center; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 30px 20px; border-radius: 8px;">
                    <div style="font-size: 36px; font-weight: bold; margin-bottom: 5px;"><?php echo $totalPodcasts; ?></div>
                    <div style="font-size: 14px; opacity: 0.9;">Podcasts Gestionados</div>
                </div>
            </div>
        </div>

        <!-- Licencia -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">📜 Licencia</h3>
            <p style="color: #4a5568; line-height: 1.8; font-size: 15px;">
                SAPO es un proyecto de código abierto desarrollado para la comunidad de radio.
            </p>
        </div>

        <!-- Enlaces útiles -->
        <div class="section" style="margin-top: 30px;">
            <h3 style="color: #667eea;">🔗 Enlaces Útiles</h3>
            <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 15px;">
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                        <a href="https://github.com/avillalba/Podget" target="_blank" style="color: #3182ce; text-decoration: none;">
                            📦 Podget en GitHub
                        </a>
                    </li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                        <a href="https://www.radiobot.org/" target="_blank" style="color: #3182ce; text-decoration: none;">
                            📻 Radiobot - Sistema de Automatización de Radio
                        </a>
                    </li>
                    <li style="padding: 10px 0;">
                        <a href="help.php" style="color: #3182ce; text-decoration: none;">
                            ❓ Guía de Uso de SAPO
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

</body>
</html>
