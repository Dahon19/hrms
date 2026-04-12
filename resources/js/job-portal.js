import { initFilePond } from './filepond-init';
import { initCoreUiValidation } from './form-validation';

(function () {
    const modals = document.querySelectorAll('.job-apply-modal');
    modals.forEach((modal) => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    const forms = document.querySelectorAll('.job-apply-form');
    if (!forms.length) return;

    const initialModal = document.querySelector('.job-apply-modal[data-auto-open="1"]');
    if (initialModal && window.jQuery) {
        window.jQuery(initialModal).modal('show');
    }

    forms.forEach((form) => {
        initFilePond(form);
    });

    initCoreUiValidation(document);
})();

(function () {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const topbar = document.getElementById('topbar');
    const nav = document.getElementById('portalNav');
    const menuToggle = document.getElementById('portalMenuToggle');
    const navLinks = nav ? Array.from(nav.querySelectorAll('a[href^="#"]')) : [];
    const sections = navLinks
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

    const setMenuOpen = (open) => {
        if (!nav || !menuToggle) return;
        nav.classList.toggle('is-open', open);
        menuToggle.classList.toggle('is-open', open);
        menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const setActiveLink = (id) => {
        navLinks.forEach((link) => {
            const active = link.getAttribute('href') === `#${id}`;
            link.classList.toggle('is-active', active);
        });
    };

    const handleTopbar = () => {
        if (!topbar) return;
        topbar.classList.toggle('compact', window.scrollY > 24);
    };

    const typewriterEl = document.getElementById('portalHeroTypewriter');
    if (typewriterEl) {
        let phrases = [];

        try {
            const rawPhrases = typewriterEl.getAttribute('data-phrases') || '[]';
            const parsed = JSON.parse(rawPhrases);
            if (Array.isArray(parsed)) {
                phrases = parsed
                    .map((phrase) => String(phrase || '').trim())
                    .filter(Boolean);
            }
        } catch (error) {
            phrases = [];
        }

        if (!phrases.length) {
            phrases = [typewriterEl.textContent.trim()].filter(Boolean);
        }

        if (phrases.length) {
            typewriterEl.textContent = phrases[0];
        }

        if (phrases.length > 1) {
            let phraseIndex = 0;
            let charIndex = phrases[0].length;
            let deleting = false;

            const tickTypewriter = () => {
                const currentPhrase = phrases[phraseIndex] || '';
                typewriterEl.textContent = currentPhrase.slice(0, charIndex);

                let delay = deleting ? 40 : 72;

                if (!deleting && charIndex < currentPhrase.length) {
                    charIndex += 1;
                } else if (!deleting) {
                    deleting = true;
                    delay = 1350;
                } else if (charIndex > 0) {
                    charIndex -= 1;
                } else {
                    deleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    delay = 240;
                }

                window.setTimeout(tickTypewriter, delay);
            };

            window.setTimeout(() => {
                charIndex = 0;
                typewriterEl.textContent = '';
                tickTypewriter();
            }, 520);
        }
    }

    // Global smooth-scroll handler for in-page anchors.
    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href^="#"]');
        if (!anchor) return;

        const href = anchor.getAttribute('href');
        if (!href || href === '#') return;

        const target = document.querySelector(href);
        if (!target) return;

        event.preventDefault();
        const topbarHeight = topbar ? topbar.getBoundingClientRect().height : 0;
        const y = window.pageYOffset + target.getBoundingClientRect().top - topbarHeight - 10;
        window.scrollTo({
            top: Math.max(0, y),
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
        });

        if (window.innerWidth <= 768) {
            setMenuOpen(false);
        }
    });

    if (menuToggle && nav) {
        menuToggle.addEventListener('click', () => {
            setMenuOpen(!nav.classList.contains('is-open'));
        });

        document.addEventListener('click', (event) => {
            if (window.innerWidth > 768) return;
            if (nav.contains(event.target) || menuToggle.contains(event.target)) return;
            setMenuOpen(false);
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                setMenuOpen(false);
            }
        });
    }

    if (sections.length) {
        const activeObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && entry.target.id) {
                        setActiveLink(entry.target.id);
                    }
                });
            },
            {
                root: null,
                threshold: 0.45,
            }
        );

        sections.forEach((section) => activeObserver.observe(section));
    }

    const revealNodes = Array.from(document.querySelectorAll('.section-reveal'));
    if (revealNodes.length) {
        if (prefersReducedMotion) {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        } else {
            const revealObserver = new IntersectionObserver(
                (entries, observer) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    });
                },
                {
                    threshold: 0.16,
                    rootMargin: '0px 0px -8% 0px',
                }
            );

            revealNodes.forEach((node) => revealObserver.observe(node));
        }
    }

    const counters = Array.from(document.querySelectorAll('[data-counter]'));
    if (counters.length) {
        const animateCounter = (el) => {
            const target = Number(el.getAttribute('data-target') || 0);
            const duration = 1200;
            const start = performance.now();

            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(target * eased).toLocaleString();
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        };

        if (prefersReducedMotion) {
            counters.forEach((counter) => {
                const target = Number(counter.getAttribute('data-target') || 0);
                counter.textContent = target.toLocaleString();
            });
        } else {
            const counterObserver = new IntersectionObserver(
                (entries, observer) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        animateCounter(entry.target);
                        observer.unobserve(entry.target);
                    });
                },
                { threshold: 0.5 }
            );

            counters.forEach((counter) => counterObserver.observe(counter));
        }
    }

    const parallaxItems = Array.from(document.querySelectorAll('[data-parallax]'));
    if (parallaxItems.length && !prefersReducedMotion) {
        const updateParallax = () => {
            const scrollY = window.scrollY;
            parallaxItems.forEach((el) => {
                const speed = Number(el.getAttribute('data-speed') || 0.1);
                const y = scrollY * speed;
                el.style.transform = `translate3d(0, ${y}px, 0)`;
            });
        };

        updateParallax();
        window.addEventListener('scroll', updateParallax, { passive: true });
    }

    handleTopbar();
    window.addEventListener('scroll', handleTopbar, { passive: true });
})();


