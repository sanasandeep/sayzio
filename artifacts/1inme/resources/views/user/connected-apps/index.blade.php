@extends('user.layouts.settings')
@section('title', 'Connected Apps')

@section('settings-content')
<div>
    @include('user.partials.page-hero', [
        'title'    => 'Connected Apps',
        'subtitle' => 'Connect your CRM and analytics tools. Push new leads, subscribers and form submissions out automatically, pull CRM contacts back into Sayzio, and forward click events to Google Analytics.',
        'icon'     => 'fa-plug',
        'chips'    => [
            ['icon' => 'fa-arrows-rotate', 'text' => 'Two-way CRM sync'],
            ['icon' => 'fa-chart-line', 'text' => 'GA4 forwarding'],
        ],
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #10b981;">
            <i class="fas fa-check-circle"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
            <i class="fas fa-triangle-exclamation"></i>{{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($providers as $p)
            @php
                $meta       = $p['meta'];
                $conn       = $p['connection'];
                $available  = $p['available'];
                $status     = $p['status'];
                $isConfig   = ($meta['connect_type'] ?? '') === 'config';
            @endphp
            <div class="card-premium p-5 flex flex-col" x-data="{ manage: false }">
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                         style="background: {{ $meta['color'] }}20;">
                        <i class="{{ $meta['icon'] }} text-xl" style="color: {{ $meta['color'] }};"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold truncate" style="color: var(--text-primary);">{{ $meta['label'] }}</h3>
                            @if($conn)
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                                      style="background: rgba(16,185,129,0.15); color: #10b981;">
                                    {{ ($conn['status'] ?? '') === 'paused' ? 'Paused' : 'Connected' }}
                                </span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                                      style="background: var(--bg-glass-input); color: var(--text-muted);">
                                    {{ $status['label'] }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] mt-0.5 uppercase tracking-wide" style="color: var(--text-muted);">
                            {{ $meta['kind'] === 'crm' ? 'CRM · two-way sync' : 'Analytics · event forwarding' }}
                        </p>
                    </div>
                </div>

                <p class="text-xs leading-relaxed mb-4 flex-1" style="color: var(--text-muted);">{{ $meta['blurb'] }}</p>

                {{-- Connected: management controls --}}
                @if($conn)
                    @if($conn['account_label'] ?? null)
                        <div class="text-xs mb-3 px-3 py-2 rounded-lg" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                            <i class="fas fa-circle-check mr-1" style="color:#10b981;"></i>{{ $conn['account_label'] }}
                        </div>
                    @endif

                    {{-- Sync status: last synced, records sent/pulled and any last error. --}}
                    @php
                        $hasError = ($conn['last_sync_status'] ?? null) === 'error' || !empty($conn['last_sync_error']);
                        $lastSynced = ($conn['kind'] ?? '') === 'crm'
                            ? ($conn['last_synced_at'] ?? $conn['last_pull_at'] ?? null)
                            : ($conn['last_synced_at'] ?? null);
                    @endphp
                    <div class="text-[11px] mb-3 px-3 py-2 rounded-lg space-y-1"
                         style="background: var(--bg-glass-input); color: var(--text-muted);">
                        <div class="flex items-center justify-between">
                            <span><i class="fas fa-clock-rotate-left mr-1"></i>Last synced</span>
                            <span style="color: var(--text-secondary);">
                                {{ $lastSynced ? \Carbon\Carbon::parse($lastSynced)->diffForHumans() : 'Never synced' }}
                            </span>
                        </div>
                        @if(($meta['kind'] ?? '') === 'crm')
                            <div class="flex items-center justify-between">
                                <span><i class="fas fa-arrow-up-from-bracket mr-1"></i>Records sent</span>
                                <span style="color: var(--text-secondary);">{{ number_format($conn['records_sent'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span><i class="fas fa-arrow-down-to-bracket mr-1"></i>Records pulled</span>
                                <span style="color: var(--text-secondary);">{{ number_format($conn['records_pulled'] ?? 0) }}</span>
                            </div>
                        @else
                            <div class="flex items-center justify-between">
                                <span><i class="fas fa-paper-plane mr-1"></i>Events forwarded</span>
                                <span style="color: var(--text-secondary);">{{ number_format($conn['records_sent'] ?? 0) }}</span>
                            </div>
                        @endif
                        @if($hasError)
                            <div class="mt-1 pt-1 flex items-start gap-1.5" style="border-top:1px solid var(--border-glass); color:#ef4444;">
                                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                                <span>{{ $conn['last_sync_error'] ?: 'Last sync failed — try reconnecting.' }}</span>
                            </div>
                        @endif
                    </div>

                    @if(($meta['kind'] ?? '') === 'crm')
                        <form method="POST" action="{{ route('user.connected-apps.update', $conn['id']) }}" class="space-y-2 mb-3">
                            @csrf @method('PUT')
                            <label class="flex items-center justify-between text-xs" style="color: var(--text-secondary);">
                                <span><i class="fas fa-arrow-up-from-bracket mr-1"></i>Push leads out to {{ $meta['label'] }}</span>
                                {{-- Hidden 0 ensures an unchecked box still posts a false value (sometimes|boolean). --}}
                                <input type="hidden" name="push_enabled" value="0">
                                <input type="checkbox" name="push_enabled" value="1" @checked($conn['push_enabled'] ?? false)
                                       onchange="this.form.submit()">
                            </label>
                            <label class="flex items-center justify-between text-xs" style="color: var(--text-secondary);">
                                <span><i class="fas fa-arrow-down-to-bracket mr-1"></i>Pull contacts into Sayzio</span>
                                <input type="hidden" name="pull_enabled" value="0">
                                <input type="checkbox" name="pull_enabled" value="1" @checked($conn['pull_enabled'] ?? false)
                                       onchange="this.form.submit()">
                            </label>
                        </form>

                        {{-- Field mapping editor: which Sayzio field feeds which CRM field. --}}
                        @php
                            $defaults = $meta['default_field_mappings'] ?? [];
                            $current  = $conn['field_mappings'] ?? [];
                            $sayzioLabels = [
                                'email' => 'Email', 'first_name' => 'First name', 'last_name' => 'Last name',
                                'phone' => 'Phone', 'company' => 'Company', 'display_name' => 'Full name',
                            ];
                        @endphp
                        @if(!empty($defaults))
                            <button type="button" @click="manage = !manage"
                                    class="w-full flex items-center justify-between text-xs mb-2 px-3 py-2 rounded-lg"
                                    style="background: var(--bg-glass-input); color: var(--text-secondary);">
                                <span><i class="fas fa-sliders mr-1"></i>Field mapping</span>
                                <i class="fas" :class="manage ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>
                            <form x-show="manage" x-cloak method="POST"
                                  action="{{ route('user.connected-apps.update', $conn['id']) }}" class="space-y-2 mb-3">
                                @csrf @method('PUT')
                                <p class="text-[11px]" style="color: var(--text-muted);">
                                    Map each Sayzio field to the matching {{ $meta['label'] }} field. Leave blank to skip.
                                </p>
                                @foreach($defaults as $sayzioField => $providerDefault)
                                    <label class="block text-[11px]" style="color: var(--text-secondary);">
                                        {{ $sayzioLabels[$sayzioField] ?? ucfirst(str_replace('_', ' ', $sayzioField)) }}
                                        <input type="text" name="field_mappings[{{ $sayzioField }}]"
                                               value="{{ $current[$sayzioField] ?? $providerDefault }}"
                                               placeholder="{{ $providerDefault }}"
                                               class="w-full mt-0.5 px-2.5 py-1.5 rounded-lg text-xs"
                                               style="background: var(--bg-glass-input); border:1px solid var(--border-glass); color: var(--text-primary);">
                                    </label>
                                @endforeach
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                        style="background: var(--accent); color:#fff;">
                                    <i class="fas fa-save"></i> Save mapping
                                </button>
                            </form>
                        @endif
                    @endif

                    <div class="flex flex-wrap gap-2 mt-auto">
                        @if(($meta['kind'] ?? '') === 'crm')
                            <form method="POST" action="{{ route('user.connected-apps.sync', $conn['id']) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold"
                                        style="background: var(--bg-glass-input); color: var(--text-primary);">
                                    <i class="fas fa-arrows-rotate"></i> Sync now
                                </button>
                            </form>
                            <form method="POST" action="{{ route('user.connected-apps.update', $conn['id']) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="paused" value="{{ ($conn['status'] ?? '') === 'paused' ? '0' : '1' }}">
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold"
                                        style="background: var(--bg-glass-input); color: var(--text-primary);">
                                    <i class="fas {{ ($conn['status'] ?? '') === 'paused' ? 'fa-play' : 'fa-pause' }}"></i>
                                    {{ ($conn['status'] ?? '') === 'paused' ? 'Resume' : 'Pause' }}
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('user.connected-apps.destroy', $conn['id']) }}"
                              onsubmit="return confirm('Disconnect {{ $meta['label'] }}? Synced contacts stay in Sayzio.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold"
                                    style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                <i class="fas fa-link-slash"></i> Disconnect
                            </button>
                        </form>
                    </div>

                {{-- Not connected: connect action or coming-soon --}}
                @elseif(!$available)
                    <button type="button" disabled
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold opacity-60 cursor-not-allowed mt-auto"
                            style="background: var(--bg-glass-input); color: var(--text-muted);">
                        <i class="fas fa-clock"></i> Coming soon
                    </button>
                @elseif($isConfig)
                    <x-how-to-get-this guide-key="connected_apps.analytics.{{ $meta['key'] }}" class="mb-2" />
                    <form method="POST" action="{{ route('user.connected-apps.google-analytics') }}" class="space-y-2 mt-auto">
                        @csrf
                        @foreach($meta['config_fields'] ?? [] as $field)
                            <input type="{{ ($field['secret'] ?? false) ? 'password' : 'text' }}"
                                   name="{{ $field['key'] }}"
                                   placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                   class="w-full px-3 py-2 rounded-lg text-sm"
                                   style="background: var(--bg-glass-input); border:1px solid var(--border-glass); color: var(--text-primary);"
                                   required>
                        @endforeach
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold"
                                style="background: var(--accent); color: #fff;">
                            <i class="fas fa-plug"></i> Connect {{ $meta['label'] }}
                        </button>
                    </form>
                @else
                    <div class="mt-auto">
                        <x-how-to-get-this guide-key="connected_apps.crm.{{ $meta['key'] }}" class="mb-2" />
                        <a href="{{ route('user.connected-apps.connect', $meta['key']) }}"
                           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold w-full"
                           style="background: var(--accent); color: #fff;">
                            <i class="fas fa-plug"></i> Connect {{ $meta['label'] }}
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
