{{-- ============================ DIALER & CONTACTS ============================ --}}
<style>
    /* Dialer & Contacts section animations */
    .dc-wrap { position: relative; }
    .dc-mesh::before {
        content:""; position:absolute; inset:-20%;
        background: rgba(27,212,217,.06);
        filter: blur(40px); pointer-events:none; z-index:0;
        animation: dcMesh 15s ease-in-out infinite alternate;
    }
    @keyframes dcMesh { 0% { transform: translate3d(0,0,0) scale(1); } 100% { transform: translate3d(-2%,2%,0) scale(1.06); } }

    /* Phone frame that houses the flipping dialer / in-call screens.
       Everything rendered inside the screen uses scoped dc-* classes with
       explicit colors so the global html.light-mode gray-text remapping can
       never darken text on this always-dark phone display. */
    .dc-phone {
        position: relative; width: 300px; max-width: 84vw; margin: 0 auto;
        aspect-ratio: 300 / 600; border-radius: 42px; padding: 12px;
        background: linear-gradient(160deg, #1b1030, #0c0718);
        box-shadow:
            0 40px 90px -30px rgba(61,107,255,.55),
            0 14px 34px -12px rgba(0,0,0,.7),
            inset 0 0 0 1.5px rgba(255,255,255,.08);
        animation: dcFloat 6.5s ease-in-out infinite;
    }
    html.light-mode .dc-phone { background: linear-gradient(160deg, #101827, #060b16); }
    @keyframes dcFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }

    /* 3D flip stage: front = dialer keypad, back = in-call caller-ID screen */
    .dc-stage { position: absolute; inset: 12px; perspective: 1400px; }
    .dc-flip { position: absolute; inset: 0; transform-style: preserve-3d; will-change: transform; }
    .dc-face {
        position: absolute; inset: 0; border-radius: 32px; overflow: hidden;
        backface-visibility: hidden; -webkit-backface-visibility: hidden;
        background: linear-gradient(180deg, #14091f 0%, #0a0a14 100%);
        display: flex; flex-direction: column; color: #fff;
    }
    .dc-back { transform: rotateY(180deg); background: linear-gradient(180deg, #101733 0%, #0a0a14 100%); }
    .dc-notch {
        position:absolute; top: 8px; left: 50%; transform: translateX(-50%);
        width: 86px; height: 20px; border-radius: 999px; background: #05030a; z-index: 5;
    }
    .dc-status {
        display:flex; align-items:center; justify-content:space-between;
        padding: 12px 20px 0; font-size: 10px; font-weight: 700;
        color: #8f9bb8; letter-spacing: .04em;
    }
    .dc-status i { font-size: 9px; margin-left: 5px; color: #8f9bb8; }

    /* Number display above the keypad (types out digits like a real dialer) */
    .dc-numwrap { margin: auto 20px 4px; text-align: center; min-height: 66px; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap: 8px; }
    .dc-match {
        display:inline-flex; align-items:center; gap: 6px;
        padding: 4px 10px 4px 5px; border-radius: 999px;
        background: rgba(61,107,255,.16); border: 1px solid rgba(61,107,255,.4);
        font-size: 10px; font-weight: 700; color: #dbe4ff; opacity: 0;
    }
    .dc-match img { width: 18px; height: 18px; border-radius: 999px; object-fit: cover; }
    .dc-match i { color: #7a9eff; font-size: 9px; }
    .dc-numdisplay { min-height: 30px; display:flex; align-items:center; justify-content:center; }
    .dc-numdigits { display:inline-flex; align-items:center; }
    .dc-digit {
        display:inline-block; opacity: 0;
        font-size: 22px; font-weight: 600; color: #fff; letter-spacing: .06em;
        font-variant-numeric: tabular-nums;
    }
    .dc-digit.gp { margin-left: 8px; }
    .dc-caret {
        display:inline-block; width: 2px; height: 22px; margin-left: 4px;
        background: #3d6bff; border-radius: 2px;
        animation: dcCaret 1.1s steps(2, start) infinite;
    }
    @keyframes dcCaret { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }

    /* T9 keypad */
    .dc-keys { margin: 12px 22px 14px; display:grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
    .dc-key {
        aspect-ratio: 1; border-radius: 999px;
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        color:#fff; line-height: 1;
    }
    .dc-key .d { font-size: 18px; font-weight: 700; }
    .dc-key .l { font-size: 7px; letter-spacing: .12em; color: rgba(255,255,255,.45); margin-top: 2px; }

    /* Dual-SIM call buttons */
    .dc-sims { display:flex; gap: 10px; margin: 0 22px 18px; }
    .dc-sim {
        flex: 1; height: 46px; border-radius: 999px;
        display:flex; align-items:center; justify-content:center; gap: 7px;
        color:#fff; font-weight: 700; font-size: 12.5px;
    }
    .dc-sim-1 {
        background: linear-gradient(135deg, #16a34a, #22c55e);
        box-shadow: 0 12px 26px -12px rgba(34,197,94,.8);
    }
    .dc-sim-2 {
        background: linear-gradient(135deg, #0d9488, #14b8a6);
        box-shadow: 0 12px 26px -12px rgba(20,184,166,.7);
    }
    .dc-sim .sb {
        width: 15px; height: 15px; border-radius: 4px 4px 4px 1px;
        background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.55);
        display:flex; align-items:center; justify-content:center;
        font-size: 9px; font-weight: 800; color:#fff;
    }

    /* Quick-channel strip on the dialer face (animates with the T9 match chip) */
    .dc-dialchans { display:flex; align-items:center; justify-content:center; gap: 7px; margin-top: 5px; opacity: 0; }
    .dc-dialchan {
        width: 28px; height: 28px; border-radius: 9px;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 11px; flex-shrink:0;
    }
    .dc-dialchan-label { font-size: 7.5px; font-weight: 700; color: rgba(255,255,255,.5); margin-top: 2px; text-align:center; display:block; }

    /* Back face: incoming-call / caller-ID screen */
    .dc-call-body { display:flex; flex-direction:column; align-items:center; text-align:center; padding: 40px 20px 16px; height: 100%; }
    .dc-cid-pill {
        display:inline-flex; align-items:center; gap: 6px;
        padding: 5px 12px; border-radius: 999px;
        background: rgba(61,107,255,.16); border: 1px solid rgba(61,107,255,.4);
        font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
        color: #dbe4ff;
    }
    .dc-cid-pill i { color: #7a9eff; font-size: 9px; }
    .dc-avatar-lg {
        position: relative; width: 84px; height: 84px; border-radius: 28px;
        margin-top: 18px; flex-shrink: 0;
        background: linear-gradient(135deg, #3d6bff, #1bd4d9);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight: 800; font-size: 26px;
        box-shadow: 0 16px 36px -12px rgba(61,107,255,.75);
    }
    .dc-avatar-lg img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius: inherit; }
    .dc-avatar-lg::before {
        content:""; position:absolute; inset:-9px; border-radius: 34px;
        border: 2px solid rgba(61,107,255,.5); opacity:.5;
    }
    .dc-call-name { margin-top: 14px; font-size: 17px; font-weight: 800; color:#fff; display:flex; align-items:center; gap: 6px; }
    .dc-call-name i { color:#3d6bff; font-size: 13px; }
    .dc-call-handle { margin-top: 2px; font-size: 11px; color: #a8b3cf; font-weight: 600; }
    .dc-call-num { margin-top: 7px; font-size: 12.5px; color: #cbd5e1; font-weight: 600; letter-spacing: .04em; font-variant-numeric: tabular-nums; }
    .dc-call-status { margin-top: 10px; font-size: 11px; font-weight: 700; color: #34d399; display:flex; align-items:center; gap: 2px; }
    .dc-dot { display:inline-block; width: 3.5px; height: 3.5px; border-radius: 999px; background: #34d399; margin-left: 3px; }
    /* Incoming-call action buttons */
    .dc-incall-actions { margin-top: auto; width: 100%; display:flex; flex-direction:column; align-items:center; gap: 8px; padding-bottom: 2px; }
    .dc-incall-msg { display:flex; flex-direction:column; align-items:center; gap: 3px; }
    .dc-msgbtn {
        width: 34px; height: 34px; border-radius: 999px;
        background: rgba(255,255,255,.11); border: 1px solid rgba(255,255,255,.18);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 12px;
    }
    .dc-incall-main { display:flex; align-items:center; justify-content:center; gap: 30px; }
    .dc-decline, .dc-answer { display:flex; flex-direction:column; align-items:center; gap: 5px; }
    .dc-decline-btn {
        width: 54px; height: 54px; border-radius: 999px;
        background: linear-gradient(135deg, #dc2626, #ef4444);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 19px;
        box-shadow: 0 14px 30px -12px rgba(239,68,68,.75);
    }
    .dc-answer-btn {
        width: 54px; height: 54px; border-radius: 999px;
        background: linear-gradient(135deg, #16a34a, #22c55e);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 19px;
        box-shadow: 0 14px 30px -12px rgba(34,197,94,.75);
    }
    .dc-btn-label { font-size: 9px; font-weight: 700; color: rgba(255,255,255,.5); letter-spacing:.03em; }
    .dc-smalllabel { font-size: 8px; font-weight: 700; color: rgba(255,255,255,.45); }

    @media (prefers-reduced-motion: no-preference) {
        /* 12s master loop: dial digits -> press SIM 1 -> flip to in-call
           caller-ID screen -> flip back. All keyframe percentages are of 12s. */
        .dc-armed .dc-flip { animation: dcFlip 12s ease-in-out infinite; }
        @keyframes dcFlip {
            0%, 38% { transform: rotateY(0deg); }
            45%     { transform: rotateY(180deg); }
            88%     { transform: rotateY(180deg); }
            95%     { transform: rotateY(360deg); }
            100%    { transform: rotateY(360deg); }
        }

        /* Digits type into the number display in sync with the keys
           (per-digit animation-delay set inline; presses at 0.6s + n*0.3s). */
        .dc-armed .dc-digit { animation: dcDigitIn 12s linear infinite; }
        @keyframes dcDigitIn {
            0%       { opacity: 0; transform: translateY(4px) scale(.85); }
            1%       { opacity: 1; transform: none; }
            50%      { opacity: 1; }
            53%,100% { opacity: 0; }
        }
        /* Whole display clears while the phone is flipped (call in progress) */
        .dc-armed .dc-numdigits { animation: dcNumWrap 12s linear infinite; }
        @keyframes dcNumWrap {
            0%, 42%  { opacity: 1; }
            45%, 99% { opacity: 0; }
            100%     { opacity: 1; }
        }
        /* T9 match chip appears once enough digits are in */
        .dc-armed .dc-match { animation: dcMatch 12s ease-in-out infinite; }
        @keyframes dcMatch {
            0%, 18%  { opacity: 0; transform: translateY(4px); }
            21%, 41% { opacity: 1; transform: none; }
            44%,100% { opacity: 0; }
        }

        /* Key press flashes — one keyframe set per key, percentages encode
           every press of that key inside the 12s loop (multiple comma
           animations on one element would override each other). */
        .dc-armed .dc-key.k4 { animation: dcK4 12s linear infinite; }
        .dc-armed .dc-key.k1 { animation: dcK1 12s linear infinite; }
        .dc-armed .dc-key.k5 { animation: dcK5 12s linear infinite; }
        .dc-armed .dc-key.k0 { animation: dcK0 12s linear infinite; }
        .dc-armed .dc-key.k8 { animation: dcK8 12s linear infinite; }
        .dc-armed .dc-key.k2 { animation: dcK2 12s linear infinite; }
        @keyframes dcK4 {
            0%, 4%   { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            5%       { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            6.2%     { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            7.5%,100%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcK1 {
            0%, 6.5% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            7.5%     { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            8.7%     { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            10%,21.5%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            22.5%    { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            23.7%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            25%,100% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcK5 {
            0%, 9%   { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            10%      { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            11.2%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            12.5%    { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            13.7%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            15%      { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            16.2%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            17.5%    { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            18.7%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            20%,100% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcK0 {
            0%, 19%  { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            20%      { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            21.2%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            22.5%,100%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcK8 {
            0%, 24%  { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            25%      { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            26.2%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            27.5%,100%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcK2 {
            0%, 26.5%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            27.5%    { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            28.7%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            30%,100% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }

        /* SIM 1 gets "pressed" right after the number is complete */
        .dc-armed .dc-sim-1 { animation: dcSimPress 12s ease-in-out infinite; }
        @keyframes dcSimPress {
            0%, 31.5% { transform: none; filter: none; }
            33%       { transform: scale(.93); filter: brightness(1.35); }
            35%, 100% { transform: none; filter: none; }
        }

        /* Quick-channel strip on the dialer face: appear with the T9 match chip */
        .dc-armed .dc-dialchans { animation: dcMatch 12s ease-in-out infinite; }

        /* In-call screen life: pulsing avatar ring + calling dots */
        .dc-armed .dc-avatar-lg::before { animation: dcRing 2.2s ease-in-out infinite; }
        @keyframes dcRing { 0%,100% { transform: scale(1); opacity:.4; } 50% { transform: scale(1.12); opacity:.85; } }
        .dc-armed .dc-dot { animation: dcDot 1.2s ease-in-out infinite; }
        .dc-armed .dc-dot:nth-child(2) { animation-delay: .18s; }
        .dc-armed .dc-dot:nth-child(3) { animation-delay: .36s; }
        @keyframes dcDot { 0%,100% { opacity:.25; transform: translateY(0); } 40% { opacity:1; transform: translateY(-2.5px); } }
    }

    /* Feature pills */
    .dc-feat { transition: transform .35s ease, background .35s ease, border-color .35s ease; }
    .dc-feat:hover { transform: translateX(6px); border-color: rgba(27,212,217,.45); background: rgba(27,212,217,.08); }
    .dc-feat-icon {
        width: 44px; height: 44px; border-radius: 14px;
        display:flex; align-items:center; justify-content:center; flex-shrink:0;
        color: #fff; box-shadow: 0 12px 28px -10px var(--dc-c, #3d6bff);
        background: var(--dc-c, #3d6bff); position: relative;
    }
    .dc-feat-icon::after {
        content:""; position:absolute; inset:-5px; border-radius:18px;
        border: 2px solid color-mix(in srgb, var(--dc-c, #3d6bff) 50%, transparent);
        opacity:.35; animation: dcPulse 2.6s ease-in-out infinite;
    }
    @keyframes dcPulse { 0%,100% { transform: scale(1); opacity:.25; } 50% { transform: scale(1.08); opacity:.6; } }

    .dc-bubble {
        position: absolute; padding: 8px 12px; border-radius: 14px;
        background: rgba(15,18,28,.85); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,.08); color:#fff;
        font-size: 11px; font-weight: 700; display:flex; align-items:center; gap:8px;
        box-shadow: 0 18px 40px -16px rgba(0,0,0,.7); z-index: 6;
        animation: dcBubble 6s ease-in-out infinite;
    }
    .dc-bubble i { color: #1bd4d9; }
    .dc-bubble-1 { top: 4%; right: -14px; animation-delay: .3s; }
    .dc-bubble-2 { bottom: 10%; left: -18px; animation-delay: 1.6s; }
    @keyframes dcBubble { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

    @media (prefers-reduced-motion: reduce) {
        .dc-mesh::before, .dc-phone, .dc-bubble, .dc-feat-icon::after, .dc-caret { animation: none !important; }
        /* Static, fully-visible caller-ID state: show the in-call face only */
        .dc-flip { transform: none !important; }
        .dc-front { display: none !important; }
        .dc-back { transform: none !important; }
        .dc-match { opacity: 1 !important; transform: none !important; }
    }
</style>
<section id="dialer-contacts" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="dc-h">
    <div class="dc-mesh absolute inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Dialer &amp; Contacts</div>
            <h2 id="dc-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Your whole phonebook,<br><span class="grad-text">turned into profiles.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A smart T9 dialer, quick call / SMS / WhatsApp channels, two-way Google Contacts sync and phone-to-biolink caller&nbsp;ID &mdash; so every number in your address book becomes a rich, connected profile.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- LEFT: animated phone / dialer --}}
            <div class="reveal rd-3 dc-wrap relative flex justify-center">
                <div class="dc-phone" role="img" aria-label="Sayzio dialer typing a phone number on a dual-SIM keypad, then flipping to a caller-ID screen that resolves the number into a verified Sayzio profile">
                    <div class="dc-stage" aria-hidden="true">
                        <div class="dc-flip">

                            {{-- FRONT: dialer keypad --}}
                            <div class="dc-face dc-front">
                                <div class="dc-notch"></div>
                                <div class="dc-status">
                                    <span>9:41</span>
                                    <span><i class="fas fa-signal"></i><i class="fas fa-wifi"></i><i class="fas fa-battery-three-quarters"></i></span>
                                </div>

                                {{-- Number display + T9 match chip --}}
                                @php
                                    // Digits typed into the display, in press order (0.6s + n*0.3s).
                                    $dial = ['4','1','5','5','5','5','0','1','8','2'];
                                    // Keys that flash — keyframes per key encode every press time.
                                    $keyAnim = ['4' => 'k4', '1' => 'k1', '5' => 'k5', '0' => 'k0', '8' => 'k8', '2' => 'k2'];
                                @endphp
                                <div class="dc-numwrap">
                                    <div class="dc-match">
                                        <img src="{{ asset('images/marketing/contact-aisha.jpg') }}" alt="" loading="lazy" onerror="this.remove()">
                                        Aisha Rahman <i class="fas fa-circle-check"></i>
                                    </div>
                                    {{-- Quick-channel icons appear with the T9 match --}}
                                    <div class="dc-dialchans">
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dc-dialchan" style="background:#3d6bff;"><i class="fas fa-comment-sms"></i></div>
                                            <span class="dc-dialchan-label">SMS</span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dc-dialchan" style="background:#25d366;"><i class="fab fa-whatsapp"></i></div>
                                            <span class="dc-dialchan-label">WhatsApp</span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dc-dialchan" style="background:#229ed9;"><i class="fab fa-telegram"></i></div>
                                            <span class="dc-dialchan-label">Telegram</span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dc-dialchan" style="background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.22);"><i class="fas fa-link" style="font-size:9px;"></i></div>
                                            <span class="dc-dialchan-label">Biolink</span>
                                        </div>
                                    </div>
                                    <div class="dc-numdisplay">
                                        <span class="dc-numdigits">
                                            @foreach($dial as $i => $d)
                                                <span class="dc-digit {{ in_array($i, [3, 6], true) ? 'gp' : '' }}" style="animation-delay: {{ number_format(0.6 + $i * 0.3, 1) }}s;">{{ $d }}</span>
                                            @endforeach
                                            <span class="dc-caret"></span>
                                        </span>
                                    </div>
                                </div>

                                {{-- T9 keypad --}}
                                <div class="dc-keys">
                                    @foreach([
                                        ['1',''], ['2','ABC'], ['3','DEF'],
                                        ['4','GHI'], ['5','JKL'], ['6','MNO'],
                                        ['7','PQRS'], ['8','TUV'], ['9','WXYZ'],
                                        ['*',''], ['0','+'], ['#',''],
                                    ] as $k)
                                        <div class="dc-key {{ $keyAnim[$k[0]] ?? '' }}">
                                            <span class="d">{{ $k[0] }}</span>
                                            @if($k[1] !== '')<span class="l">{{ $k[1] }}</span>@endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Dual-SIM call buttons --}}
                                <div class="dc-sims">
                                    <div class="dc-sim dc-sim-1"><span class="sb">1</span> <i class="fas fa-phone text-[11px]"></i> SIM 1</div>
                                    <div class="dc-sim dc-sim-2"><span class="sb">2</span> <i class="fas fa-phone text-[11px]"></i> SIM 2</div>
                                </div>
                            </div>

                            {{-- BACK: in-call / caller-ID screen --}}
                            <div class="dc-face dc-back">
                                <div class="dc-notch"></div>
                                <div class="dc-call-body">
                                    <div class="dc-cid-pill"><i class="fas fa-address-card"></i> Sayzio Caller ID</div>
                                    <div class="dc-avatar-lg">
                                        AR
                                        <img src="{{ asset('images/marketing/contact-aisha.jpg') }}" alt="" loading="lazy" onerror="this.remove()">
                                    </div>
                                    <div class="dc-call-name">Aisha Rahman <i class="fas fa-circle-check"></i></div>
                                    <div class="dc-call-handle">@aisha &middot; on Sayzio</div>
                                    <div class="dc-call-num">+1 (415) 555-0182</div>
                                    <div class="dc-call-status">
                                        <i class="fas fa-phone-volume" style="font-size:9px;margin-right:3px;"></i> Incoming call &middot; SIM 1
                                        <span class="dc-dot"></span><span class="dc-dot"></span><span class="dc-dot"></span>
                                    </div>
                                    <div class="dc-incall-actions">
                                        <div class="dc-incall-msg">
                                            <div class="dc-msgbtn"><i class="fas fa-comment-sms"></i></div>
                                            <span class="dc-smalllabel">Message</span>
                                        </div>
                                        <div class="dc-incall-main">
                                            <div class="dc-decline">
                                                <div class="dc-decline-btn"><i class="fas fa-phone-slash"></i></div>
                                                <span class="dc-btn-label">Decline</span>
                                            </div>
                                            <div class="dc-answer">
                                                <div class="dc-answer-btn"><i class="fas fa-phone"></i></div>
                                                <span class="dc-btn-label">Answer</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="dc-bubble dc-bubble-1" aria-hidden="true"><i class="fas fa-rotate"></i> Google Contacts synced</div>
                <div class="dc-bubble dc-bubble-2" aria-hidden="true"><i class="fas fa-id-card" style="color:#ff8a3c;"></i> Card scanned &middot; 1.4s</div>
            </div>

            {{-- RIGHT: features --}}
            <div class="space-y-4">
                @foreach([
                    ['fa-phone-volume', '#1bd4d9', 'Smart T9 dialer &amp; keypad',   'Type a name on the keypad and T9 finds the contact. Flip to a full alphanumeric keyboard whenever you want.'],
                    ['fa-comments',     '#3d6bff', 'Quick channels, one tap',       'Call, SMS, WhatsApp, Telegram, Signal or Viber &mdash; jump straight into the right app from any contact.'],
                    ['fa-rotate',       '#e94e8c', 'Two-way Google Contacts sync',  'Contacts stay in lockstep with Google via the People API &mdash; add or edit anywhere, changes flow both ways.'],
                    ['fa-magnifying-glass', '#ff8a3c', 'Universal finder',          'One search spans contacts, people on Sayzio, your links, biolinks and workspaces &mdash; grouped and ready to act.'],
                    ['fa-id-card',      '#22c55e', 'AI business-card scanner',      'Snap a card or brochure and AI extracts the name, numbers, emails and socials into a clean new contact.'],
                    ['fa-address-card', '#22d3ee', 'Phone &rarr; biolink caller ID', 'Numbers resolve to rich Sayzio profiles, and a tap exports any contact as a shareable vCard.'],
                ] as $i => $f)
                    <div class="reveal rd-{{ ($i % 4) + 1 }} dc-feat glass rounded-2xl p-4 flex items-start gap-4">
                        <div class="dc-feat-icon" style="--dc-c: {{ $f[1] }};"><i class="fas {{ $f[0] }}"></i></div>
                        <div class="min-w-0">
                            <div class="text-base font-bold mb-1">{!! $f[2] !!}</div>
                            <div class="text-sm text-gray-400 leading-relaxed">{!! $f[3] !!}</div>
                        </div>
                    </div>
                @endforeach

                <div class="reveal rd-4 pt-3 flex flex-wrap items-center gap-3">
                    <a href="{{ route('site.dialer-contacts') }}" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Explore Dialer &amp; Contacts <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ route('site.dialer-contacts') }}#sync" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold transition-colors">
                        See how sync works <i class="fas fa-rotate text-[10px]"></i>
                    </a>
                </div>

                <div class="reveal rd-4 pt-2">
                    <div class="text-[11px] font-bold uppercase tracking-[.14em] text-gray-500 mb-2.5">Take the dialer with you</div>
                    @include('public.partials.store-buttons')
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var section = document.getElementById('dialer-contacts');
    if (!section) return;
    var wrap = section.querySelector('.dc-wrap');
    if (!wrap) return;

    var reduceMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduceMq.matches) return;
    if (typeof window.IntersectionObserver !== 'function') return;

    var io = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
            wrap.classList.toggle('dc-armed', entries[i].isIntersecting);
        }
    }, { threshold: 0.25, rootMargin: '0px 0px -10% 0px' });
    io.observe(section);
})();
</script>
