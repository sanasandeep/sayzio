@extends('user.layouts.settings')
@section('title', 'Webhooks')

@section('content')
<div class="max-w-5xl mx-auto">

    @if(session('success'))
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5;">
            @foreach($errors->all() as $err)<div><i class="fas fa-exclamation-circle mr-1.5"></i>{{ $err }}</div>@endforeach
        </div>
    @endif

    @if(!$hasFeature)
        {{-- Upgrade prompt --}}
        <div class="card-premium p-8 text-center">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                 style="background: rgba(61,107,255,0.15);">
                <i class="fas fa-bolt text-2xl" style="color: #3d6bff;"></i>
            </div>
            <h2 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Outbound Webhook Triggers</h2>
            <p class="text-sm mb-6" style="color: var(--text-muted);">
                Get real-time HTTP notifications whenever a link is created, expires, or hits a click milestone.
                Connect Sayzio events to Zapier, Make, your own pipeline, or any webhook-ready tool.
            </p>
            <a href="{{ route('user.upgrade') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold"
               style="background: linear-gradient(135deg,#5c83ff,#2342c7); color: white;">
                <i class="fas fa-arrow-up"></i> Upgrade to unlock Webhook Triggers
            </a>
        </div>
    @else

    {{-- Existing destinations --}}
    <div class="card-premium p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold" style="color: var(--text-primary);">Webhook destinations</h2>
                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Each destination receives the events you select. Leave all sources unchecked to receive every event type.
                </p>
            </div>
            <span class="text-xs px-2 py-1 rounded-full" style="background: rgba(61,107,255,0.15); color:#bccfff;">
                {{ $destinations->count() }} destination(s)
            </span>
        </div>

        @if($destinations->isEmpty())
            <p class="text-sm py-4 text-center" style="color: var(--text-muted);">No destinations yet. Add one below to start receiving link events.</p>
        @else
            <div class="space-y-3">
                @foreach($destinations as $d)
                    @php $selSources = (array)($d->sources ?? []); @endphp
                    <details class="rounded-xl p-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <summary class="cursor-pointer flex items-center gap-3 text-sm" style="color: var(--text-primary);">
                            <i class="fas {{ $d->type === 'email' ? 'fa-envelope text-blue-400' : 'fa-bolt text-amber-400' }}"></i>
                            <span class="font-medium">{{ $d->label }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(61,107,255,0.15); color:#bccfff;">{{ $d->type }}</span>
                            <span class="text-xs truncate" style="color: var(--text-muted);">→ {{ $d->target }}</span>
                            @if($d->clickMilestoneThresholds())
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(234,179,8,0.15); color:#fde047;">
                                    milestones: {{ implode(', ', $d->clickMilestoneThresholds()) }}
                                </span>
                            @endif
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full"
                                  style="{{ $d->is_active ? 'background:rgba(16,185,129,0.15);color:#86efac;' : 'background:rgba(148,163,184,0.15);color:#cbd5e1;' }}">
                                {{ $d->is_active ? 'Active' : 'Paused' }}
                            </span>
                        </summary>

                        <form method="POST" action="{{ route('user.inbox.forwards.update', $d) }}" class="mt-4 grid md:grid-cols-2 gap-3">
                            @csrf @method('PUT')
                            @include('user.inbox.forwards._fields', ['dest' => $d, 'sourceLabels' => $sourceLabels])
                            @include('user.settings._webhook_milestone_field', ['dest' => $d])

                            <div class="md:col-span-2 flex flex-wrap gap-2 mt-1">
                                <button type="submit" class="px-4 py-2 text-xs rounded-lg font-semibold" style="background: linear-gradient(135deg,#5c83ff,#2342c7); color: white;">
                                    Save changes
                                </button>
                                <button type="submit" form="wtoggle-{{ $d->id }}" class="px-4 py-2 text-xs rounded-lg font-semibold" style="background: rgba(148,163,184,0.15); color: var(--text-secondary);">
                                    {{ $d->is_active ? 'Pause' : 'Enable' }}
                                </button>
                                <button type="submit" form="wtest-{{ $d->id }}" class="px-4 py-2 text-xs rounded-lg font-semibold"
                                        @if(!$d->is_active) disabled title="Enable this destination to send a test" @endif
                                        style="background: rgba(56,189,248,0.15); color:#7dd3fc; {{ $d->is_active ? '' : 'opacity:0.5;cursor:not-allowed;' }}">
                                    <i class="fas fa-paper-plane mr-1"></i>Send test
                                </button>
                                <button type="submit" form="wdel-{{ $d->id }}" class="px-4 py-2 text-xs rounded-lg font-semibold ml-auto"
                                        onclick="return window.themedConfirmAction(this, {title: 'Delete this destination?', message: 'Future link events will no longer be sent to this destination.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})"
                                        style="background: rgba(239,68,68,0.15); color: #fca5a5;">
                                    Delete
                                </button>
                            </div>
                        </form>

                        <form id="wtoggle-{{ $d->id }}" method="POST" action="{{ route('user.inbox.forwards.toggle', $d) }}">@csrf</form>
                        <form id="wtest-{{ $d->id }}" method="POST" action="{{ route('user.inbox.forwards.test', $d) }}">@csrf</form>
                        <form id="wdel-{{ $d->id }}" method="POST" action="{{ route('user.inbox.forwards.destroy', $d) }}">@csrf @method('DELETE')</form>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add new destination --}}
    <div class="card-premium p-5 mb-6">
        <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Add destination</h2>
        <form method="POST" action="{{ route('user.inbox.forwards.store') }}" class="grid md:grid-cols-2 gap-3">
            @csrf
            @include('user.inbox.forwards._fields', ['dest' => null, 'sourceLabels' => $sourceLabels])
            @include('user.settings._webhook_milestone_field', ['dest' => null])

            <div class="md:col-span-2">
                <button type="submit" class="px-5 py-2.5 text-sm rounded-xl font-semibold"
                        style="background: linear-gradient(135deg,#5c83ff,#2342c7); color: white;">
                    <i class="fas fa-plus mr-1.5"></i>Add destination
                </button>
            </div>
        </form>
    </div>

    {{-- Recent deliveries log --}}
    @if($deliveries->isNotEmpty())
    <div class="card-premium p-5">
        <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Recent deliveries</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-xs" style="color: var(--text-secondary);">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-glass);">
                        <th class="text-left py-2 pr-3 font-semibold" style="color: var(--text-faint);">Destination</th>
                        <th class="text-left py-2 pr-3 font-semibold" style="color: var(--text-faint);">Event</th>
                        <th class="text-left py-2 pr-3 font-semibold" style="color: var(--text-faint);">Status</th>
                        <th class="text-left py-2 pr-3 font-semibold" style="color: var(--text-faint);">HTTP</th>
                        <th class="text-left py-2 font-semibold" style="color: var(--text-faint);">When</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deliveries as $del)
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td class="py-2 pr-3">{{ $del->destination?->label ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $sourceLabels[$del->source_type] ?? $del->source_type }}</td>
                            <td class="py-2 pr-3">
                                @php
                                    $statusColor = match($del->status) {
                                        'success' => 'color:#86efac;',
                                        'pending' => 'color:#fde047;',
                                        'dead'    => 'color:#fca5a5;',
                                        default   => 'color:#f59e0b;',
                                    };
                                @endphp
                                <span style="{{ $statusColor }}">{{ $del->status }}</span>
                                @if($del->is_test) <span style="color:var(--text-faint);">(test)</span> @endif
                            </td>
                            <td class="py-2 pr-3">{{ $del->last_response_code ?? '—' }}</td>
                            <td class="py-2 whitespace-nowrap">{{ optional($del->created_at)->diffForHumans() }}</td>
                            <td class="py-2">
                                @if(in_array($del->status, ['failed', 'dead'], true))
                                    <form method="POST" action="{{ route('user.inbox.forwards.deliveries.retry', $del) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs px-2 py-0.5 rounded" style="background: rgba(56,189,248,0.15); color:#7dd3fc;">
                                            Retry
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @if($del->last_error)
                            <tr>
                                <td colspan="6" class="pb-2 text-xs" style="color: #fca5a5;">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ $del->last_error }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @endif {{-- hasFeature --}}
</div>
@endsection
