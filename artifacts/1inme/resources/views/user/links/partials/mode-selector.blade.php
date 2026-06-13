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

<div class="mode-selector-bar mb-4">
    <span class="mode-selector-label">
        <i class="fas fa-eye text-[10px]"></i>
        Display mode
    </span>
    <div class="mode-selector-seg" role="group" aria-label="Display mode">
        @foreach($__modes as $key => $meta)
            <button type="button"
                    class="mode-selector-btn {{ $__currentMode === $key ? 'is-active' : '' }}"
                    data-mode="{{ $key }}"
                    title="{{ $meta['desc'] }}">
                <i class="fas {{ $meta['icon'] }}"></i>
                <span class="mode-selector-btn-label">{{ $meta['label'] }}</span>
            </button>
        @endforeach
    </div>
</div>

@once
<style>
    .mode-selector-bar {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .mode-selector-label {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; font-weight: 600; color: var(--text-muted);
        white-space: nowrap;
    }
    .mode-selector-label i { color: #a78bfa; }
    .mode-selector-seg {
        display: inline-flex; align-items: center; gap: 2px;
        padding: 3px; border-radius: 999px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(12px);
    }
    .mode-selector-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 6px 14px; border-radius: 999px; cursor: pointer;
        background: transparent;
        border: none;
        color: var(--text-faint);
        transition: all 0.18s ease; white-space: nowrap;
    }
    .mode-selector-btn:hover {
        color: var(--text-primary);
        background: var(--bg-glass-hover, rgba(255,255,255,0.05));
    }
    .mode-selector-btn i { font-size: 12px; opacity: 0.9; }
    .mode-selector-btn-label { font-size: 12px; font-weight: 600; }
    .mode-selector-btn.is-active {
        background: linear-gradient(135deg, rgba(167,139,250,0.95), rgba(103,232,249,0.85));
        color: #fff;
        box-shadow: 0 4px 14px -5px rgba(139,92,246,0.55), inset 0 1px 0 rgba(255,255,255,0.2);
    }
    .mode-selector-btn.is-active i { color: #fff; opacity: 1; }
    .mode-selector-btn.is-busy { opacity: 0.6; pointer-events: none; }
    @media (max-width: 480px) {
        .mode-selector-bar { gap: 8px; }
        .mode-selector-btn { padding: 6px 11px; }
        .mode-selector-btn-label { font-size: 11px; }
    }
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
