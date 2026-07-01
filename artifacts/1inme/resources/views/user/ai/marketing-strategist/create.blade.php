@extends('user.layouts.app')
@section('title', 'New Marketing Strategy')

@section('content')
@php
    $params       = (array) ($old['parameters'] ?? []);
    $picked       = (array) ($old['sources'] ?? ['links','analytics','audience']);
    $oldItems     = (array) ($old['source_items'] ?? []);
    $contentTypes = ['Short-form video','Reels / Shorts','Stories','Long-form video','Live streams','Carousels','Blog posts','Newsletters / Email','Podcasts','Infographics','User-generated content','Webinars','Case studies'];
    $paidMedia    = ['Instagram Ads','Facebook Ads','TikTok Ads','YouTube Ads','Google Search Ads','Google Display','LinkedIn Ads','X / Twitter Ads','Pinterest Ads','Snapchat Ads','Local newspaper','Digital newspaper','Influencer partnerships','Podcast sponsorships'];
    $pickedContent = (array) ($params['content_types'] ?? []);
    $pickedPaid    = (array) ($params['paid_media'] ?? []);
@endphp
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
            <p class="text-xs text-white/50 mt-1">Toggle the data you want the strategist to ground its plan in. Only names and aggregate stats are shared — never private contact details. For data with individual items you can narrow to a few — <span class="text-white/70">leaving all unselected means "use everything"</span>.</p>
            <div class="grid grid-cols-1 gap-2 mt-4">
                @foreach($sources as $key => $meta)
                    @php
                        $on          = in_array($key, $picked, true);
                        $isSelect    = ($meta['selectable'] ?? false) && !empty($items[$key]);
                        $sourceItems = $items[$key] ?? [];
                        $chosenIds   = array_map('intval', (array) ($oldItems[$key] ?? []));
                    @endphp
                    <div x-data="{ on: {{ $on ? 'true' : 'false' }}, open: {{ (!empty($chosenIds)) ? 'true' : 'false' }} }"
                         class="rounded-xl border border-white/10 bg-white/[0.02] overflow-hidden">
                        <label class="flex items-start gap-3 p-3 cursor-pointer hover:bg-white/[0.05]">
                            <input type="checkbox" name="sources[]" value="{{ $key }}" x-model="on"
                                   class="mt-0.5 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-white font-medium">{{ $meta['label'] }}</span>
                                <span class="block text-[11px] text-white/40 mt-0.5">{{ $meta['description'] }}</span>
                            </span>
                            @if($isSelect)
                                <button type="button" x-show="on" @click.prevent="open = !open"
                                        class="shrink-0 text-[11px] text-blue-300 hover:text-blue-200 px-2 py-1 rounded-lg bg-blue-500/10">
                                    <span x-text="open ? 'Hide items' : 'Choose specific'"></span>
                                </button>
                            @endif
                        </label>

                        @if($isSelect)
                            <div x-show="on && open" x-cloak class="border-t border-white/10 bg-black/20 p-3">
                                <p class="text-[11px] text-white/40 mb-2">Pick the {{ strtolower($meta['label']) }} to use. Select none to use all of them.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-60 overflow-y-auto pr-1">
                                    @foreach($sourceItems as $item)
                                        <label class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1.5 cursor-pointer hover:bg-white/[0.06]">
                                            <input type="checkbox" name="source_items[{{ $key }}][]" value="{{ $item['id'] }}"
                                                   @checked(in_array((int) $item['id'], $chosenIds, true))
                                                   class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500">
                                            <span class="min-w-0">
                                                <span class="block text-xs text-white truncate">{{ $item['label'] }}</span>
                                                @if(!empty($item['sub']))
                                                    <span class="block text-[10px] text-white/40 truncate">{{ $item['sub'] }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
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
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-6">
            <div>
                <h2 class="text-white font-semibold">3. Parameters <span class="text-xs font-normal text-white/40">(optional)</span></h2>
                <p class="text-xs text-white/50 mt-1">The more you give, the sharper the plan. A region biases the plan toward locally-relevant channels.</p>
            </div>

            {{-- Budget & market --}}
            <div>
                <h3 class="text-[11px] uppercase tracking-wide text-white/40 font-semibold mb-3">Budget &amp; market</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Budget</label>
                        <input type="text" name="parameters[budget]" maxlength="120" value="{{ $params['budget'] ?? '' }}"
                               placeholder="e.g. 200 / month"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Currency</label>
                        <input type="text" name="parameters[currency]" maxlength="40" value="{{ $params['currency'] ?? '' }}"
                               placeholder="e.g. USD, EUR, INR"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Region / geographic market</label>
                        <input type="text" name="parameters[region]" maxlength="160" value="{{ $params['region'] ?? '' }}"
                               placeholder="e.g. Austin, Texas · India · Southeast Asia"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Target audience</label>
                        <input type="text" name="parameters[audience]" maxlength="300" value="{{ $params['audience'] ?? '' }}"
                               placeholder="e.g. fitness creators in the US"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                </div>
            </div>

            {{-- Timing & voice --}}
            <div>
                <h3 class="text-[11px] uppercase tracking-wide text-white/40 font-semibold mb-3">Timing &amp; voice</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Timeframe</label>
                        <input type="text" name="parameters[timeframe]" maxlength="120" value="{{ $params['timeframe'] ?? '' }}"
                               placeholder="e.g. 4 weeks"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Posting cadence</label>
                        <input type="text" name="parameters[cadence]" maxlength="120" value="{{ $params['cadence'] ?? '' }}"
                               placeholder="e.g. 3 posts / week"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Tone</label>
                        <input type="text" name="parameters[tone]" maxlength="120" value="{{ $params['tone'] ?? '' }}"
                               placeholder="e.g. friendly and bold"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Brand voice</label>
                        <input type="text" name="parameters[brand_voice]" maxlength="300" value="{{ $params['brand_voice'] ?? '' }}"
                               placeholder="e.g. expert but approachable, no jargon"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                </div>
            </div>

            {{-- Positioning --}}
            <div>
                <h3 class="text-[11px] uppercase tracking-wide text-white/40 font-semibold mb-3">Positioning</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Main offer / product</label>
                        <input type="text" name="parameters[main_offer]" maxlength="300" value="{{ $params['main_offer'] ?? '' }}"
                               placeholder="e.g. paid newsletter, $9/mo coaching"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1">Competitors</label>
                        <input type="text" name="parameters[competitors]" maxlength="400" value="{{ $params['competitors'] ?? '' }}"
                               placeholder="e.g. @rival1, @rival2"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-white/50 mb-1">Things to avoid</label>
                        <input type="text" name="parameters[avoid]" maxlength="400" value="{{ $params['avoid'] ?? '' }}"
                               placeholder="e.g. no paid ads, avoid X/Twitter"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                    </div>
                </div>
            </div>

            {{-- Channels & formats --}}
            <div>
                <h3 class="text-[11px] uppercase tracking-wide text-white/40 font-semibold mb-3">Channels &amp; formats</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-white/50 mb-1">Plan focus</label>
                            <select name="parameters[plan_type]"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                                @foreach(['both' => 'Both organic & paid', 'organic' => 'Organic only', 'paid' => 'Paid only'] as $val => $label)
                                    <option value="{{ $val }}" @selected(($params['plan_type'] ?? 'both') === $val) class="bg-slate-800">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-white/50 mb-1">Preferred channels (free text)</label>
                            <input type="text" name="parameters[channels]" maxlength="300" value="{{ $params['channels'] ?? '' }}"
                                   placeholder="e.g. Instagram, TikTok, email"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-white/50 mb-2">Content types</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($contentTypes as $ct)
                                <label class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 cursor-pointer hover:bg-white/[0.06] text-xs text-white/80">
                                    <input type="checkbox" name="parameters[content_types][]" value="{{ $ct }}"
                                           @checked(in_array($ct, $pickedContent, true))
                                           class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500">
                                    {{ $ct }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-white/50 mb-2">Paid media channels <span class="text-white/30">(incl. local &amp; digital newspapers)</span></label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($paidMedia as $pm)
                                <label class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 cursor-pointer hover:bg-white/[0.06] text-xs text-white/80">
                                    <input type="checkbox" name="parameters[paid_media][]" value="{{ $pm }}"
                                           @checked(in_array($pm, $pickedPaid, true))
                                           class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500">
                                    {{ $pm }}
                                </label>
                            @endforeach
                        </div>
                    </div>
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
