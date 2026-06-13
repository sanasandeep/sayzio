@php
    $catColors = $catColors ?? \App\Modules\User\Models\BiolinkBlock::CATEGORY_COLORS;
    $blockTypes = $blockTypes ?? \App\Modules\User\Models\BiolinkBlock::TYPES;
    $pollTallies = $pollTallies ?? [];
    $cs = $child->settings ?? [];
    $cTypeInfo = $blockTypes[$child->type] ?? ['label' => ucfirst($child->type), 'icon' => 'fa-cube'];
    $cCatColor = $catColors[$cTypeInfo['category'] ?? 'basic'] ?? '#8b5cf6';
    $childSpan = intval($cs['_style']['grid_span'] ?? 12) ?: 12;
@endphp
<div class="child-block-card rounded-lg transition-all hover:bg-white/[0.03]" data-block-id="{{ $child->id }}" style="border: 1px solid var(--border-glass);">
    <div class="flex items-center gap-2 p-2">
        <div class="child-handle cursor-grab" style="color: var(--text-faint);">
            <i class="fas fa-grip-vertical text-[9px]"></i>
        </div>
        <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0" style="background: {{ $cCatColor }}10;">
            <i class="fas {{ $cTypeInfo['icon'] }} text-[9px]" style="color: {{ $cCatColor }};"></i>
        </div>
        <div class="flex-1 min-w-0">
            <span class="text-[11px] font-semibold" style="color: var(--text-primary);">{{ $cTypeInfo['label'] }}</span>
            @if(!$child->is_active)<span class="editor-pill-badge editor-pill-badge--hidden text-[9px] px-1.5 py-0.5 rounded-full ml-1">HIDDEN</span>@endif
            <span class="editor-pill-badge editor-pill-badge--span text-[9px] px-1.5 py-0.5 rounded-md ml-1" style="{{ $childSpan >= 12 ? 'display:none;' : '' }}" data-child-span-badge="{{ $child->id }}">{{ $childSpan }}/12</span>
        </div>
        <div class="flex items-center gap-0.5 flex-shrink-0">
            <button class="block-action-btn edit-btn" style="width:22px;height:22px;" title="Edit" onclick="openEditDrawer({{ $child->id }})"><i class="fas fa-pen" style="font-size:8px;"></i></button>
            <button class="block-action-btn toggle-btn" style="width:22px;height:22px;" title="{{ $child->is_active ? 'Hide' : 'Show' }}" onclick="ajaxToggleBlock(this, '{{ route('user.links.blocks.toggle', [$link, $child]) }}', {{ $child->id }})"><i class="fas {{ $child->is_active ? 'fa-eye' : 'fa-eye-slash' }}" style="font-size:8px;"></i></button>
            <button class="block-action-btn delete-btn" style="width:22px;height:22px;" title="Delete" onclick="ajaxDeleteBlock(this, '{{ route('user.links.blocks.destroy', [$link, $child]) }}', {{ $child->id }})"><i class="fas fa-trash" style="font-size:8px;"></i></button>
        </div>
    </div>
    @if($child->type === 'poll')
        @include('user.links.partials.poll-results-panel', ['block' => $child, 'tally' => $pollTallies[$child->id] ?? null, 'compact' => true])
    @endif
    <div class="child-span-row px-2 pb-1.5">
        <div class="flex items-center gap-1">
            <span class="text-[8px] font-semibold flex-shrink-0" style="color: var(--text-faint);"><i class="fas fa-columns mr-0.5"></i>Width</span>
            <div class="flex gap-[2px] flex-1">
                @foreach([3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $spanVal => $spanLabel)
                <button type="button" class="child-span-btn text-[8px] font-bold px-1 py-0.5 rounded transition-all {{ $childSpan == $spanVal ? 'active' : '' }}"
                        onclick="setChildGridSpan({{ $child->id }}, {{ $spanVal }}, this)"
                        title="{{ $spanLabel }} width">{{ $spanLabel }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>
