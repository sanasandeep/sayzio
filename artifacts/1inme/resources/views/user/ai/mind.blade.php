@extends('user.layouts.app')
@section('title', 'Note Summarizer')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'Note Summarizer',
        'subtitle' => 'Paste raw notes — get a tight summary and clear next steps.',
        'balance'  => $balance,
    ])

    <form method="POST" action="{{ route('user.ai.mind.think') }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3">
        @csrf
        <textarea name="thoughts" rows="8" required minlength="5" maxlength="8000"
                  placeholder="Brain dump goes here…"
                  class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">{{ old('thoughts', $input) }}</textarea>
        @error('thoughts')<p class="text-xs text-red-300">{{ $message }}</p>@enderror
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Think it through
            </button>
        </div>
    </form>

    @if($result)
        <div class="mt-6 rounded-2xl border border-blue-500/20 bg-blue-500/[0.05] p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-white/40 uppercase tracking-wider">Result</p>
                <p class="text-xs text-white/40">
                    {{ $result['model'] }} · {{ number_format($result['credits_spent']) }} ✦ spent
                </p>
            </div>
            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans">{{ $result['content'] }}</pre>
        </div>
    @endif
</div>
@endsection
