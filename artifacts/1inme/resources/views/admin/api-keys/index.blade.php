@extends('admin.layouts.app')
@section('title', 'API Keys & Plugins')
@section('page-title', 'API Keys & Plugins')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'bg-red-500/10 border-red-500/20 text-red-300',
            default => 'bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@section('content')
<div class="max-w-5xl space-y-6">

    <p class="text-sm text-white/50">
        Manage the platform's outbound API credentials and plugins in one place. Secrets are
        encrypted at rest and never displayed back &mdash; leave a secret field blank to keep the
        stored value unchanged. Each group falls back to the environment configuration until you
        save a value here.
    </p>

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
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
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fab fa-whatsapp text-emerald-400"></i> WhatsApp Cloud API
                    </h3>
                    <p class="text-xs text-white/40">Used to deliver login &amp; verification OTPs over WhatsApp. Without credentials, OTPs run in preview mode (logged, not sent).</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($waStatus['tone']) }}">
                    {{ $waStatus['label'] }}
                </span>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Phone number ID</label>
                    <input type="text" name="wa_phone_number_id" value="{{ old('wa_phone_number_id', $waPhoneNumberId) }}"
                           autocomplete="off" placeholder="123456789012345"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Access token</label>
                    @if($waHasToken)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $waMaskedToken }}</span></p>
                    @endif
                    <input type="password" name="wa_access_token" autocomplete="off"
                           placeholder="{{ $waHasToken ? 'Paste a new token to replace' : 'EAAB…' }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @if($waHasToken)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                            <input type="hidden" name="clear_wa_access_token" value="0">
                            <input type="checkbox" name="clear_wa_access_token" value="1" class="accent-red-500">
                            Remove the stored token
                        </label>
                    @endif
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Template name</label>
                    <input type="text" name="wa_template_name" value="{{ old('wa_template_name', $waTemplate) }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Template language</label>
                    <input type="text" name="wa_template_language" value="{{ old('wa_template_language', $waLanguage) }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Graph API version</label>
                    <input type="text" name="wa_graph_version" value="{{ old('wa_graph_version', $waGraphVersion) }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                </div>
            </div>
            <p class="text-[11px] text-white/30">Token is encrypted at rest with the application key. Other fields are plain configuration.</p>
        </div>

        {{-- WhatsApp AI agent (inbound webhook) --------------------- --}}
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-robot text-emerald-400"></i> WhatsApp AI agent
                    </h3>
                    <p class="text-xs text-white/40">Lets verified, paid-plan users create &amp; edit links by messaging the WhatsApp number. Requires the Cloud API credentials above plus the inbound webhook below.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $waAgentEnabled ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' : 'bg-white/5 text-white/50 border-white/10' }}">
                    {{ $waAgentEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="wa_agent_enabled" value="0">
                <input type="checkbox" name="wa_agent_enabled" value="1" {{ $waAgentEnabled ? 'checked' : '' }}
                       class="w-4 h-4 accent-emerald-500">
                <span class="text-sm text-white">Enable the WhatsApp AI agent</span>
            </label>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Callback URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $waCallbackUrl }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white/70 font-mono">
                </div>
                <p class="text-[11px] text-white/30 mt-1">Set this as the webhook callback URL in the Meta app dashboard (subscribe to the <span class="font-mono">messages</span> field).</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Webhook verify token</label>
                    @if($waHasVerifyToken)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $waMaskedVerify }}</span></p>
                    @endif
                    <input type="password" name="wa_webhook_verify_token" autocomplete="off"
                           placeholder="{{ $waHasVerifyToken ? 'Paste to replace' : 'A secret string you choose' }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @if($waHasVerifyToken)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                            <input type="hidden" name="clear_wa_verify_token" value="0">
                            <input type="checkbox" name="clear_wa_verify_token" value="1" class="accent-red-500">
                            Remove the stored verify token
                        </label>
                    @endif
                    <p class="text-[11px] text-white/30 mt-1">Must match the "Verify token" entered in the Meta dashboard.</p>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">App secret</label>
                    @if($waHasAppSecret)
                        <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $waMaskedAppSecret }}</span></p>
                    @endif
                    <input type="password" name="wa_app_secret" autocomplete="off"
                           placeholder="{{ $waHasAppSecret ? 'Paste to replace' : 'Meta app secret' }}"
                           class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                    @if($waHasAppSecret)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                            <input type="hidden" name="clear_wa_app_secret" value="0">
                            <input type="checkbox" name="clear_wa_app_secret" value="1" class="accent-red-500">
                            Remove the stored app secret
                        </label>
                    @endif
                    <p class="text-[11px] text-white/30 mt-1">Used to verify the <span class="font-mono">X-Hub-Signature-256</span> on inbound payloads. Without it, signatures aren't enforced (preview mode).</p>
                </div>
            </div>
            <p class="text-[11px] text-white/30">Verify token &amp; app secret are encrypted at rest with the application key.</p>
        </div>

        {{-- Internal alerts (Slack / Discord) ---------------------- --}}
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-bell text-amber-400"></i> Internal alerts
                    </h3>
                    <p class="text-xs text-white/40">Post system &amp; team alerts (downtime, broadcasts, health notices) to a Slack and/or Discord incoming webhook.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($alertsStatus['tone']) }}">
                    {{ $alertsStatus['label'] }}
                </span>
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="alerts_enabled" value="0">
                <input type="checkbox" name="alerts_enabled" value="1" {{ $alertsEnabled ? 'checked' : '' }}
                       class="w-4 h-4 accent-blue-500">
                <span class="text-sm text-white">Enable alert delivery</span>
            </label>

            {{-- Per-category alert toggles ----------------------------- --}}
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4 space-y-3">
                <div>
                    <p class="text-xs uppercase tracking-wider text-white/40">Which alerts to send</p>
                    <p class="text-[11px] text-white/30 mt-0.5">Mute lower-severity categories to cut alert fatigue. Critical payment alerts are always sent.</p>
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
                                <span class="text-sm text-white flex items-center gap-2">
                                    {{ $cat['label'] }}
                                    @if($cat['always_on'])
                                        <span class="px-1.5 py-0.5 rounded-md bg-red-500/15 text-red-300 text-[10px] font-medium uppercase tracking-wide">Always on</span>
                                    @endif
                                </span>
                                <span class="block text-[11px] text-white/40">{{ $cat['desc'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Slack incoming webhook URL</label>
                @if($slackHasUrl)
                    <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $slackMasked }}</span></p>
                @endif
                <input type="password" name="slack_webhook_url" autocomplete="off"
                       placeholder="{{ $slackHasUrl ? 'Paste a new URL to replace' : 'https://hooks.slack.com/services/…' }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                @if($slackHasUrl)
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_slack_webhook" value="0">
                        <input type="checkbox" name="clear_slack_webhook" value="1" class="accent-red-500">
                        Remove the stored Slack webhook
                    </label>
                @endif
            </div>

            <div>
                <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Discord webhook URL</label>
                @if($discordHasUrl)
                    <p class="text-xs text-white/60 mb-1">Stored: <span class="font-mono text-amber-300">{{ $discordMasked }}</span></p>
                @endif
                <input type="password" name="discord_webhook_url" autocomplete="off"
                       placeholder="{{ $discordHasUrl ? 'Paste a new URL to replace' : 'https://discord.com/api/webhooks/…' }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                @if($discordHasUrl)
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                        <input type="hidden" name="clear_discord_webhook" value="0">
                        <input type="checkbox" name="clear_discord_webhook" value="1" class="accent-red-500">
                        Remove the stored Discord webhook
                    </label>
                @endif
            </div>
            <p class="text-[11px] text-white/30">Webhook URLs are treated as secrets and encrypted at rest. Slack falls back to the <span class="font-mono">LOG_SLACK_WEBHOOK_URL</span> environment value.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Save settings
            </button>
            <span class="text-xs text-white/30">Save before sending a test so the new values are used.</span>
        </div>
    </form>

    {{-- ============================================================ --}}
    {{-- Test actions (separate forms)                                --}}
    {{-- ============================================================ --}}
    <div class="grid sm:grid-cols-2 gap-6">
        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="font-semibold text-white text-sm">Test WhatsApp delivery</h3>
            <p class="text-xs text-white/40">Sends a sample code to a number through the live OTP path (or logs it in preview mode).</p>
            <form method="POST" action="{{ route('admin.api-keys.test-whatsapp') }}" class="flex gap-2">
                @csrf
                <input type="text" name="test_number" required placeholder="+15551234567"
                       class="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
                <button type="submit" class="px-3 py-2 bg-emerald-600 text-white rounded-xl text-xs font-medium hover:bg-emerald-700 whitespace-nowrap">
                    <i class="fas fa-paper-plane mr-1"></i> Send test
                </button>
            </form>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="font-semibold text-white text-sm">Test internal alerts</h3>
            <p class="text-xs text-white/40">Posts a sample alert to verify the saved webhook works (ignores the enable toggle).</p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.api-keys.test-alert') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="channel" value="slack">
                    <button type="submit" {{ $slackHasUrl ? '' : 'disabled' }}
                            class="w-full px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap {{ $slackHasUrl ? 'bg-[#4A154B] text-white hover:opacity-90' : 'bg-white/5 text-white/30 cursor-not-allowed' }}">
                        <i class="fab fa-slack mr-1"></i> Test Slack
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.api-keys.test-alert') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="channel" value="discord">
                    <button type="submit" {{ $discordHasUrl ? '' : 'disabled' }}
                            class="w-full px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap {{ $discordHasUrl ? 'bg-[#5865F2] text-white hover:opacity-90' : 'bg-white/5 text-white/30 cursor-not-allowed' }}">
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
            <h3 class="font-semibold text-white">Other key-bearing systems</h3>
            <p class="text-xs text-white/40">These have dedicated editors. Status is shown here for a single-pane overview.</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            @foreach($overview as $item)
                <a href="{{ $item['route'] }}" class="block rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-sm font-medium text-white">{{ $item['label'] }}</span>
                        <span class="shrink-0 px-2 py-0.5 rounded-md border text-[10px] font-medium {{ $toneClass($item['status']['tone']) }}">
                            {{ $item['status']['label'] }}
                        </span>
                    </div>
                    <p class="text-[11px] text-white/40">{{ $item['desc'] }}</p>
                    <span class="text-[11px] text-blue-300 mt-2 inline-flex items-center gap-1">Open <i class="fas fa-arrow-right text-[9px]"></i></span>
                </a>
            @endforeach
        </div>
    </div>

</div>
@endsection
