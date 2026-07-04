@extends('public.layouts.site')
@section('title', 'Smart Dialer &amp; Contacts')

@section('content')
@php
    $accent = '#3d6bff';
@endphp

<style>
    .dcp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(61,107,255,.06);
        filter: blur(40px); pointer-events:none;
        animation: dcpMesh 15s ease-in-out infinite alternate;
    }
    @keyframes dcpMesh { 0% { transform: translate3d(0,0,0); } 100% { transform: translate3d(-2%,2%,0) scale(1.05); } }

    /* Hero phone */
    .dcp-phone {
        position: relative; width: 290px; max-width: 82vw; margin: 0 auto;
        aspect-ratio: 290 / 590; border-radius: 42px; padding: 12px;
        background: linear-gradient(160deg, #1b1030, #0c0718);
        box-shadow: 0 40px 90px -30px rgba(61,107,255,.55), 0 14px 34px -12px rgba(0,0,0,.7), inset 0 0 0 1.5px rgba(255,255,255,.08);
        animation: dcpFloat 6.5s ease-in-out infinite;
    }
    @keyframes dcpFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }

    /* 3D flip stage: front = dialer keypad, back = in-call caller-ID screen.
       Everything rendered inside the screen uses scoped dcp-* classes with
       explicit colors so the global html.light-mode gray-text remapping can
       never darken text on this always-dark phone display. */
    .dcp-stage { position: absolute; inset: 12px; perspective: 1400px; }
    .dcp-flip { position: absolute; inset: 0; transform-style: preserve-3d; will-change: transform; }
    .dcp-face {
        position: absolute; inset: 0; border-radius: 32px; overflow: hidden;
        backface-visibility: hidden; -webkit-backface-visibility: hidden;
        background: linear-gradient(180deg, #14091f 0%, #0a0a14 100%);
        display: flex; flex-direction: column; color: #fff;
    }
    .dcp-back { transform: rotateY(180deg); background: linear-gradient(180deg, #101733 0%, #0a0a14 100%); }
    .dcp-notch { position:absolute; top:8px; left:50%; transform:translateX(-50%); width:84px; height:20px; border-radius:999px; background:#05030a; z-index:5; }
    .dcp-status {
        display:flex; align-items:center; justify-content:space-between;
        padding: 12px 20px 0; font-size: 10px; font-weight: 700;
        color: #8f9bb8; letter-spacing: .04em;
    }
    .dcp-status i { font-size: 9px; margin-left: 5px; color: #8f9bb8; }

    /* Number display above the keypad (types out digits like a real dialer) */
    .dcp-numwrap { margin: auto 20px 4px; text-align: center; min-height: 66px; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap: 8px; }
    .dcp-match {
        display:inline-flex; align-items:center; gap: 6px;
        padding: 4px 10px 4px 5px; border-radius: 999px;
        background: rgba(61,107,255,.16); border: 1px solid rgba(61,107,255,.4);
        font-size: 10px; font-weight: 700; color: #dbe4ff; opacity: 0;
    }
    .dcp-match img { width: 18px; height: 18px; border-radius: 999px; object-fit: cover; }
    .dcp-match i { color: #7a9eff; font-size: 9px; }
    .dcp-numdisplay { min-height: 30px; display:flex; align-items:center; justify-content:center; }
    .dcp-numdigits { display:inline-flex; align-items:center; }
    .dcp-digit {
        display:inline-block; opacity: 0;
        font-size: 22px; font-weight: 600; color: #fff; letter-spacing: .06em;
        font-variant-numeric: tabular-nums;
    }
    .dcp-digit.gp { margin-left: 8px; }
    .dcp-caret {
        display:inline-block; width: 2px; height: 22px; margin-left: 4px;
        background: #3d6bff; border-radius: 2px;
        animation: dcpCaret 1.1s steps(2, start) infinite;
    }
    @keyframes dcpCaret { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }

    /* T9 keypad */
    .dcp-keys { margin:12px 22px 14px; display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .dcp-key { aspect-ratio:1; border-radius:999px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; line-height:1; }
    .dcp-key .d { font-size:18px; font-weight:700; }
    .dcp-key .l { font-size:7px; letter-spacing:.12em; color:rgba(255,255,255,.45); margin-top:2px; }

    /* Dual-SIM call buttons */
    .dcp-sims { display:flex; gap: 10px; margin: 0 22px 18px; }
    .dcp-sim {
        flex: 1; height: 46px; border-radius: 999px;
        display:flex; align-items:center; justify-content:center; gap: 7px;
        color:#fff; font-weight: 700; font-size: 12.5px;
    }
    .dcp-sim-1 {
        background: linear-gradient(135deg, #16a34a, #22c55e);
        box-shadow: 0 12px 26px -12px rgba(34,197,94,.8);
    }
    .dcp-sim-2 {
        background: linear-gradient(135deg, #0d9488, #14b8a6);
        box-shadow: 0 12px 26px -12px rgba(20,184,166,.7);
    }
    .dcp-sim .sb {
        width: 15px; height: 15px; border-radius: 4px 4px 4px 1px;
        background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.55);
        display:flex; align-items:center; justify-content:center;
        font-size: 9px; font-weight: 800; color:#fff;
    }

    /* Quick-channel strip on the dialer face (animates with the T9 match chip) */
    .dcp-dialchans { display:flex; align-items:center; justify-content:center; gap: 7px; margin-top: 5px; opacity: 0; }
    .dcp-dialchan {
        width: 28px; height: 28px; border-radius: 9px;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 11px; flex-shrink:0;
    }
    .dcp-dialchan-label { font-size: 7.5px; font-weight: 700; color: rgba(255,255,255,.5); margin-top: 2px; text-align:center; display:block; }

    /* Back face: incoming-call / caller-ID screen */
    .dcp-call-body { display:flex; flex-direction:column; align-items:center; text-align:center; padding: 40px 20px 16px; height: 100%; }
    .dcp-cid-pill {
        display:inline-flex; align-items:center; gap: 6px;
        padding: 5px 12px; border-radius: 999px;
        background: rgba(61,107,255,.16); border: 1px solid rgba(61,107,255,.4);
        font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
        color: #dbe4ff;
    }
    .dcp-cid-pill i { color: #7a9eff; font-size: 9px; }
    .dcp-avatar-lg {
        position: relative; width: 84px; height: 84px; border-radius: 28px;
        margin-top: 18px; flex-shrink: 0;
        background: linear-gradient(135deg, #3d6bff, #1bd4d9);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight: 800; font-size: 26px;
        box-shadow: 0 16px 36px -12px rgba(61,107,255,.75);
    }
    .dcp-avatar-lg img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius: inherit; }
    .dcp-avatar-lg::before {
        content:""; position:absolute; inset:-9px; border-radius: 34px;
        border: 2px solid rgba(61,107,255,.5); opacity:.5;
    }
    .dcp-call-name { margin-top: 14px; font-size: 17px; font-weight: 800; color:#fff; display:flex; align-items:center; gap: 6px; }
    .dcp-call-name i { color:#3d6bff; font-size: 13px; }
    .dcp-call-handle { margin-top: 2px; font-size: 11px; color: #a8b3cf; font-weight: 600; }
    .dcp-call-num { margin-top: 7px; font-size: 12.5px; color: #cbd5e1; font-weight: 600; letter-spacing: .04em; font-variant-numeric: tabular-nums; }
    .dcp-call-status { margin-top: 10px; font-size: 11px; font-weight: 700; color: #34d399; display:flex; align-items:center; gap: 2px; }
    .dcp-dot { display:inline-block; width: 3.5px; height: 3.5px; border-radius: 999px; background: #34d399; margin-left: 3px; }
    /* Incoming-call action buttons */
    .dcp-incall-actions { margin-top: auto; width: 100%; display:flex; flex-direction:column; align-items:center; gap: 8px; padding-bottom: 2px; }
    .dcp-incall-main { display:flex; align-items:center; justify-content:center; gap: 30px; }
    .dcp-decline, .dcp-answer { display:flex; flex-direction:column; align-items:center; gap: 5px; }
    .dcp-decline-btn {
        width: 54px; height: 54px; border-radius: 999px;
        background: linear-gradient(135deg, #dc2626, #ef4444);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 19px;
        box-shadow: 0 14px 30px -12px rgba(239,68,68,.75);
    }
    .dcp-answer-btn {
        width: 54px; height: 54px; border-radius: 999px;
        background: linear-gradient(135deg, #16a34a, #22c55e);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size: 19px;
        box-shadow: 0 14px 30px -12px rgba(34,197,94,.75);
    }
    .dcp-btn-label { font-size: 9px; font-weight: 700; color: rgba(255,255,255,.5); letter-spacing:.03em; }
    /* Quick-channel row on the caller-ID (back) face */
    .dcp-cid-chans { display:flex; align-items:center; justify-content:center; gap: 7px; }
    .dcp-cid-chans > div { opacity: 0; }

    @media (prefers-reduced-motion: no-preference) {
        /* 12s master loop: dial digits -> press SIM 1 -> flip to in-call
           caller-ID screen -> flip back. All keyframe percentages are of 12s. */
        .dcp-armed .dcp-flip { animation: dcpFlip 12s ease-in-out infinite; }
        @keyframes dcpFlip {
            0%, 38% { transform: rotateY(0deg); }
            45%     { transform: rotateY(180deg); }
            88%     { transform: rotateY(180deg); }
            95%     { transform: rotateY(360deg); }
            100%    { transform: rotateY(360deg); }
        }
        /* Belt-and-suspenders: explicitly hide each face while the other is
           facing the viewer. backface-visibility alone is unreliable in some
           browsers when the face also has overflow:hidden + border-radius inside
           a nested 3D transform context, causing keypad digits to bleed through
           the caller-ID face. Swap points (41.5% ≈ mid forward-flip, 91.5% ≈
           mid flip-back) coincide with each 90° edge-on moment so neither face
           is ever visible edge-on. */
        .dcp-armed .dcp-front { animation: dcpFrontVis 12s linear infinite; }
        .dcp-armed .dcp-back  { animation: dcpBackVis  12s linear infinite; }
        @keyframes dcpFrontVis {
            0%, 41%   { visibility: visible; }
            42%, 91%  { visibility: hidden;  }
            92%, 100% { visibility: visible; }
        }
        @keyframes dcpBackVis {
            0%, 41%   { visibility: hidden;  }
            42%, 91%  { visibility: visible; }
            92%, 100% { visibility: hidden;  }
        }

        /* Digits type into the number display in sync with the keys
           (per-digit animation-delay set inline; presses at 0.6s + n*0.3s). */
        .dcp-armed .dcp-digit { animation: dcpDigitIn 12s linear infinite; }
        @keyframes dcpDigitIn {
            0%       { opacity: 0; transform: translateY(4px) scale(.85); }
            1%       { opacity: 1; transform: none; }
            50%      { opacity: 1; }
            53%,100% { opacity: 0; }
        }
        /* Whole display clears while the phone is flipped (call in progress) */
        .dcp-armed .dcp-numdigits { animation: dcpNumWrap 12s linear infinite; }
        @keyframes dcpNumWrap {
            0%, 42%  { opacity: 1; }
            45%, 99% { opacity: 0; }
            100%     { opacity: 1; }
        }
        /* T9 match chip appears once enough digits are in */
        .dcp-armed .dcp-match { animation: dcpMatch 12s ease-in-out infinite; }
        @keyframes dcpMatch {
            0%, 18%  { opacity: 0; transform: translateY(4px); }
            21%, 41% { opacity: 1; transform: none; }
            44%,100% { opacity: 0; }
        }

        /* Key press flashes — one keyframe set per key, percentages encode
           every press of that key inside the 12s loop (multiple comma
           animations on one element would override each other). */
        .dcp-armed .dcp-key.k4 { animation: dcpK4 12s linear infinite; }
        .dcp-armed .dcp-key.k1 { animation: dcpK1 12s linear infinite; }
        .dcp-armed .dcp-key.k5 { animation: dcpK5 12s linear infinite; }
        .dcp-armed .dcp-key.k0 { animation: dcpK0 12s linear infinite; }
        .dcp-armed .dcp-key.k8 { animation: dcpK8 12s linear infinite; }
        .dcp-armed .dcp-key.k2 { animation: dcpK2 12s linear infinite; }
        @keyframes dcpK4 {
            0%, 4%   { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            5%       { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            6.2%     { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            7.5%,100%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcpK1 {
            0%, 6.5% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            7.5%     { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            8.7%     { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            10%,21.5%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            22.5%    { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            23.7%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            25%,100% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcpK5 {
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
        @keyframes dcpK0 {
            0%, 19%  { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            20%      { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            21.2%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            22.5%,100%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcpK8 {
            0%, 24%  { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            25%      { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            26.2%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            27.5%,100%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }
        @keyframes dcpK2 {
            0%, 26.5%{ background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
            27.5%    { background-color: rgba(61,107,255,.45); border-color: rgba(122,158,255,.7); box-shadow: 0 0 18px rgba(61,107,255,.55); transform: scale(.88); }
            28.7%    { background-color: rgba(61,107,255,.3);  border-color: rgba(61,107,255,.55); box-shadow: 0 0 12px rgba(61,107,255,.4);  transform: none; }
            30%,100% { background-color: rgba(255,255,255,.06); border-color: rgba(255,255,255,.08); box-shadow: none; transform: none; }
        }

        /* SIM 1 gets "pressed" right after the number is complete */
        .dcp-armed .dcp-sim-1 { animation: dcpSimPress 12s ease-in-out infinite; }
        @keyframes dcpSimPress {
            0%, 31.5% { transform: none; filter: none; }
            33%       { transform: scale(.93); filter: brightness(1.35); }
            35%, 100% { transform: none; filter: none; }
        }

        /* Quick-channel strip on the dialer face: appear with the T9 match chip */
        .dcp-armed .dcp-dialchans { animation: dcpMatch 12s ease-in-out infinite; }

        /* Caller-ID channel icons fade up, staggered, once the flip completes
           (~45%); they stay through the back face (visible ~42%-91%) then drop
           out just before the flip-back. Timing is baked into keyframe
           percentages — not animation-delay — so they stay in sync with the
           12s master loop instead of drifting a cycle. */
        .dcp-armed .dcp-cid-chans > div:nth-child(1) { animation: dcpCidChan1 12s ease-out infinite; }
        .dcp-armed .dcp-cid-chans > div:nth-child(2) { animation: dcpCidChan2 12s ease-out infinite; }
        .dcp-armed .dcp-cid-chans > div:nth-child(3) { animation: dcpCidChan3 12s ease-out infinite; }
        .dcp-armed .dcp-cid-chans > div:nth-child(4) { animation: dcpCidChan4 12s ease-out infinite; }
        @keyframes dcpCidChan1 { 0%,46% { opacity:0; transform: translateY(6px); } 50%,88% { opacity:1; transform:none; } 91%,100% { opacity:0; transform: translateY(6px); } }
        @keyframes dcpCidChan2 { 0%,48% { opacity:0; transform: translateY(6px); } 52%,88% { opacity:1; transform:none; } 91%,100% { opacity:0; transform: translateY(6px); } }
        @keyframes dcpCidChan3 { 0%,50% { opacity:0; transform: translateY(6px); } 54%,88% { opacity:1; transform:none; } 91%,100% { opacity:0; transform: translateY(6px); } }
        @keyframes dcpCidChan4 { 0%,52% { opacity:0; transform: translateY(6px); } 56%,88% { opacity:1; transform:none; } 91%,100% { opacity:0; transform: translateY(6px); } }

        /* In-call screen life: pulsing avatar ring + calling dots */
        .dcp-armed .dcp-avatar-lg::before { animation: dcpRing 2.2s ease-in-out infinite; }
        @keyframes dcpRing { 0%,100% { transform: scale(1); opacity:.4; } 50% { transform: scale(1.12); opacity:.85; } }
        .dcp-armed .dcp-dot { animation: dcpDot 1.2s ease-in-out infinite; }
        .dcp-armed .dcp-dot:nth-child(2) { animation-delay: .18s; }
        .dcp-armed .dcp-dot:nth-child(3) { animation-delay: .36s; }
        @keyframes dcpDot { 0%,100% { opacity:.25; transform: translateY(0); } 40% { opacity:1; transform: translateY(-2.5px); } }
    }

    /* Everything grid cards */
    .dcp-card { transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease; }
    .dcp-card:hover { transform: translateY(-6px); box-shadow: 0 30px 60px -26px rgba(61,107,255,.5); border-color: rgba(61,107,255,.4); }
    .dcp-card-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; color:#fff; box-shadow:0 12px 28px -10px var(--dcp-c,#3d6bff); background:var(--dcp-c,#3d6bff); }

    /* Channel chips row */
    .dcp-chchip { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:999px; font-size:13px; font-weight:700; color:#fff; }

    /* Stat block */
    .dcp-stat { text-align:center; padding:22px 14px; }
    .dcp-stat .num { font-size:2.5rem; font-weight:800; color:#3d6bff; }
    .dcp-stat .lbl { font-size:11px; text-transform:uppercase; letter-spacing:.15em; color:#9ca3af; margin-top:4px; }
    html.light-mode .dcp-stat .lbl { color:#64748b; }

    /* Sync diagram */
    .dcp-sync-node { border-radius:20px; padding:20px; text-align:center; }
    .dcp-sync-arrows i { animation: dcpArrow 2.4s ease-in-out infinite; }
    @keyframes dcpArrow { 0%,100% { opacity:.4; transform: translateX(0); } 50% { opacity:1; transform: translateX(4px); } }

    @media (prefers-reduced-motion: reduce) {
        .dcp-mesh::before, .dcp-phone, .dcp-sync-arrows i, .dcp-caret { animation: none !important; }
        /* Static, fully-visible caller-ID state: show the in-call face only */
        .dcp-flip { transform: none !important; }
        .dcp-front { display: none !important; }
        .dcp-back { transform: none !important; }
        .dcp-match { opacity: 1 !important; transform: none !important; }
        .dcp-cid-chans > div { opacity: 1 !important; transform: none !important; }
    }

    /* Light mode: the phone screen stays a dark display. All text inside the
       screen uses scoped dcp-* classes with explicit light colors, so the
       global html.light-mode gray-text remapping cannot darken it; only the
       frame gradient needs a light-mode variant. */
    html.light-mode .dcp-phone { background: linear-gradient(160deg, #101827, #060b16); }
</style>

{{-- ============== HERO ============== --}}
<section id="dcp-hero" class="relative pt-20 pb-20 lg:pt-28 lg:pb-28 overflow-hidden">
    <div class="dcp-mesh absolute inset-0" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                  style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                <i class="fas fa-phone-volume text-[10px]"></i> Dialer &amp; Contacts
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                Every number becomes
                <span class="block grad-text">a real connection.</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                A smart T9 dialer, one-tap call / SMS / WhatsApp channels, two-way Google Contacts sync, an AI business-card scanner and phone-to-biolink caller&nbsp;ID &mdash; your entire address book, supercharged inside Sayzio.
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Open your dashboard
                    </a>
                @else
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Start free &mdash; no card
                    </a>
                @endauth
                <a href="#everything" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                    See everything it does
                </a>
            </div>
            <p class="mt-5 text-xs text-gray-500">
                <i class="fas fa-check text-[10px] mr-1 text-emerald-400"></i>
                Free Forever plan &middot; Two-way Google sync &middot; Works on web &amp; mobile
            </p>
        </div>
        <div data-anim="fade-left" class="dcp-wrap flex justify-center relative">
            <div class="dcp-phone" role="img" aria-label="Sayzio dialer typing a phone number on a dual-SIM keypad, then flipping to a caller-ID screen that resolves the number into a verified Sayzio profile">
                <div class="dcp-stage" aria-hidden="true">
                    <div class="dcp-flip">

                        {{-- FRONT: dialer keypad --}}
                        <div class="dcp-face dcp-front">
                            <div class="dcp-notch"></div>
                            <div class="dcp-status">
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
                            <div class="dcp-numwrap">
                                <div class="dcp-match">
                                    <img src="{{ asset('images/marketing/contact-aisha.jpg') }}" alt="" loading="lazy" onerror="this.remove()">
                                    Aisha Rahman <i class="fas fa-circle-check"></i>
                                </div>
                                {{-- Quick-channel icons appear with the T9 match --}}
                                <div class="dcp-dialchans">
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <div class="dcp-dialchan" style="background:#3d6bff;"><i class="fas fa-comment-sms"></i></div>
                                        <span class="dcp-dialchan-label">SMS</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <div class="dcp-dialchan" style="background:#25d366;"><i class="fab fa-whatsapp"></i></div>
                                        <span class="dcp-dialchan-label">WhatsApp</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <div class="dcp-dialchan" style="background:#229ed9;"><i class="fab fa-telegram"></i></div>
                                        <span class="dcp-dialchan-label">Telegram</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;align-items:center;">
                                        <div class="dcp-dialchan" style="background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.22);"><i class="fas fa-link" style="font-size:9px;"></i></div>
                                        <span class="dcp-dialchan-label">Biolink</span>
                                    </div>
                                </div>
                                <div class="dcp-numdisplay">
                                    <span class="dcp-numdigits">
                                        @foreach($dial as $i => $d)
                                            <span class="dcp-digit {{ in_array($i, [3, 6], true) ? 'gp' : '' }}" style="animation-delay: {{ number_format(0.6 + $i * 0.3, 1) }}s;">{{ $d }}</span>
                                        @endforeach
                                        <span class="dcp-caret"></span>
                                    </span>
                                </div>
                            </div>

                            {{-- T9 keypad --}}
                            <div class="dcp-keys">
                                @foreach([['1',''],['2','ABC'],['3','DEF'],['4','GHI'],['5','JKL'],['6','MNO'],['7','PQRS'],['8','TUV'],['9','WXYZ'],['*',''],['0','+'],['#','']] as $k)
                                    <div class="dcp-key {{ $keyAnim[$k[0]] ?? '' }}">
                                        <span class="d">{{ $k[0] }}</span>
                                        @if($k[1] !== '')<span class="l">{{ $k[1] }}</span>@endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Dual-SIM call buttons --}}
                            <div class="dcp-sims">
                                <div class="dcp-sim dcp-sim-1"><span class="sb">1</span> <i class="fas fa-phone text-[11px]"></i> SIM 1</div>
                                <div class="dcp-sim dcp-sim-2"><span class="sb">2</span> <i class="fas fa-phone text-[11px]"></i> SIM 2</div>
                            </div>
                        </div>

                        {{-- BACK: in-call / caller-ID screen --}}
                        <div class="dcp-face dcp-back">
                            <div class="dcp-notch"></div>
                            <div class="dcp-call-body">
                                <div class="dcp-cid-pill"><i class="fas fa-address-card"></i> Sayzio Caller ID</div>
                                <div class="dcp-avatar-lg">
                                    AR
                                    <img src="{{ asset('images/marketing/contact-aisha.jpg') }}" alt="" loading="lazy" onerror="this.remove()">
                                </div>
                                <div class="dcp-call-name">Aisha Rahman <i class="fas fa-circle-check"></i></div>
                                <div class="dcp-call-handle">@aisha &middot; on Sayzio</div>
                                <div class="dcp-call-num">+1 (415) 555-0182</div>
                                <div class="dcp-call-status">
                                    <i class="fas fa-phone-volume" style="font-size:9px;margin-right:3px;"></i> Incoming call &middot; SIM 1
                                    <span class="dcp-dot"></span><span class="dcp-dot"></span><span class="dcp-dot"></span>
                                </div>
                                <div class="dcp-incall-actions">
                                    <div class="dcp-cid-chans">
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dcp-dialchan" style="background:#3d6bff;"><i class="fas fa-comment-sms"></i></div>
                                            <span class="dcp-dialchan-label">SMS</span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dcp-dialchan" style="background:#25d366;"><i class="fab fa-whatsapp"></i></div>
                                            <span class="dcp-dialchan-label">WhatsApp</span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dcp-dialchan" style="background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);"><i class="fab fa-instagram"></i></div>
                                            <span class="dcp-dialchan-label">Instagram</span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;align-items:center;">
                                            <div class="dcp-dialchan" style="background:#229ed9;"><i class="fab fa-telegram"></i></div>
                                            <span class="dcp-dialchan-label">Telegram</span>
                                        </div>
                                    </div>
                                    <div class="dcp-incall-main">
                                        <div class="dcp-decline">
                                            <div class="dcp-decline-btn"><i class="fas fa-phone-slash"></i></div>
                                            <span class="dcp-btn-label">Decline</span>
                                        </div>
                                        <div class="dcp-answer">
                                            <div class="dcp-answer-btn"><i class="fas fa-phone"></i></div>
                                            <span class="dcp-btn-label">Answer</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============== STATS ============== --}}
<section class="py-10 relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['6',    'Quick channels'],
            ['2-way','Google Contacts sync'],
            ['T9',   'Smart name search'],
            ['AI',   'Business-card scan'],
        ] as $s)
            <div class="dcp-stat glass rounded-2xl reveal">
                <div class="num">{{ $s[0] }}</div>
                <div class="lbl">{{ $s[1] }}</div>
            </div>
        @endforeach
    </div>
</section>

{{-- ============== EVERYTHING GRID ============== --}}
<section id="everything" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#1bd4d9">Everything in one place</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                A dialer and address book <span class="grad-text">that actually works for you.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">From the first keypress to a shareable vCard &mdash; here's the full toolkit.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['fa-phone-volume', '#1bd4d9', 'Smart T9 dialer &amp; keypad',   'Tap out a name on the number pad and T9 surfaces the right contact instantly. Flip to a full alphanumeric keyboard whenever you prefer &mdash; both feed the same search.'],
                ['fa-comments',     '#3d6bff', 'Quick channels',                 'Reach anyone through Call, SMS, WhatsApp, Telegram, Signal or Viber &mdash; one tap opens the right app with the number pre-filled.'],
                ['fa-star',         '#e94e8c', 'Speed dial',                     'Pin the people you reach most to a speed-dial row so they are always a single tap away.'],
                ['fa-clock-rotate-left', '#ff8a3c', 'Smart history &amp; frequent', 'Recents and a frequently-contacted list are built automatically, so the right person is always near the top.'],
                ['fa-magnifying-glass', '#22c55e', 'Universal finder',           'One search spans your contacts, people on Sayzio, your links &amp; biolinks and your workspaces &mdash; grouped by category with a clear action for each result.'],
                ['fa-clipboard-list', '#22d3ee', 'Call logging &amp; reminders', 'Log call outcomes and set callback reminders so follow-ups never slip through the cracks.'],
                ['fa-ban',          '#ef4444', 'Spam &amp; block controls',      'Flag and block unwanted numbers to keep your dialer and caller ID clean.'],
                ['fa-rotate',       '#14b8a6', 'Two-way Google Contacts sync',   'Contacts stay in lockstep with Google via the People API &mdash; edits made anywhere flow both directions, incrementally and on a schedule.'],
                ['fa-address-card', '#0ea5e9', 'Phone &rarr; biolink resolution', 'Incoming and saved numbers resolve to rich Sayzio profiles, silently attaching the matching biolink (with detach memory).'],
                ['fa-id-badge',     '#f59e0b', 'Rich identity profiles',         'Every contact holds multiple numbers, emails, addresses, socials and organisations &mdash; not just a single field.'],
                ['fa-file-import',  '#16a34a', 'Bulk CSV / vCard import',        'Import large lists via a parse &rarr; preview &rarr; confirm flow, skipping rows before you commit, with big files processed in the background.'],
                ['fa-id-card',      '#ec4899', 'AI business-card scanner',       'Snap a business card or brochure and AI extracts names, numbers, emails and socials straight into a clean new contact.'],
            ] as $i => $f)
                <div class="reveal rd-{{ ($i % 3) + 1 }} dcp-card glass rounded-3xl p-6">
                    <div class="dcp-card-icon mb-4" style="--dcp-c: {{ $f[1] }};"><i class="fas {{ $f[0] }} text-lg"></i></div>
                    <h3 class="text-lg font-bold mb-1.5">{!! $f[2] !!}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{!! $f[3] !!}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============== QUICK CHANNELS ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div data-anim="fade-right">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#3d6bff">One tap, right app</div>
            <h2 class="text-4xl sm:text-5xl font-bold tracking-tight mb-5">
                Reach anyone, <span class="grad-text">however they prefer.</span>
            </h2>
            <p class="text-lg text-gray-400 leading-relaxed mb-7">
                Every contact carries the channels they actually use. Skip the copy-paste dance &mdash; tap once and Sayzio opens the right app with the number ready to go.
            </p>
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['fa-phone','#22c55e','Call'],
                    ['fa-comment-sms','#3d6bff','SMS'],
                    ['fab fa-whatsapp','#25d366','WhatsApp'],
                    ['fab fa-telegram','#229ed9','Telegram'],
                    ['fas fa-shield-halved','#3a76f0','Signal'],
                    ['fas fa-phone-volume','#7360f2','Viber'],
                ] as $c)
                    <span class="dcp-chchip glass" style="border:1px solid {{ $c[1] }}55;">
                        <i class="{{ str_starts_with($c[0],'fab') || str_starts_with($c[0],'fas') ? $c[0] : 'fas '.$c[0] }}" style="color:{{ $c[1] }};"></i> {{ $c[2] }}
                    </span>
                @endforeach
            </div>
        </div>
        <div data-anim="fade-left">
            <div class="glass rounded-3xl p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full opacity-25" style="background:#3d6bff;"></div>
                <div class="relative space-y-3">
                    @foreach([
                        ['MK','Maria Kovac','+385 91 555 0110','#e94e8c'],
                        ['JT','James Tanaka','+81 90 5555 0143','#1bd4d9'],
                        ['LO','Lola Okafor','+234 802 555 0178','#ff8a3c'],
                    ] as $r)
                        <div class="flex items-center gap-3 p-3 rounded-2xl" style="background:rgba(255,255,255,.05);">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0" style="background:linear-gradient(135deg,{{ $r[3] }},#3d6bff);">{{ $r[0] }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold truncate">{{ $r[1] }}</div>
                                <div class="text-[11px] text-gray-400">{{ $r[2] }}</div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:#22c55e;"><i class="fas fa-phone"></i></span>
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:#25d366;"><i class="fab fa-whatsapp"></i></span>
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:#3d6bff;"><i class="fas fa-comment-sms"></i></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============== GOOGLE SYNC ============== --}}
<section id="sync" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#e94e8c">Always in sync</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                Two-way Google Contacts sync, <span class="grad-text">on autopilot.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Add a contact on your phone, edit one in Sayzio &mdash; both stay identical. Changes flow both ways, incrementally, on a schedule and the moment you make them.</p>
        </div>
        <div class="grid sm:grid-cols-[1fr_auto_1fr] gap-5 items-center">
            <div class="dcp-sync-node glass reveal">
                <div class="w-12 h-12 rounded-2xl mx-auto mb-3 flex items-center justify-center text-white text-xl" style="background:#3d6bff;"><i class="fab fa-google"></i></div>
                <div class="font-bold">Google Contacts</div>
                <div class="text-xs text-gray-400 mt-1">Your existing address book</div>
            </div>
            <div class="dcp-sync-arrows reveal rd-1 flex sm:flex-col items-center justify-center gap-2 text-2xl" style="color:#1bd4d9;" aria-hidden="true">
                <i class="fas fa-arrow-right sm:rotate-0"></i>
                <i class="fas fa-arrow-left sm:rotate-0"></i>
            </div>
            <div class="dcp-sync-node glass reveal rd-2">
                <div class="w-12 h-12 rounded-2xl mx-auto mb-3 flex items-center justify-center text-white text-xl grad-bar"><i class="fas fa-address-book"></i></div>
                <div class="font-bold">Sayzio Contacts</div>
                <div class="text-xs text-gray-400 mt-1">Dialer, caller ID &amp; profiles</div>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-10">
            @foreach([
                ['fa-bolt','Instant on edit','Create or edit a contact and the change pushes immediately.'],
                ['fa-arrows-rotate','Scheduled sweep','A background sync reconciles both sides every 30 minutes.'],
                ['fa-code-branch','Incremental &amp; safe','Only changes move across, with tombstone-safe deletes.'],
            ] as $i => $f)
                <div class="reveal rd-{{ $i+1 }} glass rounded-2xl p-5">
                    <i class="fas {{ $f[0] }} text-lg mb-2" style="color:#1bd4d9;"></i>
                    <div class="font-bold text-sm mb-1">{!! $f[1] !!}</div>
                    <div class="text-xs text-gray-400 leading-relaxed">{!! $f[2] !!}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============== IMPORT & SCAN ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div data-anim="fade-right">
            <div class="glass rounded-3xl p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full opacity-20" style="background:#ff8a3c;"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#ec4899;"><i class="fas fa-id-card"></i></div>
                        <div>
                            <div class="font-bold text-sm">Business card scanned</div>
                            <div class="text-[11px] text-gray-400">AI extracted 6 fields &middot; 1.4s</div>
                        </div>
                        <span class="ml-auto text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full" style="background:rgba(34,197,94,.15); color:#22c55e;">Ready</span>
                    </div>
                    <div class="space-y-2">
                        @foreach([
                            ['fa-user','Priya Nair'],
                            ['fa-briefcase','Head of Growth · Nimbus Labs'],
                            ['fa-phone','+91 98765 43210'],
                            ['fa-envelope','priya@nimbuslabs.io'],
                            ['fa-globe','nimbuslabs.io'],
                        ] as $row)
                            <div class="flex items-center gap-3 text-sm p-2.5 rounded-xl" style="background:rgba(255,255,255,.05);">
                                <i class="fas {{ $row[0] }} text-xs w-4 text-center" style="color:#1bd4d9;"></i>
                                <span class="text-gray-200">{{ $row[1] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div data-anim="fade-left">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#ff8a3c">Get contacts in fast</div>
            <h2 class="text-4xl sm:text-5xl font-bold tracking-tight mb-5">
                Import a spreadsheet <span class="grad-text">or just snap a card.</span>
            </h2>
            <p class="text-lg text-gray-400 leading-relaxed mb-6">
                Bring in a whole list from CSV or vCard with a safe parse &rarr; preview &rarr; confirm flow &mdash; skip the rows you don't want before anything is saved, and let big files finish in the background. Or point your camera at a business card and let AI do the typing.
            </p>
            <ul class="space-y-3">
                @foreach([
                    'Preview every row and skip duplicates before committing',
                    'Large imports run as a background job with live progress',
                    'AI card &amp; brochure scanning fills names, numbers, emails &amp; socials',
                    'Export any contact as a shareable vCard in one tap',
                ] as $li)
                    <li class="flex items-start gap-3 text-sm text-gray-300">
                        <i class="fas fa-circle-check mt-0.5" style="color:#22c55e;"></i>
                        <span>{!! $li !!}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- ============== TAKE IT WITH YOU ============== --}}
<section id="dcp-store" class="py-12 lg:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="text-[11px] font-bold uppercase tracking-[.14em] text-gray-500 mb-4" data-anim="fade-up">Take the dialer with you</div>
        <p class="text-sm text-gray-400 mb-5 max-w-xl mx-auto" data-anim="fade-up">
            The full T9 dialer, quick channels and caller ID experience also ships as its own dedicated companion app, <strong class="text-white">Sayzio Dialer</strong> — alongside everything you already get inside the main Sayzio app.
        </p>
        <div class="flex flex-wrap items-center justify-center" data-anim="fade-up">
            @include('public.partials.store-buttons')
        </div>
    </div>
</section>

{{-- ============== CLOSING CTA ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                Turn your contacts into <span class="grad-text">conversations.</span>
            </h2>
            <p class="text-gray-400 max-w-2xl mx-auto mb-8">
                Bring your address book to Sayzio and get a smart dialer, quick channels, Google sync and phone-to-profile caller ID &mdash; free to start.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Go to your dashboard</a>
                @else
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Get started free</a>
                @endauth
                <a href="{{ route('site.pricing') }}" class="px-7 py-3 rounded-full glass text-white hover:bg-white/10 text-sm font-semibold">See pricing</a>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var hero = document.getElementById('dcp-hero');
    if (!hero) return;
    var wrap = hero.querySelector('.dcp-wrap');
    if (!wrap) return;

    var reduceMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduceMq.matches) return;
    if (typeof window.IntersectionObserver !== 'function') return;

    var io = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
            wrap.classList.toggle('dcp-armed', entries[i].isIntersecting);
        }
    }, { threshold: 0.25, rootMargin: '0px 0px -10% 0px' });
    io.observe(hero);
})();
</script>
@endsection
