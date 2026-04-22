@extends('admin.layouts.app')
@section('title', 'Ask Coach')

@section('content')
<div class="max-w-6xl mx-auto px-6 py-8 space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40">AI · Ask Coach</p>
            <h1 class="text-2xl font-bold text-white mt-1">Coach usage &amp; quality</h1>
            <p class="text-sm text-white/50 mt-1">Last <strong>{{ $days }}</strong> days. Spend is the sum of every AI credit charged with feature tag <code>ask_coach.*</code>.</p>
        </div>
        <form method="GET" action="{{ route('admin.ask-coach.index') }}">
            <select name="days" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                @foreach([7, 14, 30, 60, 90, 180] as $d)
                    <option value="{{ $d }}" {{ $days === $d ? 'selected' : '' }}>{{ $d }} days</option>
                @endforeach
            </select>
        </form>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Usage tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Chats started</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($threads) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Total messages</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($messages) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Coach replies</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($assistantMessages) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Credits spent</p>
            <p class="text-2xl font-bold text-violet-300 mt-1">{{ number_format($creditsSpent) }} ✦</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">👍 / 👎</p>
            <p class="text-2xl font-bold text-white mt-1">
                <span class="text-emerald-300">{{ number_format($upCount) }}</span>
                <span class="text-white/30 px-1">/</span>
                <span class="text-red-300">{{ number_format($downCount) }}</span>
            </p>
        </div>
    </div>

    {{-- Settings --}}
    <form method="POST" action="{{ route('admin.ask-coach.update') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        @csrf
        @method('PUT')
        <h2 class="text-lg font-semibold text-white">Settings</h2>

        <div>
            <label class="block text-sm text-white/70 mb-1">Central system prompt</label>
            <textarea name="system_prompt" rows="10"
                      class="w-full bg-black/30 border border-white/10 rounded-xl p-3 text-white text-sm font-mono">{{ $systemPrompt }}</textarea>
            <p class="text-xs text-white/40 mt-1">
                Sent at the top of every Ask Coach turn before the data snapshots are appended. Leave blank to restore the platform default.
            </p>
        </div>

        <div>
            <label class="block text-sm text-white/70 mb-2">Enabled plans</label>
            <p class="text-xs text-white/40 mb-2">
                Tick which plans can use Ask Coach. Leave all unticked to enable Ask Coach for every plan (the default).
            </p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                @foreach($allPlans as $p)
                    <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02]">
                        <input type="checkbox" name="plans[]" value="{{ $p->slug }}"
                               {{ in_array($p->slug, $enabledPlans, true) ? 'checked' : '' }}
                               class="rounded border-white/20 bg-white/5 text-violet-500">
                        <span class="text-sm text-white/80">{{ $p->name }}</span>
                    </label>
                @endforeach
                <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02]">
                    <input type="checkbox" name="plans[]" value="free"
                           {{ in_array('free', $enabledPlans, true) ? 'checked' : '' }}
                           class="rounded border-white/20 bg-white/5 text-violet-500">
                    <span class="text-sm text-white/80">Free / no plan</span>
                </label>
            </div>
        </div>

        <div class="pt-2">
            <button class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">
                Save settings
            </button>
        </div>
    </form>

    {{-- Recent thumbs-down for quality loop --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h2 class="text-lg font-semibold text-white mb-3">Recent thumbs-down replies</h2>
        @if($recentDowns->isEmpty())
            <p class="text-sm text-white/50">No 👎 feedback in this window — looking healthy.</p>
        @else
            <ul class="space-y-3">
                @foreach($recentDowns as $m)
                    <li class="rounded-xl bg-black/20 p-3">
                        <p class="text-[11px] text-white/40">
                            Thread #{{ $m->thread_id }} ·
                            {{ $m->created_at?->diffForHumans() }}
                        </p>
                        <p class="text-sm text-white/80 mt-1 line-clamp-3">{{ $m->content }}</p>
                        @if($m->feedback_note)
                            <p class="text-xs text-red-300 mt-2">User note: {{ $m->feedback_note }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
