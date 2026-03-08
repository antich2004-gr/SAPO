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

    const WIDGET_VERSION = '1.0.0';

    // SVG icons para redes sociales
    const SVG_WEBSITE  = `<svg viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0ZM4.5 7.5a.5.5 0 0 1 0-1h5.793l-2.147-2.146a.5.5 0 0 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 7.5H4.5Z"/></svg>`;
    const SVG_TWITTER  = `<svg viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.6.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/></svg>`;
    const SVG_INSTAGRAM = `<svg viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/></svg>`;
    const SVG_MASTODON = `<svg viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z"/></svg>`;
    const SVG_BLUESKY  = `<svg viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M3.291 3.969c1.516 1.679 3.49 4.087 4.709 5.605 1.219-1.518 3.193-3.926 4.709-5.605C13.684 2.74 15.5 1.5 15.5 3.5c0 .677-.383 2.506-.572 3.213-.22.818-.804 1.596-1.745 1.931-1.214.433-3.065.353-4.183.119V11c0 2.5-1.5 5-3 5-1.5 0-3-2.5-3-5V8.763c-1.118.234-2.969.314-4.183-.119-.941-.335-1.525-1.113-1.745-1.931C2.883 6.006 2.5 4.177 2.5 3.5c0-2 1.816-.74 2.791.469z"/></svg>`;
    const SVG_FACEBOOK = `<svg viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/></svg>`;

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
            font-size: 16px;
            background: white;
        }

        /* TABS */
        .sapo-tabs {
            display: flex;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sapo-tabs::-webkit-scrollbar { display: none; }

        .sapo-tab-btn {
            flex: 1;
            min-width: 100px;
            padding: 15px 16px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
            position: relative;
            white-space: nowrap;
            font-family: inherit;
        }

        .sapo-tab-btn:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .sapo-tab-btn.active {
            color: var(--sapo-color, #10b981);
            background: white;
        }

        .sapo-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--sapo-color, #10b981);
        }

        /* TAB CONTENT */
        .sapo-tab-content {
            display: none;
            padding: 24px;
            background: white;
            animation: sapoFadeIn 0.3s;
        }

        .sapo-tab-content.active { display: block; }

        @keyframes sapoFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* PROGRAM CARD — estilo modern (defecto) */
        .sapo-program-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            gap: 20px;
            transition: all 0.3s;
            position: relative;
        }

        .sapo-program-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        /* classic */
        .sapo-widget.classic .sapo-program-card {
            border: 2px solid #d1d5db;
            border-radius: 4px;
            box-shadow: none;
        }
        .sapo-widget.classic .sapo-program-card:hover {
            border-color: var(--sapo-color, #10b981);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transform: none;
        }

        /* compact */
        .sapo-widget.compact .sapo-program-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            box-shadow: none;
            padding: 12px 16px;
        }
        .sapo-widget.compact .sapo-program-card:hover {
            border-color: var(--sapo-color, #10b981);
            transform: translateX(4px);
            box-shadow: none;
        }

        /* minimal */
        .sapo-widget.minimal .sapo-program-card {
            border: none;
            border-radius: 0;
            box-shadow: none;
            border-bottom: 1px solid #f3f4f6;
            padding: 16px;
        }
        .sapo-widget.minimal .sapo-program-card:hover {
            background: #f9fafb;
            border-bottom-color: var(--sapo-color, #10b981);
            transform: none;
            box-shadow: none;
        }

        /* Card en vivo (programa actual) */
        .sapo-program-card.is-live {
            background: #f5f5f5;
            border-left: 3px solid #ef4444;
        }

        /* TIME */
        .sapo-program-time {
            min-width: 55px;
            text-align: left;
            font-size: 1.1em;
            font-weight: 600;
            color: #000;
            align-self: flex-start;
            flex-shrink: 0;
        }

        /* IMAGE */
        .sapo-program-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .sapo-widget.classic .sapo-program-image { border-radius: 4px; }
        .sapo-widget.compact .sapo-program-image { width: 80px; height: 80px; border-radius: 6px; }
        .sapo-widget.minimal .sapo-program-image { border-radius: 0; }

        /* INFO */
        .sapo-program-info { flex: 1; min-width: 0; }

        .sapo-program-category {
            font-size: 0.7em;
            font-weight: 700;
            color: #dc2626;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .sapo-program-title {
            font-size: 1.2em;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .sapo-widget.compact .sapo-program-title { font-size: 1.05em; }

        .sapo-program-schedule {
            font-size: 0.9em;
            color: #64748b;
            margin-bottom: 10px;
        }

        /* Badge AHORA EN DIRECTO */
        .sapo-live-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #dc2626;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .sapo-program-description {
            color: #64748b;
            font-size: 0.95em;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        /* SOCIAL LINKS */
        .sapo-program-social {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .sapo-social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s;
            color: white;
        }

        .sapo-social-link.website   { background: #6b7280; }
        .sapo-social-link.website:hover { background: #4b5563; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(107,114,128,.3); }

        .sapo-social-link.twitter   { background: #1DA1F2; }
        .sapo-social-link.twitter:hover { background: #1a8cd8; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(29,161,242,.3); }

        .sapo-social-link.instagram { background: linear-gradient(45deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%); }
        .sapo-social-link.instagram:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(188,24,136,.3); }

        .sapo-social-link.mastodon  { background: #6364FF; }
        .sapo-social-link.mastodon:hover { background: #563acc; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(99,100,255,.3); }

        .sapo-social-link.bluesky   { background: #1185fe; }
        .sapo-social-link.bluesky:hover { background: #0d6ecd; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(17,133,254,.3); }

        .sapo-social-link.facebook  { background: #1877F2; }
        .sapo-social-link.facebook:hover { background: #145dbf; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(24,119,242,.3); }

        /* RSS ÚLTIMO EPISODIO */
        .sapo-rss-link {
            text-decoration: none;
            display: block;
            margin-top: 12px;
            transition: transform 0.2s;
        }
        .sapo-rss-link:hover { transform: translateX(4px); }

        .sapo-rss-episode {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid var(--sapo-color, #10b981);
            transition: all 0.2s;
        }
        .sapo-rss-link:hover .sapo-rss-episode {
            background: #e2e8f0;
            border-left-width: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .sapo-rss-episode-title {
            font-weight: 600;
            color: #334155;
            font-size: 0.95em;
        }
        .sapo-rss-link:hover .sapo-rss-episode-title { color: var(--sapo-color, #10b981); }

        /* EMPTY DAY */
        .sapo-empty-day {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        /* LOADING / ERROR */
        .sapo-loading {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            font-size: 1.1em;
        }

        .sapo-error {
            text-align: center;
            padding: 40px 20px;
            color: #dc2626;
            background: #fee2e2;
            border-radius: 8px;
            margin: 20px;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sapo-tab-btn  { min-width: 80px; padding: 12px 10px; font-size: 0.82em; }
            .sapo-tab-content { padding: 15px; }
            .sapo-program-card { flex-wrap: wrap; }
            .sapo-program-image { width: 100%; height: 200px; }
            .sapo-program-time  { width: 100%; }
        }

        @media (max-width: 480px) {
            .sapo-program-title { font-size: 1.05em; }
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

    // "HH:mm" → segundos desde medianoche
    function timeToSeconds(timeStr) {
        if (!timeStr) return 0;
        const parts = timeStr.split(':');
        if (parts.length < 2) return 0;
        return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60;
    }

    // Escapar HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Construir HTML de iconos sociales
    function buildSocialLinks(social, url) {
        const links = [];

        if (url) {
            links.push(`<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="sapo-social-link website" title="Sitio web del programa">${SVG_WEBSITE}</a>`);
        }

        if (social) {
            if (social.twitter) {
                const t = social.twitter.startsWith('http') ? social.twitter : `https://x.com/${social.twitter.replace('@', '')}`;
                links.push(`<a href="${escapeHtml(t)}" target="_blank" rel="noopener" class="sapo-social-link twitter" title="Twitter/X">${SVG_TWITTER}</a>`);
            }
            if (social.instagram) {
                const ig = social.instagram.startsWith('http') ? social.instagram : `https://instagram.com/${social.instagram.replace('@', '')}`;
                links.push(`<a href="${escapeHtml(ig)}" target="_blank" rel="noopener" class="sapo-social-link instagram" title="Instagram">${SVG_INSTAGRAM}</a>`);
            }
            if (social.mastodon) {
                let ms = social.mastodon;
                if (!ms.startsWith('http')) {
                    const m = ms.match(/@?([^@]+)@(.+)/);
                    ms = m ? `https://${m[2]}/@${m[1]}` : `https://${ms}`;
                }
                links.push(`<a href="${escapeHtml(ms)}" target="_blank" rel="noopener" class="sapo-social-link mastodon" title="Mastodon">${SVG_MASTODON}</a>`);
            }
            if (social.bluesky) {
                const bs = social.bluesky.startsWith('http') ? social.bluesky : `https://bsky.app/profile/${social.bluesky}`;
                links.push(`<a href="${escapeHtml(bs)}" target="_blank" rel="noopener" class="sapo-social-link bluesky" title="Bluesky">${SVG_BLUESKY}</a>`);
            }
            if (social.facebook) {
                const fb = social.facebook.startsWith('http') ? social.facebook : `https://facebook.com/${social.facebook}`;
                links.push(`<a href="${escapeHtml(fb)}" target="_blank" rel="noopener" class="sapo-social-link facebook" title="Facebook">${SVG_FACEBOOK}</a>`);
            }
        }

        if (!links.length) return '';
        return `<div class="sapo-program-social">${links.join('')}</div>`;
    }

    // Construir bloque "Último episodio"
    function buildLatestEpisode(episode) {
        if (!episode || !episode.title) return '';
        const title = `<strong>Último episodio:</strong> ${escapeHtml(episode.title)}`;
        if (episode.link) {
            return `<a href="${escapeHtml(episode.link)}" target="_blank" rel="noopener" class="sapo-rss-link">
                        <div class="sapo-rss-episode"><div class="sapo-rss-episode-title">${title}</div></div>
                    </a>`;
        }
        return `<div class="sapo-rss-episode"><div class="sapo-rss-episode-title">${title}</div></div>`;
    }

    // Renderizar widget
    async function renderWidget(container, station) {
        const baseUrl = getScriptBaseUrl();
        const apiUrl = `${baseUrl}/api_schedule.php?station=${encodeURIComponent(station)}`;

        // Shadow DOM para aislar CSS del sitio padre
        const shadow = container.attachShadow({ mode: 'open' });

        const style = document.createElement('style');
        style.textContent = WIDGET_STYLES;
        shadow.appendChild(style);

        const root = document.createElement('div');
        shadow.appendChild(root);

        root.innerHTML = '<div class="sapo-loading">⏳ Cargando programación...</div>';

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error('Error al cargar la programación');

            const data = await response.json();

            const color = data.config.color || '#10b981';
            const widgetStyle = data.config.style || 'modern';

            let styleClass = '';
            if (widgetStyle === 'classic') styleClass = ' classic';
            else if (widgetStyle === 'compact') styleClass = ' compact';
            else if (widgetStyle === 'minimal') styleClass = ' minimal';

            root.className = `sapo-widget${styleClass}`;
            root.style.setProperty('--sapo-color', color);

            const streamUrl = data.station.stream_url || '';

            // Día y hora actual
            const now = new Date();
            const jsDay = now.getDay(); // 0=Dom … 6=Sab
            // schedule array: [Lun=0, Mar=1, Mié=2, Jue=3, Vie=4, Sáb=5, Dom=6]
            const currentDayIndex = jsDay === 0 ? 6 : jsDay - 1;
            const currentSeconds = now.getHours() * 3600 + now.getMinutes() * 60;

            // Tabs
            let html = '<div class="sapo-tabs">';
            data.schedule.forEach((day, index) => {
                const active = index === currentDayIndex ? ' active' : '';
                html += `<button class="sapo-tab-btn${active}" data-index="${index}">${escapeHtml(day.name)}</button>`;
            });
            html += '</div>';

            // Contenido de cada tab
            data.schedule.forEach((day, index) => {
                const active = index === currentDayIndex ? ' active' : '';
                html += `<div class="sapo-tab-content${active}" id="sapo-day-${index}">`;

                if (!day.programs || day.programs.length === 0) {
                    html += '<div class="sapo-empty-day"><p>No hay programación para este día</p></div>';
                } else {
                    // Detectar programa en vivo (solo día actual)
                    let liveIndex = -1, liveStartSec = -1;
                    if (index === currentDayIndex) {
                        day.programs.forEach((p, pi) => {
                            const startSec = timeToSeconds(p.start_time);
                            let endSec = timeToSeconds(p.end_time);
                            if (endSec <= startSec) endSec += 86400;
                            let checkSec = currentSeconds;
                            if (endSec > 86400 && checkSec < startSec) checkSec += 86400;
                            if (checkSec >= startSec && checkSec < endSec && startSec > liveStartSec) {
                                liveIndex = pi;
                                liveStartSec = startSec;
                            }
                        });
                    }

                    day.programs.forEach((program, pi) => {
                        const isLive        = pi === liveIndex;
                        const isLiveProgram = program.type === 'live';

                        html += `<div class="sapo-program-card${isLive ? ' is-live' : ''}" id="sapo-prog-${index}-${pi}">`;

                        // Hora inicio
                        html += `<div class="sapo-program-time">${escapeHtml(program.start_time)}</div>`;

                        // Imagen
                        if (program.image) {
                            html += `<img src="${escapeHtml(program.image)}" alt="${escapeHtml(program.title)}" class="sapo-program-image" loading="lazy">`;
                        }

                        // Info
                        html += `<div class="sapo-program-info">`;

                        // Badge categoría solo para EN DIRECTO
                        if (isLiveProgram) {
                            html += `<div class="sapo-program-category">EN DIRECTO</div>`;
                        }

                        // Título
                        html += `<div class="sapo-program-title">${escapeHtml(program.title)}</div>`;

                        // Rango horario
                        html += `<div class="sapo-program-schedule">${escapeHtml(program.start_time)} a ${escapeHtml(program.end_time)}</div>`;

                        // Badge "AHORA EN DIRECTO" (con enlace al stream si existe)
                        if (isLive) {
                            if (streamUrl) {
                                html += `<a href="${escapeHtml(streamUrl)}" target="_blank" rel="noopener" class="sapo-live-badge" style="text-decoration:none;cursor:pointer;" title="Escuchar en directo">▶️ AHORA EN DIRECTO</a>`;
                            } else {
                                html += `<div class="sapo-live-badge">▶️ AHORA EN DIRECTO</div>`;
                            }
                        }

                        // Descripción
                        if (program.description) {
                            html += `<div class="sapo-program-description">${escapeHtml(program.description)}</div>`;
                        }

                        // Redes sociales + URL del programa
                        html += buildSocialLinks(program.social, program.url);

                        // Último episodio RSS
                        html += buildLatestEpisode(program.latest_episode);

                        html += `</div>`; // .sapo-program-info
                        html += `</div>`; // .sapo-program-card
                    });
                }

                html += `</div>`; // .sapo-tab-content
            });

            root.innerHTML = html;

            // Gestión de tabs
            root.querySelectorAll('.sapo-tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = this.dataset.index;
                    root.querySelectorAll('.sapo-tab-btn').forEach(b => b.classList.remove('active'));
                    root.querySelectorAll('.sapo-tab-content').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    root.querySelector(`#sapo-day-${idx}`).classList.add('active');
                });
            });

            // Auto-scroll al programa en vivo
            const liveCard = root.querySelector('.sapo-program-card.is-live');
            if (liveCard) {
                setTimeout(() => liveCard.scrollIntoView({ behavior: 'smooth', block: 'center' }), 500);
            }

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidgets);
    } else {
        initWidgets();
    }

    window.SAPOWidget = {
        version: WIDGET_VERSION,
        init: initWidgets,
        render: renderWidget
    };

})();
