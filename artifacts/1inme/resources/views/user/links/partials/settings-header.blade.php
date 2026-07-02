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
@endphp

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
