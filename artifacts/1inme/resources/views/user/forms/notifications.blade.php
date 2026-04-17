@extends('user.layouts.app')
@section('title', 'Notifications · ' . $form->title)

@section('content')
<div class="max-w-5xl mx-auto" x-data="{
        emailEnabled: {{ ($notifications['email']['enabled'] ?? false) ? 'true' : 'false' }},
        autoEnabled: {{ ($notifications['autoresponder']['enabled'] ?? false) ? 'true' : 'false' }},
        smsEnabled: {{ ($notifications['sms']['enabled'] ?? false) ? 'true' : 'false' }},
        webhooks: @js($notifications['webhooks'] ?? []),
        addHook() { this.webhooks.push({url:'',method:'POST',enabled:true,header_key:'',header_value:''}); },
     }">
    @include('user.partials.page-hero', [
        'title' => 'Notifications',
        'subtitle' => 'Be alerted when someone submits — by email, SMS, auto-reply, or send to any other system via webhooks.',
        'icon' => 'fa-bell',
        'back' => route('user.forms.show', $form),
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.forms.notifications.update', $form) }}" class="space-y-6">
        @csrf @method('PUT')

        {{-- EMAIL --}}
        <div class="card-premium p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.12);">
                        <i class="fas fa-envelope text-violet-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Email me on every submission</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Get a copy of every submission delivered to your inbox.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="email_enabled" value="0">
                    <input type="checkbox" name="email_enabled" value="1" class="sr-only peer" x-model="emailEnabled">
                    <div class="w-11 h-6 rounded-full peer-checked:bg-violet-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"></div>
                    <div class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>
            <div x-show="emailEnabled" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-3 ml-13" style="margin-left: 3.25rem;">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                        Email mailer <span class="text-[10px]" style="color: var(--text-faint);">— which saved configuration to send through</span>
                    </label>
                    @include('common.partials.integration-picker', [
                        'name' => 'email_config_id',
                        'kind' => 'email',
                        'value' => $notifications['email']['config_id'] ?? null,
                    ])
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Recipients <span class="text-[10px]" style="color: var(--text-faint);">— comma-separated for multiple</span></label>
                    <input type="text" name="email_to" value="{{ $notifications['email']['to'] ?? '' }}" placeholder="you@example.com, sales@example.com" class="theme-input w-full">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Subject line</label>
                    <input type="text" name="email_subject" value="{{ $notifications['email']['subject'] ?? '' }}" placeholder="New submission on {form_title}" class="theme-input w-full">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Reply-to field <span class="text-[10px]" style="color: var(--text-faint);">— so you can reply directly</span></label>
                    <input type="text" name="email_reply_to_field" value="{{ $notifications['email']['reply_to_field'] ?? 'email' }}" placeholder="email" class="theme-input w-full">
                </div>
            </div>
        </div>

        {{-- AUTO-RESPONDER --}}
        <div class="card-premium p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.12);">
                        <i class="fas fa-reply text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Auto-reply to submitter</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Send an instant thank-you email back to the person who submitted the form.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="auto_enabled" value="0">
                    <input type="checkbox" name="auto_enabled" value="1" class="sr-only peer" x-model="autoEnabled">
                    <div class="w-11 h-6 rounded-full peer-checked:bg-emerald-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"></div>
                    <div class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>
            <div x-show="autoEnabled" x-transition class="space-y-3" style="margin-left: 3.25rem;">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                        Email mailer <span class="text-[10px]" style="color: var(--text-faint);">— pick a different mailer than notifications, e.g. transactional vs marketing</span>
                    </label>
                    @include('common.partials.integration-picker', [
                        'name' => 'auto_config_id',
                        'kind' => 'email',
                        'value' => $notifications['autoresponder']['config_id'] ?? null,
                    ])
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Subject</label>
                        <input type="text" name="auto_subject" value="{{ $notifications['autoresponder']['subject'] ?? '' }}" class="theme-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Email field ID</label>
                        <input type="text" name="auto_email_field" value="{{ $notifications['autoresponder']['email_field'] ?? 'email' }}" class="theme-input w-full">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Message body</label>
                    <textarea name="auto_body" rows="5" class="theme-input w-full">{{ $notifications['autoresponder']['body'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        {{-- SMS --}}
        <div class="card-premium p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.12);">
                        <i class="fas fa-sms text-amber-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">SMS alert</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Get a text message — pick from the SMS configurations you've saved under Integrations.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="sms_enabled" value="0">
                    <input type="checkbox" name="sms_enabled" value="1" class="sr-only peer" x-model="smsEnabled">
                    <div class="w-11 h-6 rounded-full peer-checked:bg-amber-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"></div>
                    <div class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>
            <div x-show="smsEnabled" x-transition class="space-y-3" style="margin-left: 3.25rem;">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                        SMS sender <span class="text-[10px]" style="color: var(--text-faint);">— which saved configuration to send through</span>
                    </label>
                    @include('common.partials.integration-picker', [
                        'name' => 'sms_config_id',
                        'kind' => 'sms',
                        'value' => $notifications['sms']['config_id'] ?? null,
                        'allowEmpty' => false,
                        'emptyLabel' => '— Select an SMS configuration —',
                    ])
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Phone number <span class="text-[10px]" style="color: var(--text-faint);">— with country code</span></label>
                        <input type="text" name="sms_to" value="{{ $notifications['sms']['to'] ?? '' }}" placeholder="+15551234567" class="theme-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Message</label>
                        <input type="text" name="sms_message" value="{{ $notifications['sms']['message'] ?? '' }}" placeholder="New form submission on {form_title}" class="theme-input w-full">
                    </div>
                </div>
            </div>
        </div>

        {{-- WEBHOOKS --}}
        <div class="card-premium p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(99,102,241,0.12);">
                        <i class="fas fa-plug text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Webhooks</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Send each submission to any URL — connect a CRM, Zapier, Make.com, or your own backend.</p>
                    </div>
                </div>
                <button type="button" @click="addHook" class="text-xs px-3 py-1.5 rounded-lg font-semibold" style="background: rgba(99,102,241,0.15); color: #818cf8;"><i class="fas fa-plus text-[10px] mr-1"></i> Add</button>
            </div>

            <div class="space-y-3">
                <template x-for="(h, i) in webhooks" :key="i">
                    <div class="p-4 rounded-xl space-y-2" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-2">
                            <select :name="`webhook_method[${i}]`" x-model="h.method" class="theme-input text-xs flex-shrink-0" style="width: 100px;">
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="GET">GET</option>
                            </select>
                            <input type="url" :name="`webhook_url[${i}]`" x-model="h.url" placeholder="https://hook.example.com/..." class="theme-input flex-1 text-xs">
                            <label class="text-[10px] flex items-center gap-1.5 cursor-pointer" style="color: var(--text-secondary);">
                                <input type="hidden" :name="`webhook_enabled[${i}]`" value="0">
                                <input type="checkbox" :name="`webhook_enabled[${i}]`" value="1" x-model="h.enabled" class="rounded text-violet-500"> Enabled
                            </label>
                            <button type="button" @click="webhooks.splice(i,1)" class="w-8 h-8 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(239,68,68,0.1); color: #f87171;"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" :name="`webhook_header_key[${i}]`" x-model="h.header_key" placeholder="Header name (e.g. Authorization)" class="theme-input text-xs">
                            <input type="text" :name="`webhook_header_value[${i}]`" x-model="h.header_value" placeholder="Header value" class="theme-input text-xs">
                        </div>
                    </div>
                </template>
                <div x-show="webhooks.length === 0" class="text-center py-8">
                    <p class="text-xs" style="color: var(--text-faint);">No webhooks configured. Click "Add" to send submissions to an external URL.</p>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 py-4 flex items-center gap-3" style="background: var(--bg-body); z-index: 10;">
            <button type="submit" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
                <i class="fas fa-save text-xs"></i> Save Notifications
            </button>
        </div>
    </form>
</div>
@endsection
