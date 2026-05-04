@extends('user.layouts.app')
@section('title', 'Scan in progress · ' . $file->original_name)
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card-premium p-8 text-center">
        <i class="fas fa-shield-virus text-4xl mb-4" style="color: #38bdf8;"></i>
        <h2 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Scanning attachment…</h2>
        <p class="text-sm mb-6" style="color: var(--text-muted);">
            <span class="font-mono">{{ $file->original_name }}</span> is still being checked for viruses
            and phishing patterns. This usually takes a few seconds.
        </p>
        <a href="{{ url()->previous() }}" class="inline-block px-4 py-2 rounded-lg text-xs font-semibold"
           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
            <i class="fas fa-arrow-left mr-1"></i>Back
        </a>
        <a href="{{ url()->current() }}" class="inline-block ml-2 px-4 py-2 rounded-lg text-xs font-semibold text-white"
           style="background: linear-gradient(135deg,#0ea5e9,#0369a1);">
            <i class="fas fa-arrows-rotate mr-1"></i>Refresh
        </a>
    </div>
</div>
@endsection
