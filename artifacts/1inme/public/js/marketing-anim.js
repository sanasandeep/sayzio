/* =====================================================================
   Sayzio · Marketing animation runtime
   - IntersectionObserver toggles `.in-view` on [data-anim] elements
   - Count-up animation for [data-count="123"] when revealed
   - Parallax for [data-parallax="0.15"] (translateY by scroll)
   - Tilt-on-mouse for [data-tilt]
   ===================================================================== */
(function () {
    'use strict';

    var prefersReduced = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Reveal on scroll ---------- */
    var revealEls = document.querySelectorAll('[data-anim]');
    if (revealEls.length) {
        if (prefersReduced || !('IntersectionObserver' in window)) {
            revealEls.forEach(function (el) { el.classList.add('in-view'); });
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('in-view');
                        if (e.target.dataset.animOnce !== 'false') io.unobserve(e.target);
                    }
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
            revealEls.forEach(function (el) { io.observe(el); });
        }
    }

    /* ---------- Count up ---------- */
    var counters = document.querySelectorAll('[data-count]');
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
        var co = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { animateCount(e.target); co.unobserve(e.target); }
            });
        }, { threshold: 0.5 });
        counters.forEach(function (el) { co.observe(el); });
    } else {
        counters.forEach(function (el) {
            var target = parseFloat(el.dataset.count) || 0;
            var suffix = el.dataset.countSuffix || '';
            el.textContent = (target >= 1000 ? Math.round(target).toLocaleString() : target) + suffix;
        });
    }

    /* ---------- Parallax (subtle, only desktop) ---------- */
    var parallaxEls = document.querySelectorAll('[data-parallax]');
    if (parallaxEls.length && !prefersReduced && window.innerWidth > 768) {
        var ticking = false;
        var apply = function () {
            ticking = false;
            var vh = window.innerHeight;
            parallaxEls.forEach(function (el) {
                var rect = el.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > vh) return;
                var speed = parseFloat(el.dataset.parallax) || 0.15;
                var center = rect.top + rect.height / 2;
                var offset = (vh / 2 - center) * speed;
                el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
            });
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { requestAnimationFrame(apply); ticking = true; }
        }, { passive: true });
        apply();
    }

    /* ---------- Tilt on pointer ---------- */
    var tiltEls = document.querySelectorAll('[data-tilt]');
    if (tiltEls.length && !prefersReduced && window.matchMedia('(pointer:fine)').matches) {
        tiltEls.forEach(function (el) {
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
})();
