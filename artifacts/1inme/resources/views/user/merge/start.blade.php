@extends('user.layouts.settings')
@section('title', 'Merge another account')

@section('settings-content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-white mb-2">Merge another account</h1>
    <p class="text-sm text-white/60 mb-6">Prove you own the other account and we'll fold all of its data into your current one. The other account will be deleted afterwards.</p>

    @if (session('error'))<div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-200 text-sm">{{ session('error') }}</div>@endif
    @if (session('status'))<div class="mb-4 p-3 rounded-xl bg-blue-500/10 border border-blue-500/30 text-blue-200 text-sm">{{ session('status') }}</div>@endif

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-3">By email or phone</h2>
        <form method="POST" action="{{ route('user.merge.challenge') }}" class="space-y-3">
            @csrf
            <div class="flex gap-2">
                <select name="kind" class="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
                    <option value="email" class="bg-[#0d0818]">Email</option>
                    <option value="phone" class="bg-[#0d0818]">Phone</option>
                </select>
                <input name="value" required placeholder="other@example.com or +15551234567"
                       class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
                <button class="px-4 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">Send code</button>
            </div>
            <p class="text-xs text-white/50">We'll send a one-time code to the other account so you can prove you control it.</p>
        </form>
    </div>

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-3">Or via a social account</h2>
        <div class="flex flex-wrap gap-2">
            @foreach(['instagram','facebook','twitter','linkedin','pinterest','tiktok'] as $p)
                <a href="{{ route('user.social-oauth.merge', $p) }}"
                   class="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white/80 text-sm hover:bg-white/10">
                    Continue with {{ ucfirst($p === 'twitter' ? 'X' : $p) }}
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
