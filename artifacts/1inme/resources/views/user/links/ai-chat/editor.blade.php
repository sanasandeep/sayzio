@extends('user.layouts.app')
@section('title', 'AI Chat - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .aic-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .aic-grid { grid-template-columns: minmax(0, 1fr); } }
    .aic-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        padding: 20px;
        margin-bottom: 16px;
        backdrop-filter: blur(20px);
        box-shadow: 0 4px 18px -8px rgba(15, 23, 42, 0.18);
    }
    .aic-card h5 { color: var(--text-primary); font-weight: 700; margin: 0 0 14px; font-size: 15px; }
    .aic-label { display:block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .aic-input, .aic-select, .aic-textarea {
        width: 100%; border: 1px solid var(--border-glass); border-radius: .75rem;
        background: var(--bg-glass-input); color: var(--text-primary);
        padding: 10px 12px; font-size: 14px; outline: none;
    }
    .aic-textarea { resize: vertical; min-height: 84px; }
    .aic-row { margin-bottom: 16px; }
    .aic-hint { font-size: 11px; color: var(--text-faint); margin-top: 6px; }
    .aic-toggle { display:flex; align-items:center; gap: 12px; padding: 12px 14px; border:1px solid var(--border-glass); border-radius: .85rem; background: var(--bg-glass-input); }
    .aic-toggle input { width: 18px; height: 18px; }
    .aic-banner { display:flex; gap:12px; align-items:center; padding: 14px 16px; border-radius: .85rem; margin-bottom: 16px; font-size: 13px; }
    .aic-banner.warn { background: rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color: var(--text-primary); }
    .aic-banner.info { background: rgba(124,58,237,.10); border:1px solid rgba(124,58,237,.30); color: var(--text-primary); }
    .aic-btn { display:inline-flex; align-items:center; gap:8px; padding: 10px 18px; border:0; border-radius: 999px; font-weight:600; font-size:14px; color:#fff; cursor:pointer; background: linear-gradient(135deg, #8b5cf6, #6366f1); }
    .aic-stat { display:flex; justify-content:space-between; font-size: 13px; padding: 8px 0; border-bottom: 1px dashed var(--border-glass); }
    .aic-stat:last-child { border-bottom: 0; }
    .aic-starter-rows { display:flex; flex-direction:column; gap: 8px; }
</style>

<div class="max-w-7xl mx-auto" x-data="aiChatEditor()">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'ai_chat'])

    @if(session('status'))
        <div class="aic-banner info"><i class="fas fa-check-circle"></i> {{ session('status') }}</div>
    @endif
    @if(!$aiEnabled)
        <div class="aic-banner warn">
            <i class="fas fa-triangle-exclamation"></i>
            AI is currently disabled for this workspace. You can still configure the page, but live answers won't run until AI is enabled.
        </div>
    @endif
    @error('persona_id')<div class="aic-banner warn"><i class="fas fa-circle-exclamation"></i> {{ $message }}</div>@enderror

    <form method="POST" action="{{ route('user.links.ai-chat.save', $link) }}">
        @csrf
        <div class="aic-grid">
            <div>
                <div class="aic-card">
                    <h5><i class="fas fa-robot text-[13px]" style="color:#a78bfa"></i> Chat identity</h5>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-name">Display name</label>
                        <input id="aic-name" class="aic-input" type="text" name="name" maxlength="120"
                               value="{{ old('name', $companion->name) }}" required>
                        <div class="aic-hint">Shown in the chat header on the public page.</div>
                    </div>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-persona">Persona (the brain)</label>
                        <select id="aic-persona" class="aic-select" name="persona_id" required x-model.number="personaId">
                            @foreach($personas as $p)
                                <option value="{{ $p->id }}" @selected((int) old('persona_id', $companion->persona_id) === (int) $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <div class="aic-hint">
                            The persona supplies the system prompt, model, tone and knowledge (Minds).
                            <a href="{{ route('user.ai-personas.index') }}" class="text-purple-400 no-underline">Manage personas &amp; knowledge →</a>
                        </div>
                    </div>
                </div>

                <div class="aic-card">
                    <input type="hidden" name="persona[apply]" :value="switched ? '0' : '1'">
                    <h5><i class="fas fa-brain text-[13px]" style="color:#a78bfa"></i> Personality &amp; knowledge</h5>

                    <div class="aic-banner info" x-show="switched" x-cloak style="margin-bottom:16px">
                        <i class="fas fa-circle-info"></i>
                        You picked a different persona. Save to bind it first — then its personality &amp; knowledge will load here for editing.
                    </div>

                    <div x-show="!switched">
                        <div class="aic-row">
                            <label class="aic-label" for="aic-system-prompt">System prompt</label>
                            <textarea id="aic-system-prompt" class="aic-textarea" name="persona[system_prompt]"
                                      maxlength="{{ $caps['max_system_prompt_chars'] }}" style="min-height:140px"
                                      placeholder="You are a friendly assistant for this page…">{{ old('persona.system_prompt', $persona->system_prompt) }}</textarea>
                            @error('persona.system_prompt')<div class="aic-hint" style="color:#f87171">{{ $message }}</div>@enderror
                            <div class="aic-hint">The core instructions that shape how the assistant thinks and replies.</div>
                        </div>
                        <div class="aic-row" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                            <div>
                                <label class="aic-label" for="aic-tone">Tone</label>
                                <select id="aic-tone" class="aic-select" name="persona[tone_preset]">
                                    <option value="">Default</option>
                                    @foreach($tones as $t)
                                        <option value="{{ $t }}" @selected(old('persona.tone_preset', $persona->tone_preset) === $t)>{{ ucfirst($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="aic-label" for="aic-fallback">When unsure</label>
                                @php $fallbackLabels = ['clarify' => 'Ask a clarifying question', 'escalate' => 'Offer to connect to a human', 'refuse' => 'Politely refuse &amp; explain limits']; @endphp
                                <select id="aic-fallback" class="aic-select" name="persona[fallback_behavior]">
                                    @foreach($fallbacks as $f)
                                        <option value="{{ $f }}" @selected(old('persona.fallback_behavior', $persona->fallback_behavior ?: 'clarify') === $f)>{!! $fallbackLabels[$f] ?? ucfirst($f) !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="aic-row">
                            <label class="aic-label">Knowledge (Minds)</label>
                            <label class="aic-toggle" style="margin-bottom:8px">
                                <input type="checkbox" name="persona[use_default_mind]" value="1"
                                       @checked(old('persona.use_default_mind', $persona->use_default_mind))>
                                <span>
                                    <strong style="color:var(--text-primary)">Use Sayzio's built-in knowledge</strong>
                                    @if($defaultMind)
                                        <span class="aic-hint" style="display:block;margin-top:2px">The shared "{{ $defaultMind->name }}" knowledge base.</span>
                                    @endif
                                </span>
                            </label>
                            @php $attached = old('persona.mind_ids', $attachedMindIds); @endphp
                            @forelse($myMinds as $mind)
                                <label class="aic-toggle" style="margin-bottom:8px">
                                    <input type="checkbox" name="persona[mind_ids][]" value="{{ $mind->id }}"
                                           @checked(in_array($mind->id, $attached))>
                                    <span><strong style="color:var(--text-primary)">{{ $mind->name }}</strong></span>
                                </label>
                            @empty
                                <div class="aic-hint">
                                    You don't have any custom knowledge bases yet.
                                    <a href="{{ route('user.minds.index') }}" class="text-purple-400 no-underline">Create one →</a>
                                </div>
                            @endforelse
                            <div class="aic-hint" style="margin-top:6px">Attach knowledge so the assistant can answer from your own content. Up to {{ $caps['max_minds_per_persona'] }} per persona.</div>
                        </div>
                        <div class="aic-hint">
                            Need model, temperature or version history?
                            <a href="{{ route('user.ai-personas.edit', $persona) }}" class="text-purple-400 no-underline">Open the full persona manager →</a>
                        </div>
                    </div>
                </div>

                <div class="aic-card">
                    <h5><i class="fas fa-comment-dots text-[13px]" style="color:#a78bfa"></i> Conversation</h5>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-greeting">Opening message</label>
                        <textarea id="aic-greeting" class="aic-textarea" name="config[greeting]" maxlength="1000"
                                  placeholder="Hi! Ask me anything about…">{{ old('config.greeting', $config['greeting'] ?? '') }}</textarea>
                        <div class="aic-hint">Greets the visitor before they type. Leave blank to start empty.</div>
                    </div>
                    <div class="aic-row">
                        <label class="aic-label">Starter questions <span class="text-faint">(optional, up to 6)</span></label>
                        <div class="aic-starter-rows">
                            @php $starters = old('starters', $config['starters'] ?? []); @endphp
                            @for($i = 0; $i < 6; $i++)
                                <input class="aic-input" type="text" name="starters[]" maxlength="200"
                                       value="{{ $starters[$i] ?? '' }}" placeholder="Suggested question {{ $i + 1 }}">
                            @endfor
                        </div>
                        <div class="aic-hint">Shown as tap-to-ask chips above the input.</div>
                    </div>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-placeholder">Input placeholder</label>
                        <input id="aic-placeholder" class="aic-input" type="text" name="config[placeholder]" maxlength="120"
                               value="{{ old('config.placeholder', $config['placeholder'] ?? 'Ask me anything…') }}">
                    </div>
                    <div class="aic-row">
                        <label class="aic-toggle">
                            <input type="checkbox" name="config[ground_in_profile]" value="1"
                                   @checked(old('config.ground_in_profile', $config['ground_in_profile'] ?? true))>
                            <span>
                                <strong style="color:var(--text-primary)">Ground answers in this page's profile</strong>
                                <span class="aic-hint" style="display:block;margin-top:2px">Lets the assistant reference the page's title, bio and links when answering.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="aic-card">
                    <h5><i class="fas fa-palette text-[13px]" style="color:#a78bfa"></i> Appearance</h5>
                    <div class="aic-row" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div>
                            <label class="aic-label" for="aic-theme">Theme</label>
                            <select id="aic-theme" class="aic-select" name="config[theme]">
                                @foreach(['auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark'] as $k => $v)
                                    <option value="{{ $k }}" @selected(old('config.theme', $config['theme'] ?? 'auto') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="aic-label" for="aic-accent">Accent colour</label>
                            <input id="aic-accent" class="aic-input" type="color" name="config[accent]"
                                   value="{{ old('config.accent', $config['accent'] ?? '#7c3aed') }}" style="height:42px;padding:4px">
                        </div>
                    </div>
                    <div class="aic-row">
                        <label class="aic-toggle">
                            <input type="checkbox" name="config[show_branding]" value="1"
                                   @checked(old('config.show_branding', $config['show_branding'] ?? true))>
                            <span><strong style="color:var(--text-primary)">Show "Powered by Sayzio"</strong></span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="aic-btn"><i class="fas fa-save"></i> Save AI chat</button>
            </div>

            <div>
                <div class="aic-card">
                    <h5><i class="fas fa-link text-[13px]" style="color:#a78bfa"></i> Public page</h5>
                    <p class="aic-hint" style="margin-top:0">Visitors chat at:</p>
                    <a href="{{ $publicUrl }}" target="_blank" class="text-purple-400 no-underline" style="font-size:13px;word-break:break-all">{{ $publicUrl }}</a>
                    <div style="margin-top:14px">
                        <a href="{{ $publicUrl }}" target="_blank" class="aic-btn" style="background:var(--bg-glass-input);color:var(--text-primary);border:1px solid var(--border-glass)">
                            <i class="fas fa-external-link-alt"></i> Open chat
                        </a>
                    </div>
                </div>

                <div class="aic-card">
                    <h5><i class="fas fa-chart-simple text-[13px]" style="color:#a78bfa"></i> This month</h5>
                    <div class="aic-stat"><span class="text-faint">Turns used</span><strong>{{ $usage['turns'] ?? 0 }}</strong></div>
                    <div class="aic-stat"><span class="text-faint">Free turns</span><strong>{{ $companion->free_turns_per_month }}</strong></div>
                    <div class="aic-stat"><span class="text-faint">Monthly cap</span><strong>{{ $companion->hard_cap_per_month }}</strong></div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function aiChatEditor() {
        return {
            personaId: {{ (int) old('persona_id', $companion->persona_id) }},
            boundPersonaId: {{ (int) $companion->persona_id }},
            get switched() { return this.personaId !== this.boundPersonaId; },
        };
    }
</script>
@endsection
