/* ============================================================================
   DecentEdu Docs — layout engine
   Each page ships only its <article> content + a <script> setting PAGE meta.
   This file builds the topbar, sidebar nav, mobile menu, theme toggle,
   client-side sidebar search, and the auto "On this page" TOC.
   ========================================================================== */
(function () {
    // ---- Navigation model (single source of truth) -------------------------
    const NAV = [
        {
            heading: 'Introduction',
            items: [
                { href: 'index.html', label: 'Overview', icon: 'home' },
                { href: 'getting-started.html', label: 'Getting Started', icon: 'rocket' },
            ],
        },
        {
            heading: 'Platform',
            items: [
                { href: 'architecture.html', label: 'System Architecture', icon: 'layers' },
                { href: 'roles-permissions.html', label: 'Roles & Permissions', icon: 'shield' },
                { href: 'data-model.html', label: 'Data Model', icon: 'database' },
                { href: 'workflows.html', label: 'Key Workflows', icon: 'route' },
            ],
        },
        {
            heading: 'People & Academics',
            items: [
                { href: 'module-academic.html', label: 'Academic Setup', icon: 'book' },
                { href: 'module-admissions.html', label: 'Admissions', icon: 'user-plus' },
                { href: 'module-students.html', label: 'Students', icon: 'grad' },
                { href: 'module-hr.html', label: 'HR & Staff', icon: 'users' },
            ],
        },
        {
            heading: 'Daily Operations',
            items: [
                { href: 'module-attendance.html', label: 'Attendance', icon: 'check' },
                { href: 'module-routines.html', label: 'Routines', icon: 'clock' },
                { href: 'module-examinations.html', label: 'Exams & Results', icon: 'trophy' },
            ],
        },
        {
            heading: 'Finance',
            items: [
                { href: 'module-fees.html', label: 'Fees', icon: 'wallet' },
                { href: 'module-accounting.html', label: 'Accounting', icon: 'bank' },
            ],
        },
        {
            heading: 'Engagement',
            items: [
                { href: 'module-messaging.html', label: 'SMS & Messaging', icon: 'chat' },
                { href: 'module-credentials.html', label: 'Credentials & ID', icon: 'id' },
                { href: 'module-cms.html', label: 'CMS & Website', icon: 'globe' },
            ],
        },
        {
            heading: 'System',
            items: [
                { href: 'module-reporting.html', label: 'Dashboard & Reports', icon: 'chart' },
                { href: 'module-administration.html', label: 'Administration & Audit', icon: 'settings' },
            ],
        },
        {
            heading: 'Reference',
            items: [
                { href: 'api-reference.html', label: 'API Reference', icon: 'code' },
                { href: 'glossary.html', label: 'Glossary', icon: 'book-open' },
            ],
        },
    ];

    // ---- Tiny inline icon set (stroke) -------------------------------------
    const P = (d) => `<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
    const ICONS = {
        home: P('<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>'),
        rocket: P('<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2"/><path d="M14 4c3 0 6 3 6 6-1 5-5 8-5 8l-5-5s3-8 4-9Z"/><circle cx="14.5" cy="9.5" r="1.5"/>'),
        layers: P('<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>'),
        shield: P('<path d="M12 3 5 6v5c0 4 3 7 7 9 4-2 7-5 7-9V6l-7-3Z"/>'),
        database: P('<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>'),
        route: P('<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h6a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h5"/>'),
        book: P('<path d="M4 5c0-1 1-2 2-2h12v16H6c-1 0-2 1-2 2V5Z"/><path d="M4 19c0-1 1-2 2-2h12"/>'),
        'user-plus': P('<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M18 8v6M15 11h6"/>'),
        grad: P('<path d="m12 4 10 5-10 5L2 9l10-5Z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/>'),
        users: P('<circle cx="8" cy="8" r="3"/><path d="M2 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 6a3 3 0 0 1 0 6M22 20c0-2.5-1.5-4.6-4-5.5"/>'),
        check: P('<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/><path d="m9 15 2 2 4-4"/>'),
        clock: P('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
        trophy: P('<path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3"/><path d="M10 15h4M9 21h6M12 15v6"/>'),
        wallet: P('<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 9h18"/><circle cx="17" cy="13" r="1.3"/>'),
        bank: P('<path d="M4 10h16M5 10v8M19 10v8M9 10v8M15 10v8M3 20h18"/><path d="m12 3 8 5H4l8-5Z"/>'),
        chat: P('<path d="M4 5h16v11H9l-4 4V5Z"/><path d="M8 10h8M8 13h5"/>'),
        id: P('<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.5-1.5 1.7-2.5 3-2.5s2.5 1 3 2.5M15 9h4M15 13h4"/>'),
        globe: P('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.5 6 3.5 9S14.5 18.5 12 21c-2.5-2.5-3.5-6-3.5-9S9.5 5.5 12 3Z"/>'),
        chart: P('<path d="M4 4v16h16"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/>'),
        settings: P('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6.7 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 13.4H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.9 6.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.6V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>'),
        code: P('<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13 5l-2 14"/>'),
        'book-open': P('<path d="M12 6c-2-1.5-5-2-8-1.5V19c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5V4.5C17 4 14 4.5 12 6Z"/><path d="M12 6v14.5"/>'),
        search: P('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
        sun: P('<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>'),
        moon: P('<path d="M20 14A8 8 0 1 1 10 4a6 6 0 0 0 10 10Z"/>'),
        menu: P('<path d="M4 6h16M4 12h16M4 18h16"/>'),
    };

    const PAGE = window.PAGE || {};
    const current = location.pathname.split('/').pop() || 'index.html';

    // ---- Build topbar ------------------------------------------------------
    const topbar = document.createElement('header');
    topbar.className = 'topbar';
    topbar.innerHTML =
        `<button class="icon-btn" id="menu-btn" aria-label="Menu">${ICONS.menu}</button>` +
        `<a class="brand" href="index.html"><span class="brand-mark">DE</span>` +
        `<span class="brand-name">DecentEdu<small>School Management System</small></span></a>` +
        `<div class="spacer"></div>` +
        `<div class="searchbox"><label for="nav-search" style="display:none">Search</label>${ICONS.search}` +
        `<input id="nav-search" type="search" placeholder="Filter navigation…" autocomplete="off"><span class="hint">/</span></div>` +
        `<button class="icon-btn" id="theme-btn" aria-label="Toggle theme"></button>`;
    document.body.prepend(topbar);

    // ---- Build shell + move existing content into <main> -------------------
    const article = document.querySelector('main.content') || (() => {
        const m = document.createElement('main');
        m.className = 'content';
        while (document.body.children.length && document.body.lastElementChild.tagName !== 'HEADER' && document.body.lastElementChild !== topbar) {
            // fallthrough – handled below
            break;
        }
        return m;
    })();

    const shell = document.createElement('div');
    shell.className = 'shell';

    const sidebar = document.createElement('aside');
    sidebar.className = 'sidebar';
    sidebar.id = 'sidebar';

    NAV.forEach((sec) => {
        const s = document.createElement('div');
        s.className = 'nav-section';
        s.innerHTML = `<p class="nav-heading">${sec.heading}</p>`;
        sec.items.forEach((it) => {
            const a = document.createElement('a');
            a.className = 'nav-link' + (it.href === current ? ' active' : '');
            a.href = it.href;
            a.dataset.label = it.label.toLowerCase();
            a.innerHTML = `${ICONS[it.icon] || ''}<span>${it.label}</span>`;
            s.appendChild(a);
        });
        sidebar.appendChild(s);
    });

    const contentWrap = document.createElement('div');
    contentWrap.className = 'content-wrap';

    const toc = document.createElement('nav');
    toc.className = 'toc';
    toc.setAttribute('aria-label', 'On this page');

    contentWrap.appendChild(article);
    contentWrap.appendChild(toc);
    shell.appendChild(sidebar);
    shell.appendChild(contentWrap);

    const backdrop = document.createElement('div');
    backdrop.className = 'backdrop';

    document.body.appendChild(shell);
    document.body.appendChild(backdrop);

    // ---- Build "On this page" TOC + heading anchors ------------------------
    const heads = article.querySelectorAll('h2, h3');
    if (heads.length > 2) {
        toc.innerHTML = '<p class="toc-title">On this page</p>';
        heads.forEach((h) => {
            if (!h.id) h.id = h.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            const a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent;
            a.className = h.tagName === 'H3' ? 'h3' : '';
            toc.appendChild(a);
            const anc = document.createElement('a');
            anc.href = '#' + h.id; anc.className = 'anchor'; anc.textContent = '#';
            anc.setAttribute('aria-hidden', 'true');
            h.appendChild(anc);
        });

        const tocLinks = [...toc.querySelectorAll('a[href^="#"]')];
        const byId = {};
        tocLinks.forEach((l) => (byId[l.getAttribute('href').slice(1)] = l));
        const spy = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    tocLinks.forEach((l) => l.classList.remove('active'));
                    if (byId[e.target.id]) byId[e.target.id].classList.add('active');
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });
        heads.forEach((h) => spy.observe(h));
    }

    // ---- Theme -------------------------------------------------------------
    const themeBtn = document.getElementById('theme-btn');
    const setTheme = (t) => {
        document.documentElement.setAttribute('data-theme', t);
        themeBtn.innerHTML = t === 'dark' ? ICONS.sun : ICONS.moon;
        try { localStorage.setItem('de-theme', t); } catch (e) {}
    };
    let saved;
    try { saved = localStorage.getItem('de-theme'); } catch (e) {}
    setTheme(saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    themeBtn.addEventListener('click', () => setTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

    // ---- Mobile menu -------------------------------------------------------
    const menuBtn = document.getElementById('menu-btn');
    const toggleNav = (on) => document.body.classList.toggle('nav-open', on);
    menuBtn.addEventListener('click', () => toggleNav(!document.body.classList.contains('nav-open')));
    backdrop.addEventListener('click', () => toggleNav(false));
    sidebar.addEventListener('click', (e) => { if (e.target.closest('a')) toggleNav(false); });

    // ---- Sidebar search ----------------------------------------------------
    const search = document.getElementById('nav-search');
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        sidebar.querySelectorAll('.nav-section').forEach((sec) => {
            let any = false;
            sec.querySelectorAll('.nav-link').forEach((a) => {
                const hit = !q || a.dataset.label.includes(q);
                a.style.display = hit ? '' : 'none';
                if (hit) any = true;
            });
            sec.style.display = any ? '' : 'none';
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== search) { e.preventDefault(); search.focus(); }
        if (e.key === 'Escape') { search.value = ''; search.dispatchEvent(new Event('input')); search.blur(); toggleNav(false); }
    });
})();
