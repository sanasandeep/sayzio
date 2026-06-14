{{--
    Single source of truth for "where AI credits are spent".

    Coins bought with real money can be converted into AI credits, which
    are consumed by the OpenAI-powered features below. This list is shared
    by the public /pricing #coins section and the in-app /user/upgrade page
    so both stay accurate to the features that actually meter AI credits.

    Keep this list in sync with the metered features in
    App\Services\AI\* (each charges the signed-in user's own AI credit
    balance via OpenAiService / AiCreditService).

    Optional:
      $heading  — override the section heading.
--}}
@php
    $aiCreditFeatures = [
        ['icon' => 'fa-brain',            'name' => 'AI Minds',          'desc' => 'Train knowledge bases — ingestion & embeddings'],
        ['icon' => 'fa-user-astronaut',   'name' => 'AI Personas',       'desc' => 'Public chat agents & persona generation'],
        ['icon' => 'fa-robot',            'name' => 'AI Companions',     'desc' => 'Always-on chatbots on your biolink'],
        ['icon' => 'fa-headset',          'name' => 'Site Assistant',    'desc' => 'On-site help & support chat widget'],
        ['icon' => 'fa-microphone-lines', 'name' => 'Voice Assistant',   'desc' => 'Speech-to-text, replies & voice output'],
        ['icon' => 'fa-comments',         'name' => 'Ask Coach',         'desc' => 'Data-aware assistant for your account'],
        ['icon' => 'fa-id-card',          'name' => 'Card & Brochure Scanner', 'desc' => 'Extract contacts from images'],
        ['icon' => 'fa-file-lines',       'name' => 'AI Resume Tools',   'desc' => 'Import, tailoring & cover letters'],
    ];
@endphp

<div class="mt-8 max-w-4xl mx-auto rounded-2xl border border-violet-400/20 bg-violet-500/[0.04] p-5 sm:p-6">
    <div class="text-center mb-5">
        <div class="text-[11px] font-bold uppercase tracking-[.2em] text-violet-300 mb-1">
            <i class="fas fa-wand-magic-sparkles"></i> {{ $heading ?? 'Where your AI credits go' }}
        </div>
        <p class="text-sm text-white/60 max-w-xl mx-auto">
            Turn coins into AI credits, then spend them on these
            OpenAI-powered features. Calls you make draw on your own
            credit balance — you only pay for what you use.
        </p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach($aiCreditFeatures as $f)
            <div class="flex items-start gap-3 rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2.5">
                <div class="w-8 h-8 shrink-0 rounded-lg bg-violet-500/15 ring-1 ring-violet-400/30 flex items-center justify-center">
                    <i class="fas {{ $f['icon'] }} text-violet-300 text-sm"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-white leading-tight">{{ $f['name'] }}</div>
                    <div class="text-xs text-white/50 leading-snug">{{ $f['desc'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
