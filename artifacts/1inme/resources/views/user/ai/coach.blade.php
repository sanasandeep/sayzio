@extends('user.layouts.app')
@section('title', 'AI Growth Coach')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'AI Growth Coach',
        'subtitle' => 'Pick a link — AI Growth Coach reviews recent stats and proposes experiments.',
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
                <button class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    Get suggestions
                </button>
            </div>
        </form>
    @endif

    @if($result)
        <div class="mt-6 rounded-2xl border border-blue-500/20 bg-blue-500/[0.05] p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-white/40 uppercase tracking-wider">AI Growth Coach on "{{ $result['link_title'] }}"</p>
                <p class="text-xs text-white/40">
                    {{ $result['model'] }} · {{ number_format($result['credits_spent']) }} ✦ spent
                </p>
            </div>
            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans">{{ $result['content'] }}</pre>

            @include('user.ai._partials.mind-breakdown', ['result' => $result])
        </div>
    @endif
</div>
@endsection
