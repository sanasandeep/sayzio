{{--
    Single source of truth for "where coins are spent on AI".

    AI usage is charged directly from the coin wallet at call time — there
    is no separate AI-credit balance, exchange rate, or buyable packs. This
    list is shared by the public /pricing #coins section and the in-app
    /user/upgrade page so both stay accurate to the features that actually
    meter coins.

    Keep this list in sync with the metered features in
    App\Services\AI\* (each charges the signed-in user's own coin wallet
    via OpenAiService / AiUsageCharger).

    Optional:
      $heading  — override the section heading.
--}}
@php
    $aiCoinFeatures = [
        ['icon' => 'fa-brain',            'name' => 'AI Minds','desc' => 'Train AI Minds for ingestion and embeddings'],
        ['icon' => 'fa-user-astronaut',   'name' => 'AI Agents',         'desc' => 'Public chat agents & persona generation'],
        ['icon' => 'fa-robot',            'name' => 'Chat Widgets',      'desc' => 'Always-on chatbots on your biolink'],
        ['icon' => 'fa-headset',          'name' => 'Site Assistant',    'desc' => 'On-site help & support chat widget'],
        ['icon' => 'fa-microphone-lines', 'name' => 'AI Voice Assistant','desc' => 'Speech-to-text, replies & voice output'],
        ['icon' => 'fa-comments',         'name' => 'AI Coach',          'desc' => 'Data-aware assistant for your account'],
        ['icon' => 'fa-id-card',          'name' => 'Card & Brochure Scanner', 'desc' => 'Extract contacts from images'],
        ['icon' => 'fa-file-lines',       'name' => 'AI Resume Tools',   'desc' => 'Import, tailoring & cover letters'],
        ['icon' => 'fa-chart-pie',        'name' => 'Audience Insights', 'desc' => 'AI visitor-type estimation for any link'],
    ];
@endphp

{{-- Rebuilt on the marketing system. What changed and why, against
     Sana's rules: the blue eyebrow, the blue "straight from your coin
     wallet" and the grey-on-grey descriptions are all ink now (1); the
     tinted blue panel and the tinted blue callout inside it are a bordered
     card and a hairline row (2); the nine blue icon tiles are nine quiet
     glyphs (7); and the list is the system grid (5). --}}
<div class="sy-card mt-8 max-w-4xl mx-auto">
    <div class="text-center" style="max-width: 34rem; margin-inline: auto;">
        <div class="sy-eyebrow">{{ $heading ?? 'Where your coins go on AI' }}</div>
        <p class="sy-blurb" style="margin-top: 6px;">
            Spend coins directly on these OpenAI-powered features. Each call
            draws on your own coin balance; you only pay for what you use,
            with no separate credits to buy or convert.
        </p>
    </div>

    {{-- The reassurance line. It was a tinted panel inside a tinted panel;
         a rule above and below says the same thing and stays quiet. --}}
    <div class="sy-cells" style="margin-top: 22px;">
        <div class="sy-cell">
            <i class="fas fa-coins" aria-hidden="true"></i>
            <span class="sy-cell-body">
                <b>Billed straight from your coin wallet</b>
                <span class="sy-cell-sub">No separate AI credits to buy, and nothing to convert. You pay only for what you use.</span>
            </span>
        </div>
    </div>

    <div class="sy-grid" style="--sy-min: 240px; gap: 0 28px; margin-top: 4px;">
        @foreach($aiCoinFeatures as $f)
            <div class="sy-cell">
                <i class="fas {{ $f['icon'] }}" aria-hidden="true"></i>
                <span class="sy-cell-body">
                    <b>{{ $f['name'] }}</b>
                    <span class="sy-cell-sub">{{ $f['desc'] }}</span>
                </span>
            </div>
        @endforeach
    </div>
</div>
