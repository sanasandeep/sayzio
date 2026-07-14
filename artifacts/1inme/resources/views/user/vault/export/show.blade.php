@extends('user.layouts.app')
@section('title', 'Vault — Export')
@section('content')
@include('user.vault._tabs')

<div class="max-w-xl">
    <h2 class="text-lg font-semibold mb-2">Export the vault</h2>
    <p class="text-sm mb-6" style="color: var(--text-muted);">
        Download every credential and client record as an AES-256-GCM encrypted JSON file.
        Choose a strong passphrase — it is the only thing that can open the export and is
        never stored on the server.
    </p>
    <form method="post" action="{{ route('user.vault.export.download') }}" class="space-y-4">
        @csrf
        @error('passphrase')<div class="text-red-300 text-sm">{{ $message }}</div>@enderror
        <label class="block">
            <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Passphrase (min 8 chars)</span>
            @include('common.partials.password-field', [
                'name' => 'passphrase',
                'required' => true,
                'minlength' => 8,
                'autocomplete' => 'new-password',
                'wrapClass' => 'mt-1',
                'inputClass' => 'w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm',
            ])
        </label>
        <button class="px-5 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
            <i class="fas fa-download mr-1"></i> Download encrypted export
        </button>
    </form>
</div>
@endsection
