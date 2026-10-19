@extends('user.layouts.app')
@section('title', 'Competitor Biolink Teardown')

@section('content')
<style>
/* Full width page, readable form. The hero spans the page like every other
   header; the form itself keeps a measure, because a single URL field stretched
   across 1200px is harder to use, not easier. */
.teardown-page form,
.teardown-page > .glass{max-width:760px}
</style>
{{-- No max-width wrapper, matching Create Link and My Links. This page is
     reached from the Bulk tools card on Create Link, so arriving here used to
     mean the content jumped from full width to a 672px column with a bare
     arrow where the header had been. --}}
<div class="teardown-page" x-data="competitorTeardown()">
    @include('user.partials.page-hero', [
        'title'      => 'Competitor Biolink Teardown',
        'subtitle'   => "Paste a competitor's link-in-bio (or any page) URL. AI scores it on strengths, weaknesses, missing elements and CTA quality, then you can build a better version in one click.",
        'emblemIcon' => 'fa-magnifying-glass-chart',
        'back'       => route('user.links.create'),
        'chips'      => array_values(array_filter([
            // Only claimed when the engine is actually on.
            $engineOn ? ['text' => 'AI scored', 'icon' => 'fa-robot'] : ['text' => 'AI engine off', 'icon' => 'fa-plug-circle-xmark'],
            // Balance is not repeated here: the estimate badge prints it
            // beside the price, which is where it is actually being weighed.
        ])),
        'actions'    => [
            ['label' => 'Back to Create Link', 'url' => route('user.links.create'), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

    @if(session('error'))
        <div class="glass rounded-xl p-4 mb-5 border border-red-500/20 text-sm text-red-300">{{ session('error') }}</div>
    @endif

    @if(!$engineOn)
        <div class="glass rounded-2xl p-6 text-center">
            <i class="fas fa-robot text-3xl text-white/20 mb-3"></i>
            <p class="text-white/60 text-sm">The AI Engine is currently disabled. Please try again later.</p>
        </div>
    @else
    <form @submit.prevent="analyze" method="POST" action="{{ route('user.links.teardown.store') }}">
        @csrf
        <div class="glass rounded-2xl p-6 mb-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">Competitor URL <span class="text-red-400">*</span></label>
                <input type="text" name="url" x-model="url" required maxlength="2048" placeholder="https://competitor.com/@handle"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none transition-all">
                <p class="text-xs text-white/30 mt-1.5">We fetch the public page and analyze layout, copy, CTAs and structure, no login required, and we never charge you for a page we couldn't reach.</p>
            </div>
        </div>

        {{-- The shared estimate badge, same as Ask Coach, Persona, Minds and
             the resume tools. It replaces a box that showed the balance but
             never the price, which is the half that decides whether to press
             the button. 'fixed' rather than 'live': the prompt is dominated by
             the competitor page, which has not been fetched yet, so a number
             driven by the URL field would be pricing the URL. --}}
        <div class="glass rounded-2xl p-4 mb-5 text-sm">
            @include('user.ai._partials.cost-estimate', ['cost' => [
                'feature' => 'competitor_teardown',
                'mode'    => 'fixed',
                'prefix'  => 'Up to',
                'note'    => 'per teardown',
            ]])
        </div>

        <p class="text-sm text-red-400 mb-3" x-show="error" x-text="error"></p>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" :disabled="!url.trim() || submitting"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20">
                <i class="fas fa-spinner fa-spin mr-1.5" x-show="submitting"></i>
                <i class="fas fa-magnifying-glass-chart mr-1.5 text-xs" x-show="!submitting"></i>
                <span x-text="submitting ? 'Analyzing…' : 'Run teardown'"></span>
            </button>
        </div>
    </form>
    @endif

    @if($recent->isNotEmpty())
    <div class="mt-8">
        <h2 class="text-sm font-medium text-white/50 mb-3">Recent teardowns</h2>
        <div class="space-y-2">
            @foreach($recent as $t)
                <a href="{{ route('user.links.teardown.show', $t) }}" class="glass rounded-xl px-4 py-3 flex items-center justify-between hover:bg-white/[0.06] transition-colors group">
                    <div class="min-w-0">
                        <p class="text-sm text-white truncate">{{ $t->competitor_url }}</p>
                        <p class="text-xs text-white/30 mt-0.5">{{ $t->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 ml-3">
                        @if($t->status === 'completed')
                            <span class="text-xs font-semibold text-emerald-300">{{ $t->analysis['overall_score'] ?? '—' }}/100</span>
                        @elseif($t->status === 'failed')
                            <span class="text-xs text-red-400">Failed</span>
                        @else
                            <span class="text-xs text-white/40">Processing…</span>
                        @endif
                        <i class="fas fa-chevron-right text-white/20 text-xs group-hover:text-white/40 transition-colors"></i>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>

@if($engineOn)
<script>
function competitorTeardown() {
    return {
        url: '',
        submitting: false,
        error: '',
        analyze() {
            this.submitting = true;
            this.error = '';
            this.$el.submit();
        },
    };
}
</script>
@endif
@endsection
