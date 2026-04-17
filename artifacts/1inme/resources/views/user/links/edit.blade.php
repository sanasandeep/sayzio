@extends('user.layouts.app')
@section('title', 'Edit Link')

@section('content')
@php
    $favSrc = $link->favicon
        ?? ($link->settings['biolink']['favicons']['icon_512'] ?? null)
        ?? ($link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
    if (!$favSrc && !empty($link->long_url)) {
        $host = parse_url($link->long_url, PHP_URL_HOST);
        if ($host) $favSrc = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    }
@endphp
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Edit Link',
        'subtitle' => $link->title ?: $link->alias,
        'icon'     => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link',
        'favicon'  => $favSrc,
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-circle ' . ($link->is_active ? 'text-emerald-400' : 'text-red-400'), 'text' => $link->is_active ? 'Active' : 'Inactive'],
            ['icon' => 'fa-' . ($link->type === 'biolink' ? 'th-large' : 'link'), 'text' => \App\Modules\User\Models\Link::typeLabel($link->type)],
        ],
    ])

    @php
        $s = $link->settings ?? [];
        $expMode = !empty($s['expire_on_first_click']) ? 'first_click'
            : (!empty($s['max_clicks']) ? 'clicks'
            : ($link->expires_at ? 'date' : 'none'));
        $openInApp = ($s['open_in_app'] ?? true) ? 'true' : 'false';
        $showPreview = !empty($s['show_preview_page']) ? 'true' : 'false';
    @endphp
    @php
        $tzList = ['UTC','Asia/Kolkata','Asia/Dubai','Asia/Singapore','Asia/Tokyo','Asia/Shanghai','Europe/London','Europe/Berlin','Europe/Paris','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','America/Sao_Paulo','Australia/Sydney','Africa/Lagos','Africa/Cairo','Africa/Johannesburg'];
        $smartRulesJson = json_encode($s['smart_rules'] ?? [], JSON_UNESCAPED_SLASHES);
    @endphp
    <form method="POST" action="{{ route('user.links.update', $link) }}" enctype="multipart/form-data" x-data="{
        passwordProtect: {{ $link->is_password_protected ? 'true' : 'false' }},
        expMode: '{{ $expMode }}',
        openInApp: {{ $openInApp }},
        showPreview: {{ $showPreview }},
        smartRules: {{ $smartRulesJson }},
        // 12-char alphanumeric — matches the regex the server uses to
        // accept variant ids (and is short enough for cookies).
        newId() {
            const a='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let s=''; for (let k=0;k<12;k++) s += a[Math.floor(Math.random()*a.length)];
            return s;
        },
        addRule(type) {
            const self = this;
            const tpl = {
                device:   { type:'device',   match:['mobile'], url:'' },
                country:  { type:'country',  match:['US'],     url:'' },
                language: { type:'language', match:['hi'],     url:'' },
                time:     { type:'time',     from:'09:00', to:'17:00', tz:'UTC', url:'' },
                ab:       { type:'ab',       variants:[ {id:self.newId(), url:'', weight:50}, {id:self.newId(), url:'', weight:50} ] },
            };
            this.smartRules.push(JSON.parse(JSON.stringify(tpl[type])));
        },
        removeRule(i) { this.smartRules.splice(i, 1); },
        moveRule(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.smartRules.length) return;
            const tmp = this.smartRules[i]; this.smartRules[i] = this.smartRules[j]; this.smartRules[j] = tmp;
        },
        addAbVariant(i) { this.smartRules[i].variants.push({ id:this.newId(), url:'', weight:50 }); },
        removeAbVariant(i, j) {
            if (this.smartRules[i].variants.length <= 2) return;
            this.smartRules[i].variants.splice(j, 1);
        },
        toggleMatch(rule, value) {
            const idx = rule.match.indexOf(value);
            if (idx >= 0) rule.match.splice(idx, 1); else rule.match.push(value);
        },
        ruleLabel(t) { return ({device:'Device', country:'Country', language:'Language', time:'Time', ab:'A/B Split'})[t] || t; },
    }">
        @csrf @method('PUT')

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Link Details</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Short URL <span class="text-[10px] text-white/30">(primary alias)</span></label>
                <div class="flex items-center gap-2 text-sm text-violet-400 bg-violet-500/10 px-3 py-2.5 rounded-xl">
                    <span>{{ $link->getShortUrl() }}</span>
                    <span class="text-xs text-white/40 bg-white/10 px-2 py-0.5 rounded">{{ \App\Modules\User\Models\Link::typeLabel($link->type) }}</span>
                </div>
            </div>

            @php
                $maxExtras = auth()->user()->getMaxAliasesPerLink();
                $extras = $link->aliases()->orderBy('created_at')->get();
                $usedExtras = $extras->count();
                $canAddMore = $maxExtras === -1 || $usedExtras < $maxExtras;
                $base = rtrim(config('app.url', url('/')), '/');
            @endphp
            <div class="mb-6 p-4 bg-white/5 border border-white/10 rounded-xl">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h3 class="text-sm font-semibold text-white/80">Alternative URLs (aliases)</h3>
                        <p class="text-xs text-white/40 mt-0.5">All aliases serve the same page — no redirect. Useful for campaign-specific links.</p>
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/10 text-white/60">
                        @if($maxExtras === -1) Unlimited
                        @else {{ $usedExtras }} / {{ $maxExtras }}
                        @endif
                    </span>
                </div>

                @if($maxExtras === 0)
                    <p class="text-xs text-amber-400/80 mt-2"><i class="fas fa-lock mr-1"></i> Your current plan does not include additional aliases. Upgrade your plan to unlock this feature.</p>
                @else
                    @if($extras->isNotEmpty())
                        <ul class="divide-y divide-white/5 mb-3">
                            @foreach($extras as $a)
                                <li class="flex items-center justify-between py-2">
                                    <a href="{{ $base }}/{{ $a->alias }}" target="_blank" class="text-sm text-violet-300 hover:underline truncate">{{ $base }}/{{ $a->alias }}</a>
                                    <div class="flex items-center gap-2 ml-3">
                                        <form method="POST" action="{{ route('user.links.aliases.promote', [$link, $a]) }}" class="inline" onsubmit="return confirm('Make this the primary alias? The current primary will become an alternative.')">
                                            @csrf
                                            <button type="submit" class="text-xs text-white/50 hover:text-white px-2 py-1 rounded hover:bg-white/10" title="Make this the primary alias"><i class="fas fa-star"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('user.links.aliases.destroy', [$link, $a]) }}" class="inline" onsubmit="return confirm('Delete this alias? Anyone visiting it will get a 404.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400/70 hover:text-red-400 px-2 py-1 rounded hover:bg-red-500/10"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($canAddMore)
                        <form method="POST" action="{{ route('user.links.aliases.store', $link) }}" class="flex items-center gap-2">
                            @csrf
                            <span class="text-xs text-white/40 whitespace-nowrap">{{ $base }}/</span>
                            <input type="text" name="alias" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9_-]+"
                                   placeholder="my-campaign" class="flex-1 border border-white/10 rounded-lg px-3 py-2 text-sm bg-white/5 text-white focus:ring-2 focus:ring-violet-500/40">
                            <button type="submit" class="px-3 py-2 text-sm bg-violet-500/20 text-violet-300 hover:bg-violet-500/30 rounded-lg whitespace-nowrap"><i class="fas fa-plus mr-1"></i>Add</button>
                        </form>
                        @error('alias') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    @else
                        <p class="text-xs text-white/40 mt-2"><i class="fas fa-info-circle mr-1"></i> You've reached your plan's alias limit. Upgrade for more.</p>
                    @endif
                @endif
            </div>

            @if($link->type === 'url')
            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Destination URL</label>
                <input type="url" name="long_url" value="{{ old('long_url', $link->long_url) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40">
                @error('long_url') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Redirect Type</label>
                <select name="redirect_type" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                    <option value="301" {{ old('redirect_type', $link->redirect_type) == 301 ? 'selected' : '' }}>301 - Permanent Redirect</option>
                    <option value="302" {{ old('redirect_type', $link->redirect_type) == 302 ? 'selected' : '' }}>302 - Temporary Redirect</option>
                </select>
            </div>
            @endif

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $link->title) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id', $link->project_id) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Status</label>
                    <select name="is_active" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="1" {{ $link->is_active ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ !$link->is_active ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Protection & Scheduling</h2>
            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="is_password_protected" value="1" x-model="passwordProtect" class="rounded text-violet-400 focus:ring-violet-500/40">
                    <span class="text-sm text-white/60">Password protect this link</span>
                </label>
                <div x-show="passwordProtect" class="ml-7">
                    <input type="password" name="password" placeholder="New password (leave empty to keep current)" class="w-full max-w-xs border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>

                <div>
                    <label class="block text-sm text-white/60 mb-1">Goes Live At <span class="text-white/30">(optional)</span></label>
                    <input type="datetime-local" name="start_at"
                           value="{{ old('start_at', !empty($s['start_at']) ? \Carbon\Carbon::parse($s['start_at'])->format('Y-m-d\TH:i') : '') }}"
                           class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                    <p class="text-xs text-white/30 mt-1">Visitors before this time see "not yet available".</p>
                </div>

                <div>
                    <label class="block text-sm text-white/60 mb-1">Expiry Rule</label>
                    <select name="_exp_mode" x-model="expMode" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="none">Never expires</option>
                        <option value="date">Expires on a specific date</option>
                        <option value="clicks">Expires after N clicks</option>
                        <option value="first_click">One-time use (expires after first click)</option>
                    </select>
                </div>

                <div x-show="expMode === 'date'">
                    <label class="block text-sm text-white/60 mb-1">Expiration Date</label>
                    <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $link->expires_at?->format('Y-m-d\TH:i')) }}" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>

                <div x-show="expMode === 'clicks'">
                    <label class="block text-sm text-white/60 mb-1">Maximum Clicks</label>
                    <input type="number" min="1" name="max_clicks" value="{{ old('max_clicks', $s['max_clicks'] ?? '') }}" placeholder="e.g. 100" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>

                <div x-show="expMode !== 'none'">
                    <label class="block text-sm text-white/60 mb-1">After Expiry, Redirect To <span class="text-white/30">(optional)</span></label>
                    <input type="url" name="expiry_url" value="{{ old('expiry_url', $s['expiry_url'] ?? '') }}" placeholder="https://example.com/expired" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                    <p class="text-xs text-white/30 mt-1">Leave empty to show the default "link expired" page.</p>
                </div>
            </div>
        </div>

        @if($link->type === 'url')
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-2">App Opener (Mobile Deep Link)</h2>
            <p class="text-xs text-white/40 mb-4">When a phone visitor lands on your short link, try opening the destination in its native app instead of the browser. Falls back to the web automatically if the app isn't installed.</p>

            @if($detectedApp)
                <div class="flex items-center gap-3 p-3 mb-4 rounded-xl bg-violet-500/10 border border-violet-500/30">
                    <i class="{{ $detectedApp['icon'] }} text-2xl text-violet-300"></i>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-white">Detected: {{ $detectedApp['name'] }}</div>
                        <div class="text-xs text-white/50">Mobile visitors will be sent to the {{ $detectedApp['name'] }} app when this is enabled.</div>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-3 p-3 mb-4 rounded-xl bg-white/5 border border-white/10">
                    <i class="fa-regular fa-circle-question text-xl text-white/40"></i>
                    <div class="flex-1">
                        <div class="text-sm text-white/70">No supported app detected for this URL</div>
                        <div class="text-xs text-white/40">Supported: YouTube, Instagram, TikTok, X, Facebook, Spotify, LinkedIn, Reddit, Pinterest, Snapchat, WhatsApp, Telegram, Threads, Apple Music, Google Maps, Twitch.</div>
                    </div>
                </div>
            @endif

            @if($detectedApp)
                {{-- Hidden 0 ensures unchecking submits a falsy value (browsers
                     omit unchecked checkboxes from form data). Only emitted when
                     an app is actually detected — otherwise saving the form
                     would silently disable opener for that link. --}}
                <input type="hidden" name="open_in_app" value="0">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="open_in_app" value="1" x-model="openInApp" class="rounded text-violet-400 focus:ring-violet-500/40">
                    <span class="text-sm text-white/70">Open in app on mobile when available</span>
                </label>
            @else
                <p class="text-xs text-white/40 italic">Toggle becomes available once your destination URL points at one of the supported apps.</p>
            @endif
        </div>
        @endif

        @if(in_array($link->type, ['url', 'ics', 'vcf'], true))
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-2">Engagement</h2>
            <p class="text-xs text-white/40 mb-4">Show a short interstitial page before redirecting so you can fire tracking pixels and measure dwell time.</p>
            <input type="hidden" name="show_preview_page" value="0">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="show_preview_page" value="1" x-model="showPreview" class="rounded text-violet-400 focus:ring-violet-500/40">
                <span class="text-sm text-white/70">Show preview page before redirecting</span>
            </label>
        </div>
        @endif

        @if($link->type === 'url')
        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h2 class="text-lg font-semibold text-white">Smart Redirect Rules</h2>
                    <p class="text-xs text-white/40 mt-1">Send different visitors to different destinations based on their device, country, language, the time of day, or split traffic for A/B testing. Rules run top-to-bottom — the first match wins. If nothing matches, your default destination above is used.</p>
                </div>
            </div>

            <input type="hidden" name="smart_rules_json" :value="JSON.stringify(smartRules)">

            <template x-for="(rule, i) in smartRules" :key="i">
                <div class="border border-white/10 rounded-xl p-4 mb-3 bg-white/[0.02]">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-violet-500/20 text-violet-300 text-xs font-semibold" x-text="i + 1"></span>
                            <span class="text-sm font-medium text-white" x-text="ruleLabel(rule.type)"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="moveRule(i, -1)" :disabled="i === 0" class="text-xs text-white/50 hover:text-white px-2 py-1 rounded hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed" title="Move up"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" @click="moveRule(i, 1)" :disabled="i === smartRules.length - 1" class="text-xs text-white/50 hover:text-white px-2 py-1 rounded hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed" title="Move down"><i class="fas fa-arrow-down"></i></button>
                            <button type="button" @click="removeRule(i)" class="text-xs text-rose-400 hover:text-rose-300 px-2 py-1 rounded hover:bg-rose-500/10" title="Remove rule"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>

                    {{-- Device --}}
                    <div x-show="rule.type === 'device'" class="space-y-3">
                        <div>
                            <label class="block text-xs text-white/50 mb-1">When the visitor is using</label>
                            <div class="flex gap-2">
                                <template x-for="d in ['mobile','tablet','desktop']" :key="d">
                                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 cursor-pointer hover:bg-white/5" :class="rule.match.includes(d) ? 'bg-violet-500/15 border-violet-500/40' : ''">
                                        <input type="checkbox" :checked="rule.match.includes(d)" @change="toggleMatch(rule, d)" class="rounded text-violet-400">
                                        <span class="text-sm text-white/80 capitalize" x-text="d"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Country --}}
                    <div x-show="rule.type === 'country'" class="space-y-3">
                        <div>
                            <label class="block text-xs text-white/50 mb-1">When visitor is in country (ISO codes, comma-separated — e.g. <span class="text-white/70">IN, US, GB</span>)</label>
                            <input type="text" :value="rule.match.join(', ')" @input="rule.match = $event.target.value.split(',').map(s => s.trim().toUpperCase()).filter(s => /^[A-Z]{2}$/.test(s))" placeholder="IN, US" class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        </div>
                    </div>

                    {{-- Language --}}
                    <div x-show="rule.type === 'language'" class="space-y-3">
                        <div>
                            <label class="block text-xs text-white/50 mb-1">When browser language is (ISO codes, comma-separated — e.g. <span class="text-white/70">hi, en, es</span>)</label>
                            <input type="text" :value="rule.match.join(', ')" @input="rule.match = $event.target.value.split(',').map(s => s.trim().toLowerCase()).filter(s => /^[a-z]{2,3}$/.test(s))" placeholder="hi, en" class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                        </div>
                    </div>

                    {{-- Time --}}
                    <div x-show="rule.type === 'time'" class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-white/50 mb-1">From</label>
                                <input type="time" x-model="rule.from" class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                            </div>
                            <div>
                                <label class="block text-xs text-white/50 mb-1">To</label>
                                <input type="time" x-model="rule.to" class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                            </div>
                            <div>
                                <label class="block text-xs text-white/50 mb-1">Timezone</label>
                                <select x-model="rule.tz" class="w-full border border-white/10 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                                    @foreach($tzList as $tz)
                                        <option value="{{ $tz }}">{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-white/40">Overnight ranges work too (e.g. 22:00 → 06:00 wraps midnight).</p>
                    </div>

                    {{-- Destination URL — common to non-AB rules --}}
                    <div x-show="rule.type !== 'ab'" class="mt-3">
                        <label class="block text-xs text-white/50 mb-1">Send them to</label>
                        <input type="url" x-model="rule.url" placeholder="https://example.com/destination" class="w-full border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                    </div>

                    {{-- A/B Variants --}}
                    <div x-show="rule.type === 'ab'" class="space-y-2">
                        <p class="text-xs text-white/50">Each visitor is randomly assigned a variant on first visit (weighted) and remembered for 30 days.</p>
                        <template x-for="(v, j) in rule.variants" :key="j">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-white/40 w-6 text-right" x-text="String.fromCharCode(65 + j)"></span>
                                <input type="url" x-model="v.url" placeholder="https://variant.example" class="flex-1 border border-white/10 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                                <input type="number" min="1" max="100" x-model.number="v.weight" class="w-20 border border-white/10 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-violet-500/40" title="Weight">
                                <span class="text-xs text-white/40">%</span>
                                <button type="button" @click="removeAbVariant(i, j)" :disabled="rule.variants.length <= 2" class="text-rose-400 hover:text-rose-300 px-2 py-1 rounded hover:bg-rose-500/10 disabled:opacity-30 disabled:cursor-not-allowed" title="Remove variant"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="addAbVariant(i)" :disabled="rule.variants.length >= 10" class="text-xs text-violet-300 hover:text-violet-200 disabled:opacity-30 disabled:cursor-not-allowed"><i class="fas fa-plus mr-1"></i>Add variant</button>
                    </div>
                </div>
            </template>

            <div x-show="smartRules.length === 0" class="text-center py-6 text-sm text-white/40 border border-dashed border-white/10 rounded-xl mb-3">
                No smart rules yet — every visitor goes to your default destination.
            </div>

            <div class="flex flex-wrap gap-2 pt-2 border-t border-white/5">
                <span class="text-xs text-white/50 self-center mr-1">Add rule:</span>
                <button type="button" @click="addRule('device')"   class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-violet-500/20 border border-white/10 hover:border-violet-500/40 text-white/80"><i class="fas fa-mobile-alt mr-1.5"></i>Device</button>
                <button type="button" @click="addRule('country')"  class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-violet-500/20 border border-white/10 hover:border-violet-500/40 text-white/80"><i class="fas fa-globe mr-1.5"></i>Country</button>
                <button type="button" @click="addRule('language')" class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-violet-500/20 border border-white/10 hover:border-violet-500/40 text-white/80"><i class="fas fa-language mr-1.5"></i>Language</button>
                <button type="button" @click="addRule('time')"     class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-violet-500/20 border border-white/10 hover:border-violet-500/40 text-white/80"><i class="fas fa-clock mr-1.5"></i>Time</button>
                <button type="button" @click="addRule('ab')"       class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-violet-500/20 border border-white/10 hover:border-violet-500/40 text-white/80"><i class="fas fa-random mr-1.5"></i>A/B Split</button>
            </div>
        </div>
        @endif

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">SEO Settings</h2>
            <div class="space-y-3">
                <input type="text" name="seo_title" value="{{ old('seo_title', $link->seo_title) }}" placeholder="SEO Title" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                <textarea name="seo_description" placeholder="SEO Description" rows="2" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">{{ old('seo_description', $link->seo_description) }}</textarea>
                @include('user.partials.dropzone-input', [
                    'name'        => 'seo_image',
                    'label'       => 'OG Image',
                    'policy'      => \App\Services\UploadPolicy::for('link.seo_image', auth()->user()),
                    'currentUrl'  => $link->seo_image,
                    'currentName' => $link->seo_image ? 'Saved OG image' : null,
                ])
                @include('user.partials.dropzone-input', [
                    'name'        => 'favicon',
                    'label'       => 'Favicon',
                    'policy'      => \App\Services\UploadPolicy::for('link.favicon', auth()->user()),
                    'currentUrl'  => $link->favicon,
                    'currentName' => $link->favicon ? 'Saved favicon' : null,
                    'hint'        => 'Browser-tab icon · recommended 32x32 or 64x64',
                ])
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Targeting</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Country Restrictions</label>
                    <input type="text" name="country_restrictions" value="{{ old('country_restrictions', implode(',', $link->settings['country_restrictions'] ?? [])) }}" placeholder="e.g. US,GB,CA" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                    <p class="text-xs text-white/30 mt-1">Comma-separated ISO country codes. Leave empty for no restriction.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-2">Device Targeting</label>
                    @php $deviceTargeting = $link->settings['device_targeting'] ?? []; @endphp
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-white/60">
                            <input type="checkbox" name="device_targeting[]" value="desktop" class="rounded text-violet-400" {{ in_array('desktop', $deviceTargeting) ? 'checked' : '' }}>
                            Desktop
                        </label>
                        <label class="flex items-center gap-2 text-sm text-white/60">
                            <input type="checkbox" name="device_targeting[]" value="mobile" class="rounded text-violet-400" {{ in_array('mobile', $deviceTargeting) ? 'checked' : '' }}>
                            Mobile
                        </label>
                        <label class="flex items-center gap-2 text-sm text-white/60">
                            <input type="checkbox" name="device_targeting[]" value="tablet" class="rounded text-violet-400" {{ in_array('tablet', $deviceTargeting) ? 'checked' : '' }}>
                            Tablet
                        </label>
                    </div>
                    <p class="text-xs text-white/30 mt-1">Leave unchecked to allow all devices.</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">UTM Parameters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" name="utm_source" value="{{ old('utm_source', $link->utm_source) }}" placeholder="UTM Source" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                <input type="text" name="utm_medium" value="{{ old('utm_medium', $link->utm_medium) }}" placeholder="UTM Medium" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                <input type="text" name="utm_campaign" value="{{ old('utm_campaign', $link->utm_campaign) }}" placeholder="UTM Campaign" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                <input type="text" name="utm_term" value="{{ old('utm_term', $link->utm_term) }}" placeholder="UTM Term" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
                <input type="text" name="utm_content" value="{{ old('utm_content', $link->utm_content) }}" placeholder="UTM Content" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
        </div>

        @if($pixels->count())
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Tracking Pixels</h2>
            <div class="space-y-2">
                @foreach($pixels as $pixel)
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" {{ $link->pixels->contains($pixel->id) ? 'checked' : '' }} class="rounded text-violet-400 focus:ring-violet-500/40">
                    <span class="text-sm text-white/60">{{ $pixel->name }} ({{ ucfirst(str_replace('_', ' ', $pixel->type)) }})</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.show', $link) }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
