// 1. Inisialisasi AOS (Animate On Scroll) hanya untuk elemen khusus tanpa konflik
if (window.AOS) {
    AOS.init({
        duration: 800,
        once: true,
        offset: 80,
        easing: 'ease-out-cubic',
        mirror: false,
        disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches
    });
    window.addEventListener('load', function() {
        if (window.AOS) AOS.refreshHard();
    });
}

// 2. Inisialisasi Typed.js (Efek Ketik Otomatis)
if (window.Typed && document.querySelector('#typed-text')) {
    new Typed('#typed-text', {
        strings: [
            'NEWS & LAYANAN PUBLIK',
            'PORTAL TRANSPARANSI DESA',
            'LAYANAN ADUAN WARGA'
        ],
        typeSpeed: 60,
        backSpeed: 40,
        backDelay: 2000,
        loop: true
    });
}

// 3. ENGINE SCROLL: parallax background, fade hero, dan navbar shrink.
// Transform background dan konten dipisahkan agar tidak saling menimpa.
(function() {
    const topBar = document.getElementById('top-bar');
    const mainHeader = document.getElementById('main-header');
    const headerContainer = document.getElementById('header-container');
    const heroBg = document.getElementById('hero-bg');
    const heroContent = document.getElementById('hero-content');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let ticking = false;

    function updateScrollEffects() {
        const scrollY = Math.max(0, window.scrollY || window.pageYOffset);

        if (!reduceMotion && heroBg && heroContent) {
            const heroHeight = document.getElementById('hero-section')?.offsetHeight || 700;

            // Progres fade berdasarkan tinggi hero.
            const progress = Math.min(Math.max(scrollY / (heroHeight * 0.65), 0), 1);

            // Background bergerak ke atas secara perlahan.
            const bgY = Math.min(scrollY * -0.18, 0);
            const bgScale = 1.08 + progress * 0.025;

            heroBg.style.transform =
                `translate3d(0, ${bgY}px, 0) scale(${bgScale})`;

            // Teks bergerak ke atas dan memudar.
            const contentY = scrollY * -0.12;
            const contentOpacity = Math.max(0, 1 - progress);

            heroContent.style.transform =
                `translate3d(0, ${contentY}px, 0)`;

            heroContent.style.opacity = String(contentOpacity);
            heroContent.style.pointerEvents =
                contentOpacity < 0.1 ? 'none' : '';
        }

        const scrolled = scrollY > 50;
        document.body.classList.toggle('has-scrolled', scrolled);
        if (mainHeader && headerContainer) {
            mainHeader.classList.toggle('shadow-md', scrolled);
            mainHeader.classList.toggle('bg-white/95', scrolled);
            headerContainer.classList.toggle('h-16', scrolled);
            headerContainer.classList.toggle('h-20', !scrolled);
        }
        ticking = false;
    }

    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(updateScrollEffects);
            ticking = true;
        }
    }, { passive: true });
    window.addEventListener('resize', updateScrollEffects, { passive: true });
    updateScrollEffects();
})();

// 4. INTERSECTION OBSERVER UNTUK STAGGERED REVEAL CARDS
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -60px 0px',
        threshold: 0.12
    };

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal-on-scroll').forEach(el => {
        revealObserver.observe(el);
    });
});
