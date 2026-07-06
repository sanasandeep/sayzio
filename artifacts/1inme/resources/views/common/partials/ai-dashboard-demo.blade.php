{{--
    Live, looping "describe → AI arranges → dashboard updates" demo.
    Shared by the home page AI Dashboard teaser (variant=compact) and the
    public "See how it works" page (variant=rich). Data-driven from the real
    dashboard presets/widgets — no invented metrics or widget names.

    Expects:
    - $presets: list<array{key?:string,label:string,description:string,icon:string,widgets:list<string>}>
    - $variant: 'compact' | 'rich'
--}}
@php
    $__aiddVariant = $variant ?? 'compact';
    $__aiddPresets = collect($presets ?? [])->values();
    $__aiddWidgetMeta = [
        'stat_total_clicks' => ['icon' => 'fa-chart-line',      'label' => 'Total Clicks'],
        'stat_today'        => ['icon' => 'fa-calendar-day',    'label' => 'Today'],
        'stat_plan'         => ['icon' => 'fa-gem',             'label' => 'Plan'],
        'stat_links'        => ['icon' => 'fa-link',            'label' => 'Links'],
        'stat_projects'     => ['icon' => 'fa-folder',          'label' => 'Projects'],
        'recent_links'      => ['icon' => 'fa-clock-rotate-left', 'label' => 'Recent Links'],
        'quick_actions'     => ['icon' => 'fa-bolt',            'label' => 'Quick Actions'],
        'plan_detail'       => ['icon' => 'fa-circle-info',     'label' => 'Plan Detail'],
        'traffic_channels'  => ['icon' => 'fa-diagram-project', 'label' => 'Traffic Channels'],
        'backlinks'         => ['icon' => 'fa-arrow-trend-up',  'label' => 'Backlinks'],
        'coin_balance'      => ['icon' => 'fa-coins',           'label' => 'Coin Balance'],
    ];
    $__aiddMaxTiles = $__aiddVariant === 'rich' ? 6 : 4;
@endphp
@if($__aiddPresets->isNotEmpty())
<div class="aidd aidd--{{ $__aiddVariant }}" data-aidd role="group" aria-label="Live demo: describe what you want and AI arranges your dashboard" aria-live="off">
    <div class="aidd-console glass">
        <div class="aidd-console-head">
            <span class="aidd-badge"><i class="fas fa-wand-magic-sparkles"></i> AI designer</span>
            <span class="aidd-status" data-aidd-status>
                <span class="aidd-status-dot" data-aidd-status-dot></span>
                <span data-aidd-status-text>Ready</span>
            </span>
        </div>
        <div class="aidd-prompt-row">
            <i class="fas fa-message aidd-msg-ic" aria-hidden="true"></i>
            <span class="aidd-prompt-text" data-aidd-prompt></span><span class="aidd-caret" data-aidd-caret aria-hidden="true"></span>
        </div>
    </div>

    <div class="aidd-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></div>

    <div class="aidd-board glass" data-aidd-board>
        <div class="aidd-board-head">
            <span class="aidd-board-icon" data-aidd-board-icon><i class="fas fa-gauge-high"></i></span>
            <span class="aidd-board-title" data-aidd-board-title>Overview</span>
        </div>
        <div class="aidd-scenes">
            @foreach($__aiddPresets as $i => $preset)
                <div class="aidd-scene{{ $i === 0 ? ' is-active' : '' }}"
                     data-aidd-scene
                     data-i="{{ $i }}"
                     data-prompt="{{ $preset['description'] ?? $preset['label'] ?? '' }}"
                     data-title="{{ $preset['label'] ?? '' }}"
                     data-icon="{{ $preset['icon'] ?? 'fa-gauge-high' }}">
                    <div class="aidd-tiles">
                        @foreach(array_slice($preset['widgets'] ?? [], 0, $__aiddMaxTiles) as $j => $widget)
                            @php $__meta = $__aiddWidgetMeta[$widget] ?? ['icon' => 'fa-table-cells', 'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $widget))]; @endphp
                            <div class="aidd-tile" data-aidd-tile style="--d:{{ $j * 90 }}ms">
                                <i class="fas {{ $__meta['icon'] }}"></i>
                                <span>{{ $__meta['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="aidd-tabs" aria-hidden="true">
        @foreach($__aiddPresets as $i => $preset)
            <span class="aidd-tab{{ $i === 0 ? ' is-active' : '' }}" data-aidd-tab></span>
        @endforeach
    </div>
</div>

@once
<style>
    .aidd { position: relative; display: grid; gap: 1rem; }
    .aidd--compact { grid-template-columns: 1fr; max-width: 30rem; margin: 0 auto; }
    .aidd--rich { grid-template-columns: 1fr auto 1fr; align-items: center; max-width: 46rem; margin: 0 auto; }
    .aidd--compact .aidd-arrow { display: none; }
    .aidd-arrow { color: #90acff; font-size: 1.1rem; opacity: .8; }
    @media (max-width: 640px) { .aidd--rich { grid-template-columns: 1fr; } .aidd--rich .aidd-arrow { transform: rotate(90deg); justify-self: center; } }

    .aidd-console { border-radius: 1.25rem; padding: 1.1rem 1.25rem; min-height: 6.5rem; }
    .aidd-console-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; gap: .5rem; }
    .aidd-badge { display: inline-flex; align-items: center; gap: .4rem; font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #90acff; background: rgba(61,107,255,.12); border: 1px solid rgba(61,107,255,.28); padding: .3rem .65rem; border-radius: 999px; }
    .aidd-status { display: inline-flex; align-items: center; gap: .4rem; font-size: .7rem; color: #9ca3af; font-weight: 600; }
    .aidd-status-dot { width: 6px; height: 6px; border-radius: 999px; background: #3d6bff; box-shadow: 0 0 0 0 rgba(61,107,255,.55); animation: aiddPulse 1.6s ease-in-out infinite; }
    @keyframes aiddPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(61,107,255,.5); } 70% { box-shadow: 0 0 0 6px rgba(61,107,255,0); } }
    .aidd-status.is-thinking .aidd-status-dot { background: #fbbf24; }

    .aidd-prompt-row { display: flex; align-items: flex-start; gap: .6rem; font-family: 'Space Grotesk', ui-monospace, monospace; font-size: .88rem; line-height: 1.5; color: #e5e7eb; min-height: 2.6em; }
    .aidd-msg-ic { color: #3d6bff; margin-top: .2em; flex: none; }
    .aidd-prompt-text { white-space: pre-wrap; }
    .aidd-caret { display: inline-block; width: 2px; height: 1em; background: #90acff; margin-left: 1px; vertical-align: -.15em; animation: aiddBlink 1s step-end infinite; }
    @keyframes aiddBlink { 0%,100% { opacity: 1; } 50% { opacity: 0; } }

    .aidd-board { border-radius: 1.25rem; padding: 1.1rem 1.25rem; overflow: hidden; }
    .aidd-board-head { display: flex; align-items: center; gap: .6rem; margin-bottom: .85rem; }
    .aidd-board-icon { width: 2rem; height: 2rem; border-radius: .75rem; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #3d6bff, #6e61ff); color: #fff; font-size: .8rem; flex: none; }
    .aidd-board-title { font-weight: 800; font-size: .92rem; }

    .aidd-scenes { position: relative; }
    .aidd-scene { display: none; }
    .aidd-scene.is-active { display: block; }
    .aidd-tiles { display: grid; grid-template-columns: repeat(2, 1fr); gap: .55rem; }
    .aidd--rich .aidd-tiles { grid-template-columns: repeat(2, 1fr); }
    .aidd-tile {
        display: flex; align-items: center; gap: .5rem; font-size: .74rem; font-weight: 700; color: #cbd5e1;
        background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: .75rem; padding: .55rem .65rem;
        opacity: 0; transform: translateY(6px) scale(.97);
    }
    .aidd-tile i { color: #3d6bff; font-size: .78rem; flex: none; }
    .aidd-scene.is-playing .aidd-tile { animation: aiddTileIn .5s cubic-bezier(.16,1,.3,1) forwards; animation-delay: var(--d); }
    @keyframes aiddTileIn { to { opacity: 1; transform: none; } }

    .aidd-tabs { display: flex; align-items: center; justify-content: center; gap: .4rem; grid-column: 1 / -1; }
    .aidd-tab { width: 1.35rem; height: 3px; border-radius: 999px; background: rgba(255,255,255,.14); transition: background .3s, width .3s; }
    .aidd-tab.is-active { background: #3d6bff; width: 2.1rem; }

    html.light-mode .aidd-prompt-text,
    html.light-mode .aidd-board-title { color: #111827; }
    html.light-mode .aidd-tile { color: #374151; background: rgba(17,24,39,.04); border-color: rgba(17,24,39,.08); }
    html.light-mode .aidd-status { color: #6b7280; }
    html.light-mode .aidd-tab { background: rgba(17,24,39,.12); }

    @media (prefers-reduced-motion: reduce) {
        .aidd-caret { animation: none !important; opacity: 1; }
        .aidd-status-dot { animation: none !important; }
        .aidd-tile { opacity: 1 !important; transform: none !important; animation: none !important; }
        .aidd-tab { transition: none !important; }
    }
</style>
<script>
(function () {
    if (window.__aiddInit) return;
    window.__aiddInit = true;

    function initOne(root) {
        var scenes = Array.prototype.slice.call(root.querySelectorAll('[data-aidd-scene]'));
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-aidd-tab]'));
        var promptEl = root.querySelector('[data-aidd-prompt]');
        var caretEl = root.querySelector('[data-aidd-caret]');
        var statusEl = root.querySelector('[data-aidd-status]');
        var statusTextEl = root.querySelector('[data-aidd-status-text]');
        var boardTitleEl = root.querySelector('[data-aidd-board-title]');
        var boardIconEl = root.querySelector('[data-aidd-board-icon] i');
        if (!scenes.length || !promptEl) return;

        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var idx = 0;
        var visible = false;
        var typeTimer = null;
        var stepTimer = null;
        var cycleTimer = null;

        function setScene(i, prompt, title, icon) {
            scenes.forEach(function (s, k) { s.classList.toggle('is-active', k === i); s.classList.remove('is-playing'); });
            tabs.forEach(function (t, k) { t.classList.toggle('is-active', k === i); });
            if (boardTitleEl && title) boardTitleEl.textContent = title;
            if (boardIconEl && icon) boardIconEl.className = 'fas ' + icon;
        }

        function playScene(i) {
            var scene = scenes[i];
            if (!scene) return;
            void scene.offsetWidth;
            scene.classList.add('is-playing');
        }

        function typeText(text, onDone) {
            promptEl.textContent = '';
            var chars = text.split('');
            var pos = 0;
            typeTimer = setInterval(function () {
                pos++;
                promptEl.textContent = chars.slice(0, pos).join('');
                if (pos >= chars.length) {
                    clearInterval(typeTimer);
                    typeTimer = null;
                    if (onDone) onDone();
                }
            }, 22);
        }

        function runScene(i) {
            var scene = scenes[i];
            if (!scene) return;
            var prompt = scene.getAttribute('data-prompt') || '';
            var title = scene.getAttribute('data-title') || '';
            var icon = scene.getAttribute('data-icon') || '';

            setScene(i, prompt, title, icon);
            if (statusEl) statusEl.classList.remove('is-thinking');
            if (statusTextEl) statusTextEl.textContent = 'Listening';

            if (reduce) {
                promptEl.textContent = prompt;
                if (caretEl) caretEl.style.display = 'none';
                playScene(i);
                if (statusTextEl) statusTextEl.textContent = 'Ready';
                return;
            }

            typeText(prompt, function () {
                if (statusEl) statusEl.classList.add('is-thinking');
                if (statusTextEl) statusTextEl.textContent = 'Arranging your dashboard…';
                stepTimer = setTimeout(function () {
                    if (statusEl) statusEl.classList.remove('is-thinking');
                    if (statusTextEl) statusTextEl.textContent = 'Ready';
                    playScene(i);
                }, 650);
            });
        }

        function next() { idx = (idx + 1) % scenes.length; runScene(idx); }

        function start() {
            if (reduce || cycleTimer) return;
            cycleTimer = setInterval(function () { if (visible) next(); }, 6200);
        }
        function stop() { if (cycleTimer) { clearInterval(cycleTimer); cycleTimer = null; } }

        tabs.forEach(function (tab, i) {
            tab.style.cursor = 'pointer';
            tab.style.pointerEvents = 'auto';
            tab.addEventListener('click', function () {
                idx = i;
                if (typeTimer) { clearInterval(typeTimer); typeTimer = null; }
                if (stepTimer) { clearTimeout(stepTimer); stepTimer = null; }
                runScene(idx);
            });
        });

        runScene(0);

        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    visible = e.isIntersecting;
                    if (visible) start(); else stop();
                });
            }, { threshold: .2 });
            io.observe(root);
        } else {
            visible = true;
            start();
        }
    }

    function init() {
        var roots = document.querySelectorAll('[data-aidd]');
        roots.forEach(initOne);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endonce
@endif
