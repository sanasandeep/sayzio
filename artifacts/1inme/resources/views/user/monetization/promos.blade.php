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
            @php
                $statusBadges = [
                    \App\Modules\User\Models\SubscriptionPromoCode::STATUS_ACTIVE         => ['Active', '16,185,129', '#10b981'],
                    \App\Modules\User\Models\SubscriptionPromoCode::STATUS_DISABLED       => ['Disabled', '239,68,68', '#ef4444'],
                    \App\Modules\User\Models\SubscriptionPromoCode::STATUS_EXPIRED        => ['Expired', '245,158,11', '#f59e0b'],
                    \App\Modules\User\Models\SubscriptionPromoCode::STATUS_FULLY_REDEEMED => ['Fully redeemed', '245,158,11', '#f59e0b'],
                ];
            @endphp
            <div class="divide-y" style="border-color: var(--border-color);">
                @foreach($promos as $promo)
                    @php
                        $status    = $promo->status();
                        $badge     = $statusBadges[$status] ?? $statusBadges['active'];
                        $remaining = $promo->remainingRedemptions();
                    @endphp
                    <div x-data="{ editing: false }" class="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-sm px-2 py-0.5 rounded"
                                          style="background: rgba(92,131,255,0.12); color: #5c83ff;">{{ $promo->code }}</span>
                                    <span class="text-xs font-semibold" style="color: var(--text-secondary);">{{ $promo->describe() }}</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full"
                                          style="background: rgba({{ $badge[1] }},0.12); color: {{ $badge[2] }};">{{ $badge[0] }}</span>
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-faint);">
                                    {{ $promo->label ?: 'No label' }}
                                    · <span style="color: var(--text-secondary);">{{ $promo->redemptions_count }}</span>{{ $promo->max_redemptions ? '/'.$promo->max_redemptions : '' }} used
                                    @if($remaining !== null)
                                        · {{ $remaining }} left
                                    @else
                                        · unlimited uses
                                    @endif
                                    @if($promo->expires_at)
                                        · expires {{ $promo->expires_at->format('M j, Y') }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" @click="editing = !editing"
                                        class="text-xs px-2 py-1 rounded border" style="border-color: var(--border-color); color: var(--text-secondary);">
                                    <span x-text="editing ? 'Cancel' : 'Edit'">Edit</span>
                                </button>
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

                        {{-- Inline edit form. Code + discount type are fixed; everything else is editable. --}}
                        <div x-show="editing" x-cloak class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                            <form method="POST" action="{{ route('user.monetization.promos.update', $promo) }}"
                                  class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="0">
                                <div>
                                    <label class="text-xs" style="color: var(--text-faint);">Label (internal)</label>
                                    <input type="text" name="label" value="{{ $promo->label }}" maxlength="120"
                                           class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent text-sm"
                                           style="border-color: var(--border-color); color: var(--text-primary);">
                                </div>
                                <div>
                                    <label class="text-xs" style="color: var(--text-faint);">{{ $promo->describe() }} — value</label>
                                    <input type="number" name="value" value="{{ $promo->value }}" min="0" max="100000" required
                                           class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent text-sm"
                                           style="border-color: var(--border-color); color: var(--text-primary);">
                                </div>
                                <div>
                                    <label class="text-xs" style="color: var(--text-faint);">Max redemptions (blank = unlimited)</label>
                                    <input type="number" name="max_redemptions" value="{{ $promo->max_redemptions }}" min="1" max="100000"
                                           class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent text-sm"
                                           style="border-color: var(--border-color); color: var(--text-primary);">
                                </div>
                                <div>
                                    <label class="text-xs" style="color: var(--text-faint);">Expires (blank = never)</label>
                                    <input type="datetime-local" name="expires_at"
                                           value="{{ $promo->expires_at ? $promo->expires_at->format('Y-m-d\TH:i') : '' }}"
                                           class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent text-sm"
                                           style="border-color: var(--border-color); color: var(--text-primary);">
                                </div>
                                <div class="flex items-end">
                                    <label class="inline-flex items-center gap-2 text-sm px-3 py-2 rounded-lg border cursor-pointer"
                                           style="border-color: var(--border-color); color: var(--text-secondary);">
                                        <input type="checkbox" name="is_active" value="1" class="rounded" {{ $promo->is_active ? 'checked' : '' }}>
                                        Active
                                    </label>
                                </div>

                                @if($tiers->count())
                                    @php $applied = is_array($promo->applies_to_tier_ids) ? array_map('intval', $promo->applies_to_tier_ids) : []; @endphp
                                    <div class="md:col-span-3">
                                        <label class="text-xs" style="color: var(--text-faint);">Restrict to tiers (none checked = all paid tiers)</label>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach($tiers as $t)
                                                <label class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer"
                                                       style="border-color: var(--border-color); color: var(--text-secondary);">
                                                    <input type="checkbox" name="tier_ids[]" value="{{ $t->id }}" class="rounded"
                                                           {{ in_array((int) $t->id, $applied, true) ? 'checked' : '' }}>
                                                    {{ $t->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="md:col-span-3 flex justify-end gap-2">
                                    <button type="button" @click="editing = false"
                                            class="px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Cancel</button>
                                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background: #5c83ff; color: white;">Save changes</button>
                                </div>
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
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background: #5c83ff; color: white;">
                    Add code
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
