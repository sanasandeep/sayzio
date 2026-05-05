@extends('user.layouts.app', ['pageTitle' => 'Connect ' . $provider['name']])

@section('content')
<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl"
                 style="background-color: {{ $provider['tint'] }};">
                <i class="{{ $provider['icon'] }}"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900">Connect {{ $provider['name'] }}</h1>
                <p class="text-xs text-slate-500">Hosted onboarding &middot; <strong>preview mode</strong></p>
            </div>
        </div>

        <div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
            <i class="fas fa-flask"></i>
            We don't have live API keys for {{ $provider['name'] }} on this environment, so we can't
            send you to their real onboarding flow. You can simulate a successful connection below
            to keep testing the rest of the product.
        </div>

        <ul class="mt-5 space-y-2 text-sm text-slate-700">
            <li><i class="fas fa-globe text-slate-400 w-5"></i> {{ $provider['countries'] }}</li>
            <li><i class="fas fa-bolt text-slate-400 w-5"></i> {{ $provider['payout_speed'] }}</li>
            <li><i class="fas fa-percent text-slate-400 w-5"></i> {{ $provider['fees'] }}</li>
        </ul>

        <form method="POST" action="{{ route('user.payouts.preview-complete', ['provider' => $provider['slug']]) }}" class="mt-6 flex flex-wrap gap-2">
            @csrf
            <button class="px-5 py-2.5 rounded-lg text-white font-semibold text-sm"
                    style="background-color: {{ $provider['tint'] }};">
                <i class="fas fa-check"></i> Mark as connected (preview)
            </button>
            <a href="{{ route('user.payouts.show') }}"
               class="px-5 py-2.5 rounded-lg bg-slate-100 text-slate-700 font-semibold text-sm hover:bg-slate-200">
                Cancel
            </a>
            <a href="{{ $provider['docs_url'] }}" target="_blank" rel="noopener"
               class="ml-auto text-xs text-slate-500 hover:text-slate-700 inline-flex items-center gap-1 self-center">
                {{ $provider['name'] }} docs <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
            </a>
        </form>
    </div>
</div>
@endsection
