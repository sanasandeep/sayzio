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
    {{-- Settings hand-rolled its own header: a bare .page-hero with an icon
         and two lines in it. That skipped .page-hero-deco and the card
         ribbon, which is the whole decorated treatment, so the one hub every
         account passes through was the one page without the header design
         every other page has. It uses the shared partial now, like Links,
         Dashboard and every settings SUB-page already did.

         The chip names the section you are in, from the tab that is already
         resolved two lines up -- the hero was the only place on the page that
         did not say where you were. --}}
    @include('user.partials.page-hero', [
        'title'    => 'Settings',
        'subtitle' => 'Manage your profile, security, connections and account preferences in one place.',
        'icon'     => 'fa-sliders',
        'chips'    => $__activeTab
            ? [['icon' => $__activeTab['icon'], 'text' => $__activeTab['label']]]
            : [],
    ])

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
            background: var(--accent, #2563eb);
            border-color: var(--accent, #2563eb);
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
            color: var(--accent, #3b82f6);
            background: color-mix(in srgb, var(--accent, #3b82f6) 12%, transparent);
            border-color: color-mix(in srgb, var(--accent, #3b82f6) 25%, transparent);
        }
    </style>
    @endpush
@endsection
