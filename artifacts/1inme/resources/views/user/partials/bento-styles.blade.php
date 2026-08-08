{{--
    Shared "bento command center" styling.

    The frosted-glass tile grid, localised aurora depth backdrop, live-pulse
    hero and colored metric tiles were first established on the user Dashboard
    (user/dashboard/index.blade.php). They are extracted here so other top-level
    user surfaces (Links index, Stats, ...) share the exact same visual language
    instead of re-inventing it, and so a single edit keeps them in lockstep.

    Usage — include inside a view's @push('styles'):
        @push('styles')
            @include('user.partials.bento-styles')
        @endpush

    Then wrap the page body in `.bento-stage`, lay tiles out with `.bento`
    (single column on phones → 2-up tablets → asymmetric 6-col desktop) and
    `.bento-tile`, using the span helpers `.b-2 / .b-3 / .b-4 / .b-6 / .b-feat /
    .b-4-tall`. Add `.accent` (+ a `.tile-orb` child and `--tile-accent` /
    `--tile-glow` vars) for a colored metric tile. `.bento-hero` + `.hero-grid`
    render the live-pulse hero; `.pulse-orb` / `.live-dot` its live indicator.

    The `aurora` and `float-slow` keyframes referenced below are defined globally
    in common/partials/theme-styles.blade.php; `pulse-out` / `live-blink` are
    scoped here. Everything is dark/light aware and reduced-motion respecting,
    and the accent stays blue so the brand-color guard keeps passing.
--}}
<style>
    /* ===================== Bento command center ===================== */
    /* Localised aurora depth behind the whole grid (dark mode only). */
    .bento-stage { position: relative; isolation: isolate; overflow-x: clip; }
    .bento-stage > * { position: relative; z-index: 1; }
    html:not(.light-mode) .bento-stage::before,
    html:not(.light-mode) .bento-stage::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(90px);
        pointer-events: none;
        z-index: 0;
        mix-blend-mode: screen;
        opacity: 0.5;
    }
    html:not(.light-mode) .bento-stage::before {
        top: -8%; left: -6%;
        width: 44%; height: 54%;
        background: radial-gradient(circle, rgba(61,107,255,0.32), transparent 70%);
        animation: aurora 24s ease-in-out infinite;
    }
    html:not(.light-mode) .bento-stage::after {
        top: 22%; right: -8%;
        width: 42%; height: 62%;
        background: radial-gradient(circle, rgba(34,211,238,0.24), transparent 70%);
        animation: aurora 30s ease-in-out infinite reverse;
    }

    /* Bento grid: single column on phones, 2-up on tablets, asymmetric 6-col on desktop. */
    .bento {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    @media (min-width: 768px) {
        .bento { grid-template-columns: repeat(2, 1fr); }
    }
    @media (min-width: 1024px) {
        .bento {
            grid-template-columns: repeat(6, 1fr);
            grid-auto-rows: minmax(140px, auto);
            grid-auto-flow: row dense;
            align-items: stretch;
        }
        .b-feat { grid-column: span 2; grid-row: span 2; }
        .b-2 { grid-column: span 2; }
        .b-3 { grid-column: span 3; }
        .b-4 { grid-column: span 4; }
        .b-6 { grid-column: span 6; }
        .b-4-tall { grid-column: span 4; grid-row: span 2; }
    }

    /* Layered liquid glass tile */
    .bento-tile {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid transparent;
        border-radius: 1.5rem;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), inset 1.5px 2px 0 -1px rgba(255, 255, 255, 0.4), inset -1.5px -1.5px 0 -1px rgba(255, 255, 255, 0.2), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.15), inset 0 0 8px 1px rgba(0, 0, 0, 0.2), 0 12px 32px rgba(0, 0, 0, 0.4);
        transition: transform .25s cubic-bezier(.4,0,.2,1), border-color .25s ease, box-shadow .25s ease, background .25s ease;
    }
    @supports (backdrop-filter: blur(8px)) {
        html:not(.light-mode) .bento-tile {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.01) 100%);
            backdrop-filter: blur(6px) saturate(180%) brightness(1.1);
            -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.1);
        }
    }
    html.light-mode .bento-tile {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid transparent;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4), inset 1.8px 3px 0 -2px rgba(255, 255, 255, 0.9), inset -2px -2px 0 -2px rgba(255, 255, 255, 0.8), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.6), inset -0.3px -1px 4px 0 rgba(0, 0, 0, 0.05), inset 0 0 8px 1px rgba(0, 0, 0, 0.02), 0 12px 32px rgba(0, 0, 0, 0.08);
    }
    @supports (backdrop-filter: blur(8px)) {
        html.light-mode .bento-tile {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.1) 100%);
            backdrop-filter: blur(6px) saturate(180%) brightness(1.05);
            -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.05);
        }
    }
    .bento-tile::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        pointer-events: none;
        background: linear-gradient(180deg, rgba(255,255,255,0.06), transparent 42%);
        opacity: 0.3;
    }
    html.light-mode .bento-tile::after {
        opacity: 0.3;
        background: linear-gradient(180deg, rgba(255,255,255,0.4), transparent 48%);
    }
    .bento-tile > * { position: relative; z-index: 1; }
    .bento-tile:hover {
        transform: translateY(-4px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), inset 1.5px 2px 0 -1px rgba(255, 255, 255, 0.4), inset -1.5px -1.5px 0 -1px rgba(255, 255, 255, 0.2), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.15), inset 0 0 8px 1px rgba(0, 0, 0, 0.2), 0 20px 45px rgba(0, 0, 0, 0.6);
    }
    html.light-mode .bento-tile:hover {
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4), inset 1.8px 3px 0 -2px rgba(255, 255, 255, 0.9), inset -2px -2px 0 -2px rgba(255, 255, 255, 0.8), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.6), inset -0.3px -1px 4px 0 rgba(0, 0, 0, 0.05), inset 0 0 8px 1px rgba(0, 0, 0, 0.02), 0 20px 45px rgba(0, 0, 0, 0.12);
    }
    /* Colored top accent bar (metric tiles) */
    .bento-tile.accent::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--tile-accent, linear-gradient(90deg, #3d6bff, #5c83ff));
        z-index: 2;
        opacity: 0.9;
    }
    /* Soft colored glow orb bleeding from a corner (adds spatial depth) */
    .bento-tile.accent {
        --tile-glow: rgba(61,107,255,0.16);
    }
    .bento-tile.accent .tile-orb {
        position: absolute;
        top: -40px; right: -40px;
        width: 150px; height: 150px;
        border-radius: 50%;
        background: radial-gradient(closest-side, var(--tile-glow), transparent 72%);
        filter: blur(8px);
        pointer-events: none;
        z-index: 0;
        opacity: 0.9;
    }

    /* ===================== Live-pulse hero tile ===================== */
    .bento-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.5rem;
        border: 1.5px solid var(--border-glass);
        background: var(--bg-card);
        box-shadow: var(--card-shadow);
        padding: clamp(1.25rem, 3vw, 2.1rem);
        margin-bottom: 1.25rem;
    }
    html:not(.light-mode) .bento-hero {
        background: linear-gradient(135deg, rgba(61,107,255,0.15), rgba(34,211,238,0.06) 45%, rgba(255,255,255,0.02));
        backdrop-filter: blur(30px) saturate(160%);
        -webkit-backdrop-filter: blur(30px) saturate(160%);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.06), 0 18px 46px -16px rgba(0,0,0,0.6);
    }
    html.light-mode .bento-hero {
        background: linear-gradient(135deg, #eef3ff, #ffffff 62%);
        border-color: #d3e0ff;
    }
    .bento-hero::before {
        content: '';
        position: absolute;
        top: -45%; right: -8%;
        width: 52%; height: 190%;
        background: radial-gradient(circle at center, rgba(61,107,255,0.24), transparent 68%);
        filter: blur(30px);
        pointer-events: none;
        z-index: 0;
        animation: float-slow 20s ease-in-out infinite;
    }
    html.light-mode .bento-hero::before {
        background: radial-gradient(circle at center, rgba(61,107,255,0.10), transparent 70%);
    }
    .bento-hero > * { position: relative; z-index: 1; }
    .hero-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        align-items: center;
    }
    @media (min-width: 900px) {
        .hero-grid { grid-template-columns: 1fr auto; }
    }

    /* Live pulse orb — a headline metric with a pulsing ring */
    .pulse-orb {
        position: relative;
        width: 116px;
        height: 116px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at 30% 30%, rgba(61,107,255,0.28), rgba(34,211,238,0.12));
        border: 1.5px solid rgba(61,107,255,0.4);
        flex-shrink: 0;
    }
    html.light-mode .pulse-orb {
        background: radial-gradient(circle at 30% 30%, rgba(61,107,255,0.14), rgba(61,107,255,0.04));
        border-color: rgba(61,107,255,0.28);
    }
    .pulse-orb::before,
    .pulse-orb::after {
        content: '';
        position: absolute;
        inset: -3px;
        border-radius: 50%;
        border: 2px solid rgba(61,107,255,0.5);
        animation: pulse-out 2.8s ease-out infinite;
    }
    .pulse-orb::after { animation-delay: 1.4s; }
    @keyframes pulse-out {
        0%   { transform: scale(0.92); opacity: 0.75; }
        70%  { transform: scale(1.28); opacity: 0; }
        100% { opacity: 0; }
    }
    .live-dot {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #34d399;
    }
    .live-dot .dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #34d399;
        box-shadow: 0 0 8px rgba(52,211,153,0.8);
        animation: live-blink 1.8s ease-in-out infinite;
    }
    @keyframes live-blink {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.35; transform: scale(0.8); }
    }

    @media (prefers-reduced-motion: reduce) {
        .bento-tile { transition: none; }
        .bento-tile:hover { transform: none; }
        html:not(.light-mode) .bento-stage::before,
        html:not(.light-mode) .bento-stage::after,
        .bento-hero::before,
        .pulse-orb::before, .pulse-orb::after,
        .live-dot .dot { animation: none !important; }
        .pulse-orb::before { opacity: 0.4; }
        .pulse-orb::after { display: none; }
    }
</style>
