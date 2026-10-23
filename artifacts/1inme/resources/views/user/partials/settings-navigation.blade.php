<nav aria-label="Settings categories" class="settings-category-nav mb-5">
    <label for="settings-category" class="settings-category-label">Settings section</label>
    <select id="settings-category" class="settings-category-select" onchange="if(this.value) window.location.assign(this.value)">
        @if(!$__activeTabKey)<option value="" selected>Choose a settings section</option>@endif
        @foreach($__settingsTabs as $__key => $__tab)
            <option value="{{ route($__tab['route']) }}" @selected($__activeTabKey === $__key)>{{ $__tab['label'] }}</option>
        @endforeach
    </select>
    <noscript><ul class="flex gap-3 flex-wrap">@foreach($__settingsTabs as $__tab)<li><a class="underline" href="{{ route($__tab['route']) }}">{{ $__tab['label'] }}</a></li>@endforeach</ul></noscript>
</nav>
<style>
.settings-category-nav{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;border:1px solid var(--border-soft,var(--border-glass));border-radius:14px;background:var(--bg-card)}
.settings-category-label{font-size:12px;font-weight:650;color:var(--text-muted)}
.settings-category-select{min-height:42px;max-width:100%;width:min(100%,320px);padding:9px 36px 9px 12px;border:1px solid var(--border-soft,var(--border-glass));border-radius:10px;background:var(--bg-input,var(--bg-card));color:var(--text-primary);font-size:13px;font-weight:650;color-scheme:dark}
html.light-mode .settings-category-select{color-scheme:light}
.settings-category-select:focus-visible{outline:2px solid var(--accent,#6d72ed);outline-offset:3px}
@media(max-width:480px){.settings-category-nav{align-items:stretch;gap:6px}.settings-category-label{width:100%}.settings-category-select{width:100%}}
</style>
