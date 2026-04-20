@php
    $inputClass = 'w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none';
    $labelClass = 'block text-xs text-white/40 mb-1';
    $socialOptions = ['instagram','twitter','facebook','tiktok','youtube','linkedin','github','discord','telegram','whatsapp','snapchat','pinterest','twitch','dribbble','spotify','soundcloud','apple','reddit','medium','behance','website','email'];

    // The user's connected accounts — used as an optional source for each
    // entry's follower count and canonical profile URL.
    $myConnections = collect();
    if (auth()->check()) {
        $myConnections = \App\Modules\User\Models\SocialAccountConnection::where('user_id', auth()->id())
            ->orderBy('platform')->orderBy('handle')->get();
    }
    $connByPlatform = $myConnections->groupBy('platform');
    $connectedRoute = route('user.social-accounts.index');
    // Map of platform => count of user's connections, exposed to Alpine so the
    // editor can warn inline when "Follow with count" is chosen for a platform
    // with no matching connected account.
    $connCounts = $myConnections->groupBy('platform')->map->count()->toArray();

    // Per-platform OAuth-availability map, mirroring the notices on the
    // Connected Accounts page. Values:
    //   'available'   — server has CLIENT_ID + CLIENT_SECRET, one-click works.
    //   'unavailable' — platform supports OAuth but admin hasn't configured it.
    //   (missing)     — platform isn't OAuth-capable (handle/manual only).
    // We deliberately don't expose env var names — only the user-facing state.
    $oauthSvc = app(\App\Modules\User\Services\SocialFollowers\SocialOAuthService::class);
    $oauthHints = [];
    foreach (array_keys(\App\Modules\User\Services\SocialFollowers\SocialOAuthService::PROVIDERS) as $_p) {
        $oauthHints[$_p] = $oauthSvc->isConfigured($_p) ? 'available' : 'unavailable';
    }
    // Human-friendly labels for every platform the editor knows about, so the
    // hint reads "LinkedIn"/"TikTok" rather than the raw slug.
    $platformLabels = [];
    foreach ($socialOptions as $_p) {
        $platformLabels[$_p] = \App\Modules\User\Models\SocialAccountConnection::platformLabel($_p);
    }

    // Decide which editor to show. The grouped variant (`socials_multi`) keeps
    // its entries inside `settings.groups[*].platforms`; the standard and
    // custom variants store a flat `settings.platforms` list.
    $isGrouped = isset($block) && $block->type === 'socials_multi';

    // For grouped blocks, seed Alpine with the existing groups. If a legacy
    // block has only the flat `platforms` array, lift it into a single default
    // group so the creator can keep editing without losing entries.
    if ($isGrouped) {
        $initialGroups = $s['groups'] ?? [];
        if (empty($initialGroups) && ! empty($s['platforms'] ?? [])) {
            $initialGroups = [['name' => '', 'platforms' => $s['platforms']]];
        }
        // Normalise so every entry has the new keys with sensible defaults.
        foreach ($initialGroups as &$_g) {
            $_g['name'] = $_g['name'] ?? '';
            $_g['platforms'] = array_values(array_map(function ($p) {
                return [
                    'name'          => $p['name']          ?? '',
                    'url'           => $p['url']           ?? '',
                    'display'       => $p['display']       ?? 'icon',
                    'connection_id' => $p['connection_id'] ?? '',
                ];
            }, $_g['platforms'] ?? []));
        }
        unset($_g);
    }
@endphp

@if($isGrouped)
{{-- Grouped editor (socials_multi) ------------------------------------- --}}
<div x-data="{ groups: {{ json_encode(array_values($initialGroups)) }}, connCounts: {{ json_encode($connCounts) }}, oauthHints: {{ json_encode($oauthHints) }}, platformLabels: {{ json_encode($platformLabels) }} }">
    <div class="flex items-center justify-between mb-2">
        <label class="{{ $labelClass }} mb-0">Social Groups</label>
        <a href="{{ $connectedRoute }}" target="_blank" class="text-[10px] text-violet-400 hover:text-violet-300">
            <i class="fas fa-share-nodes mr-1"></i>Manage connected accounts
        </a>
    </div>

    <template x-for="(g, gi) in groups" :key="gi">
        <div class="glass rounded-lg p-3 mb-3 space-y-2">
            <div class="flex items-center gap-2">
                <input type="text" x-model="groups[gi].name" :name="'settings[groups]['+gi+'][name]'"
                       placeholder="Group label (optional)" class="{{ $inputClass }}">
                <button type="button" @click="groups.splice(gi,1)" class="text-xs text-red-400/60 hover:text-red-400 whitespace-nowrap">
                    <i class="fas fa-times mr-1"></i>Remove group
                </button>
            </div>

            <template x-for="(p, i) in groups[gi].platforms" :key="i">
                <div class="rounded-lg p-2 space-y-2" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                    <div class="grid grid-cols-2 gap-2">
                        <select x-model="groups[gi].platforms[i].name"
                                :name="'settings[groups]['+gi+'][platforms]['+i+'][name]'"
                                class="{{ $inputClass }}">
                            <option value="" class="bg-[#0d0818]">Select…</option>
                            @foreach($socialOptions as $opt)
                                <option value="{{ $opt }}" class="bg-[#0d0818]">{{ ucfirst($opt) }}</option>
                            @endforeach
                        </select>
                        <input type="url" x-model="groups[gi].platforms[i].url"
                               :name="'settings[groups]['+gi+'][platforms]['+i+'][url]'"
                               placeholder="https://… (or leave blank if using a connected account)"
                               class="{{ $inputClass }}">
                    </div>

                    {{-- Display style: icon · follow button · follow + count --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-white/30 block mb-1">Display</label>
                            <select x-model="groups[gi].platforms[i].display"
                                    :name="'settings[groups]['+gi+'][platforms]['+i+'][display]'"
                                    class="{{ $inputClass }}">
                                <option value="icon"         class="bg-[#0d0818]">Icon</option>
                                <option value="follow"       class="bg-[#0d0818]">Follow button</option>
                                <option value="follow_count" class="bg-[#0d0818]">Follow button with count</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-white/30 block mb-1">Follower source</label>
                            {{-- Each option is hidden when its data-platform doesn't match
                                 the entry's selected platform; this prevents creators
                                 from accidentally pairing a connection from a different
                                 network with this entry. --}}
                            <select x-model="groups[gi].platforms[i].connection_id"
                                    :name="'settings[groups]['+gi+'][platforms]['+i+'][connection_id]'"
                                    @change="if (groups[gi].platforms[i].connection_id) { let opt = $event.target.selectedOptions[0]; if (opt && opt.dataset.platform && opt.dataset.platform !== groups[gi].platforms[i].name) groups[gi].platforms[i].connection_id = ''; }"
                                    class="{{ $inputClass }}">
                                <option value="" class="bg-[#0d0818]">— Manual URL only —</option>
                                @foreach($myConnections as $conn)
                                    <option value="{{ $conn->id }}" data-platform="{{ $conn->platform }}"
                                            x-show="!groups[gi].platforms[i].name || groups[gi].platforms[i].name === '{{ $conn->platform }}'"
                                            class="bg-[#0d0818]">
                                        {{ \App\Modules\User\Models\SocialAccountConnection::platformLabel($conn->platform) }} · @{{ $conn->handle }}
                                        @if($conn->follower_count !== null)
                                            ({{ \App\Modules\User\Models\SocialAccountConnection::formatCount($conn->follower_count) }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- OAuth-availability hint for the selected platform —
                         mirrors the wording on the Connected Accounts page so
                         creators know up front whether a Follow button will be
                         able to auto-refresh its count. --}}
                    <p x-show="groups[gi].platforms[i].name && oauthHints[groups[gi].platforms[i].name] === 'available'"
                       class="text-[11px] text-emerald-300/90 bg-emerald-500/10 border border-emerald-500/20 rounded px-2 py-1.5"
                       style="display: none;">
                        <i class="fas fa-circle-check mr-1"></i>
                        One-click connect available for <span x-text="platformLabels[groups[gi].platforms[i].name] || groups[gi].platforms[i].name"></span>.
                        <a href="{{ $connectedRoute }}" target="_blank" class="underline hover:text-emerald-200">Connect now</a>
                        — no token to copy or paste.
                    </p>
                    <p x-show="groups[gi].platforms[i].name && oauthHints[groups[gi].platforms[i].name] === 'unavailable'"
                       class="text-[11px] text-amber-300/80 bg-amber-500/10 border border-amber-500/20 rounded px-2 py-1.5"
                       style="display: none;">
                        <i class="fas fa-circle-info mr-1"></i>
                        One-click connect isn't enabled for <span x-text="platformLabels[groups[gi].platforms[i].name] || groups[gi].platforms[i].name"></span> on this server yet.
                        You can <a href="{{ $connectedRoute }}" target="_blank" class="underline hover:text-amber-200">paste a long-lived access token</a>,
                        or ask a server admin to enable it.
                    </p>

                    {{-- Inline warning when "Follow with count" is selected for a
                         platform that has no matching connected account. --}}
                    <p x-show="groups[gi].platforms[i].display === 'follow_count' && groups[gi].platforms[i].name && (!connCounts[groups[gi].platforms[i].name] || connCounts[groups[gi].platforms[i].name] === 0)"
                       class="text-[11px] text-amber-300/80 bg-amber-500/10 border border-amber-500/20 rounded px-2 py-1.5"
                       style="display: none;">
                        <i class="fas fa-circle-exclamation mr-1"></i>
                        You don't have a connected <span x-text="groups[gi].platforms[i].name || ''"></span> account yet, so the count won't show.
                        <a href="{{ $connectedRoute }}" target="_blank" class="underline hover:text-amber-200">Connect one</a>.
                    </p>

                    <div class="flex justify-end">
                        <button type="button" @click="groups[gi].platforms.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400">
                            <i class="fas fa-times mr-1"></i>Remove entry
                        </button>
                    </div>
                </div>
            </template>

            <button type="button"
                    @click="groups[gi].platforms.push({name:'',url:'',display:'icon',connection_id:''})"
                    class="text-xs text-violet-400 hover:text-violet-300">
                <i class="fas fa-plus mr-1"></i>Add entry
            </button>
        </div>
    </template>

    <button type="button"
            @click="groups.push({name:'',platforms:[{name:'',url:'',display:'icon',connection_id:''}]})"
            class="text-xs text-violet-400 hover:text-violet-300">
        <i class="fas fa-plus mr-1"></i>Add Group
    </button>

    <p class="text-[10px] text-white/30 mt-2">
        Follower counts come from <a href="{{ $connectedRoute }}" target="_blank" class="text-violet-400 hover:underline">your connected accounts</a> and refresh every few hours.
    </p>
</div>
@else
{{-- Standard / custom editor (flat platforms) -------------------------- --}}
<div x-data="{ platforms: {{ json_encode($s['platforms'] ?? []) }}, connCounts: {{ json_encode($connCounts) }}, oauthHints: {{ json_encode($oauthHints) }}, platformLabels: {{ json_encode($platformLabels) }} }">
    <div class="flex items-center justify-between mb-2">
        <label class="{{ $labelClass }} mb-0">Social Platforms</label>
        <a href="{{ $connectedRoute }}" target="_blank" class="text-[10px] text-violet-400 hover:text-violet-300">
            <i class="fas fa-share-nodes mr-1"></i>Manage connected accounts
        </a>
    </div>

    <template x-for="(p, i) in platforms" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <select x-model="platforms[i].name" :name="'settings[platforms]['+i+'][name]'" class="{{ $inputClass }}">
                    <option value="" class="bg-[#0d0818]">Select…</option>
                    @foreach($socialOptions as $opt)
                        <option value="{{ $opt }}" class="bg-[#0d0818]">{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
                <input type="url" x-model="platforms[i].url" :name="'settings[platforms]['+i+'][url]'" placeholder="https://… (or leave blank if using a connected account)" class="{{ $inputClass }}">
            </div>

            {{-- Display style: icon · follow button · follow + count --}}
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-white/30 block mb-1">Display</label>
                    <select x-model="platforms[i].display" :name="'settings[platforms]['+i+'][display]'" class="{{ $inputClass }}">
                        <option value="icon"        class="bg-[#0d0818]">Icon</option>
                        <option value="follow"      class="bg-[#0d0818]">Follow button</option>
                        <option value="follow_count" class="bg-[#0d0818]">Follow button with count</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-white/30 block mb-1">Follower source</label>
                    {{-- Each option is hidden when its data-platform doesn't match
                         the entry's selected platform; this prevents creators
                         from accidentally pairing, say, a GitHub connection
                         with a TikTok entry. --}}
                    <select x-model="platforms[i].connection_id" :name="'settings[platforms]['+i+'][connection_id]'"
                            @change="if (platforms[i].connection_id) { let opt = $event.target.selectedOptions[0]; if (opt && opt.dataset.platform && opt.dataset.platform !== platforms[i].name) platforms[i].connection_id = ''; }"
                            class="{{ $inputClass }}">
                        <option value=""  class="bg-[#0d0818]">— Manual URL only —</option>
                        @foreach($myConnections as $conn)
                            <option value="{{ $conn->id }}" data-platform="{{ $conn->platform }}"
                                    x-show="!platforms[i].name || platforms[i].name === '{{ $conn->platform }}'"
                                    class="bg-[#0d0818]">
                                {{ \App\Modules\User\Models\SocialAccountConnection::platformLabel($conn->platform) }} · @{{ $conn->handle }}
                                @if($conn->follower_count !== null)
                                    ({{ \App\Modules\User\Models\SocialAccountConnection::formatCount($conn->follower_count) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- OAuth-availability hint for the selected platform —
                 mirrors the wording on the Connected Accounts page so
                 creators know up front whether a Follow button will be
                 able to auto-refresh its count. --}}
            <p x-show="platforms[i].name && oauthHints[platforms[i].name] === 'available'"
               class="text-[11px] text-emerald-300/90 bg-emerald-500/10 border border-emerald-500/20 rounded px-2 py-1.5"
               style="display: none;">
                <i class="fas fa-circle-check mr-1"></i>
                One-click connect available for <span x-text="platformLabels[platforms[i].name] || platforms[i].name"></span>.
                <a href="{{ $connectedRoute }}" target="_blank" class="underline hover:text-emerald-200">Connect now</a>
                — no token to copy or paste.
            </p>
            <p x-show="platforms[i].name && oauthHints[platforms[i].name] === 'unavailable'"
               class="text-[11px] text-amber-300/80 bg-amber-500/10 border border-amber-500/20 rounded px-2 py-1.5"
               style="display: none;">
                <i class="fas fa-circle-info mr-1"></i>
                One-click connect isn't enabled for <span x-text="platformLabels[platforms[i].name] || platforms[i].name"></span> on this server yet.
                You can <a href="{{ $connectedRoute }}" target="_blank" class="underline hover:text-amber-200">paste a long-lived access token</a>,
                or ask a server admin to enable it.
            </p>

            {{-- Inline warning when "Follow with count" is selected for a
                 platform that has no matching connected account. The count
                 will silently be hidden at render time, so we tell the
                 creator up front and link them to the connect page. --}}
            <p x-show="platforms[i].display === 'follow_count' && platforms[i].name && (!connCounts[platforms[i].name] || connCounts[platforms[i].name] === 0)"
               class="text-[11px] text-amber-300/80 bg-amber-500/10 border border-amber-500/20 rounded px-2 py-1.5"
               style="display: none;">
                <i class="fas fa-circle-exclamation mr-1"></i>
                You don't have a connected <span x-text="platforms[i].name || ''"></span> account yet, so the count won't show.
                <a href="{{ $connectedRoute }}" target="_blank" class="underline hover:text-amber-200">Connect one</a>.
            </p>

            <div class="flex items-center justify-between">
                <p class="text-[10px] text-white/30">
                    Follower counts come from <a href="{{ $connectedRoute }}" target="_blank" class="text-violet-400 hover:underline">your connected accounts</a> and refresh every few hours.
                </p>
                <button type="button" @click="platforms.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400">
                    <i class="fas fa-times mr-1"></i>Remove
                </button>
            </div>
        </div>
    </template>

    <button type="button" @click="platforms.push({name:'',url:'',display:'icon',connection_id:''})" class="text-xs text-violet-400 hover:text-violet-300">
        <i class="fas fa-plus mr-1"></i>Add Platform
    </button>
</div>
@endif
