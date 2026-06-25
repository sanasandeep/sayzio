@php
    /**
     * Reusable "Short URL & Aliases" card.
     * Shows the editable primary alias plus the additional-aliases manager.
     * Drop-in: @include('user.links.partials.aliases-card', ['link' => $link])
     */
    use App\Modules\Common\Support\PlatformHosts;
    $maxExtras  = auth()->user()->getMaxAliasesPerLink($link->type);
    $extras     = $link->aliases()->orderBy('created_at')->get();
    $usedExtras = $extras->count();
    $canAddMore = $maxExtras === -1 || $usedExtras < $maxExtras;
    // Prefer the host the creator is currently browsing on (when it's a
    // configured platform host) so the displayed/copied URL matches their
    // current context. Falls back to the platform's primary public host
    // (deploy domain → dev preview → APP_URL), never to "localhost/".
    $aliasHost  = $link->domain?->domain
        ?: (PlatformHosts::currentRequestHost() ?: PlatformHosts::primary());
    // Only show "also live on" hints for platform short links (no custom domain).
    $showHostsHint = !$link->domain;
@endphp

<div class="card-premium p-6" x-data="{ editing: false, alias: @js($link->alias) }">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(61,107,255,0.1);">
                <i class="fas fa-link text-blue-400 text-xs"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Short URL &amp; Aliases</h3>
                <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">All aliases serve the same page — no redirect. Domain is fixed; only the slug is editable.</p>
            </div>
        </div>
        <span class="text-[10px] px-2 py-0.5 rounded-full" style="background: var(--bg-glass-input); color: var(--text-faint);">
            @if($maxExtras === -1)
                {{ $usedExtras }} {{ $usedExtras === 1 ? 'extra' : 'extras' }} · Unlimited
            @else
                {{ $usedExtras }} / {{ $maxExtras }} extras
            @endif
        </span>
    </div>

    {{-- PRIMARY ALIAS --}}
    <div class="mb-3">
        <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Primary alias</label>
        <div class="flex items-stretch rounded-xl overflow-hidden" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
            <span class="px-3 flex items-center text-sm flex-shrink-0" style="color: var(--text-faint); border-right: 1px solid var(--border-glass); background: var(--bg-glass);">{{ $aliasHost }}/</span>
            <template x-if="!editing">
                <button type="button"
                        class="flex-1 px-3 py-2.5 text-sm font-medium text-left flex items-center justify-between gap-2 group"
                        style="color: var(--text-primary);"
                        @click="editing = true; $nextTick(() => $refs.aliasInput.focus())">
                    <span x-text="alias"></span>
                    <span class="text-[10px] opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1" style="color: var(--text-faint);">
                        <i class="fas fa-pen text-[9px]"></i> Edit
                    </span>
                </button>
            </template>
            <template x-if="editing">
                <div class="flex-1 flex items-center">
                    <input x-ref="aliasInput" type="text" x-model="alias"
                           minlength="3" maxlength="60" pattern="[a-zA-Z0-9_-]+"
                           class="flex-1 px-3 py-2.5 text-sm font-medium bg-transparent outline-none"
                           style="color: var(--text-primary);"
                           @keydown.escape="editing = false; alias = @js($link->alias)">
                    <div class="flex items-center gap-1 pr-2">
                        <button type="button"
                                @click="editing = false; alias = @js($link->alias)"
                                class="text-[11px] px-2.5 py-1 rounded-md hover:bg-white/5"
                                style="color: var(--text-faint);">Cancel</button>
                        <button type="button"
                                class="text-[11px] px-2.5 py-1 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                                @click="fetch('{{ route('user.links.update-alias', $link) }}', {
                                    method: 'PUT',
                                    headers: {
                                        'Content-Type':'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'Accept':'application/json'
                                    },
                                    body: JSON.stringify({alias: alias})
                                }).then(r => r.json()).then(d => {
                                    if (d.success || !d.errors) { editing = false; location.reload(); }
                                    else { alert(d.errors?.alias?.[0] || d.message || 'Error'); }
                                }).catch(() => alert('Error'))">Save</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ADDITIONAL ALIASES --}}
    @if($maxExtras !== 0)
    <div>
        <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Additional aliases</label>

        @if($extras->isNotEmpty())
        <div class="space-y-1.5 mb-2">
            @foreach($extras as $a)
            <div class="flex items-stretch rounded-lg overflow-hidden group" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                <span class="px-3 flex items-center text-xs flex-shrink-0" style="color: var(--text-faint); border-right: 1px solid var(--border-glass); background: var(--bg-glass);">{{ $aliasHost }}/</span>
                <a href="{{ url($a->alias) }}" target="_blank" class="flex-1 px-3 py-2 text-sm truncate" style="color: var(--text-primary);">{{ $a->alias }}</a>
                <div class="flex items-center gap-0.5 pr-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
                    <form method="POST" action="{{ route('user.links.aliases.promote', [$link, $a]) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Make this the primary alias?', message: 'The current primary will become an alternative.', confirmText: 'Make primary', confirmIcon: 'fa-star', iconClass: 'fa-star'})">
                        @csrf
                        <button type="submit" class="px-2 py-1 rounded hover:bg-amber-500/10" style="color: var(--text-faint);" title="Promote to primary">
                            <i class="fas fa-star text-[11px] hover:text-amber-400"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.links.aliases.destroy', [$link, $a]) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this alias?', message: 'Anyone visiting it will get a 404.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1 rounded hover:bg-red-500/10" style="color: var(--text-faint);" title="Delete">
                            <i class="fas fa-trash text-[11px] hover:text-red-400"></i>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        @if($canAddMore)
        <form method="POST" action="{{ route('user.links.aliases.store', $link) }}" class="flex items-stretch rounded-lg overflow-hidden" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
            @csrf
            <span class="px-3 flex items-center text-xs flex-shrink-0" style="color: var(--text-faint); border-right: 1px dashed var(--border-glass); background: var(--bg-glass);">{{ $aliasHost }}/</span>
            <input type="text" name="alias" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9_-]+"
                   placeholder="my-campaign" class="flex-1 px-3 py-2 text-sm bg-transparent outline-none" style="color: var(--text-primary);">
            <button type="submit" class="px-3 text-xs font-semibold whitespace-nowrap hover:bg-blue-500/10" style="color: #90acff;">
                <i class="fas fa-plus text-[10px] mr-1"></i>Add alias
            </button>
        </form>
        @error('alias') <p class="text-red-400 text-[11px] mt-1">{{ $message }}</p> @enderror
        @else
        <p class="text-[11px] mt-2" style="color: var(--text-faint);"><i class="fas fa-info-circle mr-1"></i> Alias limit reached on your current plan.</p>
        @endif
    </div>
    @else
    <p class="text-[11px] mt-2" style="color: #fbbf24;">
        <i class="fas fa-lock mr-1"></i> Additional aliases are not included in your plan.
        <a href="{{ route('user.dashboard') }}" class="underline hover:no-underline">Upgrade</a> to unlock.
    </p>
    @endif

    @if($showHostsHint)
        @include('user.links.partials.platform-hosts-hint', ['primary' => $aliasHost, 'alias' => $link->alias])
    @endif
</div>
