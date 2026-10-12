document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const themeButtons = document.querySelectorAll('[data-theme-toggle]');

    document.querySelectorAll('img').forEach((image) => {
        image.addEventListener('error', () => {
            image.hidden = true;
            const fallback = document.createElement('span');
            fallback.className = 'image-fallback';
            fallback.textContent = 'Artwork unavailable';
            image.parentElement?.appendChild(fallback);
        }, { once: true });
    });

    const setTheme = (theme) => {
        const activeTheme = theme === 'light' ? 'light' : 'dark';
        root.dataset.theme = activeTheme;

        try {
            window.localStorage.setItem('cinestream-theme', activeTheme);
        } catch (error) {
            // The current page still changes theme when storage is unavailable.
        }

        themeButtons.forEach((button) => {
            const label = button.querySelector('.theme-toggle-label');
            const icon = button.querySelector('.theme-toggle-icon');
            const nextLabel = activeTheme === 'dark' ? 'Light' : 'Dark';
            button.setAttribute('aria-label', `Switch to ${nextLabel.toLowerCase()} mode`);
            button.setAttribute('aria-pressed', String(activeTheme === 'light'));
            if (label) label.textContent = nextLabel;
            if (icon) {
                icon.innerHTML = activeTheme === 'dark'
                    ? '<circle cx="12" cy="12" r="3.5"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>'
                    : '<path d="M20.2 15.4A8.4 8.4 0 0 1 8.6 3.8 8.5 8.5 0 1 0 20.2 15.4Z"/>';
            }
        });
    };

    let initialTheme = 'dark';
    try {
        initialTheme = window.localStorage.getItem('cinestream-theme') === 'light' ? 'light' : 'dark';
    } catch (error) {
        // Use dark mode as the default when storage is unavailable.
    }
    setTheme(initialTheme);

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    });

    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const revealTargets = document.querySelectorAll('.section-header, .hero-info, .movie-card, .movie-detail, .player-container');
        revealTargets.forEach((item, index) => {
            item.classList.add('reveal-on-scroll');
            item.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 55}ms`);
        });

        root.classList.add('motion-ready');
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    currentObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -32px 0px' });

        revealTargets.forEach((item) => observer.observe(item));
    }

    document.querySelectorAll('.movie-rail').forEach((rail) => {
        const shell = document.createElement('div');
        shell.className = 'movie-rail-shell';
        rail.parentNode.insertBefore(shell, rail);
        shell.appendChild(rail);

        const controls = document.createElement('div');
        controls.className = 'movie-rail-controls';
        controls.setAttribute('aria-label', 'Movie carousel controls');

        const makeArrow = (direction, label, path) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rail-arrow';
            button.setAttribute('aria-label', label);
            button.innerHTML = `<svg aria-hidden="true" viewBox="0 0 24 24"><path d="${path}"/></svg>`;
            button.addEventListener('click', () => {
                rail.scrollBy({ left: direction * Math.max(260, rail.clientWidth * 0.78), behavior: 'smooth' });
            });
            return button;
        };

        const previous = makeArrow(-1, 'Scroll movies left', 'm15 18-6-6 6-6');
        const next = makeArrow(1, 'Scroll movies right', 'm9 18 6-6-6-6');
        controls.append(previous, next);
        shell.appendChild(controls);

        const updateControls = () => {
            previous.disabled = rail.scrollLeft <= 2;
            next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2;
        };
        rail.addEventListener('scroll', updateControls, { passive: true });
        window.addEventListener('resize', updateControls, { passive: true });
        updateControls();
    });
});
