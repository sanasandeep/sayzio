@extends('admin.layouts.app')
@section('title', 'Master Password')
@section('page-title', 'Master Password')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300 admin-mp-tone-green',
            'amber' => 'bg-amber-500/10 border-amber-500/20 text-amber-300 admin-mp-tone-amber',
            'red'   => 'bg-red-500/10 border-red-500/20 text-red-300 admin-mp-tone-red',
            default => 'bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@push('styles')
<style>
html.light-mode .admin-mp-banner        { color: #92400e; }
html.light-mode .admin-mp-banner-sub    { color: #b45309; }
html.light-mode .admin-mp-flash-success { color: #065f46; }
html.light-mode .admin-mp-flash-error   { color: #991b1b; }
html.light-mode .admin-mp-tone-green    { color: #065f46; }
html.light-mode .admin-mp-tone-amber    { color: #92400e; }
html.light-mode .admin-mp-tone-red      { color: #991b1b; }
</style>
@endpush

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="admin-mp-banner p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-200/90 text-sm space-y-1">
        <p class="font-semibold flex items-center gap-2">
            <i class="fas fa-triangle-exclamation"></i> Highly sensitive
        </p>
        <p class="admin-mp-banner-sub text-amber-200/70 text-xs">
            When enabled, entering <strong>any</strong> account's email/identifier together with this master
            password signs in to that account &mdash; on web login, the mobile/REST API, and the admin panel
            &mdash; without changing the account's real password. The account's own password keeps working.
            Every master-password login is recorded in the audit trail below. Keep this off unless you need it.
        </p>
    </div>

    @if (session('success'))
        <div class="admin-mp-flash-success p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="admin-mp-flash-error p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Settings form                                                --}}
    {{-- ============================================================ --}}
    <form method="POST" action="{{ route('admin.master-password.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-key text-blue-400"></i> Master override password
                    </h3>
                    <p class="text-xs text-white/40">Off by default. Set a password, then enable the override.</p>
                </div>
                <div class="shrink-0">
                    <span class="px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                        {{ $status['label'] }}
                    </span>
                </div>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">
                    {{ $hasPassword ? 'New master password' : 'Master password' }}
                </label>
                @if($hasPassword)
                    <p class="text-xs text-white/60 mb-1">
                        A master password is currently stored
                        <span class="font-mono text-amber-300">••••••••</span>.
                        Leave this blank to keep it, or type a new one to replace it.
                    </p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'password',
                    'placeholder' => $hasPassword ? 'Type a new password to replace' : 'At least 8 characters',
                    'autocomplete' => 'new-password',
                    'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                <p class="text-[11px] text-white/30 mt-1">
                    Stored as a one-way hash, encrypted at rest with the application key. It is never displayed back.
                </p>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-white/80">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="accent-blue-500"
                       {{ $isEnabled ? 'checked' : '' }}>
                Enable the master override
            </label>

            @if($hasPassword)
                <label class="block mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                    <input type="hidden" name="clear_password" value="0">
                    <input type="checkbox" name="clear_password" value="1" class="accent-red-500">
                    Clear the stored master password (also disables the override)
                </label>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Save settings
            </button>
            @if($isActive)
                <span class="text-xs text-emerald-300/80">
                    <i class="fas fa-circle-check mr-1"></i> The override is active right now.
                </span>
            @endif
        </div>
    </form>

    {{-- ============================================================ --}}
    {{-- Audit trail                                                  --}}
    {{-- ============================================================ --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
        <div>
            <h3 class="font-semibold text-white text-sm flex items-center gap-2">
                <i class="fas fa-list-check text-sky-400"></i> Master-password logins
            </h3>
            <p class="text-xs text-white/40">The 50 most recent sign-ins that used the master password.</p>
        </div>

        @if($logins->isEmpty())
            <p class="text-xs text-white/40">No master-password logins recorded yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-white/40 uppercase tracking-wider text-[10px] text-left">
                            <th class="py-2 pr-3">When</th>
                            <th class="py-2 pr-3">Account accessed</th>
                            <th class="py-2 pr-3">Surface</th>
                            <th class="py-2 pr-3">IP</th>
                            <th class="py-2 pr-3">User agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($logins as $login)
                            <tr class="text-white/70">
                                <td class="py-2 pr-3 whitespace-nowrap" title="{{ $login->created_at?->toDayDateTimeString() }}">
                                    {{ $login->created_at?->diffForHumans() }}
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="text-white/90">{{ $login->target_name ?: '—' }}</span>
                                    <span class="block text-white/40">{{ $login->target_email ?: '' }}</span>
                                </td>
                                <td class="py-2 pr-3 whitespace-nowrap">{{ $login->guardLabel() }}</td>
                                <td class="py-2 pr-3 font-mono whitespace-nowrap">{{ $login->ip ?: '—' }}</td>
                                <td class="py-2 pr-3 max-w-[220px] truncate text-white/40" title="{{ $login->user_agent }}">
                                    {{ $login->user_agent ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
