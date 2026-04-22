@extends('user.layouts.app')
@section('title', 'Persona')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Persona',
        'title'    => 'Generate a brand persona',
        'subtitle' => 'Describe your audience — get a profile to anchor your copy.',
        'balance'  => $balance,
    ])

    <form method="POST" action="{{ route('user.ai.persona.generate') }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        @csrf
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Audience *</label>
            <input type="text" name="audience" required maxlength="400"
                   value="{{ old('audience', $input['audience'] ?? '') }}"
                   placeholder="e.g. Solo SaaS founders launching their first paid app"
                   class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            @error('audience')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Goals (optional)</label>
            <textarea name="goals" rows="2" maxlength="600"
                      placeholder="What do you want this persona to help you do?"
                      class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">{{ old('goals', $input['goals'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Preferred tone (optional)</label>
            <input type="text" name="tone" maxlength="200"
                   value="{{ old('tone', $input['tone'] ?? '') }}"
                   placeholder="e.g. friendly, expert, no jargon"
                   class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">
                Generate persona
            </button>
        </div>
    </form>

    @if($result)
        <div class="mt-6 rounded-2xl border border-violet-500/20 bg-violet-500/[0.05] p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-white/40 uppercase tracking-wider">Persona</p>
                <p class="text-xs text-white/40">
                    {{ $result['model'] }} · {{ number_format($result['credits_spent']) }} ✦ spent
                </p>
            </div>
            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans">{{ $result['content'] }}</pre>
        </div>
    @endif
</div>
@endsection
