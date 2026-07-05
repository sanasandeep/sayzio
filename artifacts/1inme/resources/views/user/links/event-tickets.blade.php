@extends('user.layouts.app')

@section('title', 'Ticketing — ' . $link->title)

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Ticketing',
        'subtitle' => $link->title,
        'icon' => 'fa-ticket-alt',
        'back' => route('user.links.show', $link),
        'chips' => [
            ['icon' => 'fa-coins text-emerald-400', 'text' => '$' . number_format($totals['gross_cents'] / 100, 2) . ' gross'],
            ['icon' => 'fa-users text-blue-400', 'text' => $totals['sold'] . ' sold'],
            ['icon' => 'fa-door-open text-primary-400', 'text' => $totals['checked_in'] . ' checked in'],
        ],
        'actions' => [
            ['label' => 'Scan tickets', 'url' => route('user.links.ics.checkin', $link), 'icon' => 'fa-qrcode', 'class' => 'btn-primary'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    <div class="card-premium p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-compass mr-1.5"></i> Public directory
            </div>
        </div>
        <form method="POST" action="{{ route('user.links.ics.update', $link) }}" class="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
            @csrf
            @method('PUT')
            <label class="flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
                <input type="hidden" name="hide_from_directory" value="0">
                <input type="checkbox" name="hide_from_directory" value="1" @checked(!empty($settings['hide_from_directory']))>
                Hide from /events directory
            </label>
            <label class="flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
                <input type="hidden" name="is_online" value="0">
                <input type="checkbox" name="is_online" value="1" @checked(!empty($settings['is_online']))>
                <i class="fas fa-video text-xs" style="color: var(--text-muted);"></i> Online event
            </label>
            @php
                $currentCategory = trim((string) ($settings['event_category'] ?? ''));
                $isKnownCategory = \App\Modules\User\Support\EventCategories::isKnown($currentCategory);
                $categorySelectValue = $currentCategory === ''
                    ? ''
                    : ($isKnownCategory ? $currentCategory : \App\Modules\User\Support\EventCategories::OTHER);
            @endphp
            <div x-data="{ cat: @js($categorySelectValue) }" class="flex flex-col sm:flex-row gap-2 flex-1">
                <select name="event_category" x-model="cat"
                        class="px-3 py-2 rounded-lg text-sm flex-1" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
                    <option value="">No category</option>
                    @foreach(\App\Modules\User\Support\EventCategories::selectOptions() as $slug => $label)
                        <option value="{{ $slug }}">{{ $label }}</option>
                    @endforeach
                    <option value="{{ \App\Modules\User\Support\EventCategories::OTHER }}">Other…</option>
                </select>
                <input type="text" name="event_category_other" placeholder="Custom category" maxlength="100"
                       value="{{ $isKnownCategory ? '' : $currentCategory }}"
                       x-show="cat === '{{ \App\Modules\User\Support\EventCategories::OTHER }}'" x-cloak
                       class="px-3 py-2 rounded-lg text-sm flex-1" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            </div>
            <button type="submit" class="btn-secondary px-4 py-2 text-sm rounded-lg">Save</button>
        </form>
    </div>

    <div class="card-premium p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div class="text-sm font-semibold" style="color: var(--text-primary);"><i class="fas fa-layer-group mr-1.5"></i> Ticket tiers</div>
        </div>

        <div class="space-y-3 mb-5">
            @foreach($tiers as $tier)
            <div class="flex flex-wrap items-center gap-3 p-3 rounded-lg" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                <div class="flex-1 min-w-[160px]">
                    <div class="font-semibold text-sm" style="color: var(--text-primary);">{{ $tier->name }}</div>
                    <div class="text-xs" style="color: var(--text-muted);">{{ $tier->priceLabel() }} &middot; {{ $tier->sold }} sold @if($tier->capacity) / {{ $tier->capacity }} @endif</div>
                </div>
                <form method="POST" action="{{ route('user.links.ics.tiers.update', [$link, $tier]) }}" class="flex flex-wrap gap-2 items-center">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $tier->name }}" class="px-2 py-1 rounded text-xs w-32" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
                    <input type="number" step="0.01" name="price" value="{{ $tier->price_cents / 100 }}" class="px-2 py-1 rounded text-xs w-20" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
                    <input type="number" name="capacity" value="{{ $tier->capacity }}" placeholder="cap" class="px-2 py-1 rounded text-xs w-16" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
                    <label class="text-xs flex items-center gap-1" style="color: var(--text-secondary);">
                        <input type="checkbox" name="is_active" value="1" @checked($tier->is_active)> Active
                    </label>
                    <button type="submit" class="btn-secondary px-3 py-1 text-xs rounded-lg">Save</button>
                </form>
                <form method="POST" action="{{ route('user.links.ics.tiers.destroy', [$link, $tier]) }}"
                      onsubmit="return confirm('Delete this tier?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-1 text-xs rounded-lg" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2);">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('user.links.ics.tiers.store', $link) }}" class="flex flex-wrap gap-2 items-end p-3 rounded-lg" style="background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.15);">
            @csrf
            <div>
                <label class="text-xs block mb-1" style="color: var(--text-muted);">Name</label>
                <input type="text" name="name" required class="px-2 py-1.5 rounded text-sm w-40" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            </div>
            <div>
                <label class="text-xs block mb-1" style="color: var(--text-muted);">Price (USD)</label>
                <input type="number" step="0.01" min="0" name="price" required value="0" class="px-2 py-1.5 rounded text-sm w-24" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            </div>
            <div>
                <label class="text-xs block mb-1" style="color: var(--text-muted);">Capacity</label>
                <input type="number" min="1" name="capacity" class="px-2 py-1.5 rounded text-sm w-24" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            </div>
            <button type="submit" class="btn-primary px-4 py-2 text-sm rounded-lg"><i class="fas fa-plus mr-1"></i> Add tier</button>
        </form>
    </div>

    <div class="card-premium p-5">
        <div class="text-sm font-semibold mb-4" style="color: var(--text-primary);"><i class="fas fa-receipt mr-1.5"></i> Recent tickets</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="color: var(--text-muted);" class="text-left">
                        <th class="pb-2">Attendee</th>
                        <th class="pb-2">Tier</th>
                        <th class="pb-2">Qty</th>
                        <th class="pb-2">Status</th>
                        <th class="pb-2">Purchased</th>
                        <th class="pb-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tickets as $t)
                    <tr style="border-top: 1px solid rgba(255,255,255,0.06);">
                        <td class="py-2" style="color: var(--text-primary);">{{ $t->attendee_name }}<div class="text-xs" style="color: var(--text-muted);">{{ $t->attendee_email }}</div></td>
                        <td class="py-2" style="color: var(--text-secondary);">{{ $t->tier?->name ?? '—' }}</td>
                        <td class="py-2" style="color: var(--text-secondary);">{{ $t->quantity }}</td>
                        <td class="py-2"><span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(255,255,255,0.06); color: var(--text-secondary);">{{ $t->status }}</span></td>
                        <td class="py-2" style="color: var(--text-muted);">{{ $t->created_at->format('M j, Y g:i A') }}</td>
                        <td class="py-2 text-right">
                            @if(in_array($t->status, ['valid', 'checked_in'], true))
                            <form method="POST" action="{{ route('user.links.ics.tickets.refund', [$link, $t]) }}"
                                  onsubmit="return confirm('Refund this ticket for ' + @js($t->attendee_name) + '? This frees the seat and notifies the attendee by email. This cannot be undone.');">
                                @csrf
                                <button type="submit" class="text-xs px-3 py-1 rounded-lg" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2);">
                                    <i class="fas fa-rotate-left mr-1"></i> Refund
                                </button>
                            </form>
                            @else
                            <span class="text-xs" style="color: var(--text-muted);">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center" style="color: var(--text-muted);">No tickets sold yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $tickets->links() }}</div>
    </div>
</div>
@endsection
