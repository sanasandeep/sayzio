@extends('user.layouts.app')
@section('title', 'Subscription Settings')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('user.subscribers.index') }}" class="p-2 rounded-xl glass transition hover:bg-white/5" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Subscription Settings</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-muted);">Configure email & WhatsApp delivery settings</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-6 p-3 rounded-xl text-sm font-medium" style="background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.2);">
        <i class="fas fa-check-circle mr-1.5"></i>{{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.subscribers.settings.update') }}">
        @csrf

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, rgba(61,107,255,0.3), rgba(92,131,255,0.2));">
                    <i class="fas fa-envelope text-blue-400"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">Email Settings</h2>
                    <p class="text-xs" style="color: var(--text-muted);">Configure how emails are sent to leads</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">From Name</label>
                    <input type="text" name="email_from_name" value="{{ $subscription['email_from_name'] ?? '' }}" placeholder="Your Brand Name" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">From Email</label>
                    <input type="email" name="email_from_address" value="{{ $subscription['email_from_address'] ?? '' }}" placeholder="hello@yourdomain.com" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Reply-To Email</label>
                    <input type="email" name="email_reply_to" value="{{ $subscription['email_reply_to'] ?? '' }}" placeholder="Optional" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(99,102,241,0.15);">
                    <i class="fas fa-server" style="color: #818cf8;"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-semibold" style="color: var(--text-primary);">Email Connection</h2>
                    <p class="text-xs" style="color: var(--text-muted);">Send broadcasts through one of your saved SMTP connections</p>
                </div>
                <a href="{{ route('user.email-connections.index') }}" class="text-xs font-semibold underline flex-shrink-0" style="color: var(--accent);">
                    Manage connections
                </a>
            </div>

            @include('common.partials.integration-picker', [
                'name'       => 'email_connection_id',
                'kind'       => 'email',
                'value'      => $subscription['email_connection_id'] ?? null,
                'emptyLabel' => 'Platform default (or legacy SMTP below)',
            ])
            @error('email_connection_id') <p class="text-[11px] mt-1 text-red-400">{{ $message }}</p> @enderror
            <p class="text-[11px] mt-2" style="color: var(--text-faint);">
                When a connection is selected it takes priority over the legacy SMTP fields below. If it's ever disabled or incomplete, sending falls back to the platform mailer so your broadcasts still go out.
            </p>
        </div>

        <div class="glass rounded-2xl p-6 mb-6" x-data="{ legacyOpen: {{ !empty($subscription['smtp_host']) ? 'true' : 'false' }} }">
            <div class="flex items-center gap-3 {{ !empty($subscription['smtp_host']) ? 'mb-5' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(234,179,8,0.15);">
                    <i class="fas fa-server text-yellow-400"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-semibold" style="color: var(--text-primary);">Custom SMTP <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md align-middle" style="background: rgba(234,179,8,0.15); color: #eab308;">LEGACY</span></h2>
                    <p class="text-xs" style="color: var(--text-muted);">Inline mail-server settings — still honored, but only when no connection is selected above. Prefer a saved connection so it can be reused everywhere.</p>
                </div>
                <button type="button" @click="legacyOpen = !legacyOpen" class="text-xs font-semibold underline flex-shrink-0" style="color: var(--text-muted);">
                    <span x-text="legacyOpen ? 'Hide' : 'Show'"></span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" x-show="legacyOpen" x-cloak :class="legacyOpen ? 'mt-0' : ''">
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">SMTP Host</label>
                    <input type="text" name="smtp_host" value="{{ $subscription['smtp_host'] ?? '' }}" placeholder="smtp.gmail.com" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">SMTP Port</label>
                    <input type="number" name="smtp_port" value="{{ $subscription['smtp_port'] ?? '' }}" placeholder="587" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">SMTP Username</label>
                    <input type="text" name="smtp_username" value="{{ $subscription['smtp_username'] ?? '' }}" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">SMTP Password</label>
                    @include('common.partials.password-field', [
                        'name' => 'smtp_password',
                        'autocomplete' => 'new-password',
                        'value' => $subscription['smtp_password'] ?? '',
                        'inputClass' => 'w-full px-3 py-2.5 rounded-xl text-sm outline-none',
                        'inputStyle' => 'background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);',
                    ])
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Encryption</label>
                    <select name="smtp_encryption" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                        <option value="tls" {{ ($subscription['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="ssl" {{ ($subscription['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                        <option value="none" {{ ($subscription['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' }}>None</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(37,211,102,0.15);">
                    <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">WhatsApp API</h2>
                    <p class="text-xs" style="color: var(--text-muted);">Connect your WhatsApp Business API for sending messages</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">API URL</label>
                    <input type="url" name="whatsapp_api_url" value="{{ $subscription['whatsapp_api_url'] ?? '' }}" placeholder="https://graph.facebook.com/v18.0/..." class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">API Token</label>
                    @include('common.partials.password-field', [
                        'name' => 'whatsapp_api_token',
                        'autocomplete' => 'off',
                        'value' => $subscription['whatsapp_api_token'] ?? '',
                        'inputClass' => 'w-full px-3 py-2.5 rounded-xl text-sm outline-none',
                        'inputStyle' => 'background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);',
                    ])
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Sender Number</label>
                    <input type="text" name="whatsapp_sender_number" value="{{ $subscription['whatsapp_sender_number'] ?? '' }}" placeholder="+1234567890" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(59,130,246,0.15);">
                    <i class="fas fa-magic text-blue-400"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">Welcome Email</h2>
                    <p class="text-xs" style="color: var(--text-muted);">Auto-send a welcome email when someone subscribes</p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <input type="hidden" name="welcome_email_enabled" value="0">
                    <input type="checkbox" name="welcome_email_enabled" value="1" {{ ($subscription['welcome_email_enabled'] ?? false) ? 'checked' : '' }} class="rounded" id="welcome_toggle">
                    <label for="welcome_toggle" class="text-sm" style="color: var(--text-secondary);">Enable welcome email</label>
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Subject</label>
                    <input type="text" name="welcome_email_subject" value="{{ $subscription['welcome_email_subject'] ?? '' }}" placeholder="Welcome aboard!" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Body</label>
                    <textarea name="welcome_email_body" rows="4" placeholder="Thank you for subscribing! We're excited to have you." class="w-full px-3 py-2.5 rounded-xl text-sm outline-none resize-y" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">{{ $subscription['welcome_email_body'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">
                <i class="fas fa-save mr-1.5"></i>Save Settings
            </button>
        </div>
    </form>
</div>
@endsection
