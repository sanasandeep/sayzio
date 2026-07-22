@extends('admin.layouts.app')
@section('title', 'Credit Reviews')
@section('page-title', 'Credit Reviews')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">{{ session('error') }}</div>
    @endif

    <div class="glass rounded-2xl border border-white/10 p-5">
        <h2 class="text-sm font-semibold text-white ak-strong">Upgrade credit reviews</h2>
        <p class="text-xs text-white/50 mt-1 ak-muted">
            When a user upgrades mid-cycle they pay the full new-plan price; leftover days and add-on time on the old plan
            are not auto-credited. Approve to extend the new plan's expiry (and shared add-on period) by a number of days,
            or dismiss to forfeit. Every decision is recorded in the activity log.
        </p>
    </div>

    {{-- Status tabs --}}
    <div class="flex flex-wrap gap-2">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'dismissed' => 'Dismissed'] as $key => $label)
            <a href="{{ route('admin.credit-reviews.index', ['status' => $key]) }}"
               class="px-4 py-2 rounded-xl text-sm font-medium border {{ $status === $key ? 'bg-amber-600 border-amber-500 text-white' : 'border-white/10 text-white/60 hover:text-white ak-muted' }}">
                {{ $label }}
                <span class="ml-1 text-xs opacity-70">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @forelse($reviews as $review)
        <div class="glass rounded-2xl border border-white/10 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-white ak-strong">
                        {{ $review->user?->name ?? 'Unknown user' }}
                        <span class="text-white/40 font-normal ak-note">&lt;{{ $review->user?->email }}&gt;</span>
                    </div>
                    <div class="text-xs text-white/50 mt-1 ak-muted">
                        {{ $review->oldPlan?->name ?? 'Old plan' }}
                        <i class="fas fa-arrow-right mx-1"></i>
                        {{ $review->newPlan?->name ?? 'New plan' }}
                        · upgraded {{ optional($review->created_at)->diffForHumans() }}
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-white/40 ak-note">Leftover days</div>
                            <div class="text-lg font-bold text-white ak-strong">{{ $review->leftover_days }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-white/40 ak-note">Add-on days</div>
                            <div class="text-lg font-bold text-white ak-strong">{{ $review->leftover_addon_days }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-white/40 ak-note">Currency</div>
                            <div class="text-lg font-bold text-white ak-strong">{{ $review->currency ?? '—' }}</div>
                        </div>
                    </div>

                    @if(!empty($review->addons_snapshot))
                        <div class="mt-3">
                            <div class="text-[11px] uppercase tracking-wider text-white/40 mb-1 ak-note">Add-ons at upgrade</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($review->addons_snapshot as $addon)
                                    @php $addonQty = is_array($addon) ? (int) ($addon['qty'] ?? $addon['quantity'] ?? 1) : 1; @endphp
                                    <span class="px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white/70 ak-strong">
                                        {{ is_array($addon) ? ($addon['name'] ?? $addon['slug'] ?? 'Add-on') : $addon }}
                                        @if($addonQty > 1)
                                            ×{{ $addonQty }}
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($review->status !== 'pending')
                        <div class="text-xs text-white/50 mt-3 ak-muted">
                            {{ ucfirst($review->status) }}
                            @if($review->status === 'approved')
                                · granted <span class="text-emerald-300 font-semibold ak-green">{{ $review->granted_days }}</span> day(s)
                            @endif
                            by {{ $review->actionedBy?->name ?? 'admin' }}
                            {{ optional($review->actioned_at)->diffForHumans() }}
                            @if($review->note)
                                <div class="mt-1 text-white/60 italic ak-muted">“{{ $review->note }}”</div>
                            @endif
                        </div>
                    @endif
                </div>

                @if($review->status === 'pending')
                    <div class="flex flex-col gap-3 w-full sm:w-auto sm:min-w-[260px]">
                        <form method="POST" action="{{ route('admin.credit-reviews.approve', $review) }}" class="space-y-2">
                            @csrf
                            <div>
                                <label class="text-[11px] uppercase tracking-wider text-white/40 ak-note">Days to grant</label>
                                <input type="number" name="granted_days" min="1" max="3650"
                                       value="{{ $review->leftover_days }}"
                                       class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white ak-strong ak-input">
                            </div>
                            <div>
                                <input type="text" name="note" placeholder="Note (optional)"
                                       class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white ak-strong ak-input">
                            </div>
                            <button type="submit"
                                    class="w-full px-4 py-2 rounded-xl text-sm font-medium bg-emerald-600 hover:bg-emerald-700 text-white">
                                <i class="fas fa-check mr-1"></i> Approve credit
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.credit-reviews.dismiss', $review) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full px-4 py-2 rounded-xl text-sm font-medium bg-white/5 hover:bg-white/10 border border-white/10 text-white/70 ak-strong">
                                <i class="fas fa-xmark mr-1"></i> Dismiss
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="glass rounded-2xl border border-white/10 p-8 text-center text-white/50 text-sm ak-muted">
            No {{ $status }} credit reviews.
        </div>
    @endforelse

    <div>{{ $reviews->links() }}</div>
</div>
@endsection
