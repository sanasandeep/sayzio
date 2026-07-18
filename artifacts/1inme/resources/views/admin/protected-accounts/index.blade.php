@extends('admin.layouts.app')
@section('title', 'Protected Accounts')
@section('page-title', 'Protected Accounts')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Protected accounts</h1>
        <p class="text-sm" style="color: var(--text-dimmed);">
            Accounts on this list can never be deleted or suspended — from user management,
            staff management, or any other path. The superadmin and demo accounts are
            permanently protected and cannot be removed.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if(session('info'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-200 text-sm">
            {{ session('info') }}
        </div>
    @endif
    @error('email')
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ $message }}
        </div>
    @enderror

    @if($canManage)
    {{-- Add a new protected account (superadmin only). --}}
    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <h3 class="text-lg font-semibold text-white mb-1">Add protected account</h3>
        <p class="text-xs text-white/40 mb-4">Protects both the user dashboard and any matching back-office staff account (matched by email). For accounts without an email (e.g. WhatsApp-only signups), enter the user ID instead.</p>
        <form method="POST" action="{{ route('admin.protected-accounts.store') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="email" name="email" maxlength="191" placeholder="email@example.com"
                   class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-blue-500/40 outline-none">
            <input type="number" name="user_id" min="1" placeholder="or user ID"
                   class="w-full sm:w-36 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-blue-500/40 outline-none">
            <input type="text" name="label" maxlength="191" placeholder="Label (optional)"
                   class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-blue-500/40 outline-none">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition whitespace-nowrap">
                <i class="fas fa-shield-alt mr-1"></i> Protect
            </button>
        </form>
    </div>
    @else
    <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-white/5 border border-white/10">
        <i class="fas fa-info-circle text-white/40 mt-0.5"></i>
        <div class="text-sm text-white/60">You can view the protected list, but only a superadmin can add or remove accounts.</div>
    </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-white/10 text-left text-xs uppercase tracking-wide text-white/40">
                    <th class="px-6 py-3">Account</th>
                    <th class="px-6 py-3">Label</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                <tr class="border-b border-white/5">
                    <td class="px-6 py-4 text-sm text-white">
                        @if($account->email)
                            {{ $account->email }}
                        @else
                            @php($protectedUser = isset($usersById) ? $usersById->get($account->user_id) : null)
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-white/10 text-white/60">User #{{ $account->user_id }}</span>
                                @if($protectedUser)
                                    <span>{{ $protectedUser->name ?? $protectedUser->handle ?? '—' }}</span>
                                @else
                                    <span class="text-white/40">(account no longer exists)</span>
                                @endif
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-white/60">{{ $account->label ?? '—' }}</td>
                    <td class="px-6 py-4">
                        @if($account->isLocked())
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/10 text-blue-300">
                                <i class="fas fa-lock text-[10px]"></i> Permanent
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400">
                                <i class="fas fa-shield-alt text-[10px]"></i> Protected
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($canManage && ! $account->isLocked())
                        <form action="{{ route('admin.protected-accounts.destroy', $account) }}" method="POST" class="inline"
                              onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove protection?', message: 'This account will be deletable and suspendable again.', confirmText: 'Remove', confirmIcon: 'fa-shield-alt', iconClass: 'fa-shield-alt'})">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-white/30 hover:text-red-400" title="Remove protection"><i class="fas fa-trash"></i></button>
                        </form>
                        @elseif($account->isLocked())
                        <span class="text-white/20" title="Cannot be removed"><i class="fas fa-lock"></i></span>
                        @else
                        <span class="text-white/20">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-white/30">No protected accounts</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
