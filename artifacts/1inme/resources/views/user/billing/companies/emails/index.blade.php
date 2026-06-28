@extends('user.layouts.app')
@section('title', 'Client Email Templates')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Client email templates</h1>
            <p class="hero-subtitle">{{ $company->name }} — subject &amp; body of the emails sent to your clients.</p>
        </div>
        <a href="{{ route('user.billing.companies.edit', $company) }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <p class="text-xs mb-4" style="color: var(--text-muted);">
        Customisations apply only to this company's client emails. When a template is left as
        <em>Default</em> it uses the platform's standard content.
    </p>

    <div class="space-y-3">
        @foreach($templates as $key => $tpl)
            <a href="{{ route('user.billing.companies.emails.edit', [$company, $key]) }}"
               class="block p-4 rounded-xl border hover:opacity-90 transition" style="border-color: var(--border-soft); background: var(--bg-card);">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold" style="color: var(--text-primary);">{{ $tpl['entry']['label'] ?? $key }}</div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted);">{{ $tpl['entry']['description'] ?? '' }}</div>
                    </div>
                    @if($tpl['override'])
                        <span class="shrink-0 text-[11px] px-2 py-1 rounded-lg bg-amber-50 text-amber-700">Customised</span>
                    @else
                        <span class="shrink-0 text-[11px] px-2 py-1 rounded-lg" style="background: var(--bg-glass-input); color: var(--text-muted);">Default</span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
