@extends('user.layouts.app')
@section('title', 'AI Persona Generator')

@section('content')
<div class="persona-page w-full">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'AI Persona Generator',
        'subtitle' => 'Describe your audience, get a profile to anchor your copy.',
        'balance'  => $balance,
    ])

    @if(session('status'))
        <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.05] px-4 py-2 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-500/20 bg-red-500/[0.05] px-4 py-2 text-sm text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <details class="persona-shortcuts rounded-2xl border border-white/10 p-5 mb-6" aria-labelledby="persona-start-title">
        <summary id="persona-start-title" class="persona-gallery-summary"><span><strong>Choose a persona brief</strong><small>54 starting points · Sales, support &amp; startups</small></span><span>Browse briefs <i class="fas fa-chevron-down" aria-hidden="true"></i></span></summary>
        <p class="text-sm text-white/50 mt-1">Choose a starting point, adjust the details, then generate. Shortcuts do not spend coins.</p>
        <div class="persona-gallery-toolbar"><label class="persona-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="persona-preset-search" aria-label="Search persona briefs" placeholder="Search persona briefs…"></label><span id="persona-preset-count" class="text-xs text-white/50">54 starting points</span></div>
        <div class="persona-preset-grid">
        @foreach(config('persona_briefs') as $preset)
            <button type="button" aria-pressed="false" class="persona-preset" data-persona-preset='{{ json_encode($preset, JSON_HEX_APOS | JSON_HEX_QUOT) }}'><i class="fas {{ $preset['icon'] }}" aria-hidden="true"></i><span class="persona-preset-copy"><small>{{ $preset['category'] ?? (in_array($preset['name'], ['Startup founders', 'Local business']) ? 'Business' : 'Audience') }}</small><strong>{{ $preset['name'] }}</strong></span><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
        @endforeach
        </div>
        <p id="persona-preset-empty" hidden class="text-sm text-white/50 mt-3">No matching briefs. Try another search or write your own audience below.</p>
        <p id="persona-preset-status" role="status" class="text-xs text-white/50 mt-3">All suggestions are editable.</p>
    </details>

    <form method="POST" action="{{ route('user.ai.persona.generate') }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        @csrf
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Audience *</label>
            <input type="text" name="audience" id="persona-audience" required maxlength="400"
                   value="{{ old('audience', $input['audience'] ?? '') }}"
                   placeholder="e.g. Solo SaaS founders launching their first paid app"
                   class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
            @error('audience')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Goals (optional)</label>
            <textarea name="goals" rows="2" maxlength="600"
                      placeholder="What do you want this persona to help you do?"
                      class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">{{ old('goals', $input['goals'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Preferred tone (optional)</label>
            <input type="text" name="tone" maxlength="200"
                   value="{{ old('tone', $input['tone'] ?? '') }}"
                   placeholder="e.g. friendly, expert, no jargon"
                   class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
        </div>

        <div>
            <label for="persona-instructions" class="text-xs uppercase tracking-wider text-white/40">AI instructions (optional)</label>
            <textarea id="persona-instructions" name="instructions" rows="3" maxlength="2000" class="w-full mt-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" placeholder="e.g. Focus on Indian small businesses. Include buying objections and sample messaging. Clearly label assumptions; avoid invented demographics.">{{ old('instructions', $input['instructions'] ?? '') }}</textarea>
            @error('instructions')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
            <p class="text-xs text-white/40 mt-1">Tell the AI what to focus on, include or avoid.</p>
        </div>

        @include('user.ai._partials.mind-picker', [
            'mineMinds'     => $mineMinds,
            'platformMind'  => $platformMind,
            'selectedIds'   => old('mind_ids', $input['mind_ids'] ?? []),
            'platformOptIn' => old('include_platform', $input['include_platform'] ?? false),
        ])

        <div class="flex items-center justify-between gap-3 flex-wrap">
            @include('user.ai._partials.cost-estimate', ['cost' => [
                'feature' => 'persona',
                'mode'    => 'live',
                'input'   => '#persona-audience',
                'prefix'  => 'Up to',
            ]])
            <button class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Generate persona
            </button>
        </div>
    </form>

    @if($result)
        <div class="mt-6 rounded-2xl border border-blue-500/20 bg-blue-500/[0.05] p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-white/40 uppercase tracking-wider">Result</p>
                <p class="text-xs text-white/40">
                    {{ $result['model'] }} · {{ number_format($result['credits_spent']) }} ✦ spent
                </p>
            </div>
            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans">{{ $result['content'] }}</pre>

            @include('user.ai._partials.mind-breakdown', ['result' => $result])

            <form method="POST" action="{{ route('user.ai.persona.save') }}"
                  class="mt-4 flex flex-col sm:flex-row gap-2 sm:items-center border-t border-white/10 pt-4">
                @csrf
                <input type="text" name="name" required maxlength="120"
                       placeholder="Name this persona (e.g. Solo SaaS founder)"
                       class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                    Save to library
                </button>
                @error('name')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
            </form>
        </div>
    @endif

    <div class="mt-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-white/80 uppercase tracking-wider">Saved personas</h2>
            <span class="text-xs text-white/40">{{ $saved->count() }} saved</span>
        </div>

        @if($saved->isEmpty())
            <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-6 text-center text-sm text-white/50">
                You haven't saved any personas yet. Generate one above and save it to reuse later.
            </div>
        @else
            <ul class="space-y-3">
                @foreach($saved as $persona)
                    <li class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-white truncate">{{ $persona->name }}</p>
                                <p class="text-xs text-white/50 mt-0.5">
                                    Saved {{ $persona->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('user.ai.persona.show', ['from' => $persona->id]) }}"
                                   class="px-3 py-1.5 rounded-lg bg-blue-600/80 text-white text-xs font-semibold hover:bg-blue-600">
                                    Use in form
                                </a>
                                <button type="button"
                                        onclick="document.getElementById('persona-details-{{ $persona->id }}').classList.toggle('hidden')"
                                        class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-white/80 text-xs hover:bg-white/10">
                                    View
                                </button>
                                <form method="POST" action="{{ route('user.ai.persona.destroy', $persona) }}"
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this persona?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1.5 rounded-lg bg-red-600/20 border border-red-500/30 text-red-200 text-xs hover:bg-red-600/30">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div id="persona-details-{{ $persona->id }}" class="hidden mt-4 space-y-3">
                            <div class="rounded-xl bg-white/[0.02] border border-white/10 p-3 text-xs text-white/70 space-y-1">
                                <p><span class="text-white/40">Audience:</span> {{ $persona->audience }}</p>
                                @if($persona->goals)
                                    <p><span class="text-white/40">Goals:</span> {{ $persona->goals }}</p>
                                @endif
                                @if($persona->tone)
                                    <p><span class="text-white/40">Tone:</span> {{ $persona->tone }}</p>
                                @endif
                            </div>
                            <pre class="whitespace-pre-wrap text-sm text-white/90 font-sans bg-white/[0.02] border border-white/10 rounded-xl p-3">{{ $persona->content }}</pre>

                            <form method="POST" action="{{ route('user.ai.persona.update', $persona) }}"
                                  class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" required maxlength="120"
                                       value="{{ $persona->name }}"
                                       class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                                <button class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-white text-xs font-semibold hover:bg-white/20">
                                    Rename
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
var presetSearch=document.getElementById('persona-preset-search');
presetSearch.addEventListener('input',function(){var query=presetSearch.value.trim().toLowerCase(),count=0;document.querySelectorAll('[data-persona-preset]').forEach(function(card){var match=(card.textContent+' '+JSON.parse(card.dataset.personaPreset).audience).toLowerCase().includes(query);card.hidden=!match;if(match)count++;});document.getElementById('persona-preset-count').textContent=count+' starting points';document.getElementById('persona-preset-empty').hidden=count>0;});
document.querySelectorAll('[data-persona-preset]').forEach(function(button){button.addEventListener('click',function(){
var form=document.querySelector('form[action="{{ route('user.ai.persona.generate') }}"]'),preset=JSON.parse(button.dataset.personaPreset);
var changed=['audience','goals','tone'].some(function(name){return form.elements[name].value.trim();});
if(changed&&!window.confirm('Replace audience, goals and tone with this starting point? Your AI instructions and knowledge selection will be kept.'))return;
['audience','goals','tone'].forEach(function(name){form.elements[name].value=preset[name];form.elements[name].dispatchEvent(new Event('input',{bubbles:true}));});
document.querySelectorAll('[data-persona-preset]').forEach(function(b){b.setAttribute('aria-pressed',b===button?'true':'false');});document.getElementById('persona-preset-status').textContent=preset.name+' brief selected. Review the details below, then generate.';form.elements.audience.focus();
});});
</script>
@endpush
<style>
.persona-page{max-width:none;margin:0;padding:0}.persona-preset-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}.persona-preset{display:flex;align-items:center;gap:12px;text-align:left;padding:18px;border:1px solid var(--border-color,rgba(128,128,128,.2));border-radius:14px;background:rgba(99,102,241,.04);color:var(--text-primary,#fff);font-size:14px;font-weight:600}.persona-preset>i:first-child{color:#6478ef;background:rgba(99,102,241,.12);padding:12px;border-radius:12px}.persona-preset>i:last-child{margin-left:auto;color:#8d95aa;font-size:12px}.persona-preset:hover,.persona-preset[aria-pressed=true]{border-color:#6478ef;background:rgba(99,102,241,.1)}.persona-page form>button,.persona-page button[type=submit]{border-radius:12px}@media(max-width:1100px){.persona-preset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.persona-preset-grid{grid-template-columns:1fr}}
</style>

<style>
.persona-gallery-toolbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:20px}.persona-search{display:flex;align-items:center;gap:10px;border:1px solid var(--border-color,#dbe2ef);border-radius:12px;padding:10px 14px;max-width:420px;width:100%;color:#9299aa}.persona-search input{width:100%;background:transparent;border:0!important;outline:0;color:var(--text-primary,inherit);font-size:14px}.persona-preset-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.persona-preset{align-items:flex-start;min-height:154px;transition:box-shadow .2s,border-color .2s,transform .2s;background:linear-gradient(140deg,rgba(99,102,241,.07),transparent)}.persona-preset:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(40,55,100,.08)}.persona-preset[hidden]{display:none}.persona-preset-copy{min-width:0}.persona-preset-copy small{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#8790aa;margin-bottom:5px}.persona-preset-copy strong{display:block;font-size:14px}.persona-preset-copy em{display:block;font-style:normal;font-size:12px;line-height:1.5;font-weight:400;color:var(--text-secondary,#9299aa);margin-top:7px}.persona-preset>i:last-child{margin-top:14px}@media(max-width:1100px){.persona-preset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.persona-preset-grid{grid-template-columns:1fr}.persona-gallery-toolbar{align-items:flex-start;flex-direction:column}}
</style>

<style>
.persona-gallery-summary{display:flex;align-items:center;justify-content:space-between;gap:16px;cursor:pointer;list-style:none;color:var(--text-primary,inherit)}.persona-gallery-summary::-webkit-details-marker{display:none}.persona-gallery-summary small{display:block;font-size:12px;font-weight:400;color:var(--text-secondary,#9299aa);margin-top:5px}.persona-gallery-summary>span:last-child{font-size:12px;color:#6478ef}.persona-shortcuts:not([open])>p{display:none}.persona-shortcuts[open] .persona-gallery-summary{margin-bottom:16px}.persona-preset-grid{grid-template-columns:repeat(4,minmax(0,1fr));max-height:420px;overflow:auto;padding:2px;gap:8px}.persona-preset{min-height:76px;padding:12px;align-items:center}.persona-preset>i:first-child{padding:9px}.persona-preset>i:last-child{margin-top:0}.persona-preset-copy strong{font-size:12px}.persona-preset-copy small{font-size:9px}@media(max-width:1100px){.persona-preset-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.persona-preset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.persona-preset-grid{grid-template-columns:1fr}.persona-gallery-summary{align-items:flex-start}}
</style>
