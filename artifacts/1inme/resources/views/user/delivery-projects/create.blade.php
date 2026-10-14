@extends('user.layouts.app')
@section('title', 'New client project')
@section('content')
@include('user.planner.styles')
@include('user.partials.client-create-styles')
<div class="client-create-page">
    @include('user.partials.page-hero',['title'=>'New client project','subtitle'=>'Connect a client profile, billing and delivery work.','emblemIcon'=>'fa-user-plus','favicon'=>null,'url'=>null,'back'=>route('user.delivery-projects.index'),'chips'=>[],'actions'=>[]])
    <div class="client-create-intro"><strong>Add work under a client</strong><p>Choose your client once, then organise delivery, tasks and billing for this project.</p><a href="{{ route('user.vault.clients.index') }}">Back to Clients →</a></div>
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg text-sm" style="background: rgba(239,68,68,.12); color:#ef4444;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.delivery-projects.store') }}" class="glass-card rounded-2xl p-6 space-y-5" style="border:1px solid var(--border);">
        @csrf
        <label class="block text-sm font-semibold">Client<select name="vault_client_id" class="theme-input mt-2 w-full"><option value="">No linked profile</option>@foreach($vaultClients as $vaultClient)<option value="{{ $vaultClient->id }}" @selected((string)old('vault_client_id',$selectedClient?->id) === (string)$vaultClient->id)>{{ $vaultClient->name }}{{ $vaultClient->company?' · '.$vaultClient->company:'' }}</option>@endforeach</select></label><p class="text-xs opacity-60">Name, company, email, phone and website remain linked to Vault. Private notes and secrets stay in Vault and are never added to client share pages.</p>
        @if($prefill['source_type'])
            <input type="hidden" name="source_type" value="{{ $prefill['source_type'] }}">
            <input type="hidden" name="source_id" value="{{ $prefill['source_id'] }}">
            <div class="px-3 py-2 rounded-lg text-xs" style="background: var(--surface-2); color: var(--text-secondary);">
                <i class="fas fa-link mr-1"></i> Linked to your sale, the buyer's details are prefilled below.
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Project name</label>
            <input name="title" value="{{ old('title', $prefill['title']) }}" required maxlength="200"
                   class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Description <span class="opacity-50">(optional)</span></label>
            <textarea name="description" rows="3" maxlength="4000"
                      class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">{{ old('description') }}</textarea>
        </div>

        <label class="block text-sm font-semibold">Folder · optional<select name="folder_id"><option value="">No Folder</option>@foreach($folders as $folder)<option value="{{ $folder->id }}" @selected((string)old('folder_id')===(string)$folder->id)>{{ $folder->name }}</option>@endforeach</select></label>
        <details><summary class="client-create-hint">Delivery settings · optional</summary>
        <p class="client-create-hint">Optional delivery contact used for completion notifications. Vault profile details stay in Vault.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Delivery contact name</label>
                <input name="client_name" value="{{ old('client_name', $prefill['client_name']) }}" maxlength="200"
                       class="w-full rounded-lg px-3 py-2 text-sm" style="background: var(--surface-2); border:1px solid var(--border); color: var(--text-primary);">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Delivery contact email</label>
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

        </details>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-5 py-2 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">
                Create project
            </button>
            <a href="{{ route('user.delivery-projects.index') }}" class="text-sm" style="color: var(--text-tertiary);">Cancel</a>
        </div>
    </form>
</div>
@endsection
