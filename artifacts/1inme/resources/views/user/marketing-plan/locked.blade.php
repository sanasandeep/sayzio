@extends('user.layouts.app')
@section('title', 'Marketing Plan Calculator')

{{-- Task #6766 — upgrade prompt rendered instead of the tool when the
     user's plan doesn't include the Marketing Plan Calculator. --}}
@section('content')
<div class="max-w-3xl mx-auto px-4 py-16">
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center"
         style="border-color: rgba(255,255,255,0.10);">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/15 text-blue-400">
            <i class="fas fa-calculator text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white mt-5" data-plan-lock="marketing_plan_calculator">Marketing Plan Calculator</h1>
        <p class="text-sm text-white/60 mt-2 max-w-md mx-auto">
            Build a 12-month channel plan, live projections dashboard and ROI summary from your
            own budget and assumptions — no spreadsheet needed.
        </p>
        <div class="mt-6 rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 inline-flex items-start gap-3 text-left">
            <i class="fas fa-lock text-blue-300 mt-0.5"></i>
            <div class="text-sm text-blue-100">
                <div class="font-semibold">The Marketing Plan Calculator is locked on your current plan.</div>
                <div class="text-blue-200/80 mt-0.5">
                    @if(($upgradePlan ?? null))
                        Upgrade to the <strong>{{ $upgradePlan->name }}</strong> plan to unlock this tool.
                    @else
                        Upgrade your plan to unlock this tool.
                    @endif
                </div>
            </div>
        </div>
        <div class="mt-6">
            <a href="{{ route('user.upgrade') }}"
               class="inline-block px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Upgrade plan
            </a>
        </div>
    </div>
</div>
@endsection
