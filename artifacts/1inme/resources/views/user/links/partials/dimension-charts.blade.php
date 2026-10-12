{{--
    Draws every dimension-card donut on the page.

    The per-link page calls `doughnut(...)` once per card from inside its own
    script block, which means a new card is only half-added until someone
    remembers the second edit. Here the canvas carries its own data, so this
    finds them all. A page gains a card by including `dimension-card`; nothing
    else has to change.

    Re-themes with the app, the same way the other charts on these pages do.
--}}
<script>
(() => {
    const PALETTE = ['#3d6bff','#10b981','#3b82f6','#f59e0b','#ec4899','#06b6d4','#5c83ff','#ef4444','#14b8a6','#eab308'];

    const tickColor = () => document.documentElement.classList.contains('light-mode')
        ? 'rgba(15,23,42,0.75)' : 'rgba(255,255,255,0.7)';

    function draw() {
        if (!window.Chart) return;
        document.querySelectorAll('canvas[data-dimension-chart]').forEach((el) => {
            if (el.dataset.drawn === '1') return;
            let labels = [], values = [];
            try {
                labels = JSON.parse(el.dataset.labels || '[]');
                values = JSON.parse(el.dataset.values || '[]');
            } catch (e) { return; }
            if (!labels.length) return;
            el.dataset.drawn = '1';
            new Chart(el, {
                type: 'doughnut',
                data: { labels, datasets: [{ data: values, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 8, spacing: 2 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '65%',
                    plugins: {
                        legend: { position: 'right', labels: { color: tickColor(), font: { size: 11, weight: '500' }, usePointStyle: true, padding: 10, boxWidth: 8 } },
                        tooltip: { padding: 10, cornerRadius: 10 }
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        draw();
    }

    // The theme toggle flips a class on <html>; follow it without a reload.
    new MutationObserver(() => {
        if (!window.Chart || !Chart.instances) return;
        Object.values(Chart.instances).forEach((c) => {
            if (!c?.options?.plugins?.legend?.labels) return;
            c.options.plugins.legend.labels.color = tickColor();
            c.update('none');
        });
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();
</script>
