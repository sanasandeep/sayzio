{{--
    Consolidated Settings hub layout (Task #3220).

    Wraps every individual settings surface in one tabbed shell:
      • a compact Settings category selector (from SettingsTabs),
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
    {{-- Global settings navigation stays distinct from each page's editor tabs. --}}
    @include('user.partials.settings-navigation')

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
        .settings-subtabs-scroll { scrollbar-width: thin; }
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
