@php
    $settingsTabs = [
        'appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance', 'route' => 'user.links.settings.appearance'],
        'layout' => ['icon' => 'fa-ruler-combined', 'label' => 'Layout', 'route' => 'user.links.settings.layout'],
        'block-theme' => ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Block Theme', 'route' => 'user.links.settings.block-theme'],
        'themes' => ['icon' => 'fa-calendar-week', 'label' => 'Themes', 'route' => 'user.links.themes.settings'],
        'advanced' => ['icon' => 'fa-sliders-h', 'label' => 'Advanced', 'route' => 'user.links.settings.advanced'],
        'embed'    => ['icon' => 'fa-code', 'label' => 'Embed', 'route' => 'user.links.settings.embed'],
        'splash'   => ['icon' => 'fa-rocket', 'label' => 'Intro', 'route' => 'user.links.splash'],
    ];
    // Design-locked pages hide every styling tab — only non-design
    // settings surfaces remain until the creator detaches from the template.
    $__designLocked = method_exists($link, 'isDesignLocked') && $link->isDesignLocked();
    if ($__designLocked) {
        unset($settingsTabs['appearance'], $settingsTabs['layout'], $settingsTabs['block-theme'], $settingsTabs['themes']);
    }
@endphp

@if($__designLocked)
@php $__lockInfo = $link->designLockInfo(); @endphp
<div class="mb-4 px-4 py-3 rounded-xl text-sm flex flex-wrap items-center gap-3" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
    <i class="fas fa-lock"></i>
    <span class="font-medium">
        Design locked by the "{{ $__lockInfo['template_name'] ?? 'template' }}" template.
        <span class="opacity-80 font-normal">Content stays editable; styling follows the template.</span>
    </span>
    <form method="POST" action="{{ route('user.links.templates.detach-design', $link) }}" class="ml-auto"
          onsubmit="return confirm('Detach from the template? The page keeps its current look, but future template design updates will no longer apply and all styling controls will be unlocked.');">
        @csrf
        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.35); color: #f59e0b;">
            <i class="fas fa-unlink mr-1"></i> Detach from template
        </button>
    </form>

    @php
        $__palettes = $link->designLockPalettes();
        $__activePalette = $link->designLockPaletteKey();
    @endphp
    @if(!empty($__palettes))
    <div class="w-full flex flex-wrap items-center gap-2 pt-1" id="tpl-palette-picker">
        <span class="text-xs font-semibold opacity-80"><i class="fas fa-swatchbook mr-1"></i> Color palette:</span>
        @foreach($__palettes as $__p)
            @php
                $__c = is_array($__p['colors'] ?? null) ? $__p['colors'] : [];
                $__bg = ($__c['background_type'] ?? '') === 'gradient' && !empty($__c['gradient_colors'][0])
                    ? 'linear-gradient(135deg, ' . e(implode(', ', array_slice((array) $__c['gradient_colors'], 0, 3))) . ')'
                    : e($__c['background_color'] ?? '#111');
                $__isActive = ($__p['key'] ?? '') === $__activePalette;
            @endphp
            <button type="button"
                    class="tpl-palette-swatch inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-semibold"
                    data-palette-key="{{ $__p['key'] ?? '' }}"
                    style="border: 1px solid rgba(245,158,11,{{ $__isActive ? '0.7' : '0.25' }}); background: rgba(245,158,11,{{ $__isActive ? '0.18' : '0.06' }}); color: #f59e0b;">
                <span class="inline-block w-4 h-4 rounded-full border border-white/30" style="background: {{ $__bg }};"></span>
                {{ $__p['name'] ?? $__p['key'] ?? 'Palette' }}
                @if($__isActive)<i class="fas fa-check text-[10px]"></i>@endif
            </button>
        @endforeach
    </div>
    <script>
    (function() {
        var picker = document.getElementById('tpl-palette-picker');
        if (!picker || picker.__bound) return;
        picker.__bound = true;
        picker.addEventListener('click', function(e) {
            var btn = e.target.closest('.tpl-palette-swatch');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            fetch(@json(route('user.links.templates.apply-palette', $link)), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ palette: btn.getAttribute('data-palette-key') })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) { window.location.reload(); }
                else { btn.disabled = false; alert(data.error || 'Failed to apply palette'); }
            }).catch(function() { btn.disabled = false; alert('Failed to apply palette'); });
        });
    })();
    </script>
    @endif
</div>
@endif

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
    <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
</div>
@endif

<div class="settings-tabs inline-flex items-center gap-1 mb-6 p-1 rounded-full">
    @foreach($settingsTabs as $tabKey => $tab)
    <a href="{{ route($tab['route'], $link) }}"
       class="settings-tab no-underline {{ $activeSettingsTab === $tabKey ? 'is-active' : '' }}">
        <i class="fas {{ $tab['icon'] }} text-[10px]"></i>
        <span>{{ $tab['label'] }}</span>
    </a>
    @endforeach
</div>
<style>
    .settings-tabs {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(16px) saturate(140%);
        -webkit-backdrop-filter: blur(16px) saturate(140%);
    }
    .settings-tab {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: var(--text-faint);
        border-radius: 9999px;
        transition: color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .settings-tab:hover { color: var(--text-primary); }
    .settings-tab.is-active {
        color: #fff;
        background: linear-gradient(135deg, rgba(144,172,255,0.95), rgba(103,232,249,0.85));
        box-shadow: 0 6px 18px -6px rgba(144,172,255,0.55), 0 2px 8px -2px rgba(103,232,249,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    html.light-mode .settings-tab.is-active {
        background: linear-gradient(135deg, #5c83ff, #3d6bff);
        box-shadow: 0 4px 14px -4px rgba(61,107,255,0.45);
    }
    #settings-tab-content.is-loading { opacity: 0.5; pointer-events: none; transition: opacity .15s ease; }
</style>

<script>
/* AJAX-swap settings sub-tabs so the device preview iframe never reloads.
   Only the left column (#settings-tab-content) is fetched and replaced.
   Falls back to a normal navigation if the destination doesn't expose the
   #settings-tab-content slot. */
(function() {
    if (window.__settingsTabSwapInit) return;
    window.__settingsTabSwapInit = true;

    function swapSettingsTab(url, push) {
        var container = document.getElementById('settings-tab-content');
        if (!container) { window.location.href = url; return; }
        container.classList.add('is-loading');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin'
        })
        .then(function(r) {
            if (!r.ok) throw new Error('http ' + r.status);
            return r.text();
        })
        .then(function(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var fresh = doc.getElementById('settings-tab-content');
            if (!fresh) { window.location.href = url; return; }

            // Defensive: if a settings page ever renders the tab bar INSIDE
            // the swap container, strip it so we never stack a second bar
            // under the one already on the page.
            fresh.querySelectorAll('.settings-tabs').forEach(function(bar) {
                bar.parentNode.removeChild(bar);
            });

            container.innerHTML = fresh.innerHTML;

            // <script> tags injected via innerHTML don't execute — re-create them.
            container.querySelectorAll('script').forEach(function(oldScript) {
                var s = document.createElement('script');
                for (var i = 0; i < oldScript.attributes.length; i++) {
                    var a = oldScript.attributes[i];
                    s.setAttribute(a.name, a.value);
                }
                s.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(s, oldScript);
            });

            var newTitle = doc.querySelector('title');
            if (newTitle) document.title = newTitle.textContent;

            if (push) history.pushState({ settingsTabUrl: url }, '', url);

            // Update active state on the visible tabs.
            document.querySelectorAll('.settings-tab').forEach(function(a) {
                if (a.getAttribute('href') === url) a.classList.add('is-active');
                else a.classList.remove('is-active');
            });

            // Bind Alpine to the freshly inserted DOM.
            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                try { window.Alpine.initTree(container); } catch (e) { console.warn('Alpine.initTree failed', e); }
            }

            container.classList.remove('is-loading');
            window.scrollTo({ top: 0, behavior: 'auto' });
        })
        .catch(function(err) {
            console.warn('settings tab swap failed, falling back to full reload', err);
            window.location.href = url;
        });
    }

    document.addEventListener('click', function(e) {
        var link = e.target.closest('.settings-tab');
        if (!link) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || (e.button !== undefined && e.button !== 0)) return;
        if (link.classList.contains('is-active')) { e.preventDefault(); return; }
        var url = link.getAttribute('href');
        if (!url || url.indexOf('#') === 0) return;
        e.preventDefault();
        swapSettingsTab(url, true);
    });

    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.settingsTabUrl) swapSettingsTab(e.state.settingsTabUrl, false);
    });
})();
</script>
