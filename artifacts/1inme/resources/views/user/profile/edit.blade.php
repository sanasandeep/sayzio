@extends('user.layouts.app')
@section('title', 'Profile')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-white mb-6">Profile Settings</h1>

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
                            @php($mode = old('follower_updates_mode', $user->follower_updates_mode ?: ($user->notify_follower_updates ? 'digest' : 'off')))
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

                            @php($prefHour = (int) old('digest_preferred_hour', $user->digest_preferred_hour ?? 9))
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
</script>
@endpush
@endsection
