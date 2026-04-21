@extends('user.layouts.app')

@section('title', 'Invite unavailable')

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <div class="rounded-lg border p-6 text-center" style="border-color: var(--border-strong); background: var(--bg-card);">
        <i class="fas fa-exclamation-triangle text-4xl text-amber-500 mb-3"></i>
        <h1 class="text-xl font-bold mb-2">This invite is no longer valid</h1>
        <p class="text-sm opacity-80">{{ $reason ?? 'The invite may have expired, been revoked, or already been accepted.' }}</p>
        <a href="{{ route('user.dashboard') }}" class="inline-block mt-5 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold">Go to dashboard</a>
    </div>
</div>
@endsection
