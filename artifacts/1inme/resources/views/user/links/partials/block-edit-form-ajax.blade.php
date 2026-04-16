@php
    $blockTypes = $blockTypes ?? \App\Modules\User\Models\BiolinkBlock::TYPES;
    $catColors = [
        'basic' => '#8b5cf6', 'media' => '#3b82f6', 'social' => '#ec4899',
        'music' => '#10b981', 'video_platforms' => '#f59e0b', 'contact' => '#6366f1',
        'interactive' => '#14b8a6', 'business' => '#f97316', 'utility' => '#64748b',
        'layout' => '#8b5cf6', 'integrations' => '#0ea5e9', 'files' => '#78716c',
        'maps' => '#22c55e', 'identity' => '#e11d48',
    ];
    $typeInfo = $blockTypes[$block->type] ?? ['label' => ucfirst($block->type), 'icon' => 'fa-cube', 'category' => 'basic'];
@endphp
<form method="POST" action="{{ route('user.links.blocks.update', [$link, $block]) }}" onsubmit="return ajaxSaveBlock(event, this)">
    @csrf @method('PUT')
    <div class="mb-4">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                <i class="fas {{ $typeInfo['icon'] ?? 'fa-cube' }} text-violet-400 text-sm"></i>
            </div>
            <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $typeInfo['label'] ?? ucfirst($block->type) }}</span>
        </div>
    </div>
    @include('user.links.partials.block-settings-form', ['block' => $block])
    <div class="flex items-center gap-2 mt-6 pt-4" style="border-top: 1px solid var(--border-subtle);">
        <button type="submit" class="btn-primary text-sm py-2.5 px-6 flex-1 justify-center" id="saveBtn_{{ $block->id }}">Save Changes</button>
        <button type="button" onclick="closeEditDrawerGlobal()" class="btn-ghost text-sm py-2.5 px-4">Cancel</button>
    </div>
</form>
