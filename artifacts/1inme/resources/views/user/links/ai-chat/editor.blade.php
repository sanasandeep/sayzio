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
    .aic-pro { font-size: 9px; font-weight: 800; letter-spacing: .03em; padding: 1px 6px; border-radius: 999px; margin-left: 6px; vertical-align: middle; background: linear-gradient(135deg, rgba(251,146,60,0.18), rgba(245,158,11,0.12)); color: #fb923c; }
    .aic-toggle { display:flex; align-items:center; gap: 12px; padding: 12px 14px; border:1px solid var(--border-glass); border-radius: .85rem; background: var(--bg-glass-input); }
    .aic-toggle input { width: 18px; height: 18px; }
    .aic-banner { display:flex; gap:12px; align-items:center; padding: 14px 16px; border-radius: .85rem; margin-bottom: 16px; font-size: 13px; }
    .aic-banner.warn { background: rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color: var(--text-primary); }
    .aic-banner.info { background: rgba(61,107,255,.10); border:1px solid rgba(61,107,255,.30); color: var(--text-primary); }
    .aic-btn { display:inline-flex; align-items:center; gap:8px; padding: 10px 18px; border:0; border-radius: 999px; font-weight:600; font-size:14px; color:#fff; cursor:pointer; background: linear-gradient(135deg, #5c83ff, #6366f1); }
    .aic-stat { display:flex; justify-content:space-between; font-size: 13px; padding: 8px 0; border-bottom: 1px dashed var(--border-glass); }
    .aic-stat:last-child { border-bottom: 0; }
    .aic-starter-rows { display:flex; flex-direction:column; gap: 8px; }

    /* Live branding preview — mirrors the public ai-chat page, theme-aware */
    .aicp-frame {
        --aicp-bg:#f6f6f9; --aicp-text:#111; --aicp-muted:rgba(0,0,0,.55);
        --aicp-border:rgba(0,0,0,.08); --aicp-amsg:rgba(0,0,0,.05); --aicp-chip-border:rgba(0,0,0,.12);
        border: 1px solid var(--border-glass); border-radius: .9rem; overflow: hidden;
        background: var(--aicp-bg); color: var(--aicp-text); display: flex; flex-direction: column;
    }
    @media (prefers-color-scheme: dark) {
        .aicp-frame[data-aicp-theme="auto"] { --aicp-bg:#0b0b10; --aicp-text:#f5f5f7; --aicp-muted:rgba(255,255,255,.6); --aicp-border:rgba(255,255,255,.08); --aicp-amsg:rgba(255,255,255,.07); --aicp-chip-border:rgba(255,255,255,.16); }
    }
    .aicp-frame[data-aicp-theme="dark"] { --aicp-bg:#0b0b10; --aicp-text:#f5f5f7; --aicp-muted:rgba(255,255,255,.6); --aicp-border:rgba(255,255,255,.08); --aicp-amsg:rgba(255,255,255,.07); --aicp-chip-border:rgba(255,255,255,.16); }
    .aicp-frame[data-aicp-theme="light"] { --aicp-bg:#f6f6f9; --aicp-text:#111; --aicp-muted:rgba(0,0,0,.55); --aicp-border:rgba(0,0,0,.08); --aicp-amsg:rgba(0,0,0,.05); --aicp-chip-border:rgba(0,0,0,.12); }
    .aicp-header { display:flex; align-items:center; gap:10px; padding:12px 14px; border-bottom:1px solid var(--aicp-border); }
    .aicp-avatar { width:34px; height:34px; border-radius:50%; flex:0 0 auto; overflow:hidden; display:flex; align-items:center; justify-content:center; color:#fff; font-size:15px; }
    .aicp-avatar img { width:100%; height:100%; object-fit:cover; }
    .aicp-title { font-weight:700; font-size:14px; line-height:1.2; color:var(--aicp-text); }
    .aicp-sub { font-size:11px; color:var(--aicp-muted); }
    .aicp-body { padding:14px; min-height:70px; }
    .aicp-msg { display:inline-block; max-width:90%; padding:9px 12px; border-radius:14px; border-bottom-left-radius:5px; font-size:13.5px; line-height:1.45; white-space:pre-wrap; word-break:break-word; background:var(--aicp-amsg); color:var(--aicp-text); }
    .aicp-empty { font-size:12px; color:var(--aicp-muted); font-style:italic; }
    .aicp-starters { display:flex; flex-wrap:wrap; gap:7px; padding:0 14px 12px; }
    .aicp-chip { border:1px solid var(--aicp-chip-border); background:transparent; color:inherit; border-radius:999px; padding:6px 11px; font-size:12px; line-height:1.3; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .aicp-chip:hover { border-color:var(--aicp-accent); color:var(--aicp-accent); }
    .aicp-input { margin:0 14px 14px; border:1px solid var(--aicp-chip-border); border-radius:12px; padding:9px 12px; font-size:12.5px; color:var(--aicp-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .aicp-foot { font-size:10.5px; text-align:center; padding:9px; color:var(--aicp-muted); border-top:1px solid var(--aicp-border); }
    .aicp-foot a { color:inherit; text-decoration:underline; }
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

    <form method="POST" action="{{ route('user.links.ai-chat.save', $link) }}" enctype="multipart/form-data">
        @csrf
        <div class="aic-grid">
            <div>
                <div class="aic-card">
                    <h5><i class="fas fa-robot text-[13px]" style="color:#90acff"></i> Chat identity</h5>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-name">Display name</label>
                        <input id="aic-name" class="aic-input" type="text" name="name" maxlength="120"
                               value="{{ old('name', $companion->name) }}" x-model="name" required>
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
                            <a href="{{ route('user.ai-personas.index') }}" class="text-blue-400 no-underline">Manage personas &amp; knowledge →</a>
                        </div>
                    </div>
                </div>

                <div class="aic-card">
                    <input type="hidden" name="persona[apply]" :value="switched ? '0' : '1'">
                    <h5><i class="fas fa-brain text-[13px]" style="color:#90acff"></i> Personality &amp; knowledge</h5>

                    <div class="aic-banner info" x-show="switched" x-cloak style="margin-bottom:16px">
                        <i class="fas fa-circle-info"></i>
                        You picked a different persona. Save to bind it first, then its personality &amp; knowledge will load here for editing.
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
                                    <a href="{{ route('user.minds.index') }}" class="text-blue-400 no-underline">Create one →</a>
                                </div>
                            @endforelse
                            <div class="aic-hint" style="margin-top:6px">Attach knowledge so the assistant can answer from your own content. Up to {{ $caps['max_minds_per_persona'] }} per persona.</div>
                        </div>
                        <div class="aic-hint">
                            Need model, temperature or version history?
                            <a href="{{ route('user.ai-personas.edit', $persona) }}" class="text-blue-400 no-underline">Open the full persona manager →</a>
                        </div>
                    </div>
                </div>

                <div class="aic-card">
                    <h5><i class="fas fa-comment-dots text-[13px]" style="color:#90acff"></i> Conversation</h5>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-greeting">Opening message</label>
                        <textarea id="aic-greeting" class="aic-textarea" name="config[greeting]" maxlength="1000"
                                  x-model="greeting"
                                  placeholder="Hi! Ask me anything about…">{{ old('config.greeting', $config['greeting'] ?? '') }}</textarea>
                        <div class="aic-hint">Greets the visitor before they type. Leave blank to start empty.</div>
                    </div>
                    <div class="aic-row">
                        <label class="aic-label">Starter questions <span class="text-faint">(optional, up to 6)</span></label>
                        <div class="aic-starter-rows">
                            @php $starters = old('starters', $config['starters'] ?? []); @endphp
                            @for($i = 0; $i < 6; $i++)
                                <input class="aic-input" type="text" name="starters[]" maxlength="200"
                                       value="{{ $starters[$i] ?? '' }}" placeholder="Suggested question {{ $i + 1 }}"
                                       x-model="starters[{{ $i }}]">
                            @endfor
                        </div>
                        <div class="aic-hint">Shown as tap-to-ask chips above the input.</div>
                    </div>
                    <div class="aic-row">
                        <label class="aic-label" for="aic-placeholder">Input placeholder</label>
                        <input id="aic-placeholder" class="aic-input" type="text" name="config[placeholder]" maxlength="120"
                               value="{{ old('config.placeholder', $config['placeholder'] ?? 'Ask me anything…') }}"
                               x-model="placeholder">
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
                    <h5><i class="fas fa-palette text-[13px]" style="color:#90acff"></i> Appearance</h5>
                    <div class="aic-row" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div>
                            <label class="aic-label" for="aic-theme">Theme</label>
                            <select id="aic-theme" class="aic-select" name="config[theme]" x-model="theme">
                                @foreach(['auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark'] as $k => $v)
                                    <option value="{{ $k }}" @selected(old('config.theme', $config['theme'] ?? 'auto') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="aic-label" for="aic-accent">Accent colour</label>
                            <input id="aic-accent" class="aic-input" type="color" name="config[accent]"
                                   value="{{ old('config.accent', $config['accent'] ?? '#3d6bff') }}" x-model="accent" style="height:42px;padding:4px">
                        </div>
                    </div>
                </div>

                <div class="aic-card">
                    @php $upgradeUrl = route('user.dashboard'); @endphp
                    <h5><i class="fas fa-id-badge text-[13px]" style="color:#90acff"></i> Branding &amp; avatar</h5>

                    {{-- Agent avatar — gated by either branding feature. Falls back to the default robot avatar. --}}
                    <div class="aic-row">
                        <label class="aic-label">Agent avatar
                            @unless($branding['can_avatar'])<span class="aic-pro">PRO</span>@endunless
                        </label>
                        @if($branding['can_avatar'])
                            @include('user.partials.dropzone-input', [
                                'name'        => 'avatar_upload',
                                'policy'      => $avatarPolicy,
                                'currentUrl'  => $config['avatar_url'] ?? null,
                                'label'       => null,
                                'previewKind' => 'image',
                                'compact'     => true,
                            ])
                            @if(!empty($config['avatar_url']))
                                <label class="aic-toggle" style="margin-top:8px">
                                    <input type="checkbox" name="avatar_remove" value="1">
                                    <span><strong style="color:var(--text-primary)">Remove current avatar</strong>
                                        <span class="aic-hint" style="display:block;margin-top:2px">Revert to the default robot avatar.</span>
                                    </span>
                                </label>
                            @endif
                            <div class="aic-hint">Upload, paste a URL, or pick from My Files. Falls back to a default robot avatar.</div>
                        @else
                            <div class="aic-hint">Give your AI agent its own face instead of the default robot avatar.
                                <a href="{{ $upgradeUrl }}" class="text-blue-400 no-underline">Upgrade to unlock →</a>
                            </div>
                        @endif
                    </div>

                    {{-- Hide branding — gated by remove_branding. --}}
                    <div class="aic-row">
                        @if($branding['can_hide_branding'])
                            <label class="aic-toggle">
                                <input type="checkbox" name="config[show_branding]" value="1"
                                       x-model="showBranding"
                                       @checked(old('config.show_branding', $config['show_branding'] ?? true))>
                                <span><strong style="color:var(--text-primary)">Show "Powered by Sayzio"</strong>
                                    <span class="aic-hint" style="display:block;margin-top:2px">Uncheck to hide the footer entirely.</span>
                                </span>
                            </label>
                        @else
                            <label class="aic-label">Branding footer <span class="aic-pro">PRO</span></label>
                            <div class="aic-hint">Your page shows a "Powered by Sayzio" footer.
                                <a href="{{ $upgradeUrl }}" class="text-blue-400 no-underline">Upgrade to hide or replace it →</a>
                            </div>
                        @endif
                    </div>

                    {{-- Custom branding text + URL — gated by custom_branding. --}}
                    <div class="aic-row">
                        <label class="aic-label">Custom branding
                            @unless($branding['can_custom_branding'])<span class="aic-pro">PRO</span>@endunless
                        </label>
                        @if($branding['can_custom_branding'])
                            <input class="aic-input" type="text" name="config[custom_branding_text]" maxlength="60"
                                   value="{{ old('config.custom_branding_text', $config['custom_branding_text'] ?? '') }}"
                                   x-model="brandText"
                                   placeholder="Powered by Your Brand" style="margin-bottom:8px">
                            <input class="aic-input" type="url" name="config[custom_branding_url]" maxlength="300"
                                   value="{{ old('config.custom_branding_url', $config['custom_branding_url'] ?? '') }}"
                                   x-model="brandUrl"
                                   placeholder="https://yourbrand.com">
                            <div class="aic-hint">Replaces "Powered by Sayzio" with your own text (and link). Leave blank to keep the default.</div>
                        @else
                            <div class="aic-hint">Replace "Powered by Sayzio" with your own text and link.
                                <a href="{{ $upgradeUrl }}" class="text-blue-400 no-underline">Upgrade to unlock →</a>
                            </div>
                        @endif
                    </div>
                </div>

                <button type="submit" class="aic-btn"><i class="fas fa-save"></i> Save AI chat</button>
            </div>

            <div>
                <div class="aic-card">
                    <h5><i class="fas fa-eye text-[13px]" style="color:#90acff"></i> Live preview</h5>
                    <div class="aicp-frame" :data-aicp-theme="theme" :style="`--aicp-accent:${accent}`">
                        <div class="aicp-header">
                            <template x-if="effAvatar">
                                <div class="aicp-avatar"><img :src="effAvatar" alt=""></div>
                            </template>
                            <template x-if="!effAvatar">
                                <div class="aicp-avatar" :style="`background:${accent}`">🤖</div>
                            </template>
                            <div>
                                <div class="aicp-title" x-text="displayName"></div>
                                <div class="aicp-sub">AI assistant</div>
                            </div>
                        </div>
                        <div class="aicp-body">
                            <template x-if="greeting && greeting.trim()">
                                <div class="aicp-msg" x-text="greeting"></div>
                            </template>
                            <template x-if="!greeting || !greeting.trim()">
                                <div class="aicp-empty">No opening message, the chat starts empty.</div>
                            </template>
                        </div>
                        <template x-if="starterList.length">
                            <div class="aicp-starters">
                                <template x-for="(q, i) in starterList" :key="i">
                                    <span class="aicp-chip" x-text="q"></span>
                                </template>
                            </div>
                        </template>
                        <div class="aicp-input" x-text="effPlaceholder"></div>
                        <div class="aicp-foot" x-show="effShowBranding" x-cloak>
                            <template x-if="effBrandText && effBrandUrl">
                                <a :href="effBrandUrl" target="_blank" rel="noopener" x-text="effBrandText"></a>
                            </template>
                            <template x-if="effBrandText && !effBrandUrl">
                                <span x-text="effBrandText"></span>
                            </template>
                            <template x-if="!effBrandText">
                                <span>Powered by <a href="{{ url('/') }}" target="_blank" rel="noopener">Sayzio</a></span>
                            </template>
                        </div>
                    </div>
                    <div class="aic-hint" style="margin-top:10px">How visitors see your agent. Branding reflects your current plan.</div>
                </div>

                <div class="aic-card">
                    <h5><i class="fas fa-link text-[13px]" style="color:#90acff"></i> Public page</h5>
                    <p class="aic-hint" style="margin-top:0">Visitors chat at:</p>
                    <a href="{{ $publicUrl }}" target="_blank" class="text-blue-400 no-underline" style="font-size:13px;word-break:break-all">{{ $publicUrl }}</a>
                    <div style="margin-top:14px">
                        <a href="{{ $publicUrl }}" target="_blank" class="aic-btn" style="background:var(--bg-glass-input);color:var(--text-primary);border:1px solid var(--border-glass)">
                            <i class="fas fa-external-link-alt"></i> Open chat
                        </a>
                    </div>
                </div>

                <div class="aic-card">
                    <h5><i class="fas fa-chart-simple text-[13px]" style="color:#90acff"></i> This month</h5>
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

            // Live branding preview — mirrors AiCompanion::brandingConfig() gating.
            canHide:   {{ $branding['can_hide_branding'] ? 'true' : 'false' }},
            canCustom: {{ $branding['can_custom_branding'] ? 'true' : 'false' }},
            canAvatar: {{ $branding['can_avatar'] ? 'true' : 'false' }},
            name:        @js(old('name', $companion->name)),
            greeting:    @js(old('config.greeting', $config['greeting'] ?? '')),
            accent:      @js(old('config.accent', $config['accent'] ?? '#3d6bff')),
            theme:       @js(old('config.theme', $config['theme'] ?? 'auto')),
            placeholder: @js(old('config.placeholder', $config['placeholder'] ?? 'Ask me anything…')),
            @php
                $starterInit = old('starters', $config['starters'] ?? []);
                $starterInit = array_map(fn($i) => (string) ($starterInit[$i] ?? ''), range(0, 5));
            @endphp
            starters:    @js($starterInit),
            showBranding: {{ old('config.show_branding', $config['show_branding'] ?? true) ? 'true' : 'false' }},
            brandText:   @js(old('config.custom_branding_text', $config['custom_branding_text'] ?? '')),
            brandUrl:    @js(old('config.custom_branding_url', $config['custom_branding_url'] ?? '')),
            savedAvatar: @js($branding['can_avatar'] ? ($config['avatar_url'] ?? '') : ''),
            avatarObjectUrl: '',
            avatarRemoved: false,
            _lastFile: null,

            get displayName() {
                const linkTitle = @js($link->title ?: '');
                if (linkTitle) return linkTitle;
                return (this.name || '').trim() || @js($link->alias);
            },
            get starterList() { return (this.starters || []).map(s => (s || '').trim()).filter(Boolean); },
            get effPlaceholder() { return (this.placeholder || '').trim() || 'Ask me anything…'; },
            get effShowBranding() { return this.canHide ? !!this.showBranding : true; },
            get effBrandText() { return this.canCustom ? (this.brandText || '').trim() : ''; },
            get effBrandUrl() { return this.canCustom ? (this.brandUrl || '').trim() : ''; },
            get effAvatar() {
                if (!this.canAvatar || this.avatarRemoved) return '';
                return this.avatarObjectUrl || this.savedAvatar || '';
            },

            init() {
                this.syncAvatar();
                // The avatar dropzone injects files programmatically (upload, URL
                // import, or vault pick) without firing a native change event, so
                // poll the underlying input to keep the preview in sync.
                setInterval(() => this.syncAvatar(), 400);
            },
            syncAvatar() {
                const removeEl = document.querySelector('input[name="avatar_remove"]');
                this.avatarRemoved = !!(removeEl && removeEl.checked);
                const input = document.querySelector('input[name="avatar_upload"]');
                const f = input && input.files && input.files[0];
                if (f && f.type && f.type.indexOf('image/') === 0) {
                    if (this._lastFile !== f) {
                        if (this.avatarObjectUrl) URL.revokeObjectURL(this.avatarObjectUrl);
                        this.avatarObjectUrl = URL.createObjectURL(f);
                        this._lastFile = f;
                    }
                } else if (this.avatarObjectUrl) {
                    URL.revokeObjectURL(this.avatarObjectUrl);
                    this.avatarObjectUrl = '';
                    this._lastFile = null;
                }
            },
        };
    }
</script>
@endsection
