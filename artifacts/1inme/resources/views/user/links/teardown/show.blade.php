@extends('user.layouts.app')
@section('title', 'Competitor Biolink Teardown')

@section('content')
<style>
/* Scores and meters only. Everything else is the shared glass/rounded idiom. */
.td-gauge{position:relative;width:104px;height:104px;flex:0 0 auto}
.td-gauge svg{transform:rotate(-90deg);display:block}
.td-gauge-val{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1}
.td-gauge-num{font-size:30px;font-weight:800;letter-spacing:-.02em}
.td-gauge-of{font-size:10px;opacity:.45;margin-top:3px}

/* One card per finding group. The grid gives three across on a wide screen and
   collapses on its own -- the page is full width now, and two 600px columns of
   six-word bullets read worse than three. */
.td-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
@media(max-width:1100px){.td-groups{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.td-groups{grid-template-columns:1fr}}

.td-list{display:flex;flex-direction:column;gap:10px;margin:0;padding:0;list-style:none}
.td-list li{display:flex;gap:10px;font-size:13px;line-height:1.55}
.td-dot{flex:0 0 auto;width:6px;height:6px;border-radius:50%;margin-top:7px}

.td-meter{height:8px;border-radius:999px;overflow:hidden;background:rgba(255,255,255,.08)}
html.light-mode .td-meter{background:rgba(15,23,42,.08)}
.td-meter-fill{height:100%;border-radius:999px}

.td-count{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:rgba(255,255,255,.08)}
html.light-mode .td-count{background:rgba(15,23,42,.06)}

/* "How Sayzio closes these gaps". Cards, because each one is a destination,
   and a link that looks like a list item does not get clicked. */
.td-fixes{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
@media(max-width:1100px){.td-fixes{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.td-fixes{grid-template-columns:1fr}}
.td-fix{display:flex;align-items:flex-start;gap:11px;padding:14px;border-radius:16px;
  border:1px solid rgba(255,255,255,.1);text-decoration:none;transition:border-color .15s,background .15s,transform .15s}
html.light-mode .td-fix{border-color:rgba(15,23,42,.12)}
a.td-fix:hover{border-color:rgba(96,165,250,.55);background:rgba(59,130,246,.06);transform:translateY(-1px)}
a.td-fix:focus-visible{outline:2px solid #60a5fa;outline-offset:2px}
.td-fix-ico{flex:0 0 auto;width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;
  background:rgba(59,130,246,.14);color:#60a5fa;font-size:13px}
.td-fix strong{display:block;font-size:13px;font-weight:600}
.td-fix em{display:block;font-style:normal;font-size:11.5px;opacity:.55;margin-top:2px;line-height:1.45}
.td-fix-note{opacity:.4!important;margin-top:5px!important}
.td-fix-go{margin-left:auto;align-self:center;font-size:11px;opacity:.3}
.td-fix-waiting{opacity:.62}

/* Build-style chooser. Two cards, not a bare radio pair: the choice changes
   what the page will look like, so it deserves the room to say so. */
.td-styles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:18px}
@media(max-width:620px){.td-styles{grid-template-columns:1fr}}
.td-style{display:flex;gap:10px;align-items:flex-start;padding:14px;border-radius:16px;cursor:pointer;
  border:1px solid rgba(255,255,255,.1);transition:border-color .15s,background .15s}
html.light-mode .td-style{border-color:rgba(15,23,42,.12)}
.td-style:hover{border-color:rgba(96,165,250,.5)}
.td-style:has(input:checked){border-color:#3b82f6;background:rgba(59,130,246,.07)}
.td-style:focus-within{outline:2px solid #60a5fa;outline-offset:2px}
.td-style input{margin-top:3px;flex:0 0 auto;accent-color:#3b82f6}
.td-style strong{display:block;font-size:13px;font-weight:600}
.td-style em{display:block;font-style:normal;font-size:11.5px;opacity:.55;margin-top:2px;line-height:1.45}
.td-swatches{display:flex;gap:4px;margin-top:8px}
.td-swatches i{width:14px;height:14px;border-radius:4px;display:block;border:1px solid rgba(127,127,127,.35)}
</style>
<div class="teardown-show" x-data="teardownBuild()">
    @php
        $score    = (int) ($analysis['overall_score'] ?? 0);
        $ctaScore = (int) ($analysis['cta']['quality_score'] ?? 0);
        // One scale, used by the gauge, the meter and the chip, so a number
        // never wears two different colours on the same page.
        $band = static fn (int $n): array => $n >= 70
            ? ['#34d399', 'text-emerald-300', 'fa-circle-check']
            : ($n >= 40 ? ['#fbbf24', 'text-amber-300', 'fa-circle-exclamation']
                        : ['#f87171', 'text-red-300', 'fa-circle-xmark']);
        [$scoreHex, $scoreText, $scoreIcon] = $band($score);
        [$ctaHex]                           = $band($ctaScore);

        $strengths = array_values(array_filter((array) ($analysis['strengths'] ?? [])));
        $weak      = array_values(array_filter((array) ($analysis['weaknesses'] ?? [])));
        $missing   = array_values(array_filter((array) ($analysis['missing_elements'] ?? [])));
        $recs      = array_values(array_filter((array) ($analysis['recommendations'] ?? [])));

        $done = $teardown->status === 'completed';
    @endphp

    @include('user.partials.page-hero', [
        'title'      => 'Teardown Results',
        'emblemIcon' => 'fa-magnifying-glass-chart',
        'url'        => $teardown->competitor_url,
        'back'       => route('user.links.teardown.create'),
        'chips'      => array_values(array_filter([
            // Only the numbers the analysis actually returned.
            $done ? ['text' => $score . '/100 overall', 'icon' => $scoreIcon] : null,
            $done ? ['text' => $ctaScore . '/100 CTA', 'icon' => 'fa-bullseye'] : null,
            $teardown->status === 'processing' ? ['text' => 'Analysing', 'icon' => 'fa-spinner fa-spin'] : null,
            $teardown->status === 'failed' ? ['text' => 'Failed', 'icon' => 'fa-triangle-exclamation'] : null,
            $teardown->built_link_id ? ['text' => 'Better version built', 'icon' => 'fa-wand-magic-sparkles'] : null,
        ])),
        'actions'    => [
            ['label' => 'Run another teardown', 'url' => route('user.links.teardown.create'), 'icon' => 'fa-rotate-right', 'class' => 'btn-ghost'],
        ],
    ])

    @if(session('error'))
        <div class="glass rounded-xl p-4 mb-5 border border-red-500/20 text-sm text-red-300">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="glass rounded-xl p-4 mb-5 border border-emerald-500/20 text-sm text-emerald-300">{{ session('success') }}</div>
    @endif

    @if($teardown->status === 'processing')
        <div class="glass rounded-2xl p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-blue-300 mb-3"></i>
            <p class="text-white/60 text-sm">Still analyzing this page, refresh in a moment.</p>
        </div>
    @elseif($teardown->status === 'failed')
        <div class="glass rounded-2xl p-8 text-center">
            <i class="fas fa-triangle-exclamation text-2xl text-red-400 mb-3"></i>
            <p class="text-white/60 text-sm">{{ $teardown->error ?: "We couldn't analyze this page." }}</p>
            <a href="{{ route('user.links.teardown.create') }}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">Try another URL</a>
        </div>
    @else
        {{-- The verdict band: the two scores the analysis produced, side by
             side, so the headline number and the CTA number are read together
             rather than one at the top of the page and one two screens down. --}}
        <div class="glass rounded-2xl p-6 mb-5">
            <div class="flex items-start gap-6 flex-wrap">
                <div class="td-gauge">
                    <svg width="104" height="104" viewBox="0 0 104 104" aria-hidden="true">
                        <circle cx="52" cy="52" r="46" fill="none" stroke="currentColor" stroke-opacity=".12" stroke-width="9"/>
                        <circle cx="52" cy="52" r="46" fill="none" stroke="{{ $scoreHex }}" stroke-width="9" stroke-linecap="round"
                                stroke-dasharray="{{ round(2 * M_PI * 46, 2) }}"
                                stroke-dashoffset="{{ round(2 * M_PI * 46 * (1 - min(100, max(0, $score)) / 100), 2) }}"/>
                    </svg>
                    <div class="td-gauge-val">
                        <span class="td-gauge-num" style="color:{{ $scoreHex }}">{{ $score }}</span>
                        <span class="td-gauge-of">out of 100</span>
                    </div>
                </div>

                <div class="min-w-0 flex-1" style="min-width:260px">
                    <p class="text-sm text-white/40 mb-1">Overall effectiveness</p>
                    <p class="text-white/80 text-sm mb-4">{{ $analysis['summary'] ?? '' }}</p>

                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs text-white/50"><i class="fas fa-bullseye mr-1.5"></i>Call-to-action quality</span>
                        <span class="text-xs font-semibold text-white">{{ $ctaScore }}/100</span>
                    </div>
                    <div class="td-meter mb-2">
                        <div class="td-meter-fill" style="width:{{ min(100, max(0, $ctaScore)) }}%;background:{{ $ctaHex }}"></div>
                    </div>
                    <p class="text-xs text-white/50">
                        {{ ($analysis['cta']['present'] ?? false) ? '' : 'No clear CTA detected. ' }}{{ $analysis['cta']['feedback'] ?? '' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- The three finding groups, one card each, counted in the heading so
             the shape of the verdict is readable before any bullet is. --}}
        <div class="td-groups mb-5">
            <div class="glass rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-emerald-300"><i class="fas fa-circle-check mr-1.5"></i>What works</h3>
                    <span class="td-count text-emerald-300">{{ count($strengths) }}</span>
                </div>
                <ul class="td-list">
                    @forelse($strengths as $item)
                        <li><span class="td-dot" style="background:#34d399"></span><span class="text-white/70">{{ $item }}</span></li>
                    @empty
                        <li><span class="text-xs text-white/30">None identified.</span></li>
                    @endforelse
                </ul>
            </div>

            <div class="glass rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-red-300"><i class="fas fa-circle-xmark mr-1.5"></i>What doesn't</h3>
                    <span class="td-count text-red-300">{{ count($weak) }}</span>
                </div>
                <ul class="td-list">
                    @forelse($weak as $item)
                        <li><span class="td-dot" style="background:#f87171"></span><span class="text-white/70">{{ $item }}</span></li>
                    @empty
                        <li><span class="text-xs text-white/30">None identified.</span></li>
                    @endforelse
                </ul>
            </div>

            <div class="glass rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-amber-300"><i class="fas fa-list-check mr-1.5"></i>What's missing</h3>
                    <span class="td-count text-amber-300">{{ count($missing) }}</span>
                </div>
                <ul class="td-list">
                    @forelse($missing as $item)
                        <li><span class="td-dot" style="background:#fbbf24"></span><span class="text-white/70">{{ $item }}</span></li>
                    @empty
                        <li><span class="text-xs text-white/30">Nothing obviously missing.</span></li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Recommendations last, because they are the thing you act on, and
             they sit directly above the button that acts on them. --}}
        @if(!empty($recs))
        <div class="glass rounded-2xl p-6 mb-5">
            <h3 class="text-sm font-semibold text-blue-300 mb-4"><i class="fas fa-lightbulb mr-1.5"></i>What to do about it</h3>
            <ol class="td-list">
                @foreach($recs as $i => $item)
                    <li>
                        <span class="shrink-0 text-xs font-semibold text-blue-300/80" style="min-width:1.4em">{{ $i + 1 }}.</span>
                        <span class="text-white/70">{{ $item }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
        @endif

        {{-- How Sayzio closes the gaps above.
             The model does not write a word of this: it picks keys from
             SayzioFixes, anything it invents is dropped before the analysis
             is stored, and every label and destination here is written by a
             person. A page with no recognised keys shows nothing at all,
             which is the right answer when nothing fits. --}}
        @if(!empty($sayzioFixes))
        <div class="glass rounded-2xl p-6 mb-5">
            <h3 class="text-sm font-semibold text-blue-300 mb-1"><i class="fas fa-wand-magic-sparkles mr-1.5"></i>How Sayzio closes these gaps</h3>
            <p class="text-xs text-white/40 mb-4">Each of these is something your account can already do.</p>
            <div class="td-fixes">
                @foreach($sayzioFixes as $fix)
                    @if($fix['url'])
                        <a href="{{ $fix['url'] }}" class="td-fix">
                            <span class="td-fix-ico"><i class="fas {{ $fix['icon'] }}"></i></span>
                            <span class="min-w-0">
                                <strong>{{ $fix['label'] }}</strong>
                                <em>{{ $fix['blurb'] }}</em>
                            </span>
                            <i class="fas fa-arrow-right td-fix-go"></i>
                        </a>
                    @else
                        <div class="td-fix td-fix-waiting">
                            <span class="td-fix-ico"><i class="fas {{ $fix['icon'] }}"></i></span>
                            <span class="min-w-0">
                                <strong>{{ $fix['label'] }}</strong>
                                <em>{{ $fix['blurb'] }}</em>
                                <em class="td-fix-note">{{ $fix['pending'] }}</em>
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

        @php
            // Only offer "match their style" when there is actually a palette
            // to match. A teardown run before palette extraction existed has
            // none, and an empty radio that silently does nothing is worse
            // than no radio.
            $palette    = is_array($teardown->extracted['palette'] ?? null) ? $teardown->extracted['palette'] : [];
            $swatches   = array_values(array_filter(array_merge(
                array_values(is_array($palette['roles'] ?? null) ? $palette['roles'] : []),
                is_array($palette['colors'] ?? null) ? $palette['colors'] : [],
            ), static fn ($c) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c)));
            $swatches   = array_slice(array_values(array_unique($swatches)), 0, 6);
            $canMatch   = $swatches !== [];
        @endphp
        <div class="glass rounded-2xl p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <p class="text-sm text-white font-medium"><i class="fas fa-wand-magic-sparkles text-blue-400 mr-1.5"></i>Ready to beat this page?</p>
                    <p class="text-xs text-white/40 mt-1">Builds a new biolink from these findings.</p>
                    {{-- The builder's own estimator, not a worst case: it
                         prices the exact description this button will send.
                         Quotes the "match" figure, the larger of the two, so
                         an "Up to" never comes in under the charge. --}}
                    <div class="mt-2">
                        @include('user.ai._partials.cost-estimate', ['cost' => [
                            'endpoint'     => route('user.links.teardown.build-estimate', $teardown),
                            'feature'      => 'biolink_builder',
                            'mode'         => 'fixed',
                            'prefix'       => 'Up to',
                            'note'         => 'to build this page',
                            'field'        => 'estimated_credits',
                            'balanceField' => 'balance',
                        ]])
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('user.links.teardown.build', $teardown) }}" @submit="building = true" class="mt-5">
                @csrf
                @if($canMatch)
                    <div class="td-styles">
                        <label class="td-style">
                            <input type="radio" name="style" value="original" checked>
                            <span>
                                <strong>Sayzio original</strong>
                                <em>Our visual direction, their gaps fixed.</em>
                            </span>
                        </label>
                        <label class="td-style">
                            <input type="radio" name="style" value="match">
                            <span>
                                <strong>Match their style</strong>
                                <em>Same palette as the page we analysed.</em>
                                <span class="td-swatches" aria-hidden="true">
                                    @foreach($swatches as $hex)
                                        <i style="background:{{ $hex }}" title="{{ $hex }}"></i>
                                    @endforeach
                                </span>
                            </span>
                        </label>
                    </div>
                @endif
                <div class="flex justify-end">
                <button type="submit" :disabled="building"
                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20 whitespace-nowrap">
                    <i class="fas fa-spinner fa-spin mr-1.5" x-show="building"></i>
                    <i class="fas fa-wand-magic-sparkles mr-1.5 text-xs" x-show="!building"></i>
                    <span x-text="building ? 'Building…' : 'Build me a better version'"></span>
                </button>
                </div>
            </form>
        </div>
    @endif
</div>

<script>
function teardownBuild() {
    return { building: false };
}
</script>
@endsection
