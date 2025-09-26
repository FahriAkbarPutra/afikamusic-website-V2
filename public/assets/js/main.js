document.addEventListener('DOMContentLoaded', () => {
    // Fungsi Animasi Gelombang
    const wave1 = document.getElementById('wave1');
    const wave2 = document.getElementById('wave2');
    const wave3 = document.getElementById('wave3');
    if (wave1 && wave2 && wave3) {
        let time = 0;
        const animateWaves = () => {
            const width = window.innerWidth;
            const height = window.innerHeight;
            wave1.setAttribute('d', generateWavePath(width, height, time, 110, 0.002, 0.35)); // top (blue)
            wave2.setAttribute('d', generateWavePath(width, height, time, 130, 0.0018, 0.52)); // middle (yellow)
            wave3.setAttribute('d', generateWavePath(width, height, time, 150, 0.0014, 0.68)); // bottom (blue)
            time += 0.3;
            requestAnimationFrame(animateWaves);
        };
        const generateWavePath = (width, height, time, amplitude, frequency, yOffset) => {
            let path = `M ${-5} ${height * yOffset}`;
            for (let x = 0; x <= width + 5; x += 5) {
                const y = Math.sin(x * frequency - time * 0.05) * amplitude + (height * yOffset);
                path += ` L ${x} ${y}`;
            }
            return path;
        };
        animateWaves();
    }

    // Fungsi Animasi Scroll
    const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    const sectionsToAnimate = document.querySelectorAll('.fade-in-section');
    sectionsToAnimate.forEach(section => observer.observe(section));

    // Fungsi Menu Mobile
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const navContainer = document.getElementById('nav-container');
    const navOverlay = document.getElementById('nav-overlay');
    const mobileDrawer = document.getElementById('mobile-drawer');
    const mobileDrawerClose = document.getElementById('mobile-drawer-close');
    if (mobileMenuButton && mobileDrawer) {
        const toggleMenu = () => {
            const isOpen = mobileDrawer.classList.toggle('is-open');
            mobileMenuButton.classList.toggle('is-active', isOpen);
            document.body.classList.toggle('menu-open', isOpen);
            mobileMenuButton.setAttribute('aria-expanded', String(isOpen));
        };
        mobileMenuButton.addEventListener('click', toggleMenu);
        mobileDrawer.addEventListener('click', (e) => {
            const target = e.target;
            if (target.tagName === 'A' && mobileDrawer.classList.contains('is-open')) {
                toggleMenu();
            }
        });
        if (mobileDrawerClose) {
            mobileDrawerClose.addEventListener('click', () => {
                if (mobileDrawer.classList.contains('is-open')) toggleMenu();
            });
        }
        if (navOverlay) {
            navOverlay.addEventListener('click', () => {
                if (mobileDrawer.classList.contains('is-open')) toggleMenu();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && mobileDrawer.classList.contains('is-open')) {
                toggleMenu();
            }
        });
    }

    // Set active nav link based on current path
    const basePath = document.body.getAttribute('data-base-path') || '';
    const currentPath = window.location.pathname.replace(basePath, '') || '/home';
    const navLinks = document.querySelectorAll('#nav-container .nav-center a, #mobile-drawer .mobile-drawer__links a');
    navLinks.forEach(link => {
        try {
            const linkPath = new URL(link.href).pathname.replace(basePath, '');
            if (linkPath === currentPath || (currentPath === '/' && linkPath === '/home')) {
                link.classList.add('is-active');
            }
        } catch (_) {}
        // Close drawer when clicking inside
        link.addEventListener('click', () => {
            if (mobileDrawer && mobileDrawer.classList.contains('is-open')) {
                mobileDrawer.classList.remove('is-open');
                mobileMenuButton.classList.remove('is-active');
                document.body.classList.remove('menu-open');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
    });
});