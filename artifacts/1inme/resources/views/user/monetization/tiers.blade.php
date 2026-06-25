@extends('user.layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-4 md:p-6">
    @include('user.monetization._nav')

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Existing tiers list --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @foreach($tiers as $tier)
            @php $color = $tier->color ?: 'violet'; @endphp
            <div class="rounded-xl border p-4" style="border-color: var(--border-color); background: var(--bg-card);">
                <form method="POST" action="{{ route('user.monetization.tiers.update', $tier) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if($tier->is_free)
                                <span class="text-xs uppercase tracking-wider font-semibold px-2 py-0.5 rounded-full"
                                      style="background: rgba(100,116,139,0.15); color: #64748b;">Free</span>
                            @else
                                <span class="text-xs uppercase tracking-wider font-semibold px-2 py-0.5 rounded-full"
                                      style="background: rgba(92,131,255,0.12); color: #5c83ff;">Paid</span>
                            @endif
                            <span class="text-xs" style="color: var(--text-faint);">/{{ $tier->slug }}</span>
                        </div>
                        @if(!$tier->is_free)
                            <label class="inline-flex items-center gap-1 text-xs cursor-pointer" style="color: var(--text-secondary);">
                                <input type="checkbox" name="is_active" value="1" {{ $tier->is_active ? 'checked' : '' }}
                                       class="rounded">
                                Active
                            </label>
                        @endif
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Name</label>
                        <input type="text" name="name" value="{{ $tier->name }}" required maxlength="80"
                               class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent"
                               style="border-color: var(--border-color); color: var(--text-primary);">
                    </div>

                    @if(!$tier->is_free)
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Monthly ($)</label>
                            <input type="number" step="0.01" min="1" max="1000" name="price_monthly"
                                   value="{{ number_format($tier->price_monthly_cents / 100, 2, '.', '') }}"
                                   class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent"
                                   style="border-color: var(--border-color); color: var(--text-primary);">
                        </div>
                        <div>
                            <label class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Yearly ($)</label>
                            <input type="number" step="0.01" min="1" max="10000" name="price_yearly"
                                   value="{{ $tier->price_yearly_cents ? number_format($tier->price_yearly_cents / 100, 2, '.', '') : '' }}"
                                   placeholder="optional"
                                   class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent"
                                   style="border-color: var(--border-color); color: var(--text-primary);">
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Color</label>
                            <select name="color" class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent"
                                    style="border-color: var(--border-color); color: var(--text-primary);">
                                @foreach(['violet', 'sky', 'emerald', 'amber', 'rose', 'fuchsia', 'slate'] as $col)
                                    <option value="{{ $col }}" {{ $tier->color === $col ? 'selected' : '' }}>{{ ucfirst($col) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Badge</label>
                            <input type="text" name="badge" maxlength="32" value="{{ $tier->badge }}"
                                   placeholder="💎 / fas fa-crown"
                                   class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent"
                                   style="border-color: var(--border-color); color: var(--text-primary);">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Perks (one per line)</label>
                        <textarea name="perks" rows="4"
                                  class="w-full mt-1 px-3 py-2 rounded-lg border bg-transparent text-sm"
                                  style="border-color: var(--border-color); color: var(--text-primary);">{{ implode("\n", $tier->visiblePerks()) }}</textarea>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t" style="border-color: var(--border-color);">
                        <button type="submit" class="px-3 py-2 rounded-lg text-sm font-semibold" style="background: #5c83ff; color: white;">
                            <i class="fas fa-save mr-1"></i> Save
                        </button>
                        @if(!$tier->is_free)
                            <button type="button" class="text-xs underline" style="color: #ef4444;"
                                    onclick="document.getElementById('del-tier-{{ $tier->id }}').submit();">
                                Delete
                            </button>
                        @endif
                    </div>
                </form>
                @if(!$tier->is_free)
                    <form id="del-tier-{{ $tier->id }}" method="POST" action="{{ route('user.monetization.tiers.destroy', $tier) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Add new tier --}}
    <div class="rounded-xl border p-5" style="border-color: var(--border-color); background: var(--bg-card);">
        <h2 class="font-semibold mb-3" style="color: var(--text-primary);"><i class="fas fa-plus mr-1"></i>Add a paid tier</h2>
        <form method="POST" action="{{ route('user.monetization.tiers.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <input type="text" name="name" placeholder="Tier name (e.g. Insider)" required maxlength="80"
                   class="px-3 py-2 rounded-lg border bg-transparent md:col-span-2"
                   style="border-color: var(--border-color); color: var(--text-primary);">
            <input type="text" name="badge" placeholder="Badge (e.g. 💎)"
                   class="px-3 py-2 rounded-lg border bg-transparent"
                   style="border-color: var(--border-color); color: var(--text-primary);">
            <input type="number" step="0.01" min="1" max="1000" name="price_monthly" placeholder="Monthly ($)" required
                   class="px-3 py-2 rounded-lg border bg-transparent"
                   style="border-color: var(--border-color); color: var(--text-primary);">
            <input type="number" step="0.01" min="1" max="10000" name="price_yearly" placeholder="Yearly ($) — optional"
                   class="px-3 py-2 rounded-lg border bg-transparent"
                   style="border-color: var(--border-color); color: var(--text-primary);">
            <select name="color" class="px-3 py-2 rounded-lg border bg-transparent"
                    style="border-color: var(--border-color); color: var(--text-primary);">
                @foreach(['violet', 'sky', 'emerald', 'amber', 'rose', 'fuchsia', 'slate'] as $col)
                    <option value="{{ $col }}">{{ ucfirst($col) }}</option>
                @endforeach
            </select>
            <textarea name="perks" rows="3" placeholder="Perks — one per line" class="md:col-span-3 px-3 py-2 rounded-lg border bg-transparent text-sm"
                      style="border-color: var(--border-color); color: var(--text-primary);"></textarea>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background: #5c83ff; color: white;">
                    Add tier
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
