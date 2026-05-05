@extends('user.layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-4 md:p-6">
    @include('user.monetization._nav')

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ $errors->first() }}</div>
    @endif

    {{-- Existing codes --}}
    <div class="rounded-xl border mb-6" style="border-color: var(--border-color); background: var(--bg-card);">
        <div class="p-4 border-b" style="border-color: var(--border-color);">
            <h2 class="font-semibold" style="color: var(--text-primary);">Active codes</h2>
            <p class="text-xs mt-1" style="color: var(--text-faint);">Use these in your bio, posts, or DMs to drive conversions.</p>
        </div>
        @if($promos->count())
            <div class="divide-y" style="border-color: var(--border-color);">
                @foreach($promos as $promo)
                    <div class="flex items-center justify-between p-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-sm px-2 py-0.5 rounded"
                                      style="background: rgba(139,92,246,0.12); color: #8b5cf6;">{{ $promo->code }}</span>
                                <span class="text-xs font-semibold" style="color: var(--text-secondary);">{{ $promo->describe() }}</span>
                                @if(!$promo->is_active)
                                    <span class="text-[11px] px-2 py-0.5 rounded-full" style="background: rgba(239,68,68,0.12); color: #ef4444;">Disabled</span>
                                @endif
                                @if($promo->expires_at && $promo->expires_at->isPast())
                                    <span class="text-[11px] px-2 py-0.5 rounded-full" style="background: rgba(245,158,11,0.12); color: #f59e0b;">Expired</span>
                                @endif
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-faint);">
                                {{ $promo->label ?: 'No label' }}
                                · {{ $promo->redemptions_count }}{{ $promo->max_redemptions ? '/'.$promo->max_redemptions : '' }} used
                                @if($promo->expires_at)
                                    · expires {{ $promo->expires_at->format('M j, Y') }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('user.monetization.promos.toggle', $promo) }}">@csrf
                                <button class="text-xs px-2 py-1 rounded border" style="border-color: var(--border-color); color: var(--text-secondary);">
                                    {{ $promo->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('user.monetization.promos.destroy', $promo) }}" onsubmit="return confirm('Delete this code?');">
                                @csrf @method('DELETE')
                                <button class="text-xs px-2 py-1 rounded border" style="border-color: var(--border-color); color: #ef4444;">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center text-sm" style="color: var(--text-faint);">
                No promo codes yet. Add one below to share with your audience.
            </div>
        @endif
    </div>

    {{-- Add code --}}
    <div class="rounded-xl border p-5" style="border-color: var(--border-color); background: var(--bg-card);">
        <h2 class="font-semibold mb-3" style="color: var(--text-primary);"><i class="fas fa-plus mr-1"></i>Add a promo code</h2>
        <form method="POST" action="{{ route('user.monetization.promos.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <input type="text" name="code" placeholder="WELCOME50" required maxlength="40"
                   class="px-3 py-2 rounded-lg border bg-transparent uppercase font-mono"
                   style="border-color: var(--border-color); color: var(--text-primary);">
            <input type="text" name="label" placeholder="Label (internal)" maxlength="120"
                   class="px-3 py-2 rounded-lg border bg-transparent md:col-span-2"
                   style="border-color: var(--border-color); color: var(--text-primary);">

            <select name="kind" class="px-3 py-2 rounded-lg border bg-transparent"
                    style="border-color: var(--border-color); color: var(--text-primary);">
                <option value="percent">% off recurring</option>
                <option value="amount">$ off (cents) recurring</option>
                <option value="months_free">Months free</option>
                <option value="founder">Founder discount (cents off)</option>
                <option value="lifetime">Lifetime free</option>
            </select>
            <input type="number" name="value" min="0" max="100000" placeholder="Value (e.g. 50 for 50%)" required
                   class="px-3 py-2 rounded-lg border bg-transparent"
                   style="border-color: var(--border-color); color: var(--text-primary);">
            <input type="number" name="max_redemptions" min="1" max="100000" placeholder="Max redemptions (optional)"
                   class="px-3 py-2 rounded-lg border bg-transparent"
                   style="border-color: var(--border-color); color: var(--text-primary);">

            <input type="datetime-local" name="expires_at" placeholder="Expires" 
                   class="px-3 py-2 rounded-lg border bg-transparent"
                   style="border-color: var(--border-color); color: var(--text-primary);">

            @if($tiers->count())
                <div class="md:col-span-2">
                    <label class="text-xs" style="color: var(--text-faint);">Restrict to tiers (optional, all paid tiers if blank)</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($tiers as $t)
                            <label class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer"
                                   style="border-color: var(--border-color); color: var(--text-secondary);">
                                <input type="checkbox" name="tier_ids[]" value="{{ $t->id }}" class="rounded">
                                {{ $t->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background: #8b5cf6; color: white;">
                    Add code
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
