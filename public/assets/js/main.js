document.addEventListener('DOMContentLoaded', () => {
    // Fungsi Animasi Gelombang
    const wave1 = document.getElementById('wave1');
    if (wave1) {
        let time = 0;
        const animateWaves = () => {
            const width = window.innerWidth;
            const height = window.innerHeight;
            const wave2 = document.getElementById('wave2');
            const wave3 = document.getElementById('wave3');
            wave1.setAttribute('d', generateWavePath(width, height, time, 120, 0.002, 0.5));
            wave2.setAttribute('d', generateWavePath(width, height, time, 150, 0.0015, 0.6));
            wave3.setAttribute('d', generateWavePath(width, height, time, 90, 0.0025, 0.4));
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
    if (mobileMenuButton && navContainer) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenuButton.classList.toggle('is-active');
            navContainer.classList.toggle('is-open');
        });
    }
});