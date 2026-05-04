{{--
    Unified display-mode selector — replaces the two separate per-page toggles
    that lived inside slides/editor and conversational/editor. Three segmented
    buttons (List · Slides · Conversational) write to the existing toggle
    endpoints and then navigate to the matching editor URL so the creator sees
    the right tools for the mode they just picked.

    Required vars: $link
--}}
@php
    $__currentMode = data_get($link->settings, 'biolink.mode', 'list');
    $__modes = [
        'list'           => ['label' => 'List',           'icon' => 'fa-list-ul',  'desc' => 'Classic biolink stack'],
        'slides'         => ['label' => 'Slides',         'icon' => 'fa-images',   'desc' => 'Full-screen swipeable deck'],
        'conversational' => ['label' => 'Conversational', 'icon' => 'fa-comments', 'desc' => 'Chat-style guided flow'],
    ];
@endphp

<div class="mode-selector-card mb-4">
    <div class="mode-selector-head">
        <div>
            <div class="mode-selector-title">Display mode</div>
            <div class="mode-selector-sub">Choose how visitors experience this page.</div>
        </div>
        <span class="mode-selector-pill" id="mode-selector-current">
            <i class="fas {{ $__modes[$__currentMode]['icon'] ?? 'fa-circle' }} text-[10px]"></i>
            {{ $__modes[$__currentMode]['label'] ?? ucfirst($__currentMode) }}
        </span>
    </div>
    <div class="mode-selector-grid">
        @foreach($__modes as $key => $meta)
            <button type="button"
                    class="mode-selector-btn {{ $__currentMode === $key ? 'is-active' : '' }}"
                    data-mode="{{ $key }}">
                <i class="fas {{ $meta['icon'] }}"></i>
                <span class="mode-selector-btn-label">{{ $meta['label'] }}</span>
                <span class="mode-selector-btn-desc">{{ $meta['desc'] }}</span>
            </button>
        @endforeach
    </div>
</div>

@once
<style>
    .mode-selector-card {
        background: linear-gradient(135deg, rgba(139,92,246,0.08), rgba(6,182,212,0.05));
        border: 1px solid rgba(139,92,246,0.25);
        border-radius: 1rem;
        padding: 16px 18px;
        backdrop-filter: blur(14px);
    }
    .mode-selector-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; margin-bottom: 12px; flex-wrap: wrap;
    }
    .mode-selector-title { font-size: 13px; font-weight: 700; color: var(--text-primary); }
    .mode-selector-sub   { font-size: 11px; color: var(--text-faint); margin-top: 2px; }
    .mode-selector-pill  {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
        background: rgba(139,92,246,0.18); color: #c4b5fd;
        border: 1px solid rgba(139,92,246,0.35);
    }
    .mode-selector-grid {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    @media (max-width: 720px) { .mode-selector-grid { grid-template-columns: 1fr; } }
    .mode-selector-btn {
        display: flex; flex-direction: column; align-items: flex-start; gap: 4px;
        padding: 12px 14px; border-radius: 12px; cursor: pointer;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-muted);
        transition: all 0.18s ease; text-align: left;
    }
    .mode-selector-btn:hover {
        background: var(--bg-glass-hover, rgba(255,255,255,0.04));
        color: var(--text-primary);
        border-color: rgba(139,92,246,0.3);
    }
    .mode-selector-btn i { font-size: 16px; opacity: 0.9; }
    .mode-selector-btn-label { font-size: 13px; font-weight: 700; color: var(--text-primary); }
    .mode-selector-btn-desc  { font-size: 11px; color: var(--text-faint); line-height: 1.3; }
    .mode-selector-btn.is-active {
        background: linear-gradient(135deg, rgba(139,92,246,0.25), rgba(99,102,241,0.18));
        color: #fff;
        border-color: rgba(167,139,250,0.6);
        box-shadow: 0 4px 18px -4px rgba(139,92,246,0.45), inset 0 1px 0 rgba(255,255,255,0.08);
    }
    .mode-selector-btn.is-active i { color: #c4b5fd; }
    .mode-selector-btn.is-busy { opacity: 0.6; pointer-events: none; }
</style>
<script>
(function() {
    if (window.__modeSelectorWired) return;
    window.__modeSelectorWired = true;

    var EDITOR_URLS = {
        list:           @json(route('user.links.blocks.editor', $link)),
        slides:         @json(route('user.links.slides.editor', $link)),
        conversational: @json(route('user.links.conversational.editor', $link)),
    };
    var TOGGLE_URLS = {
        slides:         @json(route('user.links.slides.toggle', $link)),
        conversational: @json(route('user.links.conversational.toggle', $link)),
    };
    var CSRF = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').content
        : @json(csrf_token());

    function callToggle(target, enabled) {
        return fetch(TOGGLE_URLS[target], {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ enabled: enabled }),
        }).then(function(r) { if (!r.ok) throw new Error('Mode toggle failed'); return r.json(); });
    }

    document.querySelectorAll('.mode-selector-btn[data-mode]').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var target = btn.dataset.mode;
            var current = @json($__currentMode);
            if (target === current) {
                window.location.href = EDITOR_URLS[target];
                return;
            }
            document.querySelectorAll('.mode-selector-btn[data-mode]').forEach(function(b) { b.classList.add('is-busy'); });
            try {
                if (target === 'list') {
                    // Disable whichever one is currently active. Either toggle
                    // call sets the link mode to 'list' when enabled=false, so
                    // we just hit the matching one (or both as a safety net).
                    if (current === 'slides')         await callToggle('slides', false);
                    if (current === 'conversational') await callToggle('conversational', false);
                } else {
                    // Turning on a target mode is enough — toggleMode overwrites
                    // mode unconditionally when enabled=true.
                    await callToggle(target, true);
                }
                window.location.href = EDITOR_URLS[target];
            } catch (e) {
                document.querySelectorAll('.mode-selector-btn[data-mode]').forEach(function(b) { b.classList.remove('is-busy'); });
                alert(e.message || 'Could not switch modes');
            }
        });
    });
})();
</script>
@endonce
