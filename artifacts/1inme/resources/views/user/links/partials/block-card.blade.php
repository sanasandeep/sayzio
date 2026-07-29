@php
    $catColors = $catColors ?? \App\Modules\User\Models\BiolinkBlock::CATEGORY_COLORS;
    $blockTypes = $blockTypes ?? \App\Modules\User\Models\BiolinkBlock::TYPES;
    $pollTallies = $pollTallies ?? [];
    $s = $block->settings ?? [];
    $typeInfo = $blockTypes[$block->type] ?? ['label' => ucfirst($block->type), 'icon' => 'fa-cube'];
    $catColor = $catColors[$typeInfo['category'] ?? 'basic'] ?? '#5c83ff';
    $curSpan = intval($s['_style']['grid_span'] ?? 12) ?: 12;
    // Fixed-position blocks: set by admins in the template design session
    // (pin toggle), enforced against users on design-locked pages (no drag,
    // no delete). $isTplDraft = admin design session; $isLockedFixed = a
    // user editing a design-locked page with this block pinned.
    $isTplDraft = is_array(($link->settings ?? [])['_template_draft'] ?? null);
    $isFixed = !empty($s['_fixed']);
    $isLockedFixed = $isFixed && !$isTplDraft && $link->isDesignLocked();
@endphp
<div class="block-card-wrapper" data-block-id="{{ $block->id }}" style="grid-column: span {{ $curSpan }}">
    {{-- Fixed template blocks form a contiguous prefix on a locked page, so
         no insert affordance mid-prefix (the server clamps such inserts to
         after the prefix anyway). --}}
    @unless($isLockedFixed)
    <button type="button" class="insert-block-btn" onclick="openInsertGallery({{ $block->id }})" title="Insert block after this">
        <i class="fas fa-plus"></i>
    </button>
    @endunless
    <div class="block-card {{ $block->isContainer() ? 'card-container-block' : '' }}" data-block-id="{{ $block->id }}" data-grid-span="{{ $curSpan }}" style="{{ $block->is_active ? '' : 'opacity:0.5;' }}">
        <div class="flex items-center gap-2 p-3">
            @if($isLockedFixed)
            <div class="flex-shrink-0 w-5 flex items-center justify-center" title="Fixed by the template — this block can't be moved or removed">
                <i class="fas fa-thumbtack text-[11px]" style="color: var(--text-faint);"></i>
            </div>
            @else
            <div class="drag-handle handle">
                <div class="flex gap-[3px]"><span class="dot"></span><span class="dot"></span></div>
                <div class="flex gap-[3px]"><span class="dot"></span><span class="dot"></span></div>
                <div class="flex gap-[3px]"><span class="dot"></span><span class="dot"></span></div>
            </div>
            @endif

            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $catColor }}12; border: 1px solid {{ $catColor }}25;">
                <i class="fas {{ $typeInfo['icon'] }} text-sm" style="color: {{ $catColor }};"></i>
            </div>

            <div class="flex-1 min-w-0 ml-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $typeInfo['label'] }}</span>
                    @if(!$block->is_active)
                    <span class="editor-pill-badge editor-pill-badge--hidden text-[9px] px-2 py-0.5 rounded-full">HIDDEN</span>
                    @endif
                    @if($isFixed && ($isTplDraft || $isLockedFixed))
                    <span class="editor-pill-badge text-[9px] px-2 py-0.5 rounded-full" data-fixed-badge="{{ $block->id }}" style="background: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.25);"><i class="fas fa-thumbtack mr-0.5"></i>FIXED</span>
                    @endif
                    <span class="grid-span-badge editor-pill-badge editor-pill-badge--span text-[10px] px-2 py-0.5 rounded-md" style="{{ $curSpan >= 12 ? 'display:none;' : '' }}" data-span-badge="{{ $block->id }}">{{ $curSpan }}/12</span>
                </div>
                <div class="block-preview-content mt-0.5">
                    @if($block->isContainer())
                        <i class="fas {{ $typeInfo['icon'] }} text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $block->children->count() }} block(s) inside{{ !empty($s['title']) ? ', ' . $s['title'] : '' }}
                    @elseif(in_array($block->type, ['link', 'link_big']))
                        <i class="fas fa-globe text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['text'] ?? $s['url'] ?? 'No URL set' }}
                    @elseif(in_array($block->type, ['heading', 'heading_logo']))
                        <i class="fas fa-font text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['text'] ?? 'No text' }}
                    @elseif($block->type === 'paragraph' || $block->type === 'paragraph_rich')
                        {{ \Illuminate\Support\Str::limit($s['text'] ?? $s['html'] ?? 'No content', 60) }}
                    @elseif($block->type === 'socials' || $block->type === 'socials_multi' || $block->type === 'socials_custom')
                        <i class="fas fa-users text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ count($s['platforms'] ?? []) }} platforms connected
                    @elseif(in_array($block->type, ['faq', 'faq_v2']))
                        <i class="fas fa-list text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ count($s['items'] ?? []) }} questions
                    @elseif($block->type === 'image')
                        <i class="fas fa-image text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['alt'] ?? ($s['url'] ? 'Image' : 'No image set') }}
                    @elseif(in_array($block->type, ['video', 'header_video', 'youtube']))
                        <i class="fas fa-play text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['url'] ?? $s['video_id'] ?? 'No video' }}
                    @elseif($block->type === 'cta_button')
                        <i class="fas fa-hand-pointer text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['text'] ?? 'Button' }}
                    @elseif($block->type === 'spacer')
                        <i class="fas fa-arrows-alt-v text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['height'] ?? 20 }}px
                    @elseif($block->type === 'divider')
                        <i class="fas fa-minus text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ ucfirst($s['style'] ?? 'solid') }} line
                    @elseif($block->type === 'poll')
                        <i class="fas fa-square-poll-vertical text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ \Illuminate\Support\Str::limit($s['question'] ?? $s['title'] ?? 'Poll', 60) }}
                    @else
                        {{ ucfirst(str_replace('_', ' ', $block->type)) }} block
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-1 flex-shrink-0">
                <button class="block-action-btn edit-btn" title="Edit" onclick="toggleEditInline({{ $block->id }})">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="block-action-btn toggle-btn" title="{{ $block->is_active ? 'Hide' : 'Show' }}" onclick="ajaxToggleBlock(this, '{{ route('user.links.blocks.toggle', [$link, $block]) }}', {{ $block->id }})">
                    <i class="fas {{ $block->is_active ? 'fa-eye' : 'fa-eye-slash' }}"></i>
                </button>
                @if($isTplDraft && $block->parent_id === null)
                <button class="block-action-btn fixed-toggle-btn {{ $isFixed ? 'active' : '' }}" title="{{ $isFixed ? 'Unpin (also unpins blocks below)' : 'Pin position (also pins blocks above)' }}"
                        style="{{ $isFixed ? 'color:#fbbf24;' : '' }}"
                        onclick="ajaxToggleFixed(this, '{{ route('user.links.blocks.toggleFixed', [$link, $block]) }}', {{ $block->id }}, {{ $isFixed ? 'false' : 'true' }})">
                    <i class="fas fa-thumbtack"></i>
                </button>
                @endif
                @if(!$isLockedFixed)
                <button class="block-action-btn delete-btn" title="Delete" onclick="ajaxDeleteBlock(this, '{{ route('user.links.blocks.destroy', [$link, $block]) }}', {{ $block->id }})">
                    <i class="fas fa-trash"></i>
                </button>
                @endif
            </div>
        </div>

        @if($block->type === 'poll')
            @include('user.links.partials.poll-results-panel', ['block' => $block, 'tally' => $pollTallies[$block->id] ?? null])
        @endif

        @if($block->isContainer())
        <div class="card-children-area px-3 pb-3" x-data="{ cardExpanded: true }">
            <div class="rounded-xl overflow-hidden" style="border: 1px dashed var(--border-glass); background: rgba(61,107,255,0.02);">
                <button type="button" @click="cardExpanded = !cardExpanded" class="w-full flex items-center justify-between px-3 py-1.5 text-[10px] font-semibold transition-colors hover:bg-white/[0.02]" style="color: var(--text-faint); background: rgba(61,107,255,0.04);">
                    <span><i class="fas fa-cubes mr-1"></i> Child Blocks (<span data-card-child-count="{{ $block->id }}">{{ $block->children->count() }}</span>)</span>
                    <i class="fas fa-chevron-down transition-transform text-[8px]" :class="cardExpanded ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="cardExpanded" x-collapse>
                    <div class="card-child-list p-2 space-y-1" data-card-id="{{ $block->id }}" style="min-height: 32px;">
                        @forelse($block->children as $child)
                            @include('user.links.partials.block-child-card', ['child' => $child, 'link' => $link, 'blockTypes' => $blockTypes, 'catColors' => $catColors, 'pollTallies' => $pollTallies])
                        @empty
                        <div class="card-empty-hint text-center py-3">
                            <p class="text-[10px]" style="color: var(--text-dimmed);">Drag blocks here or click + below</p>
                        </div>
                        @endforelse
                    </div>
                    <div class="px-2 pb-2">
                        <button type="button" class="w-full py-1.5 rounded-lg text-[10px] font-semibold flex items-center justify-center gap-1 transition-all hover:bg-blue-500/10" style="border: 1px dashed rgba(61,107,255,0.3); color: #90acff;" onclick="openCardGallery({{ $block->id }})">
                            <i class="fas fa-plus text-[8px]"></i> Add block to card
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="grid-span-row px-3 pb-2" data-span-row="{{ $block->id }}">
            <div class="flex items-center gap-1.5">
                <span class="text-[9px] font-semibold flex-shrink-0" style="color: var(--text-faint);"><i class="fas fa-columns mr-1"></i>Width</span>
                <div class="flex gap-[3px] flex-1">
                    @foreach([3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $spanVal => $spanLabel)
                    <button type="button" class="span-btn text-[9px] font-bold px-1.5 py-0.5 rounded transition-all {{ $curSpan == $spanVal ? 'active' : '' }}"
                            onclick="setGridSpan({{ $block->id }}, {{ $spanVal }}, this)"
                            title="{{ $spanLabel }} width ({{ $spanVal }}/12 columns)">{{ $spanLabel }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Inline block editor: the settings form expands here (below the
             card) when the creator clicks Edit. Populated/cleared by
             openEditDrawer()/closeEditDrawerGlobal() in biolink-editor. --}}
        <div class="inline-block-editor" data-inline-editor="{{ $block->id }}" hidden>
            <div class="inline-editor-head">
                <span class="text-[11px] font-bold gradient-text"><i class="fas fa-pen mr-1.5"></i>Edit Block</span>
                <span class="inline-autosave-status text-[10px] font-medium hidden" style="color: var(--text-faint);"></span>
                <button type="button" class="block-action-btn ml-auto" style="color: var(--text-faint);" title="Close" onclick="closeEditDrawerGlobal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="inline-editor-body" data-inline-editor-body="{{ $block->id }}"></div>
        </div>
    </div>
</div>
