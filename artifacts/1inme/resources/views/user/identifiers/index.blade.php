@extends('user.layouts.app')
@section('title', 'Linked identifiers')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-2xl font-bold text-white mb-2">Linked identifiers</h1>
    <p class="text-sm text-white/60 mb-6">Every email, phone number, and social account that can sign in to this 1INME account.</p>

    @if (session('success'))<div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-200 text-sm">{{ session('error') }}</div>@endif
    @if (session('status'))<div class="mb-4 p-3 rounded-xl bg-violet-500/10 border border-violet-500/30 text-violet-200 text-sm">{{ session('status') }}</div>@endif

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Your identifiers</h2>
        <ul class="divide-y divide-white/10">
            @forelse($identifiers as $id)
                <li class="py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs uppercase tracking-wider text-white/50">{{ $id->kindLabel() }}</span>
                            @if($id->is_primary)
                                <span class="text-[10px] uppercase tracking-wider text-violet-200 bg-violet-500/20 border border-violet-500/30 rounded-full px-2 py-0.5">Primary</span>
                            @endif
                            @if(!$id->verified_at)
                                <span class="text-[10px] uppercase tracking-wider text-amber-200 bg-amber-500/20 border border-amber-500/30 rounded-full px-2 py-0.5">Unverified</span>
                            @endif
                        </div>
                        <div class="text-white truncate">{{ $id->displayLabel() }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!$id->is_primary && $id->kind !== 'social' && $id->verified_at)
                            <form method="POST" action="{{ route('user.identifiers.promote', $id) }}">@csrf
                                <button class="text-xs px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-white/80 hover:bg-white/10">Make primary</button>
                            </form>
                        @endif
                        @if(!$id->is_primary)
                            <form method="POST" action="{{ route('user.identifiers.destroy', $id) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this identifier?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                                <button class="text-xs px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-200 hover:bg-red-500/20">Remove</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-3 text-white/60 text-sm">No identifiers linked yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-3">Add an email or phone</h2>
        @if($pending)
            <p class="text-sm text-white/60 mb-3">We sent a 6-digit code to <span class="text-white">{{ $pending['value'] }}</span>. Enter it below to confirm.</p>
            <form method="POST" action="{{ route('user.identifiers.confirm') }}" class="flex gap-2">
                @csrf
                <input name="code" maxlength="6" required placeholder="123456"
                       class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
                <button class="px-4 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700">Verify</button>
            </form>
        @else
            <form method="POST" action="{{ route('user.identifiers.start') }}" class="space-y-3">
                @csrf
                <div class="flex gap-2">
                    <select name="kind" class="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
                        <option value="email" class="bg-[#0d0818]">Email</option>
                        <option value="phone" class="bg-[#0d0818]">Phone</option>
                    </select>
                    <input name="value" required placeholder="you@example.com or +15551234567"
                           class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
                    <button class="px-4 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700">Send code</button>
                </div>
                <p class="text-xs text-white/50">We'll send a one-time code to verify ownership.</p>
            </form>
        @endif
    </div>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-2">Connect a social account</h2>
        <p class="text-sm text-white/60 mb-3">Connecting a social account from <a href="{{ route('user.social-accounts.index') }}" class="text-violet-300 hover:underline">Connected Accounts</a> automatically lets you sign in with it next time.</p>
    </div>

    <div class="glass rounded-2xl p-6 border border-amber-500/20">
        <h2 class="text-lg font-semibold text-white mb-1">Have a duplicate account?</h2>
        <p class="text-sm text-white/60 mb-4">Sign in to the other account through a one-time challenge and we'll move all of its data into this one.</p>
        <a href="{{ route('user.merge.start') }}" class="inline-block px-4 py-2 bg-amber-500/15 border border-amber-500/30 text-amber-100 rounded-xl text-sm hover:bg-amber-500/25">Merge another account into this one</a>
    </div>
</div>
@endsection
