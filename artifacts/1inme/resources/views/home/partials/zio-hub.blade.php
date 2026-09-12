{{--
    Zio hub — the one picture that says the AI suite is a suite.

    The AI zone lists seven or eight coworkers one after another, each in its
    own band. Read top to bottom that is a list of separate products; the
    thing it never shows is the part that actually matters, which is that all
    of them are one AI reading one set of your data. This section is that
    claim as a diagram: Zio in the middle, every surface it drives around it,
    and data visibly moving between them.

    Modelled on the orchestration diagram on stripe.com -- centre node,
    labelled nodes around it, hairline connectors, dot-grid ground -- and, as
    there, it sits on a DARK band in both colour modes. That is deliberate:
    the band marks the AI zone as its own place in the page, and the flow
    animation only reads against a dark ground.

    Geometry. Nodes are positioned in percentages and the connectors are an
    SVG on `viewBox="0 0 1000 560"` with `preserveAspectRatio="none"`, so both
    stretch with the stage and stay registered to each other at any width --
    no JS measuring, no fixed pixel canvas. The percentages in $zioNodes below
    are the single source of truth: the same numbers scaled by 10 and 5.6 give
    the SVG endpoint of each connector, which is how the two stay in step. If
    the stage's aspect ratio changes, the 5.6 and the hub's y of 280 change
    with it.

    The flow. Each connector is drawn twice: a static hairline, and over it a
    short dash on a long gap whose offset animates from the hub outward. A
    dash travelling a path reads as a packet in transit -- which is the whole
    point here, and the opposite of what a dash does to a ribbon, where it
    just chops the ribbon into pieces.

    Below 900px the diagram is not a diagram: connectors that need width to be
    legible become a tangle. The SVG is dropped and the nodes stack under the
    hub as a plain list, which is the same information without pretending to
    be a picture.
--}}
@php
    // x/y are percentages of the stage. `side` picks which edge of the node
    // the connector meets, so a line never crosses the label it points at.
    $zioNodes = [
        ['icon' => 'fa-wand-magic-sparkles', 'label' => 'AI Page Builder',        'x' => 15, 'y' => 12, 'side' => 'r', 'href' => '#features'],
        ['icon' => 'fa-robot',               'label' => 'AI Chatbot',             'x' => 50, 'y' => 5,  'side' => 'b', 'href' => '#ai-suite'],
        ['icon' => 'fa-diagram-project',     'label' => 'AI Agent',               'x' => 85, 'y' => 12, 'side' => 'l', 'href' => '#ai-suite'],
        ['icon' => 'fa-code',                'label' => 'AI Widget',              'x' => 92, 'y' => 44, 'side' => 'l', 'href' => '#ai-suite'],
        ['icon' => 'fa-phone-volume',        'label' => 'AI Voice Assistant',     'x' => 85, 'y' => 78, 'side' => 'l', 'href' => '#ai-suite'],
        ['icon' => 'fa-chart-line',          'label' => 'AI Marketing Strategist','x' => 50, 'y' => 90, 'side' => 't', 'href' => '#ai-marketing-strategist'],
        ['icon' => 'fa-comment-dots',        'label' => 'WhatsApp Agent',         'x' => 15, 'y' => 78, 'side' => 'r', 'href' => '#whatsapp-agent'],
        ['icon' => 'fa-gauge-high',          'label' => 'AI Dashboard',           'x' => 8,  'y' => 44, 'side' => 'r', 'href' => '#ai-dashboard'],
    ];
@endphp

<style>
    /* ===== Zio hub (zh- = zio hub) ===== */
    .zh-band {
        position: relative;
        /* Dark in BOTH modes, on purpose -- see the note at the top of this
           file. Every colour below is therefore a literal, not a theme token:
           there is nothing for the light-mode sheet to swap. */
        background: #0B1030;
        color: #E8ECFF;
        overflow: hidden;
        isolation: isolate;
    }
    /* Dot grid, the way Stripe grounds its diagram: enough texture to read as
       a surface, not enough to compete with the connectors. */
    .zh-band::before {
        content: ""; position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background-image: radial-gradient(rgba(140,160,255,.16) 1px, transparent 1px);
        background-size: 26px 26px;
        -webkit-mask-image: radial-gradient(115% 90% at 50% 50%, #000 42%, transparent 82%);
                mask-image: radial-gradient(115% 90% at 50% 50%, #000 42%, transparent 82%);
    }
    /* One slow wash behind the hub so the centre reads as the light source. */
    .zh-band::after {
        content: ""; position: absolute; z-index: 0; pointer-events: none;
        left: 50%; top: 54%; width: min(760px, 84%); aspect-ratio: 1;
        transform: translate(-50%, -50%);
        background: radial-gradient(circle, rgba(61,107,255,.30) 0%, rgba(27,212,217,.10) 42%, transparent 68%);
    }
    .zh-inner { position: relative; z-index: 1; }

    .zh-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 14px; border-radius: 999px;
        font-size: 11px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase;
        color: #9FB4FF; background: rgba(61,107,255,.14); border: 1px solid rgba(61,107,255,.30);
    }
    .zh-dot { width: 6px; height: 6px; border-radius: 50%; background: #1bd4d9; }
    .zh-h {
        margin: 18px auto 0;
        /* The line is two sentences and it has to break between them, not
           inside the first: the whole point is the contrast across that full
           stop. "Zio is not eight tools." is 23 characters, so anything under
           24ch forces a break mid-clause -- 18ch and 22ch both produced
           "Zio is not eight / tools. It is one.", which reads as a stumble.
           `text-wrap: balance` then evens the two sentences out. */
        max-width: 24ch;
        font-size: clamp(28px, 4.6vw, 50px); font-weight: 800;
        line-height: 1.06; letter-spacing: -.035em; text-wrap: balance;
        /* !important because the marketing page's light mode paints every
           heading that is not .grad-text near-black
           (`html.light-mode h2:not(.grad-text)`, 0,2,1). That rule is right
           for a heading on the page's white ground and wrong for this one,
           which sits on a band that is dark in BOTH modes -- without this the
           first half of the line renders black on navy and disappears. */
        color: #fff !important;
    }
    .zh-h em { font-style: normal; background: linear-gradient(96deg, #6f9bff, #1bd4d9 70%, #22d3ee);
               -webkit-background-clip: text; background-clip: text;
               -webkit-text-fill-color: transparent; color: transparent; }
    /* Same reason as the heading: the page's light mode has opinions about
       paragraph colour that do not apply on a permanently dark band. */
    .zh-sub { margin: 16px auto 0; max-width: 58ch; font-size: 15.5px; line-height: 1.62; color: #A8B2D8 !important; }

    /* ---------- the zone intro, now the top of this band ---------- */
    /* Separated from the hub block by space rather than a rule: a divider
       here would put back the seam the move was meant to remove. */
    .zh-hub-block { margin-top: 88px; }
    @media (max-width: 767px) { .zh-hub-block { margin-top: 56px; } }

    /* The intro's headline is the zone's, so it is a step larger than the
       hub's own; both take the band's white from .zh-h. */
    .zh-h-intro { font-size: clamp(32px, 5.2vw, 58px); }

    .zh-pill {
        color: #9FB4FF; background: rgba(61,107,255,.14);
        border: 1px solid rgba(122,150,255,.28);
    }
    .zh-pill-dot {
        width: 7px; height: 7px; border-radius: 9999px; background: #6f9bff;
        box-shadow: 0 0 0 0 rgba(111,155,255,.6);
        animation: zhPillPulse 2.2s ease-out infinite;
    }
    @keyframes zhPillPulse {
        0%   { box-shadow: 0 0 0 0 rgba(111,155,255,.5); }
        70%  { box-shadow: 0 0 0 8px rgba(111,155,255,0); }
        100% { box-shadow: 0 0 0 0 rgba(111,155,255,0); }
    }

    /* The four jump links. Same shape as the chips they replace, recoloured
       for a permanently dark ground -- the originals were tuned for white
       and went invisible here. */
    .zh-chip {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: .8rem; font-weight: 700; padding: 8px 15px; border-radius: 9999px;
        color: #C7D0F0; background: rgba(255,255,255,.05);
        border: 1px solid rgba(160,180,255,.18);
        transition: background .2s ease, color .2s ease, border-color .2s ease;
    }
    .zh-chip:hover { color: #fff; background: rgba(61,107,255,.22); border-color: rgba(122,150,255,.45); }
    .zh-chip i { color: #6f9bff; font-size: .72rem; }
    @media (prefers-reduced-motion: reduce) {
        .zh-pill-dot { animation: none !important; }
        .zh-chip { transition: none; }
    }
    .zh-band .zh-eyebrow { color: #9FB4FF !important; }

    /* ---------- the stage ---------- */
    /* 1000x560 rather than 1000x640: at 640 the spokes were long enough that
       the middle of the diagram was mostly empty navy, which made the band
       taller than its content earned. */
    .zh-stage {
        position: relative; margin: 42px auto 0;
        max-width: 1000px; aspect-ratio: 1000 / 560;
    }
    .zh-wires { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .zh-wire  { fill: none; stroke: rgba(150,170,255,.26); stroke-width: 1; }
    /* The travelling packet. A 5px dash on a 210px gap is one dot on an
       otherwise empty wire; animating the offset walks it from the hub to the
       node. Each wire gets its own delay inline so they do not pulse in
       lockstep, which would read as a heartbeat rather than as traffic. */
    .zh-pulse {
        fill: none; stroke: #35E0E6; stroke-width: 2.4; stroke-linecap: round;
        stroke-dasharray: 5 210; stroke-dashoffset: 215;
        animation: zhFlow 3.2s linear infinite;
        filter: drop-shadow(0 0 5px rgba(53,224,230,.75));
    }
    @keyframes zhFlow { to { stroke-dashoffset: 0; } }

    /* ---------- nodes ---------- */
    .zh-node {
        position: absolute; transform: translate(-50%, -50%);
        display: inline-flex; align-items: center; gap: 9px;
        padding: 9px 14px; border-radius: 11px; white-space: nowrap;
        font-size: 13.5px; font-weight: 650; color: #E8ECFF;
        background: #1A2352; border: 1px solid rgba(150,170,255,.24);
        text-decoration: none;
        transition: background .2s ease, border-color .2s ease, transform .2s ease;
    }
    .zh-node:hover, .zh-node:focus-visible {
        background: #24307040; background-color: #243070;
        border-color: rgba(150,170,255,.5);
        transform: translate(-50%, -50%) scale(1.04);
    }
    .zh-node i { font-size: 12px; color: #7FE6EA; }

    /* ---------- the hub ---------- */
    .zh-hub {
        position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
        display: grid; place-items: center;
        /* align-content, and it matters more than it looks.

           This is a grid with a FIXED height and auto rows, so the default
           (`normal`, which behaves as stretch) hands each row a share of the
           leftover space and then `place-items: center` centres the item
           inside its own stretched row. The spacing you see is therefore
           whatever is left over, not anything chosen: with Zio in the card
           that came out as a 23px gap under him and a 21px gap between "Zio"
           and "YOUR AI", while the artwork itself was pressed against the top
           edge with 5px to spare and the antennae clipped by it.

           `center` makes the rows hug their content and centres the group, so
           the gaps below are the gaps that render and the block is optically
           centred without a magic offset. */
        align-content: center; gap: 0;
        width: 144px; height: 144px; border-radius: 28px;
        background: linear-gradient(150deg, #2C3BA8, #1B2570);
        border: 1px solid rgba(160,180,255,.38);
        box-shadow: 0 0 0 10px rgba(61,107,255,.10), 0 22px 50px -18px rgba(0,0,0,.7);
    }
    /* Zio sizes himself from the --size the include passes, and the antennae
       and eyelids are absolutely positioned against that box -- so pinning the
       <img> to 52px here would size the body without moving the layers on top
       of it, and his eyes would blink somewhere beside his head. The card was
       built around a 52px head and still gets one; it just comes from --size
       now. */
    /* The three gaps in the card, now that align-content above lets them be
       chosen: Zio, a breath, his name, a hair, his role. The name and the role
       are one caption, so the space between them is the smallest on the card
       -- otherwise they read as two separate labels that happen to be stacked,
       which is how the old leftover spacing made them look. */
    .zh-hub .zio-face { margin-bottom: 7px; }
    .zh-hub b { font-size: 14px; font-weight: 800; letter-spacing: -.01em; line-height: 1; color: #fff; }
    /* Was 9.5px at .16em, which made "YOUR AI" nearly as wide as the card and
       gave a two-word role label the presence of a heading. */
    .zh-hub span { margin-top: 4px; font-size: 8.5px; letter-spacing: .14em; text-transform: uppercase; color: #93A3DD; }
    /* A ring that breathes, so the centre is alive without anything moving. */
    .zh-hub::after {
        content: ""; position: absolute; inset: -14px; border-radius: 34px;
        border: 1px solid rgba(53,224,230,.34);
        animation: zhRing 3.6s ease-in-out infinite;
    }
    @keyframes zhRing {
        0%, 100% { transform: scale(1);    opacity: .55; }
        50%      { transform: scale(1.06); opacity: .18; }
    }

    /* ---------- narrow: a list, not a diagram ---------- */
    .zh-list { display: none; }
    @media (max-width: 899px) {
        .zh-stage { display: none; }
        .zh-list {
            display: grid; gap: 9px; margin: 34px auto 0; max-width: 420px;
            grid-template-columns: 1fr;
        }
        .zh-list-hub {
            display: flex; align-items: center; gap: 11px; justify-content: center;
            padding: 14px; border-radius: 14px; margin-bottom: 6px;
            background: linear-gradient(150deg, #2C3BA8, #1B2570);
            border: 1px solid rgba(160,180,255,.38);
        }
        /* Same reason as .zh-hub .zio-face above: the face sizes itself. */
        .zh-list-hub .zio-face { flex: 0 0 auto; }
        .zh-list-hub b { font-size: 15px; color: #fff; }
        .zh-list a {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 11px; text-decoration: none;
            font-size: 14px; font-weight: 650; color: #E8ECFF;
            background: #1A2352; border: 1px solid rgba(150,170,255,.24);
        }
        .zh-list a i { font-size: 12px; color: #7FE6EA; width: 16px; text-align: center; }
    }

    @media (prefers-reduced-motion: reduce) {
        /* The wires stay, the traffic stops. A packet frozen mid-wire would
           read as a defect, so the pulses are hidden rather than paused. */
        .zh-pulse { animation: none !important; opacity: 0 !important; }
        .zh-hub::after { animation: none !important; }
        .zh-node { transition: none !important; }
    }
</style>

<section class="zh-band sec-ground pt-24 lg:pt-32 pb-20 lg:pb-28" aria-labelledby="zh-h">

    {{-- The AI zone's intro used to sit in its own section immediately above
         this band, on the page's normal ground. That put a hard seam between
         "Meet Zio" and the diagram that answers it, and made the band look
         like a separate thing dropped into the middle of the zone rather
         than the top of it.

         It is now the first block INSIDE the band, so the dark ground starts
         at "Meet Zio" and runs unbroken through the diagram. The markup came
         across as-is except for the classes that painted its text for the
         page's white ground; on this band those are wrong in both modes, so
         the band's own zh- type styles carry it instead. --}}
    <div class="zh-inner zh-intro max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="reveal inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-[.2em] zh-pill">
            <span class="zh-pill-dot" aria-hidden="true"></span> Meet Zio
        </div>
        <h2 class="reveal rd-1 zh-h zh-h-intro">One AI that builds and runs it all.</h2>
        <p class="reveal rd-2 zh-sub">
            From your Link in Bio to your phone line, Sayzio ships a whole crew of AI coworkers, a page
            builder, a chatbot, an agent, an embeddable widget, a voice receptionist, a marketing
            strategist and a WhatsApp teammate. One login, all grounded in your real data.
        </p>
        <div class="reveal rd-3 mt-7 flex flex-wrap items-center justify-center gap-2.5">
            <a href="#ai-suite" class="zh-chip"><i class="fas fa-robot"></i> Chatbot &amp; Agent</a>
            <a href="#ai-marketing-strategist" class="zh-chip"><i class="fas fa-chart-line"></i> AI Marketing Strategist</a>
            <a href="#whatsapp-agent" class="zh-chip"><i class="fab fa-whatsapp"></i> WhatsApp Agent</a>
            <a href="#ai-dashboard" class="zh-chip"><i class="fas fa-gauge-high"></i> AI Dashboard</a>
        </div>
    </div>

    <div class="zh-inner zh-hub-block max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">

        <p class="reveal zh-eyebrow"><span class="zh-dot" aria-hidden="true"></span> One brain, every surface</p>
        <h2 id="zh-h" class="reveal rd-1 zh-h">Zio is not eight tools. <em>It is one.</em></h2>
        <p class="reveal rd-2 zh-sub">
            The chatbot answering on your page, the agent chasing a lead, the voice picking up your phone
            and the strategist reading your numbers are the same AI, reading the same data about you.
            Teach it once and every surface knows.
        </p>

        {{-- Wide: the diagram. The SVG is decorative; every node is also a
             real link, and the list below carries the same links for narrow
             screens and for anyone the diagram does not work for. --}}
        <div class="reveal rd-3 zh-stage">
            <svg class="zh-wires" viewBox="0 0 1000 560" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                @foreach($zioNodes as $i => $n)
                    @php
                        // Node centre in viewBox units, then stepped back to the
                        // edge the connector should meet so the line stops at the
                        // chip rather than running under its label.
                        $nx = $n['x'] * 10;
                        $ny = $n['y'] * 5.6;
                        $ex = $nx + ($n['side'] === 'r' ? 78 : ($n['side'] === 'l' ? -78 : 0));
                        $ey = $ny + ($n['side'] === 'b' ? 22 : ($n['side'] === 't' ? -22 : 0));
                        // Elbow through the mid-point, curved: a straight spoke
                        // diagram reads as a starburst, and the point here is
                        // routing, not radiance.
                        $cx = 500 + ($ex - 500) * 0.45;
                        $cy = 280 + ($ey - 280) * 0.92;
                        $d  = "M500,280 C{$cx},280 {$ex},{$cy} {$ex},{$ey}";
                    @endphp
                    <path class="zh-wire" d="{{ $d }}"/>
                    <path class="zh-pulse" d="{{ $d }}" style="animation-delay: {{ number_format($i * 0.38, 2) }}s"/>
                @endforeach
            </svg>

            {{-- The same Zio as the hero, not a still of him. This section's
                 whole claim is that the eight chips around him are one AI, and
                 a flat head at the centre of a diagram about a living assistant
                 was arguing the opposite. 124px of canvas puts the face at the
                 52px the card was already built for.

                 No mouth: it is timed to the hero's speech bubbles and there
                 are none here, so he keeps the painted smile. --}}
            <div class="zh-hub">
                @include('home.partials.zio-face', [
                    'size'    => '196px',
                    'mouth'   => false,
                    'loading' => 'lazy',
                ])
                <b>Zio</b>
                <span>your AI</span>
            </div>

            @foreach($zioNodes as $n)
                <a class="zh-node" href="{{ $n['href'] }}" style="left: {{ $n['x'] }}%; top: {{ $n['y'] }}%;">
                    <i class="fas {{ $n['icon'] }}" aria-hidden="true"></i>{{ $n['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Narrow: same links, no diagram. --}}
        <div class="zh-list">
            <div class="zh-list-hub">
                @include('home.partials.zio-face', [
                    'size'    => '102px',
                    'mouth'   => false,
                    'loading' => 'lazy',
                ])
                <b>Zio</b>
            </div>
            @foreach($zioNodes as $n)
                <a href="{{ $n['href'] }}"><i class="fas {{ $n['icon'] }}" aria-hidden="true"></i>{{ $n['label'] }}</a>
            @endforeach
        </div>

    </div>
</section>
