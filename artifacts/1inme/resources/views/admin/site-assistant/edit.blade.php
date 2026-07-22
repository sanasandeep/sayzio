@extends('admin.layouts.app')
@section('title', 'Site Assistant')
@section('page-title', 'Site Assistant')

@section('content')
<div class="max-w-5xl space-y-6">
    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs ak-red">
            <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 p-6 grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
        <div><div class="text-2xl font-semibold text-white ak-strong">{{ number_format($totals['conversations']) }}</div><div class="text-xs text-white/50 ak-muted">Conversations</div></div>
        <div><div class="text-2xl font-semibold text-white ak-strong">{{ number_format($totals['handoffs']) }}</div><div class="text-xs text-white/50 ak-muted">Handoffs</div></div>
        <div><div class="text-2xl font-semibold text-white ak-strong">{{ number_format($totals['turns_month']) }}</div><div class="text-xs text-white/50 ak-muted">Turns this month</div></div>
        <div><div class="text-2xl font-semibold text-white ak-strong">{{ number_format($monthly_spend) }}</div><div class="text-xs text-white/50 ak-muted">Coins this month</div></div>
        <div><div class="text-2xl font-semibold text-white ak-strong">{{ number_format($totals['page_hints']) }} / {{ $totals['templates'] }}</div><div class="text-xs text-white/50 ak-muted">Hints / Templates</div></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.site-assistant.hints') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white ak-strong">Page Hints</a>
        <a href="{{ route('admin.site-assistant.sources') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white ak-strong">Knowledge Sources</a>
        <a href="{{ route('admin.site-assistant.templates') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white ak-strong">Response Templates</a>
        <a href="{{ route('admin.site-assistant.conversations') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white ak-strong">Conversations</a>
        <a href="{{ route('admin.site-assistant.analytics') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white ak-strong">Analytics</a>
    </div>

    <form method="POST" action="{{ route('admin.site-assistant.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <h3 class="font-semibold text-white ak-strong">Surfaces</h3>
            <p class="text-xs text-white/40 ak-note">Choose where the chat widget appears.</p>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="enabled_marketing" value="0">
                <input type="checkbox" name="enabled_marketing" value="1" class="rounded" {{ $cfg['enabled_marketing'] ? 'checked' : '' }}>
                <span class="text-sm text-white ak-strong">Show on marketing pages (logged-out)</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="enabled_app" value="0">
                <input type="checkbox" name="enabled_app" value="1" class="rounded" {{ $cfg['enabled_app'] ? 'checked' : '' }}>
                <span class="text-sm text-white ak-strong">Show on logged-in app pages</span>
            </label>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <h3 class="font-semibold text-white ak-strong">Appearance</h3>

            {{-- Live mock of the chat widget header + first message. Reflects the
                 appearance fields below as the admin edits them, so the whole look
                 can be confirmed without saving + reloading. Falls back to the same
                 defaults the runtime widget uses when a field is left blank. --}}
            <div>
                <p class="text-xs text-white/60 mb-2 ak-muted">Live preview</p>
                <div id="assistant_mock" style="width:320px;max-width:100%;background:#0f172a;color:#e2e8f0;border-radius:16px;border:1px solid rgba(255,255,255,.08);box-shadow:0 18px 40px rgba(0,0,0,.45);overflow:hidden;font-family:'Space Grotesk','system-ui',sans-serif">
                    <div style="padding:14px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.06)">
                        <img id="assistant_mock_avatar" src="{{ \App\Services\AI\SiteAssistantSettings::avatarUrlFor($cfg) }}" alt="" style="width:32px;height:32px;border-radius:10px;object-fit:contain;background:rgba(255,255,255,.08);padding:1px">
                        <div style="min-width:0">
                            <div id="assistant_mock_name" style="font-size:14px;font-weight:600;color:#fff;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ \App\Services\AI\SiteAssistantSettings::brandNameFor($cfg) }}</div>
                            <div id="assistant_mock_sub" style="font-size:11px;opacity:.65"></div>
                        </div>
                        <span style="margin-left:auto;color:#94a3b8;font-size:18px;line-height:1">&times;</span>
                    </div>
                    <div style="padding:14px;min-height:78px;display:flex;flex-direction:column;gap:10px">
                        <div id="assistant_mock_greeting" style="align-self:flex-start;max-width:85%;padding:10px 12px;border-radius:14px;border-bottom-left-radius:4px;background:rgba(255,255,255,.06);color:#e2e8f0;font-size:13.5px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word"></div>
                    </div>
                    <div style="display:flex;gap:8px;padding:10px;border-top:1px solid rgba(255,255,255,.06)">
                        <div id="assistant_mock_placeholder" style="flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#94a3b8;padding:8px 10px;border-radius:10px;font-size:13px;min-height:36px;box-sizing:border-box;display:flex;align-items:center"></div>
                        <button type="button" id="assistant_mock_send" disabled style="background:#2342c7;border:0;color:#fff;padding:0 14px;border-radius:10px;font-size:14px;cursor:default"></button>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var mock = document.getElementById('assistant_mock');
                if (!mock) return;
                var DEFAULTS = {
                    name:        @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_BRAND_NAME),
                    accent:      @json(\App\Services\AI\SiteAssistantSettings::defaults()['accent_color']),
                    greeting:    @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_GREETING),
                    subheading:  @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_SUBHEADING),
                    placeholder: @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_INPUT_PLACEHOLDER),
                    send:        @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_SEND_LABEL)
                };
                var fields = {
                    name:        document.getElementById('assistant_brand_name'),
                    accent:      document.getElementById('assistant_accent_color'),
                    greeting:    document.getElementById('assistant_greeting'),
                    subheading:  document.getElementById('assistant_subheading_input'),
                    placeholder: document.getElementById('assistant_input_placeholder'),
                    send:        document.getElementById('assistant_send_label')
                };
                var out = {
                    name:        document.getElementById('assistant_mock_name'),
                    sub:         document.getElementById('assistant_mock_sub'),
                    greeting:    document.getElementById('assistant_mock_greeting'),
                    placeholder: document.getElementById('assistant_mock_placeholder'),
                    send:        document.getElementById('assistant_mock_send'),
                    avatar:      document.getElementById('assistant_mock_avatar')
                };
                function val(key) {
                    var f = fields[key];
                    var v = f ? (f.value || '').trim() : '';
                    return v !== '' ? v : DEFAULTS[key];
                }
                function render() {
                    out.name.textContent = val('name');
                    out.sub.textContent = val('subheading');
                    out.greeting.textContent = val('greeting');
                    out.placeholder.textContent = val('placeholder');
                    out.send.textContent = val('send');
                    out.send.style.background = val('accent');
                }
                Object.keys(fields).forEach(function (k) {
                    if (fields[k]) fields[k].addEventListener('input', render);
                });
                // Mirror the avatar from the existing avatar preview (which already
                // tracks file uploads + URL edits + the bundled-mascot fallback).
                var srcAvatar = document.getElementById('assistant_avatar_preview');
                if (srcAvatar) {
                    var syncAvatar = function () { out.avatar.src = srcAvatar.src; };
                    new MutationObserver(syncAvatar).observe(srcAvatar, { attributes: true, attributeFilter: ['src'] });
                    syncAvatar();
                }
                render();
            })();
            </script>

            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Position</label>
                    <select name="launcher_position" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                        <option value="bottom-right" @selected($cfg['launcher_position']==='bottom-right')>Bottom right</option>
                        <option value="bottom-left"  @selected($cfg['launcher_position']==='bottom-left')>Bottom left</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Accent color</label>
                    <input type="text" name="accent_color" id="assistant_accent_color" value="{{ $cfg['accent_color'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="#3d6bff">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Display name</label>
                    <input type="text" maxlength="60" name="brand_name" id="assistant_brand_name" value="{{ $cfg['brand_name'] ?? '' }}" placeholder="{{ \App\Services\AI\SiteAssistantSettings::DEFAULT_BRAND_NAME }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Name shown in the chat header. Leave blank to use <code>{{ \App\Services\AI\SiteAssistantSettings::DEFAULT_BRAND_NAME }}</code>.</p>
                    @error('brand_name')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid md:grid-cols-[88px,1fr] gap-4 items-start">
                <div class="rounded-xl p-2 flex flex-col items-center justify-center gap-1.5" style="background:rgba(255,255,255,0.04); border:1px solid var(--border-glass); min-height:80px;">
                    <img id="assistant_avatar_preview" src="{{ \App\Services\AI\SiteAssistantSettings::avatarUrlFor($cfg) }}" data-default-src="{{ asset(\App\Services\AI\SiteAssistantSettings::DEFAULT_AVATAR_PATH) }}" alt="Assistant avatar preview" class="h-16 w-16 rounded-full object-cover">
                    <span id="assistant_name_preview" class="text-[11px] text-white/60 text-center leading-tight max-w-full truncate ak-muted">{{ trim($cfg['brand_name'] ?? '') !== '' ? $cfg['brand_name'] : \App\Services\AI\SiteAssistantSettings::DEFAULT_BRAND_NAME }}</span>
                </div>
                <div class="space-y-2">
                    <div>
                        <label class="block text-xs text-white/60 mb-1 ak-muted">Upload avatar</label>
                        <input type="file" name="avatar_file" id="assistant_avatar_file" accept="image/png,image/jpeg,image/webp"
                               class="block w-full text-xs text-white/70 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-primary-600 file:text-white hover:file:bg-primary-700 file:cursor-pointer">
                        <p class="text-xs text-white/40 mt-1 ak-note">PNG, JPG or WebP, up to 2&nbsp;MB. Uploading replaces the URL below.</p>
                        @error('avatar_file')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs text-white/60 mb-1 ak-muted">Avatar URL (optional)</label>
                        <input type="text" name="avatar_url" id="assistant_avatar_url" value="{{ $cfg['avatar_url'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="https://…">
                        <p class="text-xs text-white/40 mt-1 ak-note">Clear both to fall back to the bundled mascot.</p>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var img = document.getElementById('assistant_avatar_preview');
                var fileInput = document.getElementById('assistant_avatar_file');
                var urlInput = document.getElementById('assistant_avatar_url');
                var nameInput = document.getElementById('assistant_brand_name');
                var namePreview = document.getElementById('assistant_name_preview');
                if (!img) return;
                var defaultSrc = img.getAttribute('data-default-src') || img.src;
                var objectUrl = null;

                function refreshAvatar() {
                    var file = fileInput && fileInput.files && fileInput.files[0];
                    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
                    if (file) {
                        objectUrl = URL.createObjectURL(file);
                        img.src = objectUrl;
                        return;
                    }
                    var url = urlInput ? urlInput.value.trim() : '';
                    img.src = url !== '' ? url : defaultSrc;
                }

                function refreshName() {
                    if (!namePreview) return;
                    var name = nameInput ? nameInput.value.trim() : '';
                    namePreview.textContent = name !== '' ? name : @json(\App\Services\AI\SiteAssistantSettings::DEFAULT_BRAND_NAME);
                }

                if (fileInput) fileInput.addEventListener('change', refreshAvatar);
                if (urlInput) urlInput.addEventListener('input', refreshAvatar);
                if (nameInput) nameInput.addEventListener('input', refreshName);
            })();
            </script>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">Greeting</label>
                <input type="text" name="greeting" id="assistant_greeting" value="{{ $cfg['greeting'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">Starter prompts (one per line)</label>
                <textarea name="starter_prompts_text" rows="4" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="How does pricing work?">{{ implode("\n", (array)$cfg['starter_prompts']) }}</textarea>
                <script>
                  document.currentScript.previousElementSibling.addEventListener('change', function(){
                    var lines=this.value.split('\n').map(function(s){return s.trim();}).filter(Boolean);
                    var form=this.form;
                    [].slice.call(form.querySelectorAll('input[name^="starter_prompts["]')).forEach(function(n){n.remove();});
                    lines.forEach(function(l,i){ var i2=document.createElement('input'); i2.type='hidden'; i2.name='starter_prompts['+i+']'; i2.value=l; form.appendChild(i2); });
                  });
                  // Initial sync
                  var ta=document.currentScript.previousElementSibling;
                  ta.dispatchEvent(new Event('change'));
                </script>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Input placeholder</label>
                    <input type="text" maxlength="120" name="input_placeholder" id="assistant_input_placeholder" value="{{ $cfg['input_placeholder'] ?? '' }}" placeholder="Type a message…" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Shown inside the chat textarea. Leave blank to use the built-in <code>Type a message…</code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Send button label</label>
                    <input type="text" maxlength="40" name="send_label" id="assistant_send_label" value="{{ $cfg['send_label'] ?? '' }}" placeholder="Send" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Label on the message-send button. Leave blank to use the built-in <code>Send</code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Header subheading</label>
                    <input type="text" maxlength="120" name="assistant_subheading" id="assistant_subheading_input" value="{{ $cfg['assistant_subheading'] ?? '' }}" placeholder="How can I help?" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Small line under the assistant name in the chat header. Leave blank to use <code>How can I help?</code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Typing indicator</label>
                    <input type="text" maxlength="80" name="assistant_typing" value="{{ $cfg['assistant_typing'] ?? '' }}" placeholder="Assistant is typing…" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Shown while waiting for the model. Leave blank to use <code>Assistant is typing…</code>.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Handoff disabled-input note</label>
                    <input type="text" maxlength="240" name="assistant_handoff_note" value="{{ $cfg['assistant_handoff_note'] ?? '' }}" placeholder="Our team will reply by email." class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Shown after the visitor has been handed off. Leave blank to use <code>Our team will reply by email.</code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Cut-off banner notice</label>
                    <input type="text" maxlength="200" name="assistant_cutoff_notice" value="{{ $cfg['assistant_cutoff_notice'] ?? '' }}" placeholder="⚠ This reply was cut off - " class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Shown when a streamed reply is interrupted. Leave blank to use <code>⚠ This reply was cut off - </code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Cut-off retry button</label>
                    <input type="text" maxlength="40" name="assistant_cutoff_retry_label" value="{{ $cfg['assistant_cutoff_retry_label'] ?? '' }}" placeholder="Retry" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Label on the Retry button next to the cut-off notice. Leave blank to use <code>Retry</code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Network error toast</label>
                    <input type="text" maxlength="200" name="assistant_error_network" value="{{ $cfg['assistant_error_network'] ?? '' }}" placeholder="Network error." class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Shown when the request fails to reach the server. Leave blank to use <code>Network error.</code>.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Generic error toast</label>
                    <input type="text" maxlength="240" name="assistant_error_generic" value="{{ $cfg['assistant_error_generic'] ?? '' }}" placeholder="Sorry, something went wrong." class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Fallback when the server reports an error without copy. Leave blank to use <code>Sorry, something went wrong.</code>.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Login-required note</label>
                    <input type="text" maxlength="240" name="assistant_auth_required" value="{{ $cfg['assistant_auth_required'] ?? '' }}" placeholder="Please log in to chat with us." class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Shown to signed-out visitors, who must log in before chatting. Leave blank to use <code>Please log in to chat with us.</code>.</p>
                </div>
            </div>

            <div class="pt-2 border-t border-white/10 space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-white ak-strong">Per-language greeting & starter prompts</h4>
                    <p class="text-xs text-white/40 ak-note">Visitors are matched to the closest language from their browser's <span class="font-mono text-white/60 ak-muted">Accept-Language</span> header (e.g. <span class="font-mono text-white/60 ak-muted">fr-CA</span> falls back to <span class="font-mono text-white/60 ak-muted">fr</span>). Any field left blank uses the default copy above. Use BCP-47 codes like <span class="font-mono text-white/60 ak-muted">fr</span>, <span class="font-mono text-white/60 ak-muted">es</span>, <span class="font-mono text-white/60 ak-muted">pt-BR</span>, <span class="font-mono text-white/60 ak-muted">zh-CN</span>.</p>
                </div>

                <div id="intro_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="intro_locale_add" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-500/15 border border-indigo-500/35 text-indigo-200 ak-blue">
                        + Add language
                    </button>
                    <span class="text-[11px] text-white/40 ak-note">Up to 50 languages.</span>
                </div>

                <template id="intro_locale_row_tpl">
                    <div class="intro-locale-row rounded-xl p-4 bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px] ak-muted">Language code (BCP-47)
                                <input type="text" data-intro-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-intro-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1 ak-red">Remove</button>
                        </div>
                        <div class="space-y-3">
                            <label class="block text-xs text-white/60 ak-muted">Greeting
                                <input type="text" maxlength="500" data-intro-greeting class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                            </label>
                            <label class="block text-xs text-white/60 ak-muted">Starter prompts (one per line, up to 10)
                                <textarea rows="3" data-intro-prompts class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong"></textarea>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="block text-xs text-white/60 ak-muted">Input placeholder
                                    <input type="text" maxlength="120" data-intro-placeholder class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Type a message…">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Send button label
                                    <input type="text" maxlength="40" data-intro-send class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Send">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Header subheading
                                    <input type="text" maxlength="120" data-intro-subheading class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="How can I help?">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Typing indicator
                                    <input type="text" maxlength="80" data-intro-typing class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Assistant is typing…">
                                </label>
                                <label class="block text-xs text-white/60 md:col-span-2 ak-muted">Handoff disabled-input note
                                    <input type="text" maxlength="240" data-intro-handoff class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Our team will reply by email.">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Cut-off banner notice
                                    <input type="text" maxlength="200" data-intro-cutoff class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="⚠ This reply was cut off - ">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Cut-off retry button
                                    <input type="text" maxlength="40" data-intro-retry class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Retry">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Network error toast
                                    <input type="text" maxlength="200" data-intro-err-net class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Network error.">
                                </label>
                                <label class="block text-xs text-white/60 ak-muted">Generic error toast
                                    <input type="text" maxlength="240" data-intro-err-gen class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Sorry, something went wrong.">
                                </label>
                            </div>
                        </div>
                    </div>
                </template>

                <script>
                (function () {
                    var host = document.getElementById('intro_locales');
                    var tpl  = document.getElementById('intro_locale_row_tpl');
                    var addBtn = document.getElementById('intro_locale_add');
                    var seededGreetings   = @json((object)($cfg['greeting_locales'] ?? new \stdClass()));
                    var seededPrompts     = @json((object)($cfg['starter_prompts_locales'] ?? new \stdClass()));
                    var seededPlaceholders = @json((object)($cfg['input_placeholder_locales'] ?? new \stdClass()));
                    var seededSendLabels   = @json((object)($cfg['send_label_locales'] ?? new \stdClass()));
                    var seededSubheadings  = @json((object)($cfg['assistant_subheading_locales'] ?? new \stdClass()));
                    var seededTyping       = @json((object)($cfg['assistant_typing_locales'] ?? new \stdClass()));
                    var seededHandoff      = @json((object)($cfg['assistant_handoff_note_locales'] ?? new \stdClass()));
                    var seededCutoff       = @json((object)($cfg['assistant_cutoff_notice_locales'] ?? new \stdClass()));
                    var seededRetry        = @json((object)($cfg['assistant_cutoff_retry_label_locales'] ?? new \stdClass()));
                    var seededErrNet       = @json((object)($cfg['assistant_error_network_locales'] ?? new \stdClass()));
                    var seededErrGen       = @json((object)($cfg['assistant_error_generic_locales'] ?? new \stdClass()));
                    var seq = 0;

                    function bucketName(row) {
                        var code = (row.querySelector('[data-intro-locale-code]').value || '').trim();
                        return code === '' ? '__pending_' + (row.dataset.rowId || '0') : code;
                    }

                    function syncPromptInputs(row) {
                        var bucket = bucketName(row);
                        // Drop any prior hidden inputs we generated for this row
                        row.querySelectorAll('input[data-intro-prompt-hidden]').forEach(function (n) { n.remove(); });
                        var ta = row.querySelector('[data-intro-prompts]');
                        var lines = (ta.value || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
                        lines.forEach(function (line, i) {
                            var h = document.createElement('input');
                            h.type = 'hidden';
                            h.setAttribute('data-intro-prompt-hidden', '');
                            h.name = 'starter_prompts_locales[' + bucket + '][' + i + ']';
                            h.value = line;
                            row.appendChild(h);
                        });
                    }

                    // Map of [data-attr selector] → form field name, used
                    // by rewire() so each chrome string posts under the
                    // matching `[locale]` bucket. Mirrors the locale-keyed
                    // arrays defined in SiteAssistantSettings::defaults().
                    var CHROME_FIELDS = [
                        ['[data-intro-greeting]',    'greeting_locales'],
                        ['[data-intro-placeholder]', 'input_placeholder_locales'],
                        ['[data-intro-send]',        'send_label_locales'],
                        ['[data-intro-subheading]',  'assistant_subheading_locales'],
                        ['[data-intro-typing]',      'assistant_typing_locales'],
                        ['[data-intro-handoff]',     'assistant_handoff_note_locales'],
                        ['[data-intro-cutoff]',      'assistant_cutoff_notice_locales'],
                        ['[data-intro-retry]',       'assistant_cutoff_retry_label_locales'],
                        ['[data-intro-err-net]',     'assistant_error_network_locales'],
                        ['[data-intro-err-gen]',     'assistant_error_generic_locales']
                    ];

                    function rewire(row) {
                        var bucket = bucketName(row);
                        CHROME_FIELDS.forEach(function (pair) {
                            var el = row.querySelector(pair[0]);
                            if (el) el.name = pair[1] + '[' + bucket + ']';
                        });
                        syncPromptInputs(row);
                    }

                    function addRow(code, greeting, prompts, placeholder, sendLabel, extras) {
                        if (host.querySelectorAll('.intro-locale-row').length >= 50) return;
                        var node = tpl.content.firstElementChild.cloneNode(true);
                        node.dataset.rowId = String(++seq);
                        var codeInput = node.querySelector('[data-intro-locale-code]');
                        var greetInput = node.querySelector('[data-intro-greeting]');
                        var promptsTa = node.querySelector('[data-intro-prompts]');
                        var phInput = node.querySelector('[data-intro-placeholder]');
                        var sendInput = node.querySelector('[data-intro-send]');
                        codeInput.value = code || '';
                        greetInput.value = greeting || '';
                        promptsTa.value = (prompts && prompts.length) ? prompts.join('\n') : '';
                        phInput.value = placeholder || '';
                        sendInput.value = sendLabel || '';
                        // Hydrate the chrome-string fields from the extras
                        // map. Missing entries are tolerated so rows that
                        // only override greeting/prompts still post cleanly.
                        var chromeMap = {
                            '[data-intro-subheading]': (extras && extras.subheading) || '',
                            '[data-intro-typing]':     (extras && extras.typing) || '',
                            '[data-intro-handoff]':    (extras && extras.handoff) || '',
                            '[data-intro-cutoff]':     (extras && extras.cutoff) || '',
                            '[data-intro-retry]':      (extras && extras.retry) || '',
                            '[data-intro-err-net]':    (extras && extras.errNet) || '',
                            '[data-intro-err-gen]':    (extras && extras.errGen) || ''
                        };
                        Object.keys(chromeMap).forEach(function (sel) {
                            var el = node.querySelector(sel);
                            if (el) el.value = chromeMap[sel];
                        });
                        node.querySelector('[data-intro-locale-remove]').addEventListener('click', function () { node.remove(); });
                        codeInput.addEventListener('input', function () { rewire(node); });
                        promptsTa.addEventListener('input', function () { syncPromptInputs(node); });
                        host.appendChild(node);
                        rewire(node);
                    }

                    if (addBtn) addBtn.addEventListener('click', function () { addRow('', '', null, '', '', null); });

                    var codes = {};
                    [seededGreetings, seededPrompts, seededPlaceholders, seededSendLabels,
                     seededSubheadings, seededTyping, seededHandoff, seededCutoff,
                     seededRetry, seededErrNet, seededErrGen].forEach(function (m) {
                        if (m && typeof m === 'object' && !Array.isArray(m)) {
                            Object.keys(m).forEach(function (c) { codes[c] = true; });
                        }
                    });
                    Object.keys(codes).sort().forEach(function (code) {
                        addRow(
                            code,
                            (seededGreetings && seededGreetings[code]) || '',
                            (seededPrompts && seededPrompts[code]) || null,
                            (seededPlaceholders && seededPlaceholders[code]) || '',
                            (seededSendLabels && seededSendLabels[code]) || '',
                            {
                                subheading: (seededSubheadings && seededSubheadings[code]) || '',
                                typing:     (seededTyping       && seededTyping[code])       || '',
                                handoff:    (seededHandoff      && seededHandoff[code])      || '',
                                cutoff:     (seededCutoff       && seededCutoff[code])       || '',
                                retry:      (seededRetry        && seededRetry[code])        || '',
                                errNet:     (seededErrNet       && seededErrNet[code])       || '',
                                errGen:     (seededErrGen       && seededErrGen[code])       || ''
                            }
                        );
                    });
                })();
                </script>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="font-semibold text-white ak-strong">Preview</h3>
                    <p class="text-xs text-white/40 ak-note">Live render of the greeting, starter prompts, and low-balance warning bubbles (signed-in + anonymous variants) a visitor would see, using the unsaved values above. Pick a configured language or paste an <span class="font-mono">Accept-Language</span> header to test the matcher. The greeting/starter prompts and low-balance translations resolve independently, so a locale defined in only one section will fall back to the default copy in the other.</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Language</label>
                    <select id="sa_preview_locale_select" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                        <option value="__default__">Default (no Accept-Language match)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Or Accept-Language header</label>
                    <input type="text" id="sa_preview_accept" placeholder="e.g. fr-CA,fr;q=0.9,en;q=0.8" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong">
                </div>
            </div>
            <div id="sa_preview_resolved" class="text-[11px] text-white/50 ak-muted">Showing default copy.</div>

            <div id="sa_preview_widget" class="rounded-2xl border border-white/10 overflow-hidden" style="background:#0f172a;color:#e2e8f0;font-family:'Space Grotesk','system-ui',sans-serif;max-width:380px">
                <div id="sa_preview_header" style="padding:14px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.06)">
                    <div id="sa_preview_avatar" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff">★</div>
                    <div>
                        <h4 style="margin:0;font-size:14px;font-weight:600;color:#fff">{{ config('app.name') }}</h4>
                        <div id="sa_preview_subheading" style="font-size:11px;opacity:.65">How can I help?</div>
                    </div>
                </div>
                <div id="sa_preview_suggested" style="display:flex;flex-wrap:wrap;gap:6px;padding:8px 14px 0"></div>
                <div id="sa_preview_body" style="padding:14px;display:flex;flex-direction:column;gap:10px;min-height:120px">
                    <div id="sa_preview_greeting" style="align-self:flex-start;max-width:85%;padding:10px 12px;border-radius:14px;border-bottom-left-radius:4px;background:rgba(255,255,255,.06);color:#e2e8f0;font-size:13.5px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word"></div>
                </div>
                <div id="sa_preview_low_balance_signed_in" data-audience="signed_in" style="margin:0 10px 6px;padding:7px 10px;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);border-radius:8px;color:#fde68a;font-size:11.5px;line-height:1.35;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="display:inline-block;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;opacity:.7;color:#fde68a">Signed-in</span>
                    <span data-lb-msg style="flex:1;min-width:0;word-wrap:break-word"></span>
                    <span data-lb-cta role="button" aria-disabled="true" style="flex-shrink:0;background:rgba(251,191,36,.22);border:1px solid rgba(251,191,36,.45);color:#fde68a;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;text-decoration:none;font-family:inherit;cursor:default;white-space:nowrap">Top up</span>
                </div>
                <div id="sa_preview_input_row" style="display:flex;gap:8px;padding:10px;border-top:1px solid rgba(255,255,255,.06)">
                    <textarea id="sa_preview_input" rows="1" disabled placeholder="Type a message…" style="flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#fff;padding:8px 10px;border-radius:10px;resize:none;font-size:13px;font-family:inherit;min-height:36px"></textarea>
                    <button id="sa_preview_send" type="button" disabled style="background:#3d6bff;border:0;color:#fff;padding:0 14px;border-radius:10px;font-size:14px;cursor:default">Send</button>
                </div>
                <div id="sa_preview_low_balance_anonymous" data-audience="anonymous" style="margin:0 10px 10px;padding:7px 10px;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);border-radius:8px;color:#fde68a;font-size:11.5px;line-height:1.35;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="display:inline-block;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;opacity:.7;color:#fde68a">Anonymous</span>
                    <span data-lb-msg style="flex:1;min-width:0;word-wrap:break-word"></span>
                    <span data-lb-cta role="button" aria-disabled="true" style="flex-shrink:0;background:rgba(251,191,36,.22);border:1px solid rgba(251,191,36,.45);color:#fde68a;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;text-decoration:none;font-family:inherit;cursor:default;white-space:nowrap">Top up</span>
                </div>
            </div>

            <script>
            (function () {
                var form = document.querySelector('form[action$="/site-assistant"]') || document.querySelector('form');

                function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(c){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
                }
                function mdLite(s){
                    s = escapeHtml(s);
                    s = s.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');
                    s = s.replace(/\*([^*]+)\*/g,'<em>$1</em>');
                    s = s.replace(/`([^`]+)`/g,'<code>$1</code>');
                    s = s.replace(/\n/g,'<br>');
                    return s;
                }

                // Mirrors App\Modules\Common\Support\CookieConsentConfig::pickLocale
                function pickLocale(available, acceptLanguage){
                    if (!available || !available.length || !acceptLanguage) return null;
                    var availMap = {}, availPrimary = {};
                    available.forEach(function (code) {
                        availMap[code.toLowerCase()] = code;
                        var primary = code.toLowerCase().split('-')[0];
                        if (!(primary in availPrimary)) availPrimary[primary] = code;
                    });
                    var entries = [];
                    acceptLanguage.split(',').forEach(function (part) {
                        part = part.trim(); if (!part) return;
                        var q = 1.0, tag = part;
                        if (part.indexOf(';') >= 0) {
                            var bits = part.split(';');
                            tag = bits.shift().trim();
                            bits.forEach(function (p) {
                                var m = p.match(/q=([0-9.]+)/);
                                if (m) q = parseFloat(m[1]);
                            });
                        }
                        if (tag === '*' || q <= 0) return;
                        if (!/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/.test(tag)) return;
                        entries.push([tag, q]);
                    });
                    entries.sort(function (a, b) { return b[1] - a[1]; });
                    for (var i = 0; i < entries.length; i++) {
                        var low = entries[i][0].toLowerCase();
                        if (availMap[low]) return availMap[low];
                        var primary = low.split('-')[0];
                        if (availPrimary[primary]) return availPrimary[primary];
                    }
                    return null;
                }

                function defaultGreeting(){
                    var i = form.querySelector('input[name="greeting"]');
                    return i ? i.value : '';
                }
                function defaultPrompts(){
                    var ta = form.querySelector('textarea[name="starter_prompts_text"]');
                    if (!ta) return [];
                    return ta.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 10);
                }
                function accent(){
                    var i = form.querySelector('input[name="accent_color"]');
                    var v = i ? i.value.trim() : '';
                    return v || '#3d6bff';
                }
                function avatarUrl(){
                    var i = form.querySelector('input[name="avatar_url"]');
                    return i ? i.value.trim() : '';
                }

                // Mirror the backend's BCP-47 canonicalization (underscore →
                // hyphen) so codes typed as `pt_BR` resolve through the
                // Accept-Language matcher exactly the way they will after save.
                function canonLocale(code){
                    return String(code || '').replace(/_/g, '-').trim();
                }

                // Read the default low-balance copy for a given audience
                // (`signed_in` or `anonymous`) straight from the form so the
                // preview reflects unsaved edits.
                function defaultLowBalance(audience){
                    var name = audience === 'signed_in'
                        ? 'low_balance_message_signed_in'
                        : 'low_balance_message_anonymous';
                    var i = form.querySelector('input[name="' + name + '"]');
                    return i ? i.value : '';
                }

                // Pull per-language low-balance overrides from the live rows
                // in the "Per-language translations" section. Mirrors
                // SiteAssistantSettings::normalizeLowBalanceLocales (blank
                // values are dropped so they fall back to the default copy).
                function lbLocaleRows(){
                    var rows = form.querySelectorAll('#lb_locales .lb-locale-row');
                    var out = {};
                    rows.forEach(function (row) {
                        var code = canonLocale(row.querySelector('[data-lb-locale-code]').value);
                        if (!code) return;
                        if (!out[code]) out[code] = {};
                        ['signed_in', 'anonymous'].forEach(function (k) {
                            var f = row.querySelector('[data-lb-loc="' + k + '"]');
                            var v = f ? (f.value || '').trim() : '';
                            if (v) out[code][k] = v;
                        });
                        var lbl = row.querySelector('[data-lb-topup-label]');
                        var lblVal = lbl ? (lbl.value || '').trim() : '';
                        if (lblVal) out[code].topup_label = lblVal;
                    });
                    return out;
                }

                // Default CTA label for the preview button — mirrors the
                // runtime fallback chain: per-locale override → admin
                // default → audience-specific built-in label.
                function defaultTopupLabel(){
                    var i = form.querySelector('input[name="low_balance_topup_label"]');
                    return i ? (i.value || '').trim() : '';
                }
                function builtinTopupLabel(audience){
                    return audience === 'anonymous' ? 'See plans' : 'Top up';
                }

                // Pull the current per-language config from the live form rows.
                function localeRows(){
                    var rows = form.querySelectorAll('#intro_locales .intro-locale-row');
                    var out = {};
                    rows.forEach(function (row) {
                        var code = canonLocale(row.querySelector('[data-intro-locale-code]').value);
                        if (!code) return;
                        var greeting = (row.querySelector('[data-intro-greeting]').value || '').trim();
                        var prompts = (row.querySelector('[data-intro-prompts]').value || '')
                            .split('\n').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 10);
                        var phEl = row.querySelector('[data-intro-placeholder]');
                        var sendEl = row.querySelector('[data-intro-send]');
                        var subEl = row.querySelector('[data-intro-subheading]');
                        var placeholder = phEl ? (phEl.value || '').trim() : '';
                        var sendLabel = sendEl ? (sendEl.value || '').trim() : '';
                        var subheading = subEl ? (subEl.value || '').trim() : '';
                        if (!out[code]) out[code] = { greeting: '', prompts: [], placeholder: '', sendLabel: '', subheading: '' };
                        if (greeting) out[code].greeting = greeting;
                        if (prompts.length) out[code].prompts = prompts;
                        if (placeholder) out[code].placeholder = placeholder;
                        if (sendLabel) out[code].sendLabel = sendLabel;
                        if (subheading) out[code].subheading = subheading;
                    });
                    return out;
                }

                // Built-in fallbacks mirror SiteAssistantSettings::DEFAULT_*.
                var BUILTIN_PLACEHOLDER = 'Type a message…';
                var BUILTIN_SEND_LABEL = 'Send';
                function defaultPlaceholder(){
                    var i = form.querySelector('input[name="input_placeholder"]');
                    var v = i ? (i.value || '').trim() : '';
                    return v || BUILTIN_PLACEHOLDER;
                }
                function defaultSendLabel(){
                    var i = form.querySelector('input[name="send_label"]');
                    var v = i ? (i.value || '').trim() : '';
                    return v || BUILTIN_SEND_LABEL;
                }
                var BUILTIN_SUBHEADING = 'How can I help?';
                function defaultSubheading(){
                    var i = form.querySelector('input[name="assistant_subheading"]');
                    var v = i ? (i.value || '').trim() : '';
                    return v || BUILTIN_SUBHEADING;
                }

                var sel = document.getElementById('sa_preview_locale_select');
                var accept = document.getElementById('sa_preview_accept');
                var resolved = document.getElementById('sa_preview_resolved');
                var greetEl = document.getElementById('sa_preview_greeting');
                var suggested = document.getElementById('sa_preview_suggested');
                var avatarEl = document.getElementById('sa_preview_avatar');
                var lbSignedIn = document.getElementById('sa_preview_low_balance_signed_in');
                var lbAnonymous = document.getElementById('sa_preview_low_balance_anonymous');
                var previewInput = document.getElementById('sa_preview_input');
                var previewSend = document.getElementById('sa_preview_send');
                var previewSubheading = document.getElementById('sa_preview_subheading');

                function refreshLocaleOptions(){
                    var current = sel.value;
                    // Languages from either greeting/starter rows or
                    // low-balance rows should be selectable so admins can
                    // preview translations for either section.
                    var combined = {};
                    Object.keys(localeRows()).forEach(function (c) { combined[c] = true; });
                    Object.keys(lbLocaleRows()).forEach(function (c) { combined[c] = true; });
                    var codes = Object.keys(combined).sort();
                    var html = '<option value="__default__">Default (no Accept-Language match)</option>';
                    codes.forEach(function (c) {
                        html += '<option value="' + escapeHtml(c) + '">' + escapeHtml(c) + '</option>';
                    });
                    sel.innerHTML = html;
                    if (current && (current === '__default__' || codes.indexOf(current) >= 0)) sel.value = current;
                }

                function render(){
                    var rows = localeRows();
                    var available = Object.keys(rows);
                    var picked = null;
                    var note;

                    var acceptVal = accept.value.trim();
                    if (acceptVal) {
                        picked = pickLocale(available, acceptVal);
                        note = picked
                            ? 'Accept-Language matched configured locale: ' + picked
                            : 'No configured locale matched, showing default copy.';
                    } else if (sel.value && sel.value !== '__default__' && available.indexOf(sel.value) >= 0) {
                        // Direct selection from the dropdown — skip the matcher
                        // entirely so locale codes that use `_` (allowed by the
                        // form's pattern + canonicalized server-side) still
                        // resolve here without round-tripping through pickLocale.
                        picked = sel.value;
                        note = 'Showing locale: ' + picked;
                    } else {
                        note = 'Showing default copy.';
                    }
                    resolved.textContent = note;

                    var greeting = defaultGreeting();
                    var prompts = defaultPrompts();
                    var placeholder = defaultPlaceholder();
                    var sendLabel = defaultSendLabel();
                    var subheading = defaultSubheading();
                    if (picked && rows[picked]) {
                        if (rows[picked].greeting) greeting = rows[picked].greeting;
                        if (rows[picked].prompts.length) prompts = rows[picked].prompts;
                        if (rows[picked].placeholder) placeholder = rows[picked].placeholder;
                        if (rows[picked].sendLabel) sendLabel = rows[picked].sendLabel;
                        if (rows[picked].subheading) subheading = rows[picked].subheading;
                    }
                    if (previewInput) previewInput.setAttribute('placeholder', placeholder);
                    if (previewSend) {
                        previewSend.textContent = sendLabel;
                        previewSend.style.background = accent();
                    }
                    if (previewSubheading) previewSubheading.textContent = subheading;

                    var ac = accent();
                    greetEl.innerHTML = mdLite(greeting || '(empty greeting)');
                    if (!greeting) greetEl.style.opacity = '0.5'; else greetEl.style.opacity = '1';

                    suggested.innerHTML = '';
                    prompts.forEach(function (p) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.style.cssText = 'background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);color:#fff;padding:6px 12px;border-radius:999px;font-size:11.5px;cursor:default;font-family:inherit';
                        b.textContent = p;
                        b.addEventListener('mouseenter', function () { b.style.background = ac; b.style.borderColor = 'transparent'; });
                        b.addEventListener('mouseleave', function () { b.style.background = 'rgba(255,255,255,.08)'; b.style.borderColor = 'rgba(255,255,255,.1)'; });
                        suggested.appendChild(b);
                    });

                    var av = avatarUrl();
                    if (av) {
                        avatarEl.innerHTML = '';
                        avatarEl.style.background = 'transparent';
                        var img = document.createElement('img');
                        img.src = av; img.alt = '';
                        img.style.cssText = 'width:32px;height:32px;border-radius:50%;object-fit:cover';
                        avatarEl.appendChild(img);
                    } else {
                        avatarEl.innerHTML = '★';
                        avatarEl.style.background = 'rgba(255,255,255,.08)';
                    }

                    // Low-balance bubbles. The matcher runs against the
                    // low-balance row codes (mirroring
                    // SiteAssistantSettings::lowBalanceMessageFor) so that
                    // a translation set defined only in the low-balance
                    // section still resolves here, independent of the
                    // greeting/starter row set.
                    var lbRows = lbLocaleRows();
                    var lbAvailable = Object.keys(lbRows);
                    var lbPicked = null;
                    if (acceptVal) {
                        lbPicked = pickLocale(lbAvailable, acceptVal);
                    } else if (sel.value && sel.value !== '__default__' && lbAvailable.indexOf(sel.value) >= 0) {
                        lbPicked = sel.value;
                    }

                    // Append a note about the low-balance match when it
                    // differs from the greeting/starter match, so admins
                    // aren't confused when the two locale sets diverge.
                    if (lbAvailable.length) {
                        if (lbPicked && lbPicked !== picked) {
                            resolved.textContent = note + ' · low-balance matched: ' + lbPicked + '.';
                        } else if (!lbPicked && picked) {
                            resolved.textContent = note + ' · low-balance: default copy (no translation matched).';
                        }
                    }

                    var defLabel = defaultTopupLabel();
                    [['signed_in', lbSignedIn], ['anonymous', lbAnonymous]].forEach(function (pair) {
                        var audience = pair[0];
                        var box = pair[1];
                        if (!box) return;
                        var msg = defaultLowBalance(audience);
                        if (lbPicked && lbRows[lbPicked] && lbRows[lbPicked][audience]) {
                            msg = lbRows[lbPicked][audience];
                        }
                        var msgEl = box.querySelector('[data-lb-msg]');
                        if (!msg) {
                            msgEl.textContent = '(empty, bubble will not appear)';
                            box.style.opacity = '0.5';
                        } else {
                            msgEl.textContent = msg;
                            box.style.opacity = '1';
                        }
                        // Resolve CTA label using the same fallback chain
                        // as the runtime: per-locale override → admin
                        // default → audience-specific built-in label.
                        var label = builtinTopupLabel(audience);
                        if (defLabel) label = defLabel;
                        if (lbPicked && lbRows[lbPicked] && lbRows[lbPicked].topup_label) {
                            label = lbRows[lbPicked].topup_label;
                        }
                        var ctaEl = box.querySelector('[data-lb-cta]');
                        if (ctaEl) ctaEl.textContent = label;
                    });
                }

                // Re-render whenever any input in the form changes — cheap
                // enough that we don't bother filtering by field name.
                form.addEventListener('input', function () {
                    refreshLocaleOptions();
                    render();
                });
                // Row add/remove fires no input events, so observe DOM changes
                // on both per-language sections (greeting/starter prompts and
                // low-balance translations).
                ['intro_locales', 'lb_locales'].forEach(function (hostId) {
                    var host = document.getElementById(hostId);
                    if (host && window.MutationObserver) {
                        new MutationObserver(function () { refreshLocaleOptions(); render(); })
                            .observe(host, { childList: true });
                    }
                });
                sel.addEventListener('change', render);
                accept.addEventListener('input', render);

                refreshLocaleOptions();
                render();
            })();
            </script>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <h3 class="font-semibold text-white ak-strong">Behavior</h3>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">System prompt</label>
                <textarea name="system_prompt" rows="8" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong">{{ $cfg['system_prompt'] }}</textarea>
                <p class="text-xs text-white/40 mt-1 ak-note">The model is told it may reply either as plain prose or with a JSON envelope <code>{"text":"…","blocks":[…]}</code> for rich blocks.</p>
            </div>

            <div class="pt-2 border-t border-white/10 space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-white ak-strong">Per-language system prompt</h4>
                    <p class="text-xs text-white/40 ak-note">Visitors are matched to the closest language from their browser's <span class="font-mono text-white/60 ak-muted">Accept-Language</span> header (e.g. <span class="font-mono text-white/60 ak-muted">fr-CA</span> falls back to <span class="font-mono text-white/60 ak-muted">fr</span>). Any language left blank uses the default English prompt above. Use BCP-47 codes like <span class="font-mono text-white/60 ak-muted">fr</span>, <span class="font-mono text-white/60 ak-muted">es</span>, <span class="font-mono text-white/60 ak-muted">pt-BR</span>, <span class="font-mono text-white/60 ak-muted">zh-CN</span>.</p>
                </div>

                <div id="sp_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="sp_locale_add" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-500/15 border border-indigo-500/35 text-indigo-200 ak-blue">
                        + Add language
                    </button>
                    <span class="text-[11px] text-white/40 ak-note">Up to 50 languages.</span>
                </div>

                <template id="sp_locale_row_tpl">
                    <div class="sp-locale-row rounded-xl p-4 bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px] ak-muted">Language code (BCP-47)
                                <input type="text" data-sp-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-sp-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1 ak-red">Remove</button>
                        </div>
                        <label class="block text-xs text-white/60 ak-muted">System prompt
                            <textarea rows="6" maxlength="8000" data-sp-prompt class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"></textarea>
                        </label>
                    </div>
                </template>

                <script>
                (function () {
                    var host = document.getElementById('sp_locales');
                    var tpl  = document.getElementById('sp_locale_row_tpl');
                    var addBtn = document.getElementById('sp_locale_add');
                    var seeded = @json((object)($cfg['system_prompt_locales'] ?? new \stdClass()));
                    var seq = 0;

                    function bucketName(row) {
                        var code = (row.querySelector('[data-sp-locale-code]').value || '').trim();
                        return code === '' ? '__pending_' + (row.dataset.rowId || '0') : code;
                    }

                    function rewire(row) {
                        var bucket = bucketName(row);
                        row.querySelector('[data-sp-prompt]').name = 'system_prompt_locales[' + bucket + ']';
                    }

                    function addRow(code, prompt) {
                        if (host.querySelectorAll('.sp-locale-row').length >= 50) return;
                        var node = tpl.content.firstElementChild.cloneNode(true);
                        node.dataset.rowId = String(++seq);
                        var codeInput = node.querySelector('[data-sp-locale-code]');
                        var promptTa  = node.querySelector('[data-sp-prompt]');
                        codeInput.value = code || '';
                        promptTa.value = prompt || '';
                        node.querySelector('[data-sp-locale-remove]').addEventListener('click', function () { node.remove(); });
                        codeInput.addEventListener('input', function () { rewire(node); });
                        host.appendChild(node);
                        rewire(node);
                    }

                    if (addBtn) addBtn.addEventListener('click', function () { addRow('', ''); });

                    if (seeded && typeof seeded === 'object' && !Array.isArray(seeded)) {
                        Object.keys(seeded).sort().forEach(function (code) {
                            addRow(code, seeded[code] || '');
                        });
                    }
                })();
                </script>
            </div>
            <div class="grid md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Temperature</label>
                    <input type="number" step="0.05" min="0" max="2" name="temperature" value="{{ $cfg['temperature'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Max tokens</label>
                    <input type="number" min="64" max="4000" name="max_tokens" value="{{ $cfg['max_tokens'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Per-session msgs/min</label>
                    <input type="number" min="1" max="120" name="session_rate_per_minute" value="{{ $cfg['session_rate_per_minute'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Monthly budget (coins, 0 = unlimited)</label>
                    <input type="number" min="0" name="monthly_budget_credits" value="{{ $cfg['monthly_budget_credits'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Chat model</label>
                    <select name="model" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                        <option value="">Default (companion mapping)</option>
                        @foreach($chatModels as $m)
                            <option value="{{ $m }}" {{ ($cfg['model'] ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs text-white/60 mb-1 ak-muted">AI Knowledge Bases (platform Minds)</label>
                    @if($platformMinds->isEmpty())
                        <p class="text-xs text-white/40 ak-note">No platform Minds yet. <a class="text-indigo-300 underline ak-blue" href="{{ route('admin.site-assistant.knowledge') }}">Manage knowledge bases →</a></p>
                    @else
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @php $picked = array_map('intval', (array)($cfg['mind_ids'] ?? [])); @endphp
                            @foreach($platformMinds as $m)
                                <label class="flex items-center gap-2 text-sm text-white/80 bg-black/20 rounded-lg px-3 py-2 border border-white/10 ak-strong">
                                    <input type="checkbox" name="mind_ids[]" value="{{ $m->id }}" {{ in_array((int)$m->id, $picked, true) ? 'checked' : '' }}>
                                    <span>{{ $m->name }}@if($m->is_default) <em class="text-white/40 ak-note">(default)</em>@endif</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-white/40 mt-1 ak-note">Leave all unchecked to use the platform-default Mind only. <a class="text-indigo-300 underline ak-blue" href="{{ route('admin.site-assistant.knowledge') }}">Manage knowledge bases →</a></p>
                    @endif
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Billing account for anonymous visitors</label>
                    <select name="billing_user_id" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                        <option value="">Auto (first platform admin)</option>
                        @foreach($billingCandidates as $u)
                            <option value="{{ $u->id }}" {{ (int)($cfg['billing_user_id'] ?? 0) === (int)$u->id ? 'selected' : '' }}>
                                {{ $u->name }} &lt;{{ $u->email }}&gt;
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-white/40 mt-1 ak-note">Signed-in visitors are always billed to their own account. Anonymous marketing visitors are billed to this user.</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <h3 class="font-semibold text-white ak-strong">Low-balance warning</h3>
            <p class="text-xs text-white/40 ak-note">Shown to visitors before they send a message when their coin balance is close to running out. The runtime estimates an average reply cost from recent history; the fallback below is used when there's no history yet.</p>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Trigger threshold (× average reply)</label>
                    <input type="number" min="1" max="50" name="low_balance_multiplier" value="{{ $cfg['low_balance_multiplier'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Warn when balance is below this many average replies. Default 3.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Fallback average reply (coins)</label>
                    <input type="number" min="1" max="100000" name="low_balance_default_credits" value="{{ $cfg['low_balance_default_credits'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-xs text-white/40 mt-1 ak-note">Used until the visitor has assistant replies on record.</p>
                </div>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">Signed-in message</label>
                <input type="text" maxlength="500" name="low_balance_message_signed_in" value="{{ $cfg['low_balance_message_signed_in'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                <p class="text-xs text-white/40 mt-1 ak-note">Use <code>{remaining}</code> for replies left, <code>{avg}</code> for average reply cost, <code>{balance}</code> for raw coins.</p>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">Anonymous visitor message</label>
                <input type="text" maxlength="500" name="low_balance_message_anonymous" value="{{ $cfg['low_balance_message_anonymous'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                <p class="text-xs text-white/40 mt-1 ak-note">No numbers are leaked to anonymous visitors, keep this generic.</p>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">CTA button label</label>
                <input type="text" maxlength="60" name="low_balance_topup_label" value="{{ $cfg['low_balance_topup_label'] ?? '' }}" placeholder="Top up" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                <p class="text-xs text-white/40 mt-1 ak-note">Replaces the default <code>Top up</code> (signed-in) and <code>See plans</code> (anonymous) labels on the bubble's button. Leave blank to keep the built-in labels.</p>
            </div>

            <div class="pt-2 border-t border-white/10 space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-white ak-strong">Per-language translations</h4>
                    <p class="text-xs text-white/40 ak-note">Visitors are matched to the closest language from their browser's <span class="font-mono text-white/60 ak-muted">Accept-Language</span> header (e.g. <span class="font-mono text-white/60 ak-muted">fr-CA</span> falls back to <span class="font-mono text-white/60 ak-muted">fr</span>). Any field left blank uses the default copy above. Use BCP-47 codes like <span class="font-mono text-white/60 ak-muted">fr</span>, <span class="font-mono text-white/60 ak-muted">es</span>, <span class="font-mono text-white/60 ak-muted">pt-BR</span>, <span class="font-mono text-white/60 ak-muted">zh-CN</span>.</p>
                </div>

                <div id="lb_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="lb_locale_add" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-500/15 border border-indigo-500/35 text-indigo-200 ak-blue">
                        + Add language
                    </button>
                    <span class="text-[11px] text-white/40 ak-note">Up to 50 languages.</span>
                </div>

                <template id="lb_locale_row_tpl">
                    <div class="lb-locale-row rounded-xl p-4 bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px] ak-muted">Language code (BCP-47)
                                <input type="text" data-lb-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-lb-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1 ak-red">Remove</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block text-xs text-white/60 ak-muted">Signed-in message
                                <input type="text" maxlength="500" data-lb-loc="signed_in" class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                            </label>
                            <label class="block text-xs text-white/60 ak-muted">Anonymous visitor message
                                <input type="text" maxlength="500" data-lb-loc="anonymous" class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                            </label>
                            <label class="block text-xs text-white/60 md:col-span-2 ak-muted">CTA button label
                                <input type="text" maxlength="60" data-lb-topup-label class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong" placeholder="Top up">
                            </label>
                        </div>
                    </div>
                </template>

                <script>
                (function () {
                    var host = document.getElementById('lb_locales');
                    var tpl  = document.getElementById('lb_locale_row_tpl');
                    var addBtn = document.getElementById('lb_locale_add');
                    var seeded = @json((object)($cfg['low_balance_message_locales'] ?? new \stdClass()));
                    var seededLabels = @json((object)($cfg['low_balance_topup_label_locales'] ?? new \stdClass()));
                    var KEYS = ['signed_in','anonymous'];
                    var seq = 0;

                    function rewire(row) {
                        var codeInput = row.querySelector('[data-lb-locale-code]');
                        var raw = (codeInput.value || '').trim();
                        var bucket = raw === '' ? '__pending_' + (row.dataset.rowId || '0') : raw;
                        row.querySelectorAll('[data-lb-loc]').forEach(function (el) {
                            var k = el.getAttribute('data-lb-loc');
                            el.name = 'low_balance_message_locales[' + bucket + '][' + k + ']';
                        });
                        var lbl = row.querySelector('[data-lb-topup-label]');
                        if (lbl) lbl.name = 'low_balance_topup_label_locales[' + bucket + ']';
                    }

                    function addRow(code, values, label) {
                        if (host.querySelectorAll('.lb-locale-row').length >= 50) return;
                        var node = tpl.content.firstElementChild.cloneNode(true);
                        node.dataset.rowId = String(++seq);
                        var codeInput = node.querySelector('[data-lb-locale-code]');
                        codeInput.value = code || '';
                        if (values && typeof values === 'object') {
                            KEYS.forEach(function (k) {
                                var f = node.querySelector('[data-lb-loc="' + k + '"]');
                                if (f && values[k] != null) f.value = values[k];
                            });
                        }
                        var lblInput = node.querySelector('[data-lb-topup-label]');
                        if (lblInput && label != null) lblInput.value = label;
                        node.querySelector('[data-lb-locale-remove]').addEventListener('click', function () { node.remove(); });
                        codeInput.addEventListener('input', function () { rewire(node); });
                        host.appendChild(node);
                        rewire(node);
                    }

                    if (addBtn) addBtn.addEventListener('click', function () { addRow('', null, ''); });

                    // Merge codes from both seed maps so a locale defined
                    // only in the label map (or vice versa) still surfaces
                    // a row admins can edit.
                    var allCodes = {};
                    if (seeded && typeof seeded === 'object' && !Array.isArray(seeded)) {
                        Object.keys(seeded).forEach(function (c) { allCodes[c] = true; });
                    }
                    if (seededLabels && typeof seededLabels === 'object' && !Array.isArray(seededLabels)) {
                        Object.keys(seededLabels).forEach(function (c) { allCodes[c] = true; });
                    }
                    Object.keys(allCodes).sort().forEach(function (code) {
                        addRow(code, seeded ? seeded[code] : null, (seededLabels && seededLabels[code]) || '');
                    });
                })();
                </script>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="font-semibold text-white ak-strong">Customer Care Handoff</h3>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="handoff_enabled" value="0">
                <input type="checkbox" name="handoff_enabled" value="1" class="rounded" {{ $cfg['handoff_enabled'] ? 'checked' : '' }}>
                <span class="text-sm text-white ak-strong">Allow visitors to escalate the chat into the Contact Inbox</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="handoff_freeze_after" value="0">
                <input type="checkbox" name="handoff_freeze_after" value="1" class="rounded" {{ $cfg['handoff_freeze_after'] ? 'checked' : '' }}>
                <span class="text-sm text-white ak-strong">Freeze the bot after handoff (recommended)</span>
            </label>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <div>
                <h3 class="font-semibold text-white ak-strong">Cut-off retry alerts</h3>
                <p class="text-xs text-white/50 mt-1 ak-muted">A scheduled check looks at the last 24h of cut-off / failed assistant streams and notifies admins (in-app + email) when the abandon rate (the share of cut-offs visitors never clicked Retry on) exceeds the threshold below. Useful for catching upstream regressions before users complain.</p>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="cutoff_alert_enabled" value="0">
                <input type="checkbox" name="cutoff_alert_enabled" value="1" class="rounded" {{ !empty($cfg['cutoff_alert_enabled']) ? 'checked' : '' }}>
                <span class="text-sm text-white ak-strong">Enable cut-off abandon-rate alerts</span>
            </label>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Abandon-rate threshold (%)</label>
                    <input type="number" min="1" max="100" name="cutoff_alert_abandon_threshold" value="{{ (int)($cfg['cutoff_alert_abandon_threshold'] ?? 60) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-[10px] text-white/40 mt-1 ak-note">Alert fires when the 24h abandon rate is at or above this value.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Minimum sample size</label>
                    <input type="number" min="1" max="100000" name="cutoff_alert_min_sample" value="{{ (int)($cfg['cutoff_alert_min_sample'] ?? 20) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-[10px] text-white/40 mt-1 ak-note">Skip the check until at least this many cut-offs occurred in 24h.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1 ak-muted">Cooldown between alerts (hours)</label>
                    <input type="number" min="1" max="168" name="cutoff_alert_cooldown_hours" value="{{ (int)($cfg['cutoff_alert_cooldown_hours'] ?? 6) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-[10px] text-white/40 mt-1 ak-note">Suppress repeat alerts inside this window.</p>
                </div>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1 ak-muted">Email recipients (optional)</label>
                <input type="text" name="cutoff_alert_emails" value="{{ $cfg['cutoff_alert_emails'] ?? '' }}" placeholder="ops@example.com, oncall@example.com" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                <p class="text-[10px] text-white/40 mt-1 ak-note">Comma- or space-separated. Leave blank to email every platform admin (settings.manage) with a verified email instead.</p>
            </div>
            @if(!empty($cfg['cutoff_alert_last_sent_at']))
                <p class="text-[11px] text-white/40 ak-note">Last alert dispatched: <span class="text-white/70 ak-strong">{{ $cfg['cutoff_alert_last_sent_at'] }}</span></p>
            @endif
        </div>

        <div class="flex justify-end">
            <button class="px-5 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-semibold">Save settings</button>
        </div>
    </form>
</div>
@endsection
