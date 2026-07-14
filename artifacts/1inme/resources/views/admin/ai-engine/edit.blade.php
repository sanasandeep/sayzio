@extends('admin.layouts.app')
@section('title', 'AI Engine')
@section('page-title', 'AI Engine')

@section('content')
<form method="POST" action="{{ route('admin.ai-engine.update') }}" class="max-w-5xl space-y-6">
    @csrf @method('PUT')

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Master toggle + OpenAI key --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
        <div>
            <h3 class="font-semibold text-white">Engine</h3>
            <p class="text-xs text-white/40">Master switch for the entire AI subsystem and API credentials.</p>
        </div>

        <label class="flex items-center gap-3 cursor-pointer">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}
                   class="w-4 h-4 accent-blue-500">
            <span class="text-sm text-white">Enable AI Engine</span>
        </label>

        @include('admin.partials.help-note', [
            'body' => '<strong>Getting an OpenAI API key</strong>
                <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                    <li>Sign up or log in at <a class="underline" href="https://platform.openai.com/" target="_blank" rel="noopener">platform.openai.com</a>.</li>
                    <li>Go to <a class="underline" href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">API Keys</a> and click <em>Create new secret key</em>. Keys start with <code>sk-proj-…</code> (project-scoped) or <code>sk-…</code> (legacy org-scoped).</li>
                    <li>Prefer <strong>project-scoped keys</strong> (<code>sk-proj-…</code>) — they can be scoped to a single project and revoked without touching other integrations.</li>
                    <li>Add billing credits at <a class="underline" href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener">Billing</a>. API calls fail with a quota error if the balance reaches zero — the AI usage report shows consumption, but the actual USD balance lives on OpenAI\'s side.</li>
                </ol>',
        ])

        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">OpenAI API key</label>
            @if($hasKey)
                <p class="text-xs text-white/60 mb-2">Stored: <span class="font-mono text-amber-300">{{ $maskedKey }}</span></p>
            @endif
            @include('common.partials.password-field', [
                'name' => 'openai_api_key',
                'autocomplete' => 'off',
                'placeholder' => $hasKey ? 'Paste a new key to replace' : 'sk-…',
                'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
            ])
            @if($hasKey)
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                    <input type="hidden" name="clear_openai_api_key" value="0">
                    <input type="checkbox" name="clear_openai_api_key" value="1" class="accent-red-500">
                    Remove the stored key
                </label>
            @endif
            <p class="text-[11px] text-white/30 mt-1">Encrypted at rest with the application key. Never displayed back.</p>
        </div>

        <div class="pt-4 border-t border-white/10 space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" onclick="testOpenAiConnection(this)"
                        class="px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/15 text-white rounded-lg text-xs font-medium">
                    <i class="fas fa-plug mr-1"></i> Test connection
                </button>
                <span id="openai-test-result" class="text-xs text-white/50"></span>
            </div>
            <p class="text-[11px] text-white/30">Sends a tiny 1-token request to OpenAI to confirm the key works. No coins are charged. Tests the key typed above, or the stored key when the field is blank.</p>

            <div class="flex flex-wrap items-center gap-4 pt-1">
                <a href="{{ route('admin.ai-usage.index') }}" class="text-xs text-blue-300 hover:underline">
                    <i class="fas fa-chart-line mr-1"></i> View AI usage
                </a>
                <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener"
                   class="text-xs text-blue-300 hover:underline">
                    <i class="fas fa-arrow-up-right-from-square mr-1"></i> Check OpenAI balance
                </a>
            </div>
            <p class="text-[11px] text-white/30">Live USD balance lives on OpenAI's side — open their billing dashboard to check it. Internal token &amp; coin consumption is in the AI usage report.</p>
        </div>
    </div>

    {{-- Models with per-1k coin rates --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-white">Models &amp; rates</h3>
                <p class="text-xs text-white/40">Coins charged per 1 000 tokens (fractional allowed). Per-call cost is rounded up to whole coins.</p>
            </div>
            <button type="button" onclick="addModelRow()"
                    class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700">
                <i class="fas fa-plus mr-1"></i> Add model
            </button>
        </div>

        @php
            $modelToFeatures = [];
            foreach ($featureModels as $feat => $modelName) {
                $modelToFeatures[$modelName][] = $feat;
            }
        @endphp

        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">Model</th>
                <th class="text-left">Used by</th>
                <th class="text-left">Kind</th>
                <th class="text-left">Enabled</th>
                <th class="text-right">In / 1k</th>
                <th class="text-right">Out / 1k</th>
                <th></th>
            </tr></thead>
            <tbody id="models-tbody">
            @foreach($models as $i => $m)
                @php $usedBy = $modelToFeatures[$m['name']] ?? []; @endphp
                <tr class="border-t border-white/5 align-top" data-model-row data-features="{{ implode(',', $usedBy) }}">
                    <td class="py-2"><input name="models[{{ $i }}][name]" value="{{ $m['name'] }}" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
                    <td class="py-2">
                        @if($usedBy)
                            <div class="flex flex-wrap gap-1">
                                @foreach($usedBy as $feat)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-500/15 border border-blue-500/30 text-blue-200 text-[10px] font-mono uppercase tracking-wider">{{ $feat }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-white/30 text-xs">—</span>
                        @endif
                    </td>
                    <td><select name="models[{{ $i }}][kind]" class="bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm">
                        <option value="chat"      {{ $m['kind']==='chat' ? 'selected':'' }}>chat</option>
                        <option value="embedding" {{ $m['kind']==='embedding' ? 'selected':'' }}>embedding</option>
                    </select></td>
                    <td>
                        <input type="hidden" name="models[{{ $i }}][enabled]" value="0">
                        <input type="checkbox" data-enabled-toggle name="models[{{ $i }}][enabled]" value="1" {{ $m['enabled'] ? 'checked':'' }} class="accent-blue-500">
                        <p data-disable-warning class="hidden mt-1 text-[11px] text-amber-300 flex items-start gap-1">
                            <i class="fas fa-triangle-exclamation mt-0.5"></i>
                            <span data-disable-warning-text></span>
                        </p>
                    </td>
                    <td class="text-right"><input type="number" min="0" step="0.01" name="models[{{ $i }}][in_coins_per_1k]" value="{{ $m['in_coins_per_1k'] }}" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><input type="number" min="0" step="0.01" name="models[{{ $i }}][out_coins_per_1k]" value="{{ $m['out_coins_per_1k'] }}" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Per-feature model picker --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
        <div>
            <h3 class="font-semibold text-white">Feature models</h3>
            <p class="text-xs text-white/40">
                Choose which chat model each AI feature uses. Falls back to
                <span class="font-mono text-amber-300">{{ $defaultFeatureModel }}</span> if unset.
            </p>
        </div>

        @php
            $chatModelNames = array_values(array_map(fn($m) => $m['name'],
                array_filter($models, fn($m) => ($m['kind'] ?? 'chat') === 'chat')));
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($features as $f)
                @php
                    $current = $featureModels[$f] ?? $defaultFeatureModel;
                    $status  = $featureStatus[$f];
                    $inList  = in_array($current, $chatModelNames, true);
                @endphp
                <div class="space-y-1.5">
                    <label class="text-xs uppercase tracking-wider text-white/40 block">{{ ucfirst($f) }}</label>
                    <select name="feature_models[{{ $f }}]"
                            data-feature-select="{{ $f }}"
                            class="w-full bg-white/5 border border-white/10 rounded-lg px-2 py-2 text-white text-sm">
                        @foreach($chatModelNames as $name)
                            <option value="{{ $name }}" {{ $name === $current ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                        @if(!$inList)
                            <option value="{{ $current }}" selected>{{ $current }} (not in models table)</option>
                        @endif
                    </select>
                    @if(!$status['ok'])
                        <p class="text-[11px] text-red-300 flex items-start gap-1.5">
                            <i class="fas fa-triangle-exclamation mt-0.5"></i>
                            <span>{{ $status['message'] }}</span>
                        </p>
                    @else
                        <p class="text-[11px] text-white/30">Spend tagged <span class="font-mono">{{ $f }}</span>.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Per-feature model change history --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <div>
            <h3 class="font-semibold text-white">Feature model history</h3>
            <p class="text-xs text-white/40">
                Last {{ count($featureModelHistory) }} change{{ count($featureModelHistory) === 1 ? '' : 's' }}
                to per-feature models. Use this to trace sudden cost changes back to a specific switch.
            </p>
        </div>
        @if(count($featureModelHistory) === 0)
            <p class="text-xs text-white/40 italic">No changes recorded yet.</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                    <th class="text-left py-2">When</th>
                    <th class="text-left">Feature</th>
                    <th class="text-left">From</th>
                    <th class="text-left">To</th>
                    <th class="text-left">By</th>
                </tr></thead>
                <tbody>
                @foreach($featureModelHistory as $row)
                    <tr class="border-t border-white/5 align-top">
                        <td class="py-2 text-white/70 whitespace-nowrap" title="{{ $row->created_at?->toDateTimeString() }}">
                            {{ $row->created_at?->diffForHumans() }}
                        </td>
                        <td class="text-white/80 font-mono">{{ $row->feature }}</td>
                        <td class="text-white/60 font-mono">{{ $row->old_model ?? '—' }}</td>
                        <td class="text-amber-300 font-mono">{{ $row->new_model ?? '—' }}</td>
                        <td class="text-white/60">
                            {{ $row->admin_name ?? ($row->admin_id ? '#' . $row->admin_id : 'system') }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ── Voice Assistant ─────────────────────────────────── --}}
    <div class="bg-white/5 border border-white/10 rounded-2xl p-6 space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-white text-base font-semibold">AI Voice Assistant</h3>
                <p class="text-white/50 text-xs mt-1">Whisper transcribes, GPT reasons &amp; calls tools, ElevenLabs speaks. Each stage is metered separately and charged from the coin wallet.</p>
            </div>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="voice_enabled" value="0">
                <input type="checkbox" name="voice_enabled" value="1" {{ $voiceEnabled ? 'checked' : '' }}
                       class="h-4 w-4 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500">
                <span class="text-white/80 text-sm">Enabled</span>
            </label>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-white/70 text-xs">Whisper API key</label>
                @if($hasWhisperKey)
                    <div class="flex items-center gap-2 mt-1">
                        <code class="text-white/60 text-xs bg-white/5 border border-white/10 rounded px-2 py-1 flex-1">{{ $maskedWhisperKey }}</code>
                        <label class="inline-flex items-center gap-1 text-xs text-red-300"><input type="checkbox" name="clear_whisper_api_key" value="1"> clear</label>
                    </div>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'whisper_api_key',
                    'placeholder' => $hasWhisperKey ? 'Replace key…' : 'sk-…',
                    'autocomplete' => 'new-password',
                    'wrapClass' => 'mt-1',
                    'inputClass' => 'w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm',
                ])
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <button type="button" onclick="testVoiceKey(this, 'whisper')"
                            class="px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/15 text-white rounded-lg text-xs font-medium">
                        <i class="fas fa-plug mr-1"></i> Test
                    </button>
                    <span id="whisper-test-result" class="text-xs text-white/50"></span>
                </div>
                <p class="text-white/40 text-[11px] mt-1">Falls back to the main OpenAI key when blank. Test lists OpenAI models to validate the key — no coins charged.</p>
            </div>
            <div>
                <label class="text-white/70 text-xs">Whisper model</label>
                <input type="text" name="whisper_model" value="{{ $whisperModel }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            </div>
            <div>
                <label class="text-white/70 text-xs">Voice GPT model (tool-calling)</label>
                <input type="text" name="voice_gpt_model" value="{{ $voiceGptModel }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
                <p class="text-white/40 text-[11px] mt-1">Cost is billed under <code>voice_llm</code> using the model's per-1k token rates.</p>
            </div>
            <div>
                <label class="text-white/70 text-xs">ElevenLabs API key</label>
                @if($hasElevenKey)
                    <div class="flex items-center gap-2 mt-1">
                        <code class="text-white/60 text-xs bg-white/5 border border-white/10 rounded px-2 py-1 flex-1">{{ $maskedElevenKey }}</code>
                        <label class="inline-flex items-center gap-1 text-xs text-red-300"><input type="checkbox" name="clear_elevenlabs_api_key" value="1"> clear</label>
                    </div>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'elevenlabs_api_key',
                    'placeholder' => $hasElevenKey ? 'Replace key…' : 'xi-…',
                    'autocomplete' => 'new-password',
                    'wrapClass' => 'mt-1',
                    'inputClass' => 'w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm',
                ])
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <button type="button" onclick="testVoiceKey(this, 'elevenlabs')"
                            class="px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/15 text-white rounded-lg text-xs font-medium">
                        <i class="fas fa-plug mr-1"></i> Test
                    </button>
                    <span id="elevenlabs-test-result" class="text-xs text-white/50"></span>
                </div>
                <p class="text-white/40 text-[11px] mt-1">Checks the ElevenLabs voices endpoint to validate the key — no coins charged.</p>
            </div>
            <div>
                <label class="text-white/70 text-xs">ElevenLabs voice id</label>
                <input type="text" name="elevenlabs_voice_id" value="{{ $elevenVoiceId }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            </div>
            <div>
                <label class="text-white/70 text-xs">ElevenLabs model</label>
                <input type="text" name="elevenlabs_model" value="{{ $elevenModel }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            </div>
            <div>
                <label class="text-white/70 text-xs">STT coins per minute of audio</label>
                <input type="number" min="0" step="0.01" name="voice_price_stt" value="{{ $voicePriceStt }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            </div>
            <div>
                <label class="text-white/70 text-xs">TTS coins per 1 000 characters</label>
                <input type="number" min="0" step="0.01" name="voice_price_tts" value="{{ $voicePriceTts }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            </div>
            <div>
                <label class="text-white/70 text-xs">Rate limit (turns / user / minute)</label>
                <input type="number" min="1" max="600" name="voice_rate_per_minute" value="{{ $voiceRateLimit }}"
                       class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            </div>
        </div>

        @php
            try { $allPlans = \App\Modules\Admin\Models\Plan::orderBy('name')->get(['slug','name']); }
            catch (\Throwable $e) { $allPlans = collect(); }
        @endphp
        @if($allPlans->count())
            <div>
                <label class="text-white/70 text-xs">Allow Voice on plans <span class="text-white/40">(none ticked = all plans)</span></label>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach($allPlans as $plan)
                        <label class="inline-flex items-center gap-2 text-xs text-white/80 bg-white/5 border border-white/10 rounded-full px-3 py-1">
                            <input type="checkbox" name="voice_plans[]" value="{{ $plan->slug }}"
                                   @checked(in_array($plan->slug, $voicePlans, true))
                                   class="h-3.5 w-3.5 rounded border-white/20 bg-white/5 text-blue-500">
                            {{ $plan->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- AI Artistic QR (Replicate QR-ControlNet) --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
        <div>
            <h3 class="font-semibold text-white">AI Artistic QR</h3>
            <p class="text-xs text-white/40">
                Weaves a scannable QR into AI-generated artwork via Replicate's QR-ControlNet model.
                Without a token the QR Studio "AI Artistic" tab runs in preview / disabled mode.
            </p>
        </div>

        @include('admin.partials.help-note', [
            'body' => '<strong>Getting a Replicate API token</strong>
                <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                    <li>Sign up or log in at <a class="underline" href="https://replicate.com/" target="_blank" rel="noopener">replicate.com</a>.</li>
                    <li>Go to <a class="underline" href="https://replicate.com/account/api-tokens" target="_blank" rel="noopener">Account → API Tokens</a> and create a new token. Tokens start with <code>r8_…</code>.</li>
                    <li>Replicate bills per-second of GPU time used by each prediction run — add a payment method in your Replicate account before enabling this in production.</li>
                    <li>Without a token, the QR Studio <em>AI Artistic</em> tab is hidden or shown in preview mode — no charges occur.</li>
                </ol>',
        ])

        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Replicate API token</label>
            @if($hasStoredReplicateKey)
                <p class="text-xs text-white/60 mb-2">Stored: <span class="font-mono text-amber-300">{{ $maskedReplicateKey }}</span></p>
            @elseif($hasReplicateKey)
                <p class="text-xs text-white/60 mb-2">Using environment token <span class="font-mono text-amber-300">{{ $maskedReplicateKey }}</span> (<code>REPLICATE_API_TOKEN</code>).</p>
            @else
                <p class="text-xs text-amber-300/80 mb-2">No token configured — AI Artistic QR is in preview / disabled mode.</p>
            @endif
            @include('common.partials.password-field', [
                'name' => 'replicate_api_key',
                'autocomplete' => 'off',
                'placeholder' => $hasStoredReplicateKey ? 'Paste a new token to replace' : 'r8_…',
                'inputClass' => 'w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
            ])
            @if($hasStoredReplicateKey)
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                    <input type="hidden" name="clear_replicate_api_key" value="0">
                    <input type="checkbox" name="clear_replicate_api_key" value="1" class="accent-red-500">
                    Remove the stored token (fall back to the environment value, if any)
                </label>
            @endif
            <p class="text-[11px] text-white/30 mt-1">Encrypted at rest. Falls back to <code>REPLICATE_API_TOKEN</code> when no token is stored here.</p>
        </div>

        <div class="pt-4 border-t border-white/10 space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" onclick="testReplicateConnection(this)"
                        class="px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/15 text-white rounded-lg text-xs font-medium">
                    <i class="fas fa-plug mr-1"></i> Test connection
                </button>
                <span id="replicate-test-result" class="text-xs text-white/50"></span>
            </div>
            <p class="text-[11px] text-white/30">Makes a lightweight authenticated call to Replicate to confirm the token works. No prediction is queued and no coins are charged. Tests the token typed above, or the stored/environment token when the field is blank.</p>
        </div>

        <div class="pt-4 border-t border-white/10">
            <label class="text-white/70 text-xs">Coins charged per generation</label>
            <input type="number" min="1" max="100000" step="1" name="qr_art_coins" value="{{ $qrArtCoins }}"
                   class="mt-1 w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white text-sm">
            <p class="text-[11px] text-white/30 mt-1">Flat coin price billed from the user's wallet for each AI Artistic QR (auto-refunded if generation fails).</p>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('admin.ai-usage.index') }}" class="text-xs text-blue-300 hover:underline">
            View AI usage report →
        </a>
        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            Save settings
        </button>
    </div>
</form>

<script>
async function testOpenAiConnection(btn) {
    const result = document.getElementById('openai-test-result');
    if (!result) return;
    const keyField = document.querySelector('input[name="openai_api_key"]');
    const tokenField = document.querySelector('input[name="_token"]');
    result.className = 'text-xs text-white/50';
    result.textContent = 'Testing…';
    btn.disabled = true;
    try {
        const res = await fetch(@json(route('admin.ai-engine.test')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': tokenField ? tokenField.value : '',
            },
            body: JSON.stringify({ openai_api_key: keyField ? keyField.value : '' }),
        });
        const data = await res.json().catch(function () {
            return { ok: false, message: 'Unexpected response from server.' };
        });
        result.className = 'text-xs ' + (data.ok ? 'text-emerald-300' : 'text-red-300');
        result.innerHTML = '<i class="fas ' + (data.ok ? 'fa-circle-check' : 'fa-circle-xmark') + ' mr-1"></i>';
        result.appendChild(document.createTextNode(data.message || (data.ok ? 'Connection OK.' : 'Connection failed.')));
    } catch (e) {
        result.className = 'text-xs text-red-300';
        result.textContent = 'Request failed: ' + e.message;
    } finally {
        btn.disabled = false;
    }
}

async function testReplicateConnection(btn) {
    const result = document.getElementById('replicate-test-result');
    if (!result) return;
    const keyField = document.querySelector('input[name="replicate_api_key"]');
    const tokenField = document.querySelector('input[name="_token"]');
    result.className = 'text-xs text-white/50';
    result.textContent = 'Testing…';
    btn.disabled = true;
    try {
        const res = await fetch(@json(route('admin.ai-engine.test-replicate')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': tokenField ? tokenField.value : '',
            },
            body: JSON.stringify({ replicate_api_key: keyField ? keyField.value : '' }),
        });
        const data = await res.json().catch(function () {
            return { ok: false, message: 'Unexpected response from server.' };
        });
        result.className = 'text-xs ' + (data.ok ? 'text-emerald-300' : 'text-red-300');
        result.innerHTML = '<i class="fas ' + (data.ok ? 'fa-circle-check' : 'fa-circle-xmark') + ' mr-1"></i>';
        result.appendChild(document.createTextNode(data.message || (data.ok ? 'Connection OK.' : 'Connection failed.')));
    } catch (e) {
        result.className = 'text-xs text-red-300';
        result.textContent = 'Request failed: ' + e.message;
    } finally {
        btn.disabled = false;
    }
}

const VOICE_KEY_TESTS = {
    whisper: {
        url: @json(route('admin.ai-engine.test-whisper')),
        field: 'whisper_api_key',
        result: 'whisper-test-result',
    },
    elevenlabs: {
        url: @json(route('admin.ai-engine.test-elevenlabs')),
        field: 'elevenlabs_api_key',
        result: 'elevenlabs-test-result',
    },
};

async function testVoiceKey(btn, which) {
    const cfg = VOICE_KEY_TESTS[which];
    if (!cfg) return;
    const result = document.getElementById(cfg.result);
    if (!result) return;
    const keyField = document.querySelector('input[name="' + cfg.field + '"]');
    const tokenField = document.querySelector('input[name="_token"]');
    result.className = 'text-xs text-white/50';
    result.textContent = 'Testing…';
    btn.disabled = true;
    try {
        const body = {};
        body[cfg.field] = keyField ? keyField.value : '';
        const res = await fetch(cfg.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': tokenField ? tokenField.value : '',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(function () {
            return { ok: false, message: 'Unexpected response from server.' };
        });
        result.className = 'text-xs ' + (data.ok ? 'text-emerald-300' : 'text-red-300');
        result.innerHTML = '<i class="fas ' + (data.ok ? 'fa-circle-check' : 'fa-circle-xmark') + ' mr-1"></i>';
        result.appendChild(document.createTextNode(data.message || (data.ok ? 'Connection OK.' : 'Connection failed.')));
    } catch (e) {
        result.className = 'text-xs text-red-300';
        result.textContent = 'Request failed: ' + e.message;
    } finally {
        btn.disabled = false;
    }
}

function addModelRow() {
    const tb = document.getElementById('models-tbody');
    const i = tb.children.length;
    const row = document.createElement('tr');
    row.className = 'border-t border-white/5 align-top';
    row.setAttribute('data-model-row', '');
    row.setAttribute('data-features', '');
    row.innerHTML = `
        <td class="py-2"><input name="models[${i}][name]" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
        <td class="py-2"><span class="text-white/30 text-xs">—</span></td>
        <td><select name="models[${i}][kind]" class="bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"><option value="chat">chat</option><option value="embedding">embedding</option></select></td>
        <td><input type="hidden" name="models[${i}][enabled]" value="0"><input type="checkbox" data-enabled-toggle name="models[${i}][enabled]" value="1" checked class="accent-blue-500"><p data-disable-warning class="hidden mt-1 text-[11px] text-amber-300 flex items-start gap-1"><i class="fas fa-triangle-exclamation mt-0.5"></i><span data-disable-warning-text></span></p></td>
        <td class="text-right"><input type="number" min="0" step="0.01" name="models[${i}][in_coins_per_1k]" value="0" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><input type="number" min="0" step="0.01" name="models[${i}][out_coins_per_1k]" value="0" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>`;
    tb.appendChild(row);
}

(function () {
    function rowsByModelName() {
        const map = {};
        document.querySelectorAll('[data-model-row]').forEach(function (row) {
            const nameInput = row.querySelector('input[name^="models["][name$="[name]"]');
            const name = nameInput ? nameInput.value.trim() : '';
            if (!name) return;
            (map[name] = map[name] || []).push(row);
        });
        return map;
    }
    function renderBadges(row, features) {
        const cell = row.children[1];
        if (!cell) return;
        if (!features.length) {
            cell.innerHTML = '<span class="text-white/30 text-xs">—</span>';
            return;
        }
        cell.innerHTML = '<div class="flex flex-wrap gap-1">' + features.map(function (f) {
            return '<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-500/15 border border-blue-500/30 text-blue-200 text-[10px] font-mono uppercase tracking-wider">' + f + '</span>';
        }).join('') + '</div>';
    }
    function refreshDisableWarning(row) {
        const features = (row.getAttribute('data-features') || '').split(',').filter(Boolean);
        const toggle = row.querySelector('[data-enabled-toggle]');
        const warn = row.querySelector('[data-disable-warning]');
        if (!toggle || !warn) return;
        if (features.length && !toggle.checked) {
            warn.querySelector('[data-disable-warning-text]').textContent =
                'In use by ' + features.join(', ') + ' — those features will fail until reassigned.';
            warn.classList.remove('hidden');
        } else {
            warn.classList.add('hidden');
        }
    }
    function syncFeatureUsage() {
        const byModel = rowsByModelName();
        const usage = {};
        document.querySelectorAll('[data-feature-select]').forEach(function (sel) {
            const feat = sel.getAttribute('data-feature-select');
            const target = sel.value.trim();
            if (target) (usage[target] = usage[target] || []).push(feat);
        });
        document.querySelectorAll('[data-model-row]').forEach(function (row) {
            const nameInput = row.querySelector('input[name^="models["][name$="[name]"]');
            const name = nameInput ? nameInput.value.trim() : '';
            const features = name && usage[name] ? usage[name] : [];
            row.setAttribute('data-features', features.join(','));
            renderBadges(row, features);
            refreshDisableWarning(row);
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('[data-enabled-toggle]')) {
            const row = e.target.closest('[data-model-row]');
            if (row) refreshDisableWarning(row);
        }
        if (e.target && e.target.matches('[data-feature-select]')) {
            syncFeatureUsage();
        }
        if (e.target && e.target.matches('input[name^="models["][name$="[name]"]')) {
            syncFeatureUsage();
        }
    });
})();
</script>
@endsection
