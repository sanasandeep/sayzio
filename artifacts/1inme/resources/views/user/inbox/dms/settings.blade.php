@extends('user.layouts.app')
@section('title', 'DM access')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'DM access',
        'subtitle' => 'Decide who can DM you and what they pay to start a conversation',
        'icon'     => 'fa-lock',
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('user.inbox.dms.settings.update') }}" class="space-y-5 bg-white/5 border border-white/10 p-5 rounded-2xl">
        @csrf
        <div>
            <label class="block text-sm font-semibold mb-2">Who can DM me?</label>
            <div class="space-y-2">
                @foreach($modes as $value => $label)
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 hover:border-violet-400/40 cursor-pointer">
                        <input type="radio" name="dm_access_mode" value="{{ $value }}" @checked(($user->dm_access_mode ?? 'open') === $value) class="mt-1">
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="block text-sm">
                <span class="text-slate-300">Pay-to-message price (USD)</span>
                <input type="number" min="0" max="100000" step="1"
                       name="dm_pay_price_cents"
                       value="{{ (int)($user->dm_pay_price_cents ?? 0) }}"
                       class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                <span class="text-[11px] text-slate-500">In cents — e.g. 500 = $5.00.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-300">Currency</span>
                <input type="text" name="dm_pay_currency" maxlength="3"
                       value="{{ strtoupper($user->dm_pay_currency ?? 'USD') }}"
                       class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm uppercase">
            </label>
            <label class="block text-sm">
                <span class="text-slate-300">Min subscription tier (subs-only)</span>
                <select name="dm_min_tier_id" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    <option value="">— Any tier —</option>
                    @foreach($tiers as $t)
                        <option value="{{ $t->id }}" @selected((int)($user->dm_min_tier_id ?? 0) === (int)$t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="dm_read_receipts_enabled" value="0">
            <input type="checkbox" name="dm_read_receipts_enabled" value="1" @checked($user->dm_read_receipts_enabled ?? false)>
            Show read receipts to fans
        </label>

        <div class="flex items-center justify-between pt-2 border-t border-white/10">
            <div class="text-xs text-slate-500">Once a fan pays the message fee, they keep posting until you reply.</div>
            <button class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold">Save</button>
        </div>
    </form>
</div>
@endsection
