@extends('user.layouts.app')
@section('title', 'Confirm merge')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-2xl font-bold text-white mb-2">Confirm merge</h1>
    <p class="text-sm text-white/60 mb-6">
        Everything below will move from <span class="text-white">{{ $secondary->email ?: ('user #'.$secondary->id) }}</span>
        into your current account <span class="text-white">{{ $primary->email }}</span>.
        The other account will be deleted afterwards.
    </p>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-3">What will move</h2>
        @if(empty($counts))
            <p class="text-white/60 text-sm">The other account doesn't own any data, only its identifiers will be moved.</p>
        @else
            <ul class="text-sm text-white/80 grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                @foreach($counts as $label => $n)
                    <li class="py-1 flex justify-between border-b border-white/5">
                        <span class="text-white/60">{{ $label }}</span>
                        <span class="text-white font-medium">{{ $n }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-3">Identifiers being attached</h2>
        @if(count($identifiers) === 0)
            <p class="text-white/60 text-sm">No verified identifiers on the other account.</p>
        @else
            <ul class="text-sm text-white/80 space-y-1">
                @foreach($identifiers as $id)
                    <li>· <span class="text-white/60">{{ $id->kindLabel() }}:</span> {{ $id->displayLabel() }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Cancel form sits OUTSIDE the confirm form below — nested forms
         are invalid HTML and break some browsers' submission handling. --}}
    <form method="POST" action="{{ route('user.merge.cancel') }}" id="merge-cancel-form">@csrf</form>

    <form method="POST" action="{{ route('user.merge.confirm') }}">
        @csrf

        @if($primary_has_paid_plan && $secondary_has_paid_plan)
            <div class="glass rounded-2xl p-6 mb-6 border border-amber-500/30">
                <h2 class="text-lg font-semibold text-white mb-2">Choose which paid plan to keep</h2>
                <p class="text-sm text-amber-100/80 mb-4">
                    Both accounts have an active paid plan. Pick which one survives,
                    the other plan is cancelled immediately, with no refund or proration.
                    Any remaining time on the cancelled plan is forfeited.
                </p>
                <label class="flex items-start gap-2 mb-2 text-sm text-white/80">
                    <input type="radio" name="keep_plan_from" value="primary" checked class="mt-1">
                    <span>
                        Keep <span class="text-white">this account's</span> plan
                        ({{ optional($primary->plan)->name ?: 'current' }}, expires {{ optional($primary->plan_expires_at)->toFormattedDateString() ?: '—' }}).
                        Cancels the other account's plan.
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-white/80">
                    <input type="radio" name="keep_plan_from" value="secondary" class="mt-1">
                    <span>
                        Keep <span class="text-white">the other account's</span> plan
                        ({{ optional($secondary->plan)->name ?: 'other' }}, expires {{ optional($secondary->plan_expires_at)->toFormattedDateString() ?: '—' }}).
                        Cancels this account's current plan.
                    </span>
                </label>
            </div>
        @elseif($secondary_has_paid_plan)
            <div class="glass rounded-2xl p-6 mb-6">
                <p class="text-sm text-white/70">
                    The other account has a paid plan ({{ optional($secondary->plan)->name }}). It will be moved onto this account automatically.
                </p>
                <input type="hidden" name="keep_plan_from" value="secondary">
            </div>
        @else
            <input type="hidden" name="keep_plan_from" value="primary">
        @endif

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2.5 bg-amber-500 text-black rounded-xl font-medium hover:bg-amber-400">
                Merge accounts now
            </button>
            <button type="submit" form="merge-cancel-form" class="px-4 py-2.5 bg-white/5 border border-white/10 text-white/80 rounded-xl hover:bg-white/10">Cancel</button>
        </div>
    </form>
</div>
@endsection
