@extends('user.layouts.app', ['pageTitle' => 'Connect ' . $provider['name']])

@section('content')
<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="rounded-2xl border p-8 shadow-sm" style="background: var(--bg-card); border-color: var(--border-glass);">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl"
                 style="background-color: {{ $provider['tint'] }};">
                <i class="{{ $provider['icon'] }}"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold" style="color: var(--text-primary);">Connect {{ $provider['name'] }}</h1>
                <p class="text-xs" style="color: var(--text-muted);">Hosted onboarding &middot; <strong>preview mode</strong></p>
            </div>
        </div>

        <div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
            <i class="fas fa-flask"></i>
            We don't have live API keys for {{ $provider['name'] }} on this environment, so we can't
            send you to their real onboarding flow. You can simulate a successful connection below
            to keep testing the rest of the product.
        </div>

        <ul class="mt-5 space-y-2 text-sm" style="color: var(--text-secondary);">
            <li><i class="fas fa-globe w-5" style="color: var(--text-faint);"></i> {{ $provider['countries'] }}</li>
            <li><i class="fas fa-bolt w-5" style="color: var(--text-faint);"></i> {{ $provider['payout_speed'] }}</li>
            <li><i class="fas fa-percent w-5" style="color: var(--text-faint);"></i> {{ $provider['fees'] }}</li>
        </ul>

        <form method="POST" action="{{ route('user.payouts.preview-complete', ['provider' => $provider['slug']]) }}" class="mt-6 flex flex-wrap gap-2">
            @csrf
            <button class="px-5 py-2.5 rounded-lg text-white font-semibold text-sm"
                    style="background-color: {{ $provider['tint'] }};">
                <i class="fas fa-check"></i> Mark as connected (preview)
            </button>
            <a href="{{ route('user.payouts.show') }}"
               class="px-5 py-2.5 rounded-lg btn-ghost font-semibold text-sm">
                Cancel
            </a>
            <a href="{{ $provider['docs_url'] }}" target="_blank" rel="noopener"
               class="ml-auto text-xs inline-flex items-center gap-1 self-center" style="color: var(--text-muted);">
                {{ $provider['name'] }} docs <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
            </a>
        </form>
    </div>
</div>
@endsection
