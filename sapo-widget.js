/**
 * SAPO Widget JavaScript
 * Widget para incrustar parrilla de programación sin iframe
 *
 * Uso:
 * <div id="sapo-widget" data-station="nombre-emisora"></div>
 * <script src="https://tu-dominio.com/sapo-widget.js"></script>
 */

(function() {
    'use strict';

    // Configuración
    const WIDGET_VERSION = '1.0.0';

    // Estilos del widget
    const WIDGET_STYLES = `
        /* Reset base dentro del Shadow DOM */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .sapo-widget {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }

        .sapo-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px 20px;
            border-radius: 12px;
            color: white;
        }

        .sapo-header h1 {
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
        }

        .sapo-header p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }

        .sapo-days {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .sapo-day {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .sapo-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sapo-day-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid;
        }

        .sapo-program {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 8px;
            background: #f9fafb;
            transition: background 0.2s;
        }

        .sapo-program:hover {
            background: #f3f4f6;
        }

        .sapo-program:last-child {
            margin-bottom: 0;
        }

        .sapo-program-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .sapo-program-type {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .sapo-program-type.live {
            background: #ef4444;
            animation: sapo-pulse 2s infinite;
        }

        .sapo-program-type.program {
            background: #3b82f6;
        }

        .sapo-program-type.music {
            background: #8b5cf6;
        }

        @keyframes sapo-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .sapo-program-time {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }

        .sapo-program-title {
            font-weight: 600;
            color: #111827;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .sapo-program-description {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.4;
            margin-top: 6px;
        }

        .sapo-program-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            margin-top: 8px;
            display: block;
        }

        .sapo-program-social {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .sapo-program-social a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e5e7eb;
            text-decoration: none;
            color: inherit;
            font-size: 14px;
            transition: all 0.2s;
        }

        .sapo-program-social a:hover {
            transform: scale(1.1);
            background: #d1d5db;
        }

        .sapo-empty {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
            font-style: italic;
        }

        .sapo-footer {
            text-align: center;
            padding: 20px;
            color: #9ca3af;
            font-size: 12px;
        }

        .sapo-loading {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .sapo-error {
            text-align: center;
            padding: 40px 20px;
            color: #dc2626;
            background: #fee2e2;
            border-radius: 8px;
            margin: 20px 0;
        }

        /* Estilos responsivos */
        @media (max-width: 768px) {
            .sapo-days {
                grid-template-columns: 1fr;
            }

            .sapo-header h1 {
                font-size: 24px;
            }

            .sapo-header p {
                font-size: 14px;
            }
        }

        /* Estilos compactos */
        .sapo-widget.compact .sapo-day {
            padding: 15px;
        }

        .sapo-widget.compact .sapo-program {
            padding: 10px;
            margin-bottom: 10px;
        }

        /* Estilos minimalistas */
        .sapo-widget.minimal .sapo-day {
            box-shadow: none;
            border: 1px solid #e5e7eb;
        }

        .sapo-widget.minimal .sapo-program {
            background: transparent;
            border-left: 3px solid #e5e7eb;
            border-radius: 0;
        }
    `;

    // Obtener URL base del script
    function getScriptBaseUrl() {
        const scripts = document.getElementsByTagName('script');
        for (let i = scripts.length - 1; i >= 0; i--) {
            const src = scripts[i].src;
            if (src && src.includes('sapo-widget.js')) {
                return src.substring(0, src.lastIndexOf('/'));
            }
        }
        return '';
    }

    // Renderizar widget
    async function renderWidget(container, station) {
        const baseUrl = getScriptBaseUrl();
        const apiUrl = `${baseUrl}/api_schedule.php?station=${encodeURIComponent(station)}`;

        // Crear Shadow DOM para aislamiento total del CSS del sitio padre
        const shadow = container.attachShadow({ mode: 'open' });

        const style = document.createElement('style');
        style.textContent = WIDGET_STYLES;
        shadow.appendChild(style);

        const root = document.createElement('div');
        shadow.appendChild(root);

        // Mostrar loading
        root.innerHTML = '<div class="sapo-loading">⏳ Cargando programación...</div>';

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error('Error al cargar la programación');
            }

            const data = await response.json();

            // Construir HTML
            let html = '<div class="sapo-widget' + (data.config.style === 'compact' ? ' compact' : '') + (data.config.style === 'minimal' ? ' minimal' : '') + '">';

            // Header
            html += `
                <div class="sapo-header" style="background-color: ${data.config.color}">
                    <h1>${escapeHtml(data.station.name)}</h1>
                    <p>${escapeHtml(data.station.subtitle)}</p>
                </div>
            `;

            // Días
            html += '<div class="sapo-days">';

            data.schedule.forEach(day => {
                html += `<div class="sapo-day">`;
                html += `<div class="sapo-day-name" style="border-bottom-color: ${data.config.color}">${day.name}</div>`;

                if (day.programs.length === 0) {
                    html += '<div class="sapo-empty">Sin programación</div>';
                } else {
                    day.programs.forEach(program => {
                        html += `<div class="sapo-program">`;
                        html += `<div class="sapo-program-header">`;
                        html += `<div class="sapo-program-type ${program.type}"></div>`;
                        html += `<div class="sapo-program-time">${program.start_time} - ${program.end_time}</div>`;
                        html += `</div>`;
                        html += `<div class="sapo-program-title">${escapeHtml(program.title)}</div>`;

                        if (program.description) {
                            html += `<div class="sapo-program-description">${escapeHtml(program.description)}</div>`;
                        }

                        if (program.image) {
                            html += `<img src="${escapeHtml(program.image)}" alt="${escapeHtml(program.title)}" class="sapo-program-image" loading="lazy">`;
                        }

                        // Redes sociales
                        if (program.social && (program.social.twitter || program.social.instagram || program.social.facebook)) {
                            html += '<div class="sapo-program-social">';

                            if (program.social.twitter) {
                                const twitterUrl = program.social.twitter.startsWith('http') ? program.social.twitter : `https://twitter.com/${program.social.twitter.replace('@', '')}`;
                                html += `<a href="${escapeHtml(twitterUrl)}" target="_blank" rel="noopener" title="Twitter">𝕏</a>`;
                            }

                            if (program.social.instagram) {
                                const instagramUrl = program.social.instagram.startsWith('http') ? program.social.instagram : `https://instagram.com/${program.social.instagram.replace('@', '')}`;
                                html += `<a href="${escapeHtml(instagramUrl)}" target="_blank" rel="noopener" title="Instagram">📷</a>`;
                            }

                            if (program.social.facebook) {
                                html += `<a href="${escapeHtml(program.social.facebook)}" target="_blank" rel="noopener" title="Facebook">f</a>`;
                            }

                            html += '</div>';
                        }

                        html += `</div>`;
                    });
                }

                html += `</div>`;
            });

            html += '</div>';

            // Footer
            html += `<div class="sapo-footer">Programación generada con SAPO</div>`;
            html += '</div>';

            root.innerHTML = html;

        } catch (error) {
            console.error('SAPO Widget Error:', error);
            root.innerHTML = `
                <div class="sapo-error">
                    <strong>❌ Error al cargar la programación</strong><br>
                    <small>${escapeHtml(error.message)}</small>
                </div>
            `;
        }
    }

    // Escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicializar widgets
    function initWidgets() {
        const containers = document.querySelectorAll('[id^="sapo-widget"]');
        containers.forEach(container => {
            const station = container.getAttribute('data-station');
            if (!station) {
                console.error('SAPO Widget: Missing data-station attribute');
                const s = container.attachShadow({ mode: 'open' });
                s.innerHTML = '<div style="color:#dc2626;padding:10px;">Error: Falta el atributo data-station</div>';
                return;
            }

            renderWidget(container, station);
        });
    }

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidgets);
    } else {
        initWidgets();
    }

    // Exponer función global para inicialización manual
    window.SAPOWidget = {
        version: WIDGET_VERSION,
        init: initWidgets,
        render: renderWidget
    };

})();
