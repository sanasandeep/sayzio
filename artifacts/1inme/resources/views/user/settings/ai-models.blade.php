@extends('user.layouts.settings')
@section('title', 'AI Models')
@section('settings-content')
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">AI Models</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">
            Pick which AI model powers each feature for your account. Faster models cost fewer coins,
            stronger models produce richer results. Anything left on "Platform default" follows the
            model chosen by the Sayzio team.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(!$isPaid)
        <div class="rounded-2xl p-6 text-center" style="background: var(--bg-card); border:1px solid var(--border-soft);">
            <i class="fas fa-microchip text-2xl mb-3" style="color: var(--text-muted);"></i>
            <p class="text-sm font-semibold" style="color: var(--text-primary);">Per-feature model choice is a paid perk</p>
            <p class="text-xs mt-1 mb-4" style="color: var(--text-muted);">
                Upgrade to pick the exact AI model each feature uses — trade speed for quality
                (and coin cost) per feature. On the free plan everything runs on the platform defaults.
            </p>
            <a href="{{ route('user.upgrade') }}" class="inline-block px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: var(--color-primary-600, #2563eb);">
                See upgrade options
            </a>
        </div>
    @elseif(count($models) === 0)
        <div class="rounded-2xl p-6" style="background: var(--bg-card); border:1px solid var(--border-soft);">
            <p class="text-sm" style="color: var(--text-primary);">No selectable models right now.</p>
            <p class="text-xs mt-1" style="color: var(--text-muted);">All features are running on the platform defaults. Check back later.</p>
        </div>
    @else
        {{-- Model rate reference --}}
        <div class="rounded-2xl p-5 mb-5" style="background: var(--bg-card); border:1px solid var(--border-soft);">
            <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Available models &amp; coin rates</div>
            <p class="text-xs mb-3" style="color: var(--text-muted);">
                Coins charged per 1,000 tokens. "In" covers what you send (your prompt and context),
                "out" covers what the AI writes back. Each call is rounded up to whole coins.
            </p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($models as $m)
                    <div class="rounded-lg px-3 py-2 text-xs" style="background: var(--bg-soft, rgba(61,107,255,0.06));">
                        <div class="font-mono font-semibold" style="color: var(--text-primary);">{{ $m['name'] }}</div>
                        <div class="mt-0.5" style="color: var(--text-muted);">
                            In {{ rtrim(rtrim(number_format((float) $m['in_coins_per_1k'], 2), '0'), '.') }}
                            / Out {{ rtrim(rtrim(number_format((float) $m['out_coins_per_1k'], 2), '0'), '.') }} coins per 1k tokens
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('user.settings.ai-models.update') }}"
              class="rounded-2xl p-5 space-y-4"
              style="background: var(--bg-card); border:1px solid var(--border-soft);">
            @csrf
            @method('PUT')

            @php $modelNames = array_column($models, 'name'); @endphp

            <div class="divide-y" style="border-color: var(--border-soft);">
                @foreach($features as $f)
                    @php
                        $label    = ucwords(str_replace('_', ' ', $f));
                        $chosen   = $choices[$f] ?? '';
                        $platform = $platformModels[$f] ?? $defaultModel;
                        $stale    = $chosen !== '' && !in_array($chosen, $modelNames, true);
                    @endphp
                    <div class="py-3 first:pt-0 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $label }}</div>
                            <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                                Platform default: <span class="font-mono">{{ $platform }}</span>
                                @if($stale)
                                    <span class="block mt-0.5 text-amber-600">Your previous pick <span class="font-mono">{{ $chosen }}</span> is no longer available — the platform default is used until you choose again.</span>
                                @endif
                            </div>
                        </div>
                        <select name="feature_models[{{ $f }}]" class="text-sm rounded-lg px-2 py-2 sm:w-64"
                                style="background: var(--bg-soft, rgba(61,107,255,0.06)); color: var(--text-primary); border:1px solid var(--border-soft);">
                            <option value="" @selected($chosen === '' || $stale)>Platform default ({{ $platform }})</option>
                            @foreach($models as $m)
                                <option value="{{ $m['name'] }}" @selected($chosen === $m['name'])>{{ $m['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="pt-2 flex items-center justify-between gap-3">
                <button type="submit" form="ai-models-reset-form" class="text-xs underline" style="color: var(--text-muted);"
                        onclick="return confirm('Reset every feature back to the platform default model?');">
                    Reset all to platform defaults
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: var(--color-primary-600, #2563eb);">
                    Save model choices
                </button>
            </div>
        </form>

        <form id="ai-models-reset-form" method="POST" action="{{ route('user.settings.ai-models.reset') }}">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
@endsection
