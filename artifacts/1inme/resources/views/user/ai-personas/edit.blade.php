@extends('user.layouts.app')
@section('title', 'Edit AI Persona')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6"
     x-data="personaTester({
        endpoint: '{{ route('user.ai-personas.test', $persona) }}',
        greeting: @js($persona->greeting),
        starters: @js($persona->starter_questions ?? []),
     })">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <a href="{{ route('user.ai-personas.index') }}" class="text-xs text-white/50 hover:text-white"><i class="fas fa-arrow-left"></i> Back to Personas</a>
            <h1 class="text-2xl font-bold text-white mt-2">{{ $persona->name }}</h1>
            <p class="text-[11px] text-white/40">v{{ optional($persona->activeVersion)->revision ?? '—' }} &middot; AI credit balance: <span class="text-violet-300">{{ number_format($balance) }}</span></p>
            @if($persona->is_disabled)
                <p class="mt-2 text-xs text-red-300">This Persona is disabled by an administrator: {{ $persona->disabled_reason }}</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- ================= EDITOR ================= --}}
        <form method="POST" action="{{ route('user.ai-personas.update', $persona) }}" class="lg:col-span-2 space-y-5">
            @csrf @method('PUT')

            {{-- Identity --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                <h3 class="text-white font-semibold">Identity</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Name *</label>
                        <input type="text" name="name" required maxlength="120" value="{{ old('name', $persona->name) }}"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        @error('name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Avatar URL</label>
                        <input type="url" name="avatar_url" maxlength="1024" value="{{ old('avatar_url', $persona->avatar_url) }}"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">Short description</label>
                    <input type="text" name="description" maxlength="500" value="{{ old('description', $persona->description) }}"
                        class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                </div>
            </div>

            {{-- Voice --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                <h3 class="text-white font-semibold">Voice</h3>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">System prompt *</label>
                    <textarea name="system_prompt" required rows="6" maxlength="{{ $caps['max_system_prompt_chars'] }}"
                        class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm font-mono">{{ old('system_prompt', $persona->system_prompt) }}</textarea>
                    <p class="text-[10px] text-white/40 mt-1">Up to {{ number_format($caps['max_system_prompt_chars']) }} characters.</p>
                    @error('system_prompt')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Tone preset</label>
                        <select name="tone_preset" class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                            <option value="">— None —</option>
                            @foreach($tones as $t)
                                <option value="{{ $t }}" @selected(old('tone_preset', $persona->tone_preset) === $t)>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Languages (comma-separated)</label>
                        <input type="text" name="languages_csv" value="{{ old('languages_csv', implode(',', (array) $persona->languages)) }}"
                            placeholder="en, es, fr"
                            x-data x-on:input="$el.form.querySelectorAll('[data-langhidden]').forEach(e=>e.remove()); $el.value.split(',').map(s=>s.trim()).filter(Boolean).slice(0,10).forEach(v=>{ const i=document.createElement('input'); i.type='hidden'; i.name='languages[]'; i.value=v; i.dataset.langhidden='1'; $el.form.appendChild(i); })"
                            x-init="$el.dispatchEvent(new Event('input'))"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">Style guide</label>
                    <textarea name="style_guide" rows="3" maxlength="{{ $caps['max_style_guide_chars'] }}"
                        class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">{{ old('style_guide', $persona->style_guide) }}</textarea>
                </div>
            </div>

            {{-- Model knobs --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                <h3 class="text-white font-semibold">Model</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-1">
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Chat model *</label>
                        <select name="model" required class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                            @foreach($engineModels as $m)
                                <option value="{{ $m['name'] }}" @selected(old('model', $persona->model) === $m['name'])>{{ $m['name'] }} ({{ $m['in_coins_per_1k'] ?? 0 }}/{{ $m['out_coins_per_1k'] ?? 0 }} coins/1k)</option>
                            @endforeach
                        </select>
                        @error('model')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Temperature ({{ number_format($persona->temperature(), 2) }})</label>
                        <input type="number" name="temperature_x100" min="0" max="200" step="5"
                            value="{{ old('temperature_x100', $persona->temperature_x100) }}"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        <p class="text-[10px] text-white/40 mt-1">0 = deterministic, 100 = balanced, 200 = wild. Stored as ×100.</p>
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Max tokens / reply</label>
                        <input type="number" name="max_tokens" min="50" max="4000" step="50"
                            value="{{ old('max_tokens', $persona->max_tokens) }}"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    </div>
                </div>
            </div>

            {{-- Knowledge --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                <h3 class="text-white font-semibold">Knowledge (Minds)</h3>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="use_default_mind" value="0">
                    <input type="checkbox" name="use_default_mind" value="1" @checked(old('use_default_mind', $persona->use_default_mind))
                        class="rounded border-white/20 bg-white/5 text-pink-500">
                    <span class="text-sm text-white">
                        Use the platform default Mind
                        @if($defaultMind)
                            <span class="text-[10px] text-white/40">({{ $defaultMind->name }})</span>
                        @else
                            <span class="text-[10px] text-amber-300">(no default Mind configured yet)</span>
                        @endif
                    </span>
                </label>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">Your Minds (max {{ $caps['max_minds_per_persona'] }})</label>
                    @if($myMinds->isEmpty())
                        <p class="mt-2 text-xs text-white/40">You don't have any Minds yet.
                            <a href="{{ route('user.minds.index') }}" class="text-pink-300 hover:underline">Create one →</a>
                        </p>
                    @else
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">
                            @foreach($myMinds as $m)
                                <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02] cursor-pointer hover:bg-white/[0.05]">
                                    <input type="checkbox" name="mind_ids[]" value="{{ $m->id }}"
                                        @checked(in_array($m->id, old('mind_ids', $attachedIds), true))
                                        class="rounded border-white/20 bg-white/5 text-pink-500">
                                    <span class="text-sm text-white truncate">{{ $m->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Behaviour --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                <h3 class="text-white font-semibold">Behaviour</h3>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">When uncertain *</label>
                    <select name="fallback_behavior" required class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        @foreach($fallbacks as $f)
                            <option value="{{ $f }}" @selected(old('fallback_behavior', $persona->fallback_behavior) === $f)>{{ ucfirst($f) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <p class="text-[11px] uppercase tracking-wider text-white/50">Allowed actions</p>
                    @foreach($actionDefs as $key => $label)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="allowed_actions[{{ $key }}]" value="1"
                                @checked(old("allowed_actions.$key", ($persona->allowed_actions[$key] ?? false)))
                                class="rounded border-white/20 bg-white/5 text-pink-500">
                            <span class="text-sm text-white/80">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Greeting & CTA --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
                <h3 class="text-white font-semibold">Greeting &amp; CTA</h3>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">Opening greeting</label>
                    <textarea name="greeting" rows="2" maxlength="1000"
                        class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">{{ old('greeting', $persona->greeting) }}</textarea>
                </div>
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/50">Starter questions (max {{ $caps['max_starter_questions'] }})</label>
                    @for($i = 0; $i < $caps['max_starter_questions']; $i++)
                        <input type="text" name="starter_questions[]" maxlength="200"
                            value="{{ old('starter_questions.'.$i, ($persona->starter_questions[$i] ?? '')) }}"
                            placeholder="e.g. How do I get started?"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    @endfor
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Closing CTA label</label>
                        <input type="text" name="end_cta_label" maxlength="120" value="{{ old('end_cta_label', $persona->end_cta_label) }}"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider text-white/50">Closing CTA URL</label>
                        <input type="url" name="end_cta_url" maxlength="1024" value="{{ old('end_cta_url', $persona->end_cta_url) }}"
                            class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3">
                <label class="text-[11px] uppercase tracking-wider text-white/50">Version note (optional)</label>
                <input type="text" name="summary" maxlength="500" placeholder="What changed in this revision?"
                    class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                <div class="flex justify-end gap-2">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-sm">
                        Save as new version
                    </button>
                </div>
            </div>
        </form>

        {{-- ================= TEST + VERSIONS ================= --}}
        <div class="space-y-5">
            {{-- Test panel --}}
            <div class="rounded-2xl border border-pink-500/20 bg-pink-500/[0.04] p-5">
                <h3 class="text-white font-semibold flex items-center gap-2">
                    <i class="fas fa-flask text-pink-300"></i> Test panel
                </h3>
                <p class="text-[11px] text-white/50 mt-1">Hit a saved version of this Persona with the runtime visitors will use. Each turn spends AI credits.</p>

                <div class="mt-3 max-h-80 overflow-y-auto space-y-2 pr-1" x-ref="log">
                    <template x-if="!log.length">
                        <p class="text-xs text-white/40 italic">Send a message to start a test conversation.</p>
                    </template>
                    <template x-for="(t,i) in log" :key="i">
                        <div :class="t.role === 'user' ? 'text-right' : ''">
                            <div class="inline-block max-w-full text-left rounded-xl px-3 py-2 text-sm whitespace-pre-wrap"
                                 :class="t.role === 'user' ? 'bg-white/10 text-white' : 'bg-pink-500/10 text-pink-100 border border-pink-500/20'"
                                 x-text="t.content"></div>
                            <template x-if="t.meta">
                                <div class="text-[10px] text-white/40 mt-1">
                                    <span x-text="t.meta.model"></span> · <span x-text="t.meta.credits_spent + ' credits'"></span>
                                    · <span x-text="t.meta.tokens_in + '↑/' + t.meta.tokens_out + '↓ tokens'"></span>
                                </div>
                            </template>
                            <template x-if="t.citations && t.citations.length">
                                <div class="text-[10px] text-white/50 mt-1 space-y-0.5">
                                    <p class="text-white/40">Citations:</p>
                                    <template x-for="c in t.citations" :key="c.id">
                                        <p>· <span x-text="c.title"></span> <span class="text-white/30">(<span x-text="c.type"></span>, score <span x-text="c.score"></span>)</span></p>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="error">
                        <div class="text-xs text-red-300 bg-red-500/10 border border-red-500/20 rounded-xl px-3 py-2" x-text="error"></div>
                    </template>
                </div>

                <template x-if="starters.length && !log.length">
                    <div class="mt-3 flex flex-wrap gap-1">
                        <template x-for="s in starters" :key="s">
                            <button type="button" class="text-[11px] px-2 py-1 rounded-full bg-white/5 hover:bg-white/10 text-white/80 border border-white/10"
                                @click="message = s"
                                x-text="s"></button>
                        </template>
                    </div>
                </template>

                <form @submit.prevent="send()" class="mt-3 flex gap-2">
                    <input type="text" x-model="message" :disabled="busy" placeholder="Type a visitor question…"
                        class="flex-1 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                    <button :disabled="busy || !message.trim()"
                        class="px-3 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-sm disabled:opacity-50">
                        <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    </button>
                </form>
                <button type="button" @click="reset()" class="mt-2 text-[11px] text-white/40 hover:text-white">Clear conversation</button>
            </div>

            {{-- Version history --}}
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <h3 class="text-white font-semibold">Version history</h3>
                <p class="text-[11px] text-white/50 mt-1">Last {{ $caps['max_versions_per_persona'] }} revisions are kept.</p>
                <ul class="mt-3 divide-y divide-white/5 text-sm">
                    @foreach($persona->versions as $v)
                        <li class="py-2 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-white">v{{ $v->revision }}
                                    @if($v->id === $persona->active_version_id)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/20">active</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-white/40 truncate">{{ $v->summary ?: '—' }}</p>
                                <p class="text-[10px] text-white/30">{{ $v->created_at?->diffForHumans() }}</p>
                            </div>
                            @if($v->id !== $persona->active_version_id)
                                <form method="POST" action="{{ route('user.ai-personas.rollback', [$persona, $v]) }}"
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Roll back to v{{ $v->revision }}?', message: 'A new version will be written so you can roll forward again later.', confirmText: 'Roll back', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})">
                                    @csrf
                                    <button class="text-[11px] px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white">
                                        <i class="fas fa-undo"></i> Roll back
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function personaTester(opts) {
    return {
        endpoint: opts.endpoint,
        starters: opts.starters || [],
        message: '',
        log: [],
        busy: false,
        error: null,
        async send() {
            const msg = this.message.trim();
            if (!msg || this.busy) return;
            this.error = null;
            // Snapshot history *before* pushing the new user message —
            // the API expects prior turns only.
            const history = this.log
                .filter(t => t.role === 'user' || t.role === 'assistant')
                .map(t => ({ role: t.role, content: t.content }));
            this.log.push({ role: 'user', content: msg });
            this.message = '';
            this.busy = true;
            try {
                const res = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ message: msg, history }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.error = data.error || 'Request failed.';
                    if (data.top_up) this.error += ' Top up: ' + data.top_up;
                    return;
                }
                this.log.push({
                    role: 'assistant',
                    content: data.answer,
                    citations: data.citations || [],
                    meta: {
                        model: data.model,
                        credits_spent: data.credits_spent,
                        tokens_in: data.tokens_in,
                        tokens_out: data.tokens_out,
                    },
                });
            } catch (e) {
                this.error = e.message || 'Network error.';
            } finally {
                this.busy = false;
                this.$nextTick(() => { if (this.$refs.log) this.$refs.log.scrollTop = this.$refs.log.scrollHeight; });
            }
        },
        reset() { this.log = []; this.error = null; },
    };
}
</script>
@endsection
