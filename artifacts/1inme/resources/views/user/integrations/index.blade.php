@extends('user.layouts.settings')
@section('title', 'Integrations')

@section('settings-content')
<div x-data="{ tab: '{{ $activeTab }}' }">
    @include('user.partials.page-hero', [
        'title'    => 'Integrations',
        'subtitle' => 'Reusable third-party configurations, payment gateways, SMS senders, and email mailers. Save once, attach anywhere.',
        'icon'     => 'fa-plug',
        'chips'    => [
            ['icon' => 'fa-layer-group', 'text' => collect($configs)->flatten()->count() . ' total'],
        ],
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #10b981;">
            <i class="fas fa-check-circle"></i>{{ session('success') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="card-premium p-2 mb-5 flex flex-wrap gap-1">
        @foreach($kinds as $kindKey => $meta)
            @php $count = ($configs[$kindKey] ?? collect())->count(); @endphp
            <button type="button"
                    @click="tab = '{{ $kindKey }}'"
                    :class="tab === '{{ $kindKey }}' ? 'tab-active' : 'tab-inactive'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition flex-1 sm:flex-none justify-center">
                <i class="fas {{ $meta['icon'] }}" style="color: {{ $meta['color'] }};"></i>
                <span>{{ $meta['label'] }}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full" style="background: var(--bg-glass-input); color: var(--text-muted);">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    @foreach($kinds as $kindKey => $meta)
        <div x-show="tab === '{{ $kindKey }}'" x-cloak>
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-bold" style="color: var(--text-primary);">{{ $meta['label'] }}</h2>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ $meta['subtitle'] }}</p>
                </div>
                <a href="{{ route('user.integrations.create', $kindKey) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold"
                   style="background: var(--accent); color: #fff;">
                    <i class="fas fa-plus"></i> Add {{ $meta['label'] }} configuration
                </a>
            </div>

            @php $kindConfigs = $configs[$kindKey] ?? collect(); @endphp
            @if($kindConfigs->isEmpty())
                <div class="card-premium p-12 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center"
                         style="background: {{ $meta['color'] }}20;">
                        <i class="fas {{ $meta['icon'] }} text-2xl" style="color: {{ $meta['color'] }};"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No {{ strtolower($meta['label']) }} configurations yet</h3>
                    <p class="text-sm mb-5 max-w-md mx-auto" style="color: var(--text-muted);">{{ $meta['subtitle'] }}</p>
                    <a href="{{ route('user.integrations.create', $kindKey) }}"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold"
                       style="background: var(--accent); color: #fff;">
                        <i class="fas fa-plus"></i> Add your first
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($kindConfigs as $c)
                        <div class="card-premium p-5 flex flex-col">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background: {{ $c->providerColor() }}20;">
                                    <i class="fab fa-brands {{ $c->providerIcon() }} text-lg" style="color: {{ $c->providerColor() }};"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('user.integrations.edit', $c) }}"
                                       class="font-bold text-base hover:underline truncate block"
                                       style="color: var(--text-primary);">{{ $c->name }}</a>
                                    <div class="text-xs truncate" style="color: var(--text-muted);">{{ $c->providerLabel() }}</div>
                                </div>
                                @if($c->is_default)
                                    <span class="text-[10px] px-2 py-1 rounded-full font-semibold"
                                          style="background: {{ $meta['color'] }}20; color: {{ $meta['color'] }};">DEFAULT</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 text-[11px] mb-4 mt-auto" style="color: var(--text-dimmed);">
                                @if($c->is_active)
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-circle text-[6px] text-emerald-400"></i> Active</span>
                                @else
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-circle text-[6px] text-gray-400"></i> Disabled</span>
                                @endif
                                <span>·</span>
                                <span>Created {{ $c->created_at->diffForHumans() }}</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-3 border-t" style="border-color: var(--border-glass);">
                                <a href="{{ route('user.integrations.edit', $c) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                   style="background: var(--bg-glass-input); color: var(--text-primary);">
                                    <i class="fas fa-pen-to-square"></i> Edit
                                </a>
                                @if(! $c->is_default)
                                    <form method="POST" action="{{ route('user.integrations.set-default', $c) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                                style="background: var(--bg-glass-input); color: var(--text-primary);">
                                            <i class="fas fa-star"></i> Set default
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('user.integrations.toggle', $c) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                            style="background: var(--bg-glass-input); color: var(--text-primary);">
                                        <i class="fas {{ $c->is_active ? 'fa-pause' : 'fa-play' }}"></i> {{ $c->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('user.integrations.destroy', $c) }}"
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this configuration?', message: 'Anything that depends on it will fall back to defaults.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})"
                                      class="ml-auto">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                            style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>

<style>
    [x-cloak] { display: none !important; }
    .tab-active   { background: var(--accent); color: #fff; }
    .tab-inactive { background: transparent; color: var(--text-muted); }
    .tab-inactive:hover { background: var(--bg-glass-input); color: var(--text-primary); }
</style>
@endsection
