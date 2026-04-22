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

    @if(session('status'))
        <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.05] px-4 py-2 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-500/20 bg-red-500/[0.05] px-4 py-2 text-sm text-red-200">
            {{ session('error') }}
        </div>
    @endif

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

        @include('user.ai._partials.mind-picker', [
            'mineMinds'     => $mineMinds,
            'platformMind'  => $platformMind,
            'selectedIds'   => old('mind_ids', $input['mind_ids'] ?? []),
            'platformOptIn' => old('include_platform', $input['include_platform'] ?? false),
        ])

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

            @include('user.ai._partials.mind-breakdown', ['result' => $result])

            <form method="POST" action="{{ route('user.ai.persona.save') }}"
                  class="mt-4 flex flex-col sm:flex-row gap-2 sm:items-center border-t border-white/10 pt-4">
                @csrf
                <input type="text" name="name" required maxlength="120"
                       placeholder="Name this persona (e.g. Solo SaaS founder)"
                       class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                    Save to library
                </button>
                @error('name')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
            </form>
        </div>
    @endif

    <div class="mt-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-white/80 uppercase tracking-wider">Saved personas</h2>
            <span class="text-xs text-white/40">{{ $saved->count() }} saved</span>
        </div>

        @if($saved->isEmpty())
            <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-6 text-center text-sm text-white/50">
                You haven't saved any personas yet. Generate one above and save it to reuse later.
            </div>
        @else
            <ul class="space-y-3">
                @foreach($saved as $persona)
                    <li class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-white truncate">{{ $persona->name }}</p>
                                <p class="text-xs text-white/50 mt-0.5">
                                    Saved {{ $persona->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('user.ai.persona.show', ['from' => $persona->id]) }}"
                                   class="px-3 py-1.5 rounded-lg bg-violet-600/80 text-white text-xs font-semibold hover:bg-violet-600">
                                    Use in form
                                </a>
                                <button type="button"
                                        onclick="document.getElementById('persona-details-{{ $persona->id }}').classList.toggle('hidden')"
                                        class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-white/80 text-xs hover:bg-white/10">
                                    View
                                </button>
                                <form method="POST" action="{{ route('user.ai.persona.destroy', $persona) }}"
                                      onsubmit="return confirm('Delete this persona?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1.5 rounded-lg bg-red-600/20 border border-red-500/30 text-red-200 text-xs hover:bg-red-600/30">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div id="persona-details-{{ $persona->id }}" class="hidden mt-4 space-y-3">
                            <div class="rounded-xl bg-white/[0.02] border border-white/10 p-3 text-xs text-white/70 space-y-1">
                                <p><span class="text-white/40">Audience:</span> {{ $persona->audience }}</p>
                                @if($persona->goals)
                                    <p><span class="text-white/40">Goals:</span> {{ $persona->goals }}</p>
                                @endif
                                @if($persona->tone)
                                    <p><span class="text-white/40">Tone:</span> {{ $persona->tone }}</p>
                                @endif
                            </div>
                            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans bg-white/[0.02] border border-white/10 rounded-xl p-3">{{ $persona->content }}</pre>

                            <form method="POST" action="{{ route('user.ai.persona.update', $persona) }}"
                                  class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" required maxlength="120"
                                       value="{{ $persona->name }}"
                                       class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                                <button class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-white text-xs font-semibold hover:bg-white/20">
                                    Rename
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
