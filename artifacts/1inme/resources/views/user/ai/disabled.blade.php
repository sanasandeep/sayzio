@extends('user.layouts.app')
@section('title', $title ?? 'AI')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-300">
            <i class="fas fa-robot text-2xl"></i>
        </div>
        <h1 class="text-lg font-semibold text-white">{{ $heading ?? 'AI features are currently turned off' }}</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-white/60">
            {{ $message ?? 'The AI engine isn’t enabled on this account right now. Once an administrator switches it on, this feature will be ready to use here.' }}
        </p>
        <div class="mt-6">
            <a href="{{ route('user.dashboard') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                <i class="fas fa-arrow-left text-xs"></i>
                Back to dashboard
            </a>
        </div>
    </div>
</div>
@endsection
