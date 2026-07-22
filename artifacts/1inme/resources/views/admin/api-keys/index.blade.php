@extends('admin.layouts.app')
@section('title', 'API Keys & Plugins')
@section('page-title', 'API Keys & Plugins')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'ak-tone-green bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'ak-tone-amber bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'ak-tone-red bg-red-500/10 border-red-500/20 text-red-300',
            default => 'ak-tone-neutral bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@section('content')
<div class="max-w-5xl space-y-6">

    <p class="ak-strong text-sm text-white/50">
        Manage the platform's outbound API credentials and plugins in one place. Secrets are
        encrypted at rest and never displayed back &mdash; leave a secret field blank to keep the
        stored value unchanged. Each group falls back to the environment configuration until you
        save a value here.
    </p>

    @if ($errors->any())
        <div class="ak-error p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Main settings form: WhatsApp + Internal alerts               --}}
    {{-- ============================================================ --}}
    <form method="POST" action="{{ route('admin.api-keys.update') }}" class="space-y-6">
        @csrf @method('PUT')

        {{-- WhatsApp Cloud API ------------------------------------- --}}
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-whatsapp text-emerald-400 ak-green"></i> WhatsApp Cloud API
                    </h3>
                    <p class="ak-muted text-xs text-white/40">Used to deliver login &amp; verification OTPs over WhatsApp. Without credentials, OTPs run in preview mode (logged, not sent).</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($waStatus['tone']) }}">
                    {{ $waStatus['label'] }}
                </span>
            </div>

            @include('admin.partials.help-note', [
                'body' => '<strong>How to get WhatsApp Cloud API credentials</strong>
                    <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                        <li>Create or open an app at <a class="underline" href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers → My Apps</a>. Choose <em>Business</em> as the app type and add the <strong>WhatsApp</strong> product.</li>
                        <li><strong>Phone Number ID</strong>, shown on the <em>WhatsApp → API Setup</em> page of your Meta app, next to the test or registered business number.</li>
                        <li><strong>Access token</strong>, for testing, use the temporary token on the API Setup page. For production, generate a <em>System User</em> token in <a class="underline" href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">Meta Business Suite → Settings → System Users</a> with the <code>whatsapp_business_messaging</code> permission.</li>
                        <li>The <strong>Graph API version</strong> field should match the API version shown in the Meta documentation; default is <code>v19.0</code>.</li>
                    </ol>',
            ])

            <div class="ak-warn-banner glass rounded-2xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-200/85 space-y-1">
                <div class="flex items-start gap-2">
                    <i class="fas fa-triangle-exclamation text-amber-400 mt-0.5 shrink-0 ak-amber"></i>
                    <div>
                        <strong>OTP message templates must be pre-approved in Meta Business Suite.</strong>
                        The template name and language entered below must exactly match an <em>approved</em> template in your WhatsApp Business account. Unapproved or mismatched templates cause delivery to fail silently.
                        <a class="underline ml-1" href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener">Manage templates →</a>
                    </div>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Phone number ID</label>
                    <input type="text" name="wa_phone_number_id" value="{{ old('wa_phone_number_id', $waPhoneNumberId) }}"
                           autocomplete="off" placeholder="123456789012345"
                           class="ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Access token</label>
                    @if($waHasToken)
                        <p class="ak-stored text-xs text-white/60 mb-1">Stored: <span class="ak-masked font-mono text-amber-300">{{ $waMaskedToken }}</span></p>
                    @endif
                    @include('common.partials.password-field', [
                        'name' => 'wa_access_token',
                        'autocomplete' => 'off',
                        'placeholder' => $waHasToken ? 'Paste a new token to replace' : 'EAAB…',
                        'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                    ])
                    @if($waHasToken)
                        <label class="mt-2 inline-flex items-center gap-2 ak-stored text-xs text-white/60">
                            <input type="hidden" name="clear_wa_access_token" value="0">
                            <input type="checkbox" name="clear_wa_access_token" value="1" class="accent-red-500">
                            Remove the stored token
                        </label>
                    @endif
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Template name</label>
                    <input type="text" name="wa_template_name" value="{{ old('wa_template_name', $waTemplate) }}"
                           class="ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="ak-note text-[11px] text-white/30 mt-1">Exact name of the approved WhatsApp message template.</p>
                </div>
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Template language</label>
                    <input type="text" name="wa_template_language" value="{{ old('wa_template_language', $waLanguage) }}"
                           class="ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="ak-note text-[11px] text-white/30 mt-1">Language code of the approved template, e.g. <code>en_US</code>, <code>en</code>.</p>
                </div>
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Graph API version</label>
                    <input type="text" name="wa_graph_version" value="{{ old('wa_graph_version', $waGraphVersion) }}"
                           class="ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    <p class="ak-note text-[11px] text-white/30 mt-1">Meta Graph API version, e.g. <code>v19.0</code>.</p>
                </div>
            </div>
            <p class="ak-note text-[11px] text-white/30">Token is encrypted at rest with the application key. Other fields are plain configuration.</p>
        </div>

        {{-- WhatsApp AI agent (inbound webhook) --------------------- --}}
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-robot text-emerald-400 ak-green"></i> WhatsApp AI agent
                    </h3>
                    <p class="ak-muted text-xs text-white/40">Lets verified, paid-plan users create &amp; edit links by messaging the WhatsApp number. Requires the Cloud API credentials above plus the inbound webhook below.</p>
                    @include('admin.partials.help-note', [
                        'body' => '<strong>Webhook setup in Meta:</strong> in your Meta app → WhatsApp → Configuration, set the <em>Callback URL</em> to the value shown below and enter your <em>Verify token</em>. Subscribe to the <code>messages</code> webhook field. The <em>App Secret</em> is shown in your Meta app\'s <em>App Settings → Basic</em> page and is used to verify the <code>X-Hub-Signature-256</code> on inbound payloads.',
                    ])
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $waAgentEnabled ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30 ak-green' : 'bg-white/5 text-white/50 border-white/10 ak-muted' }}">
                    {{ $waAgentEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="wa_agent_enabled" value="0">
                <input type="checkbox" name="wa_agent_enabled" value="1" {{ $waAgentEnabled ? 'checked' : '' }}
                       class="w-4 h-4 accent-emerald-500">
                <span class="ak-strong text-sm text-white">Enable the WhatsApp AI agent</span>
            </label>

            @include('admin.partials.copy-uri', [
                'label' => 'Callback URL, set this as the webhook in Meta → WhatsApp → Configuration (subscribe to the messages field)',
                'value' => $waCallbackUrl,
            ])

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Webhook verify token</label>
                    @if($waHasVerifyToken)
                        <p class="ak-stored text-xs text-white/60 mb-1">Stored: <span class="ak-masked font-mono text-amber-300">{{ $waMaskedVerify }}</span></p>
                    @endif
                    @include('common.partials.password-field', [
                        'name' => 'wa_webhook_verify_token',
                        'autocomplete' => 'off',
                        'placeholder' => $waHasVerifyToken ? 'Paste to replace' : 'A secret string you choose',
                        'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                    ])
                    @if($waHasVerifyToken)
                        <label class="mt-2 inline-flex items-center gap-2 ak-stored text-xs text-white/60">
                            <input type="hidden" name="clear_wa_verify_token" value="0">
                            <input type="checkbox" name="clear_wa_verify_token" value="1" class="accent-red-500">
                            Remove the stored verify token
                        </label>
                    @endif
                    <p class="ak-note text-[11px] text-white/30 mt-1">Must match the "Verify token" entered in the Meta dashboard.</p>
                </div>
                <div>
                    <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">App secret</label>
                    @if($waHasAppSecret)
                        <p class="ak-stored text-xs text-white/60 mb-1">Stored: <span class="ak-masked font-mono text-amber-300">{{ $waMaskedAppSecret }}</span></p>
                    @endif
                    @include('common.partials.password-field', [
                        'name' => 'wa_app_secret',
                        'autocomplete' => 'off',
                        'placeholder' => $waHasAppSecret ? 'Paste to replace' : 'Meta app secret',
                        'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                    ])
                    @if($waHasAppSecret)
                        <label class="mt-2 inline-flex items-center gap-2 ak-stored text-xs text-white/60">
                            <input type="hidden" name="clear_wa_app_secret" value="0">
                            <input type="checkbox" name="clear_wa_app_secret" value="1" class="accent-red-500">
                            Remove the stored app secret
                        </label>
                    @endif
                    <p class="ak-note text-[11px] text-white/30 mt-1">Used to verify the <span class="font-mono">X-Hub-Signature-256</span> on inbound payloads. Without it, signatures aren't enforced (preview mode).</p>
                </div>
            </div>
            <p class="ak-note text-[11px] text-white/30">Verify token &amp; app secret are encrypted at rest with the application key.</p>
        </div>

        {{-- Internal alerts (Slack / Discord) ---------------------- --}}
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="ak-strong font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-bell text-amber-400 ak-amber"></i> Internal alerts
                    </h3>
                    <p class="ak-muted text-xs text-white/40">Post system &amp; team alerts (downtime, broadcasts, health notices) to a Slack and/or Discord incoming webhook.</p>

                    @include('admin.partials.help-note', [
                        'body' => '<strong>Getting incoming webhook URLs</strong>
                            <ul class="list-disc pl-4 mt-1 space-y-0.5">
                                <li><strong>Slack:</strong> go to <a class="underline" href="https://api.slack.com/apps" target="_blank" rel="noopener">api.slack.com/apps</a>, create an app (or open an existing one), enable <em>Incoming Webhooks</em> under Features, and click <em>Add New Webhook to Workspace</em>. Pick a channel and copy the <code>https://hooks.slack.com/services/…</code> URL.</li>
                                <li><strong>Discord:</strong> open a Discord server you manage, go to channel Settings → Integrations → Webhooks → New Webhook. Name it, choose a channel, then copy the <code>https://discord.com/api/webhooks/…</code> URL.</li>
                            </ul>',
                    ])
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($alertsStatus['tone']) }}">
                    {{ $alertsStatus['label'] }}
                </span>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="alerts_enabled" value="0">
                <input type="checkbox" name="alerts_enabled" value="1" {{ $alertsEnabled ? 'checked' : '' }}
                       class="w-4 h-4 accent-blue-500">
                <span class="ak-strong text-sm text-white">Enable alert delivery</span>
            </label>

            {{-- Per-category alert toggles ----------------------------- --}}
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4 space-y-3">
                <div>
                    <p class="ak-label text-xs uppercase tracking-wider text-white/40">Which alerts to send</p>
                    <p class="ak-note text-[11px] text-white/30 mt-0.5">Mute lower-severity categories to cut alert fatigue. Critical payment alerts are always sent.</p>
                </div>
                <div class="space-y-3">
                    @foreach($alertCategories as $cat)
                        <label class="flex items-start gap-3 {{ $cat['always_on'] ? '' : 'cursor-pointer' }}">
                            @if($cat['always_on'])
                                <input type="checkbox" checked disabled class="mt-0.5 w-4 h-4 accent-blue-500 opacity-60">
                            @else
                                <input type="hidden" name="alert_cat_{{ $cat['key'] }}" value="0">
                                <input type="checkbox" name="alert_cat_{{ $cat['key'] }}" value="1"
                                       {{ $cat['enabled'] ? 'checked' : '' }}
                                       class="mt-0.5 w-4 h-4 accent-blue-500">
                            @endif
                            <span class="min-w-0">
                                <span class="ak-strong text-sm text-white flex items-center gap-2">
                                    {{ $cat['label'] }}
                                    @if($cat['always_on'])
                                        <span class="ak-tone-red px-1.5 py-0.5 rounded-md bg-red-500/15 text-red-300 text-[10px] font-medium uppercase tracking-wide">Always on</span>
                                    @endif
                                </span>
                                <span class="block ak-muted text-[11px] text-white/40">{{ $cat['desc'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Slack incoming webhook URL</label>
                @if($slackHasUrl)
                    <p class="ak-stored text-xs text-white/60 mb-1">Stored: <span class="ak-masked font-mono text-amber-300">{{ $slackMasked }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'slack_webhook_url',
                    'autocomplete' => 'off',
                    'placeholder' => $slackHasUrl ? 'Paste a new URL to replace' : 'https://hooks.slack.com/services/…',
                    'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                @if($slackHasUrl)
                    <label class="mt-2 inline-flex items-center gap-2 ak-stored text-xs text-white/60">
                        <input type="hidden" name="clear_slack_webhook" value="0">
                        <input type="checkbox" name="clear_slack_webhook" value="1" class="accent-red-500">
                        Remove the stored Slack webhook
                    </label>
                @endif
            </div>

            <div>
                <label class="ak-label text-xs uppercase tracking-wider text-white/40 mb-1 block">Discord webhook URL</label>
                @if($discordHasUrl)
                    <p class="ak-stored text-xs text-white/60 mb-1">Stored: <span class="ak-masked font-mono text-amber-300">{{ $discordMasked }}</span></p>
                @endif
                @include('common.partials.password-field', [
                    'name' => 'discord_webhook_url',
                    'autocomplete' => 'off',
                    'placeholder' => $discordHasUrl ? 'Paste a new URL to replace' : 'https://discord.com/api/webhooks/…',
                    'inputClass' => 'ak-input w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white',
                ])
                @if($discordHasUrl)
                    <label class="mt-2 inline-flex items-center gap-2 ak-stored text-xs text-white/60">
                        <input type="hidden" name="clear_discord_webhook" value="0">
                        <input type="checkbox" name="clear_discord_webhook" value="1" class="accent-red-500">
                        Remove the stored Discord webhook
                    </label>
                @endif
            </div>
            <p class="ak-note text-[11px] text-white/30">Webhook URLs are treated as secrets and encrypted at rest. Slack falls back to the <span class="font-mono">LOG_SLACK_WEBHOOK_URL</span> environment value.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Save settings
            </button>
            <span class="ak-note text-xs text-white/30">Save before sending a test so the new values are used.</span>
        </div>
    </form>

    {{-- ============================================================ --}}
    {{-- Test actions (separate forms)                                --}}
    {{-- ============================================================ --}}
    <div class="grid sm:grid-cols-2 gap-6">
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="ak-strong font-semibold text-white text-sm">Test WhatsApp delivery</h3>
            <p class="ak-muted text-xs text-white/40">Sends a sample code to a number through the live OTP path (or logs it in preview mode).</p>
            <form method="POST" action="{{ route('admin.api-keys.test-whatsapp') }}" class="flex gap-2">
                @csrf
                <input type="text" name="test_number" required placeholder="+15551234567"
                       class="ak-input flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                <button type="submit" class="px-3 py-2 bg-emerald-600 text-white rounded-xl text-xs font-medium hover:bg-emerald-700 whitespace-nowrap">
                    <i class="fas fa-paper-plane mr-1"></i> Send test
                </button>
            </form>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="ak-strong font-semibold text-white text-sm">Test internal alerts</h3>
            <p class="ak-muted text-xs text-white/40">Posts a sample alert to verify the saved webhook works (ignores the enable toggle).</p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.api-keys.test-alert') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="channel" value="slack">
                    <button type="submit" {{ $slackHasUrl ? '' : 'disabled' }}
                            class="w-full px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap {{ $slackHasUrl ? 'bg-[#4A154B] text-white hover:opacity-90' : 'ak-btn-disabled bg-white/5 text-white/30 cursor-not-allowed' }}">
                        <i class="fab fa-slack mr-1"></i> Test Slack
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.api-keys.test-alert') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="channel" value="discord">
                    <button type="submit" {{ $discordHasUrl ? '' : 'disabled' }}
                            class="w-full px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap {{ $discordHasUrl ? 'bg-[#5865F2] text-white hover:opacity-90' : 'ak-btn-disabled bg-white/5 text-white/30 cursor-not-allowed' }}">
                        <i class="fab fa-discord mr-1"></i> Test Discord
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Read-only overview of the other key-bearing systems          --}}
    {{-- ============================================================ --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
        <div>
            <h3 class="ak-strong font-semibold text-white">Other key-bearing systems</h3>
            <p class="ak-muted text-xs text-white/40">These have dedicated editors. Status is shown here for a single-pane overview.</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            @foreach($overview as $item)
                <a href="{{ $item['route'] }}" class="block rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="ak-strong text-sm font-medium text-white">{{ $item['label'] }}</span>
                        <span class="shrink-0 px-2 py-0.5 rounded-md border text-[10px] font-medium {{ $toneClass($item['status']['tone']) }}">
                            {{ $item['status']['label'] }}
                        </span>
                    </div>
                    <p class="ak-muted text-[11px] text-white/40">{{ $item['desc'] }}</p>
                    <span class="ak-open-link text-[11px] text-blue-300 mt-2 inline-flex items-center gap-1">Open <i class="fas fa-arrow-right text-[9px]"></i></span>
                </a>
            @endforeach
        </div>
    </div>

</div>
@endsection
