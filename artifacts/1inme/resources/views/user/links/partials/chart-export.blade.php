{{--
    Download or copy any chart on this page as a PNG.

    The Geographic Heatmap already had a one-off version of this: build a
    canvas, toDataURL, click a synthetic <a>. This is the same idea made
    reusable, because "download the chart" is a thing every chart on the page
    should answer, not one of them.

    Two kinds of source, handled separately because they have nothing in
    common except the result:

      - a Chart.js <canvas>, which can be drawn straight into a new canvas
      - the when-it-gets-clicked grid, which is DOM, and is re-drawn as
        rectangles rather than rasterised with a library

    A Chart.js canvas is transparent. Copied into a Slack message or a doc
    that is a white-on-white or black-on-black rectangle, so everything is
    composited onto the card's own ground first, and the image is padded and
    titled so it stands alone once it leaves the page.

    Clipboard support is genuinely partial -- Safari allows the write only
    inside the gesture that triggered it, Firefox needs a flag, and any of it
    can be refused outright -- so the copy button is only rendered where the
    API exists, and a refusal says so rather than failing silently.
--}}
<script>
(function () {
    'use strict';

    var BRAND = '#3d6bff';

    function themeInk() {
        var light = document.documentElement.classList.contains('light-mode');
        return {
            bg:    light ? '#ffffff' : '#131120',
            text:  light ? '#15131f' : '#f3f2f8',
            faint: light ? '#8d8a9b' : '#716e81',
            line:  light ? 'rgba(20,18,28,0.10)' : 'rgba(255,255,255,0.10)'
        };
    }

    function fileName(slug) {
        var d = new Date();
        var p = function (n) { return String(n).padStart(2, '0'); };
        return 'sayzio-' + slug + '-' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate())
            + '-' + p(d.getHours()) + p(d.getMinutes()) + '.png';
    }

    // Everything ends up here: an opaque card with a title, the artwork, and
    // the link and period it came from, so the image still means something
    // when it is pasted somewhere with no context.
    function frame(draw, opts) {
        var ink = themeInk();
        var pad = 32, head = opts.title ? 64 : pad, foot = opts.footer ? 40 : pad;
        var w = opts.width + pad * 2;
        var h = opts.height + head + foot;

        var c = document.createElement('canvas');
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        c.width = w * dpr;
        c.height = h * dpr;
        var x = c.getContext('2d');
        x.scale(dpr, dpr);

        x.fillStyle = ink.bg;
        x.fillRect(0, 0, w, h);

        if (opts.title) {
            x.fillStyle = ink.text;
            x.font = '600 16px Inter, system-ui, sans-serif';
            x.textBaseline = 'middle';
            x.fillText(opts.title, pad, 32);
        }

        x.save();
        x.translate(pad, head);
        draw(x, opts.width, opts.height);
        x.restore();

        if (opts.footer) {
            x.fillStyle = ink.faint;
            x.font = '400 11px Inter, system-ui, sans-serif';
            x.textBaseline = 'middle';
            x.fillText(opts.footer, pad, h - foot / 2);
        }

        return c;
    }

    function canvasToBlob(c) {
        return new Promise(function (resolve, reject) {
            try {
                c.toBlob(function (b) { b ? resolve(b) : reject(new Error('toBlob returned nothing')); }, 'image/png');
            } catch (e) { reject(e); }
        });
    }

    // ---- the two source kinds -------------------------------------------

    function fromChartCanvas(el, opts) {
        var w = el.clientWidth || 640;
        var h = el.clientHeight || 320;
        return frame(function (x) {
            // drawImage, not toBase64Image: the live canvas is already laid
            // out at the size we want and scaled for this display.
            x.drawImage(el, 0, 0, w, h);
        }, { width: w, height: h, title: opts.title, footer: opts.footer });
    }

    function fromHeatGrid(grid, opts) {
        var ink = themeInk();
        var cells = grid.querySelectorAll('.hm-cell');
        if (!cells.length) return null;

        // The row labels come off the grid rather than a hardcoded list, so
        // an export matches the rows actually drawn -- the dashboard's copy
        // is dated ("Tue 22"), the link page's is not.
        var days = Array.prototype.map.call(
            grid.querySelectorAll('.hm-day'),
            function (el) { return (el.textContent || '').trim(); }
        );
        var rows = Math.max(1, Math.ceil(cells.length / 12));
        var size = 26, gap = 4, labelW = 46;
        var w = labelW + (12 * size) + (11 * gap);
        var h = (rows * size) + ((rows - 1) * gap) + 22;

        return frame(function (x) {
            for (var i = 0; i < cells.length; i++) {
                var d = Math.floor(i / 12), b = i % 12;
                if (b === 0) {
                    x.fillStyle = ink.faint;
                    x.font = '600 10px Inter, system-ui, sans-serif';
                    x.textAlign = 'center';
                    x.textBaseline = 'middle';
                    x.fillText(days[d] || '', labelW / 2, d * (size + gap) + size / 2);
                }
                // The rendered colour, so the exported image matches what is
                // on screen instead of re-deriving the scale here and drifting.
                x.fillStyle = getComputedStyle(cells[i]).backgroundColor;
                var rx = labelW + b * (size + gap);
                var ry = d * (size + gap);
                x.beginPath();
                x.roundRect ? x.roundRect(rx, ry, size, size, 6) : x.rect(rx, ry, size, size);
                x.fill();
            }
            x.fillStyle = ink.faint;
            x.font = '400 10px "IBM Plex Mono", ui-monospace, monospace';
            x.textAlign = 'center';
            ['00', '04', '08', '12', '16', '20'].forEach(function (label, k) {
                var b = k * 2;
                x.fillText(label, labelW + b * (size + gap) + size / 2, h - 8);
            });
        }, { width: w, height: h, title: opts.title, footer: opts.footer });
    }

    function build(btn) {
        var target = document.getElementById(btn.dataset.chartTarget);
        if (!target) return null;
        var opts = { title: btn.dataset.chartTitle || 'Chart', footer: btn.dataset.chartFooter || '' };
        return target.tagName === 'CANVAS' ? fromChartCanvas(target, opts) : fromHeatGrid(target, opts);
    }

    // ---- actions ---------------------------------------------------------

    function toast(msg, isError) {
        var t = document.getElementById('chart-export-toast');
        if (!t) return;
        t.textContent = msg;
        t.dataset.state = isError ? 'error' : 'ok';
        t.style.display = 'inline-flex';
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { t.style.display = 'none'; }, 3200);
    }

    function download(btn) {
        var c = build(btn);
        if (!c) { toast('Nothing to export yet.', true); return; }
        var a = document.createElement('a');
        try { a.href = c.toDataURL('image/png'); }
        catch (e) { toast('Could not export this chart.', true); return; }
        a.download = fileName(btn.dataset.chartSlug || 'chart');
        document.body.appendChild(a);
        a.click();
        a.remove();
        toast('Image downloaded.');
    }

    function copy(btn) {
        var c = build(btn);
        if (!c) { toast('Nothing to copy yet.', true); return; }
        canvasToBlob(c).then(function (blob) {
            return navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        }).then(function () {
            toast('Image copied. Paste it anywhere.');
        }).catch(function () {
            // Denied permission, an insecure origin, or a browser that only
            // allows this inside its own gesture window. Say so; the download
            // button beside it always works.
            toast('Your browser blocked the copy. Use Download instead.', true);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var canCopy = !!(navigator.clipboard && window.ClipboardItem && window.isSecureContext);

        document.querySelectorAll('[data-chart-action]').forEach(function (btn) {
            if (btn.dataset.chartAction === 'copy' && !canCopy) {
                btn.remove();
                return;
            }
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                btn.dataset.chartAction === 'copy' ? copy(btn) : download(btn);
            });
        });
    });
})();
</script>

<div id="chart-export-toast" role="status" aria-live="polite"
     style="display:none; position:fixed; left:50%; bottom:26px; transform:translateX(-50%);
            z-index:60; align-items:center; gap:8px; padding:9px 16px; border-radius:10px;
            font-size:12.5px; font-weight:600; background: var(--bg-card);
            border:1px solid var(--border-glass); color: var(--text-primary);
            box-shadow: 0 12px 32px -8px rgba(9,9,16,.28);"></div>
