// main.js - Bapenda Website Scripts
document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile Menu Toggle ────────────────────────────────────────────
    var mobileToggle = document.getElementById('mobile-toggle');
    var navMenu      = document.querySelector('.nav-menu');
    var navOverlay   = document.getElementById('nav-overlay');
    var navCloseBtn  = document.getElementById('nav-close-btn');

    function openNav() {
        if (navMenu) navMenu.classList.add('active');
        if (navOverlay) navOverlay.classList.add('active');
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeNav() {
        if (navMenu) navMenu.classList.remove('active');
        if (navOverlay) navOverlay.classList.remove('active');
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (mobileToggle) mobileToggle.addEventListener('click', openNav);
    if (navCloseBtn)  navCloseBtn.addEventListener('click', closeNav);
    if (navOverlay)   navOverlay.addEventListener('click', closeNav);

    // Close nav when a link is clicked (single-page navigation)
    if (navMenu) {
        navMenu.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) closeNav();
            });
        });
    }

    // ── Mobile sub-menu: tap/click to toggle dropdown ─────────────────
    if (navMenu) {
        navMenu.querySelectorAll('.nav-item').forEach(function(item) {
            var dropdown = item.querySelector('.nav-dropdown');
            if (dropdown) {
                var link = item.querySelector('.nav-link');
                if (link) {
                    link.addEventListener('click', function(e) {
                        if (window.innerWidth <= 992) {
                            e.preventDefault();
                            var isOpen = item.classList.contains('open');
                            // Close all other open items
                            navMenu.querySelectorAll('.nav-item.open').forEach(function(el) {
                                el.classList.remove('open');
                            });
                            if (!isOpen) item.classList.add('open');
                        }
                    });
                }
            }
        });
    }

    // ── Sticky Header ─────────────────────────────────────────────────
    var header = document.querySelector('.header');
    if (header) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                header.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
            } else {
                header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.03)';
            }
        }, { passive: true });
    }

    // ── Hero Slider ───────────────────────────────────────────────────
    var slides       = document.querySelectorAll('.slide');
    var dots         = document.querySelectorAll('.slider-dot');
    var prevBtn      = document.querySelector('.prev-btn');
    var nextBtn      = document.querySelector('.next-btn');
    var currentSlide = 0;
    var sliderTimer  = null;

    function showSlide(index) {
        if (!slides || slides.length === 0) return;
        if (index >= slides.length) currentSlide = 0;
        else if (index < 0) currentSlide = slides.length - 1;
        else currentSlide = index;

        slides.forEach(function(s) { s.classList.remove('active'); });
        slides[currentSlide].classList.add('active');

        if (dots.length > 0) {
            dots.forEach(function(d) { d.classList.remove('active'); });
            if (dots[currentSlide]) dots[currentSlide].classList.add('active');
        }
    }

    function startSliderTimer() {
        if (slides.length > 1) {
            sliderTimer = setInterval(function () { showSlide(currentSlide + 1); }, 6000);
        }
    }

    function resetSliderTimer() {
        if (sliderTimer) clearInterval(sliderTimer);
        startSliderTimer();
    }

    if (nextBtn) nextBtn.addEventListener('click', function () { showSlide(currentSlide + 1); resetSliderTimer(); });
    if (prevBtn) prevBtn.addEventListener('click', function () { showSlide(currentSlide - 1); resetSliderTimer(); });

    dots.forEach(function(dot, i) {
        dot.addEventListener('click', function () { showSlide(i); resetSliderTimer(); });
    });

    startSliderTimer();

    // ── Touch/Swipe support for hero slider ───────────────────────────
    var heroSection = document.querySelector('.hero-section');
    if (heroSection && slides.length > 1) {
        var touchStartX = 0;
        var touchStartY = 0;

        heroSection.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        heroSection.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            var dy = e.changedTouches[0].clientY - touchStartY;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                if (dx < 0) { showSlide(currentSlide + 1); }
                else        { showSlide(currentSlide - 1); }
                resetSliderTimer();
            }
        }, { passive: true });
    }

    // ── Back to Top ───────────────────────────────────────────────────
    var backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        }, { passive: true });

        backToTop.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ── Reset sticky hover on touch devices ──────────────────────────
    // Prevent iOS sticky :hover on cards
    if ('ontouchstart' in window) {
        document.querySelectorAll('.service-card, .news-card, .link-card').forEach(function(el) {
            el.addEventListener('touchend', function() {
                // Force repaint to clear hover state
                this.style.transform = '';
            }, { passive: true });
        });
    }
});
