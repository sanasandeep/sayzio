{{--
    Consolidated Settings hub layout (Task #3220).

    Wraps every individual settings surface in one tabbed shell:
      • a slim "Settings" header,
      • a primary tab strip (priority-ordered, from SettingsTabs),
      • a secondary sub-tab strip for grouped tabs (Security,
        Connected Accounts & Apps, Verification & Badges),
      • then the page's own content via @yield('settings-content').

    Individual views only swap `@extends('user.layouts.app')` +
    `@section('content')` for `@extends('user.layouts.settings')` +
    `@section('settings-content')` — no data-threading required. The
    active tab/sub-tab is inferred from the current route name so the
    child view never has to declare where it lives.
--}}
@extends('user.layouts.app')

@php
    use App\Modules\User\Support\SettingsTabs;

    $__settingsTabs   = SettingsTabs::visibleTabs();
    $__activeTabKey   = SettingsTabs::activeKey();
    $__activeTab      = $__activeTabKey ? ($__settingsTabs[$__activeTabKey] ?? null) : null;
    $__activeSubs     = $__activeTab['subs'] ?? [];
@endphp

@section('content')
    <div class="page-hero mb-6">
        <div class="flex items-center gap-3">
            <div class="nav-icon-wrap" style="width:2.5rem;height:2.5rem;">
                <i class="fas fa-sliders text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold" style="color: var(--text-strong);">Settings</h1>
                <p class="text-xs sm:text-sm mt-0.5" style="color: var(--text-muted);">
                    Manage your profile, security, connections and account preferences in one place.
                </p>
            </div>
        </div>
    </div>

    {{-- Primary tab strip --}}
    <nav aria-label="Settings" class="settings-tabs mb-4">
        <div class="settings-tabs-scroll flex items-center gap-1.5 overflow-x-auto pb-1">
            @foreach($__settingsTabs as $__key => $__tab)
                @php $__tabActive = SettingsTabs::matches($__tab['match'], $__tab['not'] ?? []); @endphp
                <a href="{{ route($__tab['route']) }}"
                   class="settings-tab {{ $__tabActive ? 'active' : '' }}"
                   @if($__tabActive) aria-current="page" @endif>
                    <i class="fas {{ $__tab['icon'] }}"></i>
                    <span>{{ $__tab['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Secondary sub-tab strip (grouped tabs only) --}}
    @if(!empty($__activeSubs))
        <nav aria-label="{{ $__activeTab['label'] }} sections" class="settings-subtabs mb-6">
            <div class="settings-subtabs-scroll flex items-center gap-1.5 overflow-x-auto pb-1">
                @foreach($__activeSubs as $__subKey => $__sub)
                    @php $__subActive = SettingsTabs::matches($__sub['match'], $__sub['not'] ?? []); @endphp
                    <a href="{{ route($__sub['route']) }}"
                       class="settings-subtab {{ $__subActive ? 'active' : '' }}"
                       @if($__subActive) aria-current="page" @endif>
                        <i class="fas {{ $__sub['icon'] }} text-[11px]"></i>
                        <span>{{ $__sub['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    <div class="settings-panel">
        @yield('settings-content')
    </div>

    @push('styles')
    <style>
        .settings-tabs-scroll, .settings-subtabs-scroll { scrollbar-width: thin; }
        .settings-tab {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0.9rem; border-radius: 0.75rem;
            font-size: 0.82rem; font-weight: 600; white-space: nowrap;
            color: var(--text-muted);
            border: 1px solid var(--border-glass);
            background: var(--surface-glass, rgba(255,255,255,0.02));
            transition: color .15s, background .15s, border-color .15s;
        }
        .settings-tab i { font-size: 0.8rem; opacity: 0.85; }
        .settings-tab:hover { color: var(--text-strong); border-color: var(--border-glass-light); }
        .settings-tab.active {
            color: #fff;
            background: var(--color-primary-600, #2563eb);
            border-color: var(--color-primary-600, #2563eb);
        }
        html.light-mode .settings-tab.active { color: #fff; }
        .settings-subtab {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.35rem 0.75rem; border-radius: 0.6rem;
            font-size: 0.78rem; font-weight: 600; white-space: nowrap;
            color: var(--text-muted);
            border: 1px solid transparent;
            transition: color .15s, background .15s, border-color .15s;
        }
        .settings-subtab:hover { color: var(--text-strong); }
        .settings-subtab.active {
            color: var(--color-primary-500, #3b82f6);
            background: var(--color-primary-soft, rgba(37,99,235,0.10));
            border-color: var(--color-primary-soft, rgba(37,99,235,0.20));
        }
    </style>
    @endpush
@endsection
