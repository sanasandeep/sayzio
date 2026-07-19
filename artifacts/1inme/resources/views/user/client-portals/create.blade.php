@extends('user.layouts.app')
@section('title', 'New Client Portal')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">New Client Portal</h1>

    <form action="{{ route('user.client-portals.store') }}" method="POST" class="space-y-4 rounded-xl border p-6" style="border-color: var(--border-strong); background: var(--bg-card);">
        @csrf
        <div>
            <label class="block text-sm font-semibold mb-1">Portal name</label>
            <input name="name" required maxlength="160" placeholder="Acme Co., Spring Campaign"
                   class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1">Linked vault client (optional)</label>
            <select name="vault_client_id" class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                <option value="">No client linked</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}@if($c->company), {{ $c->company }}@endif</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1">Brand name (optional)</label>
                <input name="brand_name" maxlength="160" placeholder="Defaults to workspace name"
                       class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Brand color</label>
                <input name="brand_color" type="color" value="#3d6bff"
                       class="h-10 w-20 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1">Logo URL (optional)</label>
            <input name="brand_logo_url" type="url" placeholder="https://…/logo.png"
                   class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1">Welcome message (optional)</label>
            <textarea name="welcome_message" rows="3" maxlength="2000"
                      class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);"></textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.client-portals.index') }}" class="px-4 py-2 text-sm">Cancel</a>
            <button class="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold text-sm">Create portal</button>
        </div>
    </form>
</div>
@endsection
