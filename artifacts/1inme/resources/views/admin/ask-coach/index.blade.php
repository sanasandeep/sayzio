@extends('admin.layouts.app')
@section('title', 'AI Coach')

@section('content')
<div class="max-w-6xl mx-auto px-6 py-8 space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40">AI · Coach</p>
            <h1 class="text-2xl font-bold text-white mt-1">Coach usage &amp; quality</h1>
            <p class="text-sm text-white/50 mt-1">Last <strong>{{ $days }}</strong> days. Spend is the sum of every AI credit charged with feature tag <code>ask_coach.*</code>.</p>
        </div>
        <form method="GET" action="{{ route('admin.ask-coach.index') }}">
            <select name="days" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                @foreach([7, 14, 30, 60, 90, 180] as $d)
                    <option value="{{ $d }}" {{ $days === $d ? 'selected' : '' }}>{{ $d }} days</option>
                @endforeach
            </select>
        </form>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Usage tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Chats started</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($threads) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Total messages</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($messages) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Coach replies</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($assistantMessages) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">Credits spent</p>
            <p class="text-2xl font-bold text-blue-300 mt-1">{{ number_format($creditsSpent) }} ✦</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs text-white/40">👍 / 👎</p>
            <p class="text-2xl font-bold text-white mt-1">
                <span class="text-emerald-300">{{ number_format($upCount) }}</span>
                <span class="text-white/30 px-1">/</span>
                <span class="text-red-300">{{ number_format($downCount) }}</span>
            </p>
        </div>
    </div>

    {{-- Settings --}}
    <form method="POST" action="{{ route('admin.ask-coach.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ── Section: System Prompt & Access ───────────────────── --}}
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            <h2 class="text-lg font-semibold text-white">System prompt &amp; access</h2>

            <div>
                <label class="block text-sm text-white/70 mb-1">Central system prompt</label>
                <textarea name="system_prompt" rows="10"
                          class="w-full bg-black/30 border border-white/10 rounded-xl p-3 text-white text-sm font-mono">{{ $systemPrompt }}</textarea>
                <p class="text-xs text-white/40 mt-1">
                    Sent at the top of every AI Coach turn before the data snapshots are appended. Leave blank to restore the platform default.
                </p>
            </div>

            <div>
                <label class="block text-sm text-white/70 mb-2">Enabled plans</label>
                <p class="text-xs text-white/40 mb-2">
                    Tick which plans can use AI Coach. Leave all unticked to enable AI Coach for every plan (the default).
                </p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach($allPlans as $p)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02]">
                            <input type="checkbox" name="plans[]" value="{{ $p->slug }}"
                                   {{ in_array($p->slug, $enabledPlans, true) ? 'checked' : '' }}
                                   class="rounded border-white/20 bg-white/5 text-blue-500">
                            <span class="text-sm text-white/80">{{ $p->name }}</span>
                        </label>
                    @endforeach
                    <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02]">
                        <input type="checkbox" name="plans[]" value="free"
                               {{ in_array('free', $enabledPlans, true) ? 'checked' : '' }}
                               class="rounded border-white/20 bg-white/5 text-blue-500">
                        <span class="text-sm text-white/80">Free / no plan</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ── Section: Behavior Controls ─────────────────────────── --}}
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Behavior</h2>
                <p class="text-sm text-white/40 mt-0.5">Control tone, verbosity, and language for every Coach reply. Blank fields use the platform default.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-white/70 mb-1">Tone preset</label>
                    <select name="tone" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        <option value="">Platform default (friendly)</option>
                        @foreach(['friendly' => 'Friendly', 'professional' => 'Professional', 'concise' => 'Concise', 'playful' => 'Playful'] as $val => $label)
                            <option value="{{ $val }}" {{ $coachTone === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-white/70 mb-1">Response length</label>
                    <select name="response_length" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                        <option value="">Platform default (medium)</option>
                        @foreach(['short' => 'Short (~60 words max)', 'medium' => 'Medium (balanced)', 'long' => 'Long (detailed, ~300 words)'] as $val => $label)
                            <option value="{{ $val }}" {{ $responseLength === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-white/70 mb-1">Reply language</label>
                    <input type="text" name="reply_language"
                           value="{{ $replyLanguage !== 'match_user' ? $replyLanguage : '' }}"
                           placeholder="Auto-detect (match_user)"
                           class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/20">
                    <p class="text-xs text-white/40 mt-1">ISO language code, e.g. <code>en</code>, <code>es</code>, <code>fr</code>. Leave blank to auto-detect.</p>
                </div>
            </div>

            <div class="max-w-xs">
                <label class="block text-sm text-white/70 mb-1">Temperature / creativity
                    <span class="text-white/40 font-normal">(0.0 – 1.5, default {{ \App\Services\AI\AiEngineSettings::DEFAULT_ASK_COACH_TEMPERATURE }})</span>
                </label>
                <input type="number" name="temperature" step="0.05" min="0" max="1.5"
                       value="{{ $temperature }}"
                       class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                <p class="text-xs text-white/40 mt-1">Lower = more factual. Higher = more creative. Leave blank to use the default.</p>
            </div>
        </div>

        {{-- ── Section: Usage Limits ───────────────────────────────── --}}
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Usage limits</h2>
                <p class="text-sm text-white/40 mt-0.5">Throttle Coach usage per plan and apply credit surcharges. Blank or zero values mean unlimited / no surcharge.</p>
            </div>

            <div>
                <label class="block text-sm text-white/70 mb-2">Per-plan message caps</label>
                <p class="text-xs text-white/40 mb-3">Set a daily or monthly message limit for each plan. Leave the cap at 0 for unlimited.</p>
                <div class="space-y-2">
                    @foreach($allPlans as $p)
                        @php
                            $slug = $p->slug;
                            $cfg  = $planCaps[$slug] ?? ['period' => 'daily', 'cap' => 0];
                        @endphp
                        <div class="flex items-center gap-3 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02]">
                            <span class="text-sm text-white/70 w-32 shrink-0">{{ $p->name }}</span>
                            <select name="plan_caps[{{ $slug }}][period]" class="bg-black/30 border border-white/10 rounded-lg px-2 py-1 text-white text-xs">
                                <option value="daily"   {{ ($cfg['period'] ?? 'daily') === 'daily'   ? 'selected' : '' }}>Daily</option>
                                <option value="monthly" {{ ($cfg['period'] ?? 'daily') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                            </select>
                            <input type="number" name="plan_caps[{{ $slug }}][cap]" min="0" max="99999"
                                   value="{{ (int) ($cfg['cap'] ?? 0) }}"
                                   placeholder="0 = unlimited"
                                   class="w-32 bg-black/30 border border-white/10 rounded-lg px-2 py-1 text-white text-xs placeholder-white/20">
                            <span class="text-xs text-white/30">messages</span>
                        </div>
                    @endforeach
                    @php $cfg = $planCaps['free'] ?? ['period' => 'daily', 'cap' => 0]; @endphp
                    <div class="flex items-center gap-3 px-3 py-2 rounded-xl border border-white/10 bg-white/[0.02]">
                        <span class="text-sm text-white/70 w-32 shrink-0">Free / no plan</span>
                        <select name="plan_caps[free][period]" class="bg-black/30 border border-white/10 rounded-lg px-2 py-1 text-white text-xs">
                            <option value="daily"   {{ ($cfg['period'] ?? 'daily') === 'daily'   ? 'selected' : '' }}>Daily</option>
                            <option value="monthly" {{ ($cfg['period'] ?? 'daily') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        </select>
                        <input type="number" name="plan_caps[free][cap]" min="0" max="99999"
                               value="{{ (int) ($cfg['cap'] ?? 0) }}"
                               placeholder="0 = unlimited"
                               class="w-32 bg-black/30 border border-white/10 rounded-lg px-2 py-1 text-white text-xs placeholder-white/20">
                        <span class="text-xs text-white/30">messages</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-white/70 mb-1">Per-user cooldown (seconds)</label>
                    <input type="number" name="cooldown_seconds" min="0" max="86400"
                           value="{{ $cooldownSeconds ?: '' }}"
                           placeholder="0 = no cooldown"
                           class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/20">
                    <p class="text-xs text-white/40 mt-1">Minimum seconds a user must wait between messages. 0 = no limit.</p>
                </div>
                <div>
                    <label class="block text-sm text-white/70 mb-1">Credit multiplier
                        <span class="text-white/40 font-normal">(≥1.0, default 1.0 = no surcharge)</span>
                    </label>
                    <input type="number" name="credit_multiplier" step="0.05" min="1" max="10"
                           value="{{ $creditMultiplier > 1.0 ? $creditMultiplier : '' }}"
                           placeholder="1.0 = no surcharge"
                           class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/20">
                    <p class="text-xs text-white/40 mt-1">e.g. 1.5 charges 50% extra on top of the normal per-plan coin rate.</p>
                </div>
            </div>
        </div>

        {{-- ── Section: Content Controls ───────────────────────────── --}}
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Content</h2>
                <p class="text-sm text-white/40 mt-0.5">Customize messages and restrict topics without touching code.</p>
            </div>

            <div>
                <label class="block text-sm text-white/70 mb-1">Banned topics / keywords</label>
                <textarea name="banned_topics" rows="4"
                          placeholder="One keyword or phrase per line&#10;e.g. crypto&#10;gambling&#10;how to hack"
                          class="w-full bg-black/30 border border-white/10 rounded-xl p-3 text-white text-sm font-mono placeholder-white/20">{{ $bannedTopics }}</textarea>
                <p class="text-xs text-white/40 mt-1">One keyword or phrase per line. Case-insensitive substring match. Messages containing any of these are politely declined before reaching the AI.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-white/70 mb-1">Custom greeting</label>
                    <textarea name="greeting" rows="3"
                              placeholder="Leave blank to start chats without a greeting message."
                              class="w-full bg-black/30 border border-white/10 rounded-xl p-3 text-white text-sm placeholder-white/20">{{ $greeting }}</textarea>
                    <p class="text-xs text-white/40 mt-1">Shown as Coach's first message in every new chat thread. Supports basic Markdown.</p>
                </div>
                <div>
                    <label class="block text-sm text-white/70 mb-1">Custom fallback message</label>
                    <textarea name="fallback_message" rows="3"
                              placeholder="Leave blank to use the platform default error message."
                              class="w-full bg-black/30 border border-white/10 rounded-xl p-3 text-white text-sm placeholder-white/20">{{ $fallbackMessage }}</textarea>
                    <p class="text-xs text-white/40 mt-1">Shown when the AI call fails entirely instead of the generic "could not reply" text.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm text-white/70 mb-1">Escalation / support note</label>
                <input type="text" name="escalation_note"
                       value="{{ $escalationNote }}"
                       placeholder="e.g. For further help, contact our support team at support@example.com."
                       class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/20">
                <p class="text-xs text-white/40 mt-1">Appended to banned-topic decline messages. Leave blank to omit.</p>
            </div>
        </div>

        {{-- ── Section: Model & Data ───────────────────────────────── --}}
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Model &amp; data</h2>
                <p class="text-sm text-white/40 mt-0.5">Choose which AI model powers Coach and which data categories it can access.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-white/70 mb-1">Coach model</label>
                    @if($enabledModels)
                        <select name="coach_model" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                            @foreach($enabledModels as $m)
                                <option value="{{ $m['id'] }}" {{ $coachModel === $m['id'] ? 'selected' : '' }}>{{ $m['label'] ?? $m['id'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-sm text-white/40">No chat models are enabled. Enable them in <a href="{{ route('admin.ai-engine.index') }}" class="underline text-blue-400">AI Engine settings</a>.</p>
                        <input type="hidden" name="coach_model" value="">
                    @endif
                </div>
                <div>
                    <label class="block text-sm text-white/70 mb-1">Max response tokens
                        <span class="text-white/40 font-normal">(100 – 4000, default {{ \App\Services\AI\AiEngineSettings::DEFAULT_ASK_COACH_MAX_TOKENS }})</span>
                    </label>
                    <input type="number" name="max_tokens" min="100" max="4000"
                           value="{{ $maxTokens !== \App\Services\AI\AiEngineSettings::DEFAULT_ASK_COACH_MAX_TOKENS ? $maxTokens : '' }}"
                           placeholder="{{ \App\Services\AI\AiEngineSettings::DEFAULT_ASK_COACH_MAX_TOKENS }} (default)"
                           class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/20">
                    <p class="text-xs text-white/40 mt-1">Maximum tokens in the AI's response per turn. Leave blank for the default.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm text-white/70 mb-2">Data snapshot categories</label>
                <p class="text-xs text-white/40 mb-3">
                    Choose which of the user's live data categories Coach can access. Un-tick a category to prevent Coach from ever loading that data.
                    Leave <strong>all ticked</strong> (the default) to allow all categories.
                </p>
                @php
                    $categoryLabels = [
                        'links'     => ['label' => 'Links & Biolinks',  'desc' => 'Short links, biolink pages, click counts, active state.'],
                        'analytics' => ['label' => 'Analytics',          'desc' => 'Daily clicks, device split, traffic funnel.'],
                        'audience'  => ['label' => 'Audience',           'desc' => 'Followers, subscribers, recent growth.'],
                        'billing'   => ['label' => 'Billing & Payments', 'desc' => 'Wallet coins, plan info, invoices, revenue summary.'],
                        'events'    => ['label' => 'Event lookup',        'desc' => 'Full stats for a specific event by name or date.'],
                    ];
                    $allOn = empty($snapshotCategories);
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($allCategories as $cat)
                        @php $meta = $categoryLabels[$cat] ?? ['label' => $cat, 'desc' => '']; @endphp
                        <label class="flex items-start gap-3 px-3 py-3 rounded-xl border border-white/10 bg-white/[0.02] cursor-pointer">
                            <input type="checkbox" name="snapshot_categories[]" value="{{ $cat }}"
                                   {{ ($allOn || in_array($cat, $snapshotCategories, true)) ? 'checked' : '' }}
                                   class="mt-0.5 rounded border-white/20 bg-white/5 text-blue-500 shrink-0">
                            <span>
                                <span class="block text-sm text-white/80 font-medium">{{ $meta['label'] }}</span>
                                <span class="block text-xs text-white/40 mt-0.5">{{ $meta['desc'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-amber-400/70 mt-2">⚠ Disabling a category removes it from both the AI tool-calling loop and the keyword-router fallback, Coach will answer without that data.</p>
            </div>
        </div>

        <div>
            <button class="px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Save all settings
            </button>
        </div>
    </form>

    {{-- Recent thumbs-down for quality loop --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h2 class="text-lg font-semibold text-white mb-3">Recent thumbs-down replies</h2>
        @if($recentDowns->isEmpty())
            <p class="text-sm text-white/50">No 👎 feedback in this window, looking healthy.</p>
        @else
            <ul class="space-y-3">
                @foreach($recentDowns as $m)
                    <li class="rounded-xl bg-black/20 p-3">
                        <p class="text-[11px] text-white/40">
                            Thread #{{ $m->thread_id }} ·
                            {{ $m->created_at?->diffForHumans() }}
                        </p>
                        <p class="text-sm text-white/80 mt-1 line-clamp-3">{{ $m->content }}</p>
                        @if($m->feedback_note)
                            <p class="text-xs text-red-300 mt-2">User note: {{ $m->feedback_note }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
