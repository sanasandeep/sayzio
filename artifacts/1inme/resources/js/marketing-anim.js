/* =====================================================================
   Sayzio · Marketing animation runtime
   - IntersectionObserver toggles `.in-view` on [data-anim] elements
   - Count-up animation for [data-count="123"] when revealed
   - Parallax for [data-parallax="0.15"] (translateY by scroll)
   - Tilt-on-mouse for [data-tilt]

   Exposes window.marketingAnimScan(root): an idempotent pass that wires
   the behaviours above for elements under `root`. It runs automatically
   for the initial document, and is re-invoked by pages that inject
   deferred server-rendered fragments (e.g. the homepage's below-the-fold
   sections) so late-arriving [data-anim]/[data-count]/… elements behave
   exactly like ones present at first paint. Elements are stamped with
   data-ma-* flags so a re-scan never double-observes or double-binds.
   ===================================================================== */
(function () {
    'use strict';

    var prefersReduced = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Shared observers, created lazily on first use so a page without a
    // given feature never pays for it.
    var revealIo = null, countIo = null;

    // Parallax runs off one scroll listener over a live list of elements.
    var parallaxEls = [];
    var parallaxBound = false;
    var parallaxEnabled = !prefersReduced && window.innerWidth > 768;

    function applyParallax() {
        var vh = window.innerHeight;
        parallaxEls.forEach(function (el) {
            var rect = el.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > vh) return;
            var speed = parseFloat(el.dataset.parallax) || 0.15;
            var center = rect.top + rect.height / 2;
            var offset = (vh / 2 - center) * speed;
            el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
        });
    }

    function scan(root) {
        root = root || document;

        /* ---------- Reveal on scroll ---------- */
        var revealEls = Array.prototype.filter.call(
            root.querySelectorAll('[data-anim]'),
            function (el) { return !el.dataset.maReveal; }
        );
        revealEls.forEach(function (el) { el.dataset.maReveal = '1'; });
        if (revealEls.length) {
            // Idempotent reveal — safe to call multiple times per element.
            var applyReveal = function (el) { el.classList.add('in-view'); };
            if (prefersReduced || !('IntersectionObserver' in window)) {
                revealEls.forEach(applyReveal);
            } else {
                if (!revealIo) {
                    revealIo = new IntersectionObserver(function (entries) {
                        entries.forEach(function (e) {
                            if (e.isIntersecting) {
                                applyReveal(e.target);
                                if (e.target.dataset.animOnce !== 'false') revealIo.unobserve(e.target);
                            }
                        });
                    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
                }
                revealEls.forEach(function (el) { revealIo.observe(el); });
                // Failsafe backstop: if the observer never fires (Safari quirks,
                // an earlier script error, bfcache restores…) content must NEVER
                // stay stuck at opacity 0. After a short grace period reveal
                // everything unconditionally — revealed elements are unaffected.
                var revealAll = function () { revealEls.forEach(applyReveal); };
                setTimeout(revealAll, 2200);
                window.addEventListener('pageshow', function (e) {
                    if (e.persisted) revealAll();
                });
            }
        }

        /* ---------- Count up ---------- */
        var counters = Array.prototype.filter.call(
            root.querySelectorAll('[data-count]'),
            function (el) { return !el.dataset.maCount; }
        );
        counters.forEach(function (el) { el.dataset.maCount = '1'; });
        if (counters.length && !prefersReduced && 'IntersectionObserver' in window) {
            var formatNum = function (n, suffix) {
                if (n >= 1000000) return (n / 1000000).toFixed(n >= 10000000 ? 0 : 1).replace(/\.0$/, '') + 'M' + suffix;
                if (n >= 1000)    return (n / 1000).toFixed(n >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'K' + suffix;
                return Math.round(n).toLocaleString() + suffix;
            };
            var animateCount = function (el) {
                var target = parseFloat(el.dataset.count) || 0;
                var suffix = el.dataset.countSuffix || '';
                var dur = parseInt(el.dataset.countDuration, 10) || 1600;
                var start = performance.now();
                var step = function (now) {
                    var p = Math.min(1, (now - start) / dur);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = formatNum(target * eased, suffix);
                    if (p < 1) requestAnimationFrame(step);
                    else el.textContent = formatNum(target, suffix);
                };
                requestAnimationFrame(step);
            };
            if (!countIo) {
                countIo = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (e.isIntersecting) { animateCount(e.target); countIo.unobserve(e.target); }
                    });
                }, { threshold: 0.5 });
            }
            counters.forEach(function (el) { countIo.observe(el); });
        } else {
            counters.forEach(function (el) {
                var target = parseFloat(el.dataset.count) || 0;
                var suffix = el.dataset.countSuffix || '';
                el.textContent = (target >= 1000 ? Math.round(target).toLocaleString() : target) + suffix;
            });
        }

        /* ---------- Parallax (subtle, only desktop) ---------- */
        if (parallaxEnabled) {
            var newParallax = Array.prototype.filter.call(
                root.querySelectorAll('[data-parallax]'),
                function (el) { return !el.dataset.maParallax; }
            );
            newParallax.forEach(function (el) {
                el.dataset.maParallax = '1';
                parallaxEls.push(el);
            });
            if (parallaxEls.length && !parallaxBound) {
                parallaxBound = true;
                var ticking = false;
                window.addEventListener('scroll', function () {
                    if (!ticking) {
                        ticking = true;
                        requestAnimationFrame(function () { ticking = false; applyParallax(); });
                    }
                }, { passive: true });
            }
            if (newParallax.length) applyParallax();
        }

        /* ---------- Tilt on pointer ---------- */
        if (!prefersReduced && window.matchMedia('(pointer:fine)').matches) {
            var tiltEls = Array.prototype.filter.call(
                root.querySelectorAll('[data-tilt]'),
                function (el) { return !el.dataset.maTilt; }
            );
            tiltEls.forEach(function (el) {
                el.dataset.maTilt = '1';
                var max = parseFloat(el.dataset.tilt) || 6;
                el.addEventListener('pointermove', function (e) {
                    var r = el.getBoundingClientRect();
                    var px = (e.clientX - r.left) / r.width - 0.5;
                    var py = (e.clientY - r.top) / r.height - 0.5;
                    el.style.transform = 'perspective(900px) rotateX(' + (-py * max).toFixed(2) +
                        'deg) rotateY(' + (px * max).toFixed(2) + 'deg)';
                });
                el.addEventListener('pointerleave', function () {
                    el.style.transform = 'perspective(900px) rotateX(0) rotateY(0)';
                });
            });
        }
    }

    window.marketingAnimScan = scan;
    scan(document);
})();
