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

    // Icon per data source so the list reads as a scannable palette, not a wall of rows.
    $sourceIcons = [
        'links'      => 'fa-link',
        'analytics'  => 'fa-chart-line',
        'audience'   => 'fa-user-group',
        'pixels'     => 'fa-bullseye',
        'minds'      => 'fa-book-open',
        'brand_kits' => 'fa-palette',
        'personas'   => 'fa-user-astronaut',
        'companions' => 'fa-robot',
    ];

    // Shared input styling for a cohesive, premium field treatment.
    $inputCls = 'w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-white/30 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-400/70 focus:outline-none transition';
@endphp

<style>
    .ms-badge {
        background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
        box-shadow: 0 8px 22px -8px rgba(59,130,246,0.65), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    .ms-step {
        position: relative;
        backdrop-filter: blur(12px);
        transition: border-color .25s ease, box-shadow .25s ease;
    }
    .ms-step::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 2px;
        border-radius: 1rem 1rem 0 0;
        background: linear-gradient(90deg, rgba(59,130,246,0.7), rgba(99,102,241,0.5), transparent 70%);
        opacity: .7;
    }
    .ms-generate {
        background: linear-gradient(120deg, #2563eb 0%, #4f46e5 100%);
        box-shadow: 0 14px 30px -10px rgba(59,130,246,0.6), inset 0 1px 0 rgba(255,255,255,0.2);
    }
    .ms-generate:hover { background: linear-gradient(120deg, #3b82f6 0%, #6366f1 100%); }
    .ms-generate:disabled { opacity: .6; }
    .ms-subhead-icon {
        background: rgba(59,130,246,0.12);
        border: 1px solid rgba(59,130,246,0.25);
    }
</style>

<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Marketing Strategist',
        'title'    => 'Build a strategy',
        'subtitle' => 'Pick what to share, set a goal, and the strategist drafts an organic + paid plan around your Sayzio features.',
        'balance'  => $balance,
    ])

    {{-- Step overview strip --}}
    <div class="grid grid-cols-3 gap-2 mb-6">
        @foreach([['1','Your data','fa-database'], ['2','Your goal','fa-bullseye-pointer'], ['3','Parameters','fa-sliders']] as [$n,$lbl,$ic])
            <div class="flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5">
                <span class="ms-badge grid place-items-center h-6 w-6 shrink-0 rounded-full text-[11px] font-bold text-white">{{ $n }}</span>
                <span class="min-w-0">
                    <span class="block text-xs font-medium text-white truncate">{{ $lbl }}</span>
                </span>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('user.ai.marketing-strategist.store') }}" id="ms-form" class="space-y-6">
        @csrf

        {{-- Data sources --}}
        <section class="ms-step rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="ms-badge grid place-items-center h-9 w-9 shrink-0 rounded-xl text-sm font-bold text-white">1</span>
                <div class="min-w-0">
                    <h2 class="text-white font-semibold text-base">Your data</h2>
                    <p class="text-xs text-white/50 mt-1 leading-relaxed">Toggle the data you want the strategist to ground its plan in. Only names and aggregate stats are shared — never private contact details. For data with individual items you can narrow to a few — <span class="text-white/70">leaving all unselected means "use everything"</span>.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-5">
                @foreach($sources as $key => $meta)
                    @php
                        $on          = in_array($key, $picked, true);
                        $isSelect    = ($meta['selectable'] ?? false) && !empty($items[$key]);
                        $sourceItems = $items[$key] ?? [];
                        $chosenIds   = array_map('intval', (array) ($oldItems[$key] ?? []));
                        $sourceIcon  = $sourceIcons[$key] ?? 'fa-database';
                    @endphp
                    <div x-data="{ on: {{ $on ? 'true' : 'false' }}, open: {{ (!empty($chosenIds)) ? 'true' : 'false' }} }"
                         :class="on ? 'border-blue-400/45 bg-blue-500/[0.08] ring-1 ring-blue-400/30' : 'border-white/10 bg-white/[0.02]'"
                         class="rounded-xl border overflow-hidden transition {{ $isSelect ? '' : 'sm:col-span-1' }}">
                        <label class="flex items-center gap-3 p-3.5 cursor-pointer">
                            <input type="checkbox" name="sources[]" value="{{ $key }}" x-model="on" class="sr-only peer">

                            <span class="grid place-items-center h-10 w-10 shrink-0 rounded-xl border transition"
                                  :class="on ? 'bg-blue-500/20 border-blue-400/40 text-blue-200' : 'bg-white/5 border-white/10 text-white/55'">
                                <i class="fas {{ $sourceIcon }} text-sm"></i>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-white font-medium leading-tight">{{ $meta['label'] }}</span>
                                <span class="block text-[11px] text-white/45 mt-0.5 leading-snug">{{ $meta['description'] }}</span>
                            </span>

                            {{-- Switch --}}
                            <span class="relative h-6 w-11 shrink-0 rounded-full transition"
                                  :class="on ? 'bg-blue-500' : 'bg-white/15'">
                                <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                      :class="on ? 'translate-x-5' : 'translate-x-0'"></span>
                            </span>
                        </label>

                        @if($isSelect)
                            <div x-show="on" class="px-3.5 pb-3 -mt-1">
                                <button type="button" @click.prevent="open = !open"
                                        class="inline-flex items-center gap-1.5 text-[11px] text-blue-300 hover:text-blue-200 px-2.5 py-1 rounded-lg bg-blue-500/10 border border-blue-400/20 transition">
                                    <i class="fas text-[10px]" :class="open ? 'fa-chevron-up' : 'fa-sliders'"></i>
                                    <span x-text="open ? 'Hide items' : 'Choose specific'"></span>
                                </button>
                            </div>

                            <div x-show="on && open" x-cloak class="border-t border-white/10 bg-black/20 p-3.5">
                                <p class="text-[11px] text-white/40 mb-2.5">Pick the {{ strtolower($meta['label']) }} to use. Select none to use all of them.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-60 overflow-y-auto pr-1">
                                    @foreach($sourceItems as $item)
                                        <label class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1.5 cursor-pointer hover:bg-white/[0.06] has-[:checked]:border-blue-400/50 has-[:checked]:bg-blue-500/[0.12] transition">
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
        <section class="ms-step rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="ms-badge grid place-items-center h-9 w-9 shrink-0 rounded-xl text-sm font-bold text-white">2</span>
                <div class="min-w-0">
                    <h2 class="text-white font-semibold text-base">Your goal</h2>
                    <p class="text-xs text-white/50 mt-1 leading-relaxed">Describe the outcome you're chasing. The clearer the target, the sharper the plan.</p>
                </div>
            </div>
            <textarea name="goal" rows="3" maxlength="4000" required
                      placeholder="e.g. Grow my newsletter subscribers and drive more clicks to my link-in-bio over the next month."
                      class="w-full mt-4 bg-white/5 border border-white/10 rounded-xl px-3.5 py-3 text-white text-sm placeholder-white/30 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-400/70 focus:outline-none transition resize-y">{{ old('goal', $old['goal'] ?? '') }}</textarea>
            @error('goal')<p class="text-xs text-red-300 mt-1.5">{{ $message }}</p>@enderror
        </section>

        {{-- Parameters --}}
        <section class="ms-step rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-6 space-y-7">
            <div class="flex items-start gap-3.5">
                <span class="ms-badge grid place-items-center h-9 w-9 shrink-0 rounded-xl text-sm font-bold text-white">3</span>
                <div class="min-w-0">
                    <h2 class="text-white font-semibold text-base">Parameters <span class="text-xs font-normal text-white/40">(optional)</span></h2>
                    <p class="text-xs text-white/50 mt-1 leading-relaxed">The more you give, the sharper the plan. A region biases the plan toward locally-relevant channels.</p>
                </div>
            </div>

            {{-- Budget & market --}}
            <div>
                <h3 class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-white/50 font-semibold mb-3">
                    <span class="ms-subhead-icon grid place-items-center h-6 w-6 rounded-lg text-blue-300"><i class="fas fa-coins text-[11px]"></i></span>
                    Budget &amp; market
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Budget</label>
                        <input type="text" name="parameters[budget]" maxlength="120" value="{{ $params['budget'] ?? '' }}"
                               placeholder="e.g. 200 / month" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Currency</label>
                        <input type="text" name="parameters[currency]" maxlength="40" value="{{ $params['currency'] ?? '' }}"
                               placeholder="e.g. USD, EUR, INR" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Region / geographic market</label>
                        <input type="text" name="parameters[region]" maxlength="160" value="{{ $params['region'] ?? '' }}"
                               placeholder="e.g. Austin, Texas · India · Southeast Asia" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Target audience</label>
                        <input type="text" name="parameters[audience]" maxlength="300" value="{{ $params['audience'] ?? '' }}"
                               placeholder="e.g. fitness creators in the US" class="{{ $inputCls }}">
                    </div>
                </div>
            </div>

            <div class="border-t border-white/[0.07]"></div>

            {{-- Timing & voice --}}
            <div>
                <h3 class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-white/50 font-semibold mb-3">
                    <span class="ms-subhead-icon grid place-items-center h-6 w-6 rounded-lg text-blue-300"><i class="fas fa-clock text-[11px]"></i></span>
                    Timing &amp; voice
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Timeframe</label>
                        <input type="text" name="parameters[timeframe]" maxlength="120" value="{{ $params['timeframe'] ?? '' }}"
                               placeholder="e.g. 4 weeks" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Posting cadence</label>
                        <input type="text" name="parameters[cadence]" maxlength="120" value="{{ $params['cadence'] ?? '' }}"
                               placeholder="e.g. 3 posts / week" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Tone</label>
                        <input type="text" name="parameters[tone]" maxlength="120" value="{{ $params['tone'] ?? '' }}"
                               placeholder="e.g. friendly and bold" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Brand voice</label>
                        <input type="text" name="parameters[brand_voice]" maxlength="300" value="{{ $params['brand_voice'] ?? '' }}"
                               placeholder="e.g. expert but approachable, no jargon" class="{{ $inputCls }}">
                    </div>
                </div>
            </div>

            <div class="border-t border-white/[0.07]"></div>

            {{-- Positioning --}}
            <div>
                <h3 class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-white/50 font-semibold mb-3">
                    <span class="ms-subhead-icon grid place-items-center h-6 w-6 rounded-lg text-blue-300"><i class="fas fa-crosshairs text-[11px]"></i></span>
                    Positioning
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Main offer / product</label>
                        <input type="text" name="parameters[main_offer]" maxlength="300" value="{{ $params['main_offer'] ?? '' }}"
                               placeholder="e.g. paid newsletter, $9/mo coaching" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-xs text-white/50 mb-1.5">Competitors</label>
                        <input type="text" name="parameters[competitors]" maxlength="400" value="{{ $params['competitors'] ?? '' }}"
                               placeholder="e.g. @rival1, @rival2" class="{{ $inputCls }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-white/50 mb-1.5">Things to avoid</label>
                        <input type="text" name="parameters[avoid]" maxlength="400" value="{{ $params['avoid'] ?? '' }}"
                               placeholder="e.g. no paid ads, avoid X/Twitter" class="{{ $inputCls }}">
                    </div>
                </div>
            </div>

            <div class="border-t border-white/[0.07]"></div>

            {{-- Channels & formats --}}
            <div>
                <h3 class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-white/50 font-semibold mb-3">
                    <span class="ms-subhead-icon grid place-items-center h-6 w-6 rounded-lg text-blue-300"><i class="fas fa-share-nodes text-[11px]"></i></span>
                    Channels &amp; formats
                </h3>
                <div class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-white/50 mb-1.5">Plan focus</label>
                            <select name="parameters[plan_type]" class="{{ $inputCls }}">
                                @foreach(['both' => 'Both organic & paid', 'organic' => 'Organic only', 'paid' => 'Paid only'] as $val => $label)
                                    <option value="{{ $val }}" @selected(($params['plan_type'] ?? 'both') === $val) class="bg-slate-800">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-white/50 mb-1.5">Preferred channels (free text)</label>
                            <input type="text" name="parameters[channels]" maxlength="300" value="{{ $params['channels'] ?? '' }}"
                                   placeholder="e.g. Instagram, TikTok, email" class="{{ $inputCls }}">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-white/50 mb-2.5">Content types</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($contentTypes as $ct)
                                <label class="group inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 cursor-pointer transition hover:bg-white/[0.06] has-[:checked]:border-blue-400/60 has-[:checked]:bg-blue-500/[0.15]">
                                    <input type="checkbox" name="parameters[content_types][]" value="{{ $ct }}"
                                           @checked(in_array($ct, $pickedContent, true)) class="peer sr-only">
                                    <i class="fas fa-check text-[10px] text-blue-300 w-0 -ml-1 opacity-0 transition-all peer-checked:w-auto peer-checked:ml-0 peer-checked:opacity-100"></i>
                                    <span class="text-xs text-white/75">{{ $ct }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-white/50 mb-2.5">Paid media channels <span class="text-white/30">(incl. local &amp; digital newspapers)</span></label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($paidMedia as $pm)
                                <label class="group inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 cursor-pointer transition hover:bg-white/[0.06] has-[:checked]:border-blue-400/60 has-[:checked]:bg-blue-500/[0.15]">
                                    <input type="checkbox" name="parameters[paid_media][]" value="{{ $pm }}"
                                           @checked(in_array($pm, $pickedPaid, true)) class="peer sr-only">
                                    <i class="fas fa-check text-[10px] text-blue-300 w-0 -ml-1 opacity-0 transition-all peer-checked:w-auto peer-checked:ml-0 peer-checked:opacity-100"></i>
                                    <span class="text-xs text-white/75">{{ $pm }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Footer action bar --}}
        <div class="sticky bottom-4 z-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.05] backdrop-blur-xl p-4 shadow-xl shadow-black/30">
                <div class="text-xs text-white/50 flex items-center gap-2 flex-wrap" id="ms-estimate">
                    <button type="button" id="ms-estimate-btn"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white/75 hover:bg-white/10 hover:text-white text-xs font-medium transition">
                        <i class="fas fa-calculator text-[11px]"></i> Estimate cost
                    </button>
                    <span id="ms-estimate-out" class="text-white/60"></span>
                </div>
                <button type="submit" id="ms-submit"
                        class="ms-generate inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-white text-sm font-semibold transition">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate strategy
                </button>
            </div>
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
