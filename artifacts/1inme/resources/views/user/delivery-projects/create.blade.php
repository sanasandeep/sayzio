@extends('user.layouts.app')
@section('title', 'New Delivery Project')
@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="page-hero mb-6">
        <h1 class="hero-title">New Delivery Project</h1>
        <p class="hero-subtitle">Spin up a shared project with tasks and a timeline.</p>
    </div>

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg text-sm" style="background: rgba(239,68,68,.12); color:#ef4444;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.delivery-projects.store') }}" class="glass-card rounded-2xl p-6 space-y-5" style="border:1px solid var(--border);">
        @csrf
        @if($prefill['source_type'])
            <input type="hidden" name="source_type" value="{{ $prefill['source_type'] }}">
            <input type="hidden" name="source_id" value="{{ $prefill['source_id'] }}">
            <div class="px-3 py-2 rounded-lg text-xs" style="background: var(--surface-2); color: var(--text-secondary);">
                <i class="fas fa-link mr-1"></i> Linked to your sale — the buyer's details are prefilled below.
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Project title</label>
            <input name="title" value="{{ old('title', $prefill['title']) }}" required maxlength="200"
                   class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Description <span class="opacity-50">(optional)</span></label>
            <textarea name="description" rows="3" maxlength="4000"
                      class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">{{ old('description') }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Client name</label>
                <input name="client_name" value="{{ old('client_name', $prefill['client_name']) }}" maxlength="200"
                       class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Client email</label>
                <input type="email" name="client_email" value="{{ old('client_email', $prefill['client_email']) }}" maxlength="200"
                       class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Warranty expires <span class="opacity-50">(optional)</span></label>
                <input type="date" name="warranty_expires_at" value="{{ old('warranty_expires_at') }}"
                       class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Remind me days before</label>
                <input type="number" name="warranty_reminder_days" min="0" max="365" value="{{ old('warranty_reminder_days', 7) }}"
                       class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
            <input type="checkbox" name="seed_starter_tasks" value="1" checked>
            Add starter tasks (Kickoff, In production, Review, Deliver)
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-5 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">
                Create project
            </button>
            <a href="{{ route('user.delivery-projects.index') }}" class="text-sm" style="color: var(--text-tertiary);">Cancel</a>
        </div>
    </form>
</div>
@endsection
