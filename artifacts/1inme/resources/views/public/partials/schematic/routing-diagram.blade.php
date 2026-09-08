{{--
    The hero graphic: one address on the left routing to eight destination
    types on the right. Drawn on a canvas rather than shipped as an image, so
    it re-colours with the theme and weighs nothing. Replaces the stock
    photography the old hero used.
--}}
<div class="sch-diagram">
    <div class="sch-diagram-bar">
        <span class="sch-mono">Routing &middot; live</span>
        <span class="sch-mono">sayz.io/you</span>
    </div>
    <canvas id="schRouting" aria-label="Diagram showing one Sayzio address routing to eight different destination types"></canvas>
</div>

@push('scripts')
@verbatim
<script>
(function () {
  var canvas = document.getElementById('schRouting');
  if (!canvas || !canvas.getContext) return;
  var ctx = canvas.getContext('2d');
  var W = 600, H = 430;
  canvas.width = W * 2; canvas.height = H * 2;
  canvas.style.aspectRatio = W + ' / ' + H;
  ctx.scale(2, 2);

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var ENDPOINTS = ['Link in Bio', 'Short link', 'QR code', 'AI chat', 'Menu', 'Form', 'Event', 'Contact card'];
  var AI_INDEX = 3;
  var ORIGIN = { x: 54, y: H / 2 }, BUS_X = 176, END_X = 386, TOP = 46, GAP = 48;

  var paths = ENDPOINTS.map(function (name, i) {
    var y = TOP + i * GAP;
    var pts = [{ x: ORIGIN.x, y: ORIGIN.y }, { x: BUS_X, y: ORIGIN.y }, { x: BUS_X, y: y }, { x: END_X, y: y }];
    var segs = [], total = 0;
    for (var k = 1; k < pts.length; k++) {
      var d = Math.hypot(pts[k].x - pts[k - 1].x, pts[k].y - pts[k - 1].y);
      segs.push(d); total += d;
    }
    return { name: name, y: y, pts: pts, segs: segs, total: total, phase: (i * 0.115) % 1 };
  });

  function pointAt(p, t) {
    var want = t * p.total, acc = 0;
    for (var k = 0; k < p.segs.length; k++) {
      if (acc + p.segs[k] >= want) {
        var f = p.segs[k] === 0 ? 0 : (want - acc) / p.segs[k];
        var a = p.pts[k], b = p.pts[k + 1];
        return { x: a.x + (b.x - a.x) * f, y: a.y + (b.y - a.y) * f };
      }
      acc += p.segs[k];
    }
    return p.pts[p.pts.length - 1];
  }

  var C = {};
  function readTokens() {
    var cs = getComputedStyle(document.body);
    C.ink = cs.getPropertyValue('--ink').trim() || '#EDEFF5';
    C.ink3 = cs.getPropertyValue('--ink-3').trim() || '#6E7686';
    C.rule = cs.getPropertyValue('--rule').trim() || '#232836';
    C.violet = cs.getPropertyValue('--violet').trim() || '#A084FF';
    C.cyan = cs.getPropertyValue('--cyan').trim() || '#4CD8E6';
    C.surface = cs.getPropertyValue('--surface').trim() || '#11141C';
  }

  function frame(now) {
    ctx.clearRect(0, 0, W, H);

    ctx.fillStyle = C.rule; ctx.globalAlpha = 0.55;
    for (var gx = 24; gx < W; gx += 24) { for (var gy = 24; gy < H; gy += 24) { ctx.fillRect(gx, gy, 1, 1); } }
    ctx.globalAlpha = 1;

    ctx.lineWidth = 1;
    paths.forEach(function (p, i) {
      ctx.strokeStyle = i === AI_INDEX ? C.cyan : C.rule;
      ctx.globalAlpha = i === AI_INDEX ? 0.5 : 1;
      ctx.beginPath();
      ctx.moveTo(p.pts[0].x + 0.5, p.pts[0].y + 0.5);
      for (var k = 1; k < p.pts.length; k++) { ctx.lineTo(p.pts[k].x + 0.5, p.pts[k].y + 0.5); }
      ctx.stroke(); ctx.globalAlpha = 1;
    });

    ctx.font = '500 10.5px "IBM Plex Mono", monospace';
    ctx.textBaseline = 'middle';
    paths.forEach(function (p, i) {
      ctx.fillStyle = C.surface;
      ctx.strokeStyle = i === AI_INDEX ? C.cyan : C.ink;
      ctx.globalAlpha = i === AI_INDEX ? 1 : 0.5;
      ctx.beginPath(); ctx.rect(END_X - 3.5, p.y - 3.5, 7, 7); ctx.fill(); ctx.stroke();
      ctx.globalAlpha = 1;
      ctx.fillStyle = i === AI_INDEX ? C.cyan : C.ink3;
      ctx.fillText(p.name.toUpperCase(), END_X + 14, p.y + 0.5);
    });

    ctx.strokeStyle = C.violet; ctx.fillStyle = C.violet;
    ctx.beginPath(); ctx.arc(ORIGIN.x, ORIGIN.y, 5.5, 0, Math.PI * 2); ctx.fill();
    ctx.globalAlpha = 0.35;
    ctx.beginPath(); ctx.arc(ORIGIN.x, ORIGIN.y, 13.5, 0, Math.PI * 2); ctx.stroke();
    ctx.globalAlpha = 1;
    ctx.fillStyle = C.ink3; ctx.textAlign = 'center';
    ctx.fillText('ONE ADDRESS', ORIGIN.x, ORIGIN.y + 34);
    ctx.textAlign = 'left';

    if (!reduce) {
      var t = now / 4200;
      paths.forEach(function (p, i) {
        var pt = pointAt(p, (t + p.phase) % 1);
        ctx.fillStyle = i === AI_INDEX ? C.cyan : C.violet;
        ctx.globalAlpha = 0.9;
        ctx.beginPath(); ctx.arc(pt.x, pt.y, 2.4, 0, Math.PI * 2); ctx.fill();
        ctx.globalAlpha = 1;
      });
    }
  }

  function repaint() { readTokens(); frame(performance.now()); }
  window.addEventListener('inme-theme-changed', repaint);
  window.addEventListener('sch-theme-repaint', repaint);

  readTokens();
  if (reduce) { frame(0); }
  else { (function loop(now) { frame(now); requestAnimationFrame(loop); })(performance.now()); }
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(repaint); }
})();
</script>
@endverbatim
@endpush
