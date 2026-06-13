{{-- Inline "Templates, forms & more" panel — lives inside the Add-blocks
     palette (absolute overlay over .palette-panel). Replaces the old
     full-screen block gallery modal. Generic blocks are added straight from
     the palette body; this panel only hosts the richer pickers: card
     templates, forms, Buzz (social proof) and AI companions. --}}
{{-- Mini template-preview placeholder typography. Renders the real sample
     text from TemplatePreviewLayoutBuilder at thumbnail scale. White on the
     dark theme; dark "ink" under html.light-mode where the pale thumbnail
     background would otherwise wash white text out. Pill/button labels stay
     white in both modes because they sit on a coloured fill. --}}
<style>
    .tpl-prev-heading { font-size: 7px; font-weight: 700; line-height: 1.1; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-name    { font-size: 6.5px; font-weight: 700; line-height: 1.1; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-sub     { font-size: 5.5px; line-height: 1.15; color: rgba(255,255,255,0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-text    { font-size: 5.5px; line-height: 1.3; color: rgba(255,255,255,0.6); display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
    .tpl-prev-list    { font-size: 5.5px; line-height: 1.1; color: rgba(255,255,255,0.65); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-pill    { font-size: 5.5px; font-weight: 700; line-height: 1; }
    html.light-mode .tpl-prev-heading,
    html.light-mode .tpl-prev-name { color: rgba(7,20,55,0.88); }
    html.light-mode .tpl-prev-sub,
    html.light-mode .tpl-prev-text,
    html.light-mode .tpl-prev-list { color: rgba(7,20,55,0.55); }
    /* Loading shimmer behind media/avatar image cells until the thumbnail
       loads (mirrors the mobile picker's ShimmerOverlay). Sits absolutely
       behind the <img>, so it causes no layout shift; removed on (e)load. */
    .tpl-prev-shimmer { position: absolute; inset: 0; border-radius: inherit; overflow: hidden; background: rgba(255,255,255,0.06); z-index: 0; }
    .tpl-prev-shimmer::after { content: ""; position: absolute; inset: 0; transform: translateX(-100%); background: linear-gradient(90deg, transparent, rgba(255,255,255,0.16), transparent); animation: tpl-prev-shimmer-sweep 1.2s ease-in-out infinite; }
    html.light-mode .tpl-prev-shimmer { background: rgba(15,12,30,0.07); }
    html.light-mode .tpl-prev-shimmer::after { background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55), transparent); }
    @keyframes tpl-prev-shimmer-sweep { 100% { transform: translateX(100%); } }
    @media (prefers-reduced-motion: reduce) { .tpl-prev-shimmer::after { animation: none; } }
</style>
<div class="special-panel" x-show="specialOpen" x-cloak
     style="position:absolute; inset:0; z-index:5; display:flex; flex-direction:column; background:var(--bg-sidebar); backdrop-filter:blur(24px) saturate(1.3); -webkit-backdrop-filter:blur(24px) saturate(1.3);"
     @keydown.escape.window="specialOpen = false">
    <div class="px-3 pt-3 pb-2 flex-shrink-0" style="border-bottom:1px solid var(--border-glass);">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" @click="specialOpen = false" class="block-action-btn" style="width:24px;height:24px;color:var(--text-faint);" title="Back to blocks"><i class="fas fa-arrow-left text-[11px]"></i></button>
                <h3 class="text-xs font-bold gradient-text truncate">Templates &amp; more</h3>
            </div>
        </div>
        <p class="text-[10px] mb-2" style="color: var(--text-faint);" x-show="cardParentId" x-cloak><i class="fas fa-layer-group mr-1"></i>Adding into card container</p>
        <p class="text-[10px] mb-2" style="color: var(--text-faint);" x-show="insertAfterId" x-cloak><i class="fas fa-arrow-down mr-1"></i>Inserting after selected block</p>
        <div class="flex items-center gap-1 mb-2 p-1 rounded-xl bg-white/5 border border-white/5">
            <button @click="specialMode = 'templates'; loadCardTemplates();" :class="specialMode === 'templates' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'" class="flex-1 px-2 py-1 text-[10px] font-semibold rounded-lg transition"><i class="fas fa-layer-group mr-1"></i>Cards</button>
            <button @click="specialMode = 'forms'" :class="specialMode === 'forms' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'" class="flex-1 px-2 py-1 text-[10px] font-semibold rounded-lg transition"><i class="fas fa-clipboard-list mr-1"></i>Forms</button>
            <button @click="specialMode = 'buzz'" :class="specialMode === 'buzz' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'" class="flex-1 px-2 py-1 text-[10px] font-semibold rounded-lg transition"><i class="fas fa-bell mr-1"></i>Buzz</button>
            <button @click="specialMode = 'companions'" :class="specialMode === 'companions' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'" class="flex-1 px-2 py-1 text-[10px] font-semibold rounded-lg transition"><i class="fas fa-robot mr-1"></i>AI</button>
        </div>
        <div class="relative">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px]" style="color: var(--text-faint);"></i>
            <input type="text" x-model="specialSearch" placeholder="Search…" class="theme-input w-full pl-7 text-xs py-1.5">
        </div>
    </div>
    <div class="flex-1 overflow-y-auto p-3">
        {{-- FORMS PICKER --}}
        <div x-show="specialMode === 'forms'" x-cloak>
            @if(empty($userForms) || count($userForms) === 0)
                <div class="text-center py-10">
                    <i class="fas fa-clipboard-list text-2xl mb-2" style="color: var(--text-faint);"></i>
                    <p class="text-sm mb-3" style="color: var(--text-muted);">You haven't created any forms yet.</p>
                    <a href="{{ route('user.forms.index') }}" class="inline-block text-xs px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-semibold"><i class="fas fa-plus mr-1"></i>Create your first form</a>
                </div>
            @else
            <div class="grid grid-cols-1 gap-2">
                @foreach($userForms as $f)
                <div x-show="specialSearch === '' || '{{ strtolower(addslashes($f['title'])) }}'.includes(specialSearch.toLowerCase())">
                    <button type="button" class="gallery-block-card w-full text-left" onclick="ajaxAddBlockWithSettings('form', {form_id: {{ $f['id'] }}, height: 600}, '{{ route('user.links.blocks.store', $link) }}')">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(124,58,237,0.12); border: 1px solid rgba(124,58,237,0.20);">
                                <i class="fas fa-clipboard-list text-sm" style="color: #8b5cf6;"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ $f['title'] }}</div>
                                <div class="text-[10px] truncate flex items-center gap-1" style="color: var(--text-faint);">
                                    <span>{{ $f['slug'] }}</span>
                                    @if(!$f['is_active'])<span class="px-1 py-0 rounded bg-red-500/15 text-red-300 text-[8px]">Inactive</span>@endif
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('user.forms.index') }}" class="text-[11px] text-violet-400 hover:text-violet-300"><i class="fas fa-cog mr-1"></i>Manage all forms</a>
            </div>
            @endif
        </div>

        {{-- BUZZ PICKER --}}
        <div x-show="specialMode === 'buzz'" x-cloak>
            @if(empty($userBuzz) || count($userBuzz) === 0)
                <div class="text-center py-10">
                    <i class="fas fa-bell text-2xl mb-2" style="color: var(--text-faint);"></i>
                    <p class="text-sm mb-3" style="color: var(--text-muted);">No Buzz campaigns yet.</p>
                    <a href="{{ route('user.social-proofs.index') }}" class="inline-block text-xs px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-semibold"><i class="fas fa-plus mr-1"></i>Create your first campaign</a>
                </div>
            @else
            <div class="grid grid-cols-1 gap-2">
                @foreach($userBuzz as $b)
                <div x-show="specialSearch === '' || '{{ strtolower(addslashes($b['name'])) }}'.includes(specialSearch.toLowerCase())">
                    <button type="button" class="gallery-block-card w-full text-left" onclick="ajaxAddBlockWithSettings('social_proof', {social_proof_id: {{ $b['id'] }}}, '{{ route('user.links.blocks.store', $link) }}')">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(244,63,94,0.12); border: 1px solid rgba(244,63,94,0.20);">
                                <i class="fas fa-bell text-sm" style="color: #f43f5e;"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ $b['name'] }}</div>
                                <div class="text-[10px] truncate flex items-center gap-1" style="color: var(--text-faint);">
                                    <span>{{ str_replace('_', ' ', $b['type']) }}</span>
                                    @if(!$b['is_active'])<span class="px-1 py-0 rounded bg-red-500/15 text-red-300 text-[8px]">Inactive</span>@endif
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('user.social-proofs.index') }}" class="text-[11px] text-violet-400 hover:text-violet-300"><i class="fas fa-cog mr-1"></i>Manage all campaigns</a>
            </div>
            @endif
        </div>

        {{-- AI COMPANIONS --}}
        <div x-show="specialMode === 'companions'" x-cloak>
            @if(empty($userCompanions) || count($userCompanions) === 0)
                <div class="text-center py-10">
                    <i class="fas fa-robot text-2xl mb-2" style="color: var(--text-faint);"></i>
                    <p class="text-sm mb-3" style="color: var(--text-muted);">No biolink AI Companions yet.</p>
                    <a href="{{ route('user.ai-companions.create') }}?placement=biolink" class="inline-block text-xs px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-semibold"><i class="fas fa-plus mr-1"></i>Create your first Companion</a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-2">
                    @foreach($userCompanions as $c)
                        <div x-show="specialSearch === '' || '{{ strtolower(addslashes($c['name'])) }}'.includes(specialSearch.toLowerCase())">
                            <button type="button" class="gallery-block-card w-full text-left" onclick="ajaxAddBlockWithSettings('ai_companion', {companion_id: {{ $c['id'] }}}, '{{ route('user.links.blocks.store', $link) }}')">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(139,92,246,0.12); border: 1px solid rgba(139,92,246,0.20);">
                                        <i class="fas fa-robot text-sm" style="color: #8b5cf6;"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ $c['name'] }}</div>
                                        <div class="text-[10px] truncate flex items-center gap-1" style="color: var(--text-faint);">
                                            <span class="font-mono">{{ $c['public_id'] }}</span>
                                            @if($c['is_disabled'])<span class="px-1 py-0 rounded bg-red-500/15 text-red-300 text-[8px]">Disabled</span>@endif
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-4">
                    <a href="{{ route('user.ai-companions.index') }}" class="text-[11px] text-violet-400 hover:text-violet-300"><i class="fas fa-cog mr-1"></i>Manage all Companions</a>
                </div>
            @endif
        </div>

        {{-- CARD TEMPLATES --}}
        <div x-show="specialMode === 'templates'" x-cloak>
            <div class="gallery-tabs pb-3" x-show="Object.keys(cardCategories).length">
                <button class="gallery-tab" :class="cardCategory === 'all' ? 'active' : ''" @click="cardCategory = 'all'; loadCardTemplates(true)">All</button>
                <template x-for="(label, key) in cardCategories" :key="key">
                    <button class="gallery-tab" :class="cardCategory === key ? 'active' : ''" @click="cardCategory = key; loadCardTemplates(true)" x-text="label"></button>
                </template>
            </div>
            <div x-show="cardTemplatesLoading" class="text-center py-10" style="color: var(--text-faint);">
                <i class="fas fa-spinner fa-spin text-xl"></i>
            </div>
            <div x-show="!cardTemplatesLoading && cardTemplates.length === 0" class="text-center py-10">
                <i class="fas fa-layer-group text-2xl mb-2" style="color: var(--text-faint);"></i>
                <p class="text-sm" style="color: var(--text-muted);">No matching card templates.</p>
            </div>
            <div class="grid grid-cols-1 gap-3" x-show="!cardTemplatesLoading">
                <template x-for="t in visibleCardTemplates()" :key="t.id">
                    <div class="relative rounded-xl border overflow-visible transition cursor-pointer group" style="border-color: var(--border-glass); background: rgba(124,58,237,0.02);"
                         x-data="{ expanded: false }"
                         @click="t.locked ? (window.location.href = '{{ route('user.upgrade') }}') : applyCardTemplate(t.id)"
                         :class="t.locked ? 'opacity-70 hover:border-amber-500/50' : 'hover:border-violet-500/50'"
                         :title="t.locked ? 'Upgrade to ' + t.plan_tier + ' to use this template' : (t.description || t.name)">
                        <div class="relative overflow-hidden rounded-t-xl" style="background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(139,92,246,0.04));">
                            <template x-if="t.thumbnail_url">
                                <div class="w-full aspect-[4/3]">
                                    <img :src="t.thumbnail_url" :alt="t.name" class="w-full h-full object-cover" loading="lazy">
                                </div>
                            </template>
                            <template x-if="!t.thumbnail_url && (t.preview_layout || []).length">
                                <div class="w-full px-2.5 py-2.5 flex flex-col gap-1.5" style="min-height: 64px; max-height: 340px;">
                                    <template x-for="(row, ri) in t.preview_layout" :key="ri">
                                        <div class="flex gap-1 w-full items-center">
                                            <template x-for="(cell, ci) in row" :key="ci">
                                                <div class="flex items-center justify-center" :style="'flex: ' + cell.span + ' 0 0;'">
                                                    <template x-if="cell.shape === 'heading'">
                                                        <div class="w-full flex flex-col gap-[1px] items-center text-center">
                                                            <template x-if="cell.text">
                                                                <div class="tpl-prev-heading w-full" x-text="cell.text"></div>
                                                            </template>
                                                            <template x-if="!cell.text">
                                                                <div class="rounded-[2px] w-full" :style="'background: ' + cell.bg + '; height: ' + cell.h + 'px;'"></div>
                                                            </template>
                                                            <template x-if="cell.sub">
                                                                <template x-if="cell.sub_text">
                                                                    <div class="tpl-prev-sub w-full" x-text="cell.sub_text"></div>
                                                                </template>
                                                            </template>
                                                            <template x-if="cell.sub && !cell.sub_text">
                                                                <div class="rounded-[2px]" :style="'background: ' + cell.bg + '; height: ' + Math.max(cell.h - 6, 4) + 'px; width: 55%;'"></div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'text_lines'">
                                                        <div class="w-full flex flex-col gap-[2px] justify-center" :style="'min-height: ' + cell.h + 'px;'">
                                                            <template x-if="cell.text">
                                                                <div class="tpl-prev-text" :style="'-webkit-line-clamp: ' + (cell.lines || 2) + ';'" x-text="cell.text"></div>
                                                            </template>
                                                            <template x-if="!cell.text">
                                                                <template x-for="i in (cell.lines || 2)" :key="i">
                                                                    <div class="rounded-[2px]" :style="'background: ' + cell.bg + '; height: 3px; width: ' + (i === (cell.lines || 2) ? '60%' : '100%') + ';'"></div>
                                                                </template>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'pill'">
                                                        <div class="w-full rounded-full flex items-center justify-center gap-1 px-1.5 text-white/95 tpl-prev-pill" :style="'background: ' + cell.bg + '; min-height: ' + cell.h + 'px;'">
                                                            <span x-show="cell.text" class="truncate" x-text="cell.text"></span>
                                                            <i x-show="cell.icon" :class="'fas ' + cell.icon" style="font-size: 6px;"></i>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'avatar'">
                                                        <div class="w-full flex items-center gap-1.5" :style="'min-height: ' + cell.h + 'px;'">
                                                            <template x-if="cell.img">
                                                                <div class="relative rounded-full overflow-hidden shrink-0" :style="'width: ' + Math.max(cell.h - 8, 14) + 'px; height: ' + Math.max(cell.h - 8, 14) + 'px;'">
                                                                    <div class="tpl-prev-shimmer"></div>
                                                                    <img :src="cell.img" alt="" loading="lazy" class="relative w-full h-full object-cover" onload="this.previousElementSibling && this.previousElementSibling.remove()" onerror="this.previousElementSibling && this.previousElementSibling.remove()">
                                                                </div>
                                                            </template>
                                                            <template x-if="!cell.img">
                                                                <div class="rounded-full flex items-center justify-center text-white/90 shrink-0" :style="'background: ' + cell.bg + '; width: ' + Math.max(cell.h - 8, 14) + 'px; height: ' + Math.max(cell.h - 8, 14) + 'px;'">
                                                                    <i x-show="cell.icon" :class="'fas ' + cell.icon" style="font-size: 7px;"></i>
                                                                </div>
                                                            </template>
                                                            <div class="flex-1 flex flex-col gap-[1px] min-w-0">
                                                                <template x-if="cell.text">
                                                                    <div class="tpl-prev-name" x-text="cell.text"></div>
                                                                </template>
                                                                <template x-if="!cell.text">
                                                                    <div class="rounded-[2px]" :style="'background: rgba(255,255,255,0.55); height: 4px; width: 70%;'"></div>
                                                                </template>
                                                                <template x-if="cell.sub_text">
                                                                    <div class="tpl-prev-sub" x-text="cell.sub_text"></div>
                                                                </template>
                                                                <template x-if="!cell.sub_text">
                                                                    <div class="rounded-[2px]" :style="'background: rgba(255,255,255,0.30); height: 3px; width: 50%;'"></div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'media'">
                                                        <div class="w-full rounded-[3px] relative overflow-hidden flex items-center justify-center text-white/85" :style="'background: ' + cell.bg + '; min-height: ' + cell.h + 'px; height: ' + cell.h + 'px;'">
                                                            <template x-if="cell.img">
                                                                <div class="absolute inset-0">
                                                                    <div class="tpl-prev-shimmer"></div>
                                                                    <img :src="cell.img" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover" onload="this.previousElementSibling && this.previousElementSibling.remove()" onerror="this.previousElementSibling && this.previousElementSibling.remove()">
                                                                </div>
                                                            </template>
                                                            <i x-show="cell.play || !cell.img" :class="'fas ' + (cell.play ? 'fa-play' : cell.icon)" class="relative" :style="'font-size: 11px;' + (cell.img ? ' text-shadow: 0 1px 3px rgba(0,0,0,0.6);' : '')"></i>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'dot_row'">
                                                        <div class="w-full flex items-center justify-center gap-1" :style="'min-height: ' + cell.h + 'px;'">
                                                            <template x-for="i in (cell.dots || 5)" :key="i">
                                                                <div class="rounded-full" :style="'background: ' + cell.bg + '; width: 5px; height: 5px;'"></div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'form'">
                                                        <div class="w-full flex flex-col gap-1 justify-center" :style="'min-height: ' + cell.h + 'px;'">
                                                            <template x-for="i in (cell.lines || 1)" :key="i">
                                                                <div class="rounded-[2px] w-full" :style="'background: ' + cell.bg + '; height: 5px;'"></div>
                                                            </template>
                                                            <div class="rounded-full mx-auto flex items-center justify-center text-white/95 tpl-prev-pill px-1.5" :style="'background: ' + (cell.btn_bg || 'rgba(139,92,246,0.85)') + '; min-height: 7px; width: 70%;'">
                                                                <span x-show="cell.text" class="truncate" x-text="cell.text"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'list_rows'">
                                                        <div class="w-full flex flex-col gap-1 justify-center" :style="'min-height: ' + cell.h + 'px;'">
                                                            <template x-for="(item, li) in (cell.items || [null, null, null]).slice(0, cell.lines || 3)" :key="li">
                                                                <div class="flex items-center gap-1 w-full">
                                                                    <div class="rounded-full shrink-0" :style="'background: ' + cell.bg + '; width: 3px; height: 3px;'"></div>
                                                                    <template x-if="item">
                                                                        <div class="tpl-prev-list flex-1" x-text="item"></div>
                                                                    </template>
                                                                    <template x-if="!item">
                                                                        <div class="rounded-[2px] flex-1" :style="'background: ' + cell.bg + '; height: 3px;'"></div>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="cell.shape === 'hairline'">
                                                        <div class="w-full rounded-[2px]" :style="'background: ' + cell.bg + '; height: ' + cell.h + 'px;'"></div>
                                                    </template>
                                                    <template x-if="cell.shape === 'spacer'">
                                                        <div class="w-full" :style="'min-height: ' + cell.h + 'px;'"></div>
                                                    </template>
                                                    <template x-if="cell.shape === 'badge'">
                                                        <div class="rounded-full mx-auto" :style="'background: ' + cell.bg + '; height: ' + cell.h + 'px; width: 50%;'"></div>
                                                    </template>
                                                    <template x-if="!cell.shape || cell.shape === 'tile'">
                                                        <div class="w-full rounded-[3px] flex items-center justify-center text-white/70" :style="'background: ' + cell.bg + '; min-height: ' + cell.h + 'px;'">
                                                            <i x-show="cell.icon" :class="'fas ' + cell.icon" style="font-size: 8px;"></i>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!t.thumbnail_url && !(t.preview_layout || []).length">
                                <div class="aspect-[4/2] w-full flex items-center justify-center">
                                    <i class="fas fa-square-poll-vertical text-2xl text-violet-300/60"></i>
                                </div>
                            </template>
                            <div x-show="t.locked" class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i><span x-text="t.plan_tier"></span></div>
                        </div>
                        <div class="p-3">
                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                <div class="text-xs font-semibold flex-1 min-w-0" style="color: var(--text-primary);" x-text="t.name"></div>
                                <span class="shrink-0 text-[8.5px] uppercase tracking-wide px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      style="color: var(--text-faint); background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass);"
                                      x-text="t.category_label || t.category"></span>
                            </div>
                            <div class="flex flex-wrap gap-1 min-h-[18px]"
                                 x-data="{
                                    chips() {
                                        const groups = new Map();
                                        for (const c of (t.children || [])) {
                                            const key = c.type;
                                            if (!groups.has(key)) {
                                                groups.set(key, { icon: c.icon || 'fa-cube', label: c.label, count: 0 });
                                            }
                                            groups.get(key).count += 1;
                                        }
                                        return Array.from(groups.values());
                                    }
                                 }">
                                <template x-if="(t.children || []).length">
                                    <template x-for="(chip, i) in chips().slice(0, 3)" :key="i">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9.5px] font-medium"
                                              style="background: rgba(139,92,246,0.10); color: var(--text-primary); border: 1px solid rgba(139,92,246,0.18);">
                                            <i :class="'fas ' + chip.icon" class="text-violet-400" style="font-size: 8px;"></i>
                                            <span x-text="chip.count > 1 ? (chip.count + ' ' + chip.label + 's') : chip.label"></span>
                                        </span>
                                    </template>
                                </template>
                                <template x-if="(t.children || []).length && chips().length > 3">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9.5px] font-semibold text-violet-400/90"
                                          x-text="'+' + (chips().length - 3) + ' more'"></span>
                                </template>
                                <template x-if="!(t.children || []).length">
                                    <span class="text-[10px]" style="color: var(--text-muted);" x-text="(t.children_count || 0) + ' blocks'"></span>
                                </template>
                            </div>
                            <button type="button"
                                    class="mt-2 text-[10px] text-violet-400 hover:text-violet-300"
                                    x-show="(t.children || []).length"
                                    @click.stop="expanded = !expanded"
                                    x-text="expanded ? 'Hide details' : 'See what\'s inside'"></button>
                        </div>
                        <div class="px-3 pb-3 -mt-1" x-show="expanded" x-cloak @click.stop>
                            <ul class="space-y-1 pt-2 border-t" style="border-color: var(--border-glass);">
                                <template x-for="(c, i) in (t.children || [])" :key="i">
                                    <li class="flex items-start gap-2 text-[11px]" style="color: var(--text-primary);">
                                        <i :class="'fas ' + (c.icon || 'fa-cube')" class="text-violet-400 mt-0.5 w-3 text-center"></i>
                                        <span class="flex-1 min-w-0">
                                            <span class="font-semibold" x-text="c.label"></span>
                                            <template x-if="c.preview">
                                                <span style="color: var(--text-muted);" x-text="' — ' + c.preview"></span>
                                            </template>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
            </div>
            <div class="text-center mt-4" x-show="!cardTemplatesLoading && visibleCardTemplates().length < cardTemplates.length">
                <button type="button" @click="cardTemplatesLimit += 24" class="text-[11px] text-violet-400 hover:text-violet-300">
                    Show more (<span x-text="cardTemplates.length - cardTemplatesLimit"></span> left)
                </button>
            </div>
        </div>
    </div>
</div>
