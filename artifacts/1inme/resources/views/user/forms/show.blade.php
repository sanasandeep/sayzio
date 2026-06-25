@extends('user.layouts.app')
@section('title', $form->title . ' · Form')

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__showActions = [
        ['label' => 'View Public', 'url' => $form->getPublicUrl(), 'icon' => 'fa-external-link-alt', 'class' => 'btn-ghost', 'target' => '_blank'],
    ];
    if ($__can('inbox.edit')) {
        array_unshift($__showActions, ['label' => 'Edit Fields', 'url' => route('user.forms.builder', $form), 'icon' => 'fa-pen', 'class' => 'btn-primary']);
    }
@endphp
<div class="max-w-6xl mx-auto" x-data="{ copied: false }">
    @include('user.partials.page-hero', [
        'title' => $form->title,
        'subtitle' => $form->description,
        'icon' => 'fa-wpforms',
        'back' => route('user.forms.index'),
        'url' => $form->getPublicUrl(),
        'chips' => [
            ['icon' => 'fa-circle ' . ($form->is_active ? 'text-emerald-400' : 'text-gray-400'), 'text' => $form->is_active ? 'Active' : 'Disabled'],
            ['icon' => 'fa-database text-pink-400', 'text' => number_format($form->total_submissions) . ' submissions'],
            ['icon' => 'fa-eye text-violet-400', 'text' => number_format($form->total_views) . ' views'],
        ],
        'actions' => $__showActions,
    ])

    @if(!$__can('inbox.edit') && !$__can('inbox.delete'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm flex items-center gap-2" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #b45309;">
        <i class="fas fa-lock"></i>
        <span>Your role is view-only on forms in this workspace. Editing, enabling/disabling and deleting are reserved for admins.</span>
    </div>
    @endif

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    @include('user.forms._tabs')

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        @foreach([
            ['Today',  $stats['today'],  'fa-bolt',         '#f59e0b'],
            ['7 days', $stats['week'],   'fa-calendar-day', '#8b5cf6'],
            ['30 days',$stats['month'],  'fa-calendar',     '#6366f1'],
            ['Unread', $stats['unread'], 'fa-envelope',     '#ec4899'],
            ['Conv. %',$stats['conversion'] . '%', 'fa-percentage', '#10b981'],
        ] as [$label, $val, $icon, $color])
            <div class="card-premium p-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: {{ $color }}22;">
                        <i class="fas {{ $icon }} text-sm" style="color: {{ $color }};"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">{{ $label }}</div>
                        <div class="text-lg font-bold leading-none mt-1" style="color: var(--text-primary);">{{ $val }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Advanced analytics (Pro and above) --}}
    @if($advancedAnalytics && $analytics)
        @php($a = $analytics)
        @if($a['is_paid'] || ($a['revenue']['paid_count'] ?? 0) > 0)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            @php($rev = $a['revenue'])
            @foreach([
                ['Revenue', number_format(($rev['gross_cents'] ?? 0) / 100, 2) . ' ' . ($rev['currency'] ?? 'USD'), 'fa-sack-dollar', '#10b981'],
                ['Paid', number_format($rev['paid_count'] ?? 0), 'fa-circle-check', '#22c55e'],
                ['Awaiting', number_format($rev['pending'] ?? 0), 'fa-hourglass-half', '#f59e0b'],
                ['Avg / order', ($rev['paid_count'] ?? 0) > 0 ? number_format((($rev['gross_cents'] ?? 0) / 100) / $rev['paid_count'], 2) . ' ' . ($rev['currency'] ?? 'USD') : '—', 'fa-chart-pie', '#8b5cf6'],
            ] as [$label, $val, $icon, $color])
                <div class="card-premium p-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: {{ $color }}22;">
                            <i class="fas {{ $icon }} text-sm" style="color: {{ $color }};"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">{{ $label }}</div>
                            <div class="text-base font-bold leading-none mt-1 truncate" style="color: var(--text-primary);">{{ $val }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- 30-day trend --}}
            <div class="card-premium p-6 lg:col-span-2">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Submissions — last 30 days</h3>
                <p class="text-[11px] mb-4" style="color: var(--text-faint);">{{ number_format($a['total']) }} completed submissions · {{ number_format($a['views'] ?? 0) }} views · {{ $a['conversion'] ?? 0 }}% conversion</p>
                @php($max = max(1, collect($a['trend'])->max('count')))
                <div class="flex items-end gap-1 h-32">
                    @foreach($a['trend'] as $pt)
                        <div class="flex-1 flex flex-col justify-end items-center group" title="{{ $pt['label'] }}: {{ $pt['count'] }}">
                            <div class="w-full rounded-t" style="height: {{ max(2, round($pt['count'] / $max * 100)) }}%; background: linear-gradient(180deg, #8b5cf6, #ec4899); min-height: 2px;"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[9px] mt-2" style="color: var(--text-faint);">
                    <span>{{ $a['trend'][0]['label'] ?? '' }}</span>
                    <span>{{ $a['trend'][count($a['trend']) - 1]['label'] ?? '' }}</span>
                </div>
            </div>

            {{-- Devices --}}
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">Devices</h3>
                @if(empty($a['devices']))
                    <p class="text-xs" style="color: var(--text-faint);">No data yet.</p>
                @else
                    @php($devTotal = array_sum($a['devices']))
                    <div class="space-y-3">
                        @foreach($a['devices'] as $dev => $count)
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span style="color: var(--text-secondary);">{{ $dev }}</span>
                                    <span style="color: var(--text-faint);">{{ $devTotal > 0 ? round($count / $devTotal * 100) : 0 }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--bg-glass-input);">
                                    <div class="h-full rounded-full" style="width: {{ $devTotal > 0 ? round($count / $devTotal * 100) : 0 }}%; background: #6366f1;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Field completion --}}
            <div class="card-premium p-6 lg:col-span-2">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Field completion</h3>
                <p class="text-[11px] mb-4" style="color: var(--text-faint);">How often each field is filled in — low rates hint at drop-off.</p>
                @if(empty($a['fields']))
                    <p class="text-xs" style="color: var(--text-faint);">No fields to report on yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach($a['fields'] as $f)
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="truncate pr-2" style="color: var(--text-secondary);">{{ $f['label'] }}</span>
                                    <span style="color: var(--text-faint);">{{ $f['rate'] }}% · {{ number_format($f['filled']) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--bg-glass-input);">
                                    <div class="h-full rounded-full" style="width: {{ $f['rate'] }}%; background: linear-gradient(90deg, #10b981, #22c55e);"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Geo --}}
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">Top countries</h3>
                @if(empty($a['geo']))
                    <p class="text-xs" style="color: var(--text-faint);">No location data captured yet.</p>
                @else
                    @php($geoTotal = array_sum($a['geo']))
                    <div class="space-y-3">
                        @foreach($a['geo'] as $country => $count)
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span style="color: var(--text-secondary);">{{ $country }}</span>
                                    <span style="color: var(--text-faint);">{{ number_format($count) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--bg-glass-input);">
                                    <div class="h-full rounded-full" style="width: {{ $geoTotal > 0 ? round($count / $geoTotal * 100) : 0 }}%; background: #ec4899;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @elseif(!$advancedAnalytics)
        <div class="card-premium p-5 mb-6 flex items-center justify-between gap-4" style="background: rgba(139,92,246,0.06); border: 1px solid rgba(139,92,246,0.2);">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(139,92,246,0.15);">
                    <i class="fas fa-chart-line text-violet-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Unlock advanced form analytics</h3>
                    <p class="text-[12px] mt-0.5" style="color: var(--text-muted);">Submission trends, field drop-off, device &amp; geo breakdowns and paid-form revenue — on Pro and above.</p>
                </div>
            </div>
            <a href="{{ route('user.upgrade') }}" class="btn-primary inline-flex items-center gap-2 text-xs flex-shrink-0">
                <i class="fas fa-arrow-up"></i> Upgrade
            </a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="card-premium p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Recent Submissions</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Latest 5 submissions to this form.</p>
                    </div>
                    <a href="{{ route('user.forms.submissions', $form) }}" class="text-xs font-semibold" style="color: var(--accent-light);">View all →</a>
                </div>
                @if($recent->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-3xl mb-3" style="color: var(--text-faint);"></i>
                        <p class="text-sm" style="color: var(--text-muted);">No submissions yet. Share your form to start collecting.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($recent as $s)
                            <a href="{{ route('user.forms.submissions.show', [$form, $s]) }}"
                               class="flex items-center gap-3 p-3 rounded-lg hover:translate-x-1 transition-all"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: linear-gradient(135deg, #8b5cf6, #ec4899); color: white;">
                                    {{ strtoupper(substr($s->data['name'] ?? $s->data['email'] ?? '#', 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                                        {{ $s->data['name'] ?? $s->data['email'] ?? '#' . $s->id }}
                                    </div>
                                    <div class="text-[11px] truncate" style="color: var(--text-faint);">
                                        {{ $s->created_at->diffForHumans() }} · {{ $s->ip ?? 'unknown ip' }}
                                    </div>
                                </div>
                                @unless($s->is_read)
                                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="card-premium p-5">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Quick Actions</h4>
                <div class="space-y-2">
                    <a href="{{ route('user.forms.embed', $form) }}" class="flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                        <i class="fas fa-code text-xs text-cyan-400"></i> Get embed code
                    </a>
                    <a href="{{ route('user.forms.submissions.export', $form) }}" class="flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                        <i class="fas fa-file-csv text-xs text-emerald-400"></i> Export CSV
                    </a>
                    <a href="{{ route('user.forms.notifications', $form) }}" class="flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                        <i class="fas fa-bell text-xs text-amber-400"></i> Configure notifications
                    </a>
                    @if($__can('inbox.edit'))
                    <form method="POST" action="{{ route('user.forms.toggle-active', $form) }}">@csrf
                        <button class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                            <i class="fas fa-power-off text-xs {{ $form->is_active ? 'text-amber-400' : 'text-emerald-400' }}"></i>
                            {{ $form->is_active ? 'Disable form' : 'Enable form' }}
                        </button>
                    </form>
                    @else
                    <button type="button" disabled class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow editing forms — ask a workspace admin">
                        <i class="fas fa-lock text-xs"></i> {{ $form->is_active ? 'Disable form' : 'Enable form' }}
                    </button>
                    @endif
                    @if($__can('inbox.delete'))
                    <form method="POST" action="{{ route('user.forms.destroy', $form) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this form?', message: 'All of its submissions will be permanently deleted. This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm hover:translate-x-1 transition-all" style="background: rgba(239,68,68,0.08); color: #f87171;">
                            <i class="fas fa-trash text-xs"></i> Delete form
                        </button>
                    </form>
                    @else
                    <button type="button" disabled class="w-full text-left flex items-center gap-2.5 p-2.5 rounded-lg text-sm cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow deleting forms — ask a workspace admin">
                        <i class="fas fa-lock text-xs"></i> Delete form
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
