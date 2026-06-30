@extends('user.layouts.app')
@section('title', 'New Marketing Strategy')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Marketing Strategist',
        'title'    => 'Build a strategy',
        'subtitle' => 'Pick what to share, set a goal, and the strategist drafts an organic + paid plan around your Sayzio features.',
        'balance'  => $balance,
    ])

    <form method="POST" action="{{ route('user.ai.marketing-strategist.store') }}" id="ms-form" class="space-y-6">
        @csrf

        {{-- Data sources --}}
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold">1. Your data</h2>
            <p class="text-xs text-white/50 mt-1">Toggle the data you want the strategist to ground its plan in. Only names and aggregate stats are shared — never private contact details.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-4">
                @php $picked = (array) ($old['sources'] ?? ['links','analytics','audience']); @endphp
                @foreach($sources as $key => $meta)
                    <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.02] p-3 cursor-pointer hover:bg-white/[0.05]">
                        <input type="checkbox" name="sources[]" value="{{ $key }}"
                               @checked(in_array($key, $picked, true))
                               class="mt-0.5 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500">
                        <span class="min-w-0">
                            <span class="block text-sm text-white font-medium">{{ $meta['label'] }}</span>
                            <span class="block text-[11px] text-white/40 mt-0.5">{{ $meta['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Goal --}}
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold">2. Your goal</h2>
            <textarea name="goal" rows="3" maxlength="4000" required
                      placeholder="e.g. Grow my newsletter subscribers and drive more clicks to my link-in-bio over the next month."
                      class="w-full mt-3 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30 focus:ring-blue-500 focus:border-blue-500">{{ old('goal', $old['goal'] ?? '') }}</textarea>
            @error('goal')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
        </section>

        {{-- Parameters --}}
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold">3. Parameters <span class="text-xs font-normal text-white/40">(optional)</span></h2>
            @php $params = (array) ($old['parameters'] ?? []); @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                <div>
                    <label class="block text-xs text-white/50 mb-1">Budget</label>
                    <input type="text" name="parameters[budget]" maxlength="120" value="{{ $params['budget'] ?? '' }}"
                           placeholder="e.g. $200 / month"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                </div>
                <div>
                    <label class="block text-xs text-white/50 mb-1">Timeframe</label>
                    <input type="text" name="parameters[timeframe]" maxlength="120" value="{{ $params['timeframe'] ?? '' }}"
                           placeholder="e.g. 4 weeks"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                </div>
                <div>
                    <label class="block text-xs text-white/50 mb-1">Target audience</label>
                    <input type="text" name="parameters[audience]" maxlength="300" value="{{ $params['audience'] ?? '' }}"
                           placeholder="e.g. fitness creators in the US"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                </div>
                <div>
                    <label class="block text-xs text-white/50 mb-1">Tone</label>
                    <input type="text" name="parameters[tone]" maxlength="120" value="{{ $params['tone'] ?? '' }}"
                           placeholder="e.g. friendly and bold"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-white/50 mb-1">Preferred channels</label>
                    <input type="text" name="parameters[channels]" maxlength="300" value="{{ $params['channels'] ?? '' }}"
                           placeholder="e.g. Instagram, TikTok, email"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                </div>
            </div>
        </section>

        <div class="flex items-center justify-between gap-4">
            <div class="text-xs text-white/50" id="ms-estimate">
                <button type="button" id="ms-estimate-btn"
                        class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
                    Estimate cost
                </button>
                <span id="ms-estimate-out" class="ml-2"></span>
            </div>
            <button type="submit" id="ms-submit"
                    class="px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-60">
                <i class="fas fa-wand-magic-sparkles mr-1"></i> Generate strategy
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('ms-form');
    const btn  = document.getElementById('ms-estimate-btn');
    const out  = document.getElementById('ms-estimate-out');
    const submit = document.getElementById('ms-submit');
    if (!form) return;

    btn?.addEventListener('click', async function () {
        out.textContent = 'Estimating…';
        btn.disabled = true;
        try {
            const fd = new FormData(form);
            const res = await fetch(@json(route('user.ai.marketing-strategist.estimate')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data?.error?.message || 'Could not estimate.');
            out.textContent = 'About ' + Number(data.estimate).toLocaleString() + ' coins · balance ' + Number(data.balance).toLocaleString();
        } catch (e) {
            out.textContent = e.message || 'Estimate failed.';
        } finally {
            btn.disabled = false;
        }
    });

    form.addEventListener('submit', function () {
        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Generating…';
    });
})();
</script>
@endsection
