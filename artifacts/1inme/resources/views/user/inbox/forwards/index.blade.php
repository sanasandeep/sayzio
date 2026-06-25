@extends('user.layouts.app')
@section('title', 'Inbox Forwarding')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Inbox forwarding',
        'subtitle' => 'Send new inbox messages to an email address or webhook',
        'icon'     => 'fa-share-from-square',
        'chips'    => [
            ['icon' => 'fa-inbox text-blue-400', 'text' => count($destinations) . ' rule(s)'],
        ],
        'actions'  => [
            ['label' => 'Back to inbox', 'url' => route('user.inbox.index'), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

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

    {{-- Existing destinations --}}
    <div class="card-premium p-5 mb-6">
        <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Forwarding rules</h2>

        @if($destinations->isEmpty())
            <p class="text-sm" style="color: var(--text-muted);">No rules yet. Add one below to start receiving inbox messages by email or webhook.</p>
        @else
            <div class="space-y-3">
                @foreach($destinations as $d)
                    <details class="rounded-xl p-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <summary class="cursor-pointer flex items-center gap-3 text-sm" style="color: var(--text-primary);">
                            <i class="fas {{ $d->type === 'email' ? 'fa-envelope text-blue-400' : 'fa-bolt text-amber-400' }}"></i>
                            <span class="font-medium">{{ $d->label }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(61,107,255,0.15); color:#bccfff;">{{ $d->type }}</span>
                            <span class="text-xs truncate" style="color: var(--text-muted);">→ {{ $d->target }}</span>
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full"
                                  style="{{ $d->is_active ? 'background:rgba(16,185,129,0.15);color:#86efac;' : 'background:rgba(148,163,184,0.15);color:#cbd5e1;' }}">
                                {{ $d->is_active ? 'Active' : 'Paused' }}
                            </span>
                        </summary>

                        <form method="POST" action="{{ route('user.inbox.forwards.update', $d) }}" class="mt-4 grid md:grid-cols-2 gap-3">
                            @csrf @method('PUT')
                            @include('user.inbox.forwards._fields', ['dest' => $d, 'sourceLabels' => $sourceLabels])

                            <div class="md:col-span-2 flex flex-wrap gap-2 mt-1">
                                <button type="submit" class="px-4 py-2 text-xs rounded-lg font-semibold" style="background: linear-gradient(135deg,#5c83ff,#2342c7); color: white;">
                                    Save changes
                                </button>
                                <button type="submit" form="toggle-{{ $d->id }}" class="px-4 py-2 text-xs rounded-lg font-semibold" style="background: rgba(148,163,184,0.15); color: var(--text-secondary);">
                                    {{ $d->is_active ? 'Pause' : 'Enable' }}
                                </button>
                                <button type="submit" form="test-{{ $d->id }}" class="px-4 py-2 text-xs rounded-lg font-semibold"
                                        @if(!$d->is_active) disabled title="Enable this rule to send a test" @endif
                                        style="background: rgba(56,189,248,0.15); color:#7dd3fc; {{ $d->is_active ? '' : 'opacity:0.5;cursor:not-allowed;' }}">
                                    <i class="fas fa-paper-plane mr-1"></i>Send test
                                </button>
                                <button type="submit" form="del-{{ $d->id }}" class="px-4 py-2 text-xs rounded-lg font-semibold ml-auto"
                                        onclick="return window.themedConfirmAction(this, {title: 'Delete this forwarding rule?', message: 'This stops future emails from being forwarded to the configured destinations.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})"
                                        style="background: rgba(239,68,68,0.15); color: #fca5a5;">
                                    Delete
                                </button>
                            </div>
                        </form>

                        <form id="toggle-{{ $d->id }}" method="POST" action="{{ route('user.inbox.forwards.toggle', $d) }}">@csrf</form>
                        <form id="test-{{ $d->id }}" method="POST" action="{{ route('user.inbox.forwards.test', $d) }}">@csrf</form>
                        <form id="del-{{ $d->id }}" method="POST" action="{{ route('user.inbox.forwards.destroy', $d) }}">@csrf @method('DELETE')</form>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add new --}}
    <div class="card-premium p-5 mb-6">
        <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Add a forwarding rule</h2>
        <form method="POST" action="{{ route('user.inbox.forwards.store') }}" class="grid md:grid-cols-2 gap-3">
            @csrf
            @include('user.inbox.forwards._fields', ['dest' => null, 'sourceLabels' => $sourceLabels])
            <div class="md:col-span-2">
                <button type="submit" class="px-5 py-2.5 text-sm rounded-lg font-semibold" style="background: linear-gradient(135deg,#5c83ff,#2342c7); color: white;">
                    <i class="fas fa-plus mr-1.5"></i>Add rule
                </button>
            </div>
        </form>
    </div>

    {{-- Delivery log --}}
    <div class="card-premium p-5">
        <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Recent deliveries</h2>
        @if($deliveries->isEmpty())
            <p class="text-sm" style="color: var(--text-muted);">No deliveries yet. They will appear here as inbox messages arrive.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr style="color: var(--text-faint);">
                            <th class="text-left py-2 pr-3">When</th>
                            <th class="text-left py-2 pr-3">Rule</th>
                            <th class="text-left py-2 pr-3">Source</th>
                            <th class="text-left py-2 pr-3">Status</th>
                            <th class="text-left py-2 pr-3">Attempts</th>
                            <th class="text-left py-2 pr-3">Detail</th>
                            <th class="text-right py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $del)
                            @php
                                $color = match ($del->status) {
                                    'success' => 'background:rgba(16,185,129,0.15);color:#86efac;',
                                    'failed'  => 'background:rgba(245,158,11,0.15);color:#fcd34d;',
                                    'dead'    => 'background:rgba(239,68,68,0.15);color:#fca5a5;',
                                    default   => 'background:rgba(148,163,184,0.15);color:#cbd5e1;',
                                };
                            @endphp
                            <tr style="border-top: 1px solid var(--border-glass); color: var(--text-secondary);">
                                <td class="py-2 pr-3 whitespace-nowrap">{{ $del->created_at?->diffForHumans() }}</td>
                                <td class="py-2 pr-3">{{ $del->destination?->label ?? '—' }}</td>
                                <td class="py-2 pr-3">
                                    {{ $sourceLabels[$del->source_type] ?? $del->source_type }}
                                    @if($del->is_test)
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide" style="background:rgba(56,189,248,0.15);color:#7dd3fc;">test</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3"><span class="px-2 py-0.5 rounded-full" style="{{ $color }}">{{ $del->status }}</span></td>
                                <td class="py-2 pr-3">{{ $del->attempts }}</td>
                                <td class="py-2 pr-3">
                                    @if($del->status === 'success')
                                        Delivered {{ $del->delivered_at?->diffForHumans() }}
                                    @elseif($del->next_retry_at && $del->status === 'failed')
                                        Retry {{ $del->next_retry_at->diffForHumans() }}
                                        @if($del->last_error)<div style="color:#fca5a5;">{{ \Illuminate\Support\Str::limit($del->last_error, 90) }}</div>@endif
                                    @elseif($del->last_error)
                                        <span style="color:#fca5a5;">{{ \Illuminate\Support\Str::limit($del->last_error, 90) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    @if(in_array($del->status, ['failed', 'dead'], true))
                                        <form method="POST" action="{{ route('user.inbox.forwards.deliveries.retry', $del) }}" class="inline">
                                            @csrf
                                            <button class="px-2 py-1 rounded-md text-[11px]" style="background: rgba(61,107,255,0.15); color:#bccfff;">
                                                <i class="fas fa-rotate-right mr-1"></i>Retry now
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
