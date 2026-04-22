@extends('user.layouts.app')
@section('title', 'Coach')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Coach',
        'title'    => 'Get suggestions for a link',
        'subtitle' => 'Pick a link — Coach reviews recent stats and proposes experiments.',
        'balance'  => $balance,
    ])

    @if($links->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 text-sm text-white/60 text-center">
            You don't have any links yet. Create one first, then come back for coaching.
        </div>
    @else
        <form method="POST" action="{{ route('user.ai.coach.suggest') }}"
              class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            @csrf
            <div>
                <label class="text-xs uppercase tracking-wider text-white/40">Link *</label>
                <select name="link_id" required
                        class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    @foreach($links as $link)
                        <option value="{{ $link->id }}" {{ (int) $pickedId === (int) $link->id ? 'selected' : '' }}>
                            {{ $link->title ?: $link->alias }} ({{ $link->type }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider text-white/40">Goal (optional)</label>
                <input type="text" name="goal" maxlength="200"
                       value="{{ old('goal', $input['goal'] ?? '') }}"
                       placeholder="e.g. more sign-ups, lower bounce, more shares"
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
                    Get suggestions
                </button>
            </div>
        </form>
    @endif

    @if($result)
        <div class="mt-6 rounded-2xl border border-violet-500/20 bg-violet-500/[0.05] p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-white/40 uppercase tracking-wider">Coach on "{{ $result['link_title'] }}"</p>
                <p class="text-xs text-white/40">
                    {{ $result['model'] }} · {{ number_format($result['credits_spent']) }} ✦ spent
                </p>
            </div>
            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans">{{ $result['content'] }}</pre>

            @if(!empty($result['minds_used']))
                <div class="mt-4 pt-4 border-t border-white/10">
                    <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Grounded in</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($result['minds_used'] as $mu)
                            <span class="text-xs px-2 py-1 rounded-full bg-white/5 border border-white/10 text-white/80">
                                {{ $mu['name'] }}@if($mu['is_platform']) · platform @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($result['citations']))
                <div class="mt-4">
                    <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Sources</p>
                    <ul class="space-y-1 text-xs text-white/70">
                        @foreach($result['citations'] as $i => $c)
                            <li>
                                <span class="text-white/40">[{{ $i + 1 }}]</span>
                                <span class="text-white/90">{{ $c['title'] }}</span>
                                <span class="text-white/40">· {{ $c['type'] }}</span>
                                <span class="text-white/40">· match {{ number_format($c['score'] * 100, 1) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
