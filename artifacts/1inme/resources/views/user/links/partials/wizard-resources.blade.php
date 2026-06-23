@php
    /**
     * AI auto-draft resource pickers — shared by the basics + additional steps.
     *
     * Lets the user ground an optional AI auto-draft in:
     *   - their AI Brains (Minds)            → ai_mind_ids[]
     *   - 1inme's built-in knowledge         → include_platform_mind (bool)
     *   - files from their vault             → file_ids[]
     *
     * Selections post with whichever step form they live in and are persisted
     * onto the draft (BiolinkWizardController::applyResourceInputs). The actual
     * "Auto-draft with AI" submit button lives on the final step only.
     *
     * Expects: $myMinds, $platformMinds, $vaultFiles, $selectedMindIds,
     * $includePlatformMind, $selectedFileIds, $aiEnabled.
     */
    $myMinds             = $myMinds ?? [];
    $platformMinds       = $platformMinds ?? [];
    $vaultFiles          = $vaultFiles ?? [];
    $selectedMindIds     = array_map('intval', $selectedMindIds ?? []);
    $includePlatformMind = (bool) ($includePlatformMind ?? false);
    $selectedFileIds     = array_map('intval', $selectedFileIds ?? []);
    $hasAnyResource      = !empty($myMinds) || !empty($platformMinds) || !empty($vaultFiles);
@endphp

<section class="lt-card-reveal glass rounded-2xl overflow-hidden"
         x-data="{
            open: {{ (!empty($selectedMindIds) || $includePlatformMind || !empty($selectedFileIds)) ? 'true' : 'false' }},
            minds: {{ \Illuminate\Support\Js::from($selectedMindIds) }},
            files: {{ \Illuminate\Support\Js::from($selectedFileIds) }},
            toggle(set, id) {
                const i = set.indexOf(id);
                if (i === -1) set.push(id); else set.splice(i, 1);
            },
            has(set, id) { return set.includes(id); }
         }">
    {{-- Sentinel: marks this form as the one carrying the resource picker so the
         controller treats unchecked boxes as "cleared" (authoritative), letting
         users unselect brains/files. --}}
    <input type="hidden" name="_resources_present" value="1">
    <header class="flex items-center gap-3 px-6 py-4 border-b border-white/5 bg-white/[0.02] cursor-pointer"
            @click="open = !open">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-fuchsia-500/25 to-violet-500/15 text-fuchsia-300 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-wand-magic-sparkles"></i>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-white">Power up an AI auto-draft <span class="text-white/30 font-normal">· optional</span></div>
            <div class="text-xs text-white/40">Pick brains &amp; files for the AI to build from. You can still generate instantly.</div>
        </div>
        <i class="fas text-white/30 text-xs" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
    </header>

    <div x-show="open" x-cloak class="p-6 space-y-6">
        {{-- AI Brains (Minds) --}}
        <div>
            <div class="flex items-center gap-2 text-sm font-medium text-white/80 mb-2">
                <i class="fas fa-brain text-fuchsia-300/70 text-xs w-4 text-center"></i>
                <span>AI Brains</span>
            </div>
            @if(empty($myMinds) && empty($platformMinds))
                <p class="text-xs text-white/30">
                    No AI Brains yet — <a href="{{ route('user.ai.mind.show') }}" class="text-violet-300 hover:underline">create one</a> to teach the AI about you, then come back.
                </p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($myMinds as $m)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="ai_mind_ids[]" value="{{ $m['id'] }}"
                                   class="sr-only peer"
                                   @checked(in_array($m['id'], $selectedMindIds, true))
                                   x-init="$el.checked = has(minds, {{ $m['id'] }})"
                                   @change="toggle(minds, {{ $m['id'] }})">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs border border-white/10 bg-white/5 text-white/60 transition-all"
                                  :class="has(minds, {{ $m['id'] }}) ? 'ring-2 ring-fuchsia-500/50 !bg-fuchsia-500/15 !text-white !border-fuchsia-500/40' : ''">
                                <i class="fas fa-brain text-[10px]"></i> {{ $m['name'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            @if(!empty($platformMinds))
                <label class="mt-3 flex items-center gap-2.5 cursor-pointer w-fit">
                    <input type="checkbox" name="include_platform_mind" value="1"
                           class="rounded border-white/20 bg-white/5 text-fuchsia-500 focus:ring-fuchsia-500/40"
                           @checked($includePlatformMind)>
                    <span class="text-xs text-white/60">Also use 1inme's built-in knowledge</span>
                </label>
            @endif
        </div>

        {{-- Vault files --}}
        <div>
            <div class="flex items-center gap-2 text-sm font-medium text-white/80 mb-2">
                <i class="fas fa-folder-open text-fuchsia-300/70 text-xs w-4 text-center"></i>
                <span>Files from your vault</span>
            </div>
            @if(empty($vaultFiles))
                <p class="text-xs text-white/30">
                    Your vault is empty — <a href="{{ route('user.files.index') }}" class="text-violet-300 hover:underline">upload files</a> to feed the AI images and documents.
                </p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-56 overflow-y-auto pr-1">
                    @foreach($vaultFiles as $f)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="file_ids[]" value="{{ $f['id'] }}"
                                   class="sr-only peer"
                                   @checked(in_array($f['id'], $selectedFileIds, true))
                                   x-init="$el.checked = has(files, {{ $f['id'] }})"
                                   @change="toggle(files, {{ $f['id'] }})">
                            <span class="flex items-center gap-2 px-2.5 py-2 rounded-xl text-xs border border-white/10 bg-white/5 text-white/60 transition-all overflow-hidden"
                                  :class="has(files, {{ $f['id'] }}) ? 'ring-2 ring-fuchsia-500/50 !bg-fuchsia-500/15 !text-white !border-fuchsia-500/40' : ''">
                                @if($f['type'] === 'image')
                                    <img src="{{ $f['url'] }}" alt="" class="w-7 h-7 rounded-lg object-cover flex-shrink-0 border border-white/10">
                                @else
                                    <span class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                                        <i class="fas {{ $f['type'] === 'video' ? 'fa-film' : ($f['type'] === 'audio' ? 'fa-music' : 'fa-file-lines') }} text-[11px]"></i>
                                    </span>
                                @endif
                                <span class="truncate">{{ $f['name'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
