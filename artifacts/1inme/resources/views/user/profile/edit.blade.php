@extends('user.layouts.app')
@section('title', 'Profile')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-white mb-6">Profile Settings</h1>

    @if(session('force_handle_rename'))
        <div class="rounded-xl px-4 py-3 mb-4 bg-amber-500/10 border border-amber-500/40 text-amber-100 text-sm">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <div>
                    <div class="font-semibold">Pick a new handle to continue</div>
                    <div class="mt-1 text-amber-100/80">
                        An admin has reserved <span class="font-mono">{{ session('force_handle_rename') }}</span>,
                        which matches your current handle. Please choose a different one below before continuing.
                    </div>
                    @if(!empty($handleSuggestions))
                        <div class="mt-3">
                            <div class="text-xs uppercase tracking-wider text-amber-200/70 mb-1.5">Available suggestions — click to use</div>
                            <div class="flex flex-wrap gap-2" data-handle-suggestions>
                                @foreach($handleSuggestions as $suggestion)
                                    <button type="button"
                                            data-handle-suggestion="{{ $suggestion }}"
                                            class="px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-400/40 text-amber-50 hover:bg-amber-500/25 hover:border-amber-300/60 text-sm font-mono transition-colors">
                                        {{ '@' . $suggestion }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Personal Information</h2>
        <form method="POST" action="{{ route('user.profile.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                    @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                    @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Billing country</label>
                    @php
                        $countries = [
                            '' => '— Not set (defaults to USD pricing)',
                            'IN' => 'India (₹ INR)',
                            'US' => 'United States ($ USD)',
                            'GB' => 'United Kingdom ($ USD)',
                            'CA' => 'Canada ($ USD)',
                            'AU' => 'Australia ($ USD)',
                            'DE' => 'Germany ($ USD)',
                            'FR' => 'France ($ USD)',
                            'NL' => 'Netherlands ($ USD)',
                            'SG' => 'Singapore ($ USD)',
                            'AE' => 'United Arab Emirates ($ USD)',
                            'BR' => 'Brazil ($ USD)',
                            'MX' => 'Mexico ($ USD)',
                            'JP' => 'Japan ($ USD)',
                            'OTHER' => 'Other (everywhere else, $ USD)',
                        ];
                    @endphp
                    <select name="country" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        @foreach($countries as $code => $label)
                            @if($code === 'OTHER')
                                @continue
                            @endif
                            <option value="{{ $code }}" {{ old('country', $user->country) === $code ? 'selected' : '' }} class="bg-[#0d0818]">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-white/30 mt-1">Used to determine which currency you'll be billed in. Switching country updates your next invoice's currency.</p>
                    @error('country')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="border-t border-white/10 pt-4">
                    <h3 class="text-sm font-semibold text-white mb-1">Billing address &amp; tax ID</h3>
                    <p class="text-[11px] text-white/40 mb-3">Used to calculate the right tax on your invoices and to print
                        on your tax invoice PDF. GSTIN is for Indian businesses; VATIN is for EU/UK businesses claiming
                        reverse-charge.</p>
                    <div class="space-y-3" data-billing-address>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-white/50 mb-1">Country (ISO-2)</label>
                                <input type="text" name="billing_country" maxlength="2" value="{{ old('billing_country', $billing->country ?? $user->country) }}"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white uppercase outline-none focus:ring-2 focus:ring-violet-500/40">
                            </div>
                            <div>
                                <label class="block text-xs text-white/50 mb-1">State / region</label>
                                <select name="billing_region" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                                    <option value="" class="bg-[#0d0818]">— None / N/A —</option>
                                    @foreach($inStates as $code => $label)
                                        <option value="{{ $code }}" {{ old('billing_region', $billing->region) === $code ? 'selected' : '' }} class="bg-[#0d0818]">IN-{{ $code }} · {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="billing_postal_code" placeholder="Postal code" value="{{ old('billing_postal_code', $billing->postal_code) }}" maxlength="16"
                                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                            <input type="text" name="billing_city" placeholder="City" value="{{ old('billing_city', $billing->city) }}" maxlength="100"
                                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                        </div>
                        <input type="text" name="billing_line1" placeholder="Address line 1" value="{{ old('billing_line1', $billing->line1) }}" maxlength="255"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                        <input type="text" name="billing_line2" placeholder="Address line 2 (optional)" value="{{ old('billing_line2', $billing->line2) }}" maxlength="255"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                        <input type="text" name="business_name" placeholder="Registered business name (optional)" value="{{ old('business_name', $billing->business_name) }}" maxlength="255"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                        <div class="grid grid-cols-3 gap-3">
                            <select name="tax_id_kind" data-tax-kind class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white outline-none focus:ring-2 focus:ring-violet-500/40">
                                @php($currentKind = old('tax_id_kind', $billing->tax_id_kind ?: 'NONE'))
                                <option value="NONE"  {{ $currentKind === 'NONE'  ? 'selected' : '' }} class="bg-[#0d0818]">No tax ID</option>
                                <option value="GSTIN" {{ $currentKind === 'GSTIN' ? 'selected' : '' }} class="bg-[#0d0818]">GSTIN (India)</option>
                                <option value="VATIN" {{ $currentKind === 'VATIN' ? 'selected' : '' }} class="bg-[#0d0818]">VATIN (EU / UK)</option>
                            </select>
                            <input type="text" name="tax_id" data-tax-id placeholder="Tax ID number" value="{{ old('tax_id', $billing->tax_id) }}" maxlength="32"
                                   class="col-span-2 px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white uppercase outline-none focus:ring-2 focus:ring-violet-500/40">
                        </div>
                        <p class="text-[11px]" data-tax-feedback></p>
                        @error('tax_id')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/60 mb-1.5">Timezone</label>
                        <select name="timezone" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" {{ $user->timezone == $tz ? 'selected' : '' }} class="bg-[#0d0818]">{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/60 mb-1.5">Language</label>
                        <select name="language" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <option value="en" {{ $user->language == 'en' ? 'selected' : '' }} class="bg-[#0d0818]">English</option>
                        </select>
                    </div>
                </div>

                <div class="border-t border-white/10 pt-4">
                    <h3 class="text-sm font-semibold text-white mb-3">Public Profile</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-white/60 mb-1.5">Handle (used in the Creators directory)</label>
                            <input type="text" name="handle" value="{{ old('handle', $user->handle) }}" placeholder="your_handle"
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                            @error('handle')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/60 mb-1.5">Bio</label>
                            <textarea name="bio" rows="3" maxlength="500" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('bio', $user->bio) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/60 mb-1.5">What best describes you?</label>
                            <select name="persona" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                                <option value="" class="bg-[#0d0818]">Prefer not to say</option>
                                @foreach(\App\Modules\User\Services\PersonaCatalog::all() as $p)
                                    <option value="{{ $p['slug'] }}" {{ old('persona', $user->persona) === $p['slug'] ? 'selected' : '' }} class="bg-[#0d0818]">{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-white/30 mt-1">Helps us recommend the right templates and blocks for your page.</p>
                            @error('persona')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/60 mb-1.5">Avatar</label>
                            @if($user->avatar)<img src="{{ $user->avatar }}" class="w-16 h-16 rounded-full mb-2 object-cover"/>@endif
                            <input type="file" name="avatar" accept="image/*" class="text-white/70 text-sm"/>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-white/70">
                            <input type="checkbox" name="discoverable" value="1" {{ $user->discoverable ? 'checked' : '' }} class="w-4 h-4">
                            Show me in the public Creators directory at /creators
                        </label>
                        <label class="flex items-center gap-2 text-sm text-white/70">
                            <input type="checkbox" name="allow_followers" value="1" {{ ($user->allow_followers ?? true) ? 'checked' : '' }} class="w-4 h-4">
                            Allow other people to follow me
                        </label>
                    </div>
                </div>

                <div class="border-t border-white/10 pt-4">
                    <h3 class="text-sm font-semibold text-white mb-3">Notifications</h3>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm text-white/70">
                            <input type="checkbox" name="notify_new_follower" value="1" {{ $user->notify_new_follower ? 'checked' : '' }} class="w-4 h-4">
                            Email me when someone follows me
                        </label>
                        <div>
                            <p class="text-sm text-white/70 mb-2">When creators I follow post updates:</p>
                            @php
                                $mode = old('follower_updates_mode', $user->follower_updates_mode ?: ($user->notify_follower_updates ? 'digest' : 'off'));
                            @endphp
                            <div class="space-y-2 pl-1">
                                <label class="flex items-start gap-2 text-sm text-white/70">
                                    <input type="radio" name="follower_updates_mode" value="instant" {{ $mode === 'instant' ? 'checked' : '' }} class="w-4 h-4 mt-0.5">
                                    <span><span class="text-white">Instant</span> — email me as soon as something happens</span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-white/70">
                                    <input type="radio" name="follower_updates_mode" value="digest" {{ $mode === 'digest' ? 'checked' : '' }} class="w-4 h-4 mt-0.5">
                                    <span><span class="text-white">Daily digest</span> — one email per day with everything new (recommended)</span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-white/70">
                                    <input type="radio" name="follower_updates_mode" value="off" {{ $mode === 'off' ? 'checked' : '' }} class="w-4 h-4 mt-0.5">
                                    <span><span class="text-white">Off</span> — don't email me about creator updates</span>
                                </label>
                            </div>

                            @php
                                $prefHour = (int) old('digest_preferred_hour', $user->digest_preferred_hour ?? 9);
                            @endphp
                            <div class="mt-4 pl-1">
                                <label class="block text-sm font-medium text-white/60 mb-1.5">
                                    Send my daily digest at
                                </label>
                                <div class="flex items-center gap-3">
                                    <select name="digest_preferred_hour" class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                                        @for($h = 0; $h < 24; $h++)
                                            @php
                                                $suffix = $h < 12 ? 'am' : 'pm';
                                                $disp = $h % 12; if ($disp === 0) $disp = 12;
                                            @endphp
                                            <option value="{{ $h }}" {{ $prefHour === $h ? 'selected' : '' }} class="bg-[#0d0818]">
                                                {{ $disp }}:00 {{ $suffix }}
                                            </option>
                                        @endfor
                                    </select>
                                    <span class="text-xs text-white/50">
                                        in your timezone ({{ $user->timezone ?: 'UTC' }})
                                    </span>
                                </div>
                                <p class="text-xs text-white/40 mt-1.5">Only applies when "Daily digest" is selected above.</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-white/10 bg-white/5 overflow-hidden"
                             data-digest-preview
                             data-preview-url="{{ route('user.profile.digest.preview') }}">
                            <div class="flex items-center justify-between px-4 py-2 border-b border-white/10 bg-white/5">
                                <div class="flex items-center gap-2 text-xs text-white/70">
                                    <i class="fas fa-envelope text-violet-400"></i>
                                    <span>Daily digest preview</span>
                                </div>
                                <span data-digest-badge-slot>
                                    @if($digestPreviewIsReal)
                                        <span class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-emerald-300 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30" title="This preview uses your real pending notifications.">
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span>
                                            </span>
                                            Live preview · showing your {{ $digestPreviewCount }} pending update{{ $digestPreviewCount === 1 ? '' : 's' }}
                                        </span>
                                    @else
                                        <span class="text-[10px] uppercase tracking-wider text-violet-300/80 px-2 py-0.5 rounded-full bg-violet-500/15 border border-violet-500/30" title="You don't have any pending updates yet, so this is a placeholder.">
                                            Example preview · not your real queue
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <iframe
                                data-digest-iframe
                                title="Digest email preview"
                                sandbox=""
                                class="block w-full bg-white"
                                style="height: 520px; border: 0;"
                                srcdoc="{{ $digestPreviewHtml }}"
                            ></iframe>
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition-all hover:shadow-lg hover:shadow-violet-500/20">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-1">Preview your daily digest</h2>
        <p class="text-sm text-white/50 mb-4">Send yourself a sample email using your current pending updates. Nothing in your real digest queue is changed.</p>
        <form method="POST" action="{{ route('user.profile.digest.sample') }}">
            @csrf
            <button type="submit" class="px-5 py-2 bg-white/5 border border-white/15 text-white rounded-xl font-medium hover:bg-white/10 transition-all">
                <i class="fas fa-paper-plane mr-1.5 text-violet-300"></i>
                Send sample digest
            </button>
        </form>
    </div>

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1">Sign-in security</h2>
        <p class="text-sm text-white/50 mb-4">Your account is protected by one-time codes — there's no password to manage.</p>
        <div class="flex items-start gap-3 rounded-xl px-4 py-3" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.20);">
            <i class="fas fa-shield-alt text-violet-400 mt-0.5"></i>
            <div class="text-sm text-white/70">
                Each time you sign in, we send a fresh 6-digit code to your email{{ auth()->user()->mobile ? ' or mobile number' : '' }}. Keep your contact details up to date above so you can always receive it.
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-digest-preview]');
    if (!root) return;
    const url = root.dataset.previewUrl;
    const badgeSlot = root.querySelector('[data-digest-badge-slot]');
    const iframe = root.querySelector('[data-digest-iframe]');
    if (!url || !badgeSlot || !iframe) return;

    let lastCount = {!! json_encode((int) $digestPreviewCount) !!};
    let lastIsReal = {!! json_encode((bool) $digestPreviewIsReal) !!};
    let lastHtml = iframe.getAttribute('srcdoc') || '';
    let inFlight = false;
    let timer = null;

    function renderBadge(isReal, count) {
        if (isReal) {
            const plural = count === 1 ? '' : 's';
            badgeSlot.innerHTML =
                '<span class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-emerald-300 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30" title="This preview uses your real pending notifications.">' +
                  '<span class="relative flex h-1.5 w-1.5">' +
                    '<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>' +
                    '<span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span>' +
                  '</span>' +
                  'Live preview · showing your ' + count + ' pending update' + plural +
                '</span>';
        } else {
            badgeSlot.innerHTML =
                '<span class="text-[10px] uppercase tracking-wider text-violet-300/80 px-2 py-0.5 rounded-full bg-violet-500/15 border border-violet-500/30" title="You don\'t have any pending updates yet, so this is a placeholder.">' +
                  'Example preview · not your real queue' +
                '</span>';
        }
    }

    async function poll() {
        if (inFlight || document.hidden) return;
        inFlight = true;
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            const count = Number(data.count) || 0;
            const isReal = !!data.isReal;
            const html = typeof data.html === 'string' ? data.html : '';
            if (count !== lastCount || isReal !== lastIsReal) {
                renderBadge(isReal, count);
                lastCount = count;
                lastIsReal = isReal;
            }
            if (html && html !== lastHtml) {
                iframe.setAttribute('srcdoc', html);
                lastHtml = html;
            }
        } catch (e) {
            // silent — next tick will retry
        } finally {
            inFlight = false;
        }
    }

    function start() { if (!timer) timer = setInterval(poll, 20000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stop(); else { start(); poll(); }
    });
    start();
})();

(function () {
    // Live tax-id format feedback (PHP-only validation also runs on submit).
    const root = document.querySelector('[data-billing-address]');
    if (root) {
        const kindEl = root.querySelector('[data-tax-kind]');
        const idEl   = root.querySelector('[data-tax-id]');
        const fb     = root.querySelector('[data-tax-feedback]');
        function check() {
            const kind = kindEl.value;
            const v = (idEl.value || '').toUpperCase().trim();
            if (!v || kind === 'NONE') { fb.textContent = ''; fb.className = 'text-[11px]'; return; }
            let ok = false, msg = '';
            if (kind === 'GSTIN') {
                ok = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/.test(v);
                msg = ok ? 'GSTIN format looks valid (server will verify checksum on save).' : 'Expected 15 chars: 2 digits + 5 letters + 4 digits + 1 letter + 1 alphanum + Z + 1 alphanum.';
            } else if (kind === 'VATIN') {
                ok = /^[A-Z]{2}[A-Z0-9]{2,}$/.test(v);
                msg = ok ? 'VAT number format looks valid (server checks per-country pattern on save).' : 'Expected 2-letter country prefix followed by the national number.';
            }
            fb.textContent = msg;
            fb.className = 'text-[11px] ' + (ok ? 'text-emerald-300' : 'text-amber-300');
        }
        kindEl.addEventListener('change', check);
        idEl.addEventListener('input', check);
        check();
    }
})();

(function () {
    document.querySelectorAll('[data-handle-suggestion]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const value = btn.getAttribute('data-handle-suggestion') || '';
            const input = document.querySelector('input[name="handle"]');
            if (!input) return;
            input.value = value;
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
})();
</script>
@endpush
@endsection
