/**
 * Shared helper for the animated, multi-view visitor-analytics charts
 * (Task #3812). Wraps the self-hosted Chart.js UMD build (public/js/vendor/
 * chart.umd.min.js — vendored on purpose, no CDN <script> SPOF) so both the
 * account-wide Visitors page and the per-link Visitor Insights page render
 * the same look, the same view toggle (line/bar/area for trends), the same
 * reduced-motion handling, and the same light/dark re-theming.
 */
(function (global) {
    function themeColors() {
        const light = document.documentElement.classList.contains('light-mode');
        return {
            tick: light ? '#475569' : 'rgba(255,255,255,0.65)',
            grid: light ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)',
            tipBg: light ? 'rgba(255,255,255,0.98)' : 'rgba(20,15,40,0.95)',
            palette: ['#3d6bff', '#0ea5e9', '#f43f5e', '#f59e0b', '#10b981', '#a855f7', '#ec4899', '#84cc16'],
        };
    }

    function prefersReducedMotion() {
        return !!(global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function hexToRgba(hex, alpha) {
        if (!hex || hex[0] !== '#') return hex;
        const r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    function datasetStyleFor(view, color) {
        return {
            fill: view === 'area',
            backgroundColor: view === 'area' ? hexToRgba(color, 0.18) : (view === 'bar' ? hexToRgba(color, 0.55) : 'transparent'),
            borderRadius: view === 'bar' ? 6 : 0,
        };
    }

    /**
     * Create an animated trend chart. `datasets` is [{label, data, color?}].
     * Returns the Chart.js instance with `__view` tracking the active toggle.
     */
    function createTrendChart(canvasId, labels, datasets, opts) {
        opts = opts || {};
        const el = document.getElementById(canvasId);
        if (!el || !global.Chart) return null;

        const c = themeColors();
        const rm = prefersReducedMotion();
        const view = opts.defaultView || 'line';

        const chart = new Chart(el, {
            type: view === 'area' ? 'line' : view,
            data: {
                labels,
                datasets: datasets.map((d, i) => {
                    const color = d.color || c.palette[i % c.palette.length];
                    return Object.assign({
                        label: d.label,
                        data: d.data,
                        borderColor: color,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: rm ? 0 : 2,
                        pointHoverRadius: 4,
                    }, datasetStyleFor(view, color));
                }),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: rm ? false : { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { position: 'bottom', labels: { color: c.tick, boxWidth: 10 } },
                    tooltip: {
                        backgroundColor: c.tipBg, titleColor: c.tick, bodyColor: c.tick,
                        borderColor: 'rgba(61,107,255,0.35)', borderWidth: 1, padding: 10, cornerRadius: 10,
                    },
                },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.tick }, border: { display: false } },
                    y: { beginAtZero: true, grid: { color: c.grid }, ticks: { color: c.tick, precision: 0 }, border: { display: false } },
                },
            },
        });
        chart.__view = view;
        return chart;
    }

    /** Swap a trend chart between line / bar / area without recreating it. */
    function setTrendView(chart, view) {
        if (!chart) return;
        chart.config.type = view === 'area' ? 'line' : view;
        chart.data.datasets.forEach((ds) => {
            Object.assign(ds, datasetStyleFor(view, ds.borderColor));
        });
        chart.__view = view;
        chart.update();
    }

    /** Doughnut/bar breakdown chart (visitors-by-type, visitors-by-source, ...). */
    function createBreakdownChart(canvasId, labels, data, opts) {
        opts = opts || {};
        const el = document.getElementById(canvasId);
        if (!el || !global.Chart) return null;

        const c = themeColors();
        const rm = prefersReducedMotion();
        const type = opts.type || 'doughnut';

        return new Chart(el, {
            type,
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: labels.map((_, i) => c.palette[i % c.palette.length]),
                    borderWidth: type === 'bar' ? 0 : 2,
                    borderColor: 'transparent',
                    borderRadius: type === 'bar' ? 6 : 0,
                }],
            },
            options: {
                indexAxis: type === 'bar' ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: rm ? false : { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: type !== 'bar', position: 'bottom', labels: { color: c.tick, boxWidth: 10 } },
                    tooltip: { backgroundColor: c.tipBg, titleColor: c.tick, bodyColor: c.tick, padding: 10, cornerRadius: 10 },
                },
                scales: type === 'bar' ? {
                    x: { beginAtZero: true, grid: { color: c.grid }, ticks: { color: c.tick, precision: 0 }, border: { display: false } },
                    y: { grid: { display: false }, ticks: { color: c.tick }, border: { display: false } },
                } : undefined,
            },
        });
    }

    /** Re-colour every live Chart.js instance when the app theme toggles. */
    function reTheme() {
        if (!global.Chart || !Chart.instances) return;
        const c = themeColors();
        Object.values(Chart.instances).forEach((ch) => {
            const o = ch.options || {};
            if (o.scales) Object.values(o.scales).forEach((sc) => {
                sc.ticks = sc.ticks || {}; sc.ticks.color = c.tick;
                sc.grid = sc.grid || {}; sc.grid.color = c.grid;
            });
            o.plugins = o.plugins || {};
            if (o.plugins.legend) { o.plugins.legend.labels = o.plugins.legend.labels || {}; o.plugins.legend.labels.color = c.tick; }
            if (o.plugins.tooltip) { o.plugins.tooltip.backgroundColor = c.tipBg; o.plugins.tooltip.titleColor = c.tick; o.plugins.tooltip.bodyColor = c.tick; }
            ch.update('none');
        });
    }
    new MutationObserver(reTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    global.AnalyticsCharts = {
        themeColors, prefersReducedMotion, createTrendChart, setTrendView, createBreakdownChart, reTheme,
    };
})(window);
