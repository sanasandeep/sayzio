/* QR Studio engine — custom SVG renderer with extensive shape & frame libraries.
 * Public surface (window.QrStudio):
 *   render(opts) -> { svg, width, height }
 *   toPngDataUrl(svg, w, h) -> Promise<string>
 *   DOTS, OUTER_EYES, INNER_EYES, FRAMES — id => render fn
 *   CATALOG.{dots, outerEyes, innerEyes, frames} — grouped IDs for the UI
 * Depends on global qrcode-generator (cdn) exposing window.qrcode.
 */
(function (global) {
  'use strict';

  // ---------- low-level path helpers ----------
  const fmt = (n) => {
    const r = Math.round(n * 1000) / 1000;
    return Number.isInteger(r) ? String(r) : r.toFixed(3).replace(/\.?0+$/, '');
  };
  const SQ = (x, y, w, h = w) =>
    `M${fmt(x)} ${fmt(y)}h${fmt(w)}v${fmt(h)}h${fmt(-w)}z`;
  const CIR = (cx, cy, r) =>
    `M${fmt(cx - r)} ${fmt(cy)}a${fmt(r)} ${fmt(r)} 0 1 0 ${fmt(2 * r)} 0a${fmt(r)} ${fmt(r)} 0 1 0 ${fmt(-2 * r)} 0z`;
  const RR = (x, y, w, h, r) => {
    r = Math.min(r, w / 2, h / 2);
    return `M${fmt(x + r)} ${fmt(y)}h${fmt(w - 2 * r)}a${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(r)} ${fmt(r)}v${fmt(h - 2 * r)}a${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(-r)} ${fmt(r)}h${fmt(-(w - 2 * r))}a${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(-r)} ${fmt(-r)}v${fmt(-(h - 2 * r))}a${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(r)} ${fmt(-r)}z`;
  };
  const POLY = (pts) =>
    'M' + pts.map((p, i) => (i ? 'L' : '') + fmt(p[0]) + ' ' + fmt(p[1])).join(' ') + 'z';

  // n-pointed star centered at (cx,cy)
  const STAR = (cx, cy, n, ro, ri) => {
    const pts = [];
    for (let i = 0; i < 2 * n; i++) {
      const r = i % 2 ? ri : ro;
      const a = (Math.PI * i) / n - Math.PI / 2;
      pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
    }
    return POLY(pts);
  };
  const NGON = (cx, cy, n, r, rot = 0) => {
    const pts = [];
    for (let i = 0; i < n; i++) {
      const a = (Math.PI * 2 * i) / n + rot;
      pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
    }
    return POLY(pts);
  };

  // half-circle (open semicircle, closed with chord)
  const HALF = (cx, cy, r, dir /* 'top'|'bottom'|'left'|'right' */) => {
    if (dir === 'top') return `M${fmt(cx - r)} ${fmt(cy)}a${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(2 * r)} 0z`;
    if (dir === 'bottom') return `M${fmt(cx - r)} ${fmt(cy)}a${fmt(r)} ${fmt(r)} 0 0 0 ${fmt(2 * r)} 0z`;
    if (dir === 'left') return `M${fmt(cx)} ${fmt(cy - r)}a${fmt(r)} ${fmt(r)} 0 0 0 0 ${fmt(2 * r)}z`;
    return `M${fmt(cx)} ${fmt(cy - r)}a${fmt(r)} ${fmt(r)} 0 0 1 0 ${fmt(2 * r)}z`;
  };

  // ---------- DOT shape generators ----------
  // Each: (x, y, s) => path d-string for one module
  const DOTS = {
    'square':         (x, y, s) => SQ(x, y, s),
    'dot':            (x, y, s) => CIR(x + s / 2, y + s / 2, s / 2),
    'rounded':        (x, y, s) => RR(x, y, s, s, s * 0.3),
    'rounded-lg':     (x, y, s) => RR(x, y, s, s, s * 0.45),
    'diamond':        (x, y, s) => POLY([[x + s / 2, y], [x + s, y + s / 2], [x + s / 2, y + s], [x, y + s / 2]]),
    'plus':           (x, y, s) => {
      const t = s / 3;
      return POLY([[x + t, y], [x + 2 * t, y], [x + 2 * t, y + t], [x + s, y + t], [x + s, y + 2 * t], [x + 2 * t, y + 2 * t], [x + 2 * t, y + s], [x + t, y + s], [x + t, y + 2 * t], [x, y + 2 * t], [x, y + t], [x + t, y + t]]);
    },
    'plus-thick':     (x, y, s) => {
      const t = s * 0.4, e = (s - t) / 2;
      return POLY([[x + e, y], [x + e + t, y], [x + e + t, y + e], [x + s, y + e], [x + s, y + e + t], [x + e + t, y + e + t], [x + e + t, y + s], [x + e, y + s], [x + e, y + e + t], [x, y + e + t], [x, y + e], [x + e, y + e]]);
    },
    'plus-rounded':   (x, y, s) => {
      const t = s / 3, r = t * 0.4;
      return RR(x + t, y, t, s, r) + RR(x, y + t, s, t, r);
    },
    'x-mark':         (x, y, s) => {
      const t = s * 0.28;
      const p = (a, b) => [x + s * a, y + s * b];
      return POLY([p(0, 0.2), p(0.2, 0), p(0.5, 0.3), p(0.8, 0), p(1, 0.2), p(0.7, 0.5), p(1, 0.8), p(0.8, 1), p(0.5, 0.7), p(0.2, 1), p(0, 0.8), p(0.3, 0.5)]);
    },
    'square-tilted':  (x, y, s) => POLY([[x + s / 2, y + s * 0.05], [x + s * 0.95, y + s / 2], [x + s / 2, y + s * 0.95], [x + s * 0.05, y + s / 2]]),
    'oval-h':         (x, y, s) => {
      const rx = s / 2, ry = s * 0.35;
      return `M${fmt(x)} ${fmt(y + s / 2)}a${fmt(rx)} ${fmt(ry)} 0 1 0 ${fmt(s)} 0a${fmt(rx)} ${fmt(ry)} 0 1 0 ${fmt(-s)} 0z`;
    },
    'oval-v':         (x, y, s) => {
      const rx = s * 0.35, ry = s / 2;
      return `M${fmt(x + s / 2 - rx)} ${fmt(y + s / 2)}a${fmt(rx)} ${fmt(ry)} 0 1 0 ${fmt(2 * rx)} 0a${fmt(rx)} ${fmt(ry)} 0 1 0 ${fmt(-2 * rx)} 0z`;
    },
    'pill-h':         (x, y, s) => RR(x, y + s * 0.2, s, s * 0.6, s * 0.3),
    'pill-v':         (x, y, s) => RR(x + s * 0.2, y, s * 0.6, s, s * 0.3),
    'dash-h':         (x, y, s) => SQ(x, y + s * 0.4, s, s * 0.2),
    'dash-v':         (x, y, s) => SQ(x + s * 0.4, y, s * 0.2, s),
    'square-tl':      (x, y, s) => RR(x, y, s, s, s * 0.5).replace(/$/, '') + '', // fallback below
    'square-tr':      (x, y, s) => '',
    'square-bl':      (x, y, s) => '',
    'square-br':      (x, y, s) => '',
    'square-top':     (x, y, s) => SQ(x, y, s, s * 0.55),
    'square-bottom':  (x, y, s) => SQ(x, y + s * 0.45, s, s * 0.55),
    'square-left':    (x, y, s) => SQ(x, y, s * 0.55, s),
    'square-right':   (x, y, s) => SQ(x + s * 0.45, y, s * 0.55, s),
    'half-circle-top':    (x, y, s) => HALF(x + s / 2, y + s / 2, s / 2, 'top'),
    'half-circle-bottom': (x, y, s) => HALF(x + s / 2, y + s / 2, s / 2, 'bottom'),
    'half-circle-left':   (x, y, s) => HALF(x + s / 2, y + s / 2, s / 2, 'left'),
    'half-circle-right':  (x, y, s) => HALF(x + s / 2, y + s / 2, s / 2, 'right'),
    'triangle-up':    (x, y, s) => POLY([[x + s / 2, y], [x + s, y + s], [x, y + s]]),
    'triangle-down':  (x, y, s) => POLY([[x, y], [x + s, y], [x + s / 2, y + s]]),
    'triangle-left':  (x, y, s) => POLY([[x + s, y], [x + s, y + s], [x, y + s / 2]]),
    'triangle-right': (x, y, s) => POLY([[x, y], [x, y + s], [x + s, y + s / 2]]),
    'hexagon':        (x, y, s) => NGON(x + s / 2, y + s / 2, 6, s / 2, Math.PI / 2),
    'hexagon-rotated':(x, y, s) => NGON(x + s / 2, y + s / 2, 6, s / 2, 0),
    'octagon':        (x, y, s) => NGON(x + s / 2, y + s / 2, 8, s / 2, Math.PI / 8),
    'pentagon':       (x, y, s) => NGON(x + s / 2, y + s / 2, 5, s / 2, -Math.PI / 2),
    'kite':           (x, y, s) => POLY([[x + s / 2, y], [x + s, y + s * 0.4], [x + s / 2, y + s], [x, y + s * 0.4]]),
    'parallelogram':  (x, y, s) => POLY([[x + s * 0.25, y], [x + s, y], [x + s * 0.75, y + s], [x, y + s]]),
    'chevron-right':  (x, y, s) => POLY([[x, y], [x + s * 0.6, y], [x + s, y + s / 2], [x + s * 0.6, y + s], [x, y + s], [x + s * 0.4, y + s / 2]]),
    'chevron-up':     (x, y, s) => POLY([[x + s / 2, y], [x + s, y + s * 0.4], [x + s, y + s], [x + s / 2, y + s * 0.6], [x, y + s], [x, y + s * 0.4]]),
    'star4':          (x, y, s) => STAR(x + s / 2, y + s / 2, 4, s / 2, s * 0.18),
    'star5':          (x, y, s) => STAR(x + s / 2, y + s / 2, 5, s / 2, s * 0.22),
    'star6':          (x, y, s) => STAR(x + s / 2, y + s / 2, 6, s / 2, s * 0.22),
    'star8':          (x, y, s) => STAR(x + s / 2, y + s / 2, 8, s / 2, s * 0.28),
    'heart':          (x, y, s) => {
      const w = s, h = s;
      return `M${fmt(x + w / 2)} ${fmt(y + h)}C${fmt(x)} ${fmt(y + h * 0.6)} ${fmt(x)} ${fmt(y + h * 0.15)} ${fmt(x + w * 0.5)} ${fmt(y + h * 0.3)}C${fmt(x + w)} ${fmt(y + h * 0.15)} ${fmt(x + w)} ${fmt(y + h * 0.6)} ${fmt(x + w / 2)} ${fmt(y + h)}z`;
    },
    'flower':         (x, y, s) => {
      const cx = x + s / 2, cy = y + s / 2, r = s * 0.3, p = s * 0.22;
      let d = '';
      for (let i = 0; i < 4; i++) {
        const a = (Math.PI / 2) * i;
        d += CIR(cx + Math.cos(a) * (s * 0.22), cy + Math.sin(a) * (s * 0.22), p);
      }
      return d + CIR(cx, cy, r * 0.5);
    },
    'flower6':        (x, y, s) => {
      const cx = x + s / 2, cy = y + s / 2, p = s * 0.18;
      let d = '';
      for (let i = 0; i < 6; i++) {
        const a = (Math.PI / 3) * i - Math.PI / 2;
        d += CIR(cx + Math.cos(a) * (s * 0.25), cy + Math.sin(a) * (s * 0.25), p);
      }
      return d;
    },
    'gear':           (x, y, s) => {
      const cx = x + s / 2, cy = y + s / 2, n = 8;
      const ro = s / 2, ri = s * 0.38;
      const pts = [];
      for (let i = 0; i < 2 * n; i++) {
        const r = i % 2 ? ri : ro;
        const a = (Math.PI * i) / n;
        pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
      }
      return POLY(pts) + CIR(cx, cy, s * 0.12);
    },
    'sparkle':        (x, y, s) => {
      const cx = x + s / 2, cy = y + s / 2;
      return POLY([[cx, y], [cx + s * 0.1, cy - s * 0.1], [x + s, cy], [cx + s * 0.1, cy + s * 0.1], [cx, y + s], [cx - s * 0.1, cy + s * 0.1], [x, cy], [cx - s * 0.1, cy - s * 0.1]]);
    },
    'cross-pattee':   (x, y, s) => {
      const c = s / 2, e = s * 0.15;
      return POLY([[x + c - e, y], [x + c + e, y], [x + c + e, y + c - e], [x + s, y + c - e], [x + s, y + c + e], [x + c + e, y + c + e], [x + c + e, y + s], [x + c - e, y + s], [x + c - e, y + c + e], [x, y + c + e], [x, y + c - e], [x + c - e, y + c - e]]);
    },
    'leaf':           (x, y, s) => `M${fmt(x)} ${fmt(y + s)}Q${fmt(x)} ${fmt(y)} ${fmt(x + s)} ${fmt(y)}Q${fmt(x + s)} ${fmt(y + s)} ${fmt(x)} ${fmt(y + s)}z`,
    'leaf-mirror':    (x, y, s) => `M${fmt(x + s)} ${fmt(y + s)}Q${fmt(x + s)} ${fmt(y)} ${fmt(x)} ${fmt(y)}Q${fmt(x)} ${fmt(y + s)} ${fmt(x + s)} ${fmt(y + s)}z`,
    'drop':           (x, y, s) => `M${fmt(x + s / 2)} ${fmt(y)}Q${fmt(x + s)} ${fmt(y + s / 2)} ${fmt(x + s / 2)} ${fmt(y + s)}Q${fmt(x)} ${fmt(y + s / 2)} ${fmt(x + s / 2)} ${fmt(y)}z`,
    'drop-rotated':   (x, y, s) => `M${fmt(x)} ${fmt(y + s / 2)}Q${fmt(x + s / 2)} ${fmt(y)} ${fmt(x + s)} ${fmt(y + s / 2)}Q${fmt(x + s / 2)} ${fmt(y + s)} ${fmt(x)} ${fmt(y + s / 2)}z`,
    'blob':           (x, y, s) => `M${fmt(x + s * 0.2)} ${fmt(y)}Q${fmt(x + s)} ${fmt(y)} ${fmt(x + s)} ${fmt(y + s * 0.6)}Q${fmt(x + s * 0.7)} ${fmt(y + s)} ${fmt(x)} ${fmt(y + s * 0.8)}Q${fmt(x)} ${fmt(y + s * 0.2)} ${fmt(x + s * 0.2)} ${fmt(y)}z`,
    'arrow-up':       (x, y, s) => POLY([[x + s / 2, y], [x + s, y + s * 0.5], [x + s * 0.7, y + s * 0.5], [x + s * 0.7, y + s], [x + s * 0.3, y + s], [x + s * 0.3, y + s * 0.5], [x, y + s * 0.5]]),
    'arrow-down':     (x, y, s) => POLY([[x + s * 0.3, y], [x + s * 0.7, y], [x + s * 0.7, y + s * 0.5], [x + s, y + s * 0.5], [x + s / 2, y + s], [x, y + s * 0.5], [x + s * 0.3, y + s * 0.5]]),
    'arrow-left':     (x, y, s) => POLY([[x, y + s / 2], [x + s * 0.5, y], [x + s * 0.5, y + s * 0.3], [x + s, y + s * 0.3], [x + s, y + s * 0.7], [x + s * 0.5, y + s * 0.7], [x + s * 0.5, y + s]]),
    'arrow-right':    (x, y, s) => POLY([[x, y + s * 0.3], [x + s * 0.5, y + s * 0.3], [x + s * 0.5, y], [x + s, y + s / 2], [x + s * 0.5, y + s], [x + s * 0.5, y + s * 0.7], [x, y + s * 0.7]]),
    'ring':           (x, y, s) => CIR(x + s / 2, y + s / 2, s / 2) + CIR(x + s / 2, y + s / 2, s * 0.28),
    'donut':          (x, y, s) => CIR(x + s / 2, y + s / 2, s / 2) + CIR(x + s / 2, y + s / 2, s * 0.18),
    'square-with-hole':(x, y, s) => SQ(x, y, s) + SQ(x + s * 0.3, y + s * 0.3, s * 0.4),
    'dotted-square':  (x, y, s) => {
      let d = '';
      const r = s * 0.12;
      for (let i = 0; i < 3; i++) for (let j = 0; j < 3; j++) {
        d += CIR(x + s * (0.2 + 0.3 * i), y + s * (0.2 + 0.3 * j), r);
      }
      return d;
    },
    'double-square':  (x, y, s) => SQ(x, y, s) + SQ(x + s * 0.2, y + s * 0.2, s * 0.6),
  };

  // Fix the 4 corner-rounded variants we set to '' above
  ['tl', 'tr', 'bl', 'br'].forEach((corner) => {
    DOTS['square-' + corner] = (x, y, s) => {
      const r = s * 0.5;
      // round one corner only by drawing a complex path
      const tl = corner === 'tl' ? r : 0;
      const tr = corner === 'tr' ? r : 0;
      const br = corner === 'br' ? r : 0;
      const bl = corner === 'bl' ? r : 0;
      return cornerRect(x, y, s, s, tl, tr, br, bl);
    };
  });
  function cornerRect(x, y, w, h, tl, tr, br, bl) {
    return `M${fmt(x + tl)} ${fmt(y)}` +
      `H${fmt(x + w - tr)}` + (tr ? `a${fmt(tr)} ${fmt(tr)} 0 0 1 ${fmt(tr)} ${fmt(tr)}` : '') +
      `V${fmt(y + h - br)}` + (br ? `a${fmt(br)} ${fmt(br)} 0 0 1 ${fmt(-br)} ${fmt(br)}` : '') +
      `H${fmt(x + bl)}` + (bl ? `a${fmt(bl)} ${fmt(bl)} 0 0 1 ${fmt(-bl)} ${fmt(-bl)}` : '') +
      `V${fmt(y + tl)}` + (tl ? `a${fmt(tl)} ${fmt(tl)} 0 0 1 ${fmt(tl)} ${fmt(-tl)}` : '') + 'z';
  }

  // ---------- OUTER eye (corner-square) shapes ----------
  // Each: (x, y, s) => path-d for the 7s × 7s ring (uses fill-rule="evenodd")
  // (x,y) = top-left of the 7-module region
  function eyeRing(outer, inner) { return outer + inner; }
  const OUTER = {
    'square':         (x, y, s) => eyeRing(SQ(x, y, 7 * s), SQ(x + s, y + s, 5 * s)),
    'rounded':        (x, y, s) => eyeRing(RR(x, y, 7 * s, 7 * s, s), RR(x + s, y + s, 5 * s, 5 * s, s * 0.7)),
    'extra-rounded':  (x, y, s) => eyeRing(RR(x, y, 7 * s, 7 * s, s * 2), RR(x + s, y + s, 5 * s, 5 * s, s * 1.4)),
    'dot':            (x, y, s) => eyeRing(CIR(x + 3.5 * s, y + 3.5 * s, 3.5 * s), CIR(x + 3.5 * s, y + 3.5 * s, 2.5 * s)),
    'soft-square':    (x, y, s) => eyeRing(RR(x, y, 7 * s, 7 * s, s * 0.6), SQ(x + s, y + s, 5 * s)),
    'beveled-square': (x, y, s) => {
      const o = POLY([[x + s, y], [x + 6 * s, y], [x + 7 * s, y + s], [x + 7 * s, y + 6 * s], [x + 6 * s, y + 7 * s], [x + s, y + 7 * s], [x, y + 6 * s], [x, y + s]]);
      const i = POLY([[x + 2 * s, y + s], [x + 5 * s, y + s], [x + 6 * s, y + 2 * s], [x + 6 * s, y + 5 * s], [x + 5 * s, y + 6 * s], [x + 2 * s, y + 6 * s], [x + s, y + 5 * s], [x + s, y + 2 * s]]);
      return eyeRing(o, i);
    },
    'double-square':  (x, y, s) => SQ(x, y, 7 * s) + SQ(x + s * 0.5, y + s * 0.5, 6 * s) + SQ(x + s, y + s, 5 * s) + SQ(x + s * 1.5, y + s * 1.5, 4 * s),
    'double-circle':  (x, y, s) => CIR(x + 3.5 * s, y + 3.5 * s, 3.5 * s) + CIR(x + 3.5 * s, y + 3.5 * s, 3 * s) + CIR(x + 3.5 * s, y + 3.5 * s, 2.5 * s) + CIR(x + 3.5 * s, y + 3.5 * s, 2 * s),
    'leaf-tl':        (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 4 * s, s, 4 * s, s), cornerRect(x + s, y + s, 5 * s, 5 * s, 3 * s, s * 0.7, 3 * s, s * 0.7)),
    'leaf-tr':        (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, s, 4 * s, s, 4 * s), cornerRect(x + s, y + s, 5 * s, 5 * s, s * 0.7, 3 * s, s * 0.7, 3 * s)),
    'leaf-bl':        (x, y, s) => OUTER['leaf-tr'](x, y, s),
    'leaf-br':        (x, y, s) => OUTER['leaf-tl'](x, y, s),
    'rounded-tl':     (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 3 * s, s, s, s), cornerRect(x + s, y + s, 5 * s, 5 * s, 2 * s, s * 0.7, s * 0.7, s * 0.7)),
    'rounded-tr':     (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, s, 3 * s, s, s), cornerRect(x + s, y + s, 5 * s, 5 * s, s * 0.7, 2 * s, s * 0.7, s * 0.7)),
    'rounded-bl':     (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, s, s, s, 3 * s), cornerRect(x + s, y + s, 5 * s, 5 * s, s * 0.7, s * 0.7, s * 0.7, 2 * s)),
    'rounded-br':     (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, s, s, 3 * s, s), cornerRect(x + s, y + s, 5 * s, 5 * s, s * 0.7, s * 0.7, 2 * s, s * 0.7)),
    'rounded-tl-br':  (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 3 * s, s, 3 * s, s), cornerRect(x + s, y + s, 5 * s, 5 * s, 2 * s, s * 0.7, 2 * s, s * 0.7)),
    'rounded-tr-bl':  (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, s, 3 * s, s, 3 * s), cornerRect(x + s, y + s, 5 * s, 5 * s, s * 0.7, 2 * s, s * 0.7, 2 * s)),
    'cut-tl':         (x, y, s) => eyeRing(POLY([[x + 2 * s, y], [x + 7 * s, y], [x + 7 * s, y + 7 * s], [x, y + 7 * s], [x, y + 2 * s]]), POLY([[x + 2.4 * s, y + s], [x + 6 * s, y + s], [x + 6 * s, y + 6 * s], [x + s, y + 6 * s], [x + s, y + 2.4 * s]])),
    'cut-tr':         (x, y, s) => eyeRing(POLY([[x, y], [x + 5 * s, y], [x + 7 * s, y + 2 * s], [x + 7 * s, y + 7 * s], [x, y + 7 * s]]), POLY([[x + s, y + s], [x + 4.6 * s, y + s], [x + 6 * s, y + 2.4 * s], [x + 6 * s, y + 6 * s], [x + s, y + 6 * s]])),
    'cut-bl':         (x, y, s) => eyeRing(POLY([[x, y], [x + 7 * s, y], [x + 7 * s, y + 7 * s], [x + 2 * s, y + 7 * s], [x, y + 5 * s]]), POLY([[x + s, y + s], [x + 6 * s, y + s], [x + 6 * s, y + 6 * s], [x + 2.4 * s, y + 6 * s], [x + s, y + 4.6 * s]])),
    'cut-br':         (x, y, s) => eyeRing(POLY([[x, y], [x + 7 * s, y], [x + 7 * s, y + 5 * s], [x + 5 * s, y + 7 * s], [x, y + 7 * s]]), POLY([[x + s, y + s], [x + 6 * s, y + s], [x + 6 * s, y + 4.6 * s], [x + 4.6 * s, y + 6 * s], [x + s, y + 6 * s]])),
    'hexagon':        (x, y, s) => eyeRing(NGON(x + 3.5 * s, y + 3.5 * s, 6, 3.5 * s, Math.PI / 2), NGON(x + 3.5 * s, y + 3.5 * s, 6, 2.5 * s, Math.PI / 2)),
    'octagon':        (x, y, s) => eyeRing(NGON(x + 3.5 * s, y + 3.5 * s, 8, 3.5 * s, Math.PI / 8), NGON(x + 3.5 * s, y + 3.5 * s, 8, 2.5 * s, Math.PI / 8)),
    'diamond':        (x, y, s) => eyeRing(POLY([[x + 3.5 * s, y], [x + 7 * s, y + 3.5 * s], [x + 3.5 * s, y + 7 * s], [x, y + 3.5 * s]]), POLY([[x + 3.5 * s, y + s], [x + 6 * s, y + 3.5 * s], [x + 3.5 * s, y + 6 * s], [x + s, y + 3.5 * s]])),
    'triangle':       (x, y, s) => eyeRing(POLY([[x + 3.5 * s, y], [x + 7 * s, y + 7 * s], [x, y + 7 * s]]), POLY([[x + 3.5 * s, y + 1.5 * s], [x + 5.7 * s, y + 6 * s], [x + 1.3 * s, y + 6 * s]])),
    'pentagon':       (x, y, s) => eyeRing(NGON(x + 3.5 * s, y + 3.5 * s, 5, 3.5 * s, -Math.PI / 2), NGON(x + 3.5 * s, y + 3.5 * s, 5, 2.5 * s, -Math.PI / 2)),
    'star':           (x, y, s) => eyeRing(STAR(x + 3.5 * s, y + 3.5 * s, 8, 3.5 * s, 2.4 * s), STAR(x + 3.5 * s, y + 3.5 * s, 8, 2.4 * s, 1.7 * s)),
    'plus-frame':     (x, y, s) => {
      const t = 2 * s;
      const o = POLY([[x + t, y], [x + 7 * s - t, y], [x + 7 * s - t, y + t], [x + 7 * s, y + t], [x + 7 * s, y + 7 * s - t], [x + 7 * s - t, y + 7 * s - t], [x + 7 * s - t, y + 7 * s], [x + t, y + 7 * s], [x + t, y + 7 * s - t], [x, y + 7 * s - t], [x, y + t], [x + t, y + t]]);
      const i = POLY([[x + t + s, y + s], [x + 7 * s - t - s, y + s], [x + 7 * s - t - s, y + t + s], [x + 7 * s - s, y + t + s], [x + 7 * s - s, y + 7 * s - t - s], [x + 7 * s - t - s, y + 7 * s - t - s], [x + 7 * s - t - s, y + 7 * s - s], [x + t + s, y + 7 * s - s], [x + t + s, y + 7 * s - t - s], [x + s, y + 7 * s - t - s], [x + s, y + t + s], [x + t + s, y + t + s]]);
      return eyeRing(o, i);
    },
    'x-frame':        (x, y, s) => OUTER['rounded-tl-br'](x, y, s),
    'shield':         (x, y, s) => eyeRing(`M${fmt(x)} ${fmt(y)}H${fmt(x + 7 * s)}V${fmt(y + 4 * s)}Q${fmt(x + 7 * s)} ${fmt(y + 7 * s)} ${fmt(x + 3.5 * s)} ${fmt(y + 7 * s)}Q${fmt(x)} ${fmt(y + 7 * s)} ${fmt(x)} ${fmt(y + 4 * s)}z`, `M${fmt(x + s)} ${fmt(y + s)}H${fmt(x + 6 * s)}V${fmt(y + 4 * s)}Q${fmt(x + 6 * s)} ${fmt(y + 6 * s)} ${fmt(x + 3.5 * s)} ${fmt(y + 6 * s)}Q${fmt(x + s)} ${fmt(y + 6 * s)} ${fmt(x + s)} ${fmt(y + 4 * s)}z`),
    'badge':          (x, y, s) => eyeRing(STAR(x + 3.5 * s, y + 3.5 * s, 12, 3.5 * s, 3 * s), STAR(x + 3.5 * s, y + 3.5 * s, 12, 2.5 * s, 2.1 * s)),
    'ticket':         (x, y, s) => OUTER['cut-tl'](x, y, s),
    'plaque':         (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 2 * s, 2 * s, 2 * s, 2 * s), cornerRect(x + s, y + s, 5 * s, 5 * s, s * 1.3, s * 1.3, s * 1.3, s * 1.3)),
    'flower':         (x, y, s) => {
      const cx = x + 3.5 * s, cy = y + 3.5 * s;
      let o = '';
      for (let i = 0; i < 6; i++) {
        const a = (Math.PI / 3) * i;
        o += CIR(cx + Math.cos(a) * 2 * s, cy + Math.sin(a) * 2 * s, 1.6 * s);
      }
      return o + CIR(cx, cy, 2.5 * s);
    },
    'gear':           (x, y, s) => {
      const cx = x + 3.5 * s, cy = y + 3.5 * s, n = 10;
      const ro = 3.5 * s, ri = 2.8 * s;
      const pts = [];
      for (let i = 0; i < 2 * n; i++) {
        const r = i % 2 ? ri : ro;
        const a = (Math.PI * i) / n;
        pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
      }
      return eyeRing(POLY(pts), CIR(cx, cy, 2 * s));
    },
    'sparkle-frame':  (x, y, s) => eyeRing(STAR(x + 3.5 * s, y + 3.5 * s, 4, 3.5 * s, 1.5 * s), STAR(x + 3.5 * s, y + 3.5 * s, 4, 2.5 * s, s)),
    'heart-frame':    (x, y, s) => {
      const w = 7 * s;
      const heart = (cx, cy, ww, off) => `M${fmt(cx)} ${fmt(cy + ww * 0.45)}C${fmt(cx - ww / 2)} ${fmt(cy + ww * 0.1)} ${fmt(cx - ww / 2)} ${fmt(cy - ww * 0.3 + off)} ${fmt(cx)} ${fmt(cy - ww * 0.15 + off)}C${fmt(cx + ww / 2)} ${fmt(cy - ww * 0.3 + off)} ${fmt(cx + ww / 2)} ${fmt(cy + ww * 0.1)} ${fmt(cx)} ${fmt(cy + ww * 0.45)}z`;
      return eyeRing(heart(x + 3.5 * s, y + 3 * s, w, 0), heart(x + 3.5 * s, y + 3.2 * s, w * 0.7, s * 0.3));
    },
    'ribbon-frame':   (x, y, s) => eyeRing(POLY([[x, y + s], [x + 7 * s, y + s], [x + 7 * s, y + 6 * s], [x + 5 * s, y + 7 * s], [x + 2 * s, y + 7 * s], [x, y + 6 * s]]), POLY([[x + s, y + 1.7 * s], [x + 6 * s, y + 1.7 * s], [x + 6 * s, y + 5.5 * s], [x + 4.5 * s, y + 6.3 * s], [x + 2.5 * s, y + 6.3 * s], [x + s, y + 5.5 * s]])),
    'dotted-frame':   (x, y, s) => {
      let d = '';
      const positions = [];
      for (let i = 0; i < 7; i++) { positions.push([i, 0]); positions.push([i, 6]); }
      for (let i = 1; i < 6; i++) { positions.push([0, i]); positions.push([6, i]); }
      positions.forEach((p) => { d += CIR(x + (p[0] + 0.5) * s, y + (p[1] + 0.5) * s, s * 0.4); });
      return d;
    },
    'dashed-frame':   (x, y, s) => {
      let d = '';
      for (let i = 0; i < 7; i += 2) {
        d += SQ(x + i * s, y, s, s);
        d += SQ(x + i * s, y + 6 * s, s, s);
      }
      for (let i = 2; i < 6; i += 2) {
        d += SQ(x, y + i * s, s, s);
        d += SQ(x + 6 * s, y + i * s, s, s);
      }
      return d;
    },
    'double-line':    (x, y, s) => SQ(x, y, 7 * s) + SQ(x + s * 0.4, y + s * 0.4, 6.2 * s) + SQ(x + s * 0.8, y + s * 0.8, 5.4 * s) + SQ(x + s, y + s, 5 * s),
    'thick-circle':   (x, y, s) => eyeRing(CIR(x + 3.5 * s, y + 3.5 * s, 3.5 * s), CIR(x + 3.5 * s, y + 3.5 * s, 2 * s)),
    'thick-square':   (x, y, s) => eyeRing(SQ(x, y, 7 * s), SQ(x + 1.5 * s, y + 1.5 * s, 4 * s)),
    'soft-rounded':   (x, y, s) => eyeRing(RR(x, y, 7 * s, 7 * s, s * 1.5), CIR(x + 3.5 * s, y + 3.5 * s, 2.4 * s)),
    'rounded-pill-h': (x, y, s) => eyeRing(RR(x, y, 7 * s, 7 * s, 3.5 * s), RR(x + s, y + s, 5 * s, 5 * s, 2.5 * s)),
    'rounded-pill-v': (x, y, s) => OUTER['rounded-pill-h'](x, y, s),
    'half-rounded-top':    (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 3.5 * s, 3.5 * s, 0, 0), cornerRect(x + s, y + s, 5 * s, 5 * s, 2.5 * s, 2.5 * s, 0, 0)),
    'half-rounded-bottom': (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 0, 0, 3.5 * s, 3.5 * s), cornerRect(x + s, y + s, 5 * s, 5 * s, 0, 0, 2.5 * s, 2.5 * s)),
    'half-rounded-left':   (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 3.5 * s, 0, 0, 3.5 * s), cornerRect(x + s, y + s, 5 * s, 5 * s, 2.5 * s, 0, 0, 2.5 * s)),
    'half-rounded-right':  (x, y, s) => eyeRing(cornerRect(x, y, 7 * s, 7 * s, 0, 3.5 * s, 3.5 * s, 0), cornerRect(x + s, y + s, 5 * s, 5 * s, 0, 2.5 * s, 2.5 * s, 0)),
  };

  // ---------- INNER eye (corner-dot) shapes ----------
  // Each: (x, y, s) => path-d for the 3s × 3s center dot
  // Reuse most DOT generators scaled to 3s
  const INNER = {};
  const reuseAsInner = [
    'dot', 'square', 'rounded', 'extra-rounded', 'diamond', 'oval-h', 'oval-v', 'pill-h', 'pill-v',
    'square-tl', 'square-tr', 'square-bl', 'square-br',
    'half-circle-top', 'half-circle-bottom', 'half-circle-left', 'half-circle-right',
    'triangle-up', 'triangle-down', 'triangle-left', 'triangle-right',
    'hexagon', 'octagon', 'pentagon', 'kite', 'chevron-right', 'chevron-up', 'parallelogram',
    'star4', 'star5', 'star6', 'star8', 'heart', 'plus', 'x', 'flower', 'gear', 'sparkle', 'cross-pattee',
    'leaf', 'leaf-mirror', 'drop', 'drop-rotated', 'blob', 'arrow-up', 'arrow-right',
    'ring', 'donut', 'square-with-hole', 'plus-thick', 'double-dot', 'double-square',
  ];
  reuseAsInner.forEach((id) => {
    let mapped = id;
    if (id === 'extra-rounded') mapped = 'rounded-lg';
    if (id === 'x') mapped = 'x-mark';
    if (id === 'double-dot') mapped = 'dotted-square';
    const fn = DOTS[mapped];
    if (fn) INNER[id] = (x, y, s) => fn(x, y, 3 * s);
  });

  // ---------- helpers for eyes mask ----------
  // Returns true if module (r,c) is inside any of the 3 finder patterns (the full 7x7 region)
  function isInFinder(r, c, n) {
    return (r < 7 && c < 7) || (r < 7 && c >= n - 7) || (r >= n - 7 && c < 7);
  }

  // ---------- two-stage renderer ----------
  // Stage 1: only depends on `data` and `errorCorrection`. Returns the raw
  // module bitmap so callers (e.g. interactive builders) can cache it and
  // skip QR encoding when only decorative options change.
  function buildMatrix(opts) {
    const { data, errorCorrection = 'M' } = opts || {};
    const qr = global.qrcode(0, errorCorrection);
    qr.addData(data || ' ');
    qr.make();
    const n = qr.getModuleCount();
    const modules = new Uint8Array(n * n);
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (qr.isDark(r, c)) modules[r * n + c] = 1;
      }
    }
    return { n, modules, data: data || ' ', errorCorrection };
  }

  // Stage 2: render the SVG given a matrix produced by buildMatrix() and the
  // current decoration options (shapes, colors, gradients, logos, frame).
  function renderFromMatrix(matrix, opts) {
    const {
      modulePx = 10,
      margin = 4,
      dotShape = 'rounded',
      outerEyeShape = 'extra-rounded',
      innerEyeShape = 'dot',
      fgColor = '#000000',
      bgColor = '#ffffff',
      transparentBg = false,
      cornerSquareColor,
      cornerDotColor,
      gradient,         // {enabled, type:'linear'|'radial', from, to, angle}
      eyeOuterGradient, // same shape
      eyeInnerGradient,
      bgGradient,       // {enabled, type, from, to, angle}
      logos = { background: null, center: null, foreground: null },
      hideDotsBehindLogo = true,
      qrRotation = 0,
      dropShadow = false,
      frame = { template: 'none' },
      fontFamily = 'Inter',
    } = opts;

    const n = matrix.n;
    const modules = matrix.modules;
    const isDark = (r, c) => modules[r * n + c] === 1;
    const innerSize = (n + 2 * margin) * modulePx;

    // Compute logo bbox in module coords (for hideDotsBehindLogo)
    const centerLogoBox = (() => {
      const c = logos.center;
      if (!c || !c.url || !c.show || !hideDotsBehindLogo) return null;
      const sizePct = Math.max(0.05, Math.min(0.5, c.size || 0.25));
      const w = sizePct * innerSize;
      const cx = innerSize * ((c.x ?? 50) / 100);
      const cy = innerSize * ((c.y ?? 50) / 100);
      const x0 = cx - w / 2, y0 = cy - w / 2, x1 = cx + w / 2, y1 = cy + w / 2;
      return { x0, y0, x1, y1 };
    })();

    // Build module path (skip finder cells)
    let dotPath = '';
    const dotFn = DOTS[dotShape] || DOTS['square'];
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (!isDark(r, c)) continue;
        if (isInFinder(r, c, n)) continue;
        const x = (c + margin) * modulePx;
        const y = (r + margin) * modulePx;
        if (centerLogoBox) {
          const cx = x + modulePx / 2, cy = y + modulePx / 2;
          if (cx >= centerLogoBox.x0 && cx <= centerLogoBox.x1 &&
              cy >= centerLogoBox.y0 && cy <= centerLogoBox.y1) continue;
        }
        dotPath += dotFn(x, y, modulePx);
      }
    }

    // Eyes: 3 corner regions
    const outerFn = OUTER[outerEyeShape] || OUTER['square'];
    const innerFn = INNER[innerEyeShape] || INNER['square'];
    const eyeCorners = [
      [margin, margin],                       // TL (col, row)
      [n - 7 + margin, margin],               // TR
      [margin, n - 7 + margin],               // BL
    ];
    let outerPath = '';
    let innerPath = '';
    eyeCorners.forEach(([cm, rm]) => {
      const ex = cm * modulePx, ey = rm * modulePx;
      outerPath += outerFn(ex, ey, modulePx);
      innerPath += innerFn(ex + 2 * modulePx, ey + 2 * modulePx, modulePx);
    });

    const defs = [];
    const grad = (g, idPrefix, vbox) => {
      if (!g || !g.enabled) return null;
      const id = idPrefix + '_' + Math.random().toString(36).slice(2, 8);
      const angle = g.angle ?? 0;
      const rad = (angle * Math.PI) / 180;
      const x1 = 50 + Math.cos(rad + Math.PI) * 50;
      const y1 = 50 + Math.sin(rad + Math.PI) * 50;
      const x2 = 50 + Math.cos(rad) * 50;
      const y2 = 50 + Math.sin(rad) * 50;
      if (g.type === 'radial') {
        defs.push(`<radialGradient id="${id}" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="${g.from}"/><stop offset="100%" stop-color="${g.to}"/></radialGradient>`);
      } else {
        defs.push(`<linearGradient id="${id}" x1="${x1}%" y1="${y1}%" x2="${x2}%" y2="${y2}%"><stop offset="0%" stop-color="${g.from}"/><stop offset="100%" stop-color="${g.to}"/></linearGradient>`);
      }
      return 'url(#' + id + ')';
    };

    const dotFill = grad(gradient, 'g_dots') || fgColor;
    const outerFill = grad(eyeOuterGradient, 'g_oeye') || cornerSquareColor || fgColor;
    const innerFill = grad(eyeInnerGradient, 'g_ieye') || cornerDotColor || fgColor;
    const bgFill = transparentBg ? 'transparent' : (grad(bgGradient, 'g_bg') || bgColor);

    const filterDef = dropShadow
      ? `<filter id="qrshadow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="${modulePx * 0.15}" stdDeviation="${modulePx * 0.2}" flood-opacity="0.35"/></filter>`
      : '';

    // Background image layer
    const bgImg = imageLayer(logos.background, innerSize);
    const fgImg = imageLayer(logos.foreground, innerSize);
    const ctImg = imageLayer(logos.center, innerSize);

    const rotateAttr = qrRotation ? ` transform="rotate(${qrRotation} ${innerSize / 2} ${innerSize / 2})"` : '';
    const shadowAttr = dropShadow ? ' filter="url(#qrshadow)"' : '';

    const innerSvg =
      `<g${rotateAttr}>` +
      (transparentBg ? '' : `<rect width="${innerSize}" height="${innerSize}" fill="${bgFill}"/>`) +
      bgImg +
      `<g${shadowAttr}>` +
        `<path d="${dotPath}" fill="${dotFill}"/>` +
        `<path d="${outerPath}" fill="${outerFill}" fill-rule="evenodd"/>` +
        `<path d="${innerPath}" fill="${innerFill}"/>` +
      `</g>` +
      ctImg +
      fgImg +
      `</g>`;

    // Apply frame
    const frameImpl = FRAMES[frame.template] || FRAMES['none'];
    const composed = frameImpl({ qrSvgInner: innerSvg, qrSize: innerSize, frame, fontFamily, defs });

    const fullDefs = (composed.defs || defs).concat(filterDef ? [filterDef] : []);
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ${composed.width} ${composed.height}" width="${composed.width}" height="${composed.height}"><defs>${fullDefs.join('')}</defs>${composed.body}</svg>`;
    return { svg, width: composed.width, height: composed.height };
  }

  // Convenience wrapper: build matrix + render in one call. Preserves the
  // original public render(opts) surface for callers that don't need caching.
  function renderQR(opts) {
    return renderFromMatrix(buildMatrix(opts), opts);
  }

  // logoCache: original URL -> resolved data URL (so SVG/PNG exports are
  // self-contained and PNG export doesn't taint the canvas with cross-origin
  // pixels). Populated by preloadLogos() and consumed by imageLayer().
  const logoCache = new Map();
  function _resolveLogoSrc(url) {
    if (!url) return url;
    if (typeof url === 'string' && url.startsWith('data:')) return url;
    return logoCache.get(url) || url;
  }
  function _fetchAsDataUrl(url) {
    return fetch(url, { mode: 'cors', credentials: 'omit' })
      .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.blob(); })
      .then(blob => new Promise((resolve, reject) => {
        const fr = new FileReader();
        fr.onload = () => resolve(fr.result);
        fr.onerror = () => reject(fr.error);
        fr.readAsDataURL(blob);
      }));
  }
  // Walk the logos object on opts and ensure every visible URL is cached as a
  // data URL. Returns an object reporting any URLs that failed to load so the
  // caller can surface a user-facing error if desired. Always resolves.
  async function preloadLogos(opts) {
    const errors = {};
    const logos = (opts && opts.logos) || {};
    const tasks = [];
    for (const slot of ['background', 'center', 'foreground']) {
      const l = logos[slot];
      if (!l || !l.url || !l.show) continue;
      if (typeof l.url !== 'string' || l.url.startsWith('data:')) continue;
      if (logoCache.has(l.url)) continue;
      const url = l.url;
      tasks.push(_fetchAsDataUrl(url).then(
        dataUrl => { logoCache.set(url, dataUrl); },
        err => { errors[slot] = String(err && err.message || err); logoCache.set(url, url); }
      ));
    }
    await Promise.all(tasks);
    return { ok: Object.keys(errors).length === 0, errors };
  }

  function imageLayer(logo, qrSize) {
    if (!logo || !logo.url || !logo.show) return '';
    const sizePct = Math.max(0.02, Math.min(1, logo.size || 0.25));
    const w = sizePct * qrSize;
    const h = w;
    const cx = qrSize * ((logo.x ?? 50) / 100);
    const cy = qrSize * ((logo.y ?? 50) / 100);
    const x = cx - w / 2, y = cy - h / 2;
    const opacity = logo.opacity ?? 1;
    const rotation = logo.rotation || 0;
    const transform = rotation ? ` transform="rotate(${rotation} ${cx} ${cy})"` : '';
    const src = _resolveLogoSrc(logo.url);
    // xlink:href for compatibility, href for modern
    return `<image href="${escAttr(src)}" xlink:href="${escAttr(src)}" x="${fmt(x)}" y="${fmt(y)}" width="${fmt(w)}" height="${fmt(h)}" opacity="${opacity}" preserveAspectRatio="xMidYMid meet"${transform}/>`;
  }
  function escAttr(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
  function escText(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  // ---------- FRAME library ----------
  // Each frame: ({qrSvgInner, qrSize, frame, fontFamily, defs}) -> {width, height, body, defs?}
  function frameNone({ qrSvgInner, qrSize }) {
    return { width: qrSize, height: qrSize, body: qrSvgInner };
  }

  function bar({ side = 'bottom', radius = 0, padding = 16, height = 64, shape = 'rect', barRadius = 0, padTop = 16 } = {}) {
    return ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const W = qrSize + 2 * padding;
      const barH = height;
      const totalH = qrSize + 2 * padding + barH + padTop;
      const qrX = padding, qrY = side === 'top' ? padding + barH + padTop : padding;
      const barY = side === 'top' ? padding : qrSize + padding + padTop;
      let barShape = '';
      if (shape === 'pill') barShape = `<rect x="${padding}" y="${barY}" width="${qrSize}" height="${barH}" rx="${barH / 2}" fill="${frame.bg_color}"/>`;
      else if (shape === 'rounded') barShape = `<rect x="${padding}" y="${barY}" width="${qrSize}" height="${barH}" rx="${barRadius || 12}" fill="${frame.bg_color}"/>`;
      else if (shape === 'double') barShape = `<rect x="${padding - 4}" y="${barY - 4}" width="${qrSize + 8}" height="${barH + 8}" fill="${frame.bg_color}"/><rect x="${padding}" y="${barY}" width="${qrSize}" height="${barH}" fill="${frame.text_color}"/>`;
      else barShape = `<rect x="${padding}" y="${barY}" width="${qrSize}" height="${barH}" fill="${frame.bg_color}"/>`;
      const cardR = radius || 0;
      const card = cardR ? `<rect x="0" y="0" width="${W}" height="${totalH}" rx="${cardR}" fill="${frame.bg_color}" opacity="0.0"/>` : '';
      const textColor = shape === 'double' ? frame.bg_color : frame.text_color;
      const textY = barY + barH / 2 + (barH * 0.18);
      const text = `<text x="${W / 2}" y="${textY}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="${barH * 0.45}" fill="${textColor}">${escText(frame.text || '')}</text>`;
      const body = `${card}${barShape}${text}<g transform="translate(${qrX} ${qrY})">${qrSvgInner}</g>`;
      return { width: W, height: totalH, body };
    };
  }

  // Bubble with tail
  function bubble({ direction = 'down', padding = 20, tail = 30 } = {}) {
    return ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const W = qrSize + 2 * padding;
      const H = qrSize + 2 * padding;
      let totalW = W, totalH = H, qrX = padding, qrY = padding, body = '';
      const r = 24;
      const path = (() => {
        if (direction === 'down') {
          totalH = H + tail + 30;
          const ty = H;
          return `M${r} 0H${W - r}A${r} ${r} 0 0 1 ${W} ${r}V${H - r}A${r} ${r} 0 0 1 ${W - r} ${H}H${W / 2 + 20}L${W / 2} ${ty + tail}L${W / 2 - 20} ${H}H${r}A${r} ${r} 0 0 1 0 ${H - r}V${r}A${r} ${r} 0 0 1 ${r} 0z`;
        }
        if (direction === 'up') {
          totalH = H + tail + 30;
          qrY = padding + tail + 30;
          return `M${r} ${tail + 30}H${W / 2 - 20}L${W / 2} 0L${W / 2 + 20} ${tail + 30}H${W - r}A${r} ${r} 0 0 1 ${W} ${tail + 30 + r}V${tail + 30 + H - r}A${r} ${r} 0 0 1 ${W - r} ${tail + 30 + H}H${r}A${r} ${r} 0 0 1 0 ${tail + 30 + H - r}V${tail + 30 + r}A${r} ${r} 0 0 1 ${r} ${tail + 30}z`;
        }
        if (direction === 'left') {
          totalW = W + tail + 20;
          qrX = padding + tail + 20;
          return `M${tail + 20 + r} 0H${tail + 20 + W - r}A${r} ${r} 0 0 1 ${tail + 20 + W} ${r}V${H - r}A${r} ${r} 0 0 1 ${tail + 20 + W - r} ${H}H${tail + 20 + r}A${r} ${r} 0 0 1 ${tail + 20} ${H - r}V${H / 2 + 20}L0 ${H / 2}L${tail + 20} ${H / 2 - 20}V${r}A${r} ${r} 0 0 1 ${tail + 20 + r} 0z`;
        }
        // right
        totalW = W + tail + 20;
        return `M${r} 0H${W - r}A${r} ${r} 0 0 1 ${W} ${r}V${H / 2 - 20}L${W + tail + 20} ${H / 2}L${W} ${H / 2 + 20}V${H - r}A${r} ${r} 0 0 1 ${W - r} ${H}H${r}A${r} ${r} 0 0 1 0 ${H - r}V${r}A${r} ${r} 0 0 1 ${r} 0z`;
      })();
      body = `<path d="${path}" fill="${frame.bg_color}"/><g transform="translate(${qrX} ${qrY})">${qrSvgInner}</g>`;
      // text below if direction!=down else inside tail
      const textY = direction === 'down' ? totalH - 4 : (direction === 'up' ? 22 : totalH / 2 + 6);
      const textX = direction === 'left' ? 14 : direction === 'right' ? totalW - 14 : totalW / 2;
      const anchor = direction === 'left' ? 'start' : direction === 'right' ? 'end' : 'middle';
      const text = `<text x="${textX}" y="${textY}" text-anchor="${anchor}" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: totalW, height: totalH, body: body + text };
    };
  }

  // Border-only frames
  function bordered({ stroke = 6, dash = 0, gap = 12, radius = 12, double = false, corners = false } = {}) {
    return ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = gap + stroke;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + 36;
      const dashAttr = dash ? ` stroke-dasharray="${dash}"` : '';
      let borders = '';
      if (corners) {
        const c = 30, x = stroke / 2, y = stroke / 2, w = W - stroke, h = qrSize + 2 * pad - stroke;
        borders = `<g stroke="${frame.bg_color}" stroke-width="${stroke}" fill="none">
          <path d="M${x + c} ${y}H${x}V${y + c}"/>
          <path d="M${x + w - c} ${y}H${x + w}V${y + c}"/>
          <path d="M${x} ${y + h - c}V${y + h}H${x + c}"/>
          <path d="M${x + w} ${y + h - c}V${y + h}H${x + w - c}"/>
        </g>`;
      } else {
        borders = `<rect x="${stroke / 2}" y="${stroke / 2}" width="${W - stroke}" height="${qrSize + 2 * pad - stroke}" rx="${radius}" fill="none" stroke="${frame.bg_color}" stroke-width="${stroke}"${dashAttr}/>`;
        if (double) borders += `<rect x="${stroke / 2 + 6}" y="${stroke / 2 + 6}" width="${W - stroke - 12}" height="${qrSize + 2 * pad - stroke - 12}" rx="${Math.max(0, radius - 4)}" fill="none" stroke="${frame.bg_color}" stroke-width="${stroke / 2}"/>`;
      }
      const text = `<text x="${W / 2}" y="${H - 10}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      const body = `${borders}<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>${text}`;
      return { width: W, height: H, body };
    };
  }

  // Polygon frame (diamond/hex/octagon) wrapper
  function polyFrame(makeOutline) {
    return ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 50;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + 36;
      const outline = makeOutline(W, qrSize + 2 * pad, frame);
      const text = `<text x="${W / 2}" y="${H - 10}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      const body = `${outline}<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>${text}`;
      return { width: W, height: H, body };
    };
  }

  // Arrow callout frames (large directional arrow)
  function arrowFrame(dir) {
    return ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 24, arrow = 60;
      const W = qrSize + 2 * pad;
      const H = qrSize + 2 * pad + arrow + 20;
      const qrX = pad, qrY = (dir === 'down' ? pad : pad + arrow + 20);
      const arrowY = dir === 'down' ? qrSize + pad + 10 : 0;
      const tipY = dir === 'down' ? arrowY + arrow : arrow + 10;
      let arrowPath;
      if (dir === 'down') {
        arrowPath = `M${W / 2 - 30} ${arrowY}H${W / 2 + 30}L${W / 2 + 30} ${arrowY + arrow * 0.4}L${W / 2 + 50} ${arrowY + arrow * 0.4}L${W / 2} ${arrowY + arrow}L${W / 2 - 50} ${arrowY + arrow * 0.4}L${W / 2 - 30} ${arrowY + arrow * 0.4}z`;
      } else if (dir === 'up') {
        arrowPath = `M${W / 2 + 30} ${arrow + 10}H${W / 2 - 30}L${W / 2 - 30} ${arrow * 0.6 + 10}L${W / 2 - 50} ${arrow * 0.6 + 10}L${W / 2} 10L${W / 2 + 50} ${arrow * 0.6 + 10}L${W / 2 + 30} ${arrow * 0.6 + 10}z`;
      } else if (dir === 'left') {
        arrowPath = `M${arrow + 10} ${H / 2 - 30}V${H / 2 + 30}L${arrow * 0.6 + 10} ${H / 2 + 30}L${arrow * 0.6 + 10} ${H / 2 + 50}L10 ${H / 2}L${arrow * 0.6 + 10} ${H / 2 - 50}L${arrow * 0.6 + 10} ${H / 2 - 30}z`;
        return { width: W + arrow + 20, height: qrSize + 2 * pad, body: `<path d="${arrowPath}" fill="${frame.bg_color}"/><g transform="translate(${arrow + 20 + pad} ${pad})">${qrSvgInner}</g>` };
      } else {
        arrowPath = `M${W} ${H / 2 - 30}V${H / 2 + 30}L${W + arrow * 0.4} ${H / 2 + 30}L${W + arrow * 0.4} ${H / 2 + 50}L${W + arrow + 10} ${H / 2}L${W + arrow * 0.4} ${H / 2 - 50}L${W + arrow * 0.4} ${H / 2 - 30}z`;
        return { width: W + arrow + 20, height: qrSize + 2 * pad, body: `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g><path d="${arrowPath}" fill="${frame.bg_color}"/>` };
      }
      const text = `<text x="${W / 2}" y="${dir === 'down' ? arrowY + arrow / 2 + 7 : arrow / 2 + 17}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: `<path d="${arrowPath}" fill="${frame.bg_color}"/>${text}<g transform="translate(${qrX} ${qrY})">${qrSvgInner}</g>` };
    };
  }

  // Device frame (phone / tablet etc)
  function deviceFrame({ deviceW, deviceH, screenInset = 24, radius = 40, notch = false } = {}) {
    return ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const W = deviceW, H = deviceH;
      const sx = screenInset, sy = screenInset + (notch ? 30 : screenInset);
      const sw = W - 2 * screenInset, sh = H - sy - screenInset;
      const scale = Math.min(sw, sh) / qrSize;
      const sxQ = sx + (sw - qrSize * scale) / 2;
      const syQ = sy + (sh - qrSize * scale) / 2;
      const notchEl = notch ? `<rect x="${W / 2 - 50}" y="10" width="100" height="20" rx="10" fill="#0a0a0a"/>` : '';
      const body =
        `<rect x="0" y="0" width="${W}" height="${H}" rx="${radius}" fill="${frame.bg_color}"/>` +
        `<rect x="${sx}" y="${sy}" width="${sw}" height="${sh}" rx="${Math.max(8, radius - 16)}" fill="#ffffff"/>` +
        notchEl +
        `<g transform="translate(${sxQ} ${syQ}) scale(${scale})">${qrSvgInner}</g>` +
        `<text x="${W / 2}" y="${H - 8}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="16" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 12, body };
    };
  }

  const FRAMES = {
    'none': frameNone,
    // Scan-Me bars
    'scan-me-bottom':  bar({ side: 'bottom', shape: 'rect' }),
    'scan-me-top':     bar({ side: 'top',    shape: 'rect' }),
    'scan-me-rounded': bar({ side: 'bottom', shape: 'rounded', barRadius: 16 }),
    'scan-me-pill':    bar({ side: 'bottom', shape: 'pill' }),
    'scan-me-double':  bar({ side: 'bottom', shape: 'double' }),
    'scan-me-bar':     bar({ side: 'top',    shape: 'rounded', barRadius: 8 }),
    'scan-me-classic': bar({ side: 'bottom', shape: 'rect', height: 56 }),
    'scan-me-bold':    bar({ side: 'bottom', shape: 'rect', height: 80 }),
    // Bubbles
    'bubble-down':    bubble({ direction: 'down' }),
    'bubble-up':      bubble({ direction: 'up' }),
    'bubble-left':    bubble({ direction: 'left' }),
    'bubble-right':   bubble({ direction: 'right' }),
    'cloud':          ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 30; const W = qrSize + 2 * pad, H = qrSize + 2 * pad + 40;
      const cloud = `M${pad} ${pad + 20}q-20 0 -20 -20q0 -25 25 -25q5 -20 30 -20q15 -25 45 -10q15 -20 50 0q10 -10 30 -5q20 -10 35 10q15 0 25 15q15 5 15 25q0 20 -25 30H${W - pad}q25 5 25 30q0 30 -35 30H${pad + 30}q-30 0 -30 -30q0 -25 25 -30z`;
      // Approx; fall back to a rounded rect with bumps
      const r = 40;
      const bumps = `<rect x="0" y="20" width="${W}" height="${qrSize + 2 * pad}" rx="${r}" fill="${frame.bg_color}"/><circle cx="${W * 0.2}" cy="20" r="22" fill="${frame.bg_color}"/><circle cx="${W * 0.5}" cy="10" r="28" fill="${frame.bg_color}"/><circle cx="${W * 0.8}" cy="20" r="22" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 8}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: bumps + `<g transform="translate(${pad} ${pad + 20})">${qrSvgInner}</g>` + text };
    },
    'thought-bubble': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 28, tail = 40;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + tail;
      const main = `<rect x="0" y="0" width="${W}" height="${qrSize + 2 * pad}" rx="40" fill="${frame.bg_color}"/>`;
      const dots = `<circle cx="${W / 2 - 30}" cy="${qrSize + 2 * pad + 10}" r="10" fill="${frame.bg_color}"/><circle cx="${W / 2 - 50}" cy="${qrSize + 2 * pad + 28}" r="6" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 4}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 16, body: main + dots + text + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` };
    },
    // Ribbons
    'ribbon-bottom':  bar({ side: 'bottom', shape: 'rect', height: 56 }),
    'ribbon-top':     bar({ side: 'top', shape: 'rect', height: 56 }),
    'banner-curve-top':    ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 16, bh = 58;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + bh + 12;
      const banner = `<path d="M0 ${bh}Q${W / 2} 0 ${W} ${bh}V${bh}H0z" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${bh / 2 + 18}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: banner + text + `<g transform="translate(${pad} ${bh + pad})">${qrSvgInner}</g>` };
    },
    'banner-curve-bottom': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 16, bh = 58;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + bh + 12;
      const by = qrSize + 2 * pad;
      const banner = `<path d="M0 ${by}Q${W / 2} ${by + bh + 20} ${W} ${by}V${by + bh}H0z" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${by + bh / 2 + 18}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 8, body: `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + banner + text };
    },
    'banner-fold': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 16, bh = 56;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + bh + 16;
      const by = qrSize + 2 * pad;
      const banner = `<path d="M0 ${by}H${W}L${W - 16} ${by + 16}H${W}L${W} ${by + bh}H0L0 ${by + bh}H0L16 ${by + 16}H0z" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${by + bh / 2 + 18}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + banner + text };
    },
    'ribbon-bow': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18, bh = 54;
      const W = qrSize + 2 * pad, H = qrSize + 2 * pad + bh + 16;
      const by = qrSize + 2 * pad;
      const main = `<rect x="0" y="${by}" width="${W}" height="${bh}" fill="${frame.bg_color}"/>`;
      const tails = `<polygon points="0 ${by} -20 ${by + bh / 2} 0 ${by + bh}" fill="${frame.bg_color}"/><polygon points="${W} ${by} ${W + 20} ${by + bh / 2} ${W} ${by + bh}" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${by + bh / 2 + 8}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W + 24, height: H, body: `<g transform="translate(${12 + pad - pad} ${pad})">${qrSvgInner}</g>` + main + tails + text };
    },
    'tape-top': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 12;
      const tape = `<g transform="rotate(-3 ${W / 2} 24)"><rect x="${W / 2 - 60}" y="6" width="120" height="32" fill="${frame.bg_color}" opacity="0.85"/></g>`;
      const text = `<text x="${W / 2}" y="${H - 4}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 16, body: `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + tape + text };
    },
    'tape-bottom': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 60;
      const tape = `<g transform="rotate(3 ${W / 2} ${qrSize + 2 * pad + 24})"><rect x="${W / 2 - 60}" y="${qrSize + 2 * pad + 8}" width="120" height="32" fill="${frame.bg_color}" opacity="0.85"/></g>`;
      const text = `<text x="${W / 2}" y="${H - 4}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="14" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + tape + text };
    },
    // Tickets / tags
    'ticket': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 28; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 50;
      const c = 14;
      const t = `<path d="M${c} 0H${W - c}A${c} ${c} 0 0 1 ${W} ${c}V${H - c}A${c} ${c} 0 0 1 ${W - c} ${H}H${c}A${c} ${c} 0 0 1 0 ${H - c}V${c}A${c} ${c} 0 0 1 ${c} 0z" fill="${frame.bg_color}"/>`;
      // Notches on sides
      const notch = `<circle cx="0" cy="${pad + qrSize / 2}" r="10" fill="#fff"/><circle cx="${W}" cy="${pad + qrSize / 2}" r="10" fill="#fff"/>`;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: t + notch + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'ticket-perforated': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 28; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 50;
      let perf = '';
      for (let y = 8; y < H - 8; y += 12) perf += `<circle cx="0" cy="${y}" r="3" fill="#fff"/><circle cx="${W}" cy="${y}" r="3" fill="#fff"/>`;
      const t = `<rect width="${W}" height="${H}" rx="10" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: t + perf + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'price-tag': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 22; const W = qrSize + 2 * pad + 30; const H = qrSize + 2 * pad;
      const tag = `<path d="M30 0H${W}V${H}H30L0 ${H / 2}z" fill="${frame.bg_color}"/><circle cx="46" cy="${H / 2}" r="8" fill="#fff"/>`;
      const text = `<text x="${W / 2 + 15}" y="${H - 10}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 16, body: tag + `<g transform="translate(${30 + pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'tag-left':  ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18; const W = qrSize + 2 * pad + 30; const H = qrSize + 2 * pad;
      const tag = `<path d="M30 0H${W}V${H}H30L0 ${H / 2}z" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2 + 15}" y="${H - 8}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 16, body: tag + `<g transform="translate(${30 + pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'tag-right': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18; const W = qrSize + 2 * pad + 30; const H = qrSize + 2 * pad;
      const tag = `<path d="M0 0H${W - 30}L${W} ${H / 2}L${W - 30} ${H}H0z" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2 - 15}" y="${H - 8}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 16, body: tag + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'luggage-tag': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 22; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 60;
      const r = 16;
      const tag = `<rect x="0" y="20" width="${W}" height="${H - 20}" rx="${r}" fill="${frame.bg_color}"/><circle cx="${W / 2}" cy="20" r="10" fill="${frame.bg_color}"/><circle cx="${W / 2}" cy="20" r="4" fill="#fff"/>`;
      const text = `<text x="${W / 2}" y="${H - 12}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: tag + `<g transform="translate(${pad} ${20 + pad})">${qrSvgInner}</g>` + text };
    },
    'coupon': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 24; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 50;
      const t = `<rect width="${W}" height="${H}" fill="${frame.bg_color}"/>`;
      let scallops = '';
      for (let x = 8; x < W - 8; x += 18) scallops += `<circle cx="${x}" cy="0" r="6" fill="#fff"/><circle cx="${x}" cy="${H}" r="6" fill="#fff"/>`;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: t + scallops + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    // Badges
    'badge-circle': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 30; const W = qrSize + 2 * pad; const H = W;
      const c = `<circle cx="${W / 2}" cy="${H / 2}" r="${W / 2}" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 16}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: c + `<g transform="translate(${pad} ${pad - 10})">${qrSvgInner}</g>` + text };
    },
    'badge-square': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 22; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 40;
      const c = `<rect width="${W}" height="${H}" rx="20" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: c + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'starburst': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 50; const W = qrSize + 2 * pad; const H = W;
      const star = STAR(W / 2, H / 2, 16, W / 2, W / 2 - 14);
      const burst = `<path d="${star}" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 18}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: burst + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'shield': polyFrame((W, H, f) => `<path d="M0 0H${W}V${H * 0.6}Q${W} ${H} ${W / 2} ${H}Q0 ${H} 0 ${H * 0.6}z" fill="${f.bg_color}"/>`),
    'plaque': polyFrame((W, H, f) => `<rect width="${W}" height="${H}" rx="24" fill="${f.bg_color}"/>`),
    'seal': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 40; const W = qrSize + 2 * pad; const H = W;
      const seal = `<g transform="translate(${W / 2} ${H / 2})"><circle r="${W / 2}" fill="${frame.bg_color}"/></g>`;
      const text = `<text x="${W / 2}" y="${H - 24}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: seal + `<g transform="translate(${pad} ${pad - 14})">${qrSvgInner}</g>` + text };
    },
    'medal': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 36; const W = qrSize + 2 * pad; const H = W + 30;
      const ribbon = `<polygon points="${W * 0.3} 0,${W * 0.45} 0,${W / 2 - 6} 50,${W / 2 + 6} 50,${W * 0.55} 0,${W * 0.7} 0,${W / 2} 80" fill="${frame.text_color}"/>`;
      const c = `<circle cx="${W / 2}" cy="${H / 2 + 10}" r="${W / 2}" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: ribbon + c + `<g transform="translate(${pad} ${pad + 10})">${qrSvgInner}</g>` + text };
    },
    // Arrows
    'arrow-down':  arrowFrame('down'),
    'arrow-up':    arrowFrame('up'),
    'arrow-left':  arrowFrame('left'),
    'arrow-right': arrowFrame('right'),
    'callout-box': bordered({ stroke: 4, radius: 10, double: true }),
    'chevron-down': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 20; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 50;
      const ch = `<polygon points="${W / 2 - 30} ${qrSize + 2 * pad},${W / 2 + 30} ${qrSize + 2 * pad},${W / 2} ${H - 4}" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H + 12}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 22, body: `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + ch + text };
    },
    'chevron-up': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 20; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 50;
      const ch = `<polygon points="${W / 2 - 30} 50,${W / 2 + 30} 50,${W / 2} 4" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H + 16}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H + 22, body: ch + `<g transform="translate(${pad} ${pad + 50})">${qrSvgInner}</g>` + text };
    },
    // Devices
    'phone-frame':  deviceFrame({ deviceW: 320, deviceH: 540, screenInset: 16, radius: 36, notch: true }),
    'tablet-frame': deviceFrame({ deviceW: 480, deviceH: 580, screenInset: 24, radius: 28, notch: false }),
    'laptop-frame': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const W = 600, H = 420;
      const screen = `<rect x="40" y="20" width="${W - 80}" height="${H - 80}" rx="14" fill="${frame.bg_color}"/><rect x="48" y="28" width="${W - 96}" height="${H - 96}" fill="#fff"/>`;
      const base = `<rect x="0" y="${H - 60}" width="${W}" height="14" rx="6" fill="${frame.bg_color}"/><rect x="20" y="${H - 46}" width="${W - 40}" height="6" rx="3" fill="${frame.bg_color}"/>`;
      const scale = (H - 110) / qrSize;
      const sxQ = (W - qrSize * scale) / 2, syQ = 32;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="14" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: screen + base + `<g transform="translate(${sxQ} ${syQ}) scale(${scale})">${qrSvgInner}</g>` + text };
    },
    'watch-frame': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const W = 280, H = 360;
      const band = `<rect x="${W / 2 - 70}" y="0" width="140" height="60" fill="${frame.text_color}"/><rect x="${W / 2 - 70}" y="${H - 60}" width="140" height="60" fill="${frame.text_color}"/>`;
      const watch = `<rect x="${W / 2 - 110}" y="60" width="220" height="240" rx="40" fill="${frame.bg_color}"/><rect x="${W / 2 - 100}" y="70" width="200" height="220" rx="32" fill="#fff"/>`;
      const scale = 200 / qrSize;
      const sxQ = (W - qrSize * scale) / 2, syQ = 80;
      return { width: W, height: H, body: band + watch + `<g transform="translate(${sxQ} ${syQ}) scale(${scale})">${qrSvgInner}</g>` };
    },
    'tv-frame': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const W = 480, H = 380;
      const tv = `<rect width="${W}" height="${H - 30}" rx="18" fill="${frame.bg_color}"/><rect x="14" y="14" width="${W - 28}" height="${H - 58}" fill="#fff"/><rect x="${W / 2 - 30}" y="${H - 30}" width="60" height="14" rx="4" fill="${frame.bg_color}"/>`;
      const scale = (H - 80) / qrSize;
      const sxQ = (W - qrSize * scale) / 2, syQ = 22;
      return { width: W, height: H, body: tv + `<g transform="translate(${sxQ} ${syQ}) scale(${scale})">${qrSvgInner}</g>` };
    },
    'polaroid': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 24; const W = qrSize + 2 * pad; const H = qrSize + pad + 80;
      const card = `<rect width="${W}" height="${H}" fill="${frame.bg_color}"/>`;
      const text = `<text x="${W / 2}" y="${H - 24}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="22" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: card + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'photo': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 14; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 40;
      const card = `<rect width="${W}" height="${H}" fill="${frame.bg_color}"/><rect x="${pad - 2}" y="${pad - 2}" width="${qrSize + 4}" height="${qrSize + 4}" fill="#fff"/>`;
      const text = `<text x="${W / 2}" y="${H - 14}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="18" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: card + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    // Geometric frames
    'rounded-card': bordered({ stroke: 0, radius: 24 }),
    'minimal-line': bordered({ stroke: 2, radius: 0, gap: 14 }),
    'double-border': bordered({ stroke: 6, double: true, radius: 6, gap: 16 }),
    'dashed-border': bordered({ stroke: 4, dash: '8 6', radius: 4, gap: 12 }),
    'dotted-border': bordered({ stroke: 4, dash: '2 6', radius: 4, gap: 12 }),
    'corners-only': bordered({ stroke: 6, corners: true, gap: 14 }),
    'diamond-frame': polyFrame((W, H, f) => `<polygon points="${W / 2} 0,${W} ${H / 2},${W / 2} ${H},0 ${H / 2}" fill="${f.bg_color}"/>`),
    'hexagon-frame': polyFrame((W, H, f) => {
      const pts = [];
      for (let i = 0; i < 6; i++) { const a = Math.PI / 3 * i + Math.PI / 2; pts.push((W / 2 + Math.cos(a) * W / 2) + ',' + (H / 2 + Math.sin(a) * H / 2)); }
      return `<polygon points="${pts.join(' ')}" fill="${f.bg_color}"/>`;
    }),
    'octagon-frame': polyFrame((W, H, f) => {
      const pts = [];
      for (let i = 0; i < 8; i++) { const a = Math.PI / 4 * i + Math.PI / 8; pts.push((W / 2 + Math.cos(a) * W / 2) + ',' + (H / 2 + Math.sin(a) * H / 2)); }
      return `<polygon points="${pts.join(' ')}" fill="${f.bg_color}"/>`;
    }),
    'gradient-border': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 14; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 40;
      const gid = 'gb_' + Math.random().toString(36).slice(2, 8);
      const grad = `<linearGradient id="${gid}" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="${frame.bg_color}"/><stop offset="100%" stop-color="${frame.text_color}"/></linearGradient>`;
      const border = `<rect x="3" y="3" width="${W - 6}" height="${qrSize + 2 * pad - 6}" rx="14" fill="none" stroke="url(#${gid})" stroke-width="6"/>`;
      const text = `<text x="${W / 2}" y="${H - 12}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: `<defs>${grad}</defs>` + border + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'neon-border': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 40;
      const fid = 'neon_' + Math.random().toString(36).slice(2, 8);
      const f = `<filter id="${fid}" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="6"/></filter>`;
      const halo = `<rect x="2" y="2" width="${W - 4}" height="${qrSize + 2 * pad - 4}" rx="14" fill="none" stroke="${frame.bg_color}" stroke-width="6" filter="url(#${fid})"/>`;
      const ring = `<rect x="2" y="2" width="${W - 4}" height="${qrSize + 2 * pad - 4}" rx="14" fill="none" stroke="${frame.bg_color}" stroke-width="3"/>`;
      const text = `<text x="${W / 2}" y="${H - 12}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: `<defs>${f}</defs>` + halo + ring + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
    'shadow-soft': ({ qrSvgInner, qrSize, frame, fontFamily }) => {
      const pad = 18; const W = qrSize + 2 * pad; const H = qrSize + 2 * pad + 40;
      const fid = 'sh_' + Math.random().toString(36).slice(2, 8);
      const f = `<filter id="${fid}" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="6" stdDeviation="10" flood-opacity="0.25"/></filter>`;
      const card = `<rect x="0" y="0" width="${W}" height="${qrSize + 2 * pad}" rx="20" fill="${frame.bg_color}" filter="url(#${fid})"/>`;
      const text = `<text x="${W / 2}" y="${H - 12}" text-anchor="middle" font-family="${fontFamily}, sans-serif" font-weight="700" font-size="20" fill="${frame.text_color}">${escText(frame.text || '')}</text>`;
      return { width: W, height: H, body: `<defs>${f}</defs>` + card + `<g transform="translate(${pad} ${pad})">${qrSvgInner}</g>` + text };
    },
  };

  // ---------- PNG export via canvas ----------
  function toPngDataUrl(svg, width, height, scale = 2) {
    return new Promise((resolve, reject) => {
      const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;
        const ctx = canvas.getContext('2d');
        ctx.scale(scale, scale);
        ctx.drawImage(img, 0, 0);
        URL.revokeObjectURL(url);
        try { resolve(canvas.toDataURL('image/png')); }
        catch (e) { reject(e); }
      };
      img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
      img.src = url;
    });
  }

  function downloadDataUrl(dataUrl, filename) {
    const a = document.createElement('a');
    a.href = dataUrl; a.download = filename;
    document.body.appendChild(a); a.click(); a.remove();
  }
  function downloadSvg(svg, filename) {
    const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    downloadDataUrl(url, filename);
    setTimeout(() => URL.revokeObjectURL(url), 1500);
  }

  // ---------- catalog metadata for the UI ----------
  const CATALOG = {
    dots: {
      'Geometric': ['square','dot','rounded','rounded-lg','diamond','plus','x-mark','square-tilted','oval-h','oval-v','pill-h','pill-v','dash-h','dash-v','square-tl','square-tr','square-bl','square-br','square-top','square-bottom','square-left','square-right','half-circle-top','half-circle-bottom','half-circle-left','half-circle-right'],
      'Polygons':  ['triangle-up','triangle-down','triangle-left','triangle-right','hexagon','hexagon-rotated','octagon','pentagon','kite','parallelogram','chevron-right','chevron-up'],
      'Decorative':['star4','star5','star6','star8','heart','flower','flower6','gear','sparkle','cross-pattee'],
      'Organic':   ['leaf','leaf-mirror','drop','drop-rotated','blob','arrow-up','arrow-right','arrow-left','arrow-down','ring','donut','square-with-hole','plus-thick','plus-rounded','dotted-square','double-square'],
    },
    outerEyes: {
      'Classic':    ['square','rounded','extra-rounded','dot','soft-square','beveled-square','double-square','double-circle'],
      'Asymmetric': ['leaf-tl','leaf-tr','leaf-bl','leaf-br','rounded-tl','rounded-tr','rounded-bl','rounded-br','rounded-tl-br','rounded-tr-bl','cut-tl','cut-tr','cut-bl','cut-br'],
      'Polygons':   ['hexagon','octagon','diamond','triangle','pentagon','star','plus-frame','x-frame','shield','badge','ticket','plaque'],
      'Decorative': ['flower','gear','sparkle-frame','heart-frame','ribbon-frame','dotted-frame','dashed-frame','double-line','thick-circle','thick-square','soft-rounded','rounded-pill-h','rounded-pill-v','half-rounded-top','half-rounded-bottom','half-rounded-left','half-rounded-right'],
    },
    innerEyes: {
      'Geometric': ['dot','square','rounded','extra-rounded','diamond','oval-h','oval-v','pill-h','pill-v','square-tl','square-tr','square-bl','square-br','half-circle-top','half-circle-bottom','half-circle-left','half-circle-right'],
      'Polygons':  ['triangle-up','triangle-down','triangle-left','triangle-right','hexagon','octagon','pentagon','kite','chevron-right','chevron-up','parallelogram'],
      'Decorative':['star4','star5','star6','star8','heart','plus','x','flower','gear','sparkle','cross-pattee'],
      'Organic':   ['leaf','leaf-mirror','drop','drop-rotated','blob','arrow-up','arrow-right','ring','donut','square-with-hole','plus-thick','double-dot','double-square'],
    },
    frames: {
      'None':         ['none'],
      'Scan-Me Bars': ['scan-me-bottom','scan-me-top','scan-me-rounded','scan-me-pill','scan-me-double','scan-me-bar','scan-me-classic','scan-me-bold'],
      'Speech & Bubbles': ['bubble-down','bubble-up','bubble-left','bubble-right','cloud','thought-bubble'],
      'Ribbons & Banners':['ribbon-bottom','ribbon-top','banner-curve-top','banner-curve-bottom','banner-fold','ribbon-bow','tape-top','tape-bottom'],
      'Tickets & Tags':   ['ticket','ticket-perforated','price-tag','tag-left','tag-right','luggage-tag','coupon'],
      'Badges & Plaques': ['badge-circle','badge-square','starburst','shield','plaque','seal','medal'],
      'Arrows & Callouts':['arrow-down','arrow-up','arrow-left','arrow-right','callout-box','chevron-down','chevron-up'],
      'Devices':          ['phone-frame','tablet-frame','laptop-frame','watch-frame','tv-frame','polaroid','photo'],
      'Geometric Frames': ['rounded-card','minimal-line','double-border','dashed-border','dotted-border','corners-only','diamond-frame','hexagon-frame','octagon-frame','gradient-border','neon-border','shadow-soft'],
    },
  };

  // Render a small thumbnail for a shape (returns inline SVG string)
  function thumbDot(id, color = '#0f172a') {
    const fn = DOTS[id]; if (!fn) return '';
    const s = 36;
    return `<svg viewBox="0 0 ${s} ${s}" width="${s}" height="${s}"><path d="${fn(2, 2, s - 4)}" fill="${color}"/></svg>`;
  }
  function thumbOuter(id, color = '#0f172a') {
    const fn = OUTER[id]; if (!fn) return '';
    const m = 4, s = 4;
    const total = 7 * s + 2 * m;
    return `<svg viewBox="0 0 ${total} ${total}" width="36" height="36"><path d="${fn(m, m, s)}" fill="${color}" fill-rule="evenodd"/></svg>`;
  }
  function thumbInner(id, color = '#0f172a') {
    const fn = INNER[id]; if (!fn) return '';
    const m = 6, s = 8;
    const total = 3 * s + 2 * m;
    return `<svg viewBox="0 0 ${total} ${total}" width="36" height="36"><path d="${fn(m, m, s)}" fill="${color}"/></svg>`;
  }
  function thumbFrame(id, fontFamily = 'Inter') {
    const fn = FRAMES[id]; if (!fn) return '';
    const inner = `<rect width="60" height="60" fill="#0f172a"/>`;
    try {
      const r = fn({
        qrSvgInner: inner, qrSize: 60,
        frame: { template: id, text: 'SCAN', bg_color: '#0f172a', text_color: '#ffffff', font: fontFamily },
        fontFamily, defs: []
      });
      const w = r.width, h = r.height;
      const scale = 80 / Math.max(w, h);
      return `<svg viewBox="0 0 ${w} ${h}" width="${w * scale}" height="${h * scale}">${r.body}</svg>`;
    } catch (e) { return ''; }
  }

  global.QrStudio = {
    DOTS, OUTER_EYES: OUTER, INNER_EYES: INNER, FRAMES,
    CATALOG,
    render: renderQR,
    buildMatrix,
    renderFromMatrix,
    preloadLogos,
    toPngDataUrl,
    downloadSvg, downloadDataUrl,
    thumbDot, thumbOuter, thumbInner, thumbFrame,
  };
})(window);
